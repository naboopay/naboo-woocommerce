<?php
/*
Plugin Name: Naboo WooCommerce Integration
Description: Integrates Naboo payment gateway with WooCommerce.
Version: 1.0
Author: Your Name
*/

// Exit if accessed directly
if (!defined("ABSPATH")) {
    exit();
}

// Include the gateway class
require_once plugin_dir_path(__FILE__) . "includes/class-wc-naboo-gateway.php";

// Register the gateway
add_filter("woocommerce_payment_gateways", "add_naboo_gateway");

function add_naboo_gateway($gateways)
{
    $gateways[] = "WC_Naboo_Gateway";
    return $gateways;
}
