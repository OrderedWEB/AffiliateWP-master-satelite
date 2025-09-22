/**
 * Affiliate Client Full - Discount Code Functionality
 *
 * JavaScript functionality for discount code widgets, copy-to-clipboard,
 * auto-apply features, and integration with e-commerce platforms.
 *
 * @package AffiliateClientFull
 * @version 1.0.0
 */

(function ($) {
  "use strict";

  // Discount code functionality
  window.AffiliateClientDiscount = {
    // Configuration
    config: {
      ajaxUrl: "",
      restUrl: "",
      nonce: "",
      debug: false,
    },

    // State
    initialized: false,
    activeWidgets: {},

    /**
     * Initialize discount functionality
     */
    init: function (config) {
      this.config = $.extend(this.config, config || {});

      this.setupEventListeners();
      this.initializeWidgets();
      this.checkAutoApply();

      this.initialized = true;
      this.log("Discount functionality initialized");
    },

    /**
     * Setup event listeners
     */
    setupEventListeners: function () {
      var self = this;

      // Copy button clicks
      $(document).on("click", ".affiliate-discount-copy-btn", function (e) {
        e.preventDefault();
        self.copyCode(this);
      });

      // Apply button clicks
      $(document).on("click", ".affiliate-discount-apply-btn", function (e) {
        e.preventDefault();
        self.applyCode(this);
      });

      // Widget interactions for tracking
      $(document).on("click", ".affiliate-discount-widget", function () {
        self.trackInteraction(this, "widget_click");
      });

      // Keyboard navigation
      $(document).on("keydown", ".affiliate-discount-widget", function (e) {
        if (e.keyCode === 13 || e.keyCode === 32) {
          // Enter or Space
          e.preventDefault();
          var $copyBtn = $(this).find(".affiliate-discount-copy-btn");
          if ($copyBtn.length > 0) {
            self.copyCode($copyBtn[0]);
          }
        }
      });
    },

    /**
     * Initialize all discount widgets on page
     */
    initializeWidgets: function () {
      var self = this;

      $(".affiliate-discount-widget").each(function () {
        var $widget = $(this);
        var widgetId =
          this.id || "widget-" + Math.random().toString(36).substr(2, 9);

        self.activeWidgets[widgetId] = {
          element: this,
          code: $widget.data("code"),
          type: $widget.data("type"),
          affiliateId: $widget.data("affiliate-id"),
          trackClicks: $widget.data("track-clicks"),
          autoApply: $widget.data("auto-apply"),
        };

        // Make widget focusable for accessibility
        if (!$widget.attr("tabindex")) {
          $widget.attr("tabindex", "0");
        }

        // Track widget view
        self.trackInteraction(this, "widget_view");
      });
    },

    /**
     * Copy discount code to clipboard
     */
    copyCode: function (button) {
      var $button = $(button);
      var $widget = $button.closest(".affiliate-discount-widget");
      var code = $widget.data("code");
      var copyText = $button.data("copy-text");
      var successText = $button.data("success-text");

      if (!code) {
        this.showFeedback($widget, "No code to copy", "error");
        return;
      }

      // Try modern clipboard API first
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard
          .writeText(code)
          .then(
            function () {
              this.onCopySuccess($button, $widget, successText);
            }.bind(this)
          )
          .catch(
            function () {
              this.fallbackCopy(code, $button, $widget, successText);
            }.bind(this)
          );
      } else {
        this.fallbackCopy(code, $button, $widget, successText);
      }

      // Track copy action
      this.trackInteraction($widget[0], "code_copied", { code: code });
    },

    /**
     * Fallback copy method for older browsers
     */
    fallbackCopy: function (text, $button, $widget, successText) {
      var textArea = document.createElement("textarea");
      textArea.value = text;
      textArea.style.position = "fixed";
      textArea.style.left = "-999999px";
      textArea.style.top = "-999999px";
      document.body.appendChild(textArea);
      textArea.focus();
      textArea.select();

      try {
        var successful = document.execCommand("copy");
        if (successful) {
          this.onCopySuccess($button, $widget, successText);
        } else {
          this.onCopyError($button, $widget);
        }
      } catch (err) {
        this.onCopyError($button, $widget);
      }

      document.body.removeChild(textArea);
    },

    /**
     * Handle successful copy
     */
    onCopySuccess: function ($button, $widget, successText) {
      var $text = $button.find(".copy-text");
      var originalText = $text.text();

      // Update button text
      $text.text(successText);
      $button.addClass("copied");

      // Show success feedback
      this.showFeedback($widget, successText, "success");

      // Reset button after delay
      setTimeout(function () {
        $text.text(originalText);
        $button.removeClass("copied");
      }, 2000);
    },

    /**
     * Handle copy error
     */
    onCopyError: function ($button, $widget) {
      this.showFeedback($widget, "Failed to copy code", "error");
      $button.addClass("error");

      setTimeout(function () {
        $button.removeClass("error");
      }, 2000);
    },

    /**
     * Apply discount code
     */
    applyCode: function (button) {
      var $button = $(button);
      var $widget = $button.closest(".affiliate-discount-widget");
      var code = $widget.data("code");
      var type = $widget.data("type");
      var affiliateId = $widget.data("affiliate-id");

      if (!code) {
        this.showFeedback($widget, "No code to apply", "error");
        return;
      }

      // Show loading state
      $button.addClass("loading").prop("disabled", true);
      var originalText = $button.text();
      $button.text("Applying...");

      // Make AJAX request
      this.sendApplyRequest(code, type, affiliateId)
        .then(
          function (response) {
            $button
              .removeClass("loading")
              .prop("disabled", false)
              .text(originalText);

            if (response.success) {
              this.showFeedback($widget, response.message, "success");
              $button.addClass("applied").text("Applied!");

              // Track successful application
              this.trackInteraction($widget[0], "code_applied", {
                code: code,
                action: response.action,
              });

              // Redirect to checkout if applicable
              if (response.action === "applied") {
                setTimeout(
                  function () {
                    this.redirectToCheckout();
                  }.bind(this),
                  1500
                );
              }
            } else {
              this.showFeedback($widget, response.message, "error");

              // Track failed application
              this.trackInteraction($widget[0], "code_apply_failed", {
                code: code,
                error: response.message,
              });
            }
          }.bind(this)
        )
        .catch(
          function (error) {
            $button
              .removeClass("loading")
              .prop("disabled", false)
              .text(originalText);
            this.showFeedback(
              $widget,
              "Failed to apply code. Please try again.",
              "error"
            );
            this.log("Apply code error:", error);
          }.bind(this)
        );
    },

    /**
     * Send apply request via AJAX or REST API
     */
    sendApplyRequest: function (code, type, affiliateId) {
      // Try REST API first
      if (this.config.restUrl) {
        return $.ajax({
          url: this.config.restUrl + "discount/apply",
          method: "POST",
          contentType: "application/json",
          headers: {
            "X-WP-Nonce": this.config.nonce,
          },
          data: JSON.stringify({
            code: code,
            type: type,
            affiliate_id: affiliateId,
          }),
        });
      } else {
        // Fallback to AJAX
        return $.ajax({
          url: this.config.ajaxUrl,
          method: "POST",
          data: {
            action: "affiliate_client_apply_discount",
            nonce: this.config.nonce,
            code: code,
            type: type,
            affiliate_id: affiliateId,
          },
        });
      }
    },

    /**
     * Auto-apply discount codes
     */
    autoApply: function (widgetId) {
      var widget = this.activeWidgets[widgetId];
      if (!widget || !widget.autoApply) {
        return;
      }

      var $widget = $(widget.element);
      var $applyBtn = $widget.find(".affiliate-discount-apply-btn");

      if ($applyBtn.length > 0) {
        // Delay auto-apply to ensure page is fully loaded
        setTimeout(
          function () {
            this.applyCode($applyBtn[0]);
          }.bind(this),
          1000
        );
      }
    },

    /**
     * Check for auto-apply on page load
     */
    checkAutoApply: function () {
      var self = this;

      // Check URL parameters for discount codes
      var urlParams = new URLSearchParams(window.location.search);
      var discountParam = urlParams.get("discount") || urlParams.get("coupon");

      if (discountParam) {
        this.applyDiscountFromUrl(discountParam);
      }

      // Check for stored discount codes
      var storedCode = this.getStoredDiscount();
      if (storedCode && this.shouldAutoApplyStored()) {
        this.applyStoredDiscount(storedCode);
      }
    },

    /**
     * Apply discount from URL parameter
     */
    applyDiscountFromUrl: function (code) {
      // Find matching widget or create temporary application
      var $matchingWidget = $(
        '.affiliate-discount-widget[data-code="' + code + '"]'
      ).first();

      if ($matchingWidget.length > 0) {
        var $applyBtn = $matchingWidget.find(".affiliate-discount-apply-btn");
        if ($applyBtn.length > 0) {
          this.applyCode($applyBtn[0]);
        }
      } else {
        // Apply directly without widget
        this.sendApplyRequest(
          code,
          "coupon",
          this.getCurrentAffiliateId()
        ).then(
          function (response) {
            if (response.success) {
              this.showGlobalNotification(response.message, "success");
            }
          }.bind(this)
        );
      }
    },

    /**
     * Get stored discount code
     */
    getStoredDiscount: function () {
      return (
        localStorage.getItem("affiliate_discount_code") ||
        this.getCookie("affiliate_discount_code")
      );
    },

    /**
     * Check if stored discount should be auto-applied
     */
    shouldAutoApplyStored: function () {
      var lastApplied = localStorage.getItem("affiliate_discount_last_applied");
      if (!lastApplied) return true;

      var daysSinceApplied =
        (Date.now() - parseInt(lastApplied)) / (1000 * 60 * 60 * 24);
      return daysSinceApplied > 1; // Don't auto-apply more than once per day
    },

    /**
     * Apply stored discount
     */
    applyStoredDiscount: function (code) {
      this.sendApplyRequest(code, "coupon", this.getCurrentAffiliateId()).then(
        function (response) {
          if (response.success) {
            localStorage.setItem(
              "affiliate_discount_last_applied",
              Date.now().toString()
            );
            this.showGlobalNotification(
              "Saved discount code applied: " + code,
              "success"
            );
          }
        }.bind(this)
      );
    },

    /**
     * Show feedback message
     */
    showFeedback: function ($widget, message, type) {
      var $feedback = $widget.find(".affiliate-discount-feedback");

      $feedback
        .removeClass("success error info")
        .addClass(type)
        .text(message)
        .slideDown(200);

      // Auto-hide after delay
      setTimeout(function () {
        $feedback.slideUp(200);
      }, 4000);
    },

    /**
     * Show global notification
     */
    showGlobalNotification: function (message, type) {
      var $notification = $(
        '<div class="affiliate-discount-notification ' + type + '">'
      )
        .html(
          '<span class="message">' +
            message +
            '</span><button class="close">&times;</button>'
        )
        .appendTo("body");

      // Show notification
      setTimeout(function () {
        $notification.addClass("show");
      }, 100);

      // Auto-hide
      setTimeout(function () {
        $notification.removeClass("show");
        setTimeout(function () {
          $notification.remove();
        }, 300);
      }, 5000);

      // Close button
      $notification.find(".close").on("click", function () {
        $notification.removeClass("show");
        setTimeout(function () {
          $notification.remove();
        }, 300);
      });
    },

    /**
     * Track discount interaction
     */
    trackInteraction: function (widget, action, data) {
      var $widget = $(widget);

      if (!$widget.data("track-clicks")) {
        return;
      }

      var eventData = $.extend(
        {
          widget_id: widget.id,
          code: $widget.data("code"),
          type: $widget.data("type"),
          affiliate_id: $widget.data("affiliate-id"),
          action: action,
          page_url: window.location.href,
        },
        data || {}
      );

      // Use main tracking system if available
      if (
        window.AffiliateClientTracker &&
        window.AffiliateClientTracker.initialized
      ) {
        window.AffiliateClientTracker.trackEvent(
          "discount_interaction",
          eventData
        );
      }
    },

    /**
     * Redirect to checkout page
     */
    redirectToCheckout: function () {
      var checkoutUrl = this.getCheckoutUrl();
      if (checkoutUrl) {
        window.location.href = checkoutUrl;
      }
    },

    /**
     * Get checkout URL based on active e-commerce plugin
     */
    getCheckoutUrl: function () {
      // WooCommerce
      if (typeof wc_checkout_params !== "undefined") {
        return wc_checkout_params.checkout_url;
      }

      // Easy Digital Downloads
      if (typeof edd_scripts !== "undefined" && edd_scripts.checkout_page) {
        return edd_scripts.checkout_page;
      }

      // Generic fallback - look for common checkout URLs
      var commonPaths = ["/checkout", "/cart", "/checkout-2", "/purchase"];
      for (var i = 0; i < commonPaths.length; i++) {
        var testUrl = window.location.origin + commonPaths[i];
        // In a real implementation, you might want to test these URLs
        return testUrl;
      }

      return null;
    },

    /**
     * Get current affiliate ID
     */
    getCurrentAffiliateId: function () {
      // Try to get from main tracker
      if (window.AffiliateClientTracker) {
        return window.AffiliateClientTracker.getAffiliateId();
      }

      // Fallback to cookie
      return this.getCookie("affiliate_client_ref");
    },

    /**
     * Get cookie value
     */
    getCookie: function (name) {
      var nameEQ = name + "=";
      var cookies = document.cookie.split(";");

      for (var i = 0; i < cookies.length; i++) {
        var cookie = cookies[i].trim();
        if (cookie.indexOf(nameEQ) === 0) {
          return decodeURIComponent(cookie.substring(nameEQ.length));
        }
      }

      return null;
    },

    /**
     * Create discount widget programmatically
     */
    createWidget: function (options) {
      var defaults = {
        code: "",
        type: "coupon",
        title: "",
        description: "",
        style: "default",
        size: "medium",
        color: "#4CAF50",
        textColor: "#ffffff",
        showCopy: true,
        showApply: true,
        autoApply: false,
        trackClicks: true,
      };

      var settings = $.extend(defaults, options);
      var widgetId =
        "affiliate-discount-dynamic-" + Math.random().toString(36).substr(2, 9);

      var widget = $("<div>")
        .attr("id", widgetId)
        .addClass(
          "affiliate-discount-widget style-" +
            settings.style +
            " size-" +
            settings.size
        )
        .attr("data-code", settings.code)
        .attr("data-type", settings.type)
        .attr("data-track-clicks", settings.trackClicks)
        .attr("data-auto-apply", settings.autoApply)
        .css({
          "--primary-color": settings.color,
          "--text-color": settings.textColor,
        });

      var content = '<div class="affiliate-discount-content">';

      if (settings.title) {
        content +=
          '<div class="affiliate-discount-title">' + settings.title + "</div>";
      }

      content += '<div class="affiliate-discount-code-section">';
      content +=
        '<span class="affiliate-discount-label">' +
        settings.type.toUpperCase() +
        " CODE:</span>";
      content += '<div class="affiliate-discount-code-container">';
      content +=
        '<span class="affiliate-discount-code">' + settings.code + "</span>";

      if (settings.showCopy) {
        content += '<button type="button" class="affiliate-discount-copy-btn">';
        content +=
          '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">';
        content +=
          '<path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>';
        content += '</svg><span class="copy-text">Copy Code</span>';
        content += "</button>";
      }

      content += "</div></div>";

      if (settings.description) {
        content +=
          '<div class="affiliate-discount-description">' +
          settings.description +
          "</div>";
      }

      if (settings.showApply) {
        content += '<div class="affiliate-discount-actions">';
        content +=
          '<button type="button" class="affiliate-discount-apply-btn">Apply Code</button>';
        content += "</div>";
      }

      content += "</div>";
      content +=
        '<div class="affiliate-discount-feedback" style="display: none;"></div>';

      widget.html(content);

      // Register widget
      this.activeWidgets[widgetId] = {
        element: widget[0],
        code: settings.code,
        type: settings.type,
        affiliateId: settings.affiliateId || this.getCurrentAffiliateId(),
        trackClicks: settings.trackClicks,
        autoApply: settings.autoApply,
      };

      return widget;
    },

    /**
     * Remove widget
     */
    removeWidget: function (widgetId) {
      if (this.activeWidgets[widgetId]) {
        $(this.activeWidgets[widgetId].element).remove();
        delete this.activeWidgets[widgetId];
      }
    },

    /**
     * Get widget statistics
     */
    getStats: function () {
      var stats = {
        totalWidgets: Object.keys(this.activeWidgets).length,
        widgetsByType: {},
        widgetsByStyle: {},
      };

      for (var id in this.activeWidgets) {
        var widget = this.activeWidgets[id];
        var $element = $(widget.element);

        // Count by type
        var type = widget.type || "unknown";
        stats.widgetsByType[type] = (stats.widgetsByType[type] || 0) + 1;

        // Count by style
        var style = "default";
        var classList = $element.attr("class").split(" ");
        for (var i = 0; i < classList.length; i++) {
          if (classList[i].startsWith("style-")) {
            style = classList[i].replace("style-", "");
            break;
          }
        }
        stats.widgetsByStyle[style] = (stats.widgetsByStyle[style] || 0) + 1;
      }

      return stats;
    },

    /**
     * Log debug messages
     */
    log: function () {
      if (this.config.debug && window.console && console.log) {
        var args = Array.prototype.slice.call(arguments);
        args.unshift("[Affiliate Client Discount]");
        console.log.apply(console, args);
      }
    },
  };

  // Auto-initialize when config is available
  $(document).ready(function () {
    if (typeof affiliateClientConfig !== "undefined") {
      AffiliateClientDiscount.init(affiliateClientConfig);
    }
  });

  // Expose to global scope
  window.AffiliateClientDiscount = AffiliateClientDiscount;
})(jQuery);
