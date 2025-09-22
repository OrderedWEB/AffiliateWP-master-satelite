/**
 * Affiliate Popup Manager JavaScript
 *
 * Plugin: Affiliate Client Integration (Satellite)
 * File: /wp-content/plugins/affiliate-client-integration/assets/js/affiliate-popup-manager.js
 *
 * Handles popup display logic, form submissions, and user interactions
 * with comprehensive error handling and analytics tracking.
 */

(function ($) {
  "use strict";

  // Main popup manager object
  window.ACI_PopupManager = {
    // Configuration
    config: {
      ajaxUrl: aciPopup.ajaxUrl || "/wp-admin/admin-ajax.php",
      nonce: aciPopup.nonce || "",
      settings: aciPopup.settings || {},
      i18n: aciPopup.i18n || {},
    },

    // Active popups registry
    activePopups: {},

    // Event handlers
    eventHandlers: {},

    // Analytics data
    analytics: {
      popupShown: [],
      formSubmissions: [],
      interactions: [],
    },

    /**
     * Initialse popup manager
     */
    init: function () {
      this.bindEvents();
      this.setupGlobalTriggers();
      this.initializeAnalytics();
      this.setupKeyboardHandlers();
      this.setupMobileOptimizations();

      // Auto-initialize any popups on page load
      this.autoInitializePopups();

      console.log("ACI Popup Manager initialized");
    },

    /**
     * Initialse a specific popup
     */
    initPopup: function (popupId, triggerType, triggerData) {
      const popup = {
        id: popupId,
        triggerType: triggerType,
        triggerData: triggerData || {},
        isActive: false,
        hasBeenShown: false,
        element: $("#" + popupId),
      };

      if (popup.element.length === 0) {
        console.warn("Popup element not found:", popupId);
        return false;
      }

      this.activePopups[popupId] = popup;
      this.setupPopupTrigger(popup);

      return true;
    },

    /**
     * Setup popup trigger based on type
     */
    setupPopupTrigger: function (popup) {
      switch (popup.triggerType) {
        case "time_delay":
          this.setupTimeDelayTrigger(popup);
          break;
        case "scroll_percentage":
          this.setupScrollTrigger(popup);
          break;
        case "exit_intent":
          this.setupExitIntentTrigger(popup);
          break;
        case "element_click":
          this.setupClickTrigger(popup);
          break;
        case "url_parameter":
          this.setupUrlParameterTrigger(popup);
          break;
        case "auto":
          this.setupAutoTrigger(popup);
          break;
        default:
          console.warn("Unknown trigger type:", popup.triggerType);
      }
    },

    /**
     * Setup time delay trigger
     */
    setupTimeDelayTrigger: function (popup) {
      const delay = popup.triggerData.delay || 5000;

      setTimeout(() => {
        if (!popup.hasBeenShown && this.shouldShowPopup(popup.id)) {
          this.showPopup(popup.id);
        }
      }, delay);
    },

    /**
     * Setup scroll percentage trigger
     */
    setupScrollTrigger: function (popup) {
      const targetPercentage = popup.triggerData.percentage || 50;
      let triggered = false;

      $(window).on("scroll.popup_" + popup.id, () => {
        if (triggered || popup.hasBeenShown) return;

        const scrollPercent =
          ($(window).scrollTop() /
            ($(document).height() - $(window).height())) *
          100;

        if (scrollPercent >= targetPercentage) {
          triggered = true;
          if (this.shouldShowPopup(popup.id)) {
            this.showPopup(popup.id);
          }
          $(window).off("scroll.popup_" + popup.id);
        }
      });
    },

    /**
     * Setup exit intent trigger
     */
    setupExitIntentTrigger: function (popup) {
      const sensitivity = popup.triggerData.sensitivity || 20;
      let triggered = false;

      $(document).on("mouseleave.popup_" + popup.id, (e) => {
        if (triggered || popup.hasBeenShown) return;

        if (e.clientY <= sensitivity) {
          triggered = true;
          if (this.shouldShowPopup(popup.id)) {
            this.showPopup(popup.id);
          }
        }
      });

      // Mobile touch version
      let touchStartY = 0;
      $(document).on("touchstart.popup_" + popup.id, (e) => {
        touchStartY = e.touches[0].clientY;
      });

      $(document).on("touchmove.popup_" + popup.id, (e) => {
        if (triggered || popup.hasBeenShown) return;

        const touchY = e.touches[0].clientY;
        const deltaY = touchY - touchStartY;

        if (deltaY > 100 && touchY < 100) {
          // Swipe up near top
          triggered = true;
          if (this.shouldShowPopup(popup.id)) {
            this.showPopup(popup.id);
          }
        }
      });
    },

    /**
     * Setup click trigger
     */
    setupClickTrigger: function (popup) {
      const selector = popup.triggerData.selector || ".affiliate-popup-trigger";

      $(document).on("click.popup_" + popup.id, selector, (e) => {
        e.preventDefault();
        if (this.shouldShowPopup(popup.id)) {
          this.showPopup(popup.id);
        }
      });
    },

    /**
     * Setup URL parameter trigger
     */
    setupUrlParameterTrigger: function (popup) {
      const affiliateCode = popup.triggerData.affiliate_code;

      if (affiliateCode && this.shouldShowPopup(popup.id)) {
        // Pre-fill the form with the affiliate code
        popup.element.find(".affiliate-code-input").val(affiliateCode);
        this.showPopup(popup.id);
      }
    },

    /**
     * Setup auto trigger
     */
    setupAutoTrigger: function (popup) {
      if (this.shouldShowPopup(popup.id)) {
        // Small delay to ensure page is fully loaded
        setTimeout(() => {
          this.showPopup(popup.id);
        }, 1000);
      }
    },

    /**
     * Show popup
     */
    showPopup: function (popupId) {
      const popup = this.activePopups[popupId];
      if (!popup || popup.isActive || popup.hasBeenShown) {
        return false;
      }

      const $popup = popup.element;
      const $overlay = $popup.find(".aci-popup-overlay");
      const $content = $popup.find(".aci-popup-content");

      // Mark as shown
      popup.hasBeenShown = true;
      popup.isActive = true;

      // Add body class to prevent scrolling
      $("body").addClass("aci-popup-open");

      // Show popup with animation
      $popup.fadeIn(300, () => {
        $content.addClass("aci-popup-animate-in");

        // Focus on first input
        $popup.find("input:first").focus();
      });

      // Track popup shown
      this.trackEvent("popup_shown", {
        popupId: popupId,
        triggerType: popup.triggerType,
        timestamp: Date.now(),
      });

      // Setup popup-specific event handlers
      this.setupPopupEventHandlers(popup);

      return true;
    },

    /**
     * Hide popup
     */
    hidePopup: function (popupId) {
      const popup = this.activePopups[popupId];
      if (!popup || !popup.isActive) {
        return false;
      }

      const $popup = popup.element;
      const $content = $popup.find(".aci-popup-content");

      $content
        .removeClass("aci-popup-animate-in")
        .addClass("aci-popup-animate-out");

      setTimeout(() => {
        $popup.fadeOut(200, () => {
          $content.removeClass("aci-popup-animate-out");
          popup.isActive = false;
          $("body").removeClass("aci-popup-open");
        });
      }, 200);

      // Track popup closed
      this.trackEvent("popup_closed", {
        popupId: popupId,
        timestamp: Date.now(),
      });

      return true;
    },

    /**
     * Setup popup-specific event handlers
     */
    setupPopupEventHandlers: function (popup) {
      const $popup = popup.element;
      const popupId = popup.id;

      // Close button
      $popup
        .find(".aci-popup-close")
        .off()
        .on("click", (e) => {
          e.preventDefault();
          this.hidePopup(popupId);
        });

      // Overlay click
      $popup
        .find(".aci-popup-overlay")
        .off()
        .on("click", (e) => {
          if (e.target === e.currentTarget) {
            this.hidePopup(popupId);
          }
        });

      // Form submission
      $popup
        .find(".aci-affiliate-form")
        .off()
        .on("submit", (e) => {
          e.preventDefault();
          this.handleFormSubmission($(e.target), popupId);
        });

      // Input validation
      $popup
        .find(".affiliate-code-input")
        .off()
        .on("blur", (e) => {
          this.validateAffiliateCode($(e.target), popupId);
        });

      // Real-time validation
      $popup
        .find(".affiliate-code-input")
        .off()
        .on("input", (e) => {
          this.clearValidationErrors($(e.target));
        });
    },

    /**
     * Handle form submission
     */
    handleFormSubmission: function ($form, popupId) {
      const $submitBtn = $form.find('button[type="submit"]');
      const $messages = $form.find(".aci-form-messages");
      const originalButtonText = $submitBtn.text();

      // Disable form
      $submitBtn.prop("disabled", true).text(this.config.i18n.submitting);
      $form.find("input").prop("disabled", true);

      // Clear previous messages
      $messages.hide().removeClass("success error");

      // Collect form data
      const formData = {
        action: "aci_submit_popup_form",
        nonce: this.config.nonce,
        popup_id: popupId,
        affiliate_code: $form.find('[name="affiliate_code"]').val(),
        user_email: $form.find('[name="user_email"]').val(),
        additional_data: this.collectAdditionalFormData($form),
      };

      // Track form submission attempt
      this.trackEvent("form_submission_attempt", {
        popupId: popupId,
        hasEmail: !!formData.user_email,
        timestamp: Date.now(),
      });

      // Submit form
      $.post(this.config.ajaxUrl, formData)
        .done((response) => {
          this.handleFormSuccess(response, $form, popupId);
        })
        .fail((xhr) => {
          this.handleFormError(xhr, $form, popupId);
        })
        .always(() => {
          // Re-enable form
          $submitBtn.prop("disabled", false).text(originalButtonText);
          $form.find("input").prop("disabled", false);
        });
    },

    /**
     * Handle form success
     */
    handleFormSuccess: function (response, $form, popupId) {
      const $messages = $form.find(".aci-form-messages");

      if (response.success) {
        $messages.addClass("success").html(response.data.message).show();

        // Track successful submission
        this.trackEvent("form_submission_success", {
          popupId: popupId,
          discountApplied: response.data.discount_applied,
          timestamp: Date.now(),
        });

        // Hide form and show success message
        setTimeout(() => {
          if (response.data.redirect_url) {
            window.location.href = response.data.redirect_url;
          } else {
            this.showSuccessState($form, response.data);
          }
        }, 2000);
      } else {
        $messages.addClass("error").html(response.data.message).show();
        this.trackEvent("form_submission_error", {
          popupId: popupId,
          error: response.data.message,
          timestamp: Date.now(),
        });
      }
    },

    /**
     * Handle form error
     */
    handleFormError: function (xhr, $form, popupId) {
      const $messages = $form.find(".aci-form-messages");
      let errorMessage = this.config.i18n.error;

      try {
        const response = JSON.parse(xhr.responseText);
        errorMessage = response.data?.message || errorMessage;
      } catch (e) {
        console.error("Failed to parse error response:", xhr.responseText);
      }

      $messages.addClass("error").html(errorMessage).show();

      this.trackEvent("form_submission_error", {
        popupId: popupId,
        error: errorMessage,
        statusCode: xhr.status,
        timestamp: Date.now(),
      });
    },

    /**
     * Show success state
     */
    showSuccessState: function ($form, responseData) {
      const $formContainer = $form.closest(".aci-popup-body");

      $formContainer.html(`
                <div class="aci-success-state">
                    <div class="aci-success-icon">✓</div>
                    <h3>${this.config.i18n.success}</h3>
                    ${
                      responseData.discount_applied
                        ? `<p>Discount: ${responseData.discount_amount}</p>`
                        : ""
                    }
                    <button type="button" class="aci-close-btn" onclick="ACI_PopupManager.hidePopup('${$form.data(
                      "popup-id"
                    )}')">${this.config.i18n.close}</button>
                </div>
            `);
    },

    /**
     * Validate affiliate code
     */
    validateAffiliateCode: function ($input, popupId) {
      const code = $input.val().trim();

      if (code.length < 3) {
        return;
      }

      const $form = $input.closest("form");
      const $feedback = $input.siblings(".aci-validation-feedback");

      // Show loading state
      $input.addClass("validating");
      if ($feedback.length === 0) {
        $input.after('<div class="aci-validation-feedback"></div>');
      }
      $input
        .siblings(".aci-validation-feedback")
        .text(this.config.i18n.validating);

      // Validate code
      $.post(this.config.ajaxUrl, {
        action: "aci_validate_popup_code",
        nonce: this.config.nonce,
        affiliate_code: code,
        popup_id: popupId,
      })
        .done((response) => {
          $input.removeClass("validating");

          if (response.success) {
            $input.addClass("valid").removeClass("invalid");
            $input
              .siblings(".aci-validation-feedback")
              .addClass("success")
              .removeClass("error")
              .text("✓ " + (response.data.message || "Valid code"));

            // Show discount info if available
            if (response.data.discount_info) {
              this.showDiscountInfo($form, response.data.discount_info);
            }
          } else {
            $input.addClass("invalid").removeClass("valid");
            $input
              .siblings(".aci-validation-feedback")
              .addClass("error")
              .removeClass("success")
              .text(
                "✗ " + (response.data.message || this.config.i18n.invalidCode)
              );
          }
        })
        .fail(() => {
          $input.removeClass("validating valid").addClass("invalid");
          $input
            .siblings(".aci-validation-feedback")
            .addClass("error")
            .removeClass("success")
            .text("✗ " + this.config.i18n.error);
        });
    },

    /**
     * Show discount information
     */
    showDiscountInfo: function ($form, discountInfo) {
      let $discountDisplay = $form.find(".aci-discount-display");

      if ($discountDisplay.length === 0) {
        $form
          .find(".form-group:last")
          .after('<div class="aci-discount-display"></div>');
        $discountDisplay = $form.find(".aci-discount-display");
      }

      $discountDisplay
        .html(
          `
                <div class="aci-discount-info">
                    <span class="aci-discount-label">Your Discount:</span>
                    <span class="aci-discount-amount">${discountInfo.formatted}</span>
                </div>
            `
        )
        .show();
    },

    /**
     * Clear validation errors
     */
    clearValidationErrors: function ($input) {
      $input.removeClass("invalid valid validating");
      $input.siblings(".aci-validation-feedback").remove();
    },

    /**
     * Collect additional form data
     */
    collectAdditionalFormData: function ($form) {
      const additionalData = {};

      $form.find("[data-additional]").each(function () {
        const $field = $(this);
        additionalData[$field.attr("name")] = $field.val();
      });

      return additionalData;
    },

    /**
     * Check if popup should be shown
     */
    shouldShowPopup: function (popupId) {
      // Check if popup was already shown in this session
      const sessionKey = "aci_popup_shown_" + popupId;
      if (sessionStorage.getItem(sessionKey)) {
        return false;
      }

      // Check frequency settings
      const frequency =
        this.config.settings.show_frequency || "once_per_session";

      switch (frequency) {
        case "once_per_day":
          const dayKey = "aci_popup_day_" + popupId;
          const today = new Date().toDateString();
          if (localStorage.getItem(dayKey) === today) {
            return false;
          }
          break;
        case "always":
          return true;
        case "once_per_session":
        default:
          // Will be handled by session storage check above
          break;
      }

      return true;
    },

    /**
     * Mark popup as shown
     */
    markPopupShown: function (popupId) {
      const frequency =
        this.config.settings.show_frequency || "once_per_session";

      sessionStorage.setItem("aci_popup_shown_" + popupId, "true");

      if (frequency === "once_per_day") {
        const today = new Date().toDateString();
        localStorage.setItem("aci_popup_day_" + popupId, today);
      }
    },

    /**
     * Track analytics event
     */
    trackEvent: function (eventType, data) {
      this.analytics[eventType] = this.analytics[eventType] || [];
      this.analytics[eventType].push({
        ...data,
        url: window.location.href,
        userAgent: navigator.userAgent,
        timestamp: Date.now(),
      });

      // Send to server if configured
      if (this.config.settings.track_analytics) {
        this.sendAnalyticsEvent(eventType, data);
      }
    },

    /**
     * Send analytics event to server
     */
    sendAnalyticsEvent: function (eventType, data) {
      // Debounce analytics sends
      clearTimeout(this.analyticsTimeout);
      this.analyticsTimeout = setTimeout(() => {
        $.post(this.config.ajaxUrl, {
          action: "aci_track_popup_event",
          nonce: this.config.nonce,
          event_type: eventType,
          event_data: data,
        });
      }, 1000);
    },

    /**
     * Bind global events
     */
    bindEvents: function () {
      // Manual popup triggers
      $(document).on("click", "[data-popup]", (e) => {
        e.preventDefault();
        const popupId = $(e.target).data("popup");
        if (popupId) {
          this.showPopup(popupId);
        }
      });

      // Keyboard shortcuts
      $(document).on("keydown", (e) => {
        // ESC to close popups
        if (e.keyCode === 27) {
          Object.keys(this.activePopups).forEach((popupId) => {
            if (this.activePopups[popupId].isActive) {
              this.hidePopup(popupId);
            }
          });
        }
      });
    },

    /**
     * Setup global triggers
     */
    setupGlobalTriggers: function () {
      // Page unload analytics
      $(window).on("beforeunload", () => {
        this.sendFinalAnalytics();
      });

      // Visibility change tracking
      document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "hidden") {
          this.sendFinalAnalytics();
        }
      });
    },

    /**
     * Initialse analytics
     */
    initializeAnalytics: function () {
      this.analytics = {
        sessionStart: Date.now(),
        pageViews: 1,
        events: [],
      };
    },

    /**
     * Setup keyboard handlers
     */
    setupKeyboardHandlers: function () {
      $(document).on("keydown", ".aci-popup-container", (e) => {
        // Tab navigation within popup
        if (e.keyCode === 9) {
          const $popup = $(e.currentTarget);
          const $focusable = $popup.find(
            'input, button, select, textarea, [tabindex]:not([tabindex="-1"])'
          );
          const $first = $focusable.first();
          const $last = $focusable.last();

          if (e.shiftKey && $(e.target).is($first)) {
            e.preventDefault();
            $last.focus();
          } else if (!e.shiftKey && $(e.target).is($last)) {
            e.preventDefault();
            $first.focus();
          }
        }
      });
    },

    /**
     * Setup mobile optimizations
     */
    setupMobileOptimizations: function () {
      if (this.isMobile()) {
        // Prevent zoom on input focus
        $("meta[name=viewport]").attr(
          "content",
          "width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"
        );

        // Mobile-specific popup positioning
        $(document).on("focus", ".aci-popup-container input", function () {
          setTimeout(() => {
            $(this).closest(".aci-popup-content").addClass("input-focused");
          }, 300);
        });

        $(document).on("blur", ".aci-popup-container input", function () {
          $(this).closest(".aci-popup-content").removeClass("input-focused");
        });
      }
    },

    /**
     * Auto-initialize popups
     */
    autoInitializePopups: function () {
      $(".aci-popup-container").each((index, element) => {
        const $popup = $(element);
        const popupId = $popup.attr("id");

        if (popupId && !this.activePopups[popupId]) {
          // Auto-detect trigger type from data attributes
          const triggerType = $popup.data("trigger-type") || "manual";
          const triggerData = $popup.data("trigger-data") || {};

          this.initPopup(popupId, triggerType, triggerData);
        }
      });
    },

    /**
     * Send final analytics
     */
    sendFinalAnalytics: function () {
      if (Object.keys(this.analytics).length > 0) {
        navigator.sendBeacon(
          this.config.ajaxUrl,
          new FormData().append("analytics", JSON.stringify(this.analytics))
        );
      }
    },

    /**
     * Check if mobile device
     */
    isMobile: function () {
      return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
        navigator.userAgent
      );
    },

    /**
     * Utility: Debounce function
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

  // Initialise when document is ready
  $(document).ready(function () {
    ACI_PopupManager.init();
  });
})(jQuery);
