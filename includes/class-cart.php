<?php
/**
 * Extend for WooCommerce Cart.
 *
 * @since   0.0.0
 * @package Extend_For_WooCommerce
 */

/**
 * Extend for WooCommerce Cart.
 *
 * @since 0.0.0
 */
class EFWC_Cart {
	/**
	 * Parent plugin class.
	 *
	 * @since 0.0.0
	 *
	 * @var   Extend_For_WooCommerce
	 */
	protected $plugin = null;
	protected $warranty_product_id = null;

	/**
	 * Constructor.
	 *
	 * @since  0.0.0
	 *
	 * @param  Extend_For_WooCommerce $plugin Main plugin object.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->hooks();
		$this->warranty_product_id = get_option('wc_extend_product_id');
	}

	/**
	 * Initiate our hooks.
	 *
	 * @since  0.0.0
	 */
	public function hooks() {


//		add_action('woocommerce_ajax_added_to_cart', [$this, 'ajax_added_to_cart']);
		add_action('woocommerce_add_to_cart', [$this, 'add_to_cart'], 10, 6);
		add_filter('woocommerce_cart_item_name', [$this, 'cart_item_name'], 10, 3);
		add_filter('woocommerce_order_item_name', [$this, 'order_item_name'], 10, 3);
		add_action('woocommerce_before_calculate_totals', [$this, 'update_price']);
		add_filter('woocommerce_get_item_data', [$this, 'checkout_details'], 10, 2);

		add_action('woocommerce_checkout_create_order_line_item', [$this, 'order_item_meta'], 10, 3);

		add_action('woocommerce_order_fully_refunded', [$this, 'process_full_refund']);
		add_action('woocommerce_order_status_refunded', [$this, 'process_full_refund']);
		add_filter('woocommerce_add_cart_item_data', [$this, 'unique_cart_items'], 10, 2);
		add_action('woocommerce_create_refund', [$this, 'process_partial_refund'], 10, 2);
		add_action('woocommerce_check_cart_items', [$this, 'validate_cart']);

		add_action('woocommerce_after_cart_item_name', [$this, 'after_cart_item_name'], 10, 2);
		add_action('woocommerce_after_cart', [$this, 'cart_offers']);

		add_action('add_meta_boxes', [$this, 'meta_boxes']);


	}

	public function extend_metabox(){
		global $post, $wpdb;

		$contracts = $wpdb->get_results("select * from $wpdb->prefix{$this->plugin->table_name} where order_id = {$post->ID}");

		if($contracts){

			echo " <ul>";

			foreach($contracts as $contract){

					if(!empty($contract->contract_number)) {
						echo "<li>Contract id: $contract->contract_number";
					}else {
						echo "<li>Extend Contract(s) prepared, but not yet sent</li>";
					}


				}

			}
		else{
			echo '<p>No Extend Contracts found</p>';
		}

	}

	public function meta_boxes(){
		add_meta_box('extend_metabox',
			'Extend Info',
			[$this, 'extend_metabox'],
			'shop_order', 'side');
	}

	private function product_has_coverage($product_id){
		foreach(WC()->cart->get_cart_contents() as $line) {

			if ( intval( $line['product_id'] ) === intval( $this->warranty_product_id ) ) {
				if(intval($product_id) ===
					intval($line['extendData']['covered_product_id'])){
					return true;
				}
			}
		}
		return false;
	}

	public function cart_offers(){
		$offers = [];
		foreach(WC()->cart->get_cart_contents() as $line) {

			if ( intval( $line['product_id'] ) !== intval( $this->warranty_product_id ) ) {
				$offers[] =
					$line['variation_id']>0?$line['variation_id']:$line['product_id'];
			}
		}

			$store_id = $this->plugin->store_id;
		$enabled = get_option('wc_extend_enabled')==='yes';

		$warranty_prod_id = $this->warranty_product_id;

			$environment = $this->plugin->mode === 'sandbox'?'demo':'live';

			$ids = array_unique($offers);
			if($store_id && $enabled){
				wp_enqueue_script('extend_script');
				wp_enqueue_script('extend_cart_script');
				wp_localize_script('extend_cart_script', 'WCCartExtend', compact('store_id',  'ids', 'environment', 'warranty_prod_id'));

			}



	}

	public function after_cart_item_name($cart_item, $key){


		if(!isset($cart_item['extendData'])){

			$item_id = $cart_item['variation_id']?$cart_item['variation_id']:$cart_item['product_id'];
			if(!$this->product_has_coverage($item_id)  && !$this->plugin->products->isExcluded($cart_item['data'])){
				echo "<div id='offer_$item_id' class='cart-extend-offer' data-covered='$item_id'> ";
			}
		}

	}

	public function validate_cart(){


		$items = [];


		$coverage_items = [];
		foreach(WC()->cart->get_cart_contents() as $line){

			if(intval($line['product_id']) === intval($this->warranty_product_id) && isset($line['extendData'])){



				$covered_id =
						$line['extendData']['covered_product_id'];

				if(!isset($coverage_items[$covered_id])){
					$coverage_items[$covered_id]=[
						'qty'=>$line['quantity'],
						'keys'=>[$line['key']]
					];
				}else{
					$coverage_items[$covered_id]['qty'] += $line['quantity'];
					$coverage_items[$covered_id]['keys'][] = $line['key'];
				}

			}else{

				$id = $line['variation_id']>0?$line['variation_id']:$line['product_id'];
				$qty = intval($line['quantity']);
				if(!isset($items[$id])){

					$items[$id] = [
						'title'=>$line['data']->get_name(),
						'qty'=>$qty
					];
				}else{
					$items[$id]['qty'] += $qty;
				}


			}

		}


		foreach($coverage_items as $prod_id=>$coverage){

			if(isset($items[$prod_id]) && $items[$prod_id]['qty'] != $coverage['qty']){
				$name = $items[$prod_id]['title'];
				$diff = $coverage['qty'] - $items[$prod_id]['qty'];
				foreach($coverage['keys'] as $cart_item_key){
					WC()->cart->set_quantity( $cart_item_key ,$items[$prod_id]['qty'] );
				}

			}elseif(!isset($items[$prod_id])){

				foreach($coverage['keys'] as $cart_item_key){
					WC()->cart->remove_cart_item( $cart_item_key );
				}

				return false;
			}
		}
	}

	private function capture_refund_data($data){

		$body = $data['response_body'];

		$refunded = $body->refundedAt;
		$status = $body->status;
		$id = $body->id;

		return compact('refunded', 'status', 'id');

	}

	public function process_full_refund($order_id){


		global  $wpdb;

		$contracts = $wpdb->get_results("select * from $wpdb->prefix{$this->plugin->table_name}  where order_id = {$order_id}");
	
		if($contracts){

			$refund_details = [];
			foreach($contracts as $contract){

				if($contract->contract_number !== ''){
					$contract_id = $contract->contract_number;

					$res = $this->plugin->remote_request('/contracts/' . $contract_id . '/refund', 'POST', [], ['commit'=>true]);
					$refund_details[$contract_id]= $this->capture_refund_data($res);
				}else{
					$wpdb->update("$wpdb->prefix{$this->plugin->table_name}",['contract_number'=>'refunded'], ['id'=>$contract->id]);
				}

			}


			update_post_meta($order_id, '_extend_refund_data', $refund_details);
		}


	}

	/**
	 * @param $refund WC_Order_Refund
	 * @param $args array
	 */
	public function process_partial_refund($refund, $args){
		global $wpdb;



		$order_id = $refund->get_parent_id();
		$contracts = $wpdb->get_results("select * from $wpdb->prefix{$this->plugin->table_name} where order_id = $order_id");
		if($contracts){
			$refund_details = [];
			foreach($contracts as $contract){
				if(!empty($contract->contract_number)){

					foreach($args['line_items'] as $item_id=> $item){
						if( $item['refund_total']>0 && isset($extend_data[$item_id])){

							$contract_id = $contract->contract_number;

							$res = $this->plugin->remote_request('/contracts/' . $contract_id . '/refund', 'POST', [], ['commit'=>true]);

							$refund_details[$contract_id]=  $this->capture_refund_data($res);

						}
					}
					update_post_meta($order_id, '_extend_refund_data', $refund_details);
				}else{
					$wpdb->update("$wpdb->prefix{$this->plugin->table_name}",['contract_number'=>'refunded'], ['id'=>$contract->id]);
				}
			}
			update_post_meta($order_id, '_extend_refund_data', $refund_details);

		}

		}


	public function unique_cart_items($cart_item_data, $product_id){

	if($product_id === intval($this->warranty_product_id)){
		$unique_cart_item_key = md5( microtime() . rand() );
		$cart_item_data['unique_key'] = $unique_cart_item_key;

	}


		return $cart_item_data;
		
	}


	/**
	 * @param $item WC_Order_Item
	 * @param $cart_item_key string
	 * @param $cart_item array
	 */
	public function order_item_meta($item, $cart_item_key, $cart_item ){
		if(isset($cart_item['extendData'])){
			$item->add_meta_data('_extend_data', $cart_item['extendData']);


			$covered_id = $cart_item['extendData']['covered_product_id'];
			$term = $cart_item['extendData']['term'];
			$title = $cart_item['extendData']['title'];
			$covered = wc_get_product($covered_id);
			$sku = $cart_item['extendData']['planId'];
			$covered_title = $covered->get_title();



			$item->add_meta_data('Warranty', $title);
			$item->add_meta_data('Warranty Term', $term . ' Months');
			$item->add_meta_data('Plan Id', $sku);
			$item->add_meta_data('Covered Product', $covered_title);

			update_post_meta($item->get_order_id(), '_has_extend', true);

		}

	}



	public function checkout_details($data, $cart_item){

		if(!is_cart() && !is_checkout()){
			return $data;
		}

		if(isset($cart_item['extendData'])){
			$covered_id = $cart_item['extendData']['covered_product_id'];
			$term = $cart_item['extendData']['term'];
			$covered = wc_get_product($covered_id);
			$sku = $cart_item['extendData']['planId'];
			$covered_title = $covered->get_title();
//			$cart_item['title'] =

			$data[] =[
				'key'=>'Covered Product',
				'value'=>$covered_title
			];
			$data[] =[
				'key'=>'Coverage Term',
				'value'=>$term . ' Months'
			];

		}



		return $data;
	}



	public function update_price($cart_object){
		$cart_items = $cart_object->cart_contents;

		if ( ! empty( $cart_items ) ) {

			foreach ( $cart_items as $key => $value ) {
				if(isset($value['extendData'])){

					$value['data']->set_price( round($value['extendData']['price']/100, 2) );
				}

			}
		}
	}


	public function order_item_name($name, $cart_item, $cart_item_key){



		$meta = $cart_item->get_meta('_extend_data');
		if($meta){
			return $meta['title'];
		}

		return $name;
	}
	public function cart_item_name($name, $cart_item, $cart_item_key){


		if(isset($cart_item['extendData'])){
			return $cart_item['extendData']['title'];
		}
		return $name;
	}

	private function maybe_update_warranty_qty($product_id, $add_qty){


		foreach(WC()->cart->get_cart() as $item){

			if(isset($item['extendData']) && intval($item['extendData']['covered_product_id']) === intval($product_id)){

				$key = $item['key'];
				$qty = intval($item['quantity']);
				WC()->cart->set_quantity( $key , ($add_qty + $qty) );
				return true;
			}
		}

		return false;
	}

	public function add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data){





		if(isset($_POST['planData'])){
			$plan = json_decode(str_replace('\\', '', $_POST['planData']), true);
			unset($_POST['planData']);
			if(empty($plan)){
				return;
			}
			$plan['covered_product_id'] = $variation_id?$variation_id: $product_id;
			$qty = filter_input(INPUT_POST, 'quantity');

			if(!$this->maybe_update_warranty_qty($product_id, $qty)){
				try{

					WC()->cart->add_to_cart($this->warranty_product_id, $qty, 0, 0, ['extendData'=>$plan] );


				}catch(Exception $e){
					error_log($e->getMessage());
				}
			}


		}

		if(isset($_POST['extendData'])){
			try{
			$plan = $_POST['extendData'];
			WC()->cart->cart_contents[$cart_item_key]['extendData'] = $plan;
			$price = round($plan['price']/100, 2);

			WC()->cart->cart_contents[$cart_item_key]['data']->set_price($price);
			}catch(Exception $e){
				error_log($e->getMessage());
				error_log($e->getTraceAsString());
			}

		}




	}



	/**
	 * @param $order_id integer
	 * @param $order WC_Order
	 * @param $product_id integer
	 * @param $qty integer
	 */
	private function send_lead($order_id, $order, $product_id, $qty, $price){

		$lead_data = [
			'customer'=>[
				'email'=>$order->get_billing_email()
			],
			'quantity'=>$qty,
			'product'=>[
				'purchasePrice'=>[
					'currencyCode' => 'USD',
					'amount'       => $price
				],
				'referenceId'=>$product_id,
				'transactionDate'=>strtotime($order->get_date_paid()),
				'transactionId'=>$order_id
			]
		];

		$res = $this->plugin->remote_request('/leads', 'POST', $lead_data);

	}

}
