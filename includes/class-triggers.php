<?php
/**
 * Automatic submission triggers.
 *
 * @package DH\IndexNow
 */

namespace DH\IndexNow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks into WordPress post lifecycle events to trigger URL submissions.
 */
class Triggers {

	/**
	 * Queue instance.
	 *
	 * @var Queue
	 */
	private Queue $queue;

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Queue    $queue    Queue manager.
	 * @param Settings $settings Settings manager.
	 */
	public function __construct( Queue $queue, Settings $settings ) {
		$this->queue    = $queue;
		$this->settings = $settings;
	}

	/**
	 * Register trigger hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'transition_post_status', array( $this, 'on_post_status_change' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'on_post_delete' ), 10, 1 );
		add_action( 'dh_indexnow_submit_url', array( $this, 'handle_scheduled_submit' ), 10, 3 );
	}

	/**
	 * Handle post status transitions (publish/update).
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 * @return void
	 */
	public function on_post_status_change( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( ! $this->settings->is_auto_submit_enabled() ) {
			return;
		}

		if ( 'publish' !== $new_status ) {
			return;
		}

		if ( ! in_array( $post->post_type, $this->settings->get_post_types(), true ) ) {
			return;
		}

		$url = get_permalink( $post );
		if ( ! $url || $this->settings->is_url_excluded( $url ) ) {
			return;
		}

		// Schedule to avoid blocking post save.
		wp_schedule_single_event(
			time() + 5,
			'dh_indexnow_submit_url',
			array( $url, 'updated', array( 'bing', 'google' ) )
		);
	}

	/**
	 * Handle post deletion.
	 *
	 * @param int $post_id Post ID being deleted.
	 * @return void
	 */
	public function on_post_delete( int $post_id ): void {
		if ( ! $this->settings->is_auto_submit_enabled() ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		if ( ! in_array( $post->post_type, $this->settings->get_post_types(), true ) ) {
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		$url = get_permalink( $post );
		if ( ! $url || $this->settings->is_url_excluded( $url ) ) {
			return;
		}

		// Only Google supports URL_DELETED; IndexNow doesn't support deletions.
		wp_schedule_single_event(
			time() + 5,
			'dh_indexnow_submit_url',
			array( $url, 'deleted', array( 'google' ) )
		);
	}

	/**
	 * Handle the scheduled submission event.
	 *
	 * @param string $url     URL to submit.
	 * @param string $action  Action type (updated/deleted).
	 * @param array  $engines Engines to submit to.
	 * @return void
	 */
	public function handle_scheduled_submit( string $url, string $action, array $engines ): void {
		$this->queue->add( $url, $action, $engines );
	}
}
