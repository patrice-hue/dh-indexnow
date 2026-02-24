<?php
/**
 * Configuration guide tab view.
 *
 * @package DH\IndexNow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$api_key            = get_option( 'dh_indexnow_api_key', '' );
$key_file_url       = ! empty( $api_key ) ? home_url( '/' . $api_key . '.txt' ) : '';
$key_file_exists    = ! empty( $api_key ) && file_exists( ABSPATH . $api_key . '.txt' );
$google_configured  = ! empty( get_option( 'dh_indexnow_google_credentials', '' ) );
$auto_submit        = get_option( 'dh_indexnow_auto_submit', '1' );
$post_types         = get_option( 'dh_indexnow_post_types', array( 'post', 'page' ) );
$cron_scheduled     = (bool) wp_next_scheduled( 'dh_indexnow_process_queue' );
?>

<div class="dh-indexnow-configuration">

	<!-- Status Overview -->
	<h2><?php esc_html_e( 'Status Overview', 'dh-indexnow' ); ?></h2>
	<table class="widefat striped dh-indexnow-status-table">
		<tbody>
			<tr>
				<td><strong><?php esc_html_e( 'Plugin Version', 'dh-indexnow' ); ?></strong></td>
				<td><?php echo esc_html( DH_INDEXNOW_VERSION ); ?></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'IndexNow API Key', 'dh-indexnow' ); ?></strong></td>
				<td>
					<?php if ( ! empty( $api_key ) ) : ?>
						<code><?php echo esc_html( $api_key ); ?></code>
						<span class="dh-indexnow-status dh-indexnow-status--ok">&#10004;</span>
					<?php else : ?>
						<span class="dh-indexnow-status dh-indexnow-status--error">&#10008; <?php esc_html_e( 'Not generated', 'dh-indexnow' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Key Verification File', 'dh-indexnow' ); ?></strong></td>
				<td>
					<?php if ( ! empty( $key_file_url ) ) : ?>
						<code><?php echo esc_url( $key_file_url ); ?></code>
						<?php if ( $key_file_exists ) : ?>
							<span class="dh-indexnow-status dh-indexnow-status--ok">&#10004; <?php esc_html_e( 'Exists', 'dh-indexnow' ); ?></span>
						<?php else : ?>
							<span class="dh-indexnow-status dh-indexnow-status--error">&#10008; <?php esc_html_e( 'Missing', 'dh-indexnow' ); ?></span>
						<?php endif; ?>
					<?php else : ?>
						<span class="dh-indexnow-status dh-indexnow-status--error">&#10008; <?php esc_html_e( 'No API key set', 'dh-indexnow' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Google Service Account', 'dh-indexnow' ); ?></strong></td>
				<td>
					<?php if ( $google_configured ) : ?>
						<span class="dh-indexnow-status dh-indexnow-status--ok">&#10004; <?php esc_html_e( 'Configured', 'dh-indexnow' ); ?></span>
					<?php else : ?>
						<span class="dh-indexnow-status dh-indexnow-status--error">&#10008; <?php esc_html_e( 'Not configured', 'dh-indexnow' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Auto-Submit', 'dh-indexnow' ); ?></strong></td>
				<td>
					<?php if ( '1' === $auto_submit ) : ?>
						<span class="dh-indexnow-status dh-indexnow-status--ok">&#10004; <?php esc_html_e( 'Enabled', 'dh-indexnow' ); ?></span>
					<?php else : ?>
						<span class="dh-indexnow-status dh-indexnow-status--error">&#10008; <?php esc_html_e( 'Disabled', 'dh-indexnow' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Monitored Post Types', 'dh-indexnow' ); ?></strong></td>
				<td><?php echo esc_html( implode( ', ', $post_types ) ); ?></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Queue Cron Job', 'dh-indexnow' ); ?></strong></td>
				<td>
					<?php if ( $cron_scheduled ) : ?>
						<span class="dh-indexnow-status dh-indexnow-status--ok">&#10004; <?php esc_html_e( 'Scheduled (every 5 minutes)', 'dh-indexnow' ); ?></span>
					<?php else : ?>
						<span class="dh-indexnow-status dh-indexnow-status--error">&#10008; <?php esc_html_e( 'Not scheduled', 'dh-indexnow' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'WP-Cron', 'dh-indexnow' ); ?></strong></td>
				<td>
					<?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) : ?>
						<span class="dh-indexnow-status dh-indexnow-status--error">&#10008; <?php esc_html_e( 'Disabled (DISABLE_WP_CRON is true) — set up a real cron job', 'dh-indexnow' ); ?></span>
					<?php else : ?>
						<span class="dh-indexnow-status dh-indexnow-status--ok">&#10004; <?php esc_html_e( 'Enabled', 'dh-indexnow' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>

	<!-- Bing / IndexNow Setup -->
	<hr />
	<h2><?php esc_html_e( 'Bing / IndexNow Setup', 'dh-indexnow' ); ?></h2>
	<p><?php esc_html_e( 'IndexNow allows instant URL submission to Bing, Yandex, and other participating search engines. This plugin handles everything automatically.', 'dh-indexnow' ); ?></p>

	<h3><?php esc_html_e( 'How It Works', 'dh-indexnow' ); ?></h3>
	<ol>
		<li><?php esc_html_e( 'On activation, the plugin generates a unique 32-character API key and writes a verification file to your site root.', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'When you publish, update, or delete a post, the plugin queues the URL for submission.', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'A WP-Cron job processes the queue every 5 minutes, sending batched POST requests to the IndexNow API.', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'Search engines verify your key file, then crawl the submitted URLs.', 'dh-indexnow' ); ?></li>
	</ol>

	<h3><?php esc_html_e( 'Verifying in Bing Webmaster Tools', 'dh-indexnow' ); ?></h3>
	<ol>
		<li><?php esc_html_e( 'Go to Bing Webmaster Tools and add your site if not already added.', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'Navigate to Settings > IndexNow to confirm submitted URLs.', 'dh-indexnow' ); ?></li>
		<li>
			<?php esc_html_e( 'Verify the key file is accessible by visiting:', 'dh-indexnow' ); ?>
			<?php if ( ! empty( $key_file_url ) ) : ?>
				<code><?php echo esc_url( $key_file_url ); ?></code>
			<?php endif; ?>
		</li>
	</ol>

	<h3><?php esc_html_e( 'HTTP Response Codes', 'dh-indexnow' ); ?></h3>
	<table class="widefat striped dh-indexnow-status-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Code', 'dh-indexnow' ); ?></th>
				<th><?php esc_html_e( 'Meaning', 'dh-indexnow' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr><td><code>200</code></td><td><?php esc_html_e( 'OK — URL submitted successfully (URL was already known).', 'dh-indexnow' ); ?></td></tr>
			<tr><td><code>202</code></td><td><?php esc_html_e( 'Accepted — URL received and will be crawled.', 'dh-indexnow' ); ?></td></tr>
			<tr><td><code>400</code></td><td><?php esc_html_e( 'Bad Request — Invalid request format.', 'dh-indexnow' ); ?></td></tr>
			<tr><td><code>403</code></td><td><?php esc_html_e( 'Forbidden — Key does not match the key file or key file not found.', 'dh-indexnow' ); ?></td></tr>
			<tr><td><code>422</code></td><td><?php esc_html_e( 'Unprocessable — URL does not belong to the host or is not valid.', 'dh-indexnow' ); ?></td></tr>
			<tr><td><code>429</code></td><td><?php esc_html_e( 'Too Many Requests — Rate limited. The plugin will retry automatically.', 'dh-indexnow' ); ?></td></tr>
		</tbody>
	</table>

	<!-- Google Indexing API Setup -->
	<hr />
	<h2><?php esc_html_e( 'Google Indexing API Setup', 'dh-indexnow' ); ?></h2>
	<p><?php esc_html_e( 'The Google Indexing API lets you notify Google directly about new or removed URLs. It requires a Google Cloud service account.', 'dh-indexnow' ); ?></p>

	<h3><?php esc_html_e( 'Step 1 — Create a Google Cloud Project', 'dh-indexnow' ); ?></h3>
	<ol>
		<li><?php esc_html_e( 'Go to the Google Cloud Console (console.cloud.google.com).', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'Create a new project or select an existing one.', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'Navigate to "APIs & Services" > "Library".', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'Search for "Web Search Indexing API" and enable it.', 'dh-indexnow' ); ?></li>
	</ol>

	<h3><?php esc_html_e( 'Step 2 — Create a Service Account', 'dh-indexnow' ); ?></h3>
	<ol>
		<li><?php esc_html_e( 'Navigate to "IAM & Admin" > "Service Accounts".', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'Click "Create Service Account".', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'Give it a name (e.g., "IndexNow Plugin") and click "Create and Continue".', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'Skip the optional permissions step and click "Done".', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'Click on the newly created service account.', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'Go to the "Keys" tab and click "Add Key" > "Create New Key".', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'Choose JSON format and download the file.', 'dh-indexnow' ); ?></li>
	</ol>

	<h3><?php esc_html_e( 'Step 3 — Add the Service Account to Google Search Console', 'dh-indexnow' ); ?></h3>
	<ol>
		<li><?php esc_html_e( 'Open Google Search Console (search.google.com/search-console).', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'Select your property (site).', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'Go to "Settings" > "Users and permissions".', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'Click "Add User".', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'Enter the service account email (the "client_email" from the JSON file — looks like name@project.iam.gserviceaccount.com).', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'Set permission to "Owner" and click "Add".', 'dh-indexnow' ); ?></li>
	</ol>

	<h3><?php esc_html_e( 'Step 4 — Paste the JSON in the Plugin', 'dh-indexnow' ); ?></h3>
	<ol>
		<li><?php esc_html_e( 'Go to the General tab above.', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'Paste the entire contents of the downloaded JSON file into the "Google Service Account JSON" field.', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'Click "Save Changes". The JSON is stored encrypted in the database.', 'dh-indexnow' ); ?></li>
	</ol>

	<h3><?php esc_html_e( 'Google HTTP Response Codes', 'dh-indexnow' ); ?></h3>
	<table class="widefat striped dh-indexnow-status-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Code', 'dh-indexnow' ); ?></th>
				<th><?php esc_html_e( 'Meaning', 'dh-indexnow' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr><td><code>200</code></td><td><?php esc_html_e( 'OK — URL notification published successfully.', 'dh-indexnow' ); ?></td></tr>
			<tr><td><code>403</code></td><td><?php esc_html_e( 'Forbidden — The service account does not have Owner permission in Search Console.', 'dh-indexnow' ); ?></td></tr>
			<tr><td><code>429</code></td><td><?php esc_html_e( 'Quota exceeded — Google limits to ~200 requests/day. The plugin will retry.', 'dh-indexnow' ); ?></td></tr>
			<tr><td><code>0</code></td><td><?php esc_html_e( 'Connection error — Check that your server can reach googleapis.com. Could also indicate an invalid private key.', 'dh-indexnow' ); ?></td></tr>
		</tbody>
	</table>

	<!-- Auto-Submit & Queue -->
	<hr />
	<h2><?php esc_html_e( 'Auto-Submit & Queue Processing', 'dh-indexnow' ); ?></h2>

	<h3><?php esc_html_e( 'Automatic Triggers', 'dh-indexnow' ); ?></h3>
	<p><?php esc_html_e( 'When "Enable Auto-Submit" is checked on the General tab, the plugin automatically queues URLs when:', 'dh-indexnow' ); ?></p>
	<ul class="ul-disc">
		<li><?php esc_html_e( 'A post is published (new or updated) — submitted to both Bing and Google as URL_UPDATED.', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'A published post is deleted — submitted to Google only as URL_DELETED (IndexNow does not support deletions).', 'dh-indexnow' ); ?></li>
	</ul>
	<p><?php esc_html_e( 'Only post types selected in the "Post Types to Index" setting are monitored.', 'dh-indexnow' ); ?></p>

	<h3><?php esc_html_e( 'Queue Processing', 'dh-indexnow' ); ?></h3>
	<ul class="ul-disc">
		<li><?php esc_html_e( 'Queued URLs are processed every 5 minutes by WP-Cron.', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'Up to 200 URLs are processed per cron run.', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'Bing URLs are submitted in batches (configured via Batch Size on the General tab, max 100 per request).', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'Google URLs are submitted one at a time (API requirement).', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'Failed submissions are retried up to 3 times before being marked as failed.', 'dh-indexnow' ); ?></li>
	</ul>

	<h3><?php esc_html_e( 'Manual & Bulk Submit', 'dh-indexnow' ); ?></h3>
	<ul class="ul-disc">
		<li><?php esc_html_e( 'Manual Submit (on the Manual Submit tab) sends URLs immediately — results appear right away.', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'Bulk Submit queues all published posts of a post type for processing by WP-Cron.', 'dh-indexnow' ); ?></li>
	</ul>

	<!-- WP-Cron Troubleshooting -->
	<hr />
	<h2><?php esc_html_e( 'WP-Cron Troubleshooting', 'dh-indexnow' ); ?></h2>
	<p><?php esc_html_e( 'WP-Cron is triggered by site visitors. On low-traffic sites, the queue may not process promptly.', 'dh-indexnow' ); ?></p>

	<h3><?php esc_html_e( 'Setting Up a Real Cron Job', 'dh-indexnow' ); ?></h3>
	<p><?php esc_html_e( 'For reliable processing, disable WP-Cron and use a real server cron job:', 'dh-indexnow' ); ?></p>
	<ol>
		<li>
			<?php esc_html_e( 'Add this to wp-config.php:', 'dh-indexnow' ); ?>
			<pre><code>define( 'DISABLE_WP_CRON', true );</code></pre>
		</li>
		<li>
			<?php esc_html_e( 'Set up a cron job to run every 5 minutes:', 'dh-indexnow' ); ?>
			<pre><code>*/5 * * * * wget -q -O /dev/null <?php echo esc_url( site_url( '/wp-cron.php?doing_wp_cron' ) ); ?></code></pre>
		</li>
	</ol>

	<!-- Self-Update -->
	<hr />
	<h2><?php esc_html_e( 'Plugin Updates', 'dh-indexnow' ); ?></h2>
	<p><?php esc_html_e( 'This plugin checks for updates from its GitHub repository automatically.', 'dh-indexnow' ); ?></p>
	<ul class="ul-disc">
		<li><?php esc_html_e( 'Updates are checked every 12 hours via the WordPress update system.', 'dh-indexnow' ); ?></li>
		<li><?php esc_html_e( 'A "Check for Update" link is available on the Plugins page for manual checks.', 'dh-indexnow' ); ?></li>
		<li>
			<?php esc_html_e( 'For private repositories, define a GitHub token in wp-config.php:', 'dh-indexnow' ); ?>
			<pre><code>define( 'DH_INDEXNOW_GITHUB_TOKEN', 'ghp_your_token_here' );</code></pre>
		</li>
	</ul>

</div>
