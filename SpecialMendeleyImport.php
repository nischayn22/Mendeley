<?php

class SpecialMendeleyImport extends SpecialPage {

	public function __construct() {
		parent::__construct( 'MendeleyImport', 'mendeleyimport' );
	}

	/**
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$request = $this->getRequest();
		$out = $this->getOutput();
	
		$formOpts = [
			'id' => 'menedeley_import',
			'method' => 'post',
			"enctype" => "multipart/form-data",
			'action' => $this->getTitle()->getFullUrl()
		];

		$out->addHTML(
			Html::openElement( 'form', $formOpts ) . "<br>" .
			Html::label( "Enter Mendeley Group ID","", array( "for" => "mendeley_group_id" ) ) . "<br>" .
			Html::element( 'input', array( "id" => "mendeley_group_id", "name" => "mendeley_group_id", "type" => "text" ) ) . "<br><br>"
		);

		$out->addHTML(
			Html::submitButton( "Submit", array() ) .
			Html::closeElement( 'form' )
		);

		if ( $request->getVal( "mendeley_group_id" ) ) {
			$this->handleImport( $request->getVal( "mendeley_group_id" ) );
		}
	}

	public function handleImport( $group_id ) {
		$request = $this->getRequest();
		$out = $this->getOutput();

		$access_token = MendeleyHooks::getAccessToken();

		$result = MendeleyHooks::httpRequest( "https://api.mendeley.com/documents?access_token=$access_token&group_id=$group_id&view=all&limit=50" );
		$result = json_decode( $result, true );
		foreach( $result as $result_row ) {
			$text = '
{{Article
|Type='. $result_row['type'] .'
|Title='. $result_row['title'] .'
|Abstract='. $result_row['abstract'] .'
|Accessed='. $result_row['accessed'] .'
|Authors='. implode( ',', array_map( function ( $author ) { return implode( ' ', $author ); }, $result_row['authors'] ) ) .'
|Source='. $result_row['source'] .'
|Volume='. $result_row['volume'] .'
|Websites='. implode( ',', $result_row['websites'] ) .'
|Doi='. $result_row['identifiers']['doi'] .'
|Keywords='. implode( ';', $result_row['keywords'] ) .'
}}
			';
			$title = Title::newFromText( $result_row['id']);
			$wikiPage = new WikiPage( $title );
			$content = ContentHandler::makeContent( $text, $title );
			$wikiPage->doEditContent( $content , "Importing document found in group");
		}
		if ( count( $result ) > 0 ) {
			$out->addHTML( "Successfully created/updated " . count( $result ) . " pages" );
		} else {
			$out->addHTML( "Invalid result" );
		}
	}
}
