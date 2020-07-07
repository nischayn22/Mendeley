<?php

class Mendeley {

	private static $instance;
	private $tokenFails = 0;

	public static function getInstance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Imports a group from Mendeley
	 *
	 * @param string $group_id
	 *
	 * @return Title[]
	 * @throws MWContentSerializationException
	 * @throws MWException
	 */
	public function importGroup( $group_id ) {
		global $wgMendeleyTemplate,
			   $wgMendeleyTemplateFields,
			   $wgMendeleyTemplateFieldsMapDelimiter,
			   $wgMendeleyPageFormula;

		$pages = 0;
		$pagesLinks = [];
		$access_token = $this->getAccessToken();
		$responseHeaders = [];
		$result = $this->httpRequest(
			"https://api.mendeley.com/documents" .
			"?access_token=$access_token" .
			"&group_id=$group_id" .
			"&view=all" .
			"&limit=50",
			'',
			array(),
			$responseHeaders
		);
		$result = json_decode( $result, true );

		// Token has expired: oauth/TOKEN_EXPIRED
		// This is necessary because we don't know initial token issue timestamp
		if ( isset( $result['errorId'] ) ) {
			// refresh token
			wfDebugLog( 'Mendeley', $result['message'] );
			$this->refreshAccessToken();
			$access_token = $this->getAccessToken();
			$responseHeaders = [];
			$result = $this->httpRequest(
				"https://api.mendeley.com/documents" .
				"?access_token=$access_token" .
				"&group_id=$group_id" .
				"&view=all" .
				"&limit=50",
				'',
				array(),
				$responseHeaders
			);
			$result = json_decode( $result, true );
		}

		// Fail after first refresh try or if token is not refreshable
		if ( isset( $result['errorId'] ) ) {
			throw new Exception($result['message']);
		}

		while ( true ) {
			foreach ( $result as $result_row ) {

				$row = $this->array_flatten( $result_row );
				$text = '{{' . $wgMendeleyTemplate . "\n";
				foreach ( $wgMendeleyTemplateFields as $property => $field ) {
					if ( !isset($row[$property]) ) {
						wfDebugLog( 'Mendeley', 'field '. $property . ' not found!' );
						continue;
					}
					if ( strpos( $field, '@' ) === 0 ) {
						$field = str_replace( '@', '', $field );
						// special case for deep arrays
						if( is_array( $row[$property] ) ) {
							if ( count($row[$property]) && is_array( $row[$property][0] ) ) {
								$text .= '|' . $field . '=' .
										 implode( $wgMendeleyTemplateFieldsMapDelimiter, array_map( function ( $item ) {
											 return implode( ' ', $item );
										 }, $row[$property] ) ) . "\n";
							} else {
								$text .= '|' . $field . '=' .
										 implode( $wgMendeleyTemplateFieldsMapDelimiter, $row[$property] ) .
										 "\n";
							}
						}else{
							// fallback to normal processing
							$text .= '|' . $field . '=' . $row[$property] . "\n";
						}
					} else {
						$text .= '|' . $field . '=' . $row[$property] . "\n";
					}
				}
				$text .= '}}';

				$pagename = $result_row['id'];

				// Replace tokens in page formula
				if ( $wgMendeleyPageFormula ) {
					$keys = array_map( function ( $key ) {
						return '<' . $key . '>';
					}, array_keys( $row ) );
					$replacements = array_map( function ( $r ) use ( $wgMendeleyTemplateFieldsMapDelimiter ) {
						if ( is_array( $r ) ) {
							if( !count( $r) ) {
								return '';
							}
							if ( is_array( $r[0] ) ) {
								if( !count( $r[0] ) ) {
									return '';
								}
								return implode( ' ', $r[0] );
							}
							return $r[0];
						}
						return $r;
					}, array_values( $row ) );
					$pagename = str_ireplace( $keys, $replacements, $wgMendeleyPageFormula );
				}

				//die();

				$title = Title::newFromText( $pagename );
				$wikiPage = new WikiPage( $title );
				$content = ContentHandler::makeContent( $text, $title );
				$wikiPage->doEditContent( $content, "Importing document found in group" );
				$pagesLinks[] = $title;
				$pages ++;
			}
			$nextLink = $this->getPaginationLink( $responseHeaders );
			if ( $nextLink ) {
				$result = $this->httpRequest( $nextLink, '', array(), $responseHeaders );
			} else {
				break;
			}
		}

		return $pagesLinks;
	}

	private function getPaginationLink( array $responseHeaders, $rel = 'next' ) {
		foreach ( $responseHeaders as $value ) {
			if ( strncmp( $value, 'Link:', 5 ) === 0 ) {
				if ( preg_match( '/Link: <([^>]*)>.*rel="([^"]*)"/', $value, $matches ) ) {
					if ( $matches[2] === $rel ) {
						return $matches[1];
					}
				}
			}
		}
		return null;
	}

	/**
	 * Flattens the multi-dimensional array, folds the prop names recursively to a/b/c..
	 *
	 * @param $array
	 * @param string $prefix
	 *
	 * @return array|false
	 */
	private function array_flatten( $array, $prefix = '' ) {
		if ( !is_array( $array ) ) {
			return false;
		}
		$result = array();
		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) && $this->is_assoc( $value ) ) {
				$result = array_merge( $result, $this->array_flatten( $value, $key ) );
			} else {
				$result = array_merge( $result, array(
					( $prefix ? $prefix . '/' : '' ) . $key => $value
				) );
			}
		}
		return $result;
	}

	/**
	 * Tests if the array is an associative array
	 *
	 * @param array $arr
	 *
	 * @return bool
	 */
	private function is_assoc( array $arr ) {
		if ( array() === $arr ) {
			return false;
		}
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}

	public function getAccessToken() {
		global $wgMendeleyConsumerKey, $wgMendeleyConsumerSecret,
			   $wgMendeleyToken, $wgMemCachedServers, $wgObjectCaches;

		// test against $wgMendeleyToken to ensure we want to use the auth code flow
		if ( !empty($wgMendeleyToken) ) {
			if ( !count($wgMemCachedServers) && !isset($wgObjectCaches['redis']) ) {
				throw new Exception(
					"The Mendeley extension is configured to use Authorization Code " .
					"flow but neither Memcached nor Redis cache is found!"
				);
			}
			return $this->getToken( 'access' );
		}
		$result = $this->httpRequest( "https://api.mendeley.com/oauth/token", "grant_type=client_credentials&scope=all&client_id=$wgMendeleyConsumerKey&client_secret=$wgMendeleyConsumerSecret" );
		return json_decode( $result )->access_token;
	}

	/**
	 * Refreshes access token and accuires new refresh token
	 * @return array|false
	 */
	public function refreshAccessToken() {
		global $wgMendeleyRefreshToken, $wgMendeleyRedirectUrl,
			   $wgMendeleyConsumerKey, $wgMendeleyConsumerSecret;

		// check for refresh token setting presence to ensure it was initially set
		if ( !$wgMendeleyRefreshToken || !$wgMendeleyRedirectUrl ) {
			return false;
		}

		$result = $this->httpRequest(
			"https://api.mendeley.com/oauth/token",
			"grant_type=refresh_token&refresh_token="
			. $this->getToken( 'refresh' )
			. '&client_id='
			. $wgMendeleyConsumerKey
			. '&client_secret=' . $wgMendeleyConsumerSecret
		);
		$result = json_decode( $result );
		if ( !$result || isset( $result->message ) ) {
			throw new Exception("Unable to refresh access token! " . $result->message );
		}

		$this->setToken( $result->access_token, 'access' );
		$this->setToken( $result->refresh_token, 'refresh' );

		return true;
	}

	public function getToken( $token = 'access' ) {
		global $wgMendeleyRefreshToken, $wgMendeleyToken;

		$cache = wfGetCache( CACHE_ANYTHING );
		$key = wfMemcKey( 'mendeley_token_' . $token );
		$keyTs = wfMemcKey( 'mendeley_token_ts_' . $token );
		$ts = $cache->get( $keyTs );
		$result = $cache->get( $key );
		if( $result ) {
			if( $token == 'access' && $ts && time() - $ts >= 3600 ) {
				$this->refreshAccessToken();
				return $this->getToken( $token );
			}
			return $result;
		}
		$token = $token == 'access' ? $wgMendeleyToken : $wgMendeleyRefreshToken;
		$cache->set( $key, $token );
		// We don't know initial token TS so not using setToken
		return $token;
	}

	public function setToken( $value, $token = 'access' ) {
		$cache = wfGetCache( CACHE_ANYTHING );
		$key = wfMemcKey( 'mendeley_token_' . $token );
		$keyTs = wfMemcKey( 'mendeley_token_ts_' . $token );
		$cache->set( $key, $value );
		$cache->set( $keyTs, time() );
	}

	public function httpRequest($url, $post = "", $headers = array(), &$responseHeaders = array() ) {
		try {
			$ch = curl_init();
			//Change the user agent below suitably
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.9) Gecko/20071025 Firefox/2.0.0.9');
			curl_setopt($ch, CURLOPT_URL, ($url));
			curl_setopt($ch, CURLOPT_ENCODING, "UTF-8");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_COOKIESESSION, false);
			curl_setopt($ch, CURLOPT_TIMEOUT, 20);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			#curl_setopt($ch, CURLOPT_VERBOSE, 1);
			curl_setopt($ch, CURLOPT_HEADER, 1);

			if (!empty($post)) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
				curl_setopt($ch, CURLOPT_POST, 1);
			}
			if (!empty($headers)) {
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			}
			$response = curl_exec($ch);

			if (!$response) {
				throw new Exception("Error getting data from server: " . curl_error($ch));
			}
			$header_size = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
			$responseHeaders = explode( "\r\n", substr( $response, 0, $header_size ) );
			$body = substr( $response, $header_size );

			curl_close($ch);
		}
		catch (Exception $e) {
			echo 'Caught exception: ', $e->getMessage(), "\n";
			return null;
		}
		return $body;
	}

}
