<?php

class SpecialMendeleyImport extends SpecialPage {

	public function __construct() {
		parent::__construct( 'MendeleyImport', 'mendeleyimport' );
	}

	private static function getPaginationLink( array $responseHeaders, $rel = 'next' ) {
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
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$request = $this->getRequest();
		$out = $this->getOutput();

		$formOpts = [
			'id' => 'menedeley_import',
			'method' => 'post',
			"enctype" => "multipart/form-data",
			'action' => $out->getTitle()->getFullUrl(),
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
		$pages = 0;
		$access_token = MendeleyHooks::getAccessToken();
		$responseHeaders = [];
		$result = MendeleyHooks::httpRequest( "https://api.mendeley.com/documents?access_token=$access_token&group_id=$group_id&view=all&limit=50", '', array(), $responseHeaders );
		while ( true ) {
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
}}';
				$title = Title::newFromText( $result_row['id'] );
				$wikiPage = new WikiPage( $title );
				$content = ContentHandler::makeContent( $text, $title );
				$wikiPage->doEditContent( $content , "Importing document found in group" );
				$pages++;
			}
			$nextLink = self::getPaginationLink( $responseHeaders );
			if ( $nextLink ) {
				$result = MendeleyHooks::httpRequest( $nextLink, '', array(), $responseHeaders );
			} else {
				break;
			}
		}

		$out = $this->getOutput();
		if ( $pages > 0 ) {
			$out->addHTML( "Successfully created/updated $pages pages" );
		} else {
			$out->addHTML( "Invalid result" );
		}
	}
}
