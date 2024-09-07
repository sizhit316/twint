<?php

use Mame_Twint\Plugin_Init;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin Name: mame TWINT for WooCommerce
 * Plugin URI: http://www.mamedev.ch
 * Description: TWINT Payment Gateway for WooCommerce
 * Version: 5.5.1
 * Author: mame software development hüttig
 * Author URL: https://mamedev.ch
 * License: Purchase a license key at mamedev.ch
 *
 * Requires at least 5.7
 * Tested up to: 6.1
 * Requires PHP: 7.0
 *
 * WC requires at least: 5.2.0
 * WC tested up to: 7.4
 */

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

include_once plugin_dir_path( __FILE__ ) . 'includes/Plugin_Init.php';
$init = new Plugin_Init();
