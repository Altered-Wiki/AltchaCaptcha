<?php
// Copyright (c) 2026 altered.wiki contributors. MIT License.

use MediaWiki\HTMLForm\HTMLFormField;

class HTMLAltchaField extends HTMLFormField {

	private array $challenge;

	public function __construct( array $params ) {
		parent::__construct( $params );
		$this->challenge = $params['challenge'];
		// name must match what AltchaCaptcha::passCaptcha reads from the request
		$this->mName  = 'altcha';
		$this->mLabel = '';
	}

	public function getInputHTML( $value ) {
		$out = $this->mParent->getOutput();

		$challengeJson = htmlspecialchars( json_encode( $this->challenge ), ENT_QUOTES, 'UTF-8' );
		$scriptUrl = htmlspecialchars(
			\SpecialPage::getTitleFor( 'AltchaJS' )->getLocalURL(),
			ENT_QUOTES,
			'UTF-8'
		);

		$out->addHeadItem(
			'altcha-widget-script',
			"<script type=\"module\" src=\"$scriptUrl\"></script>"
		);

		return "<altcha-widget challengejson=\"$challengeJson\" hidefooter name=\"altcha\"></altcha-widget>";
	}
}
