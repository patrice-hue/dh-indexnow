<?php
/**
 * Manual Submit tab view.
 *
 * @package DH\IndexNow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$all_post_types = get_post_types( array( 'public' => true ), 'objects' );
?>

<div class="dh-indexnow-manual-submit">
	<h2><?php esc_html_e( 'Manual URL Submission', 'dh-indexnow' ); ?></h2>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="dh-indexnow-urls"><?php esc_html_e( 'URLs', 'dh-indexnow' ); ?></label>
			</th>
			<td>
				<textarea id="dh-indexnow-urls" rows="6" class="large-text code"
						  placeholder="<?php esc_attr_e( "https://example.com/page-1\nhttps://example.com/page-2", 'dh-indexnow' ); ?>"></textarea>
				<p class="description"><?php esc_html_e( 'One URL per line, or comma-separated.', 'dh-indexnow' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Action', 'dh-indexnow' ); ?></th>
			<td>
				<fieldset id="dh-indexnow-action">
					<label>
						<input type="radio" name="submit_action" value="updated" checked />
						<?php esc_html_e( 'URL Updated — Notify search engines of new or updated content', 'dh-indexnow' ); ?>
					</label><br />
					<label>
						<input type="radio" name="submit_action" value="deleted" />
						<?php esc_html_e( 'URL Deleted — Request removal from search engines (Google only)', 'dh-indexnow' ); ?>
					</label>
				</fieldset>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Submit To', 'dh-indexnow' ); ?></th>
			<td>
				<fieldset id="dh-indexnow-engines">
					<label>
						<input type="checkbox" name="engines[]" value="bing" checked />
						<?php esc_html_e( 'Bing (IndexNow)', 'dh-indexnow' ); ?>
					</label><br />
					<label>
						<input type="checkbox" name="engines[]" value="google" checked />
						<?php esc_html_e( 'Google (Indexing API)', 'dh-indexnow' ); ?>
					</label>
				</fieldset>
			</td>
		</tr>

		<tr>
			<td colspan="2">
				<button type="button" id="dh-indexnow-submit-btn" class="button button-primary">
					<?php esc_html_e( 'Submit URLs', 'dh-indexnow' ); ?>
				</button>
				<span id="dh-indexnow-submit-spinner" class="spinner"></span>
			</td>
		</tr>
	</table>

	<div id="dh-indexnow-results" style="display:none;">
		<h3><?php esc_html_e( 'Results', 'dh-indexnow' ); ?></h3>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'URL', 'dh-indexnow' ); ?></th>
					<th><?php esc_html_e( 'Engine', 'dh-indexnow' ); ?></th>
					<th><?php esc_html_e( 'Action', 'dh-indexnow' ); ?></th>
					<th><?php esc_html_e( 'Status', 'dh-indexnow' ); ?></th>
					<th><?php esc_html_e( 'HTTP Code', 'dh-indexnow' ); ?></th>
					<th><?php esc_html_e( 'Timestamp', 'dh-indexnow' ); ?></th>
				</tr>
			</thead>
			<tbody id="dh-indexnow-results-body"></tbody>
		</table>
	</div>
</div>

<hr />

<div class="dh-indexnow-bulk-submit">
	<h2><?php esc_html_e( 'Bulk Submit by Post Type', 'dh-indexnow' ); ?></h2>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="dh-indexnow-bulk-post-type"><?php esc_html_e( 'Post Type', 'dh-indexnow' ); ?></label>
			</th>
			<td>
				<select id="dh-indexnow-bulk-post-type">
					<?php foreach ( $all_post_types as $pt ) : ?>
						<option value="<?php echo esc_attr( $pt->name ); ?>">
							<?php echo esc_html( $pt->labels->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Action', 'dh-indexnow' ); ?></th>
			<td>
				<fieldset id="dh-indexnow-bulk-action">
					<label>
						<input type="radio" name="bulk_submit_action" value="updated" checked />
						<?php esc_html_e( 'URL Updated', 'dh-indexnow' ); ?>
					</label><br />
					<label>
						<input type="radio" name="bulk_submit_action" value="deleted" />
						<?php esc_html_e( 'URL Deleted (Google only)', 'dh-indexnow' ); ?>
					</label>
				</fieldset>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Submit To', 'dh-indexnow' ); ?></th>
			<td>
				<fieldset id="dh-indexnow-bulk-engines">
					<label>
						<input type="checkbox" name="bulk_engines[]" value="bing" checked />
						<?php esc_html_e( 'Bing (IndexNow)', 'dh-indexnow' ); ?>
					</label><br />
					<label>
						<input type="checkbox" name="bulk_engines[]" value="google" checked />
						<?php esc_html_e( 'Google (Indexing API)', 'dh-indexnow' ); ?>
					</label>
				</fieldset>
			</td>
		</tr>

		<tr>
			<td colspan="2">
				<button type="button" id="dh-indexnow-bulk-btn" class="button button-primary">
					<?php esc_html_e( 'Submit All', 'dh-indexnow' ); ?>
				</button>
				<span id="dh-indexnow-bulk-spinner" class="spinner"></span>
				<span id="dh-indexnow-bulk-message" class="dh-indexnow-message"></span>
			</td>
		</tr>
	</table>
</div>
