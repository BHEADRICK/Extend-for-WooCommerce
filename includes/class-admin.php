<?php
/**
 * Extend for WooCommerce Admin.
 *
 * @since   0.0.0
 * @package Extend_For_WooCommerce
 */

/**
 * Extend for WooCommerce Admin.
 *
 * @since 0.0.0
 */
class EFWC_Admin {
	/**
	 * Parent plugin class.
	 *
	 * @since 0.0.0
	 *
	 * @var   Extend_For_WooCommerce
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
	 * Initiate our hooks.
	 *
	 * @since  0.0.0
	 */
	public function hooks() {

		add_action('admin_menu', [$this, 'admin_menu']);
        add_action("admin_enqueue_scripts", [$this, 'admin_scripts']);

	}

	public function admin_scripts($hook){
	    if($hook=== 'product_page_extend'){

	        wp_enqueue_script('extend_admin_script', $this->plugin->url . 'dist/app.js', [], filemtime($this->plugin->path . 'dist/app.js'), true);
	        wp_localize_script('extend_admin_script', 'extend_wc', [
		        'root'          => esc_url_raw( get_rest_url() ),
		        'versionString' => 'extend-for-woocommerce/v1/contracts/',
		        'nonce'=>wp_create_nonce( 'wp_rest' ),

            ]);


        }
    }

	public function admin_menu(){
		add_submenu_page('edit.php?post_type=product',
			__( 'Extend Warranties', 'my-textdomain' ),
			__( 'Extend Warranties', 'my-textdomain' ),
			'manage_options',
			'extend',
			[$this, 'admin_page'],
			'dashicons-schedule',
			3
		);
	}

	public function admin_page(){
		?>
<h1>Extend Warranties</h1>
        <div id="app"></div>
<?php
	}
}
