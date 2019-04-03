<?php
/*
Plugin Name: Woocommerce-vip PayPing and Gateway
Version: 1.0.2
Description:  افزونه درگاه پرداخت Payping-VIP برای ووکامرس
Plugin URI: https://www.payping.ir/
Author: Mashhadcode
Author URI: https://mashhadcode.com
*/
if (!defined('ABSPATH'))
	exit;

include_once("class-wc-gateway-payping.php");

function sync_data(){
    $call_sync = new Control_Vip_Payping();
    $wc_PayPing = new WC_PayPing();
    $token = $wc_PayPing->getToken();
    $call_sync->add_product_cat($token);
    $call_sync->add_product($token);
    $call_sync->add_coupon($token);

}
add_action('woocommerce_check_cart_items', 'sync_data', 10, 0 );