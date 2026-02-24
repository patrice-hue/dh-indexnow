<?php
/**
 * Uninstall handler for DH IndexNow.
 *
 * Removes all plugin options and the custom database table.
 *
 * @package DH\IndexNow
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete options.
$options = array(
	'dh_indexnow_api_key',
	'dh_indexnow_google_credentials',
	'dh_indexnow_post_types',
	'dh_indexnow_exclude_urls',
	'dh_indexnow_batch_size',
	'dh_indexnow_auto_submit',
	'dh_indexnow_db_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Delete transients.
delete_transient( 'dh_indexnow_google_token' );

// Remove the key verification file.
$key = get_option( 'dh_indexnow_api_key', '' );
if ( ! empty( $key ) ) {
	$file = ABSPATH . $key . '.txt';
	if ( file_exists( $file ) ) {
		wp_delete_file( $file );
	}
}

// Drop custom table.
$table_name = $wpdb->prefix . 'dh_indexnow_queue';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
