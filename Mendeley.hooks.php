<?php

class MendeleyHooks {

	/**
	 * Sets up the parser function
	 *
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		$parser->setFunctionHook( 'mendeley',
			'MendeleyHooks::mendeley' );
	}

	/**
	 * Handles the mendeley parser function
	 *
	 * @param Parser $parser Unused
	 * @param string $doi
	 * @param string $parameter
	 * @return string
	 */
	public static function mendeley( Parser &$parser, $doi, $parameter ) {
		global $wgMendeleyConsumerKey, $wgMendeleyConsumerSecret;

		// Check cache first
		$cacheProp = self::getFromCache( $parser, $doi );
		if ( array_key_exists( $doi, $cacheProp ) && ( wfTimestamp() - $cacheProp[$doi]['ts'] ) < 3 * 24 * 3600 ) {
			$serialized = serialize( $cacheProp );
			$parser->getOutput()->setProperty( 'MendeleyProperties', $serialized );
			return self::getArrayElementFromPath( $cacheProp[$doi], $parameter );
		}

		$result = httpRequest( "https://api.mendeley.com/oauth/token", "grant_type=client_credentials&scope=all&client_id=$wgMendeleyConsumerKey&client_secret=$wgMendeleyConsumerSecret" );
		$access_token = json_decode( $result )->access_token;
		$result = httpRequest( "https://api.mendeley.com/catalog?doi=$doi&access_token=$access_token&view=all" );
		$result = json_decode( $result, true )[0];

		// Store in Cache
		$result['ts'] = wfTimestamp();
		$cacheProp[$doi] = $result;
        $serialized = serialize( $cacheProp );
        $parser->getOutput()->setProperty( 'MendeleyProperties', $serialized );

		return self::getArrayElementFromPath( $result, $parameter );
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
