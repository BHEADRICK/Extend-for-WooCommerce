<?php
/**
 * Plugin Name: Extend for WooCommerce
 * Plugin URI:  https://catmanstudios.com
 * Description: A radical new plugin for WordPress!
 * Version:     0.0.0
 * Author:      Bryan Headrick
 * Author URI:  https://catmanstudios.com
 * Donate link: https://catmanstudios.com
 * License:     GPLv2
 * Text Domain: extend-for-woocommerce
 * Domain Path: /languages
 *
 * @link    https://catmanstudios.com
 *
 * @package Extend_For_WooCommerce
 * @version 0.0.0
 *
 * Built using generator-plugin-wp (https://github.com/WebDevStudios/generator-plugin-wp)
 */

/**
 * Copyright (c) 2021 Bryan Headrick (email : info@catmanstudios.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */


/**
 * Autoloads files with classes when needed.
 *
 * @since  0.0.0
 * @param  string $class_name Name of the class being requested.
 */
function extend_for_woocommerce_autoload_classes( $class_name ) {

	// If our class doesn't have our prefix, don't load it.
	if ( 0 !== strpos( $class_name, 'EFWC_' ) ) {
		return;
	}

	// Set up our filename.
	$filename = strtolower( str_replace( '_', '-', substr( $class_name, strlen( 'EFWC_' ) ) ) );

	// Include our file.
	Extend_For_WooCommerce::include_file( 'includes/class-' . $filename );
}
spl_autoload_register( 'extend_for_woocommerce_autoload_classes' );

/**
 * Main initiation class.
 *
 * @since  0.0.0
 */
final class Extend_For_WooCommerce {

	/**
	 * Current version.
	 *
	 * @var    string
	 * @since  0.0.0
	 */
	const VERSION = '0.0.0';

	/**
	 * URL of plugin directory.
	 *
	 * @var    string
	 * @since  0.0.0
	 */
	protected $url = '';

	/**
	 * Path of plugin directory.
	 *
	 * @var    string
	 * @since  0.0.0
	 */
	protected $path = '';

	/**
	 * Plugin basename.
	 *
	 * @var    string
	 * @since  0.0.0
	 */
	protected $basename = '';

	/**
	 * Detailed activation error messages.
	 *
	 * @var    array
	 * @since  0.0.0
	 */
	protected $activation_errors = array();

	/**
	 * Singleton instance of plugin.
	 *
	 * @var    Extend_For_WooCommerce
	 * @since  0.0.0
	 */
	protected static $single_instance = null;

	/**
	 * Instance of EFWC_Products
	 *
	 * @since0.0.0
	 * @var EFWC_Products
	 */
	protected $products;

	/**
	 * Instance of EWC_Frontend
	 *
	 * @since0.0.0
	 * @var EWC_Frontend
	 */
	protected $frontend;

	/**
	 * Instance of EFWC_Cart
	 *
	 * @since0.0.0
	 * @var EFWC_Cart
	 */
	protected $cart;

	protected $service_url = '';
	protected $mode = '';
	protected $api_key ='';
	protected $store_id;

	/**
	 * Creates or returns an instance of this class.
	 *
	 * @since   0.0.0
	 * @return  Extend_For_WooCommerce A single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$single_instance ) {
			self::$single_instance = new self();
		}

		return self::$single_instance;
	}

	/**
	 * Sets up our plugin.
	 *
	 * @since  0.0.0
	 */
	protected function __construct() {
		$this->basename = plugin_basename( __FILE__ );
		$this->url      = plugin_dir_url( __FILE__ );
		$this->path     = plugin_dir_path( __FILE__ );

		$mode = get_option('wc_extend_sandbox');
		if($mode==='yes'){
			$this->mode = 'sandbox';
			$this->service_url = 'https://api-demo.helloextend.com';
		}else{
			$this->mode = 'live';
			$this->service_url = 'https://api.helloextend.com';
		}
		$store_id = get_option('wc_extend_store_id');
		if($store_id){
			$this->service_url .= '/stores/' . $store_id ;
		}
		$this->api_key = get_option('wc_extend_api_key');

		$this->store_id = $store_id;
	}

	/**
	 * Attach other plugin classes to the base plugin class.
	 *
	 * @since  0.0.0
	 */
	public function plugin_classes() {

		$this->products = new EFWC_Products( $this );
		$this->frontend = new EFWC_Frontend( $this );
		$this->cart = new EFWC_Cart( $this );
	} // END OF PLUGIN CLASSES FUNCTION

	/**
	 * Add hooks and filters.
	 * Priority needs to be
	 * < 10 for CPT_Core,
	 * < 5 for Taxonomy_Core,
	 * and 0 for Widgets because widgets_init runs at init priority 1.
	 *
	 * @since  0.0.0
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'init' ), 0 );

	}
	/**
	 * @param $url
	 * @param string $method
	 * @param array $url_args
	 * @param array $body_fields
	 * @param array $headers
	 *
	 * @return array
	 */
	public function remote_request($path, $method = 'GET', $body_fields = array(),  $url_args = array() ) {

	    $url = $this->service_url . $path;
		$headers = array(
			'Accept'=> 'application/json; version=2021-04-01',
			'Content-Type' => 'application/json; charset=utf-8',

		);

		$headers['X-Extend-Access-Token']=$this->api_key;

		// Add url args (get parameters) to the main url
		if ( $url_args ) $url = add_query_arg( $url_args, $url );

		// Prepare arguments for wp_remote_request
		$args = array();

		if ( $method ) $args['method'] = $method;
		if ( $headers ) $args['headers'] = $headers;
		if ( $body_fields ) $args['body'] = json_encode( $body_fields );
		$args['timeout'] = 45;

//		error_log(print_r(compact('url', 'args'), true));

		// Make the request
		$response = wp_remote_request($url, $args);

		// Get the results
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_message = wp_remote_retrieve_response_message( $response );
		$response_body = wp_remote_retrieve_body( $response );

		// Decode the JSON in the body, if it is json
		if ( $response_body ) {
			$j = json_decode( $response_body );

			if ( $j ) $response_body = $j;
		}

		// Return this information in the same format for success or error. Includes debugging information.
		return array(
			'response_body' => $response_body,
			'response_code' => $response_code,
			'response_message' => $response_message,
			'response' => $response,
			'debug' => array(
				'file' => __FILE__,
				'line' => __LINE__,
				'function' => __FUNCTION__,
				'args' => array(
					'url' => $url,
					'method' => $method,
					'url_args' => $url_args,
					'body_fields' => $body_fields,
					'headers' => $headers,
				),
			)
		);

	}
	/**
	 * Activate the plugin.
	 *
	 * @since  0.0.0
	 */
	public function _activate() {
		// Bail early if requirements aren't met.
		if ( ! $this->check_requirements() ) {
			return;
		}

		global $wpdb;
		$exported = get_option('wc_extend_exported');
		if(!$exported){

//		    $this->plugin_classes();
//
//		    $disabled_cats = get_option('wc_extend_disabled_categories');
//		    if(!$disabled_cats){
//		        $disabled_cats = [];
//            }
//            $args = [
//	            'posts_per_page'=>-1,
//	            'fields'=>'ids',
//	            'post_type'=>['product'],
//	            'tax_query'=>[
//		            'relation' => 'AND',
//		            [
//			            'taxonomy'=>'product_type',
//			            'field'=>'slug',
//			            'terms'=>['bundle'],
//			            'operator'=>'NOT IN'
//		            ],
//		            [
//			            'taxonomy'=>'product_cat',
//			            'field'=>'term_id',
//			            'terms'=>$disabled_cats,
//			            'operator'=>'NOT IN'
//		            ]
//	            ]
//            ];
//		    $posts = get_posts($args);
//
//
//		    $this->products->exportCsv($posts);







        }

		// Make sure any rewrite functionality has been loaded.
		flush_rewrite_rules();
	}

	/**
	 * Deactivate the plugin.
	 * Uninstall routines should be in uninstall.php.
	 *
	 * @since  0.0.0
	 */
	public function _deactivate() {



	    wp_clear_scheduled_hook('wp_extend_export_product');
		$uploads = wp_upload_dir();

		$path = $uploads['path'];

		$filename = $path . '/' . 'extend_export.csv';
		unlink($filename);
		// Add deactivation cleanup functionality here.
	}

	/**
	 * Init hooks
	 *
	 * @since  0.0.0
	 */
	public function init() {

		// Bail early if requirements aren't met.
		if ( ! $this->check_requirements() ) {
			return;
		}

		// Load translated strings for plugin.
		load_plugin_textdomain( 'extend-for-woocommerce', false, dirname( $this->basename ) . '/languages/' );

		// Initialize plugin classes.
		$this->plugin_classes();
	}

	/**
	 * Check if the plugin meets requirements and
	 * disable it if they are not present.
	 *
	 * @since  0.0.0
	 *
	 * @return boolean True if requirements met, false if not.
	 */
	public function check_requirements() {

		// Bail early if plugin meets requirements.
		if ( $this->meets_requirements() ) {
			return true;
		}

		// Add a dashboard notice.
		add_action( 'all_admin_notices', array( $this, 'requirements_not_met_notice' ) );

		// Deactivate our plugin.
		add_action( 'admin_init', array( $this, 'deactivate_me' ) );

		// Didn't meet the requirements.
		return false;
	}

	/**
	 * Deactivates this plugin, hook this function on admin_init.
	 *
	 * @since  0.0.0
	 */
	public function deactivate_me() {

		// We do a check for deactivate_plugins before calling it, to protect
		// any developers from accidentally calling it too early and breaking things.
		if ( function_exists( 'deactivate_plugins' ) ) {
			deactivate_plugins( $this->basename );
		}
	}

	/**
	 * Check that all plugin requirements are met.
	 *
	 * @since  0.0.0
	 *
	 * @return boolean True if requirements are met.
	 */
	public function meets_requirements() {

		// Do checks for required classes / functions or similar.
		// Add detailed messages to $this->activation_errors array.
		return true;
	}

	/**
	 * Adds a notice to the dashboard if the plugin requirements are not met.
	 *
	 * @since  0.0.0
	 */
	public function requirements_not_met_notice() {

		// Compile default message.
		$default_message = sprintf( __( 'Extend for WooCommerce is missing requirements and has been <a href="%s">deactivated</a>. Please make sure all requirements are available.', 'extend-for-woocommerce' ), admin_url( 'plugins.php' ) );

		// Default details to null.
		$details = null;

		// Add details if any exist.
		if ( $this->activation_errors && is_array( $this->activation_errors ) ) {
			$details = '<small>' . implode( '</small><br /><small>', $this->activation_errors ) . '</small>';
		}

		// Output errors.
		?>
		<div id="message" class="error">
			<p><?php echo wp_kses_post( $default_message ); ?></p>
			<?php echo wp_kses_post( $details ); ?>
		</div>
		<?php
	}

	/**
	 * Magic getter for our object.
	 *
	 * @since  0.0.0
	 *
	 * @param  string $field Field to get.
	 * @throws Exception     Throws an exception if the field is invalid.
	 * @return mixed         Value of the field.
	 */
	public function __get( $field ) {
		switch ( $field ) {
			case 'version':
				return self::VERSION;
			case 'basename':
			case 'url':
			case 'path':
			case 'products':
			case 'frontend':
			case 'cart':
            case 'store_id':
            case 'mode':
				return $this->$field;
			default:
				throw new Exception( 'Invalid ' . __CLASS__ . ' property: ' . $field );
		}
	}

	/**
	 * Include a file from the includes directory.
	 *
	 * @since  0.0.0
	 *
	 * @param  string $filename Name of the file to be included.
	 * @return boolean          Result of include call.
	 */
	public static function include_file( $filename ) {
		$file = self::dir( $filename . '.php' );
		if ( file_exists( $file ) ) {
			return include_once( $file );
		}
		return false;
	}

	/**
	 * This plugin's directory.
	 *
	 * @since  0.0.0
	 *
	 * @param  string $path (optional) appended path.
	 * @return string       Directory and path.
	 */
	public static function dir( $path = '' ) {
		static $dir;
		$dir = $dir ? $dir : trailingslashit( dirname( __FILE__ ) );
		return $dir . $path;
	}

	/**
	 * This plugin's url.
	 *
	 * @since  0.0.0
	 *
	 * @param  string $path (optional) appended path.
	 * @return string       URL and path.
	 */
	public static function url( $path = '' ) {
		static $url;
		$url = $url ? $url : trailingslashit( plugin_dir_url( __FILE__ ) );
		return $url . $path;
	}
}

/**
 * Grab the Extend_For_WooCommerce object and return it.
 * Wrapper for Extend_For_WooCommerce::get_instance().
 *
 * @since  0.0.0
 * @return Extend_For_WooCommerce  Singleton instance of plugin class.
 */
function extend_for_woocommerce() {
	return Extend_For_WooCommerce::get_instance();
}

// Kick it off.
add_action( 'plugins_loaded', array( extend_for_woocommerce(), 'hooks' ) );

// Activation and deactivation.
register_activation_hook( __FILE__, array( extend_for_woocommerce(), '_activate' ) );
register_deactivation_hook( __FILE__, array( extend_for_woocommerce(), '_deactivate' ) );
