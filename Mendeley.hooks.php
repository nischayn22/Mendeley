<?php

class MendeleyHooks {

	/**
	 * Sets up the parser function
	 *
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		$parser->setFunctionHook(
			'mendeley',
			'MendeleyHooks::mendeley'
		);

	}

	public static function onFormPrinterSetup( &$pfFormPrinter ) {
		$pfFormPrinter->registerInputType( 'PFMendeleyInput' );
	}

	/**
	 * Handles the mendeley parser function
	 *
	 * @param Parser $parser Unused
	 * @return string
	 */
	public static function mendeley( Parser &$parser ) {
		$options = self::extractOptions( array_slice( func_get_args(), 1 ) );

		$parameter = $options['parameter'];

		$document_key = '';
		if ( isset( $options['doi'] ) ) {
			$document_key = $options['doi'];
		} else {
			$document_key = $options['id'];
		}

		// Check cache first
		$cacheProp = self::getFromCache( $parser, $document_key );
		if ( array_key_exists( $document_key, $cacheProp ) && ( wfTimestamp() - $cacheProp[$document_key]['ts'] ) < 3 * 24 * 3600 ) {
			if ( isset( $cacheProp[$document_key]['title'] ) ) {
				$serialized = serialize( $cacheProp );
				$parser->getOutput()->setProperty( 'MendeleyProperties', $serialized );
				return self::getArrayElementFromPath( $cacheProp[$document_key], $parameter );
			}
		}

		$access_token = self::getAccessToken();

		$result = array();
		if ( isset( $options['doi'] ) ) {
			$result = httpRequest( "https://api.mendeley.com/catalog?doi=". $options['doi'] ."&access_token=$access_token&view=all" );
			$result = json_decode( $result, true )[0];
		} else {
			$result = httpRequest( "https://api.mendeley.com/catalog/". $options['id'] ."?access_token=$access_token&view=all" );
			$result = json_decode( $result, true );
		}

		if ( empty( $result ) ) {
			return '';
		}
		// Store in Cache
		$result['ts'] = wfTimestamp();
		$cacheProp[$document_key] = $result;
        $serialized = serialize( $cacheProp );
        $parser->getOutput()->setProperty( 'MendeleyProperties', $serialized );

		return self::getArrayElementFromPath( $result, $parameter );
	}

	public static function getAccessToken() {
		global $wgMendeleyConsumerKey, $wgMendeleyConsumerSecret;
		$result = httpRequest( "https://api.mendeley.com/oauth/token", "grant_type=client_credentials&scope=all&client_id=$wgMendeleyConsumerKey&client_secret=$wgMendeleyConsumerSecret" );
		return json_decode( $result )->access_token;
	}

	public static function getFromCache( $parser, $doi ) {
        $pageId = $parser->getTitle()->getArticleID();
		$dbr = wfGetDB( DB_REPLICA );
		$propValue = $dbr->selectField( 'page_props', // table to use
			'pp_value', // Field to select
			array( 'pp_page' => $pageId, 'pp_propname' => "MendeleyProperties" ), // where conditions
			__METHOD__
		);
		if ( $propValue !== false ) {
			return unserialize( $propValue );
		}

		// Try the parser object itself
		$propValue = $parser->getOutput()->getProperty( 'MendeleyProperties' );
		if ( $propValue !== false ) {
			return unserialize( $propValue );
		}

		return array();
	}

	/**
	 * Get an array element from a (potentially) muti-dimensional array based on a string path,
	 * with each array element separated by a delimiter
	 *
	 * Example: To access $array['stuff']['vehicles']['car'], the path would be 'stuff;vehicles;car'
	 *  (assuming the default delimiter)
	 *
	 * @param array $array
	 * @param string $path
	 * @param string $delimiter
	 * @return string
	 */
	private static function getArrayElementFromPath( array $array, $path, $delimiter = ';' ) {
		# http://stackoverflow.com/a/2951721
		$paths = explode( $delimiter, $path );
		foreach ( $paths as $index ) {
			if ( array_keys($array) === range(0, count($array) - 1) ) {
				// if we have reached a numeric key just take the values from each array item, concatenate and return it.
				$output = array();
				foreach( $array as $array_item ) {
					if ( isset( $array_item[$index] ) ) {
						$output[] = $array_item[$index];
					}
				}
				return implode( ',', $output );
			} else {
				if ( isset( $array[$index] ) ) {
					$array = $array[$index];
				} else {
					return '';
				}
			}
		}
		return $array;
	}


	public static function extractOptions( array $options ) {
		$results = array();

		foreach ( $options as $option ) {
			$pair = explode( '=', $option, 2 );
			if ( count( $pair ) === 2 ) {
				$name = trim( $pair[0] );
				$value = trim( $pair[1] );
				$results[$name] = $value;
			}

			if ( count( $pair ) === 1 ) {
				$name = trim( $pair[0] );
				$results[$name] = true;
			}
		}
		return $results;
	}
}

function httpRequest($url, $post = "", $headers = array()) {
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
        if (!empty($post)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
			curl_setopt($ch, CURLOPT_POST, 1);
		}
        if (!empty($headers))
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);

        if (!$result) {
            throw new Exception("Error getting data from server: " . curl_error($ch));
        }

        curl_close($ch);
    }
    catch (Exception $e) {
		echo 'Caught exception: ', $e->getMessage(), "\n";
		return null;
    }
    return $result;
}
