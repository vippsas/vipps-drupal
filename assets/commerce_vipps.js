(function($) {
    Drupal.behaviors.commerce_vipps = {
        attach: function (context, settings) {
            var wrapper = jQuery('.vipps-page-wrapper');
            var order_id = wrapper.attr('data-order-id');
            var payment_id = wrapper.attr('data-payment-id');
            var payment_instance_id = wrapper.attr('data-gateway');
            var commerce_vipps = jQuery.ajax({
                type: "POST",
                data: {
                    'commerce_order': order_id,
                    'payment_instance_id': payment_instance_id,
                    'commerce_payment_id': payment_id
                },
                url: "/payment/notify/" + payment_instance_id,
                async: true,
                statusCode: {
                    404: function(xhr, ajaxOptions, thrownError) {
                        window.location.replace("/checkout/" + order_id + "/payment/return")
                    },
                    500: function(xhr, ajaxOptions, thrownError) {
                        window.location.replace("/checkout/" + order_id + "/payment/return")
                    },
                    402: function(xhr, ajaxOptions, thrownError) {
                        setTimeout(function () {
                            Drupal.attachBehaviors(context, settings);
                        }, 2000);
                    },
                    200:function(data, textStatus, XMLHttpRequest) {
                        window.location.replace("/checkout/" + order_id + "/payment/return")
                    }
                }
            });
        }
    }
})(jQuery);
