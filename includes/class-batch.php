<?php
/**
 * Extend for WooCommerce Batch.
 *
 * @since   0.0.0
 * @package Extend_For_WooCommerce
 */

/**
 * Extend for WooCommerce Batch.
 *
 * @since 0.0.0
 */
class EFWC_Batch {
	/**
	 * Parent plugin class
	 *
	 * @var   Extend_For_WooCommerce
	 *
	 * @since 0.0.0
	 */
	protected $plugin = null;

	/**
	 * Constructor.
	 *
	 * @since  0.0.0
	 *
	 * @param  Extend_For_WooCommerce $plugin Main plugin object.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		// If we have WP CLI, add our commands.
		if ( $this->verify_wp_cli() ) {
			$this->add_commands();
		}
	}

	/**
	 * Check for WP CLI running.
	 *
	 * @since  0.0.0
	 *
	 * @return boolean True if WP CLI currently running.
	 */
	public function verify_wp_cli() {
		return ( defined( 'WP_CLI' ) && WP_CLI );
	}

	/**
	 * Add our commands.
	 *
	 * @since  0.0.0
	 */
	public function add_commands() {
		WP_CLI::add_command( 'extend_wc_products_update', array( $this, 'extend_for_woo_commerce_command' ) );
		WP_CLI::add_command( 'extend_wc_migrate', array( $this, 'extend_for_woo_commerce_migration' ) );
		WP_CLI::add_command( 'extend_wc_send_contract', array( $this, 'send_contract' ) );
	}


	public function send_contract($args, $assoc_args){
		$order_id = null;
		if(!empty($args) || !empty($assoc_args)){
			if(isset($args[0])){
				$order_id = $args[0];
			}

			if(isset($assoc_args['order_id'])){
				$order_id = $assoc_args['order_id'];
			}


		}

		if($order_id=== null){
				error_log('sending all scheduled contracts');
			$this->plugin->contracts->get_contracts();
		}else{
			global $wpdb;
			error_log('sending contract for order id: ' . $order_id);
			$contract = $wpdb->get_row($wpdb->prepare("select id, order_id, product_id, warranty_price, product_price, warranty_plan_id from $wpdb->prefix{$this->plugin->table_name} where contract_number = '' and  order_id = %d limit 1", $order_id));


			if($contract){
				$this->plugin->contracts->send_contract($contract->order_id, $contract->product_id, $contract->product_price, $contract->warranty_price, $contract->warranty_plan_id, $contract->id);

			}else{
				error_log('contract not found');
			}
		}






		
	}


	public function extend_for_woo_commerce_migration($args, $assoc_args){


		$order_id = null;
		if(!empty($args) || !empty($assoc_args)){
			if(isset($args[0])){
				$order_id = $args[0];
			}

			if(isset($assoc_args['order_id'])){
				$order_id = $assoc_args['order_id'];
			}


		}



		$this->migrate_scheduled_contracts($order_id);
		$this->migrate_created_contracts($order_id);

	}
	/**
	 * Create a method stub for our first CLI command.
	 *
	 * @since 0.0.0
	 */
	public function extend_for_woo_commerce_command() {

		$posts = get_posts([
			'post_type'=>['product', 'product_variation'],
			'date_query' => array(

				array(
					'column' => 'post_modified_gmt',
					'after'  => '4 days ago',
				),
			),
			'fields'=>'ids',
			'posts_per_page'=>-1

		]);


		foreach($posts as $post){
			wp_schedule_single_event(time(), get_class($this->plugin). '_update_product', [$post]);
		}


	}


	private function migrate_created_contracts($order_id = null){


		if($order_id!==null){
			$contracts = get_post_meta($order_id, '_extend_contracts', true);

			if($contracts){
				$this->migrate_contract($order_id, $contracts);
			}

			return;
		}

		global $wpdb;

		$scheduled_sql = "select post_id, meta_value from $wpdb->postmeta where meta_key = '_extend_contracts'";

		$orders = $wpdb->get_results( $scheduled_sql );


		foreach ( $orders as $order ) {

			$order_items  = maybe_unserialize( $order->meta_value );
			$this->migrate_contract( $order->post_id, $order_items );
		}


	}

	private function migrate_scheduled_contracts( $order_id = null ) {


		if($order_id!== null){

				$this->migrate_order($order_id, get_post_meta($order_id, '_has_deferred_contracts', true));
				return;
		}

		global $wpdb;

		$scheduled_sql = "select post_id, meta_value from $wpdb->postmeta where meta_key = '_has_deferred_contracts'";

		$orders = $wpdb->get_results( $scheduled_sql );


		foreach ( $orders as $order ) {

			$order_id = $order->post_id;
			$count    = $wpdb->get_var( "select count(id) from {$wpdb->prefix}{$this->plugin->table_name} where order_id = $order_id" );

			if ( $count > 0 ) {
				continue;
			}
			$order_items = maybe_unserialize( $order->meta_value );

			$this->migrate_order( $order_id, $order_items);
		}
		//end migrate scheduled orders
	}

	/**
	 * @param $wc_order
	 * @param $product_id
	 *
	 * @return float|int
	 */
	private function get_product_data( $wc_order, $product_id ) {
		$datas = [];
		foreach ( $wc_order->get_items() as $item ) {


			$data = $item->get_data();


			if ( intval($data['product_id']) === intval($product_id) || intval($data['variation_id']) === intval($product_id) ) {

				$qty           = $data['quantity'];
				$subtotal      = $data['subtotal'];
				return $subtotal / $qty;
				break;
			}
			$datas[]= $data;
		}

		return 0;
}

	/**
	 * @param $wc_order
	 * @param $order_item_id
	 * @param $warranty_price
	 * @param $warranty_title
	 * @param $warranty_term
	 * @param $warranty_plan_id
	 * @param $product_id
	 */
	private function get_warranty_data( $wc_order, $order_item_id, &$warranty_price, &$warranty_title, &$warranty_term, &$warranty_plan_id, &$product_id, &$product_name ) {
		$item             = $wc_order->get_item( $order_item_id );
		$extend_data      = $item->get_meta( '_extend_data', true );
		$warranty_price   = $extend_data['price'] / 100;
		$warranty_title   = $extend_data['title'];
		$warranty_term    = $extend_data['term'];
		$warranty_plan_id = $extend_data['planId'];
		$product_name = $item->get_meta('Covered Product', true);

		$product_id = $extend_data['covered_product_id'];
	}

	/**
	 * @param $wc_order
	 * @param $order_items
	 * @param $product_id
	 * @param $order_id
	 * @param $order_number
	 * @param $product_name
	 * @param $warranty_plan_id
	 * @param $warranty_price
	 * @param $warranty_term
	 * @param $warranty_title
	 * @param $wpdb
	 */
	private function migrate_order( $order_id , $order_items) {

		global $wpdb;


		$wc_order    = wc_get_order( $order_id );

		if(!$wc_order){
			error_log("Not an order: $order_id");
			return;
		}
		$date_created = $wc_order->get_date_paid()->date( 'Y-m-d H:i:s' );
		$order_number = $wc_order->get_order_number();
		if( $wc_order->get_date_paid()=== null){
			error_log("order# $order_id not paid");


			return;
		}

		if(!is_array($order_items) || empty($order_items)){

				$this->plugin->contracts->schedule_contracts($order_id, $wc_order);


		}

		if ( is_array( $order_items ) ) {


			foreach ( $order_items as $order_item_id => $datestamp ) {

				$date_scheduled = date( 'Y-m-d H:i:s', $datestamp );
				$this->get_warranty_data( $wc_order, $order_item_id, $warranty_price, $warranty_title, $warranty_term, $warranty_plan_id, $product_id, $product_name );


				$product_price = $this->get_product_data( $wc_order, $product_id );
				$data          = compact( 'date_created', 'date_scheduled', 'order_id', 'order_number', 'product_id', 'product_price', 'product_name', 'warranty_plan_id', 'warranty_price', 'warranty_term', 'warranty_title' );


				$wpdb->insert( $wpdb->prefix . $this->plugin->table_name, $data );
			}
		}
	}

	/**
	 * @param $order_id
	 * @param $order
	 * @param $product_id
	 * @param $product_name
	 * @param $warranty_plan_id
	 * @param $warranty_price
	 * @param $warranty_term
	 * @param $warranty_title
	 * @param $wpdb
	 */
	private function migrate_contract(  $order_id, $order_items) {

		global $wpdb;


		$wc_order     = wc_get_order( $order_id );

		$order_number = $wc_order->get_order_number();
		$date_created = $wc_order->get_date_paid()->date( 'Y-m-d H:i:s' );


		foreach ( $order_items as $order_item_id => $contract_numbers ) {

			foreach ( $contract_numbers as $contract_number ) {
				$this->get_warranty_data( $wc_order, $order_item_id, $warranty_price, $warranty_title, $warranty_term, $warranty_plan_id, $product_id, $product_name );


				$product_price = $this->get_product_data( $wc_order, $product_id );

				$data = compact( 'date_created', 'contract_number', 'order_id', 'order_number', 'product_id', 'product_price', 'product_name', 'warranty_plan_id', 'warranty_price', 'warranty_term', 'warranty_title' );

				$wpdb->insert( $wpdb->prefix . $this->plugin->table_name, $data );


			}


		}
	}
}
