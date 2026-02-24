<?php
/**
 * Queue manager for URL submissions.
 *
 * @package DH\IndexNow
 */

namespace DH\IndexNow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the submission queue and cron processing.
 */
class Queue {

	/**
	 * Initialize queue hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedule' ) );
		add_action( 'dh_indexnow_process_queue', array( $this, 'process' ) );
	}

	/**
	 * Register a custom 5-minute cron schedule.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified schedules.
	 */
	public function add_cron_schedule( array $schedules ): array {
		$schedules['dh_indexnow_five_minutes'] = array(
			'interval' => 300,
			'display'  => __( 'Every 5 Minutes (DH IndexNow)', 'dh-indexnow' ),
		);
		return $schedules;
	}

	/**
	 * Add a URL to the submission queue.
	 *
	 * @param string $url     The URL to submit.
	 * @param string $action  Action type: updated or deleted.
	 * @param array  $engines Engines to submit to: bing, google.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public function add( string $url, string $action = 'updated', array $engines = array( 'bing', 'google' ) ): int|false {
		global $wpdb;
		$table = $wpdb->prefix . 'dh_indexnow_queue';

		$result = $wpdb->insert(
			$table,
			array(
				'url'        => $url,
				'action'     => $action,
				'engines'    => wp_json_encode( $engines ),
				'status'     => 'pending',
				'attempts'   => 0,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Process pending items in the queue.
	 *
	 * Runs via WP-Cron every 5 minutes. Processes up to 200 URLs per run.
	 *
	 * @return void
	 */
	public function process(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'dh_indexnow_queue';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s AND attempts < %d ORDER BY created_at ASC LIMIT %d",
				'pending',
				3,
				200
			)
		);

		if ( empty( $items ) ) {
			return;
		}

		$settings    = new Settings();
		$api_key     = $settings->get_api_key();
		$google_cred = $settings->get_google_credentials();
		$batch_size  = $settings->get_batch_size();

		// Group URLs for batch IndexNow submission.
		$bing_urls   = array();
		$bing_items  = array();
		$google_items = array();

		foreach ( $items as $item ) {
			$engines = json_decode( $item->engines, true ) ?: array();

			// Increment attempt count.
			$wpdb->update(
				$table,
				array( 'attempts' => (int) $item->attempts + 1 ),
				array( 'id' => $item->id ),
				array( '%d' ),
				array( '%d' )
			);

			if ( in_array( 'bing', $engines, true ) && 'deleted' !== $item->action ) {
				$bing_urls[]  = $item->url;
				$bing_items[] = $item;
			}

			if ( in_array( 'google', $engines, true ) ) {
				$google_items[] = $item;
			}
		}

		// Submit to IndexNow (Bing) in batches.
		if ( ! empty( $bing_urls ) && ! empty( $api_key ) ) {
			$results = IndexNow_Api::submit( $bing_urls, $api_key, $batch_size );
			foreach ( $results as $result ) {
				$status = $result['success'] ? 'done' : 'failed';
				foreach ( $result['urls'] as $url ) {
					// Find matching queue item.
					foreach ( $bing_items as $item ) {
						if ( $item->url === $url ) {
							Logger::log(
								(int) $item->id,
								$url,
								'bing',
								$result['http_code'],
								$result['response'],
								$status
							);
							break;
						}
					}
				}
			}
		}

		// Submit to Google sequentially.
		if ( ! empty( $google_items ) && ! empty( $google_cred ) ) {
			$google_urls = array_map( fn( $item ) => $item->url, $google_items );
			$actions     = array();
			foreach ( $google_items as $item ) {
				$actions[ $item->url ] = ( 'deleted' === $item->action ) ? 'URL_DELETED' : 'URL_UPDATED';
			}

			// Group by action type for submission.
			$grouped = array();
			foreach ( $google_items as $item ) {
				$type = ( 'deleted' === $item->action ) ? 'URL_DELETED' : 'URL_UPDATED';
				$grouped[ $type ][] = $item;
			}

			foreach ( $grouped as $type => $group_items ) {
				$urls    = array_map( fn( $i ) => $i->url, $group_items );
				$results = Google_Api::submit( $urls, $google_cred, $type );

				foreach ( $results as $result ) {
					$status = $result['success'] ? 'done' : 'failed';
					foreach ( $group_items as $item ) {
						if ( $item->url === $result['url'] ) {
							Logger::log(
								(int) $item->id,
								$result['url'],
								'google',
								$result['http_code'],
								$result['response'],
								$status
							);
							break;
						}
					}
				}
			}
		}

		// Mark items that exceeded max attempts as failed.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s WHERE status = %s AND attempts >= %d",
				'failed',
				'pending',
				3
			)
		);
	}

	/**
	 * Get queue items with optional filtering.
	 *
	 * @param array $args Query arguments: status, engine, per_page, offset, orderby, order.
	 * @return array Items from the queue.
	 */
	public function get_items( array $args = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'dh_indexnow_queue';

		$defaults = array(
			'status'   => '',
			'engine'   => '',
			'per_page' => 20,
			'offset'   => 0,
			'orderby'  => 'created_at',
			'order'    => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['engine'] ) ) {
			$where[]  = 'engine = %s';
			$values[] = $args['engine'];
		}

		$allowed_orderby = array( 'id', 'url', 'status', 'engine', 'http_code', 'created_at', 'processed_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$where_sql = implode( ' AND ', $where );

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL
					array_merge( $values, array( $args['per_page'], $args['offset'] ) )
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE 1=1 ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				$args['per_page'],
				$args['offset']
			)
		);
	}

	/**
	 * Get total count of queue items with optional filtering.
	 *
	 * @param array $args Query arguments: status, engine.
	 * @return int Total count.
	 */
	public function get_total( array $args = array() ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'dh_indexnow_queue';

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['engine'] ) ) {
			$where[]  = 'engine = %s';
			$values[] = $args['engine'];
		}

		$where_sql = implode( ' AND ', $where );

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", // phpcs:ignore WordPress.DB.PreparedSQL
					$values
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Clear all log entries from the queue table.
	 *
	 * @return void
	 */
	public function clear_logs(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'dh_indexnow_queue';
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Export all queue entries as CSV.
	 *
	 * @return string CSV content.
	 */
	public function export_csv(): string {
		global $wpdb;
		$table = $wpdb->prefix . 'dh_indexnow_queue';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$items = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );

		$output = fopen( 'php://temp', 'r+' );
		fputcsv( $output, array( 'ID', 'URL', 'Action', 'Engine', 'Status', 'HTTP Code', 'Response', 'Attempts', 'Created', 'Processed' ) );

		foreach ( $items as $item ) {
			fputcsv( $output, array(
				$item['id'],
				$item['url'],
				$item['action'],
				$item['engine'],
				$item['status'],
				$item['http_code'],
				$item['response'],
				$item['attempts'],
				$item['created_at'],
				$item['processed_at'],
			) );
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );

		return $csv;
	}
}
