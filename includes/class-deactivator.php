<?php
/**
 * Plugin deactivator.
 *
 * @package DH\IndexNow
 */

namespace DH\IndexNow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin deactivation tasks.
 */
class Deactivator {

	/**
	 * Run on plugin deactivation.
	 *
	 * Removes the key file and flushes rewrite rules.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Remove key verification file.
		$key = get_option( 'dh_indexnow_api_key', '' );
		if ( ! empty( $key ) ) {
			$file = ABSPATH . $key . '.txt';
			if ( file_exists( $file ) ) {
				wp_delete_file( $file );
			}
		}

		// Clear scheduled cron.
		$timestamp = wp_next_scheduled( 'dh_indexnow_process_queue' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'dh_indexnow_process_queue' );
		}

		flush_rewrite_rules();
	}
}
