define([
    'Magento_Checkout/js/view/summary/abstract-total',
    'Magento_Checkout/js/model/totals'
], function (Component, totals) {
    'use strict';

    const CODE = 'packlink_surcharge';

    return Component.extend({
        defaults: {
            template: 'Packlink_PacklinkPro/summary/packlink_surcharge',
            title: 'Payment Surcharge'
        },

        getSegment: function () {
            return totals.getSegment && totals.getSegment(CODE);
        },

        isDisplayed: function () {
            var s = this.getSegment();
            return !!(s && s.value > 0);
        },

        getPureValue: function () {
            var s = this.getSegment();
            return s ? s.value : 0;
        },

        getValue: function () {
            return this.getFormattedPrice(this.getPureValue());
        },

        getTitle: function () {
            return this.title;
        }
    });
});
