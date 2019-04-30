<?php
if (!defined('ABSPATH'))
	exit;

function wvpp_load_cvp()
{
    class WVPP_Control_Vip_Payping{
        protected $person_info;
        
        function __construct(){
        }
        
        public function call_PPing_Class($Param){
            $wc_ppal = new WC_PayPing();
            
            $token = $wc_ppal->getToken();
            $Debug_Mode = $wc_ppal->Debug_Mode;
            $Debug_URL = $wc_ppal->Debug_URL;
            
            if($Param == 'token'){
                return $token;
            }elseif($Param == 'debug'){
                return $Debug_Mode;
            }elseif($Param == 'url'){
                return $Debug_URL;
            }else{
                return false;
            } 
        }
        
/* function add product_cat from payping to woocommerce */
public function add_product_cat($token_access, $nonce_form = null, $notif = 0){

/* Start Check Nonce */
$nonce = $nonce_form;
$uri = $_SERVER[ 'REQUEST_URI' ];
if ( current_user_can('editor') || current_user_can('administrator') && check_admin_referer( 'wcpp_secure_form', $nonce ) ) {
/* Start Check Nonce */
    
/* Call Function Set Urls API */
$url = WC_GPP_DebugURLs($this->call_PPing_Class('debug'), $this->call_PPing_Class('url'), '/v1/category/All');
    
$cat_args = array(
'body' => '',
'timeout' => '45',
'redirection' => '5',
'httpsversion' => '1.0',
'blocking' => true,
'headers' => array(
'Authorization' => 'Bearer '.$token_access,
'Content-Type' => 'application/json',
'Accept' => 'application/json'
),
'cookies' => array()
);
$response = wp_remote_get( $url, $cat_args );

/* Call Function Show Debug In Console */
WC_GPP_Debug_Log($this->call_PPing_Class('debug'), $response, "Sync Categorys Result");

$header = wp_remote_retrieve_headers($response);
$request_api = $header['x-paypingrequest-id'];
if ( is_wp_error($response) ) {
echo $Message = $response->get_error_message();
}else{
$code = wp_remote_retrieve_response_code( $response );
if ( $code === 200) {
$product_cat = json_decode( wp_remote_retrieve_body($response), true );
/* set counter variable */
$up_count = 0;
$ins_count = 0;
foreach( $product_cat as $category ){
  $cat_name = $category['name'];
  $cat_slug = $category['code'];
    
  $check_term = get_term_by('slug', $cat_slug, 'product_cat'); 
    
  $id = $check_term->term_id;
    
  if( empty($id) ){
    wp_insert_term(
    $cat_name,
    'product_cat', 
    array(
        'description'=> '',
        'slug' => $cat_slug
        )
    );
    $ins_count++; 
  }else{
    wp_update_term( $id, 'product_cat', array(
    'name' => $cat_name,
    'slug' => $cat_slug
    ) );
      $up_count++;
  }
    }

    /* show message for admin */
    if(isset($notif) && $notif == 1){
        echo '<div class="notice notice-success is-dismissible"><p>'.$ins_count.' دسته ایجاد و '.$up_count.' دسته بروز شد.</p></div>';
    }
    
}elseif( $code == 400) {
    echo wp_remote_retrieve_response_message( $response );
    echo '<br> شناسه درخواست پی‌پینگ:'.$request_api;
}else{
    echo wp_remote_retrieve_response_message( $response );
    echo '<br> شناسه درخواست پی‌پینگ:'.$request_api;
}
}
    /* end check nonce */
}else{
  die('نانس نامعتبر است');
}
   /* end check nonce */ 
}
        /* end function add product_cat from payping to woocommerce */
        
/* function add products from payping to woocommerce */
public function add_product($token_access, $nonce_form = null, $notif = 0){
/* Start Check Nonce */
$nonce = $nonce_form;
$uri = $_SERVER[ 'REQUEST_URI' ];
if ( current_user_can('editor') || current_user_can('administrator') && check_admin_referer( 'wcpp_secure_form', $nonce ) ) {
/* Start Check Nonce */
    
/* Call Function Set Urls API */
$url = WC_GPP_DebugURLs($this->call_PPing_Class('debug'), $this->call_PPing_Class('url'), '/v1/product/All');
    
$product_args = array(
'body' => '',
'timeout' => '180',
'redirection' => '5',
'httpsversion' => '1.0',
'blocking' => true,
'headers' => array(
'Authorization' => 'Bearer '.$token_access,
'Content-Type' => 'application/json',
'Accept' => 'application/json'
),
'cookies' => array()
);
$response = wp_remote_get( $url, $product_args );

/* Call Function Show Debug In Console */
WC_GPP_Debug_Log($this->call_PPing_Class('debug'), $response, "Sync Items Result");
    
$header = wp_remote_retrieve_headers($response);
$request_api = $header['x-paypingrequest-id'];
if ( is_wp_error($response) ) {
    echo $Message = $response->get_error_message();
}else{
    $code = wp_remote_retrieve_response_code( $response );
if ( $code === 200) {
    
    $products = json_decode( wp_remote_retrieve_body($response), true );

    /* set counter variable */
    $ins_count = 0;
    $up_count = 0;
    
    foreach( $products as $product ):
    
        $product_title = $product['title'];
        $product_content = 'توضیحات محصول';
        $insert_author = get_current_user_id();
    
        /* get image link as payping */
        $img_link = $product['imageLink'];
        if( $img_link == '' ){
            $img_code = plugin_dir_url( __FILE__ ) . '/assets/images/item_empty.png';
        }else{
            $img_code = $product['imageLink'];
        }
        $url_img = basename($img_code);
        
        /* check visible product */
        if( $product['isActive'] === true ){
            $visibility = 'visible';
        }else{
            $visibility = '_visibility_hidden';
        }
    
        /* check unlimited */
        if( $product['unlimited'] === true ){
            $manage_stock = 'no';
            $quantity = '';
        }else{
            $manage_stock = 'yes';
            $quantity = $product['quantity'];
        }
        /* set product data */
        $datas = array(
            '_visibility'            => $visibility,
            '_stock_status'          => 'instock',
            'total_sales'            => '0',
            '_downloadable'          => 'no',
            '_virtual'               => 'no',
            '_regular_price'         => '',
            '_sale_price'            => "1",
            '_purchase_note'         => "",
            '_featured'              => "no",
            '_weight'                => "",
            '_length'                => "",
            '_width'                 => "",
            '_height'                => "",
            '_sku'                   => $product['code'],
            '_product_attributes'    => array(),
            '_sale_price_dates_from' => "",
            '_sale_price_dates_to'   => "",
            '_price'                 => $product['amount'],
            '_sold_individually'     => "",
            '_manage_stock'          => $manage_stock,
            '_backorders'            => "no",
            '_stock'                 => $quantity, 
        );
    
        /* others product meta */
        $defineAmountByUser = $product['defineAmountByUser'];
        $isActive = $product['isActive'];
        $haveTax = $product['haveTax'];
        $categoryCode = $product['categoryCode'];
            
        /* get product_id */
        $parent_post_id = wc_get_product_id_by_sku( $product['code'] );
        
        if( !$parent_post_id ){
            
            // Create post object
            $new_product = array(
                'post_title'    => $product_title,
                'post_content'  => $product_content,
                'post_status'   => 'publish',
                'post_author'   => $insert_author,
                'post_type'     => 'product'
            );
 
            // Insert the post into the database
            $parent_post_id = wp_insert_post( $new_product );
            
            $attach_id = $this->insert_attachment_from_url( $img_code, $parent_post_id );
            set_post_thumbnail( $parent_post_id, $attach_id );
            
            /* set other values */ 
            wp_set_object_terms($parent_post_id, $categoryCode, 'product_cat');
            $this->update_meta_product($parent_post_id, $datas);
            $ins_count++;
        }else{
            /* Update product_post_id */
            $update_product = array(
                'ID'           => $parent_post_id,
                'post_title'    => $product_title,
                'post_content'  => $product_content,
                'post_status'   => 'publish',
                'post_author'   => $insert_author,
                'post_type'     => 'product'
            );

            /* Update the post into the database */
            wp_update_post( $update_product );
            
            /* check has product thumbnail */
            if( !has_post_thumbnail( $parent_post_id ) ){
                $attach_id = $this->insert_attachment_from_url( $img_code, $parent_post_id );
                set_post_thumbnail( $parent_post_id, $attach_id );
            }else{
                $attachment_file = basename( get_the_post_thumbnail_url( $parent_post_id ) );
                /* check exist attachment file */
                if( $attachment_file !== $url_img ){
                    $attach_id = $this->insert_attachment_from_url( $img_code, $parent_post_id );
                    set_post_thumbnail( $parent_post_id, $attach_id );
                }
            }
            
            /* set other values */ 
            wp_set_object_terms($parent_post_id, $categoryCode, 'product_cat');
            $this->update_meta_product($parent_post_id, $datas);
            $up_count++;
        }
    
    endforeach;
   
    /* show message for admin */
    if(isset($notif) && $notif == 1){
        echo '<div class="notice notice-success is-dismissible"><p>'.$ins_count.' محصول ایجاد و '.$up_count.' محصول بروزرسانی شد.</p></div>';
    }

}elseif( $code == 400) {
    echo wp_remote_retrieve_response_message( $response );
    echo '<br> شناسه درخواست پی‌پینگ:'.$request_api;
}else{
    echo wp_remote_retrieve_response_message( $response );
    echo '<br> شناسه درخواست پی‌پینگ:'.$request_api;
}
}
    /* end check nonce */
}else{
  die('نانس نامعتبر است');
}
   /* end check nonce */
}
        /* end function insert and update coupon code */
        
/* function insert and update coupon code */
public function add_coupon($token_access, $nonce_form = null, $notif = 0){

/* Start Check Nonce */
$nonce = $nonce_form;
$uri = $_SERVER[ 'REQUEST_URI' ];
if ( current_user_can('editor') || current_user_can('administrator') && check_admin_referer( 'wcpp_secure_form', $nonce ) ) {
/* Start Check Nonce */
    
/* Call Function Set Urls API */
$url = WC_GPP_DebugURLs($this->call_PPing_Class('debug'), $this->call_PPing_Class('url'), '/v1/coupon/All?couponUsed=1');   
$coupon_args = array(
'body' => '',
'timeout' => '45',
'redirection' => '5',
'httpsversion' => '1.0',
'blocking' => true,
'headers' => array(
'Authorization' => 'Bearer '.$token_access,
'Content-Type' => 'application/json',
'Accept' => 'application/json'
),
'cookies' => array()
);
    
$response = wp_remote_get( $url, $coupon_args );
    
/* Call Function Show Debug In Console */
WC_GPP_Debug_Log($this->call_PPing_Class('debug'), $response, "Sync Coupons Result");
    
$header = wp_remote_retrieve_headers($response);
$request_api = $header['x-paypingrequest-id'];
if ( is_wp_error($response) ) {
    echo $Message = $response->get_error_message();
}else{
    $code = wp_remote_retrieve_response_code( $response );
    if ( $code === 200) {
        $body = json_decode( wp_remote_retrieve_body($response), true );
/* set counter variable */       
$up_count = 0;
$ins_count = 0;
        
    foreach( $body as $coupon ){
        
        /* coupon data */
        $coupon_array = get_page_by_title( $coupon['userCouponCode'], 'ARRAY_A', 'shop_coupon' );
        $coupon_id = $coupon_array['ID'];
        
        /* check active coupon */
        if( $coupon['isActive'] === true ){
            $coupon_status = 'publish';
        }else{
            $coupon_status = 'draft';
        }
        
        /* check type coupon */
        if( $coupon['type'] === 0 ){
            $discount_type = 'percent';
        }elseif( $coupon['type'] === 1 ){
            $discount_type = 'fixed_cart';
        }
        
        /* check active product coupon */
        if( $coupon['activeProductCode'] === null ){
            $product_ids = '';
        }else{ 
            $active_product = explode(',', $coupon['activeProductCode']);
            foreach( $active_product as $sku ){
                $product_ids[] = wc_get_product_id_by_sku($sku);
            }
            $product_ids = implode(', ', $product_ids);
        }
        
        /* get data coupon */
        $datas = array(
            'code'                        => $coupon['userCouponCode'],
            'amount'                      => $coupon['amount'],
            'date_created'                => null,
            'date_modified'               => null,
            'date_expires'                => $coupon['redeemDate'],
            'discount_type'               => $discount_type,
            'description'                 => '',
            'usage_count'                 => 0,
            'individual_use'              => false,
            'product_ids'                 => $product_ids,
            'excluded_product_ids'        => array(),
            'usage_limit'                 => $coupon['maxRedemption'],
            'usage_limit_per_user'        => 0,
            'limit_usage_to_x_items'      => null,
            'free_shipping'               => false,
            'product_categories'          => array(),
            'excluded_product_categories' => array(),
            'exclude_sale_items'          => false,
            'minimum_amount'              => '',
            'maximum_amount'              => '',
            'email_restrictions'          => array(),
            'virtual'                     => false,
            'used_by'                     => array(),
	   );
        
        /* check exist coupon in woocommerce */
        if( $coupon_id === NULL ){
            /* data insert coupon code */
            $insert_coupon = array(
                'post_title'                  => $coupon['userCouponCode'],
                'post_content'                => '',
                'post_status'                 => $coupon_status,
                'post_excerpt'                => $coupon['name'],
                'post_author'                 => get_current_user_id(),
                'post_type'                   => 'shop_coupon',
            );
            
            /* insert coupon code */
            $new_coupon_id = wp_insert_post($insert_coupon, true);
            
            /* update coupon meta */
            $this->update_coupon_meta($new_coupon_id, $datas);
            $ins_count++;
        }elseif($coupon_id !== NULL){
            
            /* data update coupon code */
            $update_coupon = array(
                'ID'                          => $coupon_id, 
                'post_title'                  => $coupon['userCouponCode'],
                'post_content'                => '',
                'post_status'                 => $coupon_status,
                'post_excerpt'                => $coupon['name'],
                'post_author'                 => get_current_user_id(),
                'post_type'                   => 'shop_coupon',
            );
            
            /* update coupon code */
            wp_update_post($update_coupon, true);
            
            /* update coupon meta */
            $this->update_coupon_meta($coupon_id, $datas);
            $up_count++;
        }else{
            _e('خطای غیرمنتظره!<br/>', 'woocommerce');
        }
        
    }
   
    /* show message for admin */
    if(isset($notif) && $notif == 1){
        echo '<div class="notice notice-success is-dismissible"><p>'.$ins_count.' کوپن تخفیف ایجاد و '.$up_count.' کوپن تخفیف بروز شد.</p></div>';
    }
        
    }elseif( $code == 400) {
        echo wp_remote_retrieve_response_message( $response );
        echo '<br> شناسه درخواست پی‌پینگ:'.$request_api;
    }else{
        echo wp_remote_retrieve_response_message( $response );
        echo '<br> شناسه درخواست پی‌پینگ:'.$request_api;
    }
    }
    
    /* end check nonce */
}else{
  die('نانس نامعتبر است');
}
   /* end check nonce */
}
        /* end function add products from payping to woocommerce */

/* function update meta_product */
private function update_meta_product($id, $data){
            update_post_meta( $id, '_visibility', $data['_visibility'] );
            update_post_meta( $id, '_stock_status', $data['_stock_status']);
            update_post_meta( $id, 'total_sales', $data['total_sales']);
            update_post_meta( $id, '_downloadable', $data['_downloadable']);
            update_post_meta( $id, '_virtual', $data['_virtual']);
            update_post_meta( $id, '_regular_price', $data['_regular_price'] );
            update_post_meta( $id, '_sale_price', $data['_sale_price'] );
            update_post_meta( $id, '_purchase_note', $data['_purchase_note'] );
            update_post_meta( $id, '_featured', $data['_featured'] );
            update_post_meta( $id, '_weight', $data['_weight'] );
            update_post_meta( $id, '_length', $data['_length'] );
            update_post_meta( $id, '_width', $data['_width'] );
            update_post_meta( $id, '_height', $data['_height'] );
            update_post_meta( $id, '_sku', $data['_sku']);
            update_post_meta( $id, '_product_attributes', $data['_product_attributes']);
            update_post_meta( $id, '_sale_price_dates_from', $data['_sale_price_dates_from'] );
            update_post_meta( $id, '_sale_price_dates_to', $data['_sale_price_dates_to'] );
            update_post_meta( $id, '_price', $data['_price'] );
            update_post_meta( $id, '_sold_individually', $data['_sold_individually'] );
            update_post_meta( $id, '_manage_stock', $data['_manage_stock'] );
            update_post_meta( $id, '_backorders', $data['_backorders'] );
            update_post_meta( $id, '_stock', $data['_stock'] ); 
}
        
/* function update coupon meta */
private function update_coupon_meta($id, $data){

    update_post_meta( $id, 'code', $data['code'] );
    update_post_meta( $id, 'coupon_amount', $data['amount'] );
    update_post_meta( $id, 'date_created', $data['date_created'] );
    update_post_meta( $id, 'date_modified', $data['date_modified'] );
    update_post_meta( $id, 'date_expires', $data['date_expires'] );
    update_post_meta( $id, 'discount_type', $data['discount_type'] );
    update_post_meta( $id, 'description', $data['description'] );
    update_post_meta( $id, 'usage_count', $data['usage_count'] );
    update_post_meta( $id, 'individual_use', $data['individual_use'] );
    update_post_meta( $id, 'product_ids', $data['product_ids'] );
    update_post_meta( $id, 'excluded_product_ids', $data['excluded_product_ids'] );
    update_post_meta( $id, 'usage_limit', $data['usage_limit'] );
    update_post_meta( $id, 'usage_limit_per_user', $data['usage_limit_per_user'] );
    update_post_meta( $id, 'limit_usage_to_x_items', $data['limit_usage_to_x_items'] );
    update_post_meta( $id, 'free_shipping', $data['free_shipping'] );
    update_post_meta( $id, 'product_categories', $data['product_categories'] );
    update_post_meta( $id, 'excluded_product_categories', $data['excluded_product_categories'] );
    update_post_meta( $id, 'exclude_sale_items', $data['exclude_sale_items'] );
    update_post_meta( $id, 'minimum_amount', $data['minimum_amount'] );
    update_post_meta( $id, 'maximum_amount', $data['maximum_amount'] );
    update_post_meta( $id, 'email_restrictions', $data['email_restrictions'] );
    update_post_meta( $id, 'virtual', $data['virtual'] );
    update_post_meta( $id, 'used_by', $data['used_by'] );
        
}
        
/* function add attachment from payping to woocommerce */
private function insert_attachment_from_url($url, $parent_post_id = null) {
	if( !class_exists( 'WP_Http' ) )
		include_once( ABSPATH . WPINC . '/class-http.php' );
	$http = new WP_Http();
	$response = $http->request( $url );
	if( $response['response']['code'] != 200 ) {
		return false;
	}
	$upload = wp_upload_bits( basename($url), null, $response['body'] );
	if( !empty( $upload['error'] ) ) {
		return false;
	}
	$file_path = $upload['file'];
	$file_name = basename( $file_path );
	$file_type = wp_check_filetype( $file_name, null );
	$attachment_title = sanitize_file_name( pathinfo( $file_name, PATHINFO_FILENAME ) );
	$wp_upload_dir = wp_upload_dir();
	$post_info = array(
		'guid'           => $wp_upload_dir['url'] . '/' . $file_name,
		'post_mime_type' => $file_type['type'],
		'post_title'     => $attachment_title,
		'post_content'   => '',
		'post_status'    => 'inherit',
	);
	// Create the attachment
	$attach_id = wp_insert_attachment( $post_info, $file_path, $parent_post_id );
	// Include image.php
	require_once( ABSPATH . 'wp-admin/includes/image.php' );
	// Define attachment metadata
	$attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
	// Assign metadata to attachment
	wp_update_attachment_metadata( $attach_id,  $attach_data );
	return $attach_id;
}

    }
    /* end class Control_Vip_Payping */

function register_my_custom_submenu_page() {
    add_submenu_page( 'woocommerce', __('پی‌پینگ تجاری', 'woocommerce'), __('Payping-VIP', 'woocommerce'), 'manage_options', 'vip-payping', 'wvpp_control_page_items' ); 
}

function wvpp_control_page_items() {
    $cvp = new WVPP_Control_Vip_Payping();
    $token = $cvp->call_PPing_Class('token');
    
    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">همسان‌سازی‌ها</h1>';
    echo '<hr class="wp-header-end">';

    if(isset($_POST['submit_cat'])){
        $cvp->add_product_cat($token, $_POST['st_token'], 1);
    }
    if(isset($_POST['submit_product'])){
        $cvp->add_product($token, $_POST['st_token'], 1);
    }
    if(isset($_POST['submit_coupons'])){
        $cvp->add_coupon($token, $_POST['st_token'], 1);
    }
    if(isset($_POST['submit_all'])){
        $cvp->add_product_cat($token, $_POST['st_token'], 1);
        $cvp->add_product($token, $_POST['st_token'], 1);
        $cvp->add_coupon($token, $_POST['st_token'], 1);
    }
    
    $wvpp_nonce = wp_create_nonce('insertORedit');
    echo '<div id="dashboard-widgets" class="metabox-holder">'; ?>
    
    <!-- start postbox-container -->
	<div id="postbox-container-1" class="postbox-container">
	   <div id="column4-sortables" class="meta-box-sortables ui-sortable">
          <div id="side-sortables" class="meta-box-sortables ui-sortable">
          <div id="dashboard_quick_press" class="postbox">
          <h2 class="hndle ui-sortable-handle"><span>یکسان سازی دسته های محصولات</span></h2>
          <div class="inside" style="padding: 10px;">
            <p>برای یکسان سازی تمامی دسته های فروشگاه خود با سرویس پی پینگ بر روی دکمه دسته محصولات کلیک کنید.</p>
          </div>
           <form method="post" action="">
                <?php wp_nonce_field( 'wvpp_secure_form', 'st_token' ); ?>
                <?php submit_button( __( 'دسته محصولات', 'woocommerce' ), 'primary', 'submit_cat', true, null ); ?>
           </form>
              </div>
              </div>
       </div>
    </div>
    <!-- end postbox-container -->
    <!-- start postbox-container -->
    <div id="postbox-container-2" class="postbox-container">
	   <div id="column4-sortables" class="meta-box-sortables ui-sortable">
          <div id="side-sortables" class="meta-box-sortables ui-sortable">
          <div id="dashboard_quick_press" class="postbox">
          <h2 class="hndle ui-sortable-handle"><span>یکسان سازی محصولات</span></h2>
          <div class="inside" style="padding: 10px;">
            <p>برای یکسان سازی تمامی محصولات فروشگاه خود با سرویس پی پینگ بر روی دکمه محصولات کلیک کنید <span style="color:red;">به دلیل انتقال تصاویر ممکن است چند دقیقه زمان نیاز باشد</span>.</p>
          </div>
           <form method="post" action="">
                <?php wp_nonce_field( 'wvpp_secure_form', 'st_token' ); ?>
                <?php submit_button( __( 'محصولات', 'woocommerce' ), 'primary', 'submit_product', true, null ); ?>
           </form>
              </div>
              </div>
       </div>
    </div>
    <!-- end postbox-container -->
    <!-- start postbox-container -->
    <div id="postbox-container-1" class="postbox-container">
	   <div id="column4-sortables" class="meta-box-sortables ui-sortable">
          <div id="side-sortables" class="meta-box-sortables ui-sortable">
          <div id="dashboard_quick_press" class="postbox">
          <h2 class="hndle ui-sortable-handle"><span>یکسان سازی کدهای تخفیف</span></h2>
          <div class="inside" style="padding: 10px;">
            <p>برای یکسان سازی تمامی کدهای تخفیف فروشگاه خود با سرویس پی پینگ  بر روی دکمه کد تخفیف کلیک کنید.</p>
          </div>
           <form method="post" action="">
                <?php wp_nonce_field( 'wvpp_secure_form', 'st_token' ); ?>
                <?php submit_button( __( 'کد تخفیف', 'woocommerce' ), 'primary', 'submit_coupons', true, null ); ?>
           </form>
              </div>
              </div>
       </div>
    </div>
    <!-- end postbox-container -->
    <!-- start postbox-container -->
    <div id="postbox-container-2" class="postbox-container">
	   <div id="column4-sortables" class="meta-box-sortables ui-sortable">
          <div id="side-sortables" class="meta-box-sortables ui-sortable">
          <div id="dashboard_quick_press" class="postbox">
          <h2 class="hndle ui-sortable-handle"><span>یکسان‌سازی همه موارد</span></h2>
          <div class="inside" style="padding: 10px;">
            <p>برای یکسان سازی تمامی موارد در فروشگاه خود با سرویس پی پینگ بر روی دکمه یکسان‌سازی کلی کلیک کنید <span style="color:red;">به دلیل انتقال تصاویر ممکن است چند دقیقه زمان نیاز باشد</span>.</p>
          </div>
           <form method="post" action="">
                <?php wp_nonce_field( 'wvpp_secure_form', 'st_token' ); ?>
                <?php submit_button( __( 'یکسان‌سازی کلی', 'woocommerce' ), 'primary', 'submit_all', true, null ); ?>
           </form>
              </div>
              </div>
       </div>
    </div>
    <!-- end postbox-container -->
    <?php
    
    echo '</div>';
    echo '</div>';
}
add_action('admin_menu', 'register_my_custom_submenu_page',99);
}
add_action('plugins_loaded', 'wvpp_load_cvp', 1);

?>