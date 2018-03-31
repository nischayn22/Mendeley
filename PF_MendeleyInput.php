<?php
/**
 * File holding the PFMendeleyInput class
 *
 * @file
 */

/**
 * The PFMendeleyInput class.
 */
class PFMendeleyInput extends PFFormInput {

	public static function getName() {
		return 'mendeley';
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args ) {
		global $wgOut;

		$wgOut->addModules( 'ext.mendeley.main' );
		$queryInputAttrs = array(
			'class' => 'mendeley_input',
			'style' => 'max-width: 400px;',
			'placeholder' => 'Search Document title or author name',
			'size' => '50'
		);
		$doiInputAttrs = array(
			'class' => 'menedeley_id_input',
			'style' => 'margin-top:10px;max-width: 400px;',
			'placeholder' => 'Document ID (Can be auto populated on selecting title in above field)',
			'size' => '50'
		);
		return '<div>' . Html::input( "", "", 'text', $queryInputAttrs ) . '<br>' . Html::input( $input_name, $cur_value, 'text', $doiInputAttrs ) . '<br><br></div>';
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
	 */
	public function getHtmlText() {
		return self::getHTML(
			$this->mCurrentValue,
			$this->mInputName,
			$this->mIsMandatory,
			$this->mIsDisabled,
			$this->mOtherArgs
		);
	}
}