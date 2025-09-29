/**
 * Pricing Integration JavaScript
 *
 * Plugin: Affiliate Client Integration (Satellite)
 * File: /wp-content/plugins/affiliate-client-integration/assets/js/pricing-integration.js
 * Author: Richard King, starneconsulting.com
 *
 * Handles dynamic pricing updates, discount calculations, and success tracking
 * with comprehensive error handling and real-time price modifications.
 */

(function ($) {
  "use strict";

  // Pricing integration object
  window.ACI_Pricing = {
    // Configuration
    config: {
      ajaxUrl: "",
      restUrl: "",
      nonce: "",
      obfuscatedFields: {},
      currency: {},
      strings: {},
    },

    // State management
    initialized: false,
    priceWidgets: {},
    discountCache: {},
    activeDiscountCode: null,
    activeDiscountData: null,
    updateQueue: [],
    processing: false,

    /**
     * Initialize pricing functionality
     */
    init: function () {
      if (this.initialized) {
        return;
      }

      // Load configuration
      if (typeof affiliateClientPricing !== "undefined") {
        this.config = $.extend(this.config, affiliateClientPricing);
      }

      // Initialize components
      this.initializePriceWidgets();
      this.initializeDiscountDisplays();
      this.initializeSuccessTrackers();
      this.bindEvents();
      this.checkForActiveDiscount();

      this.initialized = true;
      console.log("ACI Pricing Integration initialized");
    },

    /**
     * Initialize all price widgets on the page
     */
    initializePriceWidgets: function () {
      const self = this;

      $(".aci-price-widget").each(function () {
        const $widget = $(this);
        const widgetId = $widget.attr("id");

        if (!widgetId || self.priceWidgets[widgetId]) {
          return;
        }

        const widgetData = {
          id: widgetId,
          element: $widget,
          basePrice: parseFloat($widget.data("base-price")) || 0,
          currency: $widget.data("currency") || "EUR",
          serviceId: $widget.data("service-id") || "",
          displayOriginal: $widget.data("display-original") !== false,
          showSavings: $widget.data("show-savings") !== false,
          format: $widget.data("format") || "standard",
          animate: $widget.data("animate") !== false,
          currentPrice: null,
          discountApplied: false,
        };

        self.priceWidgets[widgetId] = widgetData;
        self.updatePriceWidget(widgetId);
      });
    },

    /**
     * Initialize discount display elements
     */
    initializeDiscountDisplays: function () {
      const self = this;

      $(".aci-discount-display").each(function () {
        const $display = $(this);
        self.updateDiscountDisplay($display);
      });
    },

    /**
     * Initialize success trackers
     */
    initializeSuccessTrackers: function () {
      const self = this;

      $(".aci-success-tracker").each(function () {
        const $tracker = $(this);
        const autoTrack = $tracker.data("auto-track") !== false;

        if (autoTrack) {
          self.checkSuccessConditions($tracker);
        }
      });
    },

    /**
     * Bind event handlers
     */
    bindEvents: function () {
      const self = this;

      // Listen for discount code application
      $(document).on("aci:discount:applied", function (e, data) {
        self.handleDiscountApplied(data);
      });

      // Listen for discount code removal
      $(document).on("aci:discount:removed", function () {
        self.handleDiscountRemoved();
      });

      // Manual price recalculation trigger
      $(document).on("click", ".aci-recalculate-price", function (e) {
        e.preventDefault();
        const widgetId = $(this).data("widget-id");
        if (widgetId) {
          self.updatePriceWidget(widgetId, true);
        }
      });

      // Success tracking manual trigger
      $(document).on("click", ".aci-track-success", function (e) {
        e.preventDefault();
        const trackerId = $(this).data("tracker-id");
        self.trackSuccess(trackerId);
      });

      // Copy discount code
      $(document).on("click", ".aci-copy-discount-code", function (e) {
        e.preventDefault();
        const code = $(this).data("code");
        self.copyDiscountCode(code, $(this));
      });
    },

    /**
     * Check for active discount on page load
     */
    checkForActiveDiscount: function () {
      const self = this;

      // Check session storage
      try {
        const sessionData = sessionStorage.getItem("aci_active_discount");
        if (sessionData) {
          const discountData = JSON.parse(sessionData);
          this.activeDiscountCode = discountData.code;
          this.activeDiscountData = discountData;
          this.applyDiscountToAllWidgets();
        }
      } catch (e) {
        console.warn("Unable to read discount from session storage:", e);
      }

      // Check for discount in URL or cookies
      const urlDiscount = this.getDiscountFromURL();
      if (urlDiscount && !this.activeDiscountCode) {
        this.validateAndApplyDiscount(urlDiscount);
      }
    },

    /**
     * Get discount code from URL parameters
     */
    getDiscountFromURL: function () {
      const urlParams = new URLSearchParams(window.location.search);
      return urlParams.get("discount") || urlParams.get("code") || null;
    },

    /**
     * Update a specific price widget
     */
    updatePriceWidget: function (widgetId, forceRefresh) {
      const widgetData = this.priceWidgets[widgetId];
      if (!widgetData) {
        return;
      }

      const $widget = widgetData.element;
      const basePrice = widgetData.basePrice;

      // Show loading state
      $widget.addClass("aci-loading");

      // Check if we have active discount
      if (this.activeDiscountData && !forceRefresh) {
        this.applyDiscountToWidget(widgetId, this.activeDiscountData);
      } else if (this.activeDiscountCode) {
        // Fetch discount data from server
        this.fetchDiscountData(
          this.activeDiscountCode,
          basePrice,
          widgetData.currency
        )
          .done((response) => {
            if (response.success && response.data) {
              this.applyDiscountToWidget(widgetId, response.data);
            } else {
              this.displayOriginalPrice(widgetId);
            }
          })
          .fail(() => {
            this.displayOriginalPrice(widgetId);
          })
          .always(() => {
            $widget.removeClass("aci-loading");
          });
      } else {
        this.displayOriginalPrice(widgetId);
        $widget.removeClass("aci-loading");
      }
    },

    /**
     * Fetch discount data from server
     */
    fetchDiscountData: function (discountCode, basePrice, currency) {
      const cacheKey = `${discountCode}_${basePrice}_${currency}`;

      // Check cache first
      if (this.discountCache[cacheKey]) {
        return $.Deferred().resolve(this.discountCache[cacheKey]);
      }

      return $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "aci_get_discount_data",
          nonce: this.config.nonce,
          discount_code: discountCode,
          base_price: basePrice,
          currency: currency,
        },
        dataType: "json",
      }).done((response) => {
        // Cache the response
        if (response.success) {
          this.discountCache[cacheKey] = response;
        }
      });
    },

    /**
     * Apply discount to a specific widget
     */
    applyDiscountToWidget: function (widgetId, discountData) {
      const widgetData = this.priceWidgets[widgetId];
      if (!widgetData) {
        return;
      }

      const $widget = widgetData.element;
      const basePrice = widgetData.basePrice;

      // Calculate discounted price
      let discountedPrice = basePrice;
      let discountAmount = 0;

      if (discountData.type === "percentage") {
        discountAmount = (basePrice * discountData.amount) / 100;
        discountedPrice = basePrice - discountAmount;
      } else if (discountData.type === "fixed") {
        discountAmount = discountData.amount;
        discountedPrice = Math.max(0, basePrice - discountAmount);
      }

      // Update widget data
      widgetData.currentPrice = discountedPrice;
      widgetData.discountApplied = true;

      // Update DOM
      const currencySymbol = this.getCurrencySymbol(widgetData.currency);

      // Original price
      if (widgetData.displayOriginal) {
        $widget
          .find(".aci-original-price")
          .html(`${currencySymbol}${this.formatPrice(basePrice)}`)
          .show();
      }

      // Discounted price
      const $discountedPrice = $widget.find(".aci-discounted-price");
      if (widgetData.animate) {
        this.animatePriceChange(
          $discountedPrice,
          basePrice,
          discountedPrice,
          currencySymbol
        );
      } else {
        $discountedPrice.html(
          `${currencySymbol}${this.formatPrice(discountedPrice)}`
        );
      }

      // Savings amount
      if (widgetData.showSavings) {
        const savingsText =
          discountData.type === "percentage"
            ? `Save ${discountData.amount}%`
            : `Save ${currencySymbol}${this.formatPrice(discountAmount)}`;

        $widget.find(".aci-savings-amount").html(savingsText).show();
      }

      // Show discount badge
      $widget.find(".aci-discount-badge").show();

      // Add class for styling
      $widget.addClass("aci-discount-active");

      // Trigger event
      $widget.trigger("aci:price:updated", {
        widgetId: widgetId,
        originalPrice: basePrice,
        discountedPrice: discountedPrice,
        discountAmount: discountAmount,
        discountData: discountData,
      });
    },

    /**
     * Display original price without discount
     */
    displayOriginalPrice: function (widgetId) {
      const widgetData = this.priceWidgets[widgetId];
      if (!widgetData) {
        return;
      }

      const $widget = widgetData.element;
      const basePrice = widgetData.basePrice;
      const currencySymbol = this.getCurrencySymbol(widgetData.currency);

      // Update widget data
      widgetData.currentPrice = basePrice;
      widgetData.discountApplied = false;

      // Hide original price display
      $widget.find(".aci-original-price").hide();

      // Show only discounted price (which is actually the original)
      $widget
        .find(".aci-discounted-price")
        .html(`${currencySymbol}${this.formatPrice(basePrice)}`);

      // Hide savings
      $widget.find(".aci-savings-amount").hide();

      // Hide discount badge
      $widget.find(".aci-discount-badge").hide();

      // Remove discount class
      $widget.removeClass("aci-discount-active");
    },

    /**
     * Animate price change
     */
    animatePriceChange: function (
      $element,
      fromPrice,
      toPrice,
      currencySymbol
    ) {
      const duration = 800;
      const steps = 20;
      const stepDuration = duration / steps;
      const priceStep = (toPrice - fromPrice) / steps;
      let currentStep = 0;

      const interval = setInterval(() => {
        currentStep++;
        const currentPrice = fromPrice + priceStep * currentStep;
        $element.html(`${currencySymbol}${this.formatPrice(currentPrice)}`);

        if (currentStep >= steps) {
          clearInterval(interval);
          $element.html(`${currencySymbol}${this.formatPrice(toPrice)}`);
        }
      }, stepDuration);
    },

    /**
     * Apply discount to all widgets
     */
    applyDiscountToAllWidgets: function () {
      for (const widgetId in this.priceWidgets) {
        if (this.priceWidgets.hasOwnProperty(widgetId)) {
          this.updatePriceWidget(widgetId);
        }
      }
    },

    /**
     * Handle discount applied event
     */
    handleDiscountApplied: function (data) {
      this.activeDiscountCode = data.code;
      this.activeDiscountData = data;

      // Store in session
      try {
        sessionStorage.setItem("aci_active_discount", JSON.stringify(data));
      } catch (e) {
        console.warn("Unable to store discount in session storage:", e);
      }

      // Update all widgets
      this.applyDiscountToAllWidgets();

      // Update discount displays
      this.updateAllDiscountDisplays();

      // Show success notification
      this.showNotification("Discount applied successfully!", "success");
    },

    /**
     * Handle discount removed event
     */
    handleDiscountRemoved: function () {
      this.activeDiscountCode = null;
      this.activeDiscountData = null;

      // Clear session
      try {
        sessionStorage.removeItem("aci_active_discount");
      } catch (e) {
        console.warn("Unable to clear discount from session storage:", e);
      }

      // Reset all widgets
      for (const widgetId in this.priceWidgets) {
        if (this.priceWidgets.hasOwnProperty(widgetId)) {
          this.displayOriginalPrice(widgetId);
        }
      }

      // Update discount displays
      this.updateAllDiscountDisplays();
    },

    /**
     * Validate and apply discount code
     */
    validateAndApplyDiscount: function (code) {
      const self = this;

      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "aci_validate_code",
          nonce: this.config.nonce,
          code: code,
        },
        dataType: "json",
      })
        .done(function (response) {
          if (response.success && response.data) {
            self.handleDiscountApplied({
              code: code,
              ...response.data,
            });
          }
        })
        .fail(function () {
          console.warn("Failed to validate discount code:", code);
        });
    },

    /**
     * Update discount display element
     */
    updateDiscountDisplay: function ($display) {
      if (!this.activeDiscountData) {
        $display.hide();
        return;
      }

      const data = this.activeDiscountData;

      // Update code
      $display.find(".aci-discount-code").text(data.code);

      // Update amount
      let amountText = "";
      if (data.type === "percentage") {
        amountText = `${data.amount}% OFF`;
      } else if (data.type === "fixed") {
        const currency = data.currency || "EUR";
        amountText = `${this.getCurrencySymbol(currency)}${this.formatPrice(
          data.amount
        )} OFF`;
      }
      $display.find(".aci-discount-amount").text(amountText);

      // Update description
      if (data.description) {
        $display
          .find(".aci-discount-description")
          .text(data.description)
          .show();
      }

      // Show display
      $display.show();
    },

    /**
     * Update all discount displays
     */
    updateAllDiscountDisplays: function () {
      const self = this;
      $(".aci-discount-display").each(function () {
        self.updateDiscountDisplay($(this));
      });
    },

    /**
     * Check success conditions for tracker
     */
    checkSuccessConditions: function ($tracker) {
      const conditions = $tracker.data("conditions") || "url_match";

      if (conditions === "url_match") {
        const successUrls = ($tracker.data("success-urls") || "").split(",");
        const currentUrl = window.location.href;

        const isMatch = successUrls.some((url) => {
          return currentUrl.includes(url.trim());
        });

        if (isMatch) {
          this.trackSuccess($tracker.attr("id"));
        }
      }
    },

    /**
     * Track success event
     */
    trackSuccess: function (trackerId) {
      const $tracker = $(`#${trackerId}`);
      if (!$tracker.length) {
        return;
      }

      const orderData = this.collectOrderData($tracker);

      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "aci_track_success",
          nonce: this.config.nonce,
          tracker_id: trackerId,
          order_data: orderData,
          discount_code: this.activeDiscountCode,
        },
        dataType: "json",
      })
        .done((response) => {
          if (response.success) {
            this.handleSuccessTracked($tracker);
          }
        })
        .fail((xhr, status, error) => {
          console.error("Success tracking failed:", error);
        });
    },

    /**
     * Collect order data for tracking
     */
    collectOrderData: function ($tracker) {
      const orderData = {
        order_id: $tracker.data("order-id") || "",
        total: parseFloat($tracker.data("order-total")) || 0,
        currency: $tracker.data("currency") || "EUR",
        items: [],
      };

      // Collect item data if available
      $tracker.find(".aci-order-item").each(function () {
        const $item = $(this);
        orderData.items.push({
          id: $item.data("product-id"),
          name: $item.data("product-name"),
          quantity: parseInt($item.data("quantity")) || 1,
          price: parseFloat($item.data("price")) || 0,
        });
      });

      return orderData;
    },

    /**
     * Handle successful tracking
     */
    handleSuccessTracked: function ($tracker) {
      const showMessage = $tracker.data("show-message") !== false;
      const redirectDelay = parseInt($tracker.data("redirect-delay")) || 0;
      const redirectUrl = $tracker.data("redirect-url") || "";

      if (showMessage) {
        $tracker.find(".aci-success-message").show();
      }

      // Trigger event
      $(document).trigger("aci:success:tracked", {
        trackerId: $tracker.attr("id"),
      });

      // Handle redirect
      if (redirectUrl && redirectDelay > 0) {
        setTimeout(() => {
          window.location.href = redirectUrl;
        }, redirectDelay * 1000);
      }
    },

    /**
     * Copy discount code to clipboard
     */
    copyDiscountCode: function (code, $button) {
      const self = this;

      // Create temporary input
      const $temp = $("<input>");
      $("body").append($temp);
      $temp.val(code).select();

      try {
        document.execCommand("copy");
        $temp.remove();

        // Show success feedback
        const originalText = $button.text();
        $button.text("Copied!").addClass("aci-copied").prop("disabled", true);

        setTimeout(() => {
          $button
            .text(originalText)
            .removeClass("aci-copied")
            .prop("disabled", false);
        }, 2000);

        // Track copy event
        $(document).trigger("aci:code:copied", { code: code });
      } catch (err) {
        console.error("Failed to copy code:", err);
        self.showNotification("Failed to copy code", "error");
      }
    },

    /**
     * Format price for display
     */
    formatPrice: function (price) {
      return parseFloat(price).toFixed(2);
    },

    /**
     * Get currency symbol
     */
    getCurrencySymbol: function (currency) {
      const symbols = this.config.currency.symbols || {
        EUR: "€",
        USD: "$",
        GBP: "£",
        JPY: "¥",
      };

      return symbols[currency] || currency + " ";
    },

    /**
     * Show notification
     */
    showNotification: function (message, type) {
      type = type || "info";

      const $notification = $("<div>")
        .addClass(`aci-notification aci-notification-${type}`)
        .text(message)
        .appendTo("body");

      setTimeout(() => {
        $notification.addClass("aci-show");
      }, 10);

      setTimeout(() => {
        $notification.removeClass("aci-show");
        setTimeout(() => {
          $notification.remove();
        }, 300);
      }, 3000);
    },

    /**
     * Get current discount code
     */
    getCurrentDiscountCode: function () {
      return this.activeDiscountCode;
    },

    /**
     * Get current discount data
     */
    getCurrentDiscountData: function () {
      return this.activeDiscountData;
    },

    /**
     * Refresh all pricing
     */
    refreshAllPricing: function () {
      this.discountCache = {};
      this.applyDiscountToAllWidgets();
    },
  };

  // Initialize on document ready
  $(document).ready(function () {
    ACI_Pricing.init();
  });

  // Re-initialize on AJAX page loads
  $(document).on("ready", function () {
    if (ACI_Pricing.initialized) {
      ACI_Pricing.initializePriceWidgets();
      ACI_Pricing.initializeDiscountDisplays();
      ACI_Pricing.initializeSuccessTrackers();
    }
  });
})(jQuery);