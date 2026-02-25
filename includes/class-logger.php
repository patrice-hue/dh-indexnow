<?php
/**
 * Logger class for recording submission results.
 *
 * @package DH\IndexNow
 */

namespace DH\IndexNow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles logging submission results to the queue table.
 */
class Logger {

	/**
	 * Log a submission result by updating the queue row or inserting a new log entry.
	 *
	 * @param int    $queue_id  Queue row ID (0 to create a new entry).
	 * @param string $url       The submitted URL.
	 * @param string $engine    Engine name (bing or google).
	 * @param int    $http_code HTTP response code.
	 * @param string $response  Response body or message.
	 * @param string $status    Result status (done or failed).
	 * @param string $action    Action type: updated or deleted.
	 * @return void
	 */
	public static function log( int $queue_id, string $url, string $engine, int $http_code, string $response, string $status = 'done', string $action = 'updated' ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'dh_indexnow_queue';

		if ( $queue_id > 0 ) {
			$wpdb->update(
				$table,
				array(
					'http_code'    => $http_code,
					'response'     => mb_substr( $response, 0, 500 ),
					'engine'       => $engine,
					'status'       => $status,
					'processed_at' => current_time( 'mysql' ),
				),
				array( 'id' => $queue_id ),
				array( '%d', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$table,
				array(
					'url'          => $url,
					'action'       => $action,
					'engines'      => wp_json_encode( array( $engine ) ),
					'engine'       => $engine,
					'status'       => $status,
					'http_code'    => $http_code,
					'response'     => mb_substr( $response, 0, 500 ),
					'attempts'     => 1,
					'created_at'   => current_time( 'mysql' ),
					'processed_at' => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
			);
		}
	}
}
