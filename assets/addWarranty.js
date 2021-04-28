let store_id = window.WCExtend.store_id;
let product_id = window.WCExtend.id;
let warranty_product_id = 414513;

function WCExtend_add_plan_to_cart(plan, product, callback){

callback();
}



function WCExtend_add_to_cart(product_id, planData, callback){
    var qty = jQuery('form.cart [name="quantity"]').val();
    var data = {
        product_id: product_id,
        quantity: qty,
       planData: planData
    };

    jQuery.post( wc_add_to_cart_params.wc_ajax_url.toString().replace( '%%endpoint%%', 'add_to_cart' ), data, function( response ) {
        if ( ! response ) {
            return;
        }

        callback(response);
    });
}

jQuery(document).ready(function(){
    Extend.config({ storeId: store_id , environment: 'demo' });

    Extend.buttons.render('#extend-offer', {
        referenceId: product_id,
    })

    jQuery('[name="add-to-cart"]').on('click', function(e) {
        e.preventDefault()

        /** get the component instance rendered previously */
        const component = Extend.buttons.instance('#extend-offer')

        /** get the users plan selection */
        const plan = component.getPlanSelection()
        const product = component.getActiveProduct()

        //

        if (plan) {

            ///wp-json/extend-for-woocommerce/v1/frontend
            var self = this;
            WCExtend_add_to_cart(product.id,{plan}, function(){

                    window.location.href = window.location.href;

            });

            // jQuery.post('/wp-json/extend-for-woocommerce/v1/frontend/' + product.id, plan).done(function(r){
            //     // jQuery('form.cart').submit()
            // })
            /**
             * If you are using an ecommerce addon (e.g. Shopify) this is where you
             * would use the respective add-to-cart helper function.
             *
             * For custom integrations, use the plan data to determine which warranty
             * sku to add to the cart and at what price.
             */
            // add plan to cart, then handle form submission
        } else{
            Extend.modal.open({
                referenceId: window.WCExtend.id,
                onClose: function(plan, product) {
                    if (plan && product) {
                        WCExtend_add_to_cart(product.id,{plan}, function(){

                        });
                    } else {
                        WCExtend_add_to_cart(product.id,{}, function(){
                            window.location.href = window.location.href;
                        });

                    }
                },
            })
        }
        //

    })

})