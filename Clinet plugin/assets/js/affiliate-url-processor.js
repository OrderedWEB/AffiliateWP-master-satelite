/**
 * Affiliate URL Processor
 * File: /wp-content/plugins/affiliate-client-integration/assets/js/affiliate-url-processor.js
 * Plugin: Affiliate Client Integration
 */

(function ($) {
  "use strict";

  window.ACI = window.ACI || {};

  /**
   * URL Processor Class
   */
  ACI.URLProcessor = {
    // Configuration
    config: {
      affiliateParam: "aff",
      alternativeParams: ["affiliate", "ref", "referrer", "partner"],
      sessionExpiry: 24 * 60 * 60 * 1000, // 24 hours
      cookieExpiry: 30, // 30 days
      trackingEndpoint: aci_ajax.ajax_url,
      validateEndpoint: aci_ajax.ajax_url,
    },

    // Current affiliate data
    currentAffiliate: null,
    isProcessing: false,

    /**
     * Initialse URL processor
     */
    init: function () {
      this.loadConfig();
      this.processCurrentURL();
      this.bindEvents();
      this.startPeriodicValidation();

      console.log("ACI URL Processor initialized");
    },

    /**
     * Load configuration from server
     */
    loadConfig: function () {
      if (typeof aci_config !== "undefined") {
        this.config = $.extend(this.config, aci_config);
      }
    },

    /**
     * Process current URL for affiliate parameters
     */
    processCurrentURL: function () {
      const urlParams = new URLSearchParams(window.location.search);
      const hashParams = this.parseHashParams();
      let affiliateCode = null;

      // Check URL parameters
      for (const param of [
        this.config.affiliateParam,
        ...this.config.alternativeParams,
      ]) {
        if (urlParams.has(param)) {
          affiliateCode = urlParams.get(param);
          break;
        }
        if (hashParams[param]) {
          affiliateCode = hashParams[param];
          break;
        }
      }

      // Check for affiliate code in path
      if (!affiliateCode) {
        affiliateCode = this.extractAffiliateFromPath();
      }

      // Process affiliate code if found
      if (affiliateCode) {
        this.processAffiliateCode(affiliateCode, {
          source: "url",
          url: window.location.href,
          referrer: document.referrer,
        });
      } else {
        // Check for existing stored affiliate
        this.loadStoredAffiliate();
      }
    },

    /**
     * Parse hash parameters
     */
    parseHashParams: function () {
      const hash = window.location.hash.substring(1);
      const params = {};

      if (hash) {
        const pairs = hash.split("&");
        for (const pair of pairs) {
          const [key, value] = pair.split("=");
          if (key && value) {
            params[decodeURIComponent(key)] = decodeURIComponent(value);
          }
        }
      }

      return params;
    },

    /**
     * Extract affiliate code from URL path
     */
    extractAffiliateFromPath: function () {
      const pathPatterns = [
        /\/aff\/([^\/]+)/i,
        /\/affiliate\/([^\/]+)/i,
        /\/ref\/([^\/]+)/i,
        /\/partner\/([^\/]+)/i,
      ];

      const path = window.location.pathname;

      for (const pattern of pathPatterns) {
        const match = path.match(pattern);
        if (match && match[1]) {
          return match[1];
        }
      }

      return null;
    },

    /**
     * Process affiliate code
     */
    processAffiliateCode: function (code, metadata = {}) {
      if (this.isProcessing || !code) return;

      this.isProcessing = true;

      // Sanitise code
      code = this.SanitiseAffiliateCode(code);
      if (!code) {
        this.isProcessing = false;
        return;
      }

      // Validate with server
      this.validateAffiliateCode(code)
        .then((response) => {
          if (response.success && response.data.valid) {
            this.setAffiliateData(code, response.data, metadata);
            this.trackAffiliateVisit(code, metadata);
            this.triggerAffiliateEvents("affiliate_set", {
              code: code,
              data: response.data,
              metadata: metadata,
            });
          } else {
            console.warn("Invalid affiliate code:", code);
            this.triggerAffiliateEvents("affiliate_invalid", { code: code });
          }
        })
        .catch((error) => {
          console.error("Affiliate validation error:", error);
          // Store temporarily and retry later
          this.storeTemporaryAffiliate(code, metadata);
        })
        .finally(() => {
          this.isProcessing = false;
        });
    },

    /**
     * Sanitise affiliate code
     */
    SanitiseAffiliateCode: function (code) {
      if (typeof code !== "string") return null;

      // Remove whitespace and convert to lowercase
      code = code.trim().toLowerCase();

      // Check length (reasonable bounds)
      if (code.length < 2 || code.length > 50) return null;

      // Allow only alphanumeric, hyphens, underscores
      if (!/^[a-z0-9_-]+$/.test(code)) return null;

      return code;
    },

    /**
     * Validate affiliate code with server
     */
    validateAffiliateCode: function (code) {
      return new Promise((resolve, reject) => {
        $.ajax({
          url: this.config.validateEndpoint,
          type: "POST",
          data: {
            action: "aci_validate_affiliate",
            affiliate_code: code,
            nonce: aci_ajax.nonce,
            url: window.location.href,
            referrer: document.referrer,
          },
          timeout: 10000,
          success: function (response) {
            resolve(response);
          },
          error: function (xhr, status, error) {
            reject(new Error(`Validation failed: ${error}`));
          },
        });
      });
    },

    /**
     * Set affiliate data in storage
     */
    setAffiliateData: function (code, serverData, metadata) {
      const affiliateData = {
        code: code,
        timestamp: Date.now(),
        expires: Date.now() + this.config.sessionExpiry,
        serverData: serverData,
        metadata: metadata,
        visits: 1,
        lastActivity: Date.now(),
      };

      // Store in multiple places for reliability
      this.storeInSessionStorage(affiliateData);
      this.storeInLocalStorage(affiliateData);
      this.storeInCookie(code);

      this.currentAffiliate = affiliateData;

      // Clean URL if configured
      if (this.config.cleanUrl !== false) {
        this.cleanURL();
      }
    },

    /**
     * Store in session storage
     */
    storeInSessionStorage: function (data) {
      try {
        sessionStorage.setItem("aci_affiliate", JSON.stringify(data));
      } catch (e) {
        console.warn("Failed to store in sessionStorage:", e);
      }
    },

    /**
     * Store in local storage
     */
    storeInLocalStorage: function (data) {
      try {
        localStorage.setItem("aci_affiliate", JSON.stringify(data));
      } catch (e) {
        console.warn("Failed to store in localStorage:", e);
      }
    },

    /**
     * Store in cookie
     */
    storeInCookie: function (code) {
      try {
        const expires = new Date();
        expires.setDate(expires.getDate() + this.config.cookieExpiry);

        document.cookie = `aci_aff=${encodeURIComponent(
          code
        )}; expires=${expires.toUTCString()}; path=/; SameSite=Lax`;
      } catch (e) {
        console.warn("Failed to store in cookie:", e);
      }
    },

    /**
     * Load stored affiliate data
     */
    loadStoredAffiliate: function () {
      let affiliateData = null;

      // Try session storage first
      try {
        const stored = sessionStorage.getItem("aci_affiliate");
        if (stored) {
          affiliateData = JSON.parse(stored);
        }
      } catch (e) {
        console.warn("Failed to read from sessionStorage:", e);
      }

      // Fallback to local storage
      if (!affiliateData) {
        try {
          const stored = localStorage.getItem("aci_affiliate");
          if (stored) {
            affiliateData = JSON.parse(stored);
          }
        } catch (e) {
          console.warn("Failed to read from localStorage:", e);
        }
      }

      // Fallback to cookie
      if (!affiliateData) {
        const cookieCode = this.getAffiliateCookie();
        if (cookieCode) {
          affiliateData = {
            code: cookieCode,
            timestamp: Date.now(),
            expires: Date.now() + this.config.sessionExpiry,
            source: "cookie",
          };
        }
      }

      // Validate and set if found
      if (affiliateData && this.isValidAffiliateData(affiliateData)) {
        this.currentAffiliate = affiliateData;
        this.updateLastActivity();
        this.triggerAffiliateEvents("affiliate_loaded", affiliateData);
      }
    },

    /**
     * Get affiliate code from cookie
     */
    getAffiliateCookie: function () {
      const cookies = document.cookie.split(";");
      for (let cookie of cookies) {
        const [name, value] = cookie.trim().split("=");
        if (name === "aci_aff") {
          return decodeURIComponent(value);
        }
      }
      return null;
    },

    /**
     * Validate affiliate data
     */
    isValidAffiliateData: function (data) {
      if (!data || typeof data !== "object") return false;
      if (!data.code || typeof data.code !== "string") return false;
      if (!data.expires || data.expires < Date.now()) return false;

      return true;
    },

    /**
     * Store temporary affiliate for retry
     */
    storeTemporaryAffiliate: function (code, metadata) {
      const tempData = {
        code: code,
        metadata: metadata,
        timestamp: Date.now(),
        retryCount: 0,
      };

      try {
        sessionStorage.setItem("aci_temp_affiliate", JSON.stringify(tempData));
        // Schedule retry
        setTimeout(() => this.retryTemporaryAffiliate(), 5000);
      } catch (e) {
        console.warn("Failed to store temporary affiliate:", e);
      }
    },

    /**
     * Retry temporary affiliate validation
     */
    retryTemporaryAffiliate: function () {
      try {
        const stored = sessionStorage.getItem("aci_temp_affiliate");
        if (!stored) return;

        const tempData = JSON.parse(stored);

        // Limit retry attempts
        if (tempData.retryCount >= 3) {
          sessionStorage.removeItem("aci_temp_affiliate");
          return;
        }

        tempData.retryCount++;
        sessionStorage.setItem("aci_temp_affiliate", JSON.stringify(tempData));

        // Retry validation
        this.processAffiliateCode(tempData.code, tempData.metadata);
      } catch (e) {
        console.warn("Failed to retry temporary affiliate:", e);
      }
    },

    /**
     * Track affiliate visit
     */
    trackAffiliateVisit: function (code, metadata) {
      $.ajax({
        url: this.config.trackingEndpoint,
        type: "POST",
        data: {
          action: "aci_track_visit",
          affiliate_code: code,
          nonce: aci_ajax.nonce,
          url: window.location.href,
          referrer: document.referrer,
          metadata: JSON.stringify(metadata),
        },
        success: function (response) {
          if (!response.success) {
            console.warn("Visit tracking failed:", response.data);
          }
        },
        error: function () {
          console.warn("Visit tracking request failed");
        },
      });
    },

    /**
     * Update last activity timestamp
     */
    updateLastActivity: function () {
      if (this.currentAffiliate) {
        this.currentAffiliate.lastActivity = Date.now();
        this.storeInSessionStorage(this.currentAffiliate);
      }
    },

    /**
     * Clean URL parameters
     */
    cleanURL: function () {
      const url = new URL(window.location);
      let modified = false;

      // Remove affiliate parameters
      for (const param of [
        this.config.affiliateParam,
        ...this.config.alternativeParams,
      ]) {
        if (url.searchParams.has(param)) {
          url.searchParams.delete(param);
          modified = true;
        }
      }

      // Update URL without reload if modified
      if (modified && window.history && window.history.replaceState) {
        window.history.replaceState({}, document.title, url.toString());
      }
    },

    /**
     * Bind events
     */
    bindEvents: function () {
      // Track page visibility changes
      document.addEventListener("visibilitychange", () => {
        if (!document.hidden) {
          this.updateLastActivity();
        }
      });

      // Track clicks on affiliate-enabled elements
      $(document).on("click", "[data-affiliate-track]", (e) => {
        const $element = $(e.currentTarget);
        const trackingData = $element.data("affiliate-track");
        this.trackInteraction("click", trackingData, $element);
      });

      // Listen for custom affiliate events
      $(document).on("aci:set_affiliate", (e, code, metadata) => {
        this.processAffiliateCode(code, metadata);
      });

      $(document).on("aci:clear_affiliate", () => {
        this.clearAffiliateData();
      });
    },

    /**
     * Track user interactions
     */
    trackInteraction: function (type, data, element) {
      if (!this.currentAffiliate) return;

      const interactionData = {
        type: type,
        data: data,
        element: element.prop("tagName"),
        timestamp: Date.now(),
        affiliate_code: this.currentAffiliate.code,
      };

      $.ajax({
        url: this.config.trackingEndpoint,
        type: "POST",
        data: {
          action: "aci_track_interaction",
          nonce: aci_ajax.nonce,
          interaction: JSON.stringify(interactionData),
        },
      });
    },

    /**
     * Start periodic validation
     */
    startPeriodicValidation: function () {
      setInterval(() => {
        if (
          this.currentAffiliate &&
          this.isValidAffiliateData(this.currentAffiliate)
        ) {
          // Revalidate every 30 minutes
          if (
            Date.now() - this.currentAffiliate.lastActivity >
            30 * 60 * 1000
          ) {
            this.validateAffiliateCode(this.currentAffiliate.code)
              .then((response) => {
                if (!response.success || !response.data.valid) {
                  this.clearAffiliateData();
                }
              })
              .catch(() => {
                // Keep existing data on validation failure
              });
          }
        } else {
          this.clearAffiliateData();
        }
      }, 5 * 60 * 1000); // Check every 5 minutes
    },

    /**
     * Clear affiliate data
     */
    clearAffiliateData: function () {
      this.currentAffiliate = null;

      try {
        sessionStorage.removeItem("aci_affiliate");
        localStorage.removeItem("aci_affiliate");
        sessionStorage.removeItem("aci_temp_affiliate");

        // Clear cookie
        document.cookie =
          "aci_aff=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
      } catch (e) {
        console.warn("Failed to clear affiliate data:", e);
      }

      this.triggerAffiliateEvents("affiliate_cleared");
    },

    /**
     * Trigger affiliate events
     */
    triggerAffiliateEvents: function (eventType, data = {}) {
      // jQuery event
      $(document).trigger(`aci:${eventType}`, [data]);

      // Custom event
      const event = new CustomEvent(`aci_${eventType}`, {
        detail: data,
        bubbles: true,
      });
      document.dispatchEvent(event);
    },

    /**
     * Get current affiliate code
     */
    getCurrentAffiliate: function () {
      return this.currentAffiliate ? this.currentAffiliate.code : null;
    },

    /**
     * Get current affiliate data
     */
    getCurrentAffiliateData: function () {
      return this.currentAffiliate;
    },

    /**
     * Check if affiliate is active
     */
    hasActiveAffiliate: function () {
      return !!(
        this.currentAffiliate &&
        this.isValidAffiliateData(this.currentAffiliate)
      );
    },

    /**
     * Manually set affiliate code
     */
    setAffiliate: function (code, metadata = {}) {
      this.processAffiliateCode(code, { ...metadata, source: "manual" });
    },

    /**
     * Get affiliate attribution data for forms
     */
    getAttributionData: function () {
      if (!this.currentAffiliate) return {};

      return {
        affiliate_code: this.currentAffiliate.code,
        affiliate_timestamp: this.currentAffiliate.timestamp,
        affiliate_source: this.currentAffiliate.metadata?.source || "unknown",
        affiliate_referrer: this.currentAffiliate.metadata?.referrer || "",
        affiliate_landing: this.currentAffiliate.metadata?.url || "",
      };
    },
  };

  // Initialise when document is ready
  $(document).ready(function () {
    ACI.URLProcessor.init();
  });

  // Export for external use
  window.ACI.URLProcessor = ACI.URLProcessor;
})(jQuery);
