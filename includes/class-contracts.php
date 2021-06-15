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
		add_action(strtolower( get_class($this->plugin)). '_send_contract', [$this, 'send_contract'], 10, 4);
	}
	/**
	 * @param $contract_data
	 * @param $contract_ids
	 * @param $item_id
	 * @param $item
	 */
	public function send_contract( $contract_data, $order_id,  $item_id, $item ) {

		$deferred_data = get_post_meta($order_id, '_has_deferred_contracts', true);

		if(!is_bool($deferred_data)){

			if(!isset($deferred_data[$item_id])){
				return;
			}
		}

		$contract_ids = [];
		$res = $this->plugin->remote_request( '/contracts', 'POST', $contract_data );


		if ( intval( $res['response_code'] ) === 201 ) {

			if ( ! isset( $contract_ids[ $item_id ] ) ) {
				$contract_ids[ $item_id ] = [];
			}
			$item->add_meta_data( "Extend Status", $res['response_body']->status );
			$contract_ids[ $item_id ][] = $res['response_body']->id;
		}else{
			error_log(print_r($res, true));
		}

		if(!empty($contract_ids)){

			$current_contracts = get_post_meta($order_id, '_extend_contracts', true);

			if($current_contracts ){
				$contract_ids =  $current_contracts + $contract_ids;
			}

			update_post_meta($order_id, '_extend_contracts', $contract_ids);

		}
	}

	public function maybe_send_contracts($order_id, $order){


		$sent = get_post_meta($order_id, '_extend_contracts', true);
		$deferred = get_post_meta($order_id, '_has_deferred_contracts', true);


		if(!$sent && !$deferred){

			$this->send_contracts($order_id, $order);
		}

	}




	/**
	 * @param $order_id integer
	 * @param $order WC_Order
	 */
	private function send_contracts( $order_id, $order) {

		$items     = $order->get_items();
		$contracts = [];
		$prices    = [];
		$covered   = [];
		$deferred_data = [];
//		$leads = [];

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

			foreach ( $contracts as $item ) {
				$item_id = $item->get_id();
				$data    = $item->get_meta( '_extend_data' );
				if ( $data ) {

					$covered_id = $data['covered_product_id'];
					$covered[]  = $covered_id;
					$delay = $this->get_product_shipping_estimate(wc_get_product($covered_id));
					$date = $order->get_date_paid();
					$projected_ship_date_obj =  date_add($date, date_interval_create_from_date_string("$delay days"));

					$projected_ship_date = $projected_ship_date_obj->getTimestamp();


					$contract_data = [
						'transactionId'    => $order_id,
						'poNumber'         => $order->get_order_number(),
						'transactionTotal' => [
							'currencyCode' => 'USD',
							'amount'       => $order->get_total() * 100
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
								'amount'       => $prices[ $covered_id ] * 100
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
						'transactionDate'  => $projected_ship_date,
						'plan'             => [
							'purchasePrice' => [
								'currencyCode' => 'USD',
								'amount'       => $data['price']
							],
							'planId'        => $data['planId']
						]

					];



					wp_schedule_single_event($projected_ship_date, strtolower( get_class($this->plugin)). '_send_contract', [$contract_data, $order_id, $item_id, $item]);

					$deferred_data[$item_id] = $projected_ship_date;

				}

			}

			update_post_meta($order_id, '_has_deferred_contracts', $deferred_data);
		}
	}

	private function get_product_shipping_estimate($product){

		 $max = 14;
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


		return $max;

	}




}
