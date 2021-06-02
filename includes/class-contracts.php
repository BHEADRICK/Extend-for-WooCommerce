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

	private function get_product_shipping_estimate($product){

		$min = 0; $max = 0;
		if($product->get_parent_id()>0){
			//variation
			if(get_post_meta($product->get_id(), 'ship_time_min', true)){
				$min = get_post_meta($product->get_id(), 'ship_time_min', true);
				$max = get_post_meta($product->get_id(), 'ship_time_max', true);


				// use postmeta
			}elseif( get_post_meta($product->get_parent_id(), 'ship_time_min', true)){
				$min = get_post_meta($product->get_parent_id(), 'ship_time_min', true);
				$max = get_post_meta($product->get_parent_id(), 'ship_time_max', true);
					//use parent post meta
				}else{
				// use brand details from parent
				$brands =   wp_get_post_terms($product->get_parent_id(), 'pa_product-brand');

				if($brands && !is_wp_error($brands)){
					$min = get_term_meta($brands[0]->term_id, 'ship_time_min', true);
					$max = get_term_meta($brands[0]->term_id, 'ship_time_max', true);
				}
			}


		}else{
			//product
			if(get_post_meta($product->get_id(), 'ship_time_min', true)) {
				// use postmeta
				$min = get_post_meta($product->get_id(), 'ship_time_min', true);
				$max = get_post_meta($product->get_id(), 'ship_time_max', true);
			}else{
				// use brand details
				$brands =   wp_get_post_terms($product->get_id(), 'pa_product-brand');

				if($brands && !is_wp_error($brands)){
					$min = get_term_meta($brands[0]->term_id, 'ship_time_min', true);
					$max = get_term_meta($brands[0]->term_id, 'ship_time_max', true);
				}
			}
		}

		return ceil(($min+$max)/2);

	}

	private  function add_business_days($datetime, $duedays)
	{
		$i = 1;
		while ($i <= $duedays)
		{
			$next_day = date('N', strtotime('+1 day', $datetime));
			if ($next_day == 6 || $next_day == 7)
			{
				$datetime = strtotime('+1 day', $datetime);
				continue;
			}
			$datetime = strtotime('+1 day', $datetime);
			$i++;
		}

		return $datetime;
	}

	public function find_contracts(){
		global $wpdb;



		$order_ids = $wpdb->get_col("select post_id from $wpdb->postmeta where meta_key = '_has_deferred_contracts'");

		$dcontracts = [];


		foreach($order_ids as $order_id){
			$order = wc_get_order($order_id);
			$contracts = get_post_meta($order_id, '_extend_contracts', true);

			$date = $order->get_date_paid();

			foreach($contracts as $item_id=>$contract_ids){
				$item = $order->get_item($item_id);


				$meta = $item->get_meta('_extend_data', true);

				if($meta && isset($meta['covered_product_id'])){

					$product = wc_get_product($meta['covered_product_id']);
					$date_add = $this->get_product_shipping_estimate($product);

					$new_date = $this->add_business_days($date->getTimestamp(), $date_add);

					$new_date_string = date('Y-M-d', $new_date);

					foreach($contract_ids as $contract_id){


						if(!empty($new_date_string)){
							$dcontracts[$contract_id] = $new_date_string;
						}
					}
				}
			}
			delete_post_meta($order_id, '_has_deferred_contracts');
		}
		$timestamp = time();
		$filename = "contracts-$timestamp.csv" ;

		$upload_dir = wp_upload_dir();

		$filepath = $upload_dir['path'] . '/' . $filename;


		$fp = fopen($filepath, 'w');

		fputcsv($fp, ['contract', 'date']);

		foreach($dcontracts as $contract=>$date){

			fputcsv($fp, [$contract, $date]);
		}
		fclose($fp);

		$attachments = [$filepath];
		$headers = 'From: Extend Deferred Contracts <noreply@poolwarehouse.com>' . "\r\n";
		wp_mail('support@extend.com', "Deferred Contracts", "see attached", $headers, $attachments);
		unlink($filepath);
	}

	/**
	 * Initiate our hooks.
	 *
	 * @since  0.0.0
	 */
	public function hooks() {

		add_action(strtolower(get_class($this->plugin)). '_get_orders', [$this, 'find_contracts']);

	}
}
