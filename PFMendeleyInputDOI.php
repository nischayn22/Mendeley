<?php
/**
 * File holding the PFMendeleyInputDOI class
 *
 * @file
 */

/**
 * The PFMendeleyInputDOI class.
 */
class PFMendeleyInputDOI extends PFFormInput {

	public static function getName() {
		return 'mendeley_doi';
	}

	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, $other_args ) {
		global $wgOut;

		$doiInputAttrs = array(
			'class' => 'menedeley_id_input',
			'style' => 'margin-top:10px;max-width: 400px;',
			'placeholder' => 'Document ID (Can be auto populated on selecting title in above field)',
			'size' => '50'
		);
		return  Html::input( $input_name, $cur_value, 'text', $doiInputAttrs );
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