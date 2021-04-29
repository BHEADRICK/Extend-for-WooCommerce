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
		$this->warranty_product_id = 415768;
	}

	/**
	 * Initiate our hooks.
	 *
	 * @since  0.0.0
	 */
	public function hooks() {
		add_action('woocommerce_ajax_added_to_cart', [$this, 'ajax_added_to_cart']);
		add_action('woocommerce_add_to_cart', [$this, 'add_to_cart'], 10, 6);
		add_filter('woocommerce_cart_item_name', [$this, 'cart_item_name'], 10, 3);
		add_filter('woocommerce_order_item_name', [$this, 'order_item_name'], 10, 3);
		add_action('woocommerce_before_calculate_totals', [$this, 'update_price']);
		add_filter('woocommerce_get_item_data', [$this, 'checkout_details'], 10, 2);

		add_action('woocommerce_checkout_create_order_line_item', [$this, 'order_item_meta'], 10, 3);
		add_action('woocommerce_order_status_processing', [$this, 'maybe_send_contract']);
		add_action('woocommerce_order_fully_refunded', [$this, 'process_full_refund']);
		add_action('woocommerce_order_status_refunded', [$this, 'process_full_refund']);
		add_filter('woocommerce_add_cart_item_data', [$this, 'unique_cart_items'], 10, 2);
		add_action('woocommerce_create_refund', [$this, 'process_partial_refund'], 10, 2);
		add_action('woocommerce_check_cart_items', [$this, 'validate_cart']);

		/** todo
		 *
		 *  if there are more coverage items than covered items,
		 *  display message that one must be removed, and do not allow continue to checkout until this is done.
		 * 
		 *  covered item is compoletely removed from cart, automatically remove associated coverage items
		 *
		 *
		 *  allow to add coverage from cart page
		 */
	}

	public function validate_cart(){


		$items = [];


		$coverage_items = [];
		foreach(WC()->cart->get_cart_contents() as $line){

			if($line['product_id'] === $this->warranty_product_id){
				$covered_id =
						$line['extendData']['covered_product_id'];

				if(!isset($coverage_items[$covered_id])){
					$coverage_items[$covered_id]=[
						'qty'=>1,
						'keys'=>[$line['key']]
					];
				}else{
					$coverage_items[$covered_id]['qty']++;
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

			if(isset($items[$prod_id]) && $items[$prod_id]['qty'] < $coverage['qty']){
				$name = $items[$prod_id]['title'];
				$diff = $coverage['qty'] - $items[$prod_id]['qty'];
				wc_add_notice("There are more Warranty products in the cart than $name Remove $diff to continue", 'error');
				return false;
			}elseif(!isset($items[$prod_id])){

				foreach($coverage['keys'] as $cart_item_key){
					WC()->cart->remove_cart_item( $cart_item_key );
				}

				return false;
			}
		}
	}

	/**
	 * @param $refund WC_Order_Refund
	 * @param $args array
	 */
	public function process_partial_refund($refund, $args){


		$order_id = $refund->get_parent_id();

		$extend_data = get_post_meta($order_id, '_extend_data', true);

		if($extend_data){
			$items = $refund->get_items();

			foreach($items as $item){
				if($item->get_product_id() === $this->warranty_product_id && isset($extend_data[$item->get_id()])){

					$contract_id = $extend_data[$item->get_id()];

					$res = $this->plugin->remote_request('/contracts/' . $contract_id . '/refund', 'POST', [], ['commit'=>true]);

//					$statuses[$covered_id]= $res['response_body']->status;
				}
			}
		}




	}

	public function unique_cart_items($cart_item_data, $product_id){

	if($product_id === $this->warranty_product_id){
		$unique_cart_item_key = md5( microtime() . rand() );
		$cart_item_data['unique_key'] = $unique_cart_item_key;

	}


		return $cart_item_data;
		
	}

	public function process_full_refund($order_id){

		$contracts = get_post_meta($order_id, '_extend_contracts', true);

//		$statuses = [];
		if($contracts){

			foreach($contracts as $item_id=>$contract_id){

				$res = $this->plugin->remote_request('/contracts/' . $contract_id . '/refund', 'POST', [], ['commit'=>true]);

//				$statuses[$item_id]= $res['response_body']->status;


			}
		}


	}
	public function maybe_send_contract($order_id){

		$this->send_contracts($order_id);

	}


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

		}

	}

	/**
	 * @param $order_id integer
	 * @param $posted_data array
	 * @param $order WC_ORder
	 */
	public function process_checkout($order_id, $posted_data, $order){

		$this->send_contracts( $order_id, $order );


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
			$data[] = [
				'key'=>'Plan Id',
				'value'=>$sku
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

	public function add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data){


		if(isset($_POST['planData'])){
			$plan = json_decode(str_replace('\\', '', $_POST['planData']), true);
			unset($_POST['planData']);
			if(empty($plan)){
				return;
			}
			$plan['covered_product_id'] = $variation_id?$variation_id: $product_id;
			$qty = filter_input(INPUT_POST, 'quantity');
			try{
				for($i = 0; $i < $qty; $i++){
					WC()->cart->add_to_cart($this->warranty_product_id, 1, 0, 0, ['extendData'=>$plan] );
				}

			}catch(Exception $e){
				error_log($e->getMessage());
			}
		}

		if(isset($cart_item_data['extendData'])){


			$price = round($cart_item_data['extendData']['price']/100, 2);

			WC()->cart->cart_contents[$cart_item_key]['data']->set_price($price);

		}

	}


	/**
	 * @param $order_id
	 * @param $order
	 */
	private function send_contracts( $order_id, $order = null) {

		if($order === null){
			$order = wc_get_order($order_id);
		}
		$items     = $order->get_items();
		$contracts = [];
		$prices    = [];
		foreach ( $items as $item ) {
			if ( $item->get_product_id() === $this->warranty_product_id ) {
				$contracts[] = $item;
			} else {
				$prices[ $item->get_product_id() ] = $item->get_subtotal() / $item->get_quantity();
			}
		}
		if ( ! empty( $contracts ) ) {
			$contract_ids = [];
			foreach ( $contracts as $item ) {
				$item_id = $item->get_id();
				$data = $item->get_meta( '_extend_data' );
				if ( $data ) {

					$covered_id = $data['covered_product_id'];

					$contract_data = [
						'transactionId'    => $order_id,
						'poNumber'         => $order->get_order_number(),
						'transactionTotal' => [
							'currencyCode' => 'USD',
							'amount'       => $order->get_total()*100
						],
						'customer'         => [
							'name'            => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
							'email'           => $order->get_billing_email(),
							'phone'           => $order->get_billing_phone(),
							'billingAddress'  => [
								'address1'     => $order->get_billing_address_1(),
								'address2'     => $order->get_billing_address_2(),
								'city'         => $order->get_billing_city(),
								'countryCode'  => $order->get_billing_country(),
								'postalCode'   => $order->get_billing_postcode(),
								'provinceCode' => $order->get_billing_state()
								],
							'shippingAddress' => [
								'address1'     => $order->get_shipping_address_1(),
								'address2'     => $order->get_shipping_address_2(),
								'city'         => $order->get_shipping_city(),
								'countryCode'  => $order->get_shipping_country(),
								'postalCode'   => $order->get_shipping_postcode(),
								'provinceCode' => $order->get_shipping_state()
								],
							],
							'product'         => [
								'referenceId'   => $covered_id,
								'purchasePrice' => [
									'currencyCode' => 'USD',
									'amount'       => $prices[ $covered_id ] * 100
								]
							],
							'currency'        => 'USD',
							'source'          => [
								'agentId'      => '',
								'channel'      => 'web',
								'integratorId' => 'netsuite',
								'locationId'   => $this->plugin->store_id,
								'platform'     => 'woocommerce'
							],
							'transactionDate' => strtotime( $order->get_date_paid() ),
							'plan'            => [
								'purchasePrice' => [
									'currencyCode' => 'USD',
									'amount'       => $data['price']
								],
								'planId'        => $data['planId']
							]

					];

				$res =	$this->plugin->remote_request( '/contracts', 'POST', $contract_data );

				if($res['response_code'] === 201){
					$item->add_meta_data("Extend Status", $res['response_body']->status);
				$contract_ids[$item_id]=	$res['response_body']->id;
				}


//				error_log(print_r($res, true));
				}


			}
			if(!empty($contract_ids)){



				update_post_meta($order_id, '_extend_contracts', $contract_ids);
			}
		}
	}
}
