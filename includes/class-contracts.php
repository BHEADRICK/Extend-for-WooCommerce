<?php
/**
 * Extend for WooCommerce Contracts.
 *
 * @since   0.0.0
 * @package Extend_For_WooCommerce
 */

/**
 * Extend for WooCommerce Contracts.
 *
 * @since 0.0.0
 */
class EFWC_Contracts {
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
		add_action('woocommerce_order_status_processing', [$this, 'maybe_send_contracts'], 10, 2);
		add_action('woocommerce_order_status_awaiting-shipment', [$this, 'maybe_send_contracts'], 10, 2);
		add_action('woocommerce_order_status_completed', [$this, 'maybe_send_contracts'], 10, 2);

		add_action(strtolower( get_class($this->plugin)). '_send_contracts', [$this, 'get_contracts']);
	}

	public function get_contracts(){

		global $wpdb;

		$contracts = $wpdb->get_results("select id, order_id, product_id, warranty_price, product_price, warranty_plan_id from $wpdb->prefix{$this->plugin->table_name} where date_scheduled < curdate() and (contract_number = '' or contract_number is null)");


		foreach($contracts as $contract){
			$this->send_contract($contract->order_id, $contract->product_id, $contract->product_price, $contract->warranty_price, $contract->warranty_plan_id, $contract->id);
		}

	}
	/**
	 * @param $contract_data
	 * @param $contract_ids
	 * @param $item_id
	 * @param $item
	 */
	public function send_contract(  $order_id,  $covered_id, $product_price, $plan_price, $plan_id, $contract_id ) {

		global $wpdb;


		error_log('attempting to send contract for order id: ' . $order_id);

		$order = wc_get_order($order_id);

		if(!$order){
			error_log("not an order: $order_id");
			return;
		}

		$status = $order->get_status();

		if(in_array($status, ['cancelled', 'refunded', 'failed'])){

			error_log("unable to send. order status is $status");

			$wpdb->update("$wpdb->prefix{$this->plugin->table_name}", ['contract_number'=> "$status order"], ['order_id'=> $order_id] );

			return;
		}
		if(!$order->get_date_paid()){
			error_log("order not paid: $order_id");
			$trans_date = $order->get_date_modified()->getTimestamp();
		}else{

			$trans_date = $order->get_date_paid()->getTimestamp();
		}

		$contract_data = $this->get_contract_data($order, $covered_id, $product_price, $plan_price, $plan_id, $trans_date );
//		error_log(print_r($contract_data, true));

		$res = $this->plugin->remote_request( '/contracts', 'POST', $contract_data );



		if ( intval( $res['response_code'] ) === 201 ) {



			$contract_number = $res['response_body']->id;

			$wpdb->update($wpdb->prefix . $this->plugin->table_name , compact('contract_number'), ['id'=>$contract_id]);
		}else{
			error_log(print_r($res, true));
		}

	}

	private function order_has_coverage($order){
		$items     = $order->get_items();
		foreach ( $items as $item ) {
			if ( intval( $item->get_product_id() ) === intval( $this->warranty_product_id ) ) {
				return true;
			}

		}
		return false;
	}

	public function maybe_send_contracts($order_id, $order){



		error_log("checking order $order_id");

		if($this->order_has_coverage($order)){
			global $wpdb;
			if(!$wpdb->get_var("select count(id) from $wpdb->prefix{$this->plugin->table_name} where order_id = $order_id")){
				$this->schedule_contracts($order_id, $order);
			}

		}

	}




	/**
	 * @param $order_id integer
	 * @param $order WC_Order
	 */
	public function schedule_contracts( $order_id, $order) {
		global $wpdb;
		error_log("scheduling contract(s) for order $order_id");
		$items     = $order->get_items();
		$contracts = [];
		$prices    = [];
		if($wpdb->get_var("select count(id) from $wpdb->prefix{$this->plugin->table_name} where order_id = $order_id")){
			return;
		}

//		$leads = [];
		$date = $order->get_date_paid();



		$method = $order->get_payment_method();

		if(!$date && $method === 'bread_finance'){
			$bread_status = get_post_meta($order_id, 'bread_tx_status', true);
			if(in_array($bread_status, ['authorized', 'settled'])){
				$date = $order->get_date_modified();
			}

		}elseif(!$date){
			$date = $order->get_date_modified();
		}
		foreach ( $items as $item ) {
			if ( intval( $item->get_product_id() ) === intval( $this->warranty_product_id ) ) {
				$qty = $item->get_quantity();
				for ( $i = 0; $i < $qty; $i ++ ) {
					$contracts[] = $item;
				}

			} else {
				$prod_id            = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
				$prices[ $prod_id ] = $item->get_subtotal() / $item->get_quantity();
			}
		}

		if ( ! empty( $contracts ) ) {



			if($date === null || $date === false){
				error_log("order $order_id is not paid");
				return;
			}

			$date_created = $date->format('Y-m-d H:i:s');

			foreach ( $contracts as $item ) {
				
				$data    = $item->get_meta( '_extend_data' );

				if ( $data ) {

					$covered_id = $data['covered_product_id'];

					$delay = $this->get_product_shipping_estimate(wc_get_product($covered_id));
						$ref_date = clone $date;
					$projected_ship_date_obj =  date_add($ref_date, date_interval_create_from_date_string("$delay days"));

					if(!$projected_ship_date_obj){
						error_log('failure to create projected ship date for ' . $order_id);
						return;
					}
					$date_scheduled = $projected_ship_date_obj->format('Y-m-d H:i:s');



					$order_number = $order->get_order_number();
					$product_id = $covered_id;
					$product_price = $prices[$product_id];
					$warranty_price = $data['price']/100;
					$warranty_plan_id = $data['planId'];

					$warranty_title = isset($data['Warranty'])?$data['Warranty']:'';
					$warranty_term = isset($data['Warranty Term'])? intval(str_replace(' Months', '', $data['Warranty Term'])):'';
					if(isset($data['term'])){
						$warranty_term = $data['term'];
					}
					$product_name = $item->get_meta('Covered Product');

					$wpdb->insert($wpdb->prefix . $this->plugin->table_name, compact('date_created', 'warranty_title', 'warranty_term', 'order_id','date_scheduled','order_number', 'product_id', 'product_price', 'product_name', 'warranty_price', 'warranty_plan_id'));

				}

			}

		}
	}

	private function get_product_shipping_estimate($product){

		 $default_max = 21;
		if($product->get_parent_id()>0){
			//variation
			if(get_post_meta($product->get_id(), 'ship_time_min', true)){
				$max = get_post_meta($product->get_id(), 'ship_time_max', true);


				// use postmeta
			}elseif( get_post_meta($product->get_parent_id(), 'ship_time_min', true)){
				$max = get_post_meta($product->get_parent_id(), 'ship_time_max', true);
					//use parent post meta
				}else{
				// use brand details from parent
				$brands =   wp_get_post_terms($product->get_parent_id(), 'pa_product-brand');

				if($brands && !is_wp_error($brands)){
					$max = get_term_meta($brands[0]->term_id, 'ship_time_max', true);
				}
			}


		}else{
			//product
			if(get_post_meta($product->get_id(), 'ship_time_min', true)) {
				// use postmeta
				$max = get_post_meta($product->get_id(), 'ship_time_max', true);
			}else{
				// use brand details
				$brands =   wp_get_post_terms($product->get_id(), 'pa_product-brand');

				if($brands && !is_wp_error($brands)){
					$max = get_term_meta($brands[0]->term_id, 'ship_time_max', true);
				}
			}
		}


		if(!$max){
			return $default_max;
		}

		return $max;

	}

	/**
	 * @param $order_id
	 * @param $order
	 * @param $covered_id
	 * @param $prices
	 * @param $projected_ship_date
	 * @param $data
	 *
	 * @return array
	 */
	private function get_contract_data(  $order, $covered_id, $product_price, $plan_price, $plan_id, $transaction_date_epoch) {

		$contract_data = [
			'transactionId'    => $order->get_order_number(),
			'poNumber'         => $order->get_order_number(),
			'transactionTotal' => [
				'currencyCode' => 'USD',
				'amount'       => intval($order->get_total() * 100)
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
			'product'          => [
				'referenceId'   => $covered_id,
				'purchasePrice' => [
					'currencyCode' => 'USD',
					'amount'       => intval($product_price * 100)
				]
			],
			'currency'         => 'USD',
			'source'           => [
				'agentId'      => '',
				'channel'      => 'web',
				'integratorId' => 'netsuite',
				'locationId'   => $this->plugin->store_id,
				'platform'     => 'woocommerce'
			],
			'transactionDate'  => $transaction_date_epoch,
			'plan'             => [
				'purchasePrice' => [
					'currencyCode' => 'USD',
					'amount'       => intval($plan_price * 100)
				],
				'planId'        => $plan_id
			]

		];

		return $contract_data;
	}


}
