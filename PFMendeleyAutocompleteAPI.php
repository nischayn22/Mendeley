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
		$term = urlencode( $this->getMain()->getVal('term') );

		$access_token = MendeleyHooks::getAccessToken();

		$result = MendeleyHooks::httpRequest( "https://api.mendeley.com/search/catalog?query==$term&access_token=$access_token&view=all&limit=20" );
		$result = json_decode( $result, true );
		if ( empty( $result ) ) {
			$result = MendeleyHooks::httpRequest( "https://api.mendeley.com/catalog?doi=". $term ."&access_token=$access_token&view=all" );
			$result = json_decode( $result, true );
		}

		$return_arr = array();
		foreach( $result as $row ) {
			$row_arr = array();
			$row_arr['id'] = $row['id'];
			$row_arr['label'] = strlen( $row['title'] ) > 50 ? substr( $row['title'] ,0 ,50 ) . "..." : $row['title'];
			$row_arr['value'] = $row['title'];
			$row_arr['year'] = $row['year'];
			$authors = array();
			foreach( $row['authors'] as $author ) {
				$authors[] = $author['first_name'] . ' ' . $author['last_name'];
			}
			$row_arr['authors'] = implode( ', ', $authors );
			$row_arr['abstract'] = strlen( $row['abstract'] ) > 300 ? substr( $row['abstract'], 0, 300 ) . "..." : $row['abstract'];
			array_push($return_arr, $row_arr);
		}
		$this->getResult()->addValue( 'result', "autocomplete_results", $return_arr );
	}
}