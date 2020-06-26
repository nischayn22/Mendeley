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
		$pfFormPrinter->registerInputType( 'PFMendeleyInputDOI' );
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

		// CACHE_DB is slow but we can cache more items - which is likely what we want
		$cache_object = ObjectCache::getInstance( CACHE_DB );

		// Check cache first
		$cacheProp = unserialize( $cache_object->get( $document_key ) );

		if ( $cacheProp && !isset( $cacheProp['errorId'] ) ) {
			return self::getArrayElementFromPath( $cacheProp, $parameter );
		}
		$access_token = self::getAccessToken();

		$result = array();
		if ( isset( $options['doi'] ) ) {
			$result = self::httpRequest( "https://api.mendeley.com/catalog?doi=". $options['doi'] ."&access_token=$access_token&view=all" );
			$result = json_decode( $result, true )[0];
		} else {
			$result = self::httpRequest( "https://api.mendeley.com/catalog/". $options['id'] ."?access_token=$access_token&view=all" );
			$result = json_decode( $result, true );
		}

		if ( empty( $result ) || isset( $result['errorId'] ) ) {
			return '';
		}

		// Store in Cache
        $serialized = serialize( $result );
		$cache_object->set( $document_key, $serialized, 5 * 24 * 60 * 60 );

		return self::getArrayElementFromPath( $result, $parameter );
	}

	public static function getAccessToken() {
		global $wgMendeleyConsumerKey, $wgMendeleyConsumerSecret;
		$result = self::httpRequest( "https://api.mendeley.com/oauth/token", "grant_type=client_credentials&scope=all&client_id=$wgMendeleyConsumerKey&client_secret=$wgMendeleyConsumerSecret" );
		return json_decode( $result )->access_token;
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
				return strip_tags( implode( ',', $output ) );
			} else {
				if ( isset( $array[$index] ) ) {
					$array = $array[$index];
				} else {
					return '';
				}
			}
		}
		return strip_tags( implode( ',', (array)$array ) );
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

	public static function httpRequest($url, $post = "", $headers = array()) {
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
			curl_setopt($ch, CURLOPT_VERBOSE, 1);

			if (!empty($post)) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
				curl_setopt($ch, CURLOPT_POST, 1);
			}
			if (!empty($headers)) {
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			}
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

}
