<?php
/**
 * Plugin Name: Withdrawal Button
 * Description: Withdrawal request form with admin management and email notifications. Shortcode: [withdrawal_form]
 * Version:     3.1.0
 * Author:      Nikolaos Mantas
 * Author URI:  https://nmantas.eu
 * Plugin URI:  https://github.com/Nikolaos-Mantas/withdrawal-button
 * License:     Proprietary — see LICENSE file
 * Text Domain: withdrawal-button
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WB_VERSION', '3.1.0' );
define( 'WB_TEXT_DOMAIN', 'withdrawal-button' );
define( 'WB_PLUGIN_SLUG', 'withdrawal-button' );
define( 'WB_FILE', __FILE__ );
define( 'WB_PATH', plugin_dir_path( __FILE__ ) );
define( 'WB_URL', plugin_dir_url( __FILE__ ) );
define( 'WB_TABLE', 'wb_withdrawal_requests' );
define( 'WB_AUTHOR', 'Nikolaos Mantas' );
define( 'WB_AUTHOR_URI', 'https://nmantas.eu' );
define( 'WB_AUTHOR_EMAIL', 'info@nmantas.eu' );
define( 'WB_GITHUB_REPO', 'Nikolaos-Mantas/withdrawal-button' );

require_once WB_PATH . 'includes/helpers.php';
require_once WB_PATH . 'includes/class-wb-install.php';
require_once WB_PATH . 'includes/class-wb-settings.php';
require_once WB_PATH . 'includes/class-wb-requests.php';
require_once WB_PATH . 'includes/class-wb-spam.php';
require_once WB_PATH . 'includes/class-wb-emails.php';
require_once WB_PATH . 'includes/class-wb-diagnostics.php';
require_once WB_PATH . 'includes/class-wb-rest-logger.php';
require_once WB_PATH . 'includes/class-wb-rest-api.php';
require_once WB_PATH . 'includes/class-wb-import-export.php';
require_once WB_PATH . 'includes/class-wb-form.php';
require_once WB_PATH . 'includes/class-wb-blocks.php';
require_once WB_PATH . 'includes/class-wb-feedback.php';
require_once WB_PATH . 'includes/class-wb-updater.php';

if ( is_admin() ) {
	require_once WB_PATH . 'admin/class-wb-requests-table.php';
	require_once WB_PATH . 'admin/class-wb-settings-page.php';
	require_once WB_PATH . 'admin/class-wb-admin.php';
}

if ( class_exists( 'WooCommerce' ) ) {
	require_once WB_PATH . 'includes/class-wb-woocommerce.php';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once WB_PATH . 'includes/class-wb-cli.php';
}

require_once WB_PATH . 'includes/class-wb-plugin.php';

register_activation_hook( __FILE__, array( 'WB_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WB_Install', 'deactivate' ) );

WB_Plugin::instance();
