<?php
/**
* Plugin Name: PayPing
* Plugin URI: https://payping.ir/
* Description: . 
* Version: 1.0.1
* Author: Pooria Monfared
* Author URI: payping.ir
* License: MIT
*/
require_once('includes/payping_api.php');
require_once('includes/DateUtils.inc.php');
if (!defined('ABSPATH'))
    exit;
global $payping_db_version;
$payping_db_version = '1.1';

function debug_log( $object=null, $label=null ){ 
    $message = json_encode($object, JSON_PRETTY_PRINT);
    $label = "Debug" . ($label ? " ($label): " : ': '); 
    echo "<script>console.log(\"$label\", $message);</script>"; }



function Load_PayPing_Gateway() {
    function db_install() {
        global $wpdb;
        global $payping_db_version;

        $t1 = $wpdb->prefix . 'payping_product';
        $t2 = $wpdb->prefix . 'payping_customer';
    
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $t1 (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            wid int(11) NOT NULL,
            pid varchar(55) DEFAULT '' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;
        CREATE TABLE $t2 (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email varchar(64) DEFAULT '' NOT NULL,
            code varchar(10) DEFAULT '' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        add_option( 'payping_db_version', $payping_db_version );
    }
    
    function payping_update_db_check() {
        global $payping_db_version;
        if ( get_site_option( 'payping_db_version' ) != $payping_db_version ) {
        db_install();
        }
    }

    payping_update_db_check();
    if (class_exists('WC_Payment_Gateway') && !class_exists('WC_PayPing') && !function_exists('Woocommerce_Add_PayPing_Gateway')) {


        add_filter('woocommerce_payment_gateways', 'Woocommerce_Add_PayPing_Gateway');

        function Woocommerce_Add_PayPing_Gateway($methods) {
            $methods[] = 'WC_PayPing';
            return $methods;
        }

        add_filter('woocommerce_currencies', 'add_IR_currency2');

        function add_IR_currency2($currencies) {
            $currencies['IRR'] = __('ریال', 'woocommerce');
            $currencies['IRT'] = __('تومان', 'woocommerce');
            $currencies['IRHR'] = __('هزار ریال', 'woocommerce');
            $currencies['IRHT'] = __('هزار تومان', 'woocommerce');

            return $currencies;
        }

        add_filter('woocommerce_currency_symbol', 'add_IR_currency2_symbol', 10, 2);

        function add_IR_currency2_symbol($currency_symbol, $currency) {
            switch ($currency) {
                case 'IRR': $currency_symbol = 'ریال';
                    break;
                case 'IRT': $currency_symbol = 'تومان';
                    break;
                case 'IRHR': $currency_symbol = 'هزار ریال';
                    break;
                case 'IRHT': $currency_symbol = 'هزار تومان';
                    break;
            }
            return $currency_symbol;
        }

        class WC_PayPing extends WC_Payment_Gateway {
            public function __construct() {

                $this->id = 'WC_PayPing';
                $this->method_title = __('پرداخت امین با پی‌پینگ', 'woocommerce');
                $this->method_description = __('تنظیمات درگاه پرداخت پی‌پینگ برای افزونه فروشگاه ساز ووکامرس', 'woocommerce');
                $this->icon = apply_filters('WC_PayPing_logo', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/images/logo.png');
                $this->has_fields = false;

                $this->init_form_fields();
                $this->init_settings();

                $this->title = $this->settings['title'];
                $this->description = $this->settings['description'];

                $payping_options = get_option( 'payping_options' );
                $this->payping_access_token = $payping_options['payping_access_token'];
                $this->payping_send_invoice_to_payer = $payping_options['payping_send_invoice_to_payer'];
        
                $this->success_massage = $this->settings['success_massage'];
                $this->failed_massage = $this->settings['failed_massage'];

                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>='))
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                else
                    add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));

                add_action('woocommerce_receipt_' . $this->id . '', array($this, 'Send_to_PayPing_Gateway'));
                add_action('woocommerce_api_' . strtolower(get_class($this)) . '', array($this, 'Return_from_PayPing_Gateway'));            
    }
        
            

    public function admin_options() {
        parent::admin_options();
    }

    public function init_form_fields() {
        $this->form_fields = apply_filters('WC_PayPing_Config', array(
            'base_confing' => array(
                'title' => __('تنظیمات پایه ای', 'woocommerce'),
                'type' => 'title',
                'description' => '',
            ),
            'enabled' => array(
                'title' => __('فعالسازی/غیرفعالسازی', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('فعالسازی درگاه پی‌پینگ', 'woocommerce'),
                'description' => __('برای فعالسازی درگاه پرداخت پی‌پینگ باید چک باکس را تیک بزنید', 'woocommerce'),
                'default' => 'yes',
                'desc_tip' => true,
            ),
            'title' => array(
                'title' => __('عنوان درگاه', 'woocommerce'),
                'type' => 'text',
                'description' => __('عنوان درگاه که در طی خرید به مشتری نمایش داده میشود', 'woocommerce'),
                'default' => __('پرداخت با پی‌پینگ', 'woocommerce'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('توضیحات درگاه', 'woocommerce'),
                'type' => 'text',
                'desc_tip' => true,
                'description' => __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'woocommerce'),
                'default' => __('پرداخت امن به وسیله کلیه کارت های عضو شتاب از طریق درگاه پی‌پینگ', 'woocommerce')
            ),
            'payment_confing' => array(
                'title' => __('تنظیمات عملیات پرداخت', 'woocommerce'),
                'type' => 'title',
                'description' => '',
            ),
            'success_massage' => array(
                'title' => __('پیام پرداخت موفق', 'woocommerce'),
                'type' => 'textarea',
                'description' => __('متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {transaction_id} برای نمایش کد رهگیری (توکن) پی‌پینگ استفاده نمایید .', 'woocommerce'),
                'default' => __('با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'woocommerce'),
            ),
            'failed_massage' => array(
                'title' => __('پیام پرداخت ناموفق', 'woocommerce'),
                'type' => 'textarea',
                'description' => __('متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید . این دلیل خطا از سایت پی‌پینگ ارسال میگردد .', 'woocommerce'),
                'default' => __('پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'woocommerce'),
            )
        ));
    }

    public function process_payment($order_id) {
        $order = new WC_Order($order_id);
        return array(
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }

    public function Get_Toman($Amount, $currency) {
        if (strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN') || strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN') || strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN') || strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN') || strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران')
            )
                $Amount = $Amount * 1;
        else if (strtolower($currency) == strtolower('IRHT'))
            $Amount = $Amount * 1000;
        else if (strtolower($currency) == strtolower('IRHR'))
            $Amount = $Amount * 100;
        else if (strtolower($currency) == strtolower('IRR'))
            $Amount = $Amount / 10;
        
        return $Amount;
    }
        
    public function Send_to_PayPing_Gateway($order_id) {
        debug_log("","Send_to_PayPing_Gateway run");
        global $woocommerce;
        $woocommerce->session->order_id_payping = $order_id;
        $order = new WC_Order($order_id);
        $currency = $order->get_order_currency();
        $currency = apply_filters('WC_PayPing_Currency', $currency, $order_id);


        $form = '<form action="" method="POST" class="payping-checkout-form" id="payping-checkout-form">
                <input type="submit" name="payping_submit" class="button alt" id="payping-payment-button" value="' . __('پرداخت', 'woocommerce') . '"/>
                <a class="button cancel" href="' . $woocommerce->cart->get_checkout_url() . '">' . __('بازگشت', 'woocommerce') . '</a>
             </form><br/>';
        $form = apply_filters('WC_PayPing_Form', $form, $order_id, $woocommerce);

        do_action('WC_PayPing_Gateway_Before_Form', $order_id, $woocommerce);
        echo $form;
        do_action('WC_PayPing_Gateway_After_Form', $order_id, $woocommerce);


        $Amount = intval($order->order_total);
        debug_log($Amount,"Amount = intval(order->order_total):");
        $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
        $Amount = $this->Get_Toman($Amount, $currency);
        debug_log($Amount,"Amount = Get_Toman(Amount, currency):");
        $Amount = apply_filters('woocommerce_order_amount_total_PayPing_gateway', $Amount, $currency);
        $shipping = $this->Get_Toman($order->get_shipping_total(), $currency);
        $access_token = $this->payping_access_token;
        $payping_send_invoice_to_payer = $this->payping_send_invoice_to_payer;
        $CallbackUrl = add_query_arg('wc_order', $order_id, WC()->api_request_url('WC_PayPing'));
        $payping_api_error =false;
        $payping_api = new PayPingAPI($access_token,$payping_send_invoice_to_payer);

        $invoiceItems = array();
        $order_items = $order->get_items();
        global $wpdb;
        $table_name = $wpdb->prefix . 'payping_product';
        $otherDiscountAmount = 0;
        $otherDiscountPercent = 0;
        foreach ((array) $order_items as $product) {
            $this_orther = new WC_Order( $product['order_id'] );
            $discountPercent = 0;
            $discountAmount = 0;
            // Coupons used in the order LOOP (as they can be multiple)
            foreach( $this_orther->get_used_coupons() as $coupon_name ){
                // Retrieving the coupon ID
                $coupon_post_obj = get_page_by_title($coupon_name, OBJECT, 'shop_coupon');
                $coupon_id = $coupon_post_obj->ID;

                // Get an instance of WC_Coupon object in an array(necesary to use WC_Coupon methods)
                $coupons_obj = new WC_Coupon($coupon_id);
                // Now you can get type in your condition
                if ( $coupons_obj->discount_type == 'percent' ){
                    if (in_array($product['product_id'], $coupons_obj->product_ids))
                        $discountPercent = $coupons_obj->get_amount();
                    else if (in_array($product['product_id'], $coupons_obj->excluded_product_ids))
                        $discountPercent = $coupons_obj->get_amount();
                    else if (empty($coupons_obj->product_ids))
                        $discountPercent = $coupons_obj->get_amount();
                }

                // Or use this other conditional method for coupon type
                if( $coupons_obj->discount_type == 'fixed_cart'){
                    // Get the coupon object amount
                    $otherDiscountAmount = $coupons_obj->get_amount();                
                }

                if( $coupons_obj->discount_type == 'fixed_product' and in_array($product['product_id'], $coupons_obj->product_ids)){
                    // Get the coupon object amount
                    $discountAmount = $coupons_obj->get_amount();                
                }
            }

            $itemCode = '-';
            $res = $wpdb->get_row( "SELECT * FROM $table_name where wid='".$product['product_id']."'" );
            if(null != $res){
                $itemCode = $res->pid;
            }
            else{
                $itemCode = $payping_api -> add_item($product['name'], '', $product['subtotal']/$product['quantity']);
                if('nores' != $itemCode){
                    $wpdb->insert( 
                        $table_name, 
                        array( 
                            'wid' => $product['product_id'], 
                            'pid' => $itemCode 
                        ), 
                        array( 
                            '%d', 
                            '%s' 
                        )
                    );
                }
                else{
                    $Message = ' خطا در دریافت کد آیتم مالی، مجددا سعی نمایید ';
                    $payping_api_error = true;
                }
            }
            array_push($invoiceItems, 
                array(
                    "code" => $itemCode, //صرفا جهت بازگشت اطلاعات پس از ثبت می باشد خالی ارسال کنید
                    "name" => $product['name'],
                    "description" => $product['description'],
                    "quantity" => $product['quantity'],
                    "discountValue" => 0, // نوع تخفیف به ازای هر آیتم (Note-3)
                    "discountAmount" => $discountAmount, // مبلغ تخفیف به ازای هر آیتم (Note-3)
                    "tax" => false,
                    "price" => $product['subtotal']/$product['quantity'], //به تومان می باشد
                )
            );
        }
              
            if(!$payping_api_error){
            $customerCode = '-';
            $table_name = $wpdb->prefix . 'payping_customer'; 
            $res = $wpdb->get_row( "SELECT * FROM $table_name where email='".$order->billing_email."'" );
            if(null != $res){
                $customerCode = $res->code;
            }
            else{
                $customerCode = $payping_api->add_customer($order->billing_first_name, 
                                $order->billing_last_name,
                                $order->billing_email,
                                $order->billing_phone);    
                    if('nocode' != $customerCode){
                    $wpdb->insert( 
                        $table_name, 
                        array( 
                            'email' => $order->billing_email, 
                            'code' => $customerCode 
                        ), 
                        array( 
                            '%s', 
                            '%s' 
                        ) 
                    );
                }
                else{
                    $Message = ' خطا در دریافت کد مشتری، مجددا سعی نمایید ';
                    $payping_api_error = true;
                }
            }
        }
        //$customerCode = 'fdf7o';
                //get_bloginfo();
                if(!$payping_api_error){
                //$invoiceDate = substr($order->get_date_created(), 0, 10);
                $invoiceDate = getUTCdateTime();
                try{
                    $res = $payping_api->add_invoice($order_id, 
                                $invoiceDate, 
                                $invoiceItems, 
                                $customerCode,
                                $shipping,
                                $CallbackUrl,
                                $otherDiscountAmount,
                                $otherDiscountPercent
                                );    
                    $paymentCode = $res['invoices'][0]['paymentCode'];            
                } 
                catch (Exception $ex) {
                $Message = $ex->getMessage();
                $Fault = '';
                }
               }
               
                $Description = 'شماره سفارش : ' . $order->get_order_number() . ' | شماره فاکتور: '.$res['invoices'][0]['code'];
              
                $Mobile = get_post_meta($order_id, '_billing_phone', true) ? get_post_meta($order_id, '_billing_phone', true) : '-';
                $Email = $order->billing_email;
                $Paymenter = $order->billing_first_name . ' ' . $order->billing_last_name;
                $ResNumber = intval($order->get_order_number());

                
                $Description = apply_filters('WC_PayPing_Description', $Description, $order_id);
                $Mobile = apply_filters('WC_PayPing_Mobile', $Mobile, $order_id);
                $Email = apply_filters('WC_PayPing_Email', $Email, $order_id);
                $Paymenter = apply_filters('WC_PayPing_Paymenter', $Paymenter, $order_id);
                $ResNumber = apply_filters('WC_PayPing_ResNumber', $ResNumber, $order_id);
                do_action('WC_PayPing_Gateway_Payment', $order_id, $Description, $Mobile);
                $Email = !filter_var($Email, FILTER_VALIDATE_EMAIL) === false ? $Email : '';
                $Mobile = preg_match('/^09[0-9]{9}/i', $Mobile) ? $Mobile : '';

                try {
                    $resCode = $paymentCode;
            if ($resCode != '') {
                $Payment_URL = 'https://api.payping.ir/v1/pay/gotoipg/'.$resCode;
                echo 'در حال انتقال به درگاه بانکی  ....';
                echo "<script type='text/javascript'>window.onload = function () { top.location.href = '" . $Payment_URL . "'; };</script>";
                exit;
            } 
            else {
                $Message = ' خطا در دریافت کد پرداخت، مجددا سعی نمایید ';
                $Fault = '';
            }
                } 
                catch (Exception $ex) {
                    $Message = $ex->getMessage();
                    $Fault = '';
                }


                if (!empty($Message) && $Message) {

                    $Note = sprintf(__('خطا در هنگام ارسال به بانک : %s', 'woocommerce'), $Message);
                    $Note = apply_filters('WC_PayPing_Send_to_Gateway_Failed_Note', $Note, $order_id, $Fault);
                    $order->add_order_note($Note);


                    $Notice = sprintf(__('در هنگام اتصال به بانک خطای زیر رخ داده است : <br/>%s', 'woocommerce'), $Message);
                    $Notice = apply_filters('WC_PayPing_Send_to_Gateway_Failed_Notice', $Notice, $order_id, $Fault);
                    if ($Notice)
                        wc_add_notice($Notice, 'error');

                    do_action('WC_PayPing_Send_to_Gateway_Failed', $order_id, $Fault);
                }
            }

    public function Return_from_PayPing_Gateway() {
        debug_log("","Return_from_PayPing_Gateway run:");
        $invoicecode = (string)$_GET['clientrefid'];
        debug_log($invoicecode,"Return_from_PayPing_Gateway invoicecode:");
        $refid = (int)$_GET['refid'];
        debug_log($refid,"Return_from_PayPing_Gateway refid:");

        global $woocommerce;
        if(0==$refid){
            $Fault = __('انصراف از پرداخت .', 'woocommerce');
            $Notice = wpautop(wptexturize($this->failed_massage));
            $Notice = str_replace("{fault}", $Fault, $Notice);
            wp_redirect($woocommerce->cart->get_checkout_url());
            exit();
        }
        $Transaction_ID = $refid;
        if (isset($_GET['wc_order']))
            $order_id = $_GET['wc_order'];
        else {
            $order_id = $woocommerce->session->order_id_payping;
            unset($woocommerce->session->order_id_payping);
        }
        if ($order_id) {
            $order = new WC_Order($order_id);
            $currency = $order->get_order_currency();
            $currency = apply_filters('WC_PayPing_Currency', $currency, $order_id);
            $Amount = intval($order->order_total);
            $Amount = $this->Get_Toman($Amount, $currency);
            debug_log($Amount,"SendVerifyPayment before amount:");
            if ($order->status != 'completed' and $order->status != 'processing') {  
  
                $payping_api = new PayPingAPI($this->payping_access_token,$this->payping_send_invoice_to_payer);
                debug_log($refid,"SendVerifyPayment before refid:");
                debug_log($invoicecode,"SendVerifyPayment before order_id:");
                debug_log($order_id,"SendVerifyPayment before invoicecode:");

                $res = $payping_api->SendVerifyPayment($refid,$invoicecode,$order_id);
                debug_log($res,"SendVerifyPayment res:");

                $pay_amount = 0;
                if(isset($res['amount']))
                    $pay_amount = $res['amount'];
                        if ((int)$pay_amount == (int)$Amount) {
                            update_post_meta($order_id, '_transaction_id', $Transaction_ID);

                            $order->payment_complete($Transaction_ID);
                            $woocommerce->cart->empty_cart();

                            $Note = sprintf(__('پرداخت موفقیت آمیز بود .<br/> کد رهگیری : %s', 'woocommerce'), $Transaction_ID);
                            $Note = apply_filters('WC_PayPing_Return_from_Gateway_Success_Note', $Note, $order_id, $Transaction_ID);
                            if ($Note)
                                $order->add_order_note($Note, 1);
                            $Notice = wpautop(wptexturize($this->success_massage));

                            $Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);

                            $Notice = apply_filters('WC_PayPing_Return_from_Gateway_Success_Notice', $Notice, $order_id, $Transaction_ID);
                            if ($Notice)
                                wc_add_notice($Notice, 'success');

                            do_action('WC_PayPing_Return_from_Gateway_Success', $order_id, $Transaction_ID);
                            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                            exit();
                        }
                        else {
                            debug_log($pay_amount,"not Verify pay_amount:");
                            debug_log($Amount,"not Verify Amount:");

                            $Message = 'تراکنش انجام نشد .';
                            $Note = sprintf(__('خطا در هنگام بازگشت از بانک : %s %s', 'woocommerce'), $Message, $refid);

                            $Note = apply_filters('WC_PayPing_Return_from_Gateway_Failed_Note', $Note, $order_id, $Transaction_ID, $Fault);
                            if ($Note)
                                $order->add_order_note($Note, 1);

                            $Notice = wpautop(wptexturize($this->failed_massage));

                            $Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);

                            $Notice = str_replace("{fault}", $Message, $Notice);
                            $Notice = apply_filters('WC_PayPing_Return_from_Gateway_Failed_Notice', $Notice, $order_id, $Transaction_ID, $Fault);
                            if ($Notice)
                                wc_add_notice($Notice, 'error');

                            do_action('WC_PayPing_Return_from_Gateway_Failed', $order_id, $Transaction_ID, $Fault);
                            wp_redirect($woocommerce->cart->get_checkout_url());
                            exit();
                        }
                    }
                    else {

                        wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                        exit();
                    }
                }
                else {
                    $Fault = __('شماره سفارش وجود ندارد .', 'woocommerce');
                    $Notice = wpautop(wptexturize($this->failed_massage));
                    $Notice = str_replace("{fault}", $Fault, $Notice);
                    $Notice = apply_filters('WC_PayPing_Return_from_Gateway_No_Order_ID_Notice', $Notice, $order_id, $Fault);
                    if ($Notice)
                        wc_add_notice($Notice, 'error');
                    do_action('WC_PayPing_Return_from_Gateway_No_Order_ID', $order_id, $Transaction_ID, $Fault);
                    wp_redirect($woocommerce->cart->get_checkout_url());
                    exit();
                }
            }
        }
    }
}

function payping_menu_pages(){
    add_menu_page('تنظیمات پی‌پینگ', 'تنظیمات پی‌پینگ', 'manage_options', 'payping', 'payping_settings_page' );
    
}



function payping_section_cb( $args ) {
     return;
}

function payping_field_cb( $args ) {
    $options = get_option( 'payping_options' );
    echo '<input type="text" name="payping_options['.esc_attr( $args['label_for'] ).']" value="'.$options[ $args['label_for'] ].'">';
}

function payping_checkbox_cb( $args ) {
    $options = get_option( 'payping_options' );
    echo '<input type="checkbox" id="checkbox_'.esc_attr( $args['label_for'] ).'" name="payping_options['.esc_attr( $args['label_for'] ).']" value="1"' . checked( 1, $options[ $args['label_for'] ], false ) . '/><label for="checkbox_'.esc_attr( $args['label_for'] ).'">ارسال اطلاع رسانی فاکتور پس از پرداخت موفق به پرداخت کننده</label>';
}

function payping_checkbox_cbc( $args ) {
    $options = get_option( 'payping_options' );

    $html = '<input type="checkbox" id="checkbox_'.esc_attr( $args['label_for'] ).'" name="payping_options['.esc_attr( $args['label_for'] ).']" value="1"' . checked( 1, $options['label_for'], false ) . '/>';
    $html .= '<label for="checkbox_'.esc_attr( $args['label_for'] ).'">ارسال اطلاع رسانی فاکتور پس از پرداخت موفق به پرداخت کننده</label>';

    echo $html;
}

function payping_register_settings() {
    register_setting( 'payping', 'payping_options' );
    add_settings_section(
         'payping_section', 'تنظیمات',
         'payping_section_cb',
         'payping'
     );
     add_settings_field(
         'payping_access_token', 'توکن دسترسی',
         'payping_field_cb',
         'payping',
         'payping_section',
         [
             'label_for' => 'payping_access_token',
             'class' => 'wporg_row',
             'wporg_custom_data' => 'custom',
         ]
     );
     add_settings_field(
        'payping_send_invoice_to_payer', '',
        'payping_checkbox_cb',
        'payping',
        'payping_section',
         [
             'label_for' => 'payping_send_invoice_to_payer',
             'class' => 'wporg_row',
             'wporg_custom_data' => 'custom',
         ]
    );
}


function payping_add_new_item($post, $amount){
    //Create post
    $post_id = wp_insert_post( $post, $wp_error );
    if($post_id){
        add_post_meta( $post_id, '_regular_price', $amount );
        add_post_meta( $post_id, '_price', $amount );
        add_post_meta( $post_id, '_stock_status', 'instock' );
        
    }
    
    return $post_id;
}

function payping_settings_page() {
    if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    if ( isset( $_GET['settings-updated'] ) ) {
         add_settings_error( 'payping_messages', 'payping_message', 'تنظیمات ذخیره شد', 'updated' );
    }
    if(isset($_POST['fromPayping']) or isset($_POST['toPayping'])){
        $payping_options = get_option( 'payping_options' );
        $access_token = $payping_options['payping_access_token'];
        $payping_send_invoice_to_payer = $payping_options['payping_send_invoice_to_payer'];
        $payping_api = new PayPingAPI($access_token,$payping_send_invoice_to_payer);
    
    
        if(isset($_POST['toPayping'])){
            $posts = get_posts( [
                'numberposts' => -1,
                'post_type' => 'product'
            ] );
            global $wpdb;
            $table_name = $wpdb->prefix . 'payping_product';
            $num_all_item=$num_post_item=0;
                foreach ($posts as $product) {
                    $num_all_item++;
                    $res = $wpdb->get_row( "SELECT * FROM $table_name where wid='".$product->ID."'" );
                    if(null == $res){
                        $meta = get_post_meta($product->ID);
                        $itemCode = $payping_api->add_item($product->post_title, '', $meta['_price'][0]);
                        if($itemCode != 'nores'){
                            $num_post_item++;
                        $wpdb->insert( 
                            $table_name, 
                            array( 
                                'wid' => $product->ID, 
                                'pid' => $itemCode 
                            ), 
                            array( 
                                '%d', 
                                '%s' 
                            ) 
                        );
                    }
                    }
                }
                add_settings_error( 'payping_messages', 'payping_message', 'تعداد '.$num_all_item.' آیتم یافت شد', 'updated' );
            add_settings_error( 'payping_messages', 'payping_message', 'تعداد '.$num_post_item.'  آیتم به پی‌پینگ ارسال شد', 'updated' );
        }
        else{
            $all_item = $payping_api->get_item();
            global $wpdb;
            $table_name = $wpdb->prefix . 'payping_product';
            $num_get_item=$num_inser_item=0;
            foreach($all_item as $item){
                $num_get_item++;
                $res = $wpdb->get_row( "SELECT * FROM $table_name where pid='".$item['code']."'" );
                
                    if(null == $res){
                        $post = array(
                        'post_author' => $user_id,
                        'post_content' => '',
                        'post_status' => "publish",
                        'post_title' => $item['title'],
                        'post_parent' => '',
                        'post_type' => "product",
                    );
                    $product_id = payping_add_new_item($post, $item['amount']);
                    if($product_id>0){
                        $wpdb->insert( 
                            $table_name, 
                            array( 
                                'wid' => $product_id, 
                                'pid' => $item['code'] 
                            ), 
                            array( 
                                '%d', 
                                '%s' 
                            ) 
                        );
                        $num_inser_item++;
                        print_r($item);
                        echo '<hr>';
                    }
                    }
                    
                
            }
            add_settings_error( 'payping_messages', 'payping_message', 'تعداد '.$num_get_item.' آیتم از پی‌پینگ دریافت شد', 'updated' );
            add_settings_error( 'payping_messages', 'payping_message', 'تعداد '.$num_inser_item.'  آیتم جدید به سیستم اضافه شد', 'updated' );
        }
        
    }
    
    if(isset($_POST['removeCache'])){
        global $wpdb;
        $table_name = $wpdb->prefix . 'payping_product';
        $wpdb->query("TRUNCATE TABLE $table_name");
        add_settings_error( 'payping_messages', 'payping_message', ' تاریخچه همسان‌سازی حذف شد!', 'updated' );
    }
    
    if(isset($_POST['removeProducts'])){
        $posts = get_posts( [
                'numberposts' => -1,
                'post_type' => 'product'
              ] );
          //* ...and delete them
          array_filter( $posts, function( $post ) {
                  wp_delete_post( $post->ID );
              });
          add_settings_error( 'payping_messages', 'payping_message', 'اطلاعات همه محصولات حذف شد!', 'updated' );
    }
     // show error/update messages
    settings_errors( 'payping_messages' );
    echo '<form action="options.php" method="post">';
    settings_fields( 'payping' );
    do_settings_sections( 'payping' );
    submit_button( 'ذخیره تغییرات' );
    echo '</form>';
    
?>
    <hr><h2>همسان‌سازی اطلاعات با پی‌پینگ</h2>
    <table>
        <tr>
        <td>
        <form method="post">
            <?php submit_button( 'ارسال اطلاعات کالاها به پی‌پینگ', 'secondary',  "toPayping"); ?>
        </form>
        </td>
        <td>
        <form method="post">
            <?php submit_button( 'دریافت اطلاعات کالاها از پی‌پینگ', 'secondary',  "fromPayping"); ?>
        </form>
        </td>
        </tr>
        <tr>
        <td>
        <form method="post">
            <?php submit_button( 'حذف تاریخچه همسان‌سازی', 'delete',  "removeCache"); ?>
        </form>
        </td>
        <td>
        <form method="post">
            <?php submit_button( 'حذف همه محصولات', 'delete',  "removeProducts"); ?>
        </form>
        </td>
        </tr>
    </table>
<?php
}


add_action('admin_menu', 'payping_menu_pages');
add_action('admin_init', 'payping_register_settings');
add_action('plugins_loaded', 'Load_PayPing_Gateway', 0);

?>
