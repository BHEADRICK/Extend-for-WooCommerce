# Extend for WooCommerce #
**Contributors:**      Bryan Headrick  
**Donate link:**       https://bryanheadrick.com  
**Tags:**  
**Requires at least:** 4.4  
**Tested up to:**      4.8.1 
**Stable tag:**        0.0.0  
**License:**           GPLv2  
**License URI:**       http://www.gnu.org/licenses/gpl-2.0.html  

## Description ##

Extend Integration for WooCommerce

### Manual Installation ###

1. Upload the entire `/extend-for-woocommerce` directory to the `/wp-content/plugins/` directory.
2. Activate Extend for WooCommerce through the 'Plugins' menu in WordPress.
3. Create a dummy product for the Extend Warranty - this product should be 
hidden from catalog
4. Go to WooCommerce > Settings > Products > Extend Warranties and complete the configuation
including adding the id from the dummy product.

## Notes ##
For this implementation, the on-product buttons to select the plan are hidden, 
so the plan options are only displayed after adding to cart.

If you wish to display these, you can fork this plugin and update line 109 on 
class-products.php

### Refunds ###
Warranty Contracts can be cancelled/refunded by cancelling the warranty line-item 
on the order edit screen 

## Frequently Asked Questions ##


## Screenshots ##

## Requirements ##

Must have an account with Extend (https://www.extend.com/)

## API Reference ##
Not all features are implemented, but this provides a solid foundation.
For information on all features, see (https://developers.extend.com/2021-04-01)

## Changelog ##

### 0.0.0 ###
* First release

## Upgrade Notice ##

### 0.0.0 ###
First Release
