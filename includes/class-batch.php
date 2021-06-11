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
}
