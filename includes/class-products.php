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

	protected $csv_fields = null;

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

		$this->csv_fields = ['brand','price','title','referenceId', 'parentReferenceId','imageUrl','category','description','mfrWarrantyParts','mfrWarrantyLabor','mfrWarrantyUrl','sku','gtin','upc'];

	}

	/**
	 * Initiate our hooks.
	 *
	 * @since  0.0.0
	 */
	public function hooks() {
		add_action('save_post_product', [$this, 'saveProduct'], 99, 3);
		add_action('save_post_product_variation', [$this, 'saveProduct'], 99, 3);
		add_action('woocommerce_save_product_variation', [$this, 'updateProduct']);
		add_action('woocommerce_ajax_save_product_variations', [$this, 'save_variations']);

		add_action(get_class($this->plugin). '_add_product', [$this, 'addProduct']);
		add_action(get_class($this->plugin). '_update_product', [$this, 'updateProduct']);
		add_action( 'woocommerce_get_sections_products', array( $this, 'add_woocommerce_settings_tab' ), 50 );
		add_action( 'woocommerce_get_settings_products', array( $this, 'get_settings' ), 50, 2 );
		add_action('wp_extend_export_product', [$this, 'exportProduct'], 10, 2);
		add_action('wp_enqueue_scripts', [$this, 'scripts']);
		add_action('woocommerce_before_add_to_cart_form', [$this, 'product_offer']);


//		add_filter('woocommerce_add_cart_item', [$this, 'add_cart_item']);

	}

	public function save_variations($product_id){

		error_log('saving variations for ' . $product_id);
		$product = wc_get_product($product_id);
		$variations = $product->get_available_variations();

		foreach($variations as $variation){
			$variation_id = $variation['variation_id'];

			$this->updateProduct($variation_id);
		}

	}




	public function scripts(){
		wp_register_script('extend_script', 'https://sdk.helloextend.com/extend-sdk-client/v1/extend-sdk-client.min.js');
		wp_register_script('extend_warranty_script', $this->plugin->url . 'assets/addWarranty.js', ['jquery', 'extend_script'], filemtime($this->plugin->path .'assets/addWarranty.js' ));
	}

	public function product_offer(){
		global $product;

		if($this->isExcluded($product) ){
			error_log('not available');
			return;
		}
		$id = $product->get_id();

		$store_id = get_option('wc_extend_store_id');
		$type = $product->get_type();
		
		$environment = $this->plugin->mode === 'sandbox'?'demo':'live';

		if($type === 'variable'){
			$ids = $product->get_children();
		}else{
			$ids = [$id];
		}
		if($store_id){
			wp_enqueue_script('extend_script');
			wp_enqueue_script('extend_warranty_script');
			wp_localize_script('extend_warranty_script', 'WCExtend', compact('store_id', 'id', 'type', 'ids', 'environment'));
			echo "<div id=\"extend-offer\" style='display:none'></div>";


		}


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
				'name' => __( 'Coverage Product Id', 'extend-for-woocommerce' ),
				'type'        => 'text',
				'desc'        => __( 'This is the product id for the Extend Warranty product', 'extend-for-woocommerce' ),
				'default'     => '',
				'placeholder' => __( '', 'extend-for-woocommerce' ),
				'id'          => 'wc_extend_product_id',
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
			array( 'type' => 'sectionend', 'id' => 'wc_extend_defaults' ),
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


	public function exportProduct($post_id, $file_path){

		$product = wc_get_product($post_id);

		if(!$this->isEnabled($product)){
			return;
		}

		if($this->isExcluded($product)){
			return;
		}

		if($product->get_type()==='variable'){

			foreach($product->get_available_variations() as $variation){
				wp_schedule_single_event(time(), 'wp_extend_export_product', [$variation['variation_id'] , $file_path]);
			}
		}

		$data = $this->getProductData($product);

		if(!empty($data)){
			$this->saveProductCsv($data, $file_path);
		}else{
			error_log('data empty for ' . $post_id);
		}





	}

	public function get_variation_title($variation){
		$attributes = $variation->get_variation_attributes();
		$atts = [];
		foreach($attributes as $key=>$val){
			$key = ucwords( str_replace('-', ' ',str_replace('attribute_', '', $key)));
			$atts[] = $key .': ' . $val;
		}

		return $variation->get_title() . '(' . implode(', ', $atts) . ')';
	}

	public function addProduct($id){

		$data = $this->getProductData($id);
		$res = $this->plugin->remote_request('/products', 'POST', $data, ['upsert'=>false]);
		update_post_meta($id, '_extend_added', true);
	}

	public function updateProduct($id){
		error_log('updating ' . $id);
		$data = $this->getProductData($id);

		$exists = get_post_meta($id, '_extend_added', true);

		if($exists){
			$res = $this->plugin->remote_request('/products/'. $id, 'PUT',  $data);

			if($res['response_code']=== 404){
				$res = $this->plugin->remote_request('/products', 'POST', $data, ['upsert'=>true]);
			}
		}else{
			$res = $this->plugin->remote_request('/products', 'POST', $data, ['upsert'=>true]);

			if($res['response_code']=== 409 ){

				update_post_meta($id, '_extend_added', true);

				$res = $this->plugin->remote_request('/products/'. $id, 'PUT',  $data);
			}


		}


	}

	/**
	 * @param $product mixed
	 *
	 * @return array
	 */

	private function getProductData($product = null){


		if(is_numeric($product)){
			$id = $product;
			$product = wc_get_product($id);
		}else{
			$id = $product->get_id();
		}





		$image = get_the_post_thumbnail_url($id);
		$title = $product->get_title();
		if($product->get_parent_id()>0){
			$parent = wc_get_product($product->get_parent_id());
			$brand = $parent->get_attribute('pa_product-brand');
			$description = $parent->get_short_description();
			$description = $this->getPlain($description);
			if(empty($description)){
				$description = $parent->get_description();
			}
			if(empty($image)){
				$image = get_the_post_thumbnail_url($product->get_parent_id());
			}
			$category = $this->getCategory($product->get_parent_id());
			$title = $this->get_variation_title($product);
		}else{
			$brand = $product->get_attribute('pa_product-brand');
			$description = $product->get_short_description();
			$description = $this->getPlain($description);
			if(empty($description)){
				$description = $product->get_description();
				$description = $this->getPlain($description);
			}
			$category = $this->getCategory($id);
		}

		
		if(strpos($image, 'www.poolwarehouse.com')===false){
			$parsed = parse_url($image);
			$image = 'https://www.poolwarehouse.com' . $parsed['path'];
		}





		$data = [
		'referenceId'=>$id,
		'brand'=>$brand,
		'category'=>$category,
		'description'=>substr($description, 0, 2000),
		'enabled'=>$this->isEnabled($product),
			'price'=>['currencyCode'=>'USD', 'amount'=> $product->get_price()],
		'title'=>$title,
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


			$data['parentReferenceId'] = $product->get_parent_id();




		return $data;
		
	}

	private function flattenData($data){
		$data['mfrWarrantyParts'] = '';
		$data['mfrWarrantyLabor'] = '';
		$data['mfrWarrantyUrl'] = '';

		if(isset($data['price'])){


			$data['price'] = $data['price']['amount'];
		}

		foreach($data['identifiers'] as $key=>$val){
			$data[$key] = $val;
		}


		if(isset($data['mfrWarranty'])){
			if(empty($data['mfrWarranty'])){
				unset($data['mfrWarranty']);

			}else{
				$data['mfrWarrantyParts'] = isset($data['mfrWarranty']['parts'])?$data['mfrWarranty']['parts']:'';
				$data['mfrWarrantyLabor'] = isset($data['mfrWarranty']['labor'])?$data['mfrWarranty']['labor']:'';
				$data['mfrWarrantyUrl'] = isset($data['mfrWarranty']['url'])?$data['mfrWarranty']['url']:'';
				unset($data['mfrWarranty']);
			}
		}
		$data['gtin'] = isset($data['upc'])? $data['upc']:'';

		return $data;
	}

	/**
	 * @param array $product_ids
	 */
	public function exportCsv($product_ids = []){



		$uploads = wp_upload_dir();

		$path = $uploads['path'];

		$filename = $path . '/' . 'extend_export.csv';

		$fp = fopen( $filename, 'w+' );
		if($fp){
			fputcsv($fp, $this->csv_fields);
			fclose($fp);
		}else{
			error_log('unable to open  for writing: ' > $filename);
		}
		chmod($filename, 0755);



		foreach($product_ids as $product_id){
			wp_schedule_single_event(time(), 'wp_extend_export_product', [$product_id, $filename]);
		}
	}


	private function saveProductCsv($data, $file_path){

	$data = $this->flattenData($data);

	$csv_data = [];
	foreach($this->csv_fields as $field){

		$csv_data[] = (isset($data[$field]) && !is_array($data[$field]))?$data[$field]:'';
	}



	$fp = fopen($file_path, 'a');

	if($fp){
		fputcsv($fp, $csv_data);
		fclose($fp);
	}else{
		error_log('unable to open ' . $file_path);
	}




	//save flatteened data to csv file

	}

	private function getPlain($html){
		$text = preg_replace( "/\n\s+/", "\n", rtrim(html_entity_decode(strip_tags($html))) );

		$remove = [
			'Call Now With Questions: 800-515-1747',
			'Or Email: sales@poolwarehouse.com',
			'Select A Liner Pattern To Begin!'
		];

		$text = preg_replace("/\[[A-Za-z _0-9=\"\']\]/", "", $text);

		foreach($remove as $r){
			$text = str_replace($r, '', $text);
		}

		return $text;

	}

	/**
	 * @param $id
	 *
	 * @return string
	 */

	private function getCategory($id){
		
		$primary_cat = get_post_meta($id, '_yoast_wpseo_primary_product_cat', true);
		
		if($primary_cat && is_numeric($primary_cat)){
			$term = get_term($primary_cat, 'product_cat');
			if(is_object($term)){
				return $term->name;
			}

		}

			$cats = wc_get_product_category_list($id);

			$cats = explode(',', $cats);

			$cats = array_map(function($cat){
				return strip_tags($cat);
			}, $cats);
			return implode(',', $cats);


		
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

		$stock = $product->get_stock_status();
		if($stock !== 'instock'){
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


}
