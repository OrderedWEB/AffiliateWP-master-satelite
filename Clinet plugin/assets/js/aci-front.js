/**
 * ACI Frontend JavaScript
 *
 * Plugin: Affiliate Client Integration (Satellite)
 * File: /wp-content/plugins/affiliate-client-integration/assets/js/aci-front.js
 * Author: Richard King, starneconsulting.com
 * Email: r.king@starneconsulting.com
 *
 * Handles frontend affiliate tracking, URL parameter processing, cookie management,
 * and integration with forms and popups. Renamed from affci-front.js to aci-front.js.
 */

(function ($) {
  "use strict";

  /**
   * Main ACI Frontend Controller
   */
  const ACI_Frontend = {
    // Configuration from localized script
    config: window.AFFCI || {},

    // State management
    state: {
      affiliateCode: null,
      affiliateId: null,
      sessionId: null,
      trackingActive: false,
      lastInteraction: null,
    },

    // Cookie settings
    cookies: {
      prefix: "aci_",
      duration: 30, // days
      domain: window.location.hostname,
    },

    /**
     * Initialize frontend tracking
     */
    init: function () {
      console.log("ACI Frontend: Initializing...");

      // Load saved state
      this.loadState();

      // Process URL parameters
      this.processUrlParameters();

      // Initialize tracking
      this.initializeTracking();

      // Bind event handlers
      this.bindEvents();

      // Start session monitoring
      this.startSessionMonitoring();

      console.log("ACI Frontend: Initialized successfully");
    },

    /**
     * Load state from cookies and localStorage
     */
    loadState: function () {
      // Load from cookies
      this.state.affiliateCode = this.getCookie("affiliate_code");
      this.state.affiliateId = this.getCookie("affiliate_id");
      this.state.sessionId = this.getCookie("session_id");

      // Generate session ID if not exists
      if (!this.state.sessionId) {
        this.state.sessionId = this.generateSessionId();
        this.setCookie("session_id", this.state.sessionId, 1); // 1 day
      }

      console.log("ACI Frontend: State loaded", this.state);
    },

    /**
     * Process URL parameters for affiliate tracking
     */
    processUrlParameters: function () {
      const urlParams = new URLSearchParams(window.location.search);
      const affiliateParams = [
        "aff",
        "affiliate",
        "ref",
        "referrer",
        "partner",
        "promo",
      ];

      // Check for affiliate parameters
      for (const param of affiliateParams) {
        const value = urlParams.get(param);
        if (value) {
          console.log(
            `ACI Frontend: Found affiliate parameter ${param}=${value}`
          );
          this.setAffiliateCode(value);
          this.trackAffiliateVisit(value, param);
          break;
        }
      }

      // Store all URL parameters for reference
      const allParams = {};
      urlParams.forEach((value, key) => {
        allParams[key] = value;
      });

      if (Object.keys(allParams).length > 0) {
        this.state.urlParameters = allParams;
        this.triggerEvent("aci:url-parameters-processed", allParams);
      }
    },

    /**
     * Initialize tracking functionality
     */
    initializeTracking: function () {
      if (!this.state.affiliateCode) {
        console.log("ACI Frontend: No affiliate code found, tracking inactive");
        return;
      }

      this.state.trackingActive = true;

      // Track page view
      this.trackPageView();

      // Initialize engagement tracking
      this.initializeEngagementTracking();

      // Track time on page
      this.startTimeTracking();

      console.log(
        "ACI Frontend: Tracking active for affiliate:",
        this.state.affiliateCode
      );
    },

    /**
     * Bind event handlers
     */
    bindEvents: function () {
      const self = this;

      // Affiliate form submissions
      $(document).on(
        "submit",
        ".aci-affiliate-form, [data-aci-form]",
        function (e) {
          e.preventDefault();
          self.handleFormSubmission($(this));
        }
      );

      // Popup triggers
      $(document).on(
        "click",
        ".aci-popup-trigger, [data-aci-popup]",
        function (e) {
          e.preventDefault();
          self.showPopup($(this).data("popup-id"));
        }
      );

      // Code validation inputs
      $(document).on("input", ".aci-code-input", function () {
        const $input = $(this);
        const code = $input.val().trim().toUpperCase();

        if (code.length >= 4) {
          self.validateCodeRealtime(code, $input);
        }
      });

      // Track affiliate link clicks
      $(document).on(
        "click",
        'a[href*="aff="], a[href*="affiliate="], a[href*="ref="]',
        function () {
          const href = $(this).attr("href");
          self.trackLinkClick(href);
        }
      );

      // Track conversions (form submissions, purchases)
      $(document).on(
        "submit",
        "form.checkout, form.purchase, form.order",
        function () {
          if (self.state.trackingActive) {
            self.trackConversion();
          }
        }
      );

      // Listen for custom events
      $(window).on("aci:affiliate-applied", function (e, data) {
        self.handleAffiliateApplied(data);
      });

      $(window).on("aci:discount-applied", function (e, data) {
        self.handleDiscountApplied(data);
      });
    },

    /**
     * Handle form submission
     */
    handleFormSubmission: function ($form) {
      const self = this;
      const $submitBtn = $form.find(
        'button[type="submit"], input[type="submit"]'
      );
      const $message = $form.find(".aci-message, .aci-validation-message");
      const code = $form
        .find('input[name="affiliate_code"], input.aci-code-input')
        .val()
        .trim()
        .toUpperCase();

      if (!code) {
        this.showMessage($message, "error", "Please enter an affiliate code");
        return;
      }

      // Show loading state
      $submitBtn.prop("disabled", true).addClass("loading");
      $message.removeClass("error success").text("Validating...").show();

      // Validate with server
      $.ajax({
        url: this.config.ajaxUrl,
        method: "POST",
        data: {
          action: "aci_validate_code",
          nonce: this.config.nonce,
          code: code,
          url: window.location.href,
          referrer: document.referrer,
        },
        success: function (response) {
          if (response.success && response.data.valid) {
            self.setAffiliateCode(code, response.data.affiliate_id);
            self.showMessage(
              $message,
              "success",
              response.data.message || "Code applied successfully!"
            );

            // Trigger success event
            self.triggerEvent("aci:code-validated", response.data);

            // Redirect if specified
            const redirectUrl =
              $form.data("redirect") || response.data.redirect_url;
            if (redirectUrl) {
              setTimeout(function () {
                window.location.href = redirectUrl;
              }, 1000);
            } else {
              // Reload page to apply discount
              setTimeout(function () {
                window.location.reload();
              }, 1500);
            }
          } else {
            self.showMessage(
              $message,
              "error",
              response.data.message || "Invalid affiliate code"
            );
            $submitBtn.prop("disabled", false).removeClass("loading");
          }
        },
        error: function () {
          self.showMessage(
            $message,
            "error",
            "Connection error. Please try again."
          );
          $submitBtn.prop("disabled", false).removeClass("loading");
        },
      });
    },

    /**
     * Validate code in real-time
     */
    validateCodeRealtime: function (code, $input) {
      const self = this;

      // Clear previous timeout
      if (this.validateTimeout) {
        clearTimeout(this.validateTimeout);
      }

      // Debounce validation
      this.validateTimeout = setTimeout(function () {
        $.ajax({
          url: self.config.ajaxUrl,
          method: "POST",
          data: {
            action: "aci_validate_code",
            nonce: self.config.nonce,
            code: code,
          },
          success: function (response) {
            if (response.success && response.data.valid) {
              $input.addClass("valid").removeClass("invalid");
              self.showInputFeedback($input, "Valid code!", "success");
            } else {
              $input.addClass("invalid").removeClass("valid");
              self.showInputFeedback($input, "Invalid code", "error");
            }
          },
        });
      }, 500);
    },

    /**
     * Show input feedback
     */
    showInputFeedback: function ($input, message, type) {
      let $feedback = $input.siblings(".aci-input-feedback");

      if (!$feedback.length) {
        $feedback = $('<div class="aci-input-feedback"></div>');
        $input.after($feedback);
      }

      $feedback
        .removeClass("success error")
        .addClass(type)
        .text(message)
        .fadeIn();
    },

    /**
     * Set affiliate code
     */
    setAffiliateCode: function (code, affiliateId) {
      this.state.affiliateCode = code.toUpperCase();
      this.state.affiliateId = affiliateId || null;

      // Store in cookies
      this.setCookie(
        "affiliate_code",
        this.state.affiliateCode,
        this.cookies.duration
      );
      if (affiliateId) {
        this.setCookie("affiliate_id", affiliateId, this.cookies.duration);
      }

      // Update tracking state
      if (!this.state.trackingActive) {
        this.initializeTracking();
      }

      console.log(
        "ACI Frontend: Affiliate code set:",
        this.state.affiliateCode
      );
    },

    /**
     * Track affiliate visit
     */
    trackAffiliateVisit: function (code, source) {
      $.ajax({
        url: this.config.ajaxUrl,
        method: "POST",
        data: {
          action: "aci_track_visit",
          nonce: this.config.nonce,
          code: code,
          source: source,
          url: window.location.href,
          referrer: document.referrer,
          session_id: this.state.sessionId,
        },
      });
    },

    /**
     * Track page view
     */
    trackPageView: function () {
      if (!this.state.trackingActive) return;

      $.ajax({
        url: this.config.ajaxUrl,
        method: "POST",
        data: {
          action: "aci_track_pageview",
          nonce: this.config.nonce,
          affiliate_code: this.state.affiliateCode,
          affiliate_id: this.state.affiliateId,
          url: window.location.href,
          title: document.title,
          session_id: this.state.sessionId,
        },
      });
    },

    /**
     * Track conversion
     */
    trackConversion: function () {
      const conversionData = {
        action: "aci_track_conversion",
        nonce: this.config.nonce,
        affiliate_code: this.state.affiliateCode,
        affiliate_id: this.state.affiliateId,
        session_id: this.state.sessionId,
        url: window.location.href,
        timestamp: Date.now(),
      };

      // Send immediately using sendBeacon for reliability
      const formData = new FormData();
      Object.keys(conversionData).forEach((key) => {
        formData.append(key, conversionData[key]);
      });

      if (navigator.sendBeacon) {
        navigator.sendBeacon(this.config.ajaxUrl, formData);
      } else {
        $.ajax({
          url: this.config.ajaxUrl,
          method: "POST",
          data: conversionData,
          async: false,
        });
      }

      console.log("ACI Frontend: Conversion tracked");
    },

    /**
     * Track link click
     */
    trackLinkClick: function (href) {
      if (!this.state.trackingActive) return;

      $.ajax({
        url: this.config.ajaxUrl,
        method: "POST",
        data: {
          action: "aci_track_click",
          nonce: this.config.nonce,
          affiliate_code: this.state.affiliateCode,
          link_url: href,
          page_url: window.location.href,
          session_id: this.state.sessionId,
        },
      });
    },

    /**
     * Initialize engagement tracking
     */
    initializeEngagementTracking: function () {
      const self = this;

      // Track scroll depth
      let maxScroll = 0;
      $(window).on(
        "scroll",
        this.debounce(function () {
          const scrollPercent = Math.round(
            ($(window).scrollTop() /
              ($(document).height() - $(window).height())) *
              100
          );
          if (scrollPercent > maxScroll) {
            maxScroll = scrollPercent;
            if (maxScroll % 25 === 0) {
              // Track at 25%, 50%, 75%, 100%
              self.trackEngagement("scroll", maxScroll);
            }
          }
        }, 500)
      );

      // Track time milestones
      const timeMilestones = [30, 60, 120, 300]; // seconds
      timeMilestones.forEach((milestone) => {
        setTimeout(() => {
          if (self.state.trackingActive) {
            self.trackEngagement("time", milestone);
          }
        }, milestone * 1000);
      });
    },

    /**
     * Track engagement event
     */
    trackEngagement: function (type, value) {
      $.ajax({
        url: this.config.ajaxUrl,
        method: "POST",
        data: {
          action: "aci_track_engagement",
          nonce: this.config.nonce,
          affiliate_code: this.state.affiliateCode,
          engagement_type: type,
          engagement_value: value,
          url: window.location.href,
          session_id: this.state.sessionId,
        },
      });
    },

    /**
     * Start time tracking
     */
    startTimeTracking: function () {
      this.timeOnPage = 0;
      this.timeTracker = setInterval(() => {
        this.timeOnPage += 10;
      }, 10000); // Track every 10 seconds

      // Send time on page before leaving
      $(window).on("beforeunload", () => {
        if (this.state.trackingActive) {
          this.trackEngagement("total_time", this.timeOnPage);
        }
      });
    },

    /**
     * Start session monitoring
     */
    startSessionMonitoring: function () {
      const self = this;

      // Update last activity timestamp
      $(document).on(
        "click keypress scroll",
        this.debounce(function () {
          self.state.lastInteraction = Date.now();
        }, 1000)
      );

      // Check for session timeout (30 minutes of inactivity)
      setInterval(function () {
        if (self.state.lastInteraction) {
          const inactive = Date.now() - self.state.lastInteraction;
          if (inactive > 1800000) {
            // 30 minutes
            self.endSession();
          }
        }
      }, 60000); // Check every minute
    },

    /**
     * End session
     */
    endSession: function () {
      if (this.timeTracker) {
        clearInterval(this.timeTracker);
      }

      if (this.state.trackingActive) {
        this.trackEngagement("session_end", this.timeOnPage);
      }

      console.log("ACI Frontend: Session ended");
    },

    /**
     * Show popup
     */
    showPopup: function (popupId) {
      const $popup = $("#" + (popupId || "aci-popup-container"));
      if ($popup.length) {
        $popup.fadeIn(300);
        $popup.find('input[type="text"]').first().focus();

        this.triggerEvent("aci:popup-shown", { popupId: popupId });
      }
    },

    /**
     * Show message in element
     */
    showMessage: function ($element, type, message) {
      $element
        .removeClass("error success info")
        .addClass(type)
        .html(message)
        .fadeIn();
    },

    /**
     * Cookie management
     */
    setCookie: function (name, value, days) {
      const expires = new Date();
      expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
      document.cookie = `${
        this.cookies.prefix
      }${name}=${value};expires=${expires.toUTCString()};path=/;domain=${
        this.cookies.domain
      }`;
    },

    getCookie: function (name) {
      const nameEQ = `${this.cookies.prefix}${name}=`;
      const ca = document.cookie.split(";");
      for (let i = 0; i < ca.length; i++) {
        let c = ca[i].trim();
        if (c.indexOf(nameEQ) === 0) {
          return c.substring(nameEQ.length);
        }
      }
      return null;
    },

    /**
     * Generate unique session ID
     */
    generateSessionId: function () {
      return (
        "aci_" + Date.now() + "_" + Math.random().toString(36).substr(2, 9)
      );
    },

    /**
     * Trigger custom event
     */
    triggerEvent: function (eventName, data) {
      $(window).trigger(eventName, [data]);
      console.log("ACI Frontend: Event triggered:", eventName, data);
    },

    /**
     * Handle affiliate applied event
     */
    handleAffiliateApplied: function (data) {
      console.log("ACI Frontend: Affiliate applied", data);

      // Update display elements
      $(".aci-affiliate-display").text(data.code).fadeIn();
      $(".aci-discount-amount").text(
        data.discount_value + (data.discount_type === "percentage" ? "%" : "")
      );

      // Show success notifications
      this.showNotification("Affiliate code applied successfully!", "success");
    },

    /**
     * Handle discount applied event
     */
    handleDiscountApplied: function (data) {
      console.log("ACI Frontend: Discount applied", data);

      // Update price displays
      if (data.original_price && data.discounted_price) {
        $(".aci-original-price").text(data.original_price);
        $(".aci-discounted-price").text(data.discounted_price);
        $(".aci-savings-amount").text(data.savings);
      }
    },

    /**
     * Show notification
     */
    showNotification: function (message, type) {
      const $notification = $(`
                <div class="aci-notification aci-${type}">
                    <span class="aci-notification-icon">✓</span>
                    <span class="aci-notification-message">${message}</span>
                    <button class="aci-notification-close">×</button>
                </div>
            `);

      $("body").append($notification);

      setTimeout(() => {
        $notification.addClass("show");
      }, 100);

      $notification.find(".aci-notification-close").on("click", function () {
        $notification.removeClass("show");
        setTimeout(() => $notification.remove(), 300);
      });

      setTimeout(() => {
        $notification.removeClass("show");
        setTimeout(() => $notification.remove(), 300);
      }, 5000);
    },

    /**
     * Debounce helper
     */
    debounce: function (func, wait) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    },
  };

  /**
   * Initialize on document ready
   */
  $(document).ready(function () {
    ACI_Frontend.init();
  });

  // Expose to global scope for external access
  window.ACI_Frontend = ACI_Frontend;
})(jQuery);