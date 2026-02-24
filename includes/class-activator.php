<?php
/**
 * Plugin activator.
 *
 * @package DH\IndexNow
 */

namespace DH\IndexNow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation tasks.
 */
class Activator {

	/**
	 * Run on plugin activation.
	 *
	 * Generates an API key, writes the verification file, and creates the queue table.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::generate_api_key();
		self::create_queue_table();
		self::schedule_cron();
	}

	/**
	 * Generate a random 32-char hex API key and write verification file.
	 *
	 * @return void
	 */
	private static function generate_api_key(): void {
		$existing = get_option( 'dh_indexnow_api_key', '' );
		if ( ! empty( $existing ) ) {
			return;
		}

		$key = bin2hex( random_bytes( 16 ) );
		update_option( 'dh_indexnow_api_key', $key );

		$file = ABSPATH . $key . '.txt';
		if ( ! file_exists( $file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $file, $key );
		}

		// Set defaults.
		if ( false === get_option( 'dh_indexnow_post_types' ) ) {
			update_option( 'dh_indexnow_post_types', array( 'post', 'page' ) );
		}
		if ( false === get_option( 'dh_indexnow_batch_size' ) ) {
			update_option( 'dh_indexnow_batch_size', 100 );
		}
		if ( false === get_option( 'dh_indexnow_auto_submit' ) ) {
			update_option( 'dh_indexnow_auto_submit', '1' );
		}
	}

	/**
	 * Create the queue database table using dbDelta.
	 *
	 * @return void
	 */
	private static function create_queue_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'dh_indexnow_queue';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			url text NOT NULL,
			action varchar(20) NOT NULL DEFAULT 'updated',
			engines text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			http_code int(5) DEFAULT NULL,
			response text DEFAULT NULL,
			engine varchar(20) DEFAULT NULL,
			attempts smallint(3) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			processed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'dh_indexnow_db_version', '1.0.0' );
	}

	/**
	 * Schedule the cron event for queue processing.
	 *
	 * @return void
	 */
	private static function schedule_cron(): void {
		if ( ! wp_next_scheduled( 'dh_indexnow_process_queue' ) ) {
			wp_schedule_event( time(), 'dh_indexnow_five_minutes', 'dh_indexnow_process_queue' );
		}
	}
}
