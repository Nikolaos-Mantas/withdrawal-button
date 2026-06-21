(function ($) {
	'use strict';

	function lookupOrder() {
		if (!wbForm.wooAutofill) {
			return;
		}

		var orderNumber = $('#wb_order').val();
		if (!orderNumber || orderNumber.length < 2) {
			return;
		}

		$.post(wbForm.ajaxUrl, {
			action: 'wb_lookup_order',
			nonce: $('#wb_nonce').val(),
			order_number: orderNumber,
			email: $('#wb_email').val()
		}).done(function (response) {
			if (!response.success) {
				return;
			}
			var data = response.data;
			if (data.name) $('#wb_name').val(data.name);
			if (data.email) $('#wb_email').val(data.email);
			if (data.products) $('#wb_products').val(data.products);
			if (data.store) $('#wb_store').val(data.store);
			if (data.wc_order_id) $('#wb_wc_order_id').val(data.wc_order_id);
		});
	}

	$(function () {
		var $form = $('#wb-withdrawal-form');
		if (!$form.length) {
			return;
		}

		if (wbForm.wooAutofill) {
			$('#wb_order').on('blur change', lookupOrder);
		}

		if (wbForm.recaptchaV3Key) {
			$form.on('submit', function (e) {
				var $token = $('#wb_recaptcha_token');
				if (!$token.length || $token.val() || $form.hasClass('wb-recaptcha-ready')) {
					return;
				}
				e.preventDefault();
				grecaptcha.ready(function () {
					grecaptcha.execute(wbForm.recaptchaV3Key, { action: 'wb_withdrawal' }).then(function (token) {
						$token.val(token);
						$form.addClass('wb-recaptcha-ready');
						$form[0].submit();
					});
				});
			});
		}
	});
})(jQuery);
