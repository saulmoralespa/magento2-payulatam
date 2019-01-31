define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'payulatam',
                component: 'Saulmoralespa_PayuLatam/js/view/payment/method-renderer/payulatam'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);