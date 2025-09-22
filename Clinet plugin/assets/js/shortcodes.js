/**
 * Shortcodes JavaScript
 * File: /wp-content/plugins/affiliate-client-integration/assets/js/shortcodes.js
 * Plugin: Affiliate Client Integration
 */

(function ($) {
  "use strict";

  window.ACI = window.ACI || {};

  /**
   * Shortcode Manager
   */
  ACI.Shortcodes = {
    // Configuration
    config: {
      animationDuration: 300,
      autoInit: true,
      trackInteractions: true,
    },

    // Shortcode handlers
    handlers: {},

    /**
     * Initialize shortcode system
     */
    init: function () {
      this.registerHandlers();
      this.initializeShortcodes();
      this.bindEvents();

      console.log("ACI Shortcodes initialized");
    },

    /**
     * Register shortcode handlers
     */
    registerHandlers: function () {
      this.handlers = {
        "affiliate-form": this.handleAffiliateForm.bind(this),
        "discount-display": this.handleDiscountDisplay.bind(this),
        "popup-trigger": this.handlePopupTrigger.bind(this),
        "price-calculator": this.handlePriceCalculator.bind(this),
        "affiliate-link": this.handleAffiliateLink.bind(this),
        "conversion-tracker": this.handleConversionTracker.bind(this),
        "affiliate-notice": this.handleAffiliateNotice.bind(this),
        "referral-stats": this.handleReferralStats.bind(this),
      };
    },

    /**
     * Initialize all shortcodes on page
     */
    initializeShortcodes: function () {
      $(".aci-shortcode").each((index, element) => {
        this.initializeShortcode($(element));
      });
    },

    /**
     * Initialize individual shortcode
     */
    initializeShortcode: function ($element) {
      const type = $element.data("shortcode-type");
      const config = $element.data("shortcode-config") || {};

      if (this.handlers[type]) {
        this.handlers[type]($element, config);
        $element.addClass("aci-shortcode-initialized");

        if (this.config.trackInteractions) {
          this.trackShortcodeView(type, config);
        }
      } else {
        console.warn("Unknown shortcode type:", type);
      }
    },

    /**
     * Bind global events
     */
    bindEvents: function () {
      // Re-initialize shortcodes when content is dynamically added
      $(document).on("DOMNodeInserted", (e) => {
        const $target = $(e.target);
        if (
          $target.hasClass("aci-shortcode") &&
          !$target.hasClass("aci-shortcode-initialized")
        ) {
          this.initializeShortcode($target);
        }
      });

      // Handle shortcode interactions
      $(document).on("click", ".aci-shortcode [data-aci-action]", (e) => {
        this.handleShortcodeAction($(e.currentTarget));
      });

      // Listen for affiliate events
      $(document).on("aci:affiliate_set", (e, data) => {
        this.onAffiliateChange(data);
      });

      $(document).on("aci:affiliate_cleared", () => {
        this.onAffiliateChange(null);
      });
    },

    /**
     * Shortcode Handlers
     */

    /**
     * Handle affiliate form shortcode
     */
    handleAffiliateForm: function ($element, config) {
      const formType = config.type || "inline";
      const $form = $element.find(".aci-affiliate-form");

      if (!$form.length) return;

      // Setup form validation
      $form.find('input[name="affiliate_code"]').on("input", (e) => {
        this.validateAffiliateCode($(e.target));
      });

      // Handle form submission
      $form.on("submit", (e) => {
        e.preventDefault();
        this.submitAffiliateForm($form, config);
      });

      // Auto-focus if configured
      if (config.auto_focus) {
        $form.find('input[name="affiliate_code"]').focus();
      }

      // Apply custom styling
      if (config.theme) {
        $element.addClass(`aci-theme-${config.theme}`);
      }
    },

    /**
     * Handle discount display shortcode
     */
    handleDiscountDisplay: function ($element, config) {
      const updateDisplay = (affiliateData) => {
        if (affiliateData && affiliateData.discount) {
          const discount = affiliateData.discount;
          const $display = $element.find(".aci-discount-amount");

          if (discount.type === "percentage") {
            $display.text(`${discount.value}% OFF`);
          } else {
            $display.text(`$${discount.value} OFF`);
          }

          $element.show().addClass("aci-active");

          if (config.animate) {
            this.animateDiscount($element);
          }
        } else {
          $element.hide().removeClass("aci-active");
        }
      };

      // Check current affiliate
      if (window.ACI.URLProcessor) {
        const current = window.ACI.URLProcessor.getCurrentAffiliateData();
        updateDisplay(current);
      }

      // Listen for changes
      $(document).on("aci:affiliate_set aci:affiliate_cleared", () => {
        const current = window.ACI.URLProcessor
          ? window.ACI.URLProcessor.getCurrentAffiliateData()
          : null;
        updateDisplay(current);
      });
    },

    /**
     * Handle popup trigger shortcode
     */
    handlePopupTrigger: function ($element, config) {
      const triggerType = config.trigger || "click";
      const popupType = config.popup_type || "default";

      if (triggerType === "click") {
        $element.on("click", (e) => {
          e.preventDefault();
          this.triggerPopup(popupType, config);
        });
      } else if (triggerType === "hover") {
        $element.on("mouseenter", () => {
          this.triggerPopup(popupType, config);
        });
      } else if (triggerType === "auto") {
        const delay = parseInt(config.delay) || 0;
        setTimeout(() => {
          this.triggerPopup(popupType, config);
        }, delay * 1000);
      }
    },

    /**
     * Handle price calculator shortcode
     */
    handlePriceCalculator: function ($element, config) {
      // Initialize the price calculator for this element
      if (window.ACI.PriceCalculator) {
        window.ACI.PriceCalculator.initializeForm(
          $element.find(".aci-price-form")
        );
      }

      // Setup custom calculations if configured
      if (config.custom_calculation) {
        $element.data("custom-calculation", config.custom_calculation);
      }

      // Handle currency display
      if (config.currency) {
        $element.data("currency", config.currency);
      }
    },

    /**
     * Handle affiliate link shortcode
     */
    handleAffiliateLink: function ($element, config) {
      const $link = $element.find("a");
      if (!$link.length) return;

      // Add affiliate code to link if available
      $link.on("click", (e) => {
        const currentAffiliate = window.ACI.URLProcessor
          ? window.ACI.URLProcessor.getCurrentAffiliate()
          : null;

        if (currentAffiliate) {
          const url = new URL($link.attr("href"), window.location.origin);
          url.searchParams.set(config.param || "aff", currentAffiliate);
          $link.attr("href", url.toString());

          // Track click
          this.trackShortcodeInteraction("affiliate_link_click", {
            affiliate: currentAffiliate,
            url: url.toString(),
          });
        }
      });

      // Add visual indicator if affiliate is active
      $(document).on("aci:affiliate_set aci:affiliate_cleared", () => {
        const currentAffiliate = window.ACI.URLProcessor
          ? window.ACI.URLProcessor.getCurrentAffiliate()
          : null;

        if (currentAffiliate) {
          $element.addClass("aci-affiliate-active");
          if (config.show_indicator) {
            this.addAffiliateIndicator($element);
          }
        } else {
          $element.removeClass("aci-affiliate-active");
          $element.find(".aci-affiliate-indicator").remove();
        }
      });
    },

    /**
     * Handle conversion tracker shortcode
     */
    handleConversionTracker: function ($element, config) {
      const trackingPixel = config.pixel_url;
      const conversionValue = config.value || 0;

      // Setup conversion tracking
      if (config.trigger === "page_load") {
        this.trackConversion(trackingPixel, conversionValue, config);
      } else if (config.trigger === "form_submit") {
        $(config.form_selector || "form").on("submit", () => {
          this.trackConversion(trackingPixel, conversionValue, config);
        });
      } else if (config.trigger === "element_click") {
        $(config.element_selector).on("click", () => {
          this.trackConversion(trackingPixel, conversionValue, config);
        });
      }
    },

    /**
     * Handle affiliate notice shortcode
     */
    handleAffiliateNotice: function ($element, config) {
      const position = config.position || "top";
      const style = config.style || "banner";

      $element.addClass(`aci-notice-${position} aci-notice-${style}`);

      const updateNotice = (affiliateData) => {
        if (affiliateData) {
          const message =
            config.message ||
            `Affiliate discount active: ${affiliateData.code}`;
          $element.find(".aci-notice-message").text(message);

          if (config.animate) {
            $element.hide().slideDown(this.config.animationDuration);
          } else {
            $element.show();
          }
        } else {
          if (config.animate) {
            $element.slideUp(this.config.animationDuration);
          } else {
            $element.hide();
          }
        }
      };

      // Check current state
      if (window.ACI.URLProcessor) {
        const current = window.ACI.URLProcessor.getCurrentAffiliateData();
        updateNotice(current);
      }

      // Listen for changes
      $(document).on("aci:affiliate_set aci:affiliate_cleared", () => {
        const current = window.ACI.URLProcessor
          ? window.ACI.URLProcessor.getCurrentAffiliateData()
          : null;
        updateNotice(current);
      });

      // Handle close button
      $element.find(".aci-notice-close").on("click", () => {
        $element.slideUp(this.config.animationDuration);
      });
    },

    /**
     * Handle referral stats shortcode
     */
    handleReferralStats: function ($element, config) {
      const affiliateCode =
        config.affiliate_code ||
        (window.ACI.URLProcessor
          ? window.ACI.URLProcessor.getCurrentAffiliate()
          : null);

      if (affiliateCode) {
        this.loadReferralStats(affiliateCode).then((stats) => {
          this.displayReferralStats($element, stats, config);
        });
      }

      // Update when affiliate changes
      $(document).on("aci:affiliate_set", (e, data) => {
        this.loadReferralStats(data.code).then((stats) => {
          this.displayReferralStats($element, stats, config);
        });
      });
    },

    /**
     * Helper Methods
     */

    /**
     * Submit affiliate form
     */
    submitAffiliateForm: function ($form, config) {
      const $input = $form.find('input[name="affiliate_code"]');
      const $button = $form.find('button[type="submit"]');
      const code = $input.val().trim();

      if (!code) {
        this.showFormMessage($form, "Please enter an affiliate code", "error");
        return;
      }

      // Show loading state
      $button.prop("disabled", true).addClass("aci-loading");
      $form.find(".aci-form-message").remove();

      // Validate affiliate code
      $.ajax({
        url: aci_ajax.ajax_url,
        type: "POST",
        data: {
          action: "aci_validate_affiliate",
          affiliate_code: code,
          nonce: aci_ajax.nonce,
        },
        success: (response) => {
          if (response.success) {
            this.showFormMessage(
              $form,
              "Affiliate code applied successfully!",
              "success"
            );

            // Trigger affiliate set event
            $(document).trigger("aci:affiliate_set", [response.data]);

            // Redirect if configured
            if (config.redirect_url) {
              setTimeout(() => {
                window.location.href = config.redirect_url;
              }, 1500);
            }

            // Track conversion
            this.trackShortcodeInteraction("affiliate_form_submit", {
              code: code,
              success: true,
            });
          } else {
            this.showFormMessage(
              $form,
              response.data || "Invalid affiliate code",
              "error"
            );
            this.trackShortcodeInteraction("affiliate_form_submit", {
              code: code,
              success: false,
            });
          }
        },
        error: () => {
          this.showFormMessage(
            $form,
            "Unable to validate affiliate code. Please try again.",
            "error"
          );
        },
        complete: () => {
          $button.prop("disabled", false).removeClass("aci-loading");
        },
      });
    },

    /**
     * Validate affiliate code input
     */
    validateAffiliateCode: function ($input) {
      const code = $input.val().trim();
      const $indicator = $input.siblings(".aci-input-indicator");

      if (code.length < 2) {
        $indicator.removeClass("aci-valid aci-invalid");
        return;
      }

      // Basic format validation
      if (!/^[a-zA-Z0-9_-]+$/.test(code)) {
        $indicator.removeClass("aci-valid").addClass("aci-invalid");
        return;
      }

      $indicator.removeClass("aci-invalid").addClass("aci-valid");
    },

    /**
     * Show form message
     */
    showFormMessage: function ($form, message, type) {
      $form.find(".aci-form-message").remove();

      const messageHtml = `<div class="aci-form-message aci-message-${type}">${message}</div>`;
      $form.append(messageHtml);

      // Auto-hide success messages
      if (type === "success") {
        setTimeout(() => {
          $form.find(".aci-form-message").fadeOut();
        }, 5000);
      }
    },

    /**
     * Trigger popup
     */
    triggerPopup: function (popupType, config) {
      if (window.ACI.PopupManager) {
        window.ACI.PopupManager.show(popupType, config);

        this.trackShortcodeInteraction("popup_triggered", {
          type: popupType,
          trigger: config.trigger,
        });
      }
    },

    /**
     * Animate discount display
     */
    animateDiscount: function ($element) {
      $element.addClass("aci-discount-pulse");

      setTimeout(() => {
        $element.removeClass("aci-discount-pulse");
      }, 1000);
    },

    /**
     * Add affiliate indicator
     */
    addAffiliateIndicator: function ($element) {
      if ($element.find(".aci-affiliate-indicator").length) return;

      const indicator =
        '<span class="aci-affiliate-indicator" title="Affiliate discount will be applied">💰</span>';
      $element.append(indicator);
    },

    /**
     * Track conversion
     */
    trackConversion: function (pixelUrl, value, config) {
      const affiliateCode = window.ACI.URLProcessor
        ? window.ACI.URLProcessor.getCurrentAffiliate()
        : null;

      if (!affiliateCode) return;

      // Fire tracking pixel
      if (pixelUrl) {
        const img = new Image();
        img.src = pixelUrl
          .replace("{{affiliate}}", affiliateCode)
          .replace("{{value}}", value);
      }

      // Send to server
      $.ajax({
        url: aci_ajax.ajax_url,
        type: "POST",
        data: {
          action: "aci_track_conversion",
          affiliate_code: affiliateCode,
          conversion_value: value,
          conversion_type: config.type || "general",
          nonce: aci_ajax.nonce,
        },
      });

      this.trackShortcodeInteraction("conversion_tracked", {
        affiliate: affiliateCode,
        value: value,
        type: config.type,
      });
    },

    /**
     * Load referral stats
     */
    loadReferralStats: function (affiliateCode) {
      return new Promise((resolve, reject) => {
        $.ajax({
          url: aci_ajax.ajax_url,
          type: "POST",
          data: {
            action: "aci_get_referral_stats",
            affiliate_code: affiliateCode,
            nonce: aci_ajax.nonce,
          },
          success: (response) => {
            if (response.success) {
              resolve(response.data);
            } else {
              reject(response.data);
            }
          },
          error: reject,
        });
      });
    },

    /**
     * Display referral stats
     */
    displayReferralStats: function ($element, stats, config) {
      const template = config.template || "default";

      let html = "";

      if (template === "simple") {
        html = `
                    <div class="aci-stats-simple">
                        <span class="aci-stat-label">Referrals:</span>
                        <span class="aci-stat-value">${
                          stats.referrals || 0
                        }</span>
                    </div>
                `;
      } else {
        html = `
                    <div class="aci-stats-detailed">
                        <div class="aci-stat-item">
                            <span class="aci-stat-label">Total Referrals:</span>
                            <span class="aci-stat-value">${
                              stats.referrals || 0
                            }</span>
                        </div>
                        <div class="aci-stat-item">
                            <span class="aci-stat-label">Conversions:</span>
                            <span class="aci-stat-value">${
                              stats.conversions || 0
                            }</span>
                        </div>
                        <div class="aci-stat-item">
                            <span class="aci-stat-label">Total Earned:</span>
                            <span class="aci-stat-value">${
                              stats.total_earned || "0.00"
                            }</span>
                        </div>
                    </div>
                `;
      }

      $element.find(".aci-stats-container").html(html);
    },

    /**
     * Handle affiliate change
     */
    onAffiliateChange: function (affiliateData) {
      // Update all relevant shortcodes
      $(".aci-shortcode-initialized").each((index, element) => {
        const $element = $(element);
        const type = $element.data("shortcode-type");

        // Refresh shortcodes that depend on affiliate state
        if (
          ["discount-display", "affiliate-notice", "affiliate-link"].includes(
            type
          )
        ) {
          const config = $element.data("shortcode-config") || {};
          if (this.handlers[type]) {
            this.handlers[type]($element, config);
          }
        }
      });
    },

    /**
     * Track shortcode interactions
     */
    trackShortcodeView: function (type, config) {
      if (!this.config.trackInteractions) return;

      this.trackShortcodeInteraction("shortcode_view", {
        type: type,
        config: config,
      });
    },

    trackShortcodeInteraction: function (action, data) {
      if (!this.config.trackInteractions) return;

      if (window.ACI.App && window.ACI.App.trackEvent) {
        window.ACI.App.trackEvent("shortcode_interaction", {
          action: action,
          ...data,
        });
      }
    },

    /**
     * Register custom shortcode handler
     */
    registerHandler: function (type, handler) {
      this.handlers[type] = handler;
    },

    /**
     * Manually initialize shortcode
     */
    initShortcode: function (selector, type, config = {}) {
      const $element = $(selector);
      $element.data("shortcode-type", type);
      $element.data("shortcode-config", config);
      $element.addClass("aci-shortcode");

      this.initializeShortcode($element);
    },

    /**
     * Refresh all shortcodes
     */
    refresh: function () {
      $(".aci-shortcode-initialized").removeClass("aci-shortcode-initialized");
      this.initializeShortcodes();
    },
  };

  // Initialize when document is ready
  $(document).ready(function () {
    if (ACI.Shortcodes.config.autoInit) {
      ACI.Shortcodes.init();
    }
  });

  // Export for external use
  window.ACI.Shortcodes = ACI.Shortcodes;
})(jQuery);
