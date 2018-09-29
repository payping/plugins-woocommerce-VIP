<?php
class PayPingAPI {
	private $access_token = ''; //invoice
	private $debug = false;

	function debug_log( $object=null, $label=null ){ 
		$message = json_encode($object, JSON_PRETTY_PRINT);
		$label = "Debug" . ($label ? " ($label): " : ': '); 
		echo "<script>console.log(\"$label\", $message);</script>"; }
	
	public function PayPingAPI($access_token, $debug=false){
		if($access_token==''){
			echo 'token empty!';
			exit();
		}
		$this->access_token = $access_token;
		$this->debug = $debug;
	}	
	
	private function api_post($url, $content,$order_id = '',$sendPluginDetails=true){
		debug_log($content,"api_post content:");
		$header = array("Content-Type: application/json");
		array_push($header, "Authorization: Bearer ".$this->access_token);
		if($sendPluginDetails)
		{
			array_push($header, "PluginVersion: 1.3");
		    array_push($header, "PluginName: woocomerce");
		    array_push($header, "WcOrderId: ".$order_id);
		}
		
		
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST,1);
		$t=json_encode($content);//, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		curl_setopt($ch, CURLOPT_POSTFIELDS, $t);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		$output = curl_exec($ch);
		curl_close($ch);
		// if($this->debug)
		// 	echo '<pre>'.$output.'</pre>';
		debug_log($output,"api_post output:");
		return json_decode($output, true);
	}
	
	private function api_get($url,$sendPluginDetails=true){
		$header = array("Accept: application/json");
		

		array_push($header, "Authorization: Bearer ".$this->access_token);
		if($sendPluginDetails)
		{
			array_push($header, "PluginVersion: 1.3");
			array_push($header, "PluginName: woocomerce");
		}

		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST,0);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		$output = curl_exec($ch);
		curl_close($ch);
		// if($this->debug)
		// 	echo '<pre>'.$output.'</pre>'; 
		return json_decode($output, true);
	}
	
	
	public function SendVerifyPayment($RefId, $invoicecode, $order_id){
		$content= array(
			"InvoiceCode" => $invoicecode,
			"RefId" => "$RefId"
		);
		$res = $this->api_post("https://api.payping.ir/v1/invoice/confirmpaymentbyplugin", $content, $order_id);
		return $res;
	}

	public function add_invoice($order_id, $invoiceDate, $invoiceItems, $customerCode, $shipping, $return_url, $otherDiscountAmount, $otherDiscountPercent){
		$content = array(
			"billToes" => array(
				array(
					"addressBookCode" => $customerCode,
				)
			),
			"createStatus"=> 1,// صفر = ذخیره در پیش نویس , یک = ارسال به مشتری جهت انجام عملیات پرداخت و تسویه
			"paidManualDescription" => "",
			"saveToTemplate" => false,
			"templateCode" => "",
			"invoiceNumber" => $order_id,
			"invoiceTitle" => "فاکتور فروش",
			"isSendAttachmentsAfterPayment" => false, //نمایش فایل پس از پرداخت موفق در صفحه نمایش فاکتور
			"isSendNotesAndTermsAfterPayment" => false, //نمایش پیام پس از پرداخت موفق در صفحه نمایش فاکتور
			"invoiceDateTime" => $invoiceDate, //تاریخ ایجاد فاکتور
			"dueDate" => $invoiceDate, // تاریخ سررسید فاکتور
			"totalDiscountValue"=> $otherDiscountAmount,// مبلغ تخفیف کلی روی مبلغ نهایی فاکتور
            "totalDiscountType"=> 0,
            "totalDiscountCouponCode"=> "",
            "totalTaxtionValue"=> 0,
            "totalTaxtionType"=> 0,
			"shipping" => $shipping, // مبلغ کرایه و حمل و نقل
            "notes"=> "",
            "termsAndConditions"=> "",
            "memo"=> "",
			"invoiceSchulder" => null,
			"invoiceItems" => $invoiceItems,
			"returnUrl" => $return_url,
			"attachmentsIds" => [],
			
		);
		$result = $this->api_post("https://api.payping.ir/v1/invoice", $content,$order_id);
		//var_dump($result);
		return $result;
	}
	
	public function add_item($name, $desc, $price){
		$content = array(
			"title" => "$name",
			"description" => "$desc",
			"amount" => $price,
			"quantity"=> 0,
			"haveTax" => false,
			"unlimited" => false,
			"imageLink" => "",
			"defineAmountByUser" => false
		);
		$result = $this->api_post("https://api.payping.ir/v1/product", $content,null,false);
		if(isset($result['code'])){
			return $result['code'];
		}
		return 'nores';
	}

	
	
	public function get_item($code='all'){
		$url = "https://api.payping.ir/v1/product";
		if($code!='all')
			$url .= '/'.$code;
		else
			$url .= '/list';
		
		$result = $this->api_get($url, 2,false);
		return $result;
	}
	
	function add_customer($fname, $lname, $email='', $phone=''){
		$content = array(
			//"userPhotoFileId" => "",
			"email" => "$email",
			"phone" => "$phone",
			"firstName" => "$fname",
			"lastName" => "$lname",
			//"businessName" => "",
			//"additionalInfo" => "",
			//"zipCode" => "",
			//"state" => "",
			//"city" => "",
			//"location" => "",
			//"memo" => "",
			//"isLegal" => false,
			//"nationalId" => ""
		);
		$result = $this->api_post("https://api.payping.ir/v1/addressbook", $content);
		if(isset($result['code'])){
			return $result['code'];
		}
		return 'nocode';
	}
}
?>
