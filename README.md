# AltchaCaptcha

A [ConfirmEdit](https://www.mediawiki.org/wiki/Extension:ConfirmEdit) backend for MediaWiki that uses [Altcha](https://altcha.org/) proof-of-work challenges. No external service, no cookies, no tracking.

## How it works

When a CAPTCHA trigger fires (account creation, bad login, URL addition), the server generates a SHA-256 proof-of-work challenge signed with an HMAC key. The browser widget solves it client-side (typically < 2 seconds) and submits the solution. The server re-derives the expected hash and verifies the HMAC. No stored state required.

The Altcha widget JS is never loaded from a CDN by the browser. Instead, `Special:AltchaJS` fetches it server-side on first use, caches it in `$wgUploadDirectory/altcha-cache/`, and serves it locally. The cache refreshes automatically after the configured TTL. If the upstream is unreachable, the stale cache is served as a fallback.

## Requirements

- MediaWiki 1.41+
- [ConfirmEdit](https://www.mediawiki.org/wiki/Extension:ConfirmEdit) extension

Tested against MediaWiki 1.44 with the refactored ConfirmEdit AuthManager API.

## Installation

1. Clone or copy this directory into your wiki's `extensions/AltchaCaptcha/`.

2. Add to `LocalSettings.php`:
   ```php
   wfLoadExtension( 'ConfirmEdit' );
   wfLoadExtension( 'AltchaCaptcha' );

   $wgCaptchaClass    = 'AltchaCaptcha';
   $wgAltchaHmacKey   = 'your-secret-key-here'; // openssl rand -hex 32

   # Optional complexity tuning (default shown):
   $wgAltchaComplexityMin = 40000;
   $wgAltchaComplexityMax = 200000;

   # Trigger CAPTCHA on account creation and bad login:
   $wgCaptchaTriggers['createaccount'] = true;
   $wgCaptchaTriggers['badlogin']      = true;
   $wgCaptchaTriggers['addurl']        = true;
   ```

3. Generate your HMAC key:
   ```sh
   openssl rand -hex 32
   ```

The widget JS is fetched and cached automatically on first page load — no manual download required.

## Configuration

| Variable | Default | Description |
|---|---|---|
| `$wgAltchaHmacKey` | `''` | **Required.** HMAC-SHA256 key for signing challenges. |
| `$wgAltchaComplexityMin` | `40000` | Minimum PoW iterations (lower = faster solve). |
| `$wgAltchaComplexityMax` | `200000` | Maximum PoW iterations. |
| `$wgAltchaJSUrl` | `https://cdn.jsdelivr.net/npm/altcha/dist/altcha.min.js` | Upstream URL the server fetches the widget JS from. |
| `$wgAltchaJSTTL` | `86400` | Seconds before the cached JS is re-fetched from upstream. |

## License

MIT
