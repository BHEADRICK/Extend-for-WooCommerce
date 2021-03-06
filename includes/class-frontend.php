<?php
/**
 * Extend for WooCommerce Frontend.
 *
 * @since   0.0.0
 * @package Extend_For_WooCommerce
 */

/**
 * Endpoint class.
 *
 * @since   0.0.0
 * @package Extend_For_WooCommerce
 */
if ( class_exists( 'WP_REST_Controller' ) ) {
	class EFWC_Frontend extends WP_REST_Controller {
		/**
		 * Parent plugin class.
		 *
		 * @var   Extend_For_WooCommerce
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
			$this->hooks();
		}

		/**
		 * Add our hooks.
		 *
		 * @since  0.0.0
		 */
		public function hooks() {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}

		/**
	     * Register the routes for the objects of the controller.
	     *
	     * @since  0.0.0
	     */
		public function register_routes() {

			// Set up defaults.
			$version = '1';
			$namespace = 'extend-for-woocommerce/v' . $version;
			$base = 'contracts';


			// Example register_rest_route calls.
			register_rest_route( $namespace, '/' . $base, array(
				array(
					'methods' => WP_REST_Server::READABLE,
					'callback' => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permission_check' ),
					'args' => array(),
				),
			) );

			register_rest_route( $namespace, '/' . $base . '/(?P<id>[\d]+)', array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'context' => array(
							'default' => 'view',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( false ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => array(
						'force' => array(
							'default' => false,
							),
						),
					),
				)
			);


		}

		/**
		 * Get items.
		 *
		 * @since  0.0.0
		 *
		 * @param  WP_REST_Request $request Full details about the request.
		 */
		public function get_items( $request ) {

			$limit = $request->get_param('limit');
			$offset = $request->get_param('offset');
			$order_num = $request->get_param('order_num');
			$status = $request->get_param('status');
			$where = "";

			if(!empty($order_num)){
				$where .= " and order_number like '%$order_num%'";
			}

			if(!empty($status)){
				switch ($status){
					case 'sent':
							$where.= " and length(contract_number) = 36";
						break;

					case 'scheduled':
							$where .= " and (contract_number is null or contract_number = '')";
						break;

					case 'cancelled':
						$where .= " and length(contract_number) between 1 and 35";
						break;
				}
			}
			global $wpdb;
			$sql = "select id, date_created, date_scheduled, contract_number, order_id, order_number, product_name, product_id, warranty_price, warranty_term from $wpdb->prefix{$this->plugin->table_name} where 1 $where order by date_created desc limit  $limit offset $offset ";

			$items = $wpdb->get_results($sql);

			$filtered_count = $wpdb->get_var("select count(id) from $wpdb->prefix{$this->plugin->table_name} where 1 $where");

			$totals = $wpdb->get_row("select count(id) count, sum(warranty_price) revenue from $wpdb->prefix{$this->plugin->table_name}");

			$month = $wpdb->get_row( "select count(id) count, sum(warranty_price) revenue from $wpdb->prefix{$this->plugin->table_name} where month(`date_created`) = month(now())");

			$products = $wpdb->get_results("
			select product_name, product_id, count(id) count from $wpdb->prefix{$this->plugin->table_name} 
group by product_name, product_id
order by count(id) desc
limit 10");

			return compact('items', 'totals', 'month', 'products', 'filtered_count');
		}

		/**
		 * Permission check for getting items.
		 *
		 * @since  0.0.0
		 *
		 * @param  WP_REST_Request $request Full details about the request.
		 */
		public function get_items_permission_check( $request ) {
			return true;
//			return current_user_can('manage_woocommerce');
		}

		/**
		 * Get item.
		 *
		 * @since  0.0.0
		 *
		 * @param  WP_REST_Request $request Full details about the request.
		 */
		public function get_item( $request ) {}

		/**
		 * Permission check for getting item.
		 *
		 * @since  0.0.0
		 *
		 * @param  WP_REST_Request $request Full details about the request.
		 */
		public function get_item_permissions_check( $request ) {}

		/**
		 * Update item.
		 *
		 * @since  0.0.0
		 *
		 * @param  WP_REST_Request $request Full details about the request.
		 */
		public function update_item( $request ) {}

		/**
		 * Permission check for updating items.
		 *
		 * @since  0.0.0
		 *
		 * @param  WP_REST_Request $request Full details about the request.
		 */
		public function update_item_permissions_check( $request ) {}

		/**
		 * Delete item.
		 *
		 * @since  0.0.0
		 *
		 * @param  WP_REST_Request $request Full details about the request.
		 */
		public function delete_item( $request ) {}

		/**
		 * Permission check for deleting items.
		 *
		 * @since  0.0.0
		 *
		 * @param  WP_REST_Request $request Full details about the request.
		 */
		public function delete_item_permissions_check( $request ) {}

		/**
		 * Get item schema.
		 *
		 * @since  0.0.0
		 */
		public function get_public_item_schema() {}
	}
}
