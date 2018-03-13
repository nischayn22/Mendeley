<?php

/**
 * Provides autocomplete for Mendeley documents.
 *
 *
 * @author Nischay Nahata
 */
class PFMendeleyAutocompleteAPI extends ApiBase {

	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName );
	}

	public function execute() {
		$term = $this->getMain()->getVal('term');

		$access_token = MendeleyHooks::getAccessToken();

		$result = httpRequest( "https://api.mendeley.com/search/catalog?query==$term&access_token=$access_token&view=all&limit=20" );
		$result = json_decode( $result, true );

		$return_arr = array();
		foreach( $result as $row ) {
			$row_arr = array();
			$row_arr['id'] = $row['id'];
			$row_arr['label'] = strlen( $row['title'] ) > 50 ? substr( $row['title'] ,0 ,50 )."..." : $row['title'];
			$row_arr['full_label'] = $row['title'];
			array_push($return_arr, $row_arr);
		}
		$this->getResult()->addValue( 'result', "autcomplete_results", $return_arr );
	}
}