<?php
/**
 * PHPUnit bootstrap with minimal WordPress stubs.
 */

define( 'ABSPATH', __DIR__ . '/../' );
define( 'WB_VERSION', '3.1.0' );
define( 'WB_TEXT_DOMAIN', 'withdrawal-button' );
define( 'WB_PLUGIN_SLUG', 'withdrawal-button' );
define( 'WB_FILE', dirname( __DIR__ ) . '/withdrawal-button.php' );
define( 'WB_PATH', dirname( __DIR__ ) . '/' );
define( 'WB_URL', 'http://example.com/wp-content/plugins/withdrawal-button/' );
define( 'WB_TABLE', 'wb_withdrawal_requests' );
define( 'WB_AUTHOR', 'Nikolaos Mantas' );
define( 'WB_AUTHOR_URI', 'https://nmantas.eu' );
define( 'WB_AUTHOR_EMAIL', 'info@nmantas.eu' );
define( 'WB_GITHUB_REPO', 'Nikolaos-Mantas/withdrawal-button' );

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

$GLOBALS['wb_test_options']  = array();
$GLOBALS['wb_test_transients'] = array();
$GLOBALS['wp_version']         = '6.4.0';

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code;
		public $message;
		public $data;

		public function __construct( $code, $message, $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private $headers = array();
		private $params  = array();

		public function get_header( $name ) {
			$key = strtolower( str_replace( '-', '_', $name ) );
			return $this->headers[ $key ] ?? null;
		}

		public function set_header( $name, $value ) {
			$key                      = strtolower( str_replace( '-', '_', $name ) );
			$this->headers[ $key ] = $value;
		}

		public function get_param( $key ) {
			return $this->params[ $key ] ?? null;
		}

		public function set_param( $key, $value ) {
			$this->params[ $key ] = $value;
		}
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		return filter_var( $email, FILTER_SANITIZE_EMAIL );
	}
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( $email ) {
		return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return sanitize_text_field( $str );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( $defaults, $args );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return isset( $GLOBALS['wb_test_options'][ $key ] ) ? $GLOBALS['wb_test_options'][ $key ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value ) {
		$GLOBALS['wb_test_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) {
		if ( 'name' === $show ) {
			return 'Test Site';
		}
		return '';
	}
}

if ( ! function_exists( 'wp_specialchars_decode' ) ) {
	function wp_specialchars_decode( $string, $quote_style = ENT_NOQUOTES ) {
		return $string;
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
		return str_repeat( 'a', $length );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		return isset( $GLOBALS['wb_test_user_caps'][ $capability ] ) && $GLOBALS['wb_test_user_caps'][ $capability ];
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) {
		if ( 'mysql' === $type ) {
			return gmdate( 'Y-m-d H:i:s' );
		}
		return time();
	}
}

if ( ! function_exists( 'date_i18n' ) ) {
	function date_i18n( $format, $timestamp ) {
		return date( $format, $timestamp );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'http://example.com/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'http://example.com/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return $url;
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return 'withdrawal-button/withdrawal-button.php';
	}
}

if ( ! function_exists( 'wp_mail' ) ) {
	function wp_mail( $to, $subject, $message, $headers = '' ) {
		$GLOBALS['wb_test_last_mail'] = compact( 'to', 'subject', 'message', 'headers' );
		return true;
	}
}

if ( ! function_exists( 'get_site_transient' ) ) {
	function get_site_transient( $key ) {
		return get_transient( $key );
	}
}

if ( ! function_exists( 'set_site_transient' ) ) {
	function set_site_transient( $key, $value, $expiration ) {
		return set_transient( $key, $value, $expiration );
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		if ( ! isset( $GLOBALS['wb_test_transients'][ $key ] ) ) {
			return false;
		}
		$item = $GLOBALS['wb_test_transients'][ $key ];
		if ( $item['expires'] > 0 && time() > $item['expires'] ) {
			unset( $GLOBALS['wb_test_transients'][ $key ] );
			return false;
		}
		return $item['value'];
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $expiration ) {
		$GLOBALS['wb_test_transients'][ $key ] = array(
			'value'   => $value,
			'expires' => $expiration > 0 ? time() + $expiration : 0,
		);
		return true;
	}
}

/**
 * Reset test state and optionally override settings.
 *
 * @param array<string, mixed> $settings_overrides Settings overrides.
 */
function wb_test_reset( $settings_overrides = array() ) {
	$GLOBALS['wb_test_options']    = array();
	$GLOBALS['wb_test_transients'] = array();
	$GLOBALS['wb_test_user_caps']  = array();
	$_POST                         = array();
	$_SERVER['REMOTE_ADDR']        = '203.0.113.10';

	update_option( 'wb_settings', wp_parse_args( $settings_overrides, WB_Settings::defaults() ) );
	update_option( 'date_format', 'Y-m-d' );
	update_option( 'time_format', 'H:i' );
	update_option( 'admin_email', 'admin@example.com' );
}

require_once WB_PATH . 'includes/class-wb-settings.php';
require_once WB_PATH . 'includes/class-wb-rest-api.php';
require_once WB_PATH . 'includes/helpers.php';
require_once WB_PATH . 'includes/class-wb-spam.php';
require_once WB_PATH . 'includes/class-wb-form.php';
require_once WB_PATH . 'includes/class-wb-feedback.php';
require_once WB_PATH . 'includes/class-wb-updater.php';

wb_test_reset();
