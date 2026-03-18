<?php
/**
 * Plugin Name: FreeScout WooCommerce My Account
 * Plugin URI:  https://github.com/OneTwo3D/freescout-woocommerce-myaccount
 * Description: Allows WooCommerce customers to read and reply to FreeScout support tickets and browse the knowledge base from within the My Account section.
 * Version:     1.1.53
 * Author:      OneTwo3D
 * License:     GPL-2.0-or-later
 * Text Domain: fswa
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

define( 'FSWA_VERSION', '1.1.53' );
define( 'FSWA_PLUGIN_FILE', __FILE__ );
define( 'FSWA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FSWA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin bootstrap – loaded after all plugins are initialised.
 */
add_action( 'plugins_loaded', 'fswa_init' );
function fswa_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'FreeScout WooCommerce My Account requires WooCommerce to be active.', 'fswa' )
				. '</p></div>';
		} );
		return;
	}

	require_once FSWA_PLUGIN_DIR . 'includes/class-fswa-api.php';
	require_once FSWA_PLUGIN_DIR . 'includes/class-fswa-admin.php';
	require_once FSWA_PLUGIN_DIR . 'includes/class-fswa-ajax.php';
	require_once FSWA_PLUGIN_DIR . 'includes/class-fswa-myaccount.php';
	require_once FSWA_PLUGIN_DIR . 'includes/class-fswa-knowledgebase.php';
	require_once FSWA_PLUGIN_DIR . 'includes/class-fswa-shortcodes.php';

	FSWA_Admin::init();
	FSWA_Ajax::init();
	FSWA_MyAccount::init();
	FSWA_KnowledgeBase::init();
	FSWA_Shortcodes::init();
}

/**
 * Flush rewrite rules on activation so the new endpoint is recognised.
 */
register_activation_hook( __FILE__, 'fswa_activate' );
function fswa_activate() {
	// Register endpoints first, then flush.
	add_rewrite_endpoint( 'support-tickets', EP_ROOT | EP_PAGES );
	add_rewrite_endpoint( 'knowledge-base', EP_ROOT | EP_PAGES );
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'fswa_deactivate' );
function fswa_deactivate() {
	flush_rewrite_rules();
}
