/**
 * Affiliate Main JavaScript
 * File: /wp-content/plugins/affiliate-client-integration/assets/js/affiliate-main.js
 * Plugin: Affiliate Client Integration
 */

(function ($) {
  "use strict";

  window.ACI = window.ACI || {};

  /**
   * Main ACI Application
   */
  ACI.App = {
    // Configuration
    config: {
      debug: false,
      version: "1.0.0",
      apiEndpoint: aci_ajax.ajax_url,
      nonce: aci_ajax.nonce,
      domain: window.location.hostname,
    },

    // Application state
    state: {
      initialized: false,
      affiliate: null,
      cart: [],
      session: null,
      popup: null,
    },

    // Event handlers
    handlers: {},

    /**
     * Initialize the application
     */
    init: function () {
      if (this.state.initialized) return;

      this.loadConfig();
      this.setupEventHandlers();
      this.initializeModules();
      this.bindGlobalEvents();
      this.checkInitialState();

      this.state.initialized = true;
      this.log("ACI Application initialized");

      // Trigger initialisation event
      this.trigger("app:initialized");
    },

    /**
     * Load configuration from server
     */
    loadConfig: function () {
      if (typeof aci_config !== "undefined") {
        this.config = $.extend(true, this.config, aci_config);
      }

      // Enable debug mode
      this.config.debug =
        this.config.debug || window.location.hash.includes("aci-debug");
    },

    /**
     * Setup core event handlers
     */
    setupEventHandlers: function () {
      this.handlers = {
        // Affiliate events
        "aci:affiliate_set": this.onAffiliateSet.bind(this),
        "aci:affiliate_cleared": this.onAffiliateCleared.bind(this),
        "aci:affiliate_validated": this.onAffiliateValidated.bind(this),

        // Cart events
        "aci:cart_updated": this.onCartUpdated.bind(this),
        "aci:cart_cleared": this.onCartCleared.bind(this),

        // Price events
        "aci:price_calculated": this.onPriceCalculated.bind(this),
        "aci:discount_applied": this.onDiscountApplied.bind(this),

        // Form events
        "aci:form_submitted": this.onFormSubmitted.bind(this),
        "aci:form_validated": this.onFormValidated.bind(this),

        // Popup events
        "aci:popup_shown": this.onPopupShown.bind(this),
        "aci:popup_closed": this.onPopupClosed.bind(this),
      };

      // Bind all handlers
      for (const [event, handler] of Object.entries(this.handlers)) {
        $(document).on(event, handler);
      }
    },

    /**
     * Initialize sub-modules
     */
    initializeModules: function () {
      // Initialize URL processor
      if (ACI.URLProcessor) {
        ACI.URLProcessor.init();
      }

      // Initialize price calculator
      if (ACI.PriceCalculator) {
        ACI.PriceCalculator.init();
      }

      // Initialize validation
      if (ACI.Validation) {
        ACI.Validation.init();
      }

      // Initialize popup manager
      if (ACI.PopupManager) {
        ACI.PopupManager.init();
      }

      // Initialize session manager
      this.initializeSession();
    },

    /**
     * Initialize session management
     */
    initializeSession: function () {
      // Create session identifier
      this.state.session = {
        id: this.generateSessionId(),
        startTime: Date.now(),
        pageViews: 1,
        interactions: [],
      };

      // Track page view
      this.trackPageView();

      // Setup session heartbeat
      setInterval(() => {
        this.sendHeartbeat();
      }, 30000); // Every 30 seconds
    },

    /**
     * Bind global events
     */
    bindGlobalEvents: function () {
      // Page visibility changes
      document.addEventListener("visibilitychange", () => {
        if (document.hidden) {
          this.trigger("app:page_hidden");
        } else {
          this.trigger("app:page_visible");
          this.sendHeartbeat();
        }
      });

      // Before unload
      window.addEventListener("beforeunload", () => {
        this.trigger("app:before_unload");
        this.sendFinalStats();
      });

      // Window resize
      let resizeTimeout;
      window.addEventListener("resize", () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
          this.trigger("app:window_resized");
        }, 250);
      });

      // Scroll tracking
      let scrollTimeout;
      window.addEventListener("scroll", () => {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
          this.trackScrollDepth();
        }, 100);
      });

      // Click tracking
      document.addEventListener(
        "click",
        (e) => {
          this.trackClick(e);
        },
        true
      );

      // Form interactions
      $(document).on("focus", "input, select, textarea", (e) => {
        this.trackFormInteraction("focus", e.target);
      });

      $(document).on("change", "input, select, textarea", (e) => {
        this.trackFormInteraction("change", e.target);
      });
    },

    /**
     * Check initial application state
     */
    checkInitialState: function () {
      // Check for affiliate in URL or storage
      if (ACI.URLProcessor && ACI.URLProcessor.hasActiveAffiliate()) {
        this.state.affiliate = ACI.URLProcessor.getCurrentAffiliateData();
        this.trigger("app:affiliate_detected", this.state.affiliate);
      }

      // Check for cart data
      this.loadCartFromStorage();

      // Check for popup conditions
      this.checkPopupConditions();
    },

    /**
     * Event handlers
     */
    onAffiliateSet: function (event, data) {
      this.state.affiliate = data;
      this.saveAffiliateToStorage(data);
      this.updateAffiliateUI(data);
      this.trackEvent("affiliate_set", { code: data.code });
      this.log("Affiliate set:", data);
    },

    onAffiliateCleared: function () {
      this.state.affiliate = null;
      this.clearAffiliateFromStorage();
      this.updateAffiliateUI(null);
      this.trackEvent("affiliate_cleared");
      this.log("Affiliate cleared");
    },

    onAffiliateValidated: function (event, code, data) {
      this.trackEvent("affiliate_validated", { code, valid: data.valid });

      if (data.valid) {
        this.showNotification(
          "success",
          `Affiliate code "${code}" applied successfully!`
        );
      } else {
        this.showNotification("error", "Invalid affiliate code");
      }
    },

    onCartUpdated: function (event, cartData) {
      this.state.cart = cartData;
      this.saveCartToStorage(cartData);
      this.updateCartUI(cartData);
      this.trackEvent("cart_updated", { items: cartData.length });
    },

    onCartCleared: function () {
      this.state.cart = [];
      this.clearCartFromStorage();
      this.updateCartUI([]);
      this.trackEvent("cart_cleared");
    },

    onPriceCalculated: function (event, calculation) {
      this.trackEvent("price_calculated", {
        total: calculation.total,
        discount: calculation.discount_amount,
        affiliate: this.state.affiliate?.code,
      });
    },

    onDiscountApplied: function (event, discountData) {
      this.trackEvent("discount_applied", discountData);

      if (discountData.amount > 0) {
        this.showNotification(
          "success",
          `Discount of ${this.formatCurrency(discountData.amount)} applied!`
        );
      }
    },

    onFormSubmitted: function (event, formData) {
      this.trackEvent("form_submitted", {
        form_id: formData.form_id,
        affiliate: this.state.affiliate?.code,
      });
    },

    onFormValidated: function (event, validationData) {
      this.trackEvent("form_validated", {
        valid: validationData.valid,
        errors: validationData.errors?.length || 0,
      });
    },

    onPopupShown: function (event, popupData) {
      this.state.popup = popupData;
      this.trackEvent("popup_shown", { type: popupData.type });
    },

    onPopupClosed: function (event, popupData) {
      this.state.popup = null;
      this.trackEvent("popup_closed", {
        type: popupData.type,
        action: popupData.action,
      });
    },

    /**
     * Storage management
     */
    saveAffiliateToStorage: function (data) {
      try {
        localStorage.setItem("aci_affiliate_data", JSON.stringify(data));
      } catch (e) {
        this.log("Failed to save affiliate to storage:", e);
      }
    },

    loadAffiliateFromStorage: function () {
      try {
        const stored = localStorage.getItem("aci_affiliate_data");
        return stored ? JSON.parse(stored) : null;
      } catch (e) {
        this.log("Failed to load affiliate from storage:", e);
        return null;
      }
    },

    clearAffiliateFromStorage: function () {
      try {
        localStorage.removeItem("aci_affiliate_data");
      } catch (e) {
        this.log("Failed to clear affiliate from storage:", e);
      }
    },

    saveCartToStorage: function (cartData) {
      try {
        localStorage.setItem(
          "aci_cart_data",
          JSON.stringify({
            data: cartData,
            timestamp: Date.now(),
          })
        );
      } catch (e) {
        this.log("Failed to save cart to storage:", e);
      }
    },

    loadCartFromStorage: function () {
      try {
        const stored = localStorage.getItem("aci_cart_data");
        if (stored) {
          const cartInfo = JSON.parse(stored);
          // Check if cart is not too old (24 hours)
          if (Date.now() - cartInfo.timestamp < 24 * 60 * 60 * 1000) {
            this.state.cart = cartInfo.data;
            this.trigger("app:cart_loaded", cartInfo.data);
          }
        }
      } catch (e) {
        this.log("Failed to load cart from storage:", e);
      }
    },

    clearCartFromStorage: function () {
      try {
        localStorage.removeItem("aci_cart_data");
      } catch (e) {
        this.log("Failed to clear cart from storage:", e);
      }
    },

    /**
     * UI management
     */
    updateAffiliateUI: function (affiliateData) {
      const $indicators = $(".aci-affiliate-indicator");

      if (affiliateData) {
        $indicators
          .addClass("aci-active")
          .find(".aci-affiliate-code")
          .text(affiliateData.code);

        // Show affiliate notices
        this.showAffiliateNotice(affiliateData);
      } else {
        $indicators
          .removeClass("aci-active")
          .find(".aci-affiliate-code")
          .text("");

        // Hide affiliate notices
        this.hideAffiliateNotice();
      }
    },

    updateCartUI: function (cartData) {
      const $indicators = $(".aci-cart-indicator");
      const itemCount = Array.isArray(cartData) ? cartData.length : 0;

      $indicators.find(".aci-cart-count").text(itemCount);

      if (itemCount > 0) {
        $indicators.addClass("aci-has-items");
      } else {
        $indicators.removeClass("aci-has-items");
      }
    },

    showAffiliateNotice: function (affiliateData) {
      const noticeHtml = `
                <div class="aci-affiliate-notice aci-notice-floating">
                    <div class="aci-notice-content">
                        <i class="aci-icon-discount"></i>
                        <span>Affiliate discount active: ${affiliateData.code}</span>
                        <button class="aci-notice-close" onclick="ACI.App.hideAffiliateNotice()">&times;</button>
                    </div>
                </div>
            `;

      // Remove existing notice
      $(".aci-affiliate-notice").remove();

      // Add new notice
      $("body").append(noticeHtml);

      // Auto-hide after 10 seconds
      setTimeout(() => {
        $(".aci-affiliate-notice").fadeOut();
      }, 10000);
    },

    hideAffiliateNotice: function () {
      $(".aci-affiliate-notice").fadeOut();
    },

    showNotification: function (type, message, duration = 5000) {
      const notificationHtml = `
                <div class="aci-notification aci-notification-${type}">
                    <div class="aci-notification-content">
                        <span class="aci-notification-message">${message}</span>
                        <button class="aci-notification-close">&times;</button>
                    </div>
                </div>
            `;

      const $notification = $(notificationHtml);
      $("body").append($notification);

      // Animate in
      setTimeout(() => {
        $notification.addClass("aci-notification-show");
      }, 10);

      // Auto-hide
      setTimeout(() => {
        this.hideNotification($notification);
      }, duration);

      // Bind close button
      $notification.find(".aci-notification-close").on("click", () => {
        this.hideNotification($notification);
      });
    },

    hideNotification: function ($notification) {
      $notification.removeClass("aci-notification-show");
      setTimeout(() => {
        $notification.remove();
      }, 300);
    },

    /**
     * Tracking and analytics
     */
    trackEvent: function (eventName, data = {}) {
      const eventData = {
        event: eventName,
        timestamp: Date.now(),
        session_id: this.state.session?.id,
        affiliate: this.state.affiliate?.code,
        url: window.location.href,
        user_agent: navigator.userAgent,
        ...data,
      };

      // Store in session for batching
      this.state.session.interactions.push(eventData);

      // Send immediately for important events
      const immediateEvents = [
        "affiliate_set",
        "form_submitted",
        "discount_applied",
      ];
      if (immediateEvents.includes(eventName)) {
        this.sendEvent(eventData);
      }

      this.log("Event tracked:", eventData);
    },

    trackPageView: function () {
      this.trackEvent("page_view", {
        title: document.title,
        referrer: document.referrer,
        screen_resolution: `${screen.width}x${screen.height}`,
      });
    },

    trackScrollDepth: function () {
      const scrollTop = window.pageYOffset;
      const docHeight =
        document.documentElement.scrollHeight - window.innerHeight;
      const scrollPercent = Math.round((scrollTop / docHeight) * 100);

      // Track at 25%, 50%, 75%, 100% milestones
      const milestones = [25, 50, 75, 100];
      const milestone = milestones.find(
        (m) =>
          scrollPercent >= m &&
          !this.state.session.scrollMilestones?.includes(m)
      );

      if (milestone) {
        this.state.session.scrollMilestones =
          this.state.session.scrollMilestones || [];
        this.state.session.scrollMilestones.push(milestone);

        this.trackEvent("scroll_depth", {
          depth: milestone,
          total_scroll: scrollPercent,
        });
      }
    },

    trackClick: function (event) {
      const target = event.target;
      const $target = $(target);

      // Track affiliate-related clicks
      if ($target.closest("[data-aci-track]").length) {
        const trackingData = $target
          .closest("[data-aci-track]")
          .data("aci-track");
        this.trackEvent("element_click", {
          element: trackingData,
          tag: target.tagName,
          text: target.textContent?.substring(0, 50),
        });
      }
    },

    trackFormInteraction: function (type, element) {
      if ($(element).closest(".aci-form").length) {
        this.trackEvent("form_interaction", {
          type: type,
          field: element.name || element.id,
          form_id: $(element).closest(".aci-form").attr("id"),
        });
      }
    },

    sendEvent: function (eventData) {
      $.ajax({
        url: this.config.apiEndpoint,
        type: "POST",
        data: {
          action: "aci_track_event",
          nonce: this.config.nonce,
          event_data: JSON.stringify(eventData),
        },
        success: (response) => {
          if (!response.success) {
            this.log("Event tracking failed:", response.data);
          }
        },
        error: (xhr, status, error) => {
          this.log("Event tracking error:", error);
        },
      });
    },

    sendHeartbeat: function () {
      if (this.state.session.interactions.length === 0) return;

      // Send batched events
      const events = [...this.state.session.interactions];
      this.state.session.interactions = [];

      $.ajax({
        url: this.config.apiEndpoint,
        type: "POST",
        data: {
          action: "aci_heartbeat",
          nonce: this.config.nonce,
          session_data: JSON.stringify({
            session_id: this.state.session.id,
            events: events,
            timestamp: Date.now(),
          }),
        },
      });
    },

    sendFinalStats: function () {
      if (this.state.session.interactions.length > 0) {
        // Use sendBeacon for reliability
        if (navigator.sendBeacon) {
          const data = new FormData();
          data.append("action", "aci_final_stats");
          data.append("nonce", this.config.nonce);
          data.append(
            "session_data",
            JSON.stringify({
              session_id: this.state.session.id,
              events: this.state.session.interactions,
              session_duration: Date.now() - this.state.session.startTime,
            })
          );

          navigator.sendBeacon(this.config.apiEndpoint, data);
        }
      }
    },

    /**
     * Popup management
     */
    checkPopupConditions: function () {
      if (!ACI.PopupManager) return;

      // Check if popup should be shown
      const popupSettings = this.config.popup || {};

      if (!popupSettings.enabled) return;

      // Check if already shown in this session
      if (popupSettings.show_once && sessionStorage.getItem("aci_popup_shown"))
        return;

      // Check if affiliate is already active
      if (this.state.affiliate) return;

      // Trigger based on settings
      switch (popupSettings.trigger) {
        case "exit_intent":
          this.setupExitIntentPopup();
          break;
        case "time_delay":
          setTimeout(() => {
            ACI.PopupManager.show("default");
          }, (popupSettings.delay || 5) * 1000);
          break;
        case "scroll":
          this.setupScrollPopup(popupSettings.scroll_percentage || 50);
          break;
      }
    },

    setupExitIntentPopup: function () {
      let shown = false;

      document.addEventListener("mouseleave", (e) => {
        if (e.clientY <= 0 && !shown) {
          shown = true;
          ACI.PopupManager.show("default");
        }
      });
    },

    setupScrollPopup: function (percentage) {
      let shown = false;

      window.addEventListener("scroll", () => {
        if (shown) return;

        const scrollTop = window.pageYOffset;
        const docHeight =
          document.documentElement.scrollHeight - window.innerHeight;
        const scrollPercent = (scrollTop / docHeight) * 100;

        if (scrollPercent >= percentage) {
          shown = true;
          ACI.PopupManager.show("default");
        }
      });
    },

    /**
     * Utility methods
     */
    generateSessionId: function () {
      return (
        "aci_" + Date.now() + "_" + Math.random().toString(36).substr(2, 9)
      );
    },

    formatCurrency: function (amount) {
      const formatter = new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: this.config.currency || "USD",
      });
      return formatter.format(amount);
    },

    trigger: function (eventName, data = {}) {
      $(document).trigger(eventName, [data]);
      this.log("Event triggered:", eventName, data);
    },

    log: function (...args) {
      if (this.config.debug) {
        console.log("[ACI]", ...args);
      }
    },

    /**
     * Public API methods
     */
    getState: function () {
      return { ...this.state };
    },

    setAffiliate: function (code, metadata = {}) {
      if (ACI.URLProcessor) {
        ACI.URLProcessor.setAffiliate(code, metadata);
      }
    },

    clearAffiliate: function () {
      if (ACI.URLProcessor) {
        ACI.URLProcessor.clearAffiliateData();
      }
    },

    showPopup: function (type = "default") {
      if (ACI.PopupManager) {
        ACI.PopupManager.show(type);
      }
    },

    addToCart: function (item) {
      this.state.cart.push(item);
      this.trigger("aci:cart_updated", this.state.cart);
    },

    removeFromCart: function (index) {
      this.state.cart.splice(index, 1);
      this.trigger("aci:cart_updated", this.state.cart);
    },

    clearCart: function () {
      this.state.cart = [];
      this.trigger("aci:cart_cleared");
    },
  };

  // Initialize when document is ready
  $(document).ready(function () {
    ACI.App.init();
  });

  // Export for external use
  window.ACI.App = ACI.App;
})(jQuery);
