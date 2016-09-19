(function($) {
    Drupal.behaviors.commerce_vipps = {
        attach: function (context, settings) {
            var commerce_vipps = jQuery.ajax({
                type: "GET",
                url: settings.basePath + "commerce_vipps/" + settings.commerce_vipps.transaction + "/transaction/" + settings.commerce_vipps.payment_redirect_key,
                async: false,
                success: function(data, textStatus, XMLHttpRequest){
                    location.reload();
                },
                error:function (xhr, ajaxOptions, thrownError){
                    setTimeout(function () {
                        Drupal.attachBehaviors(context, settings);
                    }, 5000);
                }
            });
        }
    }
})(jQuery);
