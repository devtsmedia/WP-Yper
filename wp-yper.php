<?php
/**
 * Plugin Name: WP Yper for WooCommerce
 * Description: A plugin to integrate WooCommerce with the Yper API.
 * Version: 1.0
 * Author: Venture Queue
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!file_exists(__DIR__ . "/vendor/autoload.php")) {
    return;
}

if (!defined('WC_VERSION')) {
    return;
}

require_once __DIR__ . "/vendor/autoload.php";

require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-yper-api.php';

register_activation_hook(__FILE__, 'wp_yper_activate');
register_deactivation_hook(__FILE__, 'wp_yper_deactivate');

function wp_yper_activate() {
    add_option('wp_yper_client_id', '');
    add_option('wp_yper_client_secret', '');
    add_option('wp_yper_retailpoint_id', '');
    add_option('wp_yper_pro_id', '');
    add_option('wp_yper_pro_secret_token', '');
}

function wp_yper_deactivate() {
    delete_option('wp_yper_client_id');
    delete_option('wp_yper_client_secret');
    delete_option('wp_yper_retailpoint_id');
    delete_option('wp_yper_pro_id');
    delete_option('wp_yper_pro_secret_token');
}

add_action( 'plugins_loaded', 'init_wp_yper_admin_settings' );
add_action( 'woocommerce_thankyou', 'create_yper_delivery', 9, 1 );
//add_action( 'woocommerce_thankyou', 'add_yper_content_thankyou', 20, 1 );

function init_wp_yper_admin_settings() {
    new WP_Yper_Admin_Settings();
}

function create_yper_delivery ( $order_id ){
    $yper_api = new WP_Yper_API();
    $yper_api->create_delivery($order_id);
}
?>
