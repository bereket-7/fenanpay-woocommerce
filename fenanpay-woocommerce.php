<?php
/**
 * Plugin Name: FenanPay WooCommerce Gateway
 * Plugin URI:  https://fenanpay.com/fenanpay
 * Description: FenanPay payment gateway for WooCommerce (Basic Auth: API Key + Secret).
 * Version:     0.1.0
 * Author:      FenanPay
 * Author URI:  https://fenanpay.com
 * Text Domain: fenanpay
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Try to load composer autoload (lib/autoload.php) if it exists.
 * If you don't run composer, ensure src files are available and/or require them directly.
 */
$autoloader = __DIR__ . '/lib/autoload.php';
if ( file_exists( $autoloader ) ) {
    require_once $autoloader;
}

/**
 * Ensure WooCommerce is active.
 */
add_action( 'plugins_loaded', 'fenanpay_init_gateway', 11 );
function fenanpay_init_gateway() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    // Load translations
    load_plugin_textdomain( 'fenanpay', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    // If composer wasn't run and autoloader missing, include the gateway file manually.
    if ( ! class_exists( 'FenanPay\\FenanPay\\WC\\WC_FenanPay_Gateway' ) ) {
        $maybe = __DIR__ . '/src/WC/WC_FenanPay_Gateway.php';
        if ( file_exists( $maybe ) ) {
            require_once $maybe;
        }
    }

    // Register gateway
    add_filter( 'woocommerce_payment_gateways', function( $methods ) {
        $methods[] = 'FenanPay\\FenanPay\\WC\\WC_FenanPay_Gateway';
        return $methods;
    } );

    // Add a rewrite rule for a simple webhook path (optional)
    add_action( 'init', function() {
        add_rewrite_rule( '^fenanpay-webhook/?', 'index.php?fenanpay_webhook=1', 'top' );
    } );

    add_filter( 'query_vars', function( $vars ) {
        $vars[] = 'fenanpay_webhook';
        return $vars;
    } );

    add_action( 'template_redirect', function() {
        if ( intval( get_query_var( 'fenanpay_webhook' ) ) === 1 ) {
            // Load gateway class if needed
            if ( class_exists( 'FenanPay\\FenanPay\\WC\\WC_FenanPay_Gateway' ) ) {
                $gateway = new FenanPay\FenanPay\WC\WC_FenanPay_Gateway();
                $gateway->handle_webhook_direct();
            } else {
                status_header( 404 );
                echo 'Not Found';
            }
            exit;
        }
    } );
}

// Add settings link on plugin list
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'fenanpay_action_links' );
function fenanpay_action_links( $links ) {
    $settings = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=fenanpay' ) . '">' . __( 'Settings', 'fenanpay' ) . '</a>';
    array_unshift( $links, $settings );
    return $links;
}

// Activation/deactivation flush rewrite rules (for webhook)
register_activation_hook( __FILE__, 'fenanpay_activate' );
register_deactivation_hook( __FILE__, 'fenanpay_deactivate' );

function fenanpay_activate() {
    // ensure rewrite rules include our webhook rule
    fenanpay_flush_rewrite_rules();
}

function fenanpay_deactivate() {
    fenanpay_flush_rewrite_rules();
}

function fenanpay_flush_rewrite_rules() {
    // trigger init then flush
    if ( ! did_action( 'init' ) ) {
        do_action( 'init' );
    }
    flush_rewrite_rules();
}
