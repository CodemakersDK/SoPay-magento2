define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (Component,
              rendererList) {
        'use strict';
        rendererList.push(
            {
                type: 'sopay',
                component: 'Codemakers_SoPay/js/view/payment/method-renderer/sopay-method'
            }
        );
        return Component.extend({});
    }
);