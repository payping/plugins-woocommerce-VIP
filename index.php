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
/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) 
{
include_once("class-wc-gateway-payping.php");
}else{
function wvpp_admin_notice(){
    echo '<div class="notice notice-error is-dismissible">
             <p>افزونه VIP-Payping برای کارکرد صحیح نیاز به فعال بودن افزونه <a href="https://wordpress.org/plugins/woocommerce/" target="_blank">ووکامرس</a> دارد.</p>
         </div>';
}
add_action('admin_notices', 'wvpp_admin_notice');
}