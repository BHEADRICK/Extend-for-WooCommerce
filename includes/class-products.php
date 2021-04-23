<?php
/**
 * Extend for WooCommerce Products.
 *
 * @since   0.0.0
 * @package Extend_For_WooCommerce
 */

/**
 * Extend for WooCommerce Products.
 *
 * @since 0.0.0
 */
class EFWC_Products {
	/**
	 * Parent plugin class.
	 *
	 * @since 0.0.0
	 *
	 * @var   Extend_For_WooCommerce
	 */
	protected $plugin = null;
	protected $settings_tab_id = 'warranties';
	protected $service_url = '';
	protected $mode = '';
	protected $api_key ='';

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
			$this->service_url .= '/stores/' . $store_id . '/products';
		}
		$this->api_key = get_option('wc_extend_api_key');
	}

	/**
	 * Initiate our hooks.
	 *
	 * @since  0.0.0
	 */
	public function hooks() {
		add_action('save_post_product', [$this, 'saveProduct'], 99, 3);

		add_action(get_class($this->plugin). '_add_product', [$this, 'addProduct']);
		add_action(get_class($this->plugin). '_update_product', [$this, 'updateProduct']);
		add_action( 'woocommerce_get_sections_products', array( $this, 'add_woocommerce_settings_tab' ), 50 );
		add_action( 'woocommerce_get_settings_products', array( $this, 'get_settings' ), 50, 2 );
	}

	public function add_woocommerce_settings_tab($settings_tabs){

		$settings_tabs[ $this->settings_tab_id ] = __( 'Extended Warranties', 'extend-for-woocommerce' );
		return $settings_tabs;
	}

	public function get_settings( $settings, $current_section ){

		if($current_section !== $this->settings_tab_id){
			return $settings;
		}

		$cat_terms = get_terms([
			'parent'=>0,
			'taxonomy'=>'product_cat'
		]);



		$top_level_cats = [];

		foreach($cat_terms as $term){
			$top_level_cats[$term->term_id] = $term->name;
		}
		
		return array(
			array(
				'name' => __( 'Storewide Warranty Configuration', 'extend-for-woocommerce' ),
				'type' => 'title',
				'desc' => __( 'These settings affect all products storewide. You can override these settings on a per product basis to make exceptions.', 'extend-for-woocommerce' ),
				'id'   => 'wc_extend_title',
			),
			array(
				'title'   => __( 'Sandbox Mode', 'wcpf' ),
				'type'    => 'checkbox',
				'id'      => 'wc_extend_sandbox',
				'default' => 'no',
			),

			array(
				'name' => __( 'Extend Store Id', 'extend-for-woocommerce' ),
				'type'        => 'text',
				'desc'        => __( '', 'extend-for-woocommerce' ),
				'default'     => '',
				'placeholder' => __( '', 'extend-for-woocommerce' ),
				'id'          => 'wc_extend_store_id',
				'desc_tip'    => true,
			),
			array(
				'name' => __( 'Extend API Key', 'extend-for-woocommerce' ),
				'type'        => 'text',
				'desc'        => __( '', 'extend-for-woocommerce' ),
				'default'     => '',
				'placeholder' => __( '', 'extend-for-woocommerce' ),
				'id'          => 'wc_extend_api_key',
				'desc_tip'    => true,
			),

			array(
				'name'     => __( 'Exclude Categories for Extended Warranty offers', 'extend-for-woocommerce' ),
				'type'     => 'multiselect',
				'class'    => 'wc-enhanced-select',
				'css'      => 'width: 450px;',
				'desc'     => __( 'Select categories that should not have warranties.', 'extend-for-woocommerce' ),
				'default'  => '',
				'id'       => 'wc_extend_disabled_categories',
				'desc_tip' => true,
				'options'  => $top_level_cats,
			),
			array( 'type' => 'sectionend', 'id' => 'deposits_defaults' ),
		);

	}

	public function saveProduct($post_id, $post, $update){
		if($update){
//			wp_schedule_single_event(time(),get_class($this->plugin). '_update_product', [$post_id] );
			$this->updateProduct($post_id);
		}else{
			$this->addProduct($post_id);
//			wp_schedule_single_event(time(),get_class($this->plugin). '_add_product', [$post_id] );
		}

	}



	public function addProduct($id){

		$data = $this->getProductData($id);

	}

	public function updateProduct($id){
		error_log('getting data');
		$data = $this->getProductData($id);

		error_log('posting to ' . $this->service_url);


		$res = $this->remote_request($this->service_url, 'POST', ['upsert'=>true], $data);

		error_log(print_r($res, true));




	}

	private function getProductData($id){



		$product = wc_get_product($id);


		$brand = $product->get_attribute('pa_product-brand');
		$image = get_the_post_thumbnail_url($id);

		$data = [
		'referenceId'=>$id,
		'brand'=>$brand,
		'category'=>$this->getCategory($id),
		'description'=>substr($this->getPlain($product->get_short_description()), 0, 2000),
		'enabled'=>$this->isEnabled($product),
			'price'=>['currencyCode'=>'USD', 'amount'=> $product->get_price()],
		'title'=>$product->get_title(),
			'imageUrl'=>$image,
			'identifiers'=>[
				'sku'=>$product->get_sku()

			]

		];

		$warranty =$this->getWarranty($id);
		if(!empty($warranty)){
			$data['mfrWarranty']=$warranty;
		}


		$upc = get_post_meta($id, '_cpf_upc', true);
		if($upc && strpos($upc, '000000')===false ){
			$data['identifiers']['upc'] = $upc;
		}

		if($product->get_parent_id()){
			$data['parentReferenceId'] = $product->get_parent_id();
		}


		
	}

	private function getPlain($html){
		return preg_replace( "/\n\s+/", "\n", rtrim(html_entity_decode(strip_tags($html))) );
	}

	/**
	 * @param $id
	 *
	 * @return string
	 */

	private function getCategory($id){
		
		$primary_cat = get_post_meta($id, '_yoast_wpseo_primary_product_cat', true);
		
		if($primary_cat){
			$term = get_term($primary_cat, 'product_cat');
			return $term->name;
		}else{
			$cats = wc_get_product_category_list($id);

			$cats = explode(',', $cats);

			$cats = array_map(function($cat){
				return strip_tags($cat);
			}, $cats);
			return implode(',', $cats);
		}

		
	}

	/**
	 * @param $product WC_Product
	 */
	private function isExcluded($product){
		$cats = $product->get_category_ids();
		$excluded_cat_ids = get_option('wc_extend_disabled_categories');
		if(!$excluded_cat_ids){
			return false;
		}
		foreach($cats as $cat){
			$terms =[$cat];
			$this->getParents($cat, $terms);

			if(!empty(array_intersect($terms, $excluded_cat_ids))){
				return true;
			}


		}
		return false;
	}

	private function getParents($term_id, &$parents = []){
		$parent_id = wp_get_term_taxonomy_parent_id($term_id, 'product_cat');

		if($parent_id>0){
			$parents[] = $parent_id;
			$this->getParents($parent_id, $parents);
		}

	}

	/**
	 * @param $product WC_Product
	 *
	 * @return bool
	 */
	private function isEnabled($product){
			$enabled = true;

		if($product->get_status() !=='publish'){
			return false;
		}

		if($this->isExcluded($product)){
			return false;
		}

		$catonly = get_post_meta($product->get_id(), '_catalog_only', true);
		if($catonly && $catonly!== null){
			$enabled = false;
		}






		return $enabled;
	}

	/**
	 * @param $id
	 *
	 * @return array
	 */
	private function getWarranty($id){

		return [];
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
	private function remote_request( $url, $method = 'GET', $url_args = array(), $body_fields = array() ) {

		$headers = array(
			'Accept', 'application/json; version=2021-04-01',
			'Content-Type' => 'application/json; charset=utf-8',

		);
		if($this->mode === 'sandbox' || $this->api_key){
			$headers['X-Extend-Access-Token (sandbox)']=$this->api_key;
		}else{
			$headers['X-Extend-Access-Token']=$this->api_key;
		}
		// Add url args (get parameters) to the main url
		if ( $url_args ) $url = add_query_arg( $url_args, $url );

		// Prepare arguments for wp_remote_request
		$args = array();

		if ( $method ) $args['method'] = $method;
		if ( $headers ) $args['headers'] = $headers;
		if ( $body_fields ) $args['body'] = json_encode( $body_fields );

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
}
