<?php

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Extension\ConfirmEdit\Auth\CaptchaAuthenticationRequest;
use MediaWiki\Extension\ConfirmEdit\SimpleCaptcha\SimpleCaptcha;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;

/**
 * Altcha proof-of-work CAPTCHA backend for ConfirmEdit.
 *
 * Verification is fully stateless: the HMAC signature on the challenge
 * proves server origin, so no session storage is required.
 */
class AltchaCaptcha extends SimpleCaptcha {

	private function getConfig(): \Config {
		return MediaWikiServices::getInstance()->getMainConfig();
	}

	private function getHmacKey(): string {
		$key = $this->getConfig()->get( 'AltchaHmacKey' );
		if ( empty( $key ) ) {
			throw new RuntimeException( '$wgAltchaHmacKey must be set in LocalSettings.php' );
		}
		return $key;
	}

	/**
	 * Generate a new Altcha challenge. The solution number is embedded in the
	 * challenge so verification needs no stored state — the HMAC proves the
	 * challenge was issued by this server.
	 */
	public function generateChallenge(): array {
		$config = $this->getConfig();
		$min = (int)$config->get( 'AltchaComplexityMin' );
		$max = (int)$config->get( 'AltchaComplexityMax' );

		$salt = bin2hex( random_bytes( 12 ) );
		$number = random_int( $min, $max );
		$challenge = hash( 'sha256', $salt . $number );
		$signature = hash_hmac( 'sha256', $challenge, $this->getHmacKey() );

		return [
			'algorithm' => 'SHA-256',
			'challenge' => $challenge,
			'salt'      => $salt,
			'signature' => $signature,
		];
	}

	/**
	 * Verify an Altcha payload submitted by the client.
	 *
	 * The payload is a base64-encoded JSON string containing:
	 *   algorithm, challenge, number, salt, signature
	 */
	private function verifyPayload( string $payload ): bool {
		$data = json_decode( base64_decode( $payload ), true );

		if ( !is_array( $data ) ) {
			return false;
		}

		$algorithm = strtolower( $data['algorithm'] ?? '' );
		$challenge  = $data['challenge'] ?? '';
		$number     = $data['number'] ?? -1;
		$salt       = $data['salt'] ?? '';
		$signature  = $data['signature'] ?? '';

		if ( $algorithm !== 'sha-256' || $number < 0 || !$challenge || !$salt || !$signature ) {
			return false;
		}

		// Verify the client found the correct number (proof-of-work)
		$computed = hash( 'sha256', $salt . $number );
		if ( !hash_equals( $challenge, $computed ) ) {
			return false;
		}

		// Verify the challenge was issued by this server
		$expectedSig = hash_hmac( 'sha256', $challenge, $this->getHmacKey() );
		return hash_equals( $expectedSig, $signature );
	}

	public function getCaptcha(): array {
		return [ 'type' => 'altcha' ];
	}

	public function getCaptchaInfo( $captchaData, $id ) {
		return 'altcha';
	}

	/**
	 * Replace the default captchaWord field with the Altcha widget.
	 */
	public function onAuthChangeFormFields(
		array $requests, array $fieldInfo, array &$formDescriptor, $action
	) {
		$req = AuthenticationRequest::getRequestByClass(
			$requests,
			CaptchaAuthenticationRequest::class,
			true
		);
		if ( !$req ) {
			return;
		}

		// hide the info field — the Altcha widget is self-explanatory
		$formDescriptor['captchaInfo']['type'] = 'hidden';

		$formDescriptor['captchaWord'] = [
			'class'     => HTMLAltchaField::class,
			'challenge' => $this->generateChallenge(),
			'label'     => null,
		] + $formDescriptor['captchaWord'];
	}

	protected function passCaptcha( $index, $word, $user = null ): bool {
		$request = \RequestContext::getMain()->getRequest();
		$payload = $request->getVal( 'altcha', '' );

		if ( empty( $payload ) ) {
			return false;
		}

		return $this->verifyPayload( $payload );
	}

	public function showEditFormFields( EditPage $editPage, OutputPage $out ): void {
		// Intentionally empty: widget is injected via onAuthChangeFormFields
	}
}
