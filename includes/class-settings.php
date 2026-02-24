<?php
/**
 * Plugin settings management.
 *
 * @package DH\IndexNow
 */

namespace DH\IndexNow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and manages all plugin settings via the WordPress Settings API.
 */
class Settings {

	/**
	 * Initialize settings hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register all plugin settings with the Settings API.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting( 'dh_indexnow_general', 'dh_indexnow_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		) );

		register_setting( 'dh_indexnow_general', 'dh_indexnow_google_credentials', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_google_credentials' ),
		) );

		register_setting( 'dh_indexnow_general', 'dh_indexnow_post_types', array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_post_types' ),
		) );

		register_setting( 'dh_indexnow_general', 'dh_indexnow_exclude_urls', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_textarea_field',
		) );

		register_setting( 'dh_indexnow_general', 'dh_indexnow_batch_size', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
		) );

		register_setting( 'dh_indexnow_general', 'dh_indexnow_auto_submit', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		) );
	}

	/**
	 * Encrypt and sanitize Google credentials JSON.
	 *
	 * @param string $value Raw JSON input.
	 * @return string Encrypted JSON string.
	 */
	public function sanitize_google_credentials( string $value ): string {
		$value = trim( $value );
		if ( empty( $value ) ) {
			return '';
		}

		// Validate it's valid JSON.
		$decoded = json_decode( $value, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			add_settings_error(
				'dh_indexnow_google_credentials',
				'invalid_json',
				__( 'Google Service Account JSON is not valid JSON.', 'dh-indexnow' )
			);
			return get_option( 'dh_indexnow_google_credentials', '' );
		}

		return self::encrypt( $value );
	}

	/**
	 * Sanitize post types array.
	 *
	 * @param mixed $value Input value.
	 * @return array Sanitized post types.
	 */
	public function sanitize_post_types( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array( 'post', 'page' );
		}
		return array_map( 'sanitize_key', $value );
	}

	/**
	 * Get the IndexNow API key.
	 *
	 * @return string
	 */
	public function get_api_key(): string {
		return get_option( 'dh_indexnow_api_key', '' );
	}

	/**
	 * Get the decrypted Google credentials.
	 *
	 * @return array|null Decoded JSON or null on failure.
	 */
	public function get_google_credentials(): ?array {
		$encrypted = get_option( 'dh_indexnow_google_credentials', '' );
		if ( empty( $encrypted ) ) {
			return null;
		}

		$decrypted = self::decrypt( $encrypted );
		if ( false === $decrypted ) {
			return null;
		}

		$decoded = json_decode( $decrypted, true );
		return ( json_last_error() === JSON_ERROR_NONE ) ? $decoded : null;
	}

	/**
	 * Get the selected post types for indexing.
	 *
	 * @return array
	 */
	public function get_post_types(): array {
		return get_option( 'dh_indexnow_post_types', array( 'post', 'page' ) );
	}

	/**
	 * Get the exclusion URL list.
	 *
	 * @return array List of excluded URLs.
	 */
	public function get_excluded_urls(): array {
		$raw = get_option( 'dh_indexnow_exclude_urls', '' );
		if ( empty( $raw ) ) {
			return array();
		}
		return array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
	}

	/**
	 * Get the batch size.
	 *
	 * @return int
	 */
	public function get_batch_size(): int {
		$size = (int) get_option( 'dh_indexnow_batch_size', 100 );
		return max( 1, min( 100, $size ) );
	}

	/**
	 * Check if auto-submit is enabled.
	 *
	 * @return bool
	 */
	public function is_auto_submit_enabled(): bool {
		return '1' === get_option( 'dh_indexnow_auto_submit', '1' );
	}

	/**
	 * Check if a URL is excluded.
	 *
	 * @param string $url URL to check.
	 * @return bool
	 */
	public function is_url_excluded( string $url ): bool {
		$excluded = $this->get_excluded_urls();
		return in_array( $url, $excluded, true );
	}

	/**
	 * Encrypt a string using AES-256-CBC.
	 *
	 * @param string $data Plaintext data.
	 * @return string Base64-encoded ciphertext with IV prepended.
	 */
	public static function encrypt( string $data ): string {
		$key    = self::get_encryption_key();
		$iv     = openssl_random_pseudo_bytes( 16 );
		$cipher = openssl_encrypt( $data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		return base64_encode( $iv . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a string encrypted with encrypt().
	 *
	 * @param string $data Base64-encoded ciphertext.
	 * @return string|false Plaintext or false on failure.
	 */
	public static function decrypt( string $data ): string|false {
		$key  = self::get_encryption_key();
		$raw  = base64_decode( $data, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw || strlen( $raw ) < 17 ) {
			return false;
		}
		$iv   = substr( $raw, 0, 16 );
		$text = substr( $raw, 16 );
		return openssl_decrypt( $text, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
	}

	/**
	 * Derive a consistent encryption key from AUTH_KEY.
	 *
	 * @return string 32-byte key.
	 */
	private static function get_encryption_key(): string {
		return hash( 'sha256', AUTH_KEY, true );
	}
}
