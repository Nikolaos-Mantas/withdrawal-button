(function ($) {
	'use strict';

	function loadScript(src, onLoad) {
		var script = document.createElement('script');
		script.src = src;
		script.async = true;
		if (onLoad) {
			script.onload = onLoad;
		}
		document.head.appendChild(script);
	}

	function revealCaptcha() {
		var $wrap = $('#wb-captcha-wrap');
		if ($wrap.length) {
			$wrap.removeClass('wb-captcha-pending');
			$wrap.find('.wb-captcha-hint').hide();
		}
	}

	function initDeferredCaptcha() {
		if (!wbForm.captchaDefer || wbForm.captchaLoaded) {
			return;
		}
		wbForm.captchaLoaded = true;

		var provider = wbForm.captchaProvider;

		if (provider === 'recaptcha_v2' && wbForm.recaptchaV2Site) {
			loadScript('https://www.google.com/recaptcha/api.js', revealCaptcha);
			return;
		}

		if (provider === 'recaptcha_v3' && wbForm.recaptchaV3Key) {
			loadScript(
				'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(wbForm.recaptchaV3Key),
				revealCaptcha
			);
			return;
		}

		if (provider === 'turnstile' && wbForm.turnstileSite) {
			loadScript('https://challenges.cloudflare.com/turnstile/v0/api.js', revealCaptcha);
		}
	}

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

		if (wbForm.captchaDefer) {
			$('#wb_privacy').on('change', function () {
				if (this.checked) {
					initDeferredCaptcha();
				}
			});
		}

		if (wbForm.recaptchaV3Key) {
			$form.on('submit', function (e) {
				var $token = $('#wb_recaptcha_token');
				if (!$token.length || $token.val() || $form.hasClass('wb-recaptcha-ready')) {
					return;
				}

				if (wbForm.captchaDefer && !$('#wb_privacy').is(':checked')) {
					e.preventDefault();
					alert(wbForm.i18n.acceptPrivacyForCaptcha);
					return;
				}

				if (typeof grecaptcha === 'undefined') {
					if (wbForm.captchaDefer) {
						e.preventDefault();
						initDeferredCaptcha();
						alert(wbForm.i18n.acceptPrivacyForCaptcha);
					}
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
