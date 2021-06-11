let store_id = window.WCExtend.store_id;
let product_type = window.WCExtend.type;
let product_id = window.WCExtend.id;
let product_ids = window.WCExtend.ids;
let environment = window.WCExtend.environment;



jQuery(document).ready(function(){
    Extend.config({
        storeId: store_id ,
        environment: environment ,
        referenceIds: product_ids
    });

    if(product_type ==='simple'){
        Extend.buttons.render('#extend-offer', {
            referenceId: product_id,
        })
    }else{
        Extend.buttons.render('#extend-offer', {
            referenceId: product_id,
        });

        setTimeout(function(){
            let variation_id = jQuery('[name="variation_id"]').val();
            if(variation_id ) {
                let comp = Extend.buttons.instance('#extend-offer');
                    comp.setActiveProduct(variation_id)
            }
            }, 500);



            jQuery( ".single_variation_wrap" ).on( "show_variation", function ( event, variation )  {
                let component = Extend.buttons.instance('#extend-offer');
                product_id = variation.variation_id;
                component.setActiveProduct(variation.variation_id)
            } );

    }

    jQuery('form.cart').append('<input type="hidden" name="planData"  id="planData"/>');


    jQuery('button.single_add_to_cart_button:not([name="add_to_quote"])').on('click', function extendHandler(e) {
        e.preventDefault()


        // /** get the component instance rendered previously */
        const component = Extend.buttons.instance('#extend-offer');

        /** get the users plan selection */
        const plan = component.getPlanSelection();
        const product = component.getActiveProduct();

        if (plan) {

            jQuery('#planData').val(JSON.stringify(plan));
            jQuery(e.target).off('click', extendHandler);
            jQuery(e.target).trigger('click');

        } else{
            if(jQuery('#planData').val()===''){
                Extend.modal.open({
                    referenceId: product_id,
                    onClose: function(plan, product) {
                        if (plan && product) {
                            jQuery('#planData').val(JSON.stringify(plan));

                            jQuery(e.target).off('click', extendHandler);
                            jQuery(e.target).trigger('click');
                        } else {
                            jQuery(e.target).off('click', extendHandler);
                            jQuery(e.target).trigger('click');


                        }
                    },
                });
            }

        }


    });

});