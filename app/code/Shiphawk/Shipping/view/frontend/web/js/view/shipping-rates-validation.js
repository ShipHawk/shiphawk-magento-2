/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'uiComponent',
    'Magento_Checkout/js/model/shipping-rates-validator',
    'Magento_Checkout/js/model/shipping-rates-validation-rules',
    'Shiphawk_Shipping/js/model/shipping-rates-validator',
    'Shiphawk_Shipping/js/model/shipping-rates-validation-rules'
], function (
    Component,
    defaultShippingRatesValidator,
    defaultShippingRatesValidationRules,
    shiphawkShippingRatesValidator,
    shiphawkShippingRatesValidationRules
) {
    'use strict';

    defaultShippingRatesValidator.registerValidator('shiphawk', shiphawkShippingRatesValidator);
    defaultShippingRatesValidationRules.registerRules('shiphawk', shiphawkShippingRatesValidationRules);

    return Component;
});
