<?php
/**
 * Google Indexing API client.
 *
 * @package DH\IndexNow
 */

namespace DH\IndexNow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles URL submission to the Google Indexing API using JWT / OAuth2.
 */
class Google_Api {

	private const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
	private const INDEXING_URL = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
	private const SCOPE        = 'https://www.googleapis.com/auth/indexing';

	/**
	 * Submit URLs to the Google Indexing API.
	 *
	 * @param array  $urls        List of URLs to submit.
	 * @param array  $credentials Decoded service account JSON.
	 * @param string $action      Submission type: URL_UPDATED or URL_DELETED.
	 * @return array Array of result arrays with keys: url, http_code, response, success.
	 */
	public static function submit( array $urls, array $credentials, string $action = 'URL_UPDATED' ): array {
		$results = array();

		$access_token = self::get_access_token( $credentials );
		if ( is_wp_error( $access_token ) ) {
			foreach ( $urls as $url ) {
				$results[] = array(
					'url'       => $url,
					'http_code' => 0,
					'response'  => $access_token->get_error_message(),
					'success'   => false,
				);
			}
			return $results;
		}

		foreach ( $urls as $url ) {
			$payload = array(
				'url'  => $url,
				'type' => $action,
			);

			$response = wp_remote_post( self::INDEXING_URL, array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $access_token,
				),
				'body'    => wp_json_encode( $payload ),
			) );

			if ( is_wp_error( $response ) ) {
				$results[] = array(
					'url'       => $url,
					'http_code' => 0,
					'response'  => $response->get_error_message(),
					'success'   => false,
				);
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );

			$results[] = array(
				'url'       => $url,
				'http_code' => $code,
				'response'  => $body,
				'success'   => ( $code >= 200 && $code < 300 ),
			);
		}

		return $results;
	}

	/**
	 * Get a cached or fresh OAuth2 access token using JWT.
	 *
	 * @param array $credentials Service account JSON data.
	 * @return string|\WP_Error Access token or error.
	 */
	private static function get_access_token( array $credentials ): string|\WP_Error {
		$cached = get_transient( 'dh_indexnow_google_token' );
		if ( false !== $cached ) {
			return $cached;
		}

		$jwt = self::create_jwt( $credentials );
		if ( is_wp_error( $jwt ) ) {
			return $jwt;
		}

		$response = wp_remote_post( self::TOKEN_URL, array(
			'timeout' => 10,
			'body'    => array(
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion'  => $jwt,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || empty( $body['access_token'] ) ) {
			$error_msg = $body['error_description'] ?? $body['error'] ?? 'Unknown token error';
			return new \WP_Error( 'google_token_error', $error_msg );
		}

		$token = $body['access_token'];
		set_transient( 'dh_indexnow_google_token', $token, 55 * MINUTE_IN_SECONDS );

		return $token;
	}

	/**
	 * Create a signed JWT for Google OAuth2.
	 *
	 * @param array $credentials Service account JSON data.
	 * @return string|\WP_Error Signed JWT string or error.
	 */
	private static function create_jwt( array $credentials ): string|\WP_Error {
		if ( empty( $credentials['client_email'] ) || empty( $credentials['private_key'] ) ) {
			return new \WP_Error( 'google_credentials_error', 'Missing client_email or private_key in credentials.' );
		}

		$now = time();

		$header = array(
			'alg' => 'RS256',
			'typ' => 'JWT',
		);

		$claim = array(
			'iss'   => $credentials['client_email'],
			'scope' => self::SCOPE,
			'aud'   => self::TOKEN_URL,
			'iat'   => $now,
			'exp'   => $now + 3600,
		);

		$segments   = array();
		$segments[] = self::base64url_encode( wp_json_encode( $header ) );
		$segments[] = self::base64url_encode( wp_json_encode( $claim ) );

		$signing_input = implode( '.', $segments );
		$signature     = '';

		$key = openssl_pkey_get_private( $credentials['private_key'] );
		if ( false === $key ) {
			return new \WP_Error( 'google_credentials_error', 'Invalid private key in credentials.' );
		}

		$success = openssl_sign( $signing_input, $signature, $key, OPENSSL_ALGO_SHA256 );
		if ( ! $success ) {
			return new \WP_Error( 'google_jwt_error', 'Failed to sign JWT.' );
		}

		$segments[] = self::base64url_encode( $signature );

		return implode( '.', $segments );
	}

	/**
	 * Base64url encode a string (RFC 4648).
	 *
	 * @param string $data Data to encode.
	 * @return string Base64url-encoded string.
	 */
	private static function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}
}
