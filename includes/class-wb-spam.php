<?php
/**
 * Anti-spam: honeypot, time trap, rate limit, captcha.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_Spam {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue captcha scripts when needed.
	 */
	public static function enqueue_scripts() {
		if ( ! WB_Form::page_has_form() ) {
			return;
		}

		$settings = WB_Settings::get();
		$provider = $settings['captcha_provider'];

		if ( 'recaptcha_v2' === $provider && $settings['recaptcha_v2_site'] ) {
			wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true );
		}

		if ( 'recaptcha_v3' === $provider && $settings['recaptcha_v3_site'] ) {
			wp_enqueue_script(
				'google-recaptcha-v3',
				'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $settings['recaptcha_v3_site'] ),
				array(),
				null,
				true
			);
		}

		if ( 'turnstile' === $provider && $settings['turnstile_site'] ) {
			wp_enqueue_script( 'cloudflare-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
		}
	}

	/**
	 * Validate all spam checks.
	 *
	 * @return array<int, string> Errors.
	 */
	public static function validate() {
		$errors   = array();
		$settings = WB_Settings::get();

		if ( $settings['honeypot_enabled'] && ! empty( $_POST['wb_website'] ) ) {
			$errors[] = __( 'Submission rejected.', WB_TEXT_DOMAIN );
			return $errors;
		}

		if ( $settings['time_trap_enabled'] ) {
			$loaded = isset( $_POST['wb_loaded_at'] ) ? (int) $_POST['wb_loaded_at'] : 0;
			$min    = max( 1, (int) $settings['time_trap_seconds'] );
			if ( $loaded && ( time() - $loaded ) < $min ) {
				$errors[] = __( 'Please wait a moment before submitting.', WB_TEXT_DOMAIN );
			}
		}

		if ( $settings['rate_limit_enabled'] ) {
			$ip    = wb_get_ip();
			$key   = 'wb_rate_' . md5( $ip );
			$count = (int) get_transient( $key );
			$limit = max( 1, (int) $settings['rate_limit_count'] );
			if ( $count >= $limit ) {
				$errors[] = __( 'Too many submissions. Please try again later.', WB_TEXT_DOMAIN );
			}
		}

		if ( 'none' !== $settings['captcha_provider'] ) {
			$captcha_error = self::validate_captcha( $settings );
			if ( $captcha_error ) {
				$errors[] = $captcha_error;
			}
		}

		return $errors;
	}

	/**
	 * Increment rate limit counter after successful submission.
	 */
	public static function increment_rate_limit() {
		$settings = WB_Settings::get();
		if ( ! $settings['rate_limit_enabled'] ) {
			return;
		}
		$ip    = wb_get_ip();
		$key   = 'wb_rate_' . md5( $ip );
		$count = (int) get_transient( $key );
		$window = max( 60, (int) $settings['rate_limit_window'] );
		set_transient( $key, $count + 1, $window );
	}

	/**
	 * Validate captcha token.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return string|null Error message or null.
	 */
	private static function validate_captcha( $settings ) {
		$provider = $settings['captcha_provider'];

		if ( 'recaptcha_v2' === $provider ) {
			$token  = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : '';
			$secret = $settings['recaptcha_v2_secret'];
			if ( ! $token || ! $secret ) {
				return __( 'Captcha verification failed. Please try again.', WB_TEXT_DOMAIN );
			}
			$result = self::remote_verify( 'https://www.google.com/recaptcha/api/siteverify', array(
				'secret'   => $secret,
				'response' => $token,
				'remoteip' => wb_get_ip(),
			) );
			if ( empty( $result['success'] ) ) {
				return __( 'Captcha verification failed. Please try again.', WB_TEXT_DOMAIN );
			}
		}

		if ( 'recaptcha_v3' === $provider ) {
			$token  = isset( $_POST['wb_recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['wb_recaptcha_token'] ) ) : '';
			$secret = $settings['recaptcha_v3_secret'];
			if ( ! $token || ! $secret ) {
				return __( 'Captcha verification failed. Please try again.', WB_TEXT_DOMAIN );
			}
			$result = self::remote_verify( 'https://www.google.com/recaptcha/api/siteverify', array(
				'secret'   => $secret,
				'response' => $token,
				'remoteip' => wb_get_ip(),
			) );
			$score  = isset( $result['score'] ) ? (float) $result['score'] : 0;
			$min    = (float) $settings['recaptcha_v3_score'];
			if ( empty( $result['success'] ) || $score < $min ) {
				return __( 'Captcha verification failed. Please try again.', WB_TEXT_DOMAIN );
			}
		}

		if ( 'turnstile' === $provider ) {
			$token  = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
			$secret = $settings['turnstile_secret'];
			if ( ! $token || ! $secret ) {
				return __( 'Captcha verification failed. Please try again.', WB_TEXT_DOMAIN );
			}
			$result = self::remote_verify( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
				'secret'   => $secret,
				'response' => $token,
				'remoteip' => wb_get_ip(),
			) );
			if ( empty( $result['success'] ) ) {
				return __( 'Captcha verification failed. Please try again.', WB_TEXT_DOMAIN );
			}
		}

		return null;
	}

	/**
	 * Remote POST verification.
	 *
	 * @param string               $url  Endpoint URL.
	 * @param array<string, mixed> $body Body data.
	 * @return array<string, mixed>
	 */
	private static function remote_verify( $url, $body ) {
		$response = wp_remote_post( $url, array(
			'timeout' => 15,
			'body'    => $body,
		) );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Render captcha field HTML.
	 *
	 * @return string
	 */
	public static function render_captcha_field() {
		$settings = WB_Settings::get();
		$provider = $settings['captcha_provider'];

		if ( 'recaptcha_v2' === $provider && $settings['recaptcha_v2_site'] ) {
			return '<div class="g-recaptcha" data-sitekey="' . esc_attr( $settings['recaptcha_v2_site'] ) . '"></div>';
		}

		if ( 'turnstile' === $provider && $settings['turnstile_site'] ) {
			return '<div class="cf-turnstile" data-sitekey="' . esc_attr( $settings['turnstile_site'] ) . '"></div>';
		}

		if ( 'recaptcha_v3' === $provider && $settings['recaptcha_v3_site'] ) {
			return '<input type="hidden" name="wb_recaptcha_token" id="wb_recaptcha_token" value="">';
		}

		return '';
	}
}
