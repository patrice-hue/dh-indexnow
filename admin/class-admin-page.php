<?php
/**
 * Admin page controller.
 *
 * @package DH\IndexNow
 */

namespace DH\IndexNow\Admin;

use DH\IndexNow\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the settings page and handles tab rendering.
 */
class Admin_Page {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add the settings page under the Settings menu.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		add_options_page(
			__( 'DH IndexNow', 'dh-indexnow' ),
			__( 'DH IndexNow', 'dh-indexnow' ),
			'manage_options',
			'dh-indexnow',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin CSS and JS on the plugin settings page only.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'settings_page_dh-indexnow' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'dh-indexnow-admin',
			DH_INDEXNOW_URL . 'assets/css/admin.css',
			array(),
			DH_INDEXNOW_VERSION
		);

		wp_enqueue_script(
			'dh-indexnow-admin',
			DH_INDEXNOW_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			DH_INDEXNOW_VERSION,
			true
		);

		wp_localize_script( 'dh-indexnow-admin', 'dhIndexNow', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'dh_indexnow_ajax' ),
			'i18n'    => array(
				'submitting'  => __( 'Submitting...', 'dh-indexnow' ),
				'submit'      => __( 'Submit URLs', 'dh-indexnow' ),
				'submitAll'   => __( 'Submit All', 'dh-indexnow' ),
				'processing'  => __( 'Processing...', 'dh-indexnow' ),
				'cleared'     => __( 'Logs cleared.', 'dh-indexnow' ),
				'error'       => __( 'An error occurred.', 'dh-indexnow' ),
				'confirmClear' => __( 'Are you sure you want to clear all logs?', 'dh-indexnow' ),
			),
		) );
	}

	/**
	 * Render the settings page with tabs.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tabs = array(
			'general' => __( 'General', 'dh-indexnow' ),
			'manual'  => __( 'Manual Submit', 'dh-indexnow' ),
			'logs'    => __( 'Logs', 'dh-indexnow' ),
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		if ( ! array_key_exists( $active_tab, $tabs ) ) {
			$active_tab = 'general';
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'DH IndexNow', 'dh-indexnow' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'dh-indexnow', 'tab' => $tab_key ), admin_url( 'options-general.php' ) ) ); ?>"
					   class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="dh-indexnow-tab-content">
				<?php
				switch ( $active_tab ) {
					case 'manual':
						include DH_INDEXNOW_DIR . 'admin/views/settings-manual.php';
						break;
					case 'logs':
						include DH_INDEXNOW_DIR . 'admin/views/settings-logs.php';
						break;
					default:
						include DH_INDEXNOW_DIR . 'admin/views/settings-general.php';
						break;
				}
				?>
			</div>
		</div>
		<?php
	}
}
