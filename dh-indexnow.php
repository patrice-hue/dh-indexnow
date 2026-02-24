<?php
/**
 * Plugin Name: DH IndexNow
 * Plugin URI:  https://github.com/patrice-hue/dh-indexnow
 * Description: Instant URL submission to Bing (IndexNow) and Google (Indexing API) with automatic triggers and manual bulk-submission UI.
 * Version:     1.0.0
 * Author:      DH
 * Author URI:  https://github.com/patrice-hue
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dh-indexnow
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package DH\IndexNow
 */

namespace DH\IndexNow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DH_INDEXNOW_VERSION', '1.0.0' );
define( 'DH_INDEXNOW_FILE', __FILE__ );
define( 'DH_INDEXNOW_DIR', plugin_dir_path( __FILE__ ) );
define( 'DH_INDEXNOW_URL', plugin_dir_url( __FILE__ ) );
define( 'DH_INDEXNOW_BASENAME', plugin_basename( __FILE__ ) );

// Autoload classes.
spl_autoload_register( function ( string $class ): void {
	$prefix = 'DH\\IndexNow\\';
	if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
		return;
	}

	$relative = substr( $class, strlen( $prefix ) );
	$parts    = explode( '\\', $relative );
	$filename = 'class-' . strtolower( str_replace( '_', '-', array_pop( $parts ) ) ) . '.php';

	$subdirs = array_map( 'strtolower', $parts );
	$path    = DH_INDEXNOW_DIR . implode( DIRECTORY_SEPARATOR, array_merge( $subdirs, array( $filename ) ) );

	// Try includes/ first, then admin/.
	$candidates = array(
		DH_INDEXNOW_DIR . 'includes' . DIRECTORY_SEPARATOR . $filename,
		DH_INDEXNOW_DIR . 'admin' . DIRECTORY_SEPARATOR . $filename,
	);

	if ( file_exists( $path ) ) {
		require_once $path;
		return;
	}

	foreach ( $candidates as $candidate ) {
		if ( file_exists( $candidate ) ) {
			require_once $candidate;
			return;
		}
	}
} );

// Activation / Deactivation hooks.
register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Deactivator::class, 'deactivate' ) );

/**
 * Boot the plugin after all plugins are loaded.
 *
 * @return void
 */
function dh_indexnow_init(): void {
	// Settings.
	$settings = new Settings();
	$settings->init();

	// Queue processor.
	$queue = new Queue();
	$queue->init();

	// Automatic triggers.
	$triggers = new Triggers( $queue, $settings );
	$triggers->init();

	// Admin UI.
	if ( is_admin() ) {
		$admin_page = new Admin\Admin_Page( $settings );
		$admin_page->init();

		$ajax = new Admin\Ajax( $settings, $queue );
		$ajax->init();
	}

	// Serve key verification file header.
	add_action( 'init', function (): void {
		$key = get_option( 'dh_indexnow_api_key', '' );
		if ( empty( $key ) ) {
			return;
		}
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( '/' . $key . '.txt' === parse_url( $request_uri, PHP_URL_PATH ) ) {
			header( 'Content-Type: text/plain' );
			header( 'X-Robots-Tag: noindex' );
			echo esc_html( $key );
			exit;
		}
	} );
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\dh_indexnow_init' );
