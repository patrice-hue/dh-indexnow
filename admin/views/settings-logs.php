<?php
/**
 * Logs tab view.
 *
 * @package DH\IndexNow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$queue = new \DH\IndexNow\Queue();

// Filters.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$filter_status = isset( $_GET['log_status'] ) ? sanitize_key( $_GET['log_status'] ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$filter_engine = isset( $_GET['log_engine'] ) ? sanitize_key( $_GET['log_engine'] ) : '';

// Pagination.
$per_page = 20;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$offset = ( $paged - 1 ) * $per_page;

$args = array(
	'status'   => $filter_status,
	'engine'   => $filter_engine,
	'per_page' => $per_page,
	'offset'   => $offset,
	'orderby'  => 'created_at',
	'order'    => 'DESC',
);

$items       = $queue->get_items( $args );
$total_items = $queue->get_total( array( 'status' => $filter_status, 'engine' => $filter_engine ) );
$total_pages = ceil( $total_items / $per_page );

$base_url = add_query_arg( array( 'page' => 'dh-indexnow', 'tab' => 'logs' ), admin_url( 'options-general.php' ) );
?>

<div class="dh-indexnow-logs">
	<div class="tablenav top">
		<div class="alignleft actions">
			<form method="get" action="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>" style="display:inline;">
				<input type="hidden" name="page" value="dh-indexnow" />
				<input type="hidden" name="tab" value="logs" />

				<select name="log_status">
					<option value=""><?php esc_html_e( 'All Statuses', 'dh-indexnow' ); ?></option>
					<option value="pending" <?php selected( $filter_status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'dh-indexnow' ); ?></option>
					<option value="done" <?php selected( $filter_status, 'done' ); ?>><?php esc_html_e( 'Done', 'dh-indexnow' ); ?></option>
					<option value="failed" <?php selected( $filter_status, 'failed' ); ?>><?php esc_html_e( 'Failed', 'dh-indexnow' ); ?></option>
				</select>

				<select name="log_engine">
					<option value=""><?php esc_html_e( 'All Engines', 'dh-indexnow' ); ?></option>
					<option value="bing" <?php selected( $filter_engine, 'bing' ); ?>><?php esc_html_e( 'Bing', 'dh-indexnow' ); ?></option>
					<option value="google" <?php selected( $filter_engine, 'google' ); ?>><?php esc_html_e( 'Google', 'dh-indexnow' ); ?></option>
				</select>

				<?php submit_button( __( 'Filter', 'dh-indexnow' ), 'secondary', 'filter', false ); ?>
			</form>
		</div>

		<div class="alignright">
			<button type="button" id="dh-indexnow-clear-logs" class="button">
				<?php esc_html_e( 'Clear Logs', 'dh-indexnow' ); ?>
			</button>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=dh_indexnow_export_csv' ), 'dh_indexnow_ajax', 'nonce' ) ); ?>"
			   class="button">
				<?php esc_html_e( 'Export CSV', 'dh-indexnow' ); ?>
			</a>
		</div>
	</div>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th class="column-date"><?php esc_html_e( 'Date', 'dh-indexnow' ); ?></th>
				<th class="column-url"><?php esc_html_e( 'URL', 'dh-indexnow' ); ?></th>
				<th class="column-engine"><?php esc_html_e( 'Engine', 'dh-indexnow' ); ?></th>
				<th class="column-action"><?php esc_html_e( 'Action', 'dh-indexnow' ); ?></th>
				<th class="column-status"><?php esc_html_e( 'Status', 'dh-indexnow' ); ?></th>
				<th class="column-http-code"><?php esc_html_e( 'HTTP Code', 'dh-indexnow' ); ?></th>
				<th class="column-response"><?php esc_html_e( 'Response', 'dh-indexnow' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $items ) ) : ?>
				<tr>
					<td colspan="7"><?php esc_html_e( 'No log entries found.', 'dh-indexnow' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $items as $item ) : ?>
					<tr>
						<td><?php echo esc_html( $item->created_at ); ?></td>
						<td class="column-url"><code><?php echo esc_html( $item->url ); ?></code></td>
						<td><?php echo esc_html( ucfirst( $item->engine ?? '—' ) ); ?></td>
						<td>
							<?php
							$action_label = ( 'deleted' === ( $item->action ?? 'updated' ) )
								? __( 'Deleted', 'dh-indexnow' )
								: __( 'Updated', 'dh-indexnow' );
							?>
							<span class="dh-indexnow-badge dh-indexnow-badge--<?php echo esc_attr( $item->action ?? 'updated' ); ?>">
								<?php echo esc_html( $action_label ); ?>
							</span>
						</td>
						<td>
							<span class="dh-indexnow-badge dh-indexnow-badge--<?php echo esc_attr( $item->status ); ?>">
								<?php echo esc_html( ucfirst( $item->status ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $item->http_code ?? '—' ); ?></td>
						<td class="column-response"><code><?php echo esc_html( mb_substr( $item->response ?? '', 0, 120 ) ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post( paginate_links( array(
					'base'      => add_query_arg( 'paged', '%#%', $base_url ),
					'format'    => '',
					'current'   => $paged,
					'total'     => $total_pages,
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
					'add_args'  => array(
						'log_status' => $filter_status,
						'log_engine' => $filter_engine,
					),
				) ) );
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
