<?php
/**
 * General settings tab view.
 *
 * @package DH\IndexNow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$api_key      = get_option( 'dh_indexnow_api_key', '' );
$post_types   = get_option( 'dh_indexnow_post_types', array( 'post', 'page' ) );
$exclude_urls = get_option( 'dh_indexnow_exclude_urls', '' );
$batch_size   = get_option( 'dh_indexnow_batch_size', 100 );
$auto_submit  = get_option( 'dh_indexnow_auto_submit', '1' );
$github_repo  = get_option( 'dh_indexnow_github_repo', 'patrice-hue/dh-indexnow' );
$github_token = get_option( 'dh_indexnow_github_token', '' );

// Check key file status.
$key_file_url    = home_url( '/' . $api_key . '.txt' );
$key_file_exists = ! empty( $api_key ) && file_exists( ABSPATH . $api_key . '.txt' );

// Get all public post types.
$all_post_types = get_post_types( array( 'public' => true ), 'objects' );

// Check if Google credentials are configured.
$google_configured = ! empty( get_option( 'dh_indexnow_google_credentials', '' ) );
?>

<form method="post" action="options.php">
	<?php settings_fields( 'dh_indexnow_general' ); ?>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="dh_indexnow_api_key"><?php esc_html_e( 'IndexNow API Key', 'dh-indexnow' ); ?></label>
			</th>
			<td>
				<input type="text" id="dh_indexnow_api_key" name="dh_indexnow_api_key"
					   value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" />
				<p class="description">
					<?php esc_html_e( 'Key file URL:', 'dh-indexnow' ); ?>
					<code><?php echo esc_url( $key_file_url ); ?></code>
					<?php if ( $key_file_exists ) : ?>
						<span class="dh-indexnow-status dh-indexnow-status--ok">&#10004; <?php esc_html_e( 'Reachable', 'dh-indexnow' ); ?></span>
					<?php else : ?>
						<span class="dh-indexnow-status dh-indexnow-status--error">&#10008; <?php esc_html_e( 'Missing', 'dh-indexnow' ); ?></span>
					<?php endif; ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="dh_indexnow_google_credentials"><?php esc_html_e( 'Google Service Account JSON', 'dh-indexnow' ); ?></label>
			</th>
			<td>
				<textarea id="dh_indexnow_google_credentials" name="dh_indexnow_google_credentials"
						  rows="6" class="large-text code"
						  placeholder='<?php esc_attr_e( 'Paste your Google Service Account JSON here...', 'dh-indexnow' ); ?>'><?php
						  // Do not output the encrypted value — show placeholder when configured.
						  ?></textarea>
				<p class="description">
					<?php if ( $google_configured ) : ?>
						<span class="dh-indexnow-status dh-indexnow-status--ok">&#10004; <?php esc_html_e( 'Credentials configured. Paste new JSON to replace.', 'dh-indexnow' ); ?></span>
					<?php else : ?>
						<?php esc_html_e( 'Paste the full JSON file content from your Google Cloud service account. Stored encrypted.', 'dh-indexnow' ); ?>
					<?php endif; ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Post Types to Index', 'dh-indexnow' ); ?></th>
			<td>
				<fieldset>
					<?php foreach ( $all_post_types as $pt ) : ?>
						<label>
							<input type="checkbox" name="dh_indexnow_post_types[]"
								   value="<?php echo esc_attr( $pt->name ); ?>"
								   <?php checked( in_array( $pt->name, $post_types, true ) ); ?> />
							<?php echo esc_html( $pt->labels->name ); ?>
						</label><br />
					<?php endforeach; ?>
				</fieldset>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="dh_indexnow_exclude_urls"><?php esc_html_e( 'Exclude URLs', 'dh-indexnow' ); ?></label>
			</th>
			<td>
				<textarea id="dh_indexnow_exclude_urls" name="dh_indexnow_exclude_urls"
						  rows="4" class="large-text code"><?php echo esc_textarea( $exclude_urls ); ?></textarea>
				<p class="description"><?php esc_html_e( 'One URL per line. These URLs will be excluded from automatic submission.', 'dh-indexnow' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="dh_indexnow_batch_size"><?php esc_html_e( 'Batch Size', 'dh-indexnow' ); ?></label>
			</th>
			<td>
				<input type="number" id="dh_indexnow_batch_size" name="dh_indexnow_batch_size"
					   value="<?php echo esc_attr( $batch_size ); ?>" min="1" max="100" class="small-text" />
				<p class="description"><?php esc_html_e( 'Maximum URLs per IndexNow request (max 100).', 'dh-indexnow' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Enable Auto-Submit', 'dh-indexnow' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="dh_indexnow_auto_submit" value="1"
						   <?php checked( $auto_submit, '1' ); ?> />
					<?php esc_html_e( 'Automatically submit URLs when posts are published, updated, or deleted.', 'dh-indexnow' ); ?>
				</label>
			</td>
		</tr>
	</table>

	<h2 class="title"><?php esc_html_e( 'Plugin Updates', 'dh-indexnow' ); ?></h2>
	<p class="description"><?php esc_html_e( 'The plugin checks GitHub for new releases and shows updates on the Plugins page.', 'dh-indexnow' ); ?></p>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="dh_indexnow_github_repo"><?php esc_html_e( 'GitHub Repository', 'dh-indexnow' ); ?></label>
			</th>
			<td>
				<input type="text" id="dh_indexnow_github_repo" name="dh_indexnow_github_repo"
					   value="<?php echo esc_attr( $github_repo ); ?>" class="regular-text"
					   placeholder="owner/repo" />
				<p class="description"><?php esc_html_e( 'Format: owner/repo (e.g. patrice-hue/dh-indexnow). The plugin will check this repository for new releases.', 'dh-indexnow' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="dh_indexnow_github_token"><?php esc_html_e( 'GitHub Access Token', 'dh-indexnow' ); ?></label>
			</th>
			<td>
				<input type="password" id="dh_indexnow_github_token" name="dh_indexnow_github_token"
					   value="<?php echo esc_attr( $github_token ); ?>" class="regular-text"
					   autocomplete="off" />
				<p class="description"><?php esc_html_e( 'Optional. Required only for private repositories. Use a GitHub Personal Access Token with repo read access.', 'dh-indexnow' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Current Version', 'dh-indexnow' ); ?></th>
			<td>
				<code><?php echo esc_html( DH_INDEXNOW_VERSION ); ?></code>
			</td>
		</tr>
	</table>

	<?php submit_button(); ?>
</form>
