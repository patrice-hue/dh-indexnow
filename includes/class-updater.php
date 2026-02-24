<?php
/**
 * GitHub-based plugin updater.
 *
 * Checks a GitHub repository for new releases and integrates with the
 * WordPress plugin update system so that updates appear on the standard
 * Plugins → Updates screen and can be installed with one click.
 *
 * @package DH\IndexNow
 */

namespace DH\IndexNow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles self-update from GitHub releases.
 */
class Updater {

	/**
	 * GitHub repository owner/name.
	 *
	 * @var string
	 */
	private string $repo;

	/**
	 * Current plugin version.
	 *
	 * @var string
	 */
	private string $current_version;

	/**
	 * Plugin basename (e.g. dh-indexnow/dh-indexnow.php).
	 *
	 * @var string
	 */
	private string $plugin_basename;

	/**
	 * Plugin slug (directory name).
	 *
	 * @var string
	 */
	private string $plugin_slug;

	/**
	 * Transient key used to cache the GitHub API response.
	 *
	 * @var string
	 */
	private const CACHE_KEY = 'dh_indexnow_github_update';

	/**
	 * Cache lifetime in seconds (12 hours).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 43200;

	/**
	 * Constructor.
	 *
	 * @param string $repo            GitHub owner/repo (e.g. "patrice-hue/dh-indexnow").
	 * @param string $current_version Current installed version.
	 * @param string $plugin_basename Plugin basename.
	 */
	public function __construct( string $repo, string $current_version, string $plugin_basename ) {
		$this->repo            = $repo;
		$this->current_version = $current_version;
		$this->plugin_basename = $plugin_basename;
		$this->plugin_slug     = dirname( $plugin_basename );
	}

	/**
	 * Register WordPress hooks for the update system.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'post_install' ), 10, 3 );
		add_filter( 'plugin_action_links_' . $this->plugin_basename, array( $this, 'add_check_update_link' ) );
		add_action( 'admin_init', array( $this, 'handle_check_update' ) );
		add_action( 'admin_notices', array( $this, 'check_update_notice' ) );
	}

	/**
	 * Inject update data into the WordPress update transient.
	 *
	 * Hooked to `pre_set_site_transient_update_plugins`.
	 *
	 * @param object $transient The update_plugins transient object.
	 * @return object Modified transient.
	 */
	public function check_for_update( object $transient ): object {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( null === $release ) {
			return $transient;
		}

		$remote_version = ltrim( $release['tag_name'], 'vV' );

		if ( version_compare( $remote_version, $this->current_version, '>' ) ) {
			$transient->response[ $this->plugin_basename ] = (object) array(
				'slug'        => $this->plugin_slug,
				'plugin'      => $this->plugin_basename,
				'new_version' => $remote_version,
				'url'         => $release['html_url'],
				'package'     => $release['zipball_url'],
				'icons'       => array(),
				'banners'     => array(),
				'tested'      => '',
				'requires'    => '6.0',
				'requires_php' => '8.0',
			);
		}

		return $transient;
	}

	/**
	 * Provide plugin details for the WordPress "View Details" modal.
	 *
	 * Hooked to `plugins_api`.
	 *
	 * @param false|object|array $result Default result.
	 * @param string             $action API action (e.g. "plugin_information").
	 * @param object             $args   Request arguments.
	 * @return false|object Plugin info or false to use default.
	 */
	public function plugin_info( false|object|array $result, string $action, object $args ): false|object {
		if ( 'plugin_information' !== $action || ( $args->slug ?? '' ) !== $this->plugin_slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( null === $release ) {
			return $result;
		}

		$remote_version = ltrim( $release['tag_name'], 'vV' );

		return (object) array(
			'name'          => 'DH IndexNow',
			'slug'          => $this->plugin_slug,
			'version'       => $remote_version,
			'author'        => '<a href="https://github.com/patrice-hue">DH</a>',
			'homepage'      => 'https://github.com/' . $this->repo,
			'download_link' => $release['zipball_url'],
			'requires'      => '6.0',
			'requires_php'  => '8.0',
			'tested'        => '',
			'last_updated'  => $release['published_at'] ?? '',
			'sections'      => array(
				'description' => 'Instant URL submission to Bing (IndexNow) and Google (Indexing API) with automatic triggers and manual bulk-submission UI.',
				'changelog'   => nl2br( esc_html( $release['body'] ?? '' ) ),
			),
		);
	}

	/**
	 * Fix the directory name after WordPress extracts the GitHub zipball.
	 *
	 * GitHub zipballs extract to "owner-repo-hash/" instead of the plugin slug,
	 * so we rename the directory to match the expected plugin folder name.
	 *
	 * Hooked to `upgrader_post_install`.
	 *
	 * @param bool  $response   Install response.
	 * @param array $hook_extra Extra info about the install.
	 * @param array $result     Install result with destination paths.
	 * @return bool|\WP_Error Modified result or error.
	 */
	public function post_install( bool $response, array $hook_extra, array $result ): bool|\WP_Error {
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
			return $response;
		}

		global $wp_filesystem;

		$plugin_dir  = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $this->plugin_slug;
		$source      = $result['destination'];

		// Rename the extracted folder to the correct plugin slug.
		if ( $source !== $plugin_dir ) {
			$wp_filesystem->move( $source, $plugin_dir );
		}

		// Re-activate the plugin if it was active.
		if ( is_plugin_active( $this->plugin_basename ) ) {
			activate_plugin( $this->plugin_basename );
		}

		return $response;
	}

	/**
	 * Add a "Check for Update" link to the plugin action links.
	 *
	 * @param array $links Existing action links.
	 * @return array Modified action links.
	 */
	public function add_check_update_link( array $links ): array {
		$url = wp_nonce_url(
			admin_url( 'plugins.php?dh_indexnow_check_update=1' ),
			'dh_indexnow_check_update'
		);
		$links['check_update'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Check for Update', 'dh-indexnow' ) . '</a>';
		return $links;
	}

	/**
	 * Handle the manual "Check for Update" action.
	 *
	 * @return void
	 */
	public function handle_check_update(): void {
		if ( ! isset( $_GET['dh_indexnow_check_update'] ) ) {
			return;
		}

		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		check_admin_referer( 'dh_indexnow_check_update' );

		self::clear_cache();
		wp_update_plugins();

		$has_update  = false;
		$update_data = get_site_transient( 'update_plugins' );
		if ( isset( $update_data->response[ $this->plugin_basename ] ) ) {
			$has_update = true;
		}

		wp_safe_redirect( admin_url( 'plugins.php?dh_indexnow_checked=1&dh_indexnow_has_update=' . ( $has_update ? '1' : '0' ) ) );
		exit;
	}

	/**
	 * Display an admin notice after a manual update check.
	 *
	 * @return void
	 */
	public function check_update_notice(): void {
		if ( ! isset( $_GET['dh_indexnow_checked'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$has_update = ! empty( $_GET['dh_indexnow_has_update'] );
		$class      = $has_update ? 'notice-warning' : 'notice-success';
		$message    = $has_update
			? __( 'DH IndexNow: A new version is available! You can update it above.', 'dh-indexnow' )
			: __( 'DH IndexNow: You are running the latest version.', 'dh-indexnow' );

		printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	/**
	 * Fetch the latest release from the GitHub API (cached).
	 *
	 * @return array|null Release data or null on failure.
	 */
	private function get_latest_release(): ?array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && ! empty( $cached['tag_name'] ) ) {
			return $cached;
		}
		// If the transient is a non-false placeholder (e.g. from a cached failure), skip the API call.
		if ( false !== $cached ) {
			return null;
		}

		$url  = 'https://api.github.com/repos/' . $this->repo . '/releases/latest';
		$args = array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github.v3+json',
				'User-Agent' => 'DH-IndexNow/' . $this->current_version,
			),
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			// Cache failure briefly (15 min) to avoid hammering.
			set_transient( self::CACHE_KEY, 'error', 900 );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['tag_name'] ) ) {
			return null;
		}

		set_transient( self::CACHE_KEY, $body, self::CACHE_TTL );

		return $body;
	}

	/**
	 * Delete the cached release data.
	 *
	 * Useful when the user manually checks for updates.
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		delete_transient( self::CACHE_KEY );
	}
}
