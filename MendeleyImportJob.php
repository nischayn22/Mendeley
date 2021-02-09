<?php

class MendeleyImportJob extends Job {

	public function __construct( $title, $params = null ) {
		parent::__construct( 'MendeleyImportJob', $title, $params );
		$this->removeDuplicates = true;
	}

	public function run() {
		$status = $this->editPage();
		if ( !$status->isOK() ) {
			$this->setLastError( $status->getMessage() );
			return false;
		}

		return true;
	}

	private function editPage() {
		$wikiPage = new WikiPage( $this->title );
		$content = ContentHandler::makeContent( $this->params[ 'text' ], $this->title );
		return $wikiPage->doEditContent(
			$content,
			"Importing document found in group (job)",
			0,
			false,
			$this->params['actor_id'] ? User::newFromId( $this->params['actor_id'] ) : null
		);
	}

}
