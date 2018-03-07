<?php

class Mendeley {
	function __construct() {
		global $wgMendeleyConsumerKey, $wgMendeleyConsumerSecret;
		$this->oauth = new OAuth($wgMendeleyConsumerKey, $wgMendeleyConsumerSecret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
		$this->oauth->enableDebug(); // for debugging
	}

	function fetch($url, $params = null, $method = 'GET', $headers = array()) { 
		try {
			$this->oauth->fetch('http://api.mendeley.com/oapi' . $url, $params, constant('OAUTH_HTTP_METHOD_' . $method), $headers);
			return json_decode($this->oauth->getLastResponse(), true);
		}
		catch(OAuthException $e) {
			print_r($this->oauth->debugInfo); // for debugging
		}
	}

	// http://apidocs.mendeley.com/home/public-resources/search-details
	function document($id, $type = null) {
		if ($type == 'doi') $id = str_replace('/', '%2F', $id); // workaround
		return $this->fetch('/documents/details/' . rawurlencode($id), array('type' => $type));
	}
}
