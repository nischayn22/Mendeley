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

		$group_id = $request->getVal( "mendeley_group_id", $par );

		$formOpts = [
			'id' => 'menedeley_import',
			'method' => 'post',
			"enctype" => "multipart/form-data",
			'action' => $out->getTitle()->getFullUrl(),
		];

		$out->addHTML(
			Html::openElement( 'form', $formOpts ) . "<br>" .
			Html::label( "Enter Mendeley Group ID","", array( "for" => "mendeley_group_id" ) ) . "<br>" .
			Html::element( 'input', array( "id" => "mendeley_group_id", "name" => "mendeley_group_id", "type" => "text", "value" => $group_id ) ) . "<br><br>"
		);

		$out->addHTML(
			Html::submitButton( "Submit", array() ) .
			Html::closeElement( 'form' )
		);

		if ( $group_id ) {
			$this->handleImport( $group_id );
		}
	}

	public function handleImport( $group_id ) {
		global $wgMendeleyUseJobs;
		$pages = Mendeley::getInstance()->importGroup( $group_id );
		$out = $this->getOutput();
		if ( count($pages) > 0 ) {
			$out->addHTML( Html::openElement('ul') );
			foreach ($pages as $pl) {
				$out->addHTML( Html::rawElement( 'li', array(), Linker::link($pl) ) );
			}
			$out->addHTML( Html::closeElement('ul') );
			if ( $wgMendeleyUseJobs ) {
				$out->addHTML( "Successfully scheduled " . count( $pages ) . " pages for import. " .
							   "Please wait for the jobs to be processed or run runJobs.php maintenance " .
							   "script by hand." );
			} else {
				$out->addHTML( "Successfully created/updated " . count( $pages ) . " pages" );
			}
		} else {
			$out->addHTML( "Invalid result" );
		}
	}

}
