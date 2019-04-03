<?php
if (!defined('ABSPATH'))
	exit;
include_once("class-control-vip-payping.php");

function Load_payping_Gateway()
{

	if (class_exists('WC_Payment_Gateway') && !class_exists('WC_PayPing') && !function_exists('Woocommerce_Add_payping_Gateway')) {


		add_filter('woocommerce_payment_gateways', 'Woocommerce_Add_payping_Gateway');

		function Woocommerce_Add_payping_Gateway($methods)
		{
			$methods[] = 'WC_PayPing';
			return $methods;
		}

		add_filter('woocommerce_currencies', 'add_IR_currency_For_PayPing');

		function add_IR_currency_For_PayPing($currencies)
		{
			$currencies['IRR'] = __('ریال', 'woocommerce');
			$currencies['IRT'] = __('تومان', 'woocommerce');

			return $currencies;
		}

		add_filter('woocommerce_currency_symbol', 'add_IR_currency_symbol_For_PayPing', 10, 2);

		function add_IR_currency_symbol_For_PayPing($currency_symbol, $currency)
		{
			switch ($currency) {
				case 'IRR':
					$currency_symbol = 'ریال';
					break;
				case 'IRT':
					$currency_symbol = 'تومان';
					break;
			}
			return $currency_symbol;
		}

		class WC_PayPing extends WC_Payment_Gateway
		{

			public function __construct()
			{

				$this->id = 'WC_PayPing';
				$this->method_title = __('پرداخت از طریق درگاه پی‌پینگ', 'woocommerce');
				$this->method_description = __('تنظیمات درگاه پرداخت پی‌پینگ برای افزونه فروشگاه ساز ووکامرس', 'woocommerce');
				$this->icon = apply_filters('WC_PayPing_logo', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/images/logo.png');
				$this->has_fields = false;

				$this->init_form_fields();
				$this->init_settings();

				$this->title = $this->settings['title'];
				$this->description = $this->settings['description'];

				$this->paypingToken = $this->settings['paypingToken'];

				$this->success_massage = $this->settings['success_massage'];
				$this->failed_massage = $this->settings['failed_massage'];

				if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>='))
					add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
				else
					add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));

				add_action('woocommerce_receipt_' . $this->id . '', array($this, 'Send_to_payping_Gateway'));
				add_action('woocommerce_api_' . strtolower(get_class($this)) . '', array($this, 'Return_from_payping_Gateway'));

			}

            function getToken()
            {
                return $this->paypingToken;
            }
            
			public function admin_options()
			{
				parent::admin_options();
			}

			public function init_form_fields()
			{
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
							'default' => __('پرداخت از طریق پی‌پینگ', 'woocommerce'),
							'desc_tip' => true,
						),
						'description' => array(
							'title' => __('توضیحات درگاه', 'woocommerce'),
							'type' => 'text',
							'desc_tip' => true,
							'description' => __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'woocommerce'),
							'default' => __('پرداخت به وسیله کلیه کارت های عضو شتاب از طریق درگاه پی‌پینگ', 'woocommerce')
						),
						'account_confing' => array(
							'title' => __('تنظیمات حساب پی‌پینگ', 'woocommerce'),
							'type' => 'title',
							'description' => '',
						),
						'paypingToken' => array(
							'title' => __('توکن', 'woocommerce'),
							'type' => 'text',
							'description' => __('توکن درگاه پی‌پینگ', 'woocommerce'),
							'default' => '',
							'desc_tip' => true
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
							'description' => __('متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید .', 'woocommerce'),
							'default' => __('پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'woocommerce'),
						),
					)
				);
			}

			public function process_payment($order_id)
			{
				$order = new WC_Order($order_id);
				return array(
					'result' => 'success',
					'redirect' => $order->get_checkout_payment_url(true)
				);
			}

			function isJson($string) {
				json_decode($string);
				return (json_last_error() == JSON_ERROR_NONE);
			}
			function status_message($code) {
				switch ($code){
					case 200 :
						return 'عملیات با موفقیت انجام شد';
						break ;
					case 400 :
						return 'مشکلی در ارسال درخواست وجود دارد';
						break ;
					case 500 :
						return 'مشکلی در سرور رخ داده است';
						break;
					case 503 :
						return 'سرور در حال حاضر قادر به پاسخگویی نمی‌باشد';
						break;
					case 401 :
						return 'عدم دسترسی';
						break;
					case 403 :
						return 'دسترسی غیر مجاز';
						break;
					case 404 :
						return 'آیتم درخواستی مورد نظر موجود نمی‌باشد';
						break;
				}
			}

			public function Send_to_payping_Gateway($order_id)
			{
				global $woocommerce;
				$woocommerce->session->order_id_payping = $order_id;
				$order = new WC_Order($order_id);
				$currency = $order->get_currency();
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
				$Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
				if (strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN') || strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN') || strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN') || strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN') || strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران')
				)
					$Amount = $Amount * 1;
				else if (strtolower($currency) == strtolower('IRR'))
					$Amount = $Amount / 10;

				$Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $Amount, $currency);
				$Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_irt', $Amount, $currency);
				$Amount = apply_filters('woocommerce_order_amount_total_payping_gateway', $Amount, $currency);

				$CallbackUrl = add_query_arg('wc_order', $order_id, WC()->api_request_url('WC_PayPing'));
    
				$products = array();
				$order_items = $order->get_items();

				foreach ((array)$order_items as $item) {
					$products[] = $item['name'] . ' (' . $item['qty'] . ') ';
                    $quantity[] = $item['qty'];
                    $product_variation_id = $item['variation_id'];
                    // Check if product has variation.
                    if($product_variation_id) { 
                        $product = new WC_Product($item['variation_id']);
                    }else{
                        $product = new WC_Product($item['product_id']);
                    }

                    // Get SKU
                    $sku[] = $product->get_sku();
                    
                    $invoiceItems[] = array (
                                        'code' => $product->get_sku(),
                                        'name' => $item['name'],
                                        'description' => $product->get_short_description(),
                                        'tax' => false,
                                        'quantity' => $item['qty'],
                                        'discountValue' => 0,
                                        'discountType' => 0,
                                        'discountCouponCode' => '',
                                        'price' => $product->get_price(),
                                    );
				}
                
                /* An order can have no used coupons or also many used coupons */
                $coupons  = $order->get_used_coupons();
                $coupons  = count($coupons) > 0 ? implode(',', $coupons) : '';

                $discount = $order->get_total_discount();
                $currency = $order->get_currency();
                $subtotal = $order->get_subtotal();
                $total    = $order->get_total();
				$products = implode(' - ', $products);

				$Description = 'خرید به شماره سفارش : ' . $order->get_order_number() . ' | خریدار : ' . $order->billing_first_name . ' ' . $order->billing_last_name . ' | محصولات : ' . $products;
				$Mobile = get_post_meta($order_id, '_billing_phone', true) ? get_post_meta($order_id, '_billing_phone', true) : '-';
				$Email = $order->billing_email;
				$Paymenter = $order->billing_first_name . ' ' . $order->billing_last_name;
				$ResNumber = intval($order->get_order_number());

				//Hooks for iranian developer
				$Description = apply_filters('WC_PayPing_Description', $Description, $order_id);
				$Mobile = apply_filters('WC_PayPing_Mobile', $Mobile, $order_id);
				$Email = apply_filters('WC_PayPing_Email', $Email, $order_id);
				$Paymenter = apply_filters('WC_PayPing_Paymenter', $Paymenter, $order_id);
				$ResNumber = apply_filters('WC_PayPing_ResNumber', $ResNumber, $order_id);
				do_action('WC_PayPing_Gateway_Payment', $order_id, $Description, $Mobile);
				$Email = !filter_var($Email, FILTER_VALIDATE_EMAIL) === false ? $Email : '';
				$Mobile = preg_match('/^09[0-9]{9}/i', $Mobile) ? $Mobile : '';
				if ( $Email == '' )
					$payerIdentity = $Mobile ;
				else
					$payerIdentity = $Email ;
 
            $call_sync = new Control_Vip_Payping();
            
                       
/* set customer */ 
$state = $order->billing_state;         
switch ($state) {
    case "ABZ":
        $state = 'البرز ';
        break;
    case "ADL":
        $state = 'اردبیل ';
        break;
    case "EAZ":
        $state = 'آذربایجان‌شرقی ';
        break;
    case "WAZ":
        $state = 'آذربایجان‌غربی ';
        break;
    case "BHR":
        $state = 'بوشهر ';
        break;
    case "CHB":
        $state = 'چهارمحال و بختیاری ';
        break;
    case "FRS":
        $state = 'فارس ';
        break;
    case "GIL":
        $state = 'گیلان ';
        break;
    case "GLS":
        $state = 'گلستان ';
        break;
    case "HDN":
        $state = 'همدان ';
        break;
    case "HRZ":
        $state = 'تهران ';
        break;
    case "ILM":
        $state = 'ایلام ';
        break;
    case "ESF":
        $state = 'اصفهان ';
        break;
    case "KRN":
        $state = 'کرمان ';
        break;
    case "KRH":
        $state = 'کرمانشاه ';
        break;
    case "NKH":
        $state = 'خراسان شمالی ';
        break;
    case "RKH":
        $state = 'خراسان رضوی ';
        break;
    case "SKH":
        $state = 'خراسان جنوبی ';
        break;
    case "KHZ":
        $state = 'خوزستان ';
        break;
    case "KBD":
        $state = 'کهگیلویه و بویراحمد ';
        break;
    case "KRD":
        $state = 'کردستان ';
        break;
    case "LRS":
        $state = 'لرستان ';
        break;
    case "MKZ":
        $state = 'مرکزی ';
        break;
    case "MZN":
        $state = 'مازندران ';
        break;
    case "GZN":
        $state = 'قزوین ';
        break;
    case "QHM":
        $state = 'قم ';
        break;
    case "SMN":
        $state = 'سمنان ';
        break;
    case "SBN":
        $state = 'سیستان و بلوچستان ';
        break;
    case "THR":
        $state = 'تهران ';
        break;
    case "YZD":
        $state = 'یزد ';
        break;
    case "ZJN":
        $state = 'زنجان ';
        break;
}
$url_customer = "https://api.payping.ir/v1/customer/CreateOrFind";   
$body_customer = array (
  'email' => $Email,
  'phone' => $Mobile,
  'firstName' => $order->billing_first_name,
  'lastName' => $order->billing_last_name,
//   'businessName' => $Description,
  'zipCode' => $order->billing_postcode,
  'state' => $state,
  'city' => $order->billing_city,
  'location' => $order->billing_address_1.' '.$order->billing_address_2
);

$header_customer = array(
    'Accept' => 'application/json',
    'Authorization' => 'Bearer '.$this->paypingToken,
    'Content-Type' => 'application/json',
    'PluginName' => 'woocommerce-vip',
    'PluginVersion' => '1.0.2'  
    );
    
$customer_arrgs = array(
	'method' => 'POST',
	'timeout' => 45,
	'redirection' => 5,
	'httpversion' => '1.0',
	'blocking' => true,
	'headers' => $header_customer,
	'body' => json_encode($body_customer, true),
	'cookies' => array()
    );
    
$customer = wp_remote_post( $url_customer, $customer_arrgs);
$header = wp_remote_retrieve_headers($customer);
$customerCode = null;   
if ( is_wp_error( $customer ) ) {
   $error_message = $customer->get_error_message();
   echo "خطای سمت افزونه: $error_message";
}else{
    
$code_costumer = wp_remote_retrieve_response_code( $customer );
$messages = wp_remote_retrieve_response_message($customer);

if ( $code_costumer === 200 && $messages === 'OK') {
    
    $customer_res = json_decode( wp_remote_retrieve_body($customer), true );
    $customerCode = $customer_res['code'];
    
}elseif( $code_costumer === 400) {
    $message = wp_remote_retrieve_response_message( $customer );
    echo 'خطای 400: '.$message;
    echo '<br> شناسه درخواست پی‌پینگ:'.$request_api;
}else{
    $message = wp_remote_retrieve_response_message( $customer );
    echo 'خطای ارسال درخواست: '.$message;
    echo '<br> شناسه درخواست پی‌پینگ:'.$request_api;
}
   
}
$invoiceDate = get_the_time('Y-m-d H:s');
/* send to payment page */
$body_post = array (
  'billToes' => 
  array ( 
    array (
      'addressBookCode' => $customerCode,
    ),
  ),
  'createStatus' => 1,
  'invoiceNumber' => $order_id,
  'invoiceTitle' => $products,
  'totalDiscountValue' => $discount, /* total value code */
  'totalDiscountType' => 0, /* if codetype == 1 else  == 0*/
  'invoiceItems' => $invoiceItems
);
   
                        $args = array(
                            'body' => json_encode($body_post, true),
                            'timeout' => '45',
                            'redirection' => '5',
                            'httpsversion' => '1.0',
                            'blocking' => true,
	                        'headers' => array(
	                       	'Authorization' => 'Bearer '.$this->paypingToken,
	                       	'Content-Type'  => 'application/json',
                            'PluginName' => 'woocommerce-vip',
                            'PluginVersion' => '1.0.2',
                            'WcOrderId'     => $order_id,
                            'returnUrl'  => $CallbackUrl,
	                       	'Accept' => 'application/json'
	                       	),
                         'cookies' => array()
                        );
$response = wp_remote_post('https://api.payping.ir/v1/invoice', $args);
//        var_dump($response);        
if ( is_wp_error($response) ) {
  echo "خطای افزونه";
}else{
    $header = wp_remote_retrieve_headers($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    $invoices = $body['invoices']['0'];
    $invoiceCode = $invoices['code'];
    $paymentCode = $invoices['paymentCode'];

    $httpcode = wp_remote_retrieve_response_code( $response );
    if( $httpcode === 200 ){
        if( isset($paymentCode) && $paymentCode !== '' ){
            /* save invoceCode in order */
            update_post_meta($order_id, 'invoceCode', $invoiceCode);
            wp_redirect(sprintf('https://api.payping.ir/v1/pay/gotoipg/%s', $paymentCode));
        }else{
            $Message = 'کد پرداخت تنظیم نشده است';
            echo 'کد پرداخت تنظیم نشده است';
        }
    }elseif( $httpcode === 400 ){
        $Message = $httpcode;
        echo 'خطای 400:'.$httpcode;
        echo '<br> شناسه درخواست پی‌پینگ:'.$request_api;
    }else{
        $Message = $httpcode;
        echo 'خطای کد:'.$httpcode;
        echo '<br> شناسه درخواست پی‌پینگ:'.$request_api;
    }
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


			public function Return_from_payping_Gateway()
			{
				global $woocommerce;


				if (isset($_GET['wc_order']))
					$order_id = $_GET['wc_order'];
				else {
					$order_id = $woocommerce->session->order_id_payping;
					unset($woocommerce->session->order_id_payping);
				}

				if ($order_id) {

					$order = new WC_Order($order_id);
					$currency = $order->get_currency();
					$currency = apply_filters('WC_PayPing_Currency', $currency, $order_id);

					if ($order->status != 'completed') {

						$Amount = intval($order->order_total);
						$Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
						if (strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN') || strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN') || strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN') || strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN') || strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران')
						)
							$Amount = $Amount * 1;
						else if (strtolower($currency) == strtolower('IRR'))
							$Amount = $Amount / 10;
                        
                        $invoiceCode = get_post_meta( $order_id, 'invoceCode', true );
                        
						$data = array('refId' => $_GET["refid"], 'invoiceCode' => $invoiceCode);
                        $args = array(
                            'body' => json_encode($data),
                            'timeout' => '45',
                            'redirection' => '5',
                            'httpsversion' => '1.0',
                            'blocking' => true,
	                        'headers' => array(
	                       	'Authorization' => 'Bearer '.$this->paypingToken,
	                       	'Content-Type'  => 'application/json',
                            'PluginName' => 'woocommerce-vip',
                            'PluginVersion' => '1.0.2',
	                       	'Accept' => 'application/json'
	                       	),
                         'cookies' => array()
                        );

                    $response = wp_remote_post('https://api.payping.ir/v1/invoice/confirmpaymentbyplugin', $args);
                    $header = wp_remote_retrieve_headers($response);
                    if ( is_wp_error($response) ) {
                        $Status = 'failed';
				        $Fault = 'Curl Error.';
						$Message = 'خطا در ارتباط به پی‌پینگ : شرح خطا '.$response->get_error_message();
					} else {	
						$code = wp_remote_retrieve_response_code( $response );
						if ( $code === 200 ) {
                            
							if (isset($_GET["refid"]) && $_GET['refid'] != '' && isset($_GET['clientrefid']) == $invoiceCode ) {
								$Status = 'completed';
								$Transaction_ID = $_GET["refid"];
								$Fault = '';
								$Message = '';
							} else {
                                $Status = 'failed';
								$Transaction_ID = $_GET['refid'];
								$Message = 'متاسفانه سامانه قادر به دریافت کد پیگیری نمی باشد! نتیجه درخواست : ' .wp_remote_retrieve_body( $response ).'<br /> شماره خطا: '.$header;
								$Fault = $code;
							}
						} elseif ( $code == 400) {
                            $Status = 'failed';
				            $Transaction_ID = $_GET['refid'];
							$Message = wp_remote_retrieve_body( $response );
							$Fault = $code;
                            echo '<br> شناسه درخواست پی‌پینگ:'.$request_api;
						} else {
                            $Status = 'failed';
				            $Transaction_ID = $_GET['refid'];
							$Message = wp_remote_retrieve_body( $response );
                            $Fault = $code;
                            echo '<br> شناسه درخواست پی‌پینگ:'.$request_api;
						}
					}


						if ($Status == 'completed' && isset($Transaction_ID) && $Transaction_ID != 0) {
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
							exit;
						} else {


							$tr_id = ($Transaction_ID && $Transaction_ID != 0) ? ('<br/>کد پیگیری : ' . $Transaction_ID) : '';

							$Note = sprintf(__('خطا در هنگام بازگشت از بانک : %s %s', 'woocommerce'), $Message, $tr_id);

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
							exit;
						}
					} else {


						$Transaction_ID = get_post_meta($order_id, '_transaction_id', true);

						$Notice = wpautop(wptexturize($this->success_massage));

						$Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);

						$Notice = apply_filters('WC_PayPing_Return_from_Gateway_ReSuccess_Notice', $Notice, $order_id, $Transaction_ID);
						if ($Notice)
							wc_add_notice($Notice, 'success');


						do_action('WC_PayPing_Return_from_Gateway_ReSuccess', $order_id, $Transaction_ID);

						wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
						exit;
					}
				} else {

					$Fault = __('شماره سفارش وجود ندارد .', 'woocommerce');
					$Notice = wpautop(wptexturize($this->failed_massage));
					$Notice = str_replace("{fault}", $Fault, $Notice);
					$Notice = apply_filters('WC_PayPing_Return_from_Gateway_No_Order_ID_Notice', $Notice, $order_id, $Fault);
					if ($Notice)
						wc_add_notice($Notice, 'error');

					do_action('WC_PayPing_Return_from_Gateway_No_Order_ID', $order_id, $Transaction_ID, $Fault);

					wp_redirect($woocommerce->cart->get_checkout_url());
					exit;
				}
			}

		}

	}
}
add_action('plugins_loaded', 'Load_payping_Gateway', 0);

?>