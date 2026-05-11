<?php
// Copyright (c) 2026 altered.wiki contributors. MIT License.

use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\UnlistedSpecialPage;

class SpecialAltchaJS extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct( 'AltchaJS' );
	}

	public function execute( $subPage ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$upstreamUrl = $config->get( 'AltchaJSUrl' );
		$ttl = (int)$config->get( 'AltchaJSTTL' );

		$cacheDir  = $config->get( 'UploadDirectory' ) . '/altcha-cache';
		$cachePath = $cacheDir . '/altcha.min.js';

		if ( file_exists( $cachePath ) && ( time() - filemtime( $cachePath ) ) < $ttl ) {
			$this->serveFile( $cachePath );
			return;
		}

		$factory = MediaWikiServices::getInstance()->getHttpRequestFactory();
		$js = $factory->get( $upstreamUrl, [], __METHOD__ );

		if ( $js !== null ) {
			wfMkdirParents( $cacheDir );
			file_put_contents( $cachePath, $js );
			$this->serveFile( $cachePath );
			return;
		}

		// Upstream unreachable — serve stale cache rather than break the form
		if ( file_exists( $cachePath ) ) {
			$this->serveFile( $cachePath );
			return;
		}

		$this->getOutput()->setStatusCode( 503 );
		$this->getOutput()->disable();
		echo '/* AltchaJS: upstream unavailable and no local cache */';
	}

	private function serveFile( string $path ): void {
		$response = $this->getRequest()->response();
		$response->header( 'Content-Type: application/javascript; charset=utf-8' );
		$response->header( 'Cache-Control: public, max-age=3600' );
		$this->getOutput()->disable();
		echo file_get_contents( $path );
	}
}
