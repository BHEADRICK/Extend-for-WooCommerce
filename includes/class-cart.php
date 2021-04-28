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
		add_action('woocommerce_checkout_order_processed', [$this, 'process_checkout'], 10, 3);
		add_action('woocommerce_checkout_create_order_line_item', [$this, 'order_item_meta'], 10, 3);
	}

	public function order_item_meta($item, $cart_item_key, $cart_item ){
		if(isset($cart_item['extendData'])){
			$item->add_meta_data('_extend_data', $cart_item['extendData']);


			$covered_id = $cart_item['extendData']['covered_product_id'];
			$term = $cart_item['extendData']['term'];
			$covered = wc_get_product($covered_id);
			$sku = $cart_item['extendData']['planId'];
			$covered_title = $covered->get_title();

			$item->add_meta_data('Warranty Term', $term . ' Months');
			$item->add_meta_data('Contract Id', $sku);
			$item->add_meta_data('Covered Product', $covered_title);

		}

	}

	/**
	 * @param $order_id integer
	 * @param $posted_data array
	 * @param $order WC_ORder
	 */
	public function process_checkout($order_id, $posted_data, $order){

		$items = $order->get_items();
		$contracts = [];
		$prices = [];
		foreach($items as $item){
			if($item->get_product_id() === $this->warranty_product_id){
				$contracts[] = $item;
			}else{
				$prices[$item->get_product_id()] = $item->get_subtotal()/$item->get_quantity();
			}
		}
		if(!empty($contracts)){
			foreach($contracts as $item){
				$data = $item->get_meta('_extend_data');
				if($data){

					$covered_id = $data['covered_product_id'];

					$contract_data = [
						'transactionId'=>$order_id,
						'poNumber'=>$order->get_order_number(),
						'transactionTotal'=>[
							'currencyCode'=>'USD',
							'amount'=>$order->get_total()
						],
						'customer'=>[
							'name'=>$order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
							'email'=>$order->get_billing_email(),
							'phone'=>$order->get_billing_phone(),
							'billingAddress'=>[
								'address1'=>$order->get_billing_address_1(),
								'address2'=>$order->get_billing_address_2(),
								'city'=>$order->get_billing_city(),
								'countryCode'=>$order->get_billing_country(),
								'postalCode'=>$order->get_billing_postcode(),
								'provinceCode'=>$order->get_billing_state()
							],
							'shippingAddress'=>[
								'address1'=>$order->get_shipping_address_1(),
								'address2'=>$order->get_shipping_address_2(),
								'city'=>$order->get_shipping_city(),
								'countryCode'=>$order->get_shipping_country(),
								'postalCode'=>$order->get_shipping_postcode(),
								'provinceCode'=>$order->get_shipping_state()
							],
							'product'=>[
								'referenceId'=>$covered_id,
								'purchasePrice'=>[
									'currencyCode'=>'USD',
									'amount'=>$prices[$covered_id]*100
								]
							],
							'currency'=>'USD',
							'source'=>[
								'agentId'=>'',
								'channel'=>'web',
								'integratorId'=>'netsuite',
								'locationId'=>'',
								'platform'=>'woocommerce'
								],
							'transactionDate'=>strtotime($order->get_date_paid()),
							'plan'=>[
								'purchasePrice'=>[
									'currencyCode'=>'USD',
									'amount'=>$data['price']
								],
								'planId'=>$data['planId']
							]
						]
					];

					error_log(print_r($contract_data, true));

//				$this->plugin->remote_request($this-)

				}


			}
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
			$data[] = [
				'key'=>'Contract id',
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
					$value['data']->set_price( $value['extendData']['price']/100 );
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

		if(isset($cart_item_data['extendData'])){


			$price = $cart_item_data['extendData']['price']/100;

			WC()->cart->cart_contents[$cart_item_key]['data']->set_price($price);

		}

	}

	public function ajax_added_to_cart($product_id){

		if(isset($_POST['planData'])){
			$plan = $_POST['planData']['plan'];
			$plan['covered_product_id'] = $product_id;

			try{
				WC()->cart->add_to_cart($this->warranty_product_id, 1, 0, 0, ['extendData'=>$plan] );
			}catch(Exception $e){
				error_log($e->getMessage());
			}
		}
	}
}
