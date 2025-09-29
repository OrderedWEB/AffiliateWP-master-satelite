/**
 * Affiliate Client Full - JavaScript Tracking
 *
 * Handles client-side tracking functionality including
 * page views, clicks, form submissions, and custom events.
 *
 * @package AffiliateClientFull
 * @version 1.0.0
 */

(function ($) {
  "use strict";

  // Main tracking object
  window.AffiliateClientTracker = {
    // Configuration
    config: {},

    // State
    initialized: false,
    sessionData: {},
    queue: [],

    /**
     * Initialize tracker
     */
    init: function (config) {
      this.config = $.extend(
        {
          ajaxUrl: "",
          restUrl: "",
          nonce: "",
          trackingEnabled: true,
          debug: false,
          cookieDomain: "",
          cookieExpiry: 30 * 24 * 60 * 60 * 1000, // 30 days
          trackingEvents: ["page_view", "click", "form_submit"],
        },
        config || {}
      );

      if (!this.config.trackingEnabled) {
        this.log("Tracking disabled");
        return;
      }

      this.sessionData = {
        affiliateId: this.config.affiliateId || null,
        visitId: this.config.visitId || this.generateVisitId(),
        pageUrl: this.config.pageUrl || window.location.href,
        pageTitle: this.config.pageTitle || document.title,
        timestamp: this.config.timestamp || Math.floor(Date.now() / 1000),
      };

      this.setupEventListeners();
      this.trackPageView();
      this.processQueue();

      this.initialized = true;
      this.log("Tracker initialized", this.sessionData);

      // Fire initialisation event
      $(document).trigger("affiliate_client_tracker_ready", [this]);
    },

    /**
     * Setup event listeners
     */
    setupEventListeners: function () {
      var self = this;

      // Track clicks on tracked elements
      $('a, button, [data-affiliate-track="click"]').on("click", function (e) {
        self.trackClick(this, e);
      });

      // Track form submissions
      $("form").on("submit", function (e) {
        self.trackFormSubmit(this, e);
      });

      // Track add to cart events (WooCommerce)
      $(document).on(
        "added_to_cart",
        function (event, fragments, cart_hash, button) {
          self.trackEvent("wc_add_to_cart", {
            product_id: button.data("product_id"),
            quantity: button.data("quantity") || 1,
            cart_hash: cart_hash,
          });
        }
      );

      // Track EDD add to cart
      $(document).on("edd_cart_item_added", function (event, response) {
        self.trackEvent("edd_add_to_cart", {
          download_id: response.download_id,
          cart_total: response.cart_total,
        });
      });

      // Track video engagement
      $("video").on("play pause ended", function (e) {
        self.trackEvent("video_" + e.type, {
          video_src: this.src || this.currentSrc,
          current_time: this.currentTime,
          duration: this.duration,
        });
      });

      // Track scroll depth
      this.setupScrollTracking();

      // Track time on page
      this.setupTimeTracking();

      // Track outbound links
      this.setupOutboundLinkTracking();
    },

    /**
     * Track page view
     */
    trackPageView: function () {
      if (!this.isEventEnabled("page_view")) {
        return;
      }

      var data = {
        url: this.sessionData.pageUrl,
        title: this.sessionData.pageTitle,
        referrer: document.referrer,
        screen_resolution: screen.width + "x" + screen.height,
        viewport_size: $(window).width() + "x" + $(window).height(),
        user_agent: navigator.userAgent,
        language: navigator.language,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
      };

      this.trackEvent("page_view", data);
    },

    /**
     * Track click event
     */
    trackClick: function (element, event) {
      if (!this.isEventEnabled("click")) {
        return;
      }

      var $element = $(element);
      var data = {
        element_type: element.tagName.toLowerCase(),
        element_text: $element.text().trim().substring(0, 100),
        element_id: element.id || "",
        element_class: element.className || "",
        href: element.href || "",
        position: this.getElementPosition($element),
        page_url: window.location.href,
      };

      // Check if it's a special tracking element
      if ($element.data("affiliate-track")) {
        data.track_type = $element.data("affiliate-track");
        data.track_data = $element.data("affiliate-data") || {};
      }

      this.trackEvent("click", data);
    },

    /**
     * Track form submission
     */
    trackFormSubmit: function (form, event) {
      if (!this.isEventEnabled("form_submit")) {
        return;
      }

      var $form = $(form);
      var data = {
        form_id: form.id || "",
        form_action: form.action || "",
        form_method: form.method || "get",
        form_class: form.className || "",
        field_count: $form.find("input, select, textarea").length,
        page_url: window.location.href,
      };

      // Capture form data (non-sensitive fields only)
      var formData = {};
      $form.find("input, select, textarea").each(function () {
        var $field = $(this);
        var name = this.name;
        var type = this.type;

        // Skip sensitive fields
        if (name && !this.isPasswordField(type, name)) {
          var value = $field.val();
          if (value && value.length <= 100) {
            formData[name] = value;
          }
        }
      });

      data.form_data = formData;

      this.trackEvent("form_submit", data);
    },

    /**
     * Track custom event
     */
    trackEvent: function (eventType, eventData) {
      if (!this.config.trackingEnabled) {
        return;
      }

      var data = $.extend(
        {
          event_type: eventType,
          affiliate_id: this.sessionData.affiliateId,
          visit_id: this.sessionData.visitId,
          timestamp: Math.floor(Date.now() / 1000),
          page_url: window.location.href,
          page_title: document.title,
        },
        eventData || {}
      );

      if (!this.initialized) {
        this.queue.push(data);
        return;
      }

      this.sendTrackingData(data);
    },

    /**
     * Send tracking data to server
     */
    sendTrackingData: function (data) {
      var self = this;

      // Try REST API first, fallback to AJAX
      if (this.config.restUrl) {
        this.sendViaRest(data).fail(function () {
          self.sendViaAjax(data);
        });
      } else {
        this.sendViaAjax(data);
      }
    },

    /**
     * Send data via REST API
     */
    sendViaRest: function (data) {
      var self = this;

      return $.ajax({
        url: this.config.restUrl + "track",
        method: "POST",
        contentType: "application/json",
        data: JSON.stringify({
          event: data.event_type,
          data: data,
        }),
        timeout: 5000,
      })
        .done(function (response) {
          self.log("Event tracked via REST", data.event_type, response);
          $(document).trigger("affiliate_client_event_tracked", [
            data,
            response,
          ]);
        })
        .fail(function (xhr, status, error) {
          self.log("REST tracking failed", error);
        });
    },

    /**
     * Send data via AJAX
     */
    sendViaAjax: function (data) {
      var self = this;

      $.ajax({
        url: this.config.ajaxUrl,
        method: "POST",
        data: {
          action: "affiliate_client_track_event",
          nonce: this.config.nonce,
          event_type: data.event_type,
          data: data,
        },
        timeout: 5000,
      })
        .done(function (response) {
          self.log("Event tracked via AJAX", data.event_type, response);
          $(document).trigger("affiliate_client_event_tracked", [
            data,
            response,
          ]);
        })
        .fail(function (xhr, status, error) {
          self.log("AJAX tracking failed", error);
        });
    },

    /**
     * Setup scroll depth tracking
     */
    setupScrollTracking: function () {
      var self = this;
      var tracked = {};
      var thresholds = [25, 50, 75, 90, 100];

      $(window).on(
        "scroll",
        $.throttle(250, function () {
          var scrollTop = $(window).scrollTop();
          var documentHeight = $(document).height();
          var windowHeight = $(window).height();
          var scrollPercent = Math.round(
            (scrollTop / (documentHeight - windowHeight)) * 100
          );

          thresholds.forEach(function (threshold) {
            if (scrollPercent >= threshold && !tracked[threshold]) {
              tracked[threshold] = true;
              self.trackEvent("scroll_depth", {
                depth_percent: threshold,
                scroll_top: scrollTop,
                page_height: documentHeight,
              });
            }
          });
        })
      );
    },

    /**
     * Setup time on page tracking
     */
    setupTimeTracking: function () {
      var self = this;
      var startTime = Date.now();
      var intervals = [30, 60, 120, 300]; // seconds
      var tracked = {};

      intervals.forEach(function (interval) {
        setTimeout(function () {
          if (!tracked[interval]) {
            tracked[interval] = true;
            self.trackEvent("time_on_page", {
              duration_seconds: interval,
              total_time: Math.floor((Date.now() - startTime) / 1000),
            });
          }
        }, interval * 1000);
      });

      // Track when user leaves
      $(window).on("beforeunload", function () {
        var totalTime = Math.floor((Date.now() - startTime) / 1000);

        // Use navigator.sendBeacon for better reliability
        if (navigator.sendBeacon && self.config.restUrl) {
          var data = JSON.stringify({
            event: "page_exit",
            data: {
              event_type: "page_exit",
              duration_seconds: totalTime,
              affiliate_id: self.sessionData.affiliateId,
              visit_id: self.sessionData.visitId,
              page_url: window.location.href,
            },
          });
          navigator.sendBeacon(self.config.restUrl + "track", data);
        }
      });
    },

    /**
     * Setup outbound link tracking
     */
    setupOutboundLinkTracking: function () {
      var self = this;
      var currentDomain = window.location.hostname;

      $('a[href^="http"]').on("click", function (e) {
        var link = this;
        var href = link.href;
        var linkDomain = this.hostname;

        if (linkDomain !== currentDomain) {
          self.trackEvent("outbound_link", {
            url: href,
            domain: linkDomain,
            text: $(link).text().trim().substring(0, 100),
          });
        }
      });
    },

    /**
     * Process queued events
     */
    processQueue: function () {
      var self = this;

      this.queue.forEach(function (data) {
        self.sendTrackingData(data);
      });

      this.queue = [];
    },

    /**
     * Check if event type is enabled
     */
    isEventEnabled: function (eventType) {
      return this.config.trackingEvents.indexOf(eventType) !== -1;
    },

    /**
     * Check if field is password type
     */
    isPasswordField: function (type, name) {
      var sensitiveTypes = ["password", "hidden"];
      var sensitiveNames = [
        "password",
        "pass",
        "pwd",
        "ssn",
        "social",
        "credit",
        "card",
        "cvv",
      ];

      if (sensitiveTypes.indexOf(type) !== -1) {
        return true;
      }

      name = name.toLowerCase();
      return sensitiveNames.some(function (sensitive) {
        return name.indexOf(sensitive) !== -1;
      });
    },

    /**
     * Get element position on page
     */
    getElementPosition: function ($element) {
      try {
        var offset = $element.offset();
        return {
          x: Math.round(offset.left),
          y: Math.round(offset.top),
        };
      } catch (e) {
        return { x: 0, y: 0 };
      }
    },

    /**
     * Generate unique visit ID
     */
    generateVisitId: function () {
      return (
        "visit_" + Date.now() + "_" + Math.random().toString(36).substr(2, 9)
      );
    },

    /**
     * Log debug messages
     */
    log: function () {
      if (this.config.debug && window.console && console.log) {
        var args = Array.prototype.slice.call(arguments);
        args.unshift("[Affiliate Client Tracker]");
        console.log.apply(console, args);
      }
    },

    /**
     * Set affiliate ID manually
     */
    setAffiliateId: function (affiliateId) {
      this.sessionData.affiliateId = affiliateId;

      // Update cookie
      this.setCookie(
        "affiliate_client_ref",
        affiliateId,
        this.config.cookieExpiry
      );

      this.log("Affiliate ID set", affiliateId);
    },

    /**
     * Get current affiliate ID
     */
    getAffiliateId: function () {
      return this.sessionData.affiliateId;
    },

    /**
     * Clear affiliate tracking
     */
    clearAffiliate: function () {
      this.sessionData.affiliateId = null;
      this.deleteCookie("affiliate_client_ref");
      this.log("Affiliate tracking cleared");
    },

    /**
     * Set cookie
     */
    setCookie: function (name, value, expiry) {
      var expires = new Date();
      expires.setTime(expires.getTime() + expiry);

      var cookieString =
        name +
        "=" +
        encodeURIComponent(value) +
        "; expires=" +
        expires.toUTCString() +
        "; path=/";

      if (this.config.cookieDomain) {
        cookieString += "; domain=" + this.config.cookieDomain;
      }

      if (window.location.protocol === "https:") {
        cookieString += "; secure";
      }

      document.cookie = cookieString;
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
     * Delete cookie
     */
    deleteCookie: function (name) {
      this.setCookie(name, "", -1);
    },

    /**
     * Track conversion
     */
    trackConversion: function (amount, reference, data) {
      var conversionData = $.extend(
        {
          event_type: "conversion",
          amount: parseFloat(amount) || 0,
          reference: reference || "",
          affiliate_id: this.sessionData.affiliateId,
          visit_id: this.sessionData.visitId,
          currency: this.detectCurrency(),
          timestamp: Math.floor(Date.now() / 1000),
        },
        data || {}
      );

      this.trackEvent("conversion", conversionData);

      // Also send to conversion endpoint
      if (window.AffiliateClientConversion) {
        window.AffiliateClientConversion.track(amount, reference, data);
      }
    },

    /**
     * Detect currency from page
     */
    detectCurrency: function () {
      // Try to detect currency from common selectors
      var currencySelectors = [
        ".woocommerce-Price-currencySymbol",
        ".edd-cart-meta .currency",
        "[data-currency]",
        ".currency",
      ];

      for (var i = 0; i < currencySelectors.length; i++) {
        var $element = $(currencySelectors[i]).first();
        if ($element.length > 0) {
          var currency = $element.text().trim() || $element.data("currency");
          if (currency) {
            return currency;
          }
        }
      }

      return "USD"; // Default
    },

    /**
     * Enable debug mode
     */
    enableDebug: function () {
      this.config.debug = true;
      this.log("Debug mode enabled");
    },

    /**
     * Disable debug mode
     */
    disableDebug: function () {
      this.config.debug = false;
    },

    /**
     * Get tracking statistics
     */
    getStats: function () {
      return {
        initialized: this.initialized,
        sessionData: this.sessionData,
        queueLength: this.queue.length,
        config: this.config,
      };
    },

    /**
     * Manually trigger page view tracking
     */
    trackPageViewManual: function (url, title) {
      this.sessionData.pageUrl = url || window.location.href;
      this.sessionData.pageTitle = title || document.title;
      this.trackPageView();
    },

    /**
     * Track custom user action
     */
    trackUserAction: function (action, data) {
      this.trackEvent(
        "user_action",
        $.extend(
          {
            action: action,
          },
          data || {}
        )
      );
    },

    /**
     * Track file download
     */
    trackDownload: function (url, filename) {
      this.trackEvent("download", {
        file_url: url,
        filename: filename || this.getFilenameFromUrl(url),
        file_extension: this.getFileExtension(url),
      });
    },

    /**
     * Get filename from URL
     */
    getFilenameFromUrl: function (url) {
      try {
        return url.split("/").pop().split("?")[0];
      } catch (e) {
        return "";
      }
    },

    /**
     * Get file extension
     */
    getFileExtension: function (url) {
      try {
        var filename = this.getFilenameFromUrl(url);
        return filename.split(".").pop().toLowerCase();
      } catch (e) {
        return "";
      }
    },

    /**
     * Track external API interaction
     */
    trackApiCall: function (endpoint, method, success) {
      this.trackEvent("api_call", {
        endpoint: endpoint,
        method: method || "GET",
        success: !!success,
        timestamp: Math.floor(Date.now() / 1000),
      });
    },

    /**
     * Track error
     */
    trackError: function (error, context) {
      this.trackEvent("error", {
        error_message: error.toString(),
        error_stack: error.stack || "",
        context: context || "javascript",
        user_agent: navigator.userAgent,
        page_url: window.location.href,
      });
    },

    /**
     * Track search
     */
    trackSearch: function (query, results) {
      this.trackEvent("search", {
        search_query: query,
        results_count: results || 0,
        page_url: window.location.href,
      });
    },

    /**
     * Track social share
     */
    trackSocialShare: function (platform, url) {
      this.trackEvent("social_share", {
        platform: platform,
        shared_url: url || window.location.href,
        page_title: document.title,
      });
    },
  };

  // jQuery throttle function (simple implementation)
  $.throttle = function (delay, func) {
    var timeout;
    var lastExec = 0;

    return function () {
      var context = this;
      var args = arguments;
      var elapsed = Date.now() - lastExec;

      function exec() {
        lastExec = Date.now();
        func.apply(context, args);
      }

      if (elapsed > delay) {
        exec();
      } else {
        clearTimeout(timeout);
        timeout = setTimeout(exec, delay - elapsed);
      }
    };
  };

  // Auto-initialize if config is available
  $(document).ready(function () {
    if (typeof affiliateClientConfig !== "undefined") {
      AffiliateClientTracker.init(affiliateClientConfig);
    }
  });

  // Global error tracking
  window.addEventListener("error", function (e) {
    if (window.AffiliateClientTracker && AffiliateClientTracker.initialized) {
      AffiliateClientTracker.trackError(
        e.error || new Error(e.message),
        "global"
      );
    }
  });

  // Unhandled promise rejection tracking
  window.addEventListener("unhandledrejection", function (e) {
    if (window.AffiliateClientTracker && AffiliateClientTracker.initialized) {
      AffiliateClientTracker.trackError(e.reason, "promise");
    }
  });

  // Track downloads automatically
  $(document).on(
    "click",
    'a[href$=".pdf"], a[href$=".doc"], a[href$=".docx"], a[href$=".xls"], a[href$=".xlsx"], a[href$=".zip"], a[href$=".rar"]',
    function () {
      if (window.AffiliateClientTracker && AffiliateClientTracker.initialized) {
        AffiliateClientTracker.trackDownload(this.href);
      }
    }
  );

  // Track email links
  $(document).on("click", 'a[href^="mailto:"]', function () {
    if (window.AffiliateClientTracker && AffiliateClientTracker.initialized) {
      AffiliateClientTracker.trackEvent("email_click", {
        email: this.href.replace("mailto:", ""),
        page_url: window.location.href,
      });
    }
  });

  // Track phone links
  $(document).on("click", 'a[href^="tel:"]', function () {
    if (window.AffiliateClientTracker && AffiliateClientTracker.initialized) {
      AffiliateClientTracker.trackEvent("phone_click", {
        phone: this.href.replace("tel:", ""),
        page_url: window.location.href,
      });
    }
  });

  // Track social media shares
  $(document).on("click", "[data-social-share]", function () {
    if (window.AffiliateClientTracker && AffiliateClientTracker.initialized) {
      var platform = $(this).data("social-share") || "unknown";
      AffiliateClientTracker.trackSocialShare(platform);
    }
  });

  // Expose to global scope
  window.AffiliateClientTracker = AffiliateClientTracker;
})(jQuery);