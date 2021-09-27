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
	}


	public function extend_for_woo_commerce_migration(){

		$this->migrate_scheduled_contracts();

		$this->migrate_created_contracts();

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

//			$this->plugin->products->updateProduct($post);
			wp_schedule_single_event(time(), get_class($this->plugin). '_update_product', [$post]);
		}


	}


	private function migrate_created_contracts(){

		global $wpdb;

		$scheduled_sql = "select post_id, meta_value from $wpdb->postmeta where meta_key = '_extend_contracts'";

		$orders = $wpdb->get_results( $scheduled_sql );


		foreach ( $orders as $order ) {
			$order_id = $order->post_id;

			$wc_order    = wc_get_order( $order_id );
			$order_items = maybe_unserialize( $order->meta_value );
			$order_number = $wc_order->get_order_number();
			$date_created = $wc_order->get_date_paid()->date( 'Y-m-d H:i:s' );

			
			foreach ( $order_items as $order_item_id => $contract_numbers ) {

				foreach($contract_numbers as $contract_number){
					$this->get_warranty_data( $wc_order, $order_item_id, $warranty_price, $warranty_title, $warranty_term, $warranty_plan_id, $product_id );


					$product_price  = $this->get_product_data( $wc_order, $product_id );
					$data = compact( 'date_created', 'contract_number', 'order_id', 'order_number', 'product_id', 'product_price', 'product_name', 'warranty_plan_id', 'warranty_price', 'warranty_term', 'warranty_title' );

				$wpdb->insert( $wpdb->prefix . $this->plugin->table_name, $data );


				}




			}
		}


	}

	private function migrate_scheduled_contracts(  ) {
		global $wpdb;

		$scheduled_sql = "select post_id, meta_value from $wpdb->postmeta where meta_key = '_has_deferred_contracts'";

		$orders = $wpdb->get_results( $scheduled_sql );


		foreach ( $orders as $order ) {

			$order_id = $order->post_id;
			$count    = $wpdb->get_var( "select count(id) from {$wpdb->prefix}{$this->plugin->table_name} where order_id = $order_id" );

			if ( $count > 0 ) {
				continue;
			}

			$wc_order    = wc_get_order( $order_id );
			$order_items = maybe_unserialize( $order->meta_value );


			$order_number = $wc_order->get_order_number();
			$date_created = $wc_order->get_date_paid()->date( 'Y-m-d H:i:s' );
			if ( is_array( $order_items ) ) {


				foreach ( $order_items as $order_item_id => $datestamp ) {

					$date_scheduled = date( 'Y-m-d H:i:s', $datestamp );
					$this->get_warranty_data( $wc_order, $order_item_id, $warranty_price, $warranty_title, $warranty_term, $warranty_plan_id, $product_id );


					$product_price  = $this->get_product_data( $wc_order, $product_id );
					$data = compact( 'date_created', 'date_scheduled', 'order_id', 'order_number', 'product_id', 'product_price', 'product_name', 'warranty_plan_id', 'warranty_price', 'warranty_term', 'warranty_title' );


					$wpdb->insert( $wpdb->prefix . $this->plugin->table_name, $data );
				}
			}
		}
		//end migrate scheduled orders
	}

	/**
	 * @param $wc_order
	 * @param $product_id
	 *
	 * @return array
	 */
	private function get_product_data( $wc_order, $product_id ) {
		foreach ( $wc_order->get_items() as $item ) {


			$data = $item->get_data();


			if ( $data['product_id'] === $product_id || $data['variation_id'] === $product_id ) {

				$qty           = $data['quantity'];
				$subtotal      = $data['subtotal'];
				$product_price = $subtotal / $qty;
				break;
			}
		}

		return   $product_price ;
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
	private function get_warranty_data( $wc_order, $order_item_id, &$warranty_price, &$warranty_title, &$warranty_term, &$warranty_plan_id, &$product_id ) {
		$item             = $wc_order->get_item( $order_item_id );
		$extend_data      = $item->get_meta( '_extend_data', true );
		$warranty_price   = $extend_data['price'] / 100;
		$warranty_title   = $extend_data['title'];
		$warranty_term    = $extend_data['term'];
		$warranty_plan_id = $extend_data['planId'];

		$product_id = $extend_data['covered_product_id'];
	}
}
