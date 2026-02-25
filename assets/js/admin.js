/**
 * DH IndexNow — Admin JavaScript
 *
 * Handles AJAX submission and results rendering.
 */
(function ($) {
	'use strict';

	var i18n = dhIndexNow.i18n;

	/**
	 * Toggle engine checkboxes based on action selection.
	 *
	 * When "deleted" is selected, Bing is unchecked and disabled because
	 * the IndexNow API does not support URL deletion requests.
	 *
	 * @param {string} actionFieldset  Selector for the action radio fieldset.
	 * @param {string} enginesFieldset Selector for the engines checkbox fieldset.
	 */
	function bindActionToggle(actionFieldset, enginesFieldset) {
		$(actionFieldset).on('change', 'input[type="radio"]', function () {
			var isDeleted   = $(this).val() === 'deleted';
			var $bingCheckbox = $(enginesFieldset).find('input[value="bing"]');
			var $googleCheckbox = $(enginesFieldset).find('input[value="google"]');

			if (isDeleted) {
				$bingCheckbox.prop('checked', false).prop('disabled', true);
				$googleCheckbox.prop('checked', true);
			} else {
				$bingCheckbox.prop('disabled', false).prop('checked', true);
				$googleCheckbox.prop('checked', true);
			}
		});
	}

	/**
	 * Manual URL submission handler.
	 */
	function initManualSubmit() {
		var $btn     = $('#dh-indexnow-submit-btn');
		var $spinner = $('#dh-indexnow-submit-spinner');

		if (!$btn.length) {
			return;
		}

		bindActionToggle('#dh-indexnow-action', '#dh-indexnow-engines');

		$btn.on('click', function () {
			var urls = $('#dh-indexnow-urls').val().trim();
			if (!urls) {
				return;
			}

			var engines = [];
			$('#dh-indexnow-engines input:checked').each(function () {
				engines.push($(this).val());
			});

			if (!engines.length) {
				alert('Please select at least one engine.');
				return;
			}

			var submitAction = $('#dh-indexnow-action input[type="radio"]:checked').val() || 'updated';

			$btn.prop('disabled', true).text(i18n.submitting);
			$spinner.addClass('is-active');

			$.ajax({
				url: dhIndexNow.ajaxUrl,
				type: 'POST',
				data: {
					action: 'dh_indexnow_manual_submit',
					nonce: dhIndexNow.nonce,
					urls: urls,
					engines: engines,
					submit_action: submitAction
				},
				success: function (response) {
					if (response.success && response.data.results) {
						renderResults(response.data.results);
					} else {
						alert(response.data ? response.data.message : i18n.error);
					}
				},
				error: function () {
					alert(i18n.error);
				},
				complete: function () {
					$btn.prop('disabled', false).text(i18n.submit);
					$spinner.removeClass('is-active');
				}
			});
		});
	}

	/**
	 * Render results into the results table.
	 *
	 * @param {Array} results Array of result objects.
	 */
	function renderResults(results) {
		var $container = $('#dh-indexnow-results');
		var $body      = $('#dh-indexnow-results-body');

		$body.empty();

		$.each(results, function (_, result) {
			var statusClass = result.status === 'done' ? 'dh-indexnow-badge--done' : 'dh-indexnow-badge--failed';
			var actionLabel = result.submit_action === 'deleted' ? 'Deleted' : 'Updated';
			var row = '<tr>' +
				'<td><code>' + escapeHtml(result.url) + '</code></td>' +
				'<td>' + escapeHtml(result.engine) + '</td>' +
				'<td>' + escapeHtml(actionLabel) + '</td>' +
				'<td><span class="dh-indexnow-badge ' + statusClass + '">' + escapeHtml(result.status) + '</span></td>' +
				'<td>' + escapeHtml(String(result.http_code)) + '</td>' +
				'<td>' + escapeHtml(result.timestamp) + '</td>' +
				'</tr>';
			$body.append(row);
		});

		$container.show();
	}

	/**
	 * Bulk submit by post type handler.
	 */
	function initBulkSubmit() {
		var $btn     = $('#dh-indexnow-bulk-btn');
		var $spinner = $('#dh-indexnow-bulk-spinner');
		var $message = $('#dh-indexnow-bulk-message');

		if (!$btn.length) {
			return;
		}

		bindActionToggle('#dh-indexnow-bulk-action', '#dh-indexnow-bulk-engines');

		$btn.on('click', function () {
			var postType = $('#dh-indexnow-bulk-post-type').val();

			var engines = [];
			$('#dh-indexnow-bulk-engines input:checked').each(function () {
				engines.push($(this).val());
			});

			if (!engines.length) {
				alert('Please select at least one engine.');
				return;
			}

			var submitAction = $('#dh-indexnow-bulk-action input[type="radio"]:checked').val() || 'updated';

			$btn.prop('disabled', true).text(i18n.processing);
			$spinner.addClass('is-active');
			$message.text('');

			$.ajax({
				url: dhIndexNow.ajaxUrl,
				type: 'POST',
				data: {
					action: 'dh_indexnow_bulk_submit',
					nonce: dhIndexNow.nonce,
					post_type: postType,
					engines: engines,
					submit_action: submitAction
				},
				success: function (response) {
					if (response.success) {
						$message.text(response.data.message);
					} else {
						$message.text(response.data ? response.data.message : i18n.error);
					}
				},
				error: function () {
					$message.text(i18n.error);
				},
				complete: function () {
					$btn.prop('disabled', false).text(i18n.submitAll);
					$spinner.removeClass('is-active');
				}
			});
		});
	}

	/**
	 * Clear logs handler.
	 */
	function initClearLogs() {
		var $btn = $('#dh-indexnow-clear-logs');

		if (!$btn.length) {
			return;
		}

		$btn.on('click', function () {
			if (!confirm(i18n.confirmClear)) {
				return;
			}

			$btn.prop('disabled', true);

			$.ajax({
				url: dhIndexNow.ajaxUrl,
				type: 'POST',
				data: {
					action: 'dh_indexnow_clear_logs',
					nonce: dhIndexNow.nonce
				},
				success: function (response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data ? response.data.message : i18n.error);
					}
				},
				error: function () {
					alert(i18n.error);
				},
				complete: function () {
					$btn.prop('disabled', false);
				}
			});
		});
	}

	/**
	 * Escape HTML entities for safe DOM insertion.
	 *
	 * @param {string} str Raw string.
	 * @return {string} Escaped string.
	 */
	function escapeHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

	// Initialize on DOM ready.
	$(document).ready(function () {
		initManualSubmit();
		initBulkSubmit();
		initClearLogs();
	});

})(jQuery);
