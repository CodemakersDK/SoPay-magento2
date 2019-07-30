define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Codemakers_SoPay/js/action/redirect-on-success'
    ],
    function (Component, sopayRedirect) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Codemakers_SoPay/payment/form',
                paymentReady: false
            },
            redirectAfterPlaceOrder: false,

            /**
             * @return {exports}
             */
            initObservable: function () {
                this._super()
                    .observe('paymentReady');

                return this;
            },

            /**
             * @return {*}
             */
            isPaymentReady: function () {
                return this.paymentReady();
            },

            getCode: function() {
                return 'sopay';
            },
            getData: function() {
                return {
                    'method': this.item.method,
                };
            },
            afterPlaceOrder: function() {
                sopayRedirect.execute();
            },
            getPaymentLogoSrc: function () {
                return window.checkoutConfig.payment.sopay.paymentLogoSrc;
            },
        });
    }
);