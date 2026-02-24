<?php
/**
 * AJAX handler for DH IndexNow.
 *
 * @package DH\IndexNow
 */

namespace DH\IndexNow\Admin;

use DH\IndexNow\Settings;
use DH\IndexNow\Queue;
use DH\IndexNow\IndexNow_Api;
use DH\IndexNow\Google_Api;
use DH\IndexNow\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles all AJAX requests for the plugin.
 */
class Ajax {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Queue instance.
	 *
	 * @var Queue
	 */
	private Queue $queue;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings instance.
	 * @param Queue    $queue    Queue instance.
	 */
	public function __construct( Settings $settings, Queue $queue ) {
		$this->settings = $settings;
		$this->queue    = $queue;
	}

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_dh_indexnow_manual_submit', array( $this, 'handle_manual_submit' ) );
		add_action( 'wp_ajax_dh_indexnow_bulk_submit', array( $this, 'handle_bulk_submit' ) );
		add_action( 'wp_ajax_dh_indexnow_clear_logs', array( $this, 'handle_clear_logs' ) );
		add_action( 'wp_ajax_dh_indexnow_export_csv', array( $this, 'handle_export_csv' ) );
	}

	/**
	 * Handle manual URL submission via AJAX.
	 *
	 * @return void
	 */
	public function handle_manual_submit(): void {
		check_ajax_referer( 'dh_indexnow_ajax', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'dh-indexnow' ) ), 403 );
		}

		$urls_raw = isset( $_POST['urls'] ) ? sanitize_textarea_field( wp_unslash( $_POST['urls'] ) ) : '';
		$engines  = isset( $_POST['engines'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['engines'] ) ) : array( 'bing', 'google' );

		// Parse URLs — support newline and comma separation.
		$urls = preg_split( '/[\n,]+/', $urls_raw, -1, PREG_SPLIT_NO_EMPTY );
		$urls = array_map( 'trim', $urls );
		$urls = array_filter( $urls, 'wp_http_validate_url' );
		$urls = array_values( array_unique( $urls ) );

		if ( empty( $urls ) ) {
			wp_send_json_error( array( 'message' => __( 'No valid URLs provided.', 'dh-indexnow' ) ) );
		}

		$results = array();

		// Submit to Bing / IndexNow.
		if ( in_array( 'bing', $engines, true ) ) {
			$api_key = $this->settings->get_api_key();
			if ( ! empty( $api_key ) ) {
				$bing_results = IndexNow_Api::submit( $urls, $api_key, $this->settings->get_batch_size() );
				foreach ( $bing_results as $batch_result ) {
					foreach ( $batch_result['urls'] as $url ) {
						$status = $batch_result['success'] ? 'done' : 'failed';
						Logger::log( 0, $url, 'bing', $batch_result['http_code'], $batch_result['response'], $status );
						$results[] = array(
							'url'       => $url,
							'engine'    => 'bing',
							'http_code' => $batch_result['http_code'],
							'status'    => $status,
							'timestamp' => current_time( 'Y-m-d H:i:s' ),
						);
					}
				}
			}
		}

		// Submit to Google.
		if ( in_array( 'google', $engines, true ) ) {
			$creds = $this->settings->get_google_credentials();
			if ( ! empty( $creds ) ) {
				$google_results = Google_Api::submit( $urls, $creds );
				foreach ( $google_results as $result ) {
					$status = $result['success'] ? 'done' : 'failed';
					Logger::log( 0, $result['url'], 'google', $result['http_code'], $result['response'], $status );
					$results[] = array(
						'url'       => $result['url'],
						'engine'    => 'google',
						'http_code' => $result['http_code'],
						'status'    => $status,
						'timestamp' => current_time( 'Y-m-d H:i:s' ),
					);
				}
			}
		}

		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * Handle bulk submission by post type via AJAX.
	 *
	 * Queues all published posts of the selected type for submission via WP-Cron.
	 *
	 * @return void
	 */
	public function handle_bulk_submit(): void {
		check_ajax_referer( 'dh_indexnow_ajax', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'dh-indexnow' ) ), 403 );
		}

		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
		$engines   = isset( $_POST['engines'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['engines'] ) ) : array( 'bing', 'google' );

		if ( empty( $post_type ) || ! post_type_exists( $post_type ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post type.', 'dh-indexnow' ) ) );
		}

		$posts = get_posts( array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		if ( empty( $posts ) ) {
			wp_send_json_error( array( 'message' => __( 'No published posts found.', 'dh-indexnow' ) ) );
		}

		$excluded = $this->settings->get_excluded_urls();
		$queued   = 0;

		foreach ( $posts as $post_id ) {
			$url = get_permalink( $post_id );
			if ( ! $url || in_array( $url, $excluded, true ) ) {
				continue;
			}
			$this->queue->add( $url, 'updated', $engines );
			++$queued;
		}

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %d: number of URLs queued */
				__( '%d URLs queued for submission. They will be processed by WP-Cron.', 'dh-indexnow' ),
				$queued
			),
			'count'   => $queued,
		) );
	}

	/**
	 * Handle clearing all logs via AJAX.
	 *
	 * @return void
	 */
	public function handle_clear_logs(): void {
		check_ajax_referer( 'dh_indexnow_ajax', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'dh-indexnow' ) ), 403 );
		}

		$this->queue->clear_logs();
		wp_send_json_success( array( 'message' => __( 'Logs cleared successfully.', 'dh-indexnow' ) ) );
	}

	/**
	 * Handle CSV export of logs via AJAX.
	 *
	 * @return void
	 */
	public function handle_export_csv(): void {
		check_ajax_referer( 'dh_indexnow_ajax', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'dh-indexnow' ) );
		}

		$csv = $this->queue->export_csv();

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=dh-indexnow-logs-' . gmdate( 'Y-m-d' ) . '.csv' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
