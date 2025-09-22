/**
 * Affiliate Client Full - Pricing Integration
 * 
 * JavaScript for dynamic pricing updates, discount calculations,
 * and success tracking functionality.
 * 
 * @package AffiliateClientFull
 * @version 1.0.0
 */

(function($) {
    'use strict';

    // Pricing integration object
    window.AffiliateClientPricing = {

        // Configuration
        config: {
            ajaxUrl: '',
            restUrl: '',
            nonce: '',
            obfuscatedFields: {},
            currency: {},
            strings: {}
        },

        // State
        initialized: false,
        priceWidgets: {},
        discountCache: {},
        activeDiscountCode: null,

        /**
         * Initialse pricing functionality