(function ($) {
	'use strict';

	$(function () {
		if ($.fn.wpColorPicker) {
			$('.wb-color-picker').wpColorPicker();
		}

		var frame;
		$('.wb-upload-logo').on('click', function (e) {
			e.preventDefault();
			if (frame) {
				frame.open();
				return;
			}
			frame = wp.media({
				title: wbAdmin.i18n.selectLogo,
				button: { text: wbAdmin.i18n.selectLogo },
				multiple: false
			});
			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				$('.wb-logo-url').val(attachment.url);
			});
			frame.open();
		});

		$('.wb-send-test-email').on('click', function () {
			var $btn = $(this);
			var $result = $btn.siblings('.wb-test-email-result');
			var email = $btn.siblings('.wb-test-email-to').val() || $('.wb-test-email-to').first().val();
			$btn.prop('disabled', true);
			$result.removeClass('wb-test-ok wb-test-fail').text(wbAdmin.i18n.running);

			$.post(wbAdmin.ajaxUrl, {
				action: 'wb_send_test_email',
				nonce: wbAdmin.nonce,
				email: email || ''
			}).done(function (response) {
				if (response.success) {
					$result.addClass('wb-test-ok').text(wbAdmin.i18n.testSent);
				} else {
					$result.addClass('wb-test-fail').text(wbAdmin.i18n.testFailed);
				}
			}).fail(function () {
				$result.addClass('wb-test-fail').text(wbAdmin.i18n.testFailed);
			}).always(function () {
				$btn.prop('disabled', false);
			});
		});

		$('.wb-run-test').on('click', function () {
			var test = $(this).data('test');
			var email = '';
			if (test === 'smtp') {
				email = $(this).closest('tr').find('.wb-test-email-to').val() || $('.wb-test-email-to').first().val();
			}
			runTest(test, email);
		});

		$('.wb-clear-rest-logs').on('click', function () {
			var $btn = $(this);
			$btn.prop('disabled', true);
			$.post(wbAdmin.ajaxUrl, {
				action: 'wb_clear_rest_logs',
				nonce: wbAdmin.nonce
			}).done(function (response) {
				if (response.success) {
					location.reload();
				}
			}).always(function () {
				$btn.prop('disabled', false);
			});
		});

		function runTest(test, email) {
			var $results = $('.wb-test-result[data-test="' + test + '"]');
			$results.removeClass('wb-test-ok wb-test-fail').text(wbAdmin.i18n.running);

			$.post(wbAdmin.ajaxUrl, {
				action: 'wb_run_diagnostic',
				nonce: wbAdmin.nonce,
				test: test,
				email: email || ''
			}).done(function (response) {
				var msg = response.data && response.data.message ? response.data.message : '';
				if (response.success) {
					$results.addClass('wb-test-ok').text(msg || wbAdmin.i18n.testOk);
					if (response.data && response.data.checks) {
						var html = '<ul>';
						response.data.checks.forEach(function (c) {
							html += '<li>' + (c.ok ? '✓' : '✗') + ' ' + c.label + (c.note ? ' <em>(' + c.note + ')</em>' : '') + '</li>';
						});
						html += '</ul>';
						$results.append(html);
					}
				} else {
					$results.addClass('wb-test-fail').text(msg || wbAdmin.i18n.testFailed);
				}
			}).fail(function () {
				$results.addClass('wb-test-fail').text(wbAdmin.i18n.testFailed);
			});
		}

		$('.wb-send-feedback').on('click', function () {
			var $btn = $(this);
			var $result = $('.wb-feedback-result');
			$btn.prop('disabled', true);
			$result.removeClass('wb-test-ok wb-test-fail').text(wbAdmin.i18n.running);

			$.post(wbAdmin.ajaxUrl, {
				action: 'wb_send_feedback',
				nonce: wbAdmin.nonce,
				type: $('.wb-feedback-type').val(),
				email: $('.wb-feedback-email').val(),
				message: $('.wb-feedback-message').val()
			}).done(function (response) {
				if (response.success) {
					$result.addClass('wb-test-ok').text(wbAdmin.i18n.feedbackSent);
					$('.wb-feedback-message').val('');
				} else {
					var msg = response.data && response.data.message ? response.data.message : wbAdmin.i18n.feedbackFailed;
					$result.addClass('wb-test-fail').text(msg);
				}
			}).fail(function () {
				$result.addClass('wb-test-fail').text(wbAdmin.i18n.feedbackFailed);
			}).always(function () {
				$btn.prop('disabled', false);
			});
		});
	});
})(jQuery);
