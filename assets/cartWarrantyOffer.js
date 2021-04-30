let store_id = window.WCCartExtend.store_id;
let product_id = window.WCCartExtend.id;
let product_ids = window.WCCartExtend.ids;
let environment = window.WCCartExtend.environment;
let warranty_prod_id = window.WCCartExtend.warranty_prod_id;




jQuery(document).ready(function() {
    Extend.config({
        storeId: store_id,
        environment: environment,
        referenceIds: product_ids
    });


    jQuery('.cart-extend-offer').each(function(ix, val){
        let ref_id =  jQuery(val).data('covered');
        let qty = jQuery(val).parents('.cart_item').find('input.qty').val();

    /** initialize offer */
        Extend.buttons.renderSimpleOffer('#'+this.id, {
            referenceId: ref_id,
            onAddToCart:
                function({ plan, product }) {
                    if (plan && product) {

                        plan['covered_product_id'] = ref_id

                        var data = {
                            product_id: warranty_prod_id,
                            quantity: qty,
                            extendData: plan
                        };

                        console.log(plan, product);
                        jQuery.post( wc_add_to_cart_params.wc_ajax_url.toString().replace( '%%endpoint%%', 'add_to_cart' ), data, function( response ) {
                            if (!response) {
                                return;
                            }
                            jQuery("[name='update_cart']").removeAttr('disabled');
                            jQuery("[name='update_cart']").trigger("click");

                        });

                    }
                },
        });
    })

});