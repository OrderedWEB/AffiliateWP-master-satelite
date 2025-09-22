/**
 * Affiliate Client Full - Zoho Forms Integration
 *
 * JavaScript for automatic population of Zoho forms with affiliate
 * tracking data and handling form submission events.
 *
 * @package AffiliateClientFull
 * @version 1.0.0
 */

(function ($) {
  "use strict";

  // Zoho Forms integration object
  window.AffiliateClientZoho = {
    // Configuration
    config: {
      ajaxUrl: "",
      restUrl: "",
      nonce: "",
      trackingData: {},
    },

    // State
    initialized: false,
    forms: {},
    observers: [],

    /**
     * Initialse Zoho integration
     */
    init: function (config) {
      this.config = $.extend(this.config, config || {});

      this.setupFormDetection();
      this.setupMessageListeners();
      this.populateExistingForms();
      this.setupSubmissionTracking();

      this.initialized = true;
      this.log("Zoho integration initialized");
    },

    /**
     * Setup automatic form detection
     */
    setupFormDetection: function () {
      var self = this;

      // Detect existing Zoho forms
      this.detectZohoForms();

      // Setup mutation observer for dynamically loaded forms
      if (window.MutationObserver) {
        var observer = new MutationObserver(function (mutations) {
          mutations.forEach(function (mutation) {
            if (mutation.type === "childList") {
              mutation.addedNodes.forEach(function (node) {
                if (node.nodeType === 1) {
                  // Element node
                  self.detectZohoForms(node);
                }
              });
            }
          });
        });

        observer.observe(document.body, {
          childList: true,
          subtree: true,
        });

        this.observers.push(observer);
      }

      // Fallback polling for forms
      setInterval(function () {
        self.detectZohoForms();
      }, 5000);
    },

    /**
     * Detect Zoho forms on page
     */
    detectZohoForms: function (container) {
      var self = this;
      var searchContainer = container || document;

      // Find Zoho form iframes
      var zohoIframes = searchContainer.querySelectorAll(
        'iframe[src*="zohopublic.com"], iframe[src*="zohoforms.com"]'
      );

      zohoIframes.forEach(function (iframe) {
        var formId = self.extractFormId(iframe.src);
        if (formId && !self.forms[formId]) {
          self.registerForm(iframe, formId);
        }
      });

      // Find embedded Zoho forms (div containers)
      var zohoContainers = searchContainer.querySelectorAll(
        '[id*="zoho"], [class*="zoho"], [data-zoho-form]'
      );

      zohoContainers.forEach(function (container) {
        var formId =
          container.getAttribute("data-zoho-form") ||
          self.extractFormIdFromElement(container);
        if (formId && !self.forms[formId]) {
          self.registerForm(container, formId);
        }
      });
    },

    /**
     * Register a Zoho form for tracking
     */
    registerForm: function (element, formId) {
      this.forms[formId] = {
        element: element,
        formId: formId,
        populated: false,
        submitted: false,
      };

      this.log("Registered Zoho form:", formId);

      // Attempt to populate the form
      this.populateForm(formId);

      // Track form load
      this.trackFormEvent("form_loaded", formId);
    },

    /**
     * Extract form ID from iframe src
     */
    extractFormId: function (src) {
      // Common Zoho form URL patterns
      var patterns = [
        /\/form\/([^\/]+)\/formperma/,
        /\/form\/([^\/]+)$/,
        /formId=([^&]+)/,
        /\/([^\/]+)\/formperma/,
      ];

      for (var i = 0; i < patterns.length; i++) {
        var match = src.match(patterns[i]);
        if (match) {
          return match[1];
        }
      }

      return null;
    },

    /**
     * Extract form ID from element attributes
     */
    extractFormIdFromElement: function (element) {
      return (
        element.getAttribute("data-zoho-form") ||
        element.getAttribute("data-form-id") ||
        element.id.replace(/[^a-zA-Z0-9]/g, "")
      );
    },

    /**
     * Populate existing forms with tracking data
     */
    populateExistingForms: function () {
      var self = this;

      Object.keys(this.forms).forEach(function (formId) {
        if (!self.forms[formId].populated) {
          self.populateForm(formId);
        }
      });
    },

    /**
     * Populate form with affiliate tracking data
     */
    populateForm: function (formId) {
      var formData = this.forms[formId];
      if (!formData || formData.populated) {
        return;
      }

      var element = formData.element;
      var trackingData = this.config.trackingData;

      // Method 1: Try to populate via postMessage (for iframes)
      if (element.tagName === "IFRAME") {
        this.populateViaPostMessage(element, trackingData);
      }

      // Method 2: Try to populate via URL parameters
      this.populateViaUrlParams(element, trackingData, formId);

      // Method 3: Try direct DOM manipulation (for embedded forms)
      this.populateViaDom(element, trackingData);

      formData.populated = true;
      this.log("Populated form:", formId, trackingData);
    },

    /**
     * Populate form via postMessage
     */
    populateViaPostMessage: function (iframe, trackingData) {
      var self = this;

      // Wait for iframe to load
      var populateData = function () {
        try {
          iframe.contentWindow.postMessage(
            {
              type: "affiliate_populate_form",
              data: trackingData,
            },
            "*"
          );
        } catch (e) {
          self.log("PostMessage failed:", e);
        }
      };

      if (iframe.complete) {
        populateData();
      } else {
        iframe.addEventListener("load", populateData);
      }
    },

    /**
     * Populate form via URL parameters
     */
    populateViaUrlParams: function (element, trackingData, formId) {
      if (element.tagName !== "IFRAME") {
        return;
      }

      var currentSrc = element.src;
      var urlParams = new URLSearchParams();

      // Map tracking data to URL parameters
      if (trackingData.affiliateId) {
        urlParams.append("SingleLine", trackingData.affiliateId);
        urlParams.append("affiliate_id", trackingData.affiliateId);
      }

      if (trackingData.visitId) {
        urlParams.append("SingleLine1", trackingData.visitId);
        urlParams.append("visit_id", trackingData.visitId);
      }

      if (trackingData.utmSource) {
        urlParams.append("SingleLine2", trackingData.utmSource);
        urlParams.append("utm_source", trackingData.utmSource);
      }

      if (trackingData.utmCampaign) {
        urlParams.append("SingleLine3", trackingData.utmCampaign);
        urlParams.append("utm_campaign", trackingData.utmCampaign);
      }

      // Add additional UTM parameters
      ["utmMedium", "utmContent", "utmTerm"].forEach(function (param, index) {
        if (trackingData[param]) {
          urlParams.append("SingleLine" + (4 + index), trackingData[param]);
          urlParams.append(
            param.toLowerCase().replace("utm", "utm_"),
            trackingData[param]
          );
        }
      });

      // Update iframe src if we have parameters to add
      if (urlParams.toString()) {
        var separator = currentSrc.indexOf("?") !== -1 ? "&" : "?";
        element.src = currentSrc + separator + urlParams.toString();
        this.log("Updated iframe src with tracking params:", element.src);
      }
    },

    /**
     * Populate form via direct DOM manipulation
     */
    populateViaDom: function (element, trackingData) {
      var form =
        element.tagName === "FORM" ? element : element.querySelector("form");
      if (!form) {
        return;
      }

      // Map tracking data to common field patterns
      var fieldMappings = {
        affiliate_id: [
          "affiliate_id",
          "ref",
          "SingleLine",
          "affiliate",
          "referrer_id",
        ],
        visit_id: ["visit_id", "SingleLine1", "session_id", "tracking_id"],
        utm_source: ["utm_source", "SingleLine2", "source", "traffic_source"],
        utm_campaign: [
          "utm_campaign",
          "SingleLine3",
          "campaign",
          "campaign_name",
        ],
        utm_medium: ["utm_medium", "SingleLine4", "medium"],
        utm_content: ["utm_content", "SingleLine5", "content"],
        utm_term: ["utm_term", "SingleLine6", "keyword", "term"],
      };

      Object.keys(fieldMappings).forEach(function (dataKey) {
        var value =
          trackingData[
            dataKey === "affiliate_id"
              ? "affiliateId"
              : dataKey === "visit_id"
              ? "visitId"
              : dataKey.replace("_", "").replace("utm", "utm")
          ];

        if (value) {
          fieldMappings[dataKey].forEach(function (fieldName) {
            var field = form.querySelector(
              '[name="' + fieldName + '"], [id="' + fieldName + '"]'
            );
            if (field && !field.value) {
              field.value = value;
              this.log("Populated field:", fieldName, value);
            }
          });
        }
      });
    },

    /**
     * Setup message listeners for cross-frame communication
     */
    setupMessageListeners: function () {
      var self = this;

      window.addEventListener("message", function (event) {
        // Handle messages from Zoho forms
        if (event.data && typeof event.data === "object") {
          switch (event.data.type) {
            case "zoho_form_submit":
              self.handleFormSubmission(event.data);
              break;
            case "zoho_form_loaded":
              self.handleFormLoaded(event.data);
              break;
            case "zoho_form_error":
              self.handleFormError(event.data);
              break;
          }
        }
      });
    },

    /**
     * Setup form submission tracking
     */
    setupSubmissionTracking: function () {
      var self = this;

      // Monitor for form submissions via various methods
      this.setupFormSubmitListeners();
      this.setupUrlChangeDetection();
      this.setupThankYouPageDetection();
    },

    /**
     * Setup form submit event listeners
     */
    setupFormSubmitListeners: function () {
      var self = this;

      // Listen for form submissions
      $(document).on("submit", "form", function (e) {
        var form = this;
        var formId = self.getFormIdFromElement(form);

        if (formId && self.forms[formId]) {
          self.trackFormEvent("form_submit_attempt", formId, {
            form_action: form.action,
            form_method: form.method,
          });
        }
      });

      // Listen for Zoho-specific submission events
      $(document).on("click", '[type="submit"]', function () {
        var button = this;
        var form = $(button).closest("form")[0];

        if (form) {
          var formId = self.getFormIdFromElement(form);
          if (formId && self.forms[formId]) {
            // Delay to allow form validation
            setTimeout(function () {
              self.checkForSubmissionSuccess(formId);
            }, 1000);
          }
        }
      });
    },

    /**
     * Setup URL change detection for SPA forms
     */
    setupUrlChangeDetection: function () {
      var self = this;
      var lastUrl = window.location.href;

      setInterval(function () {
        var currentUrl = window.location.href;
        if (currentUrl !== lastUrl) {
          self.handleUrlChange(lastUrl, currentUrl);
          lastUrl = currentUrl;
        }
      }, 1000);
    },

    /**
     * Setup thank you page detection
     */
    setupThankYouPageDetection: function () {
      var self = this;

      // Check if current page is a thank you page
      var thankYouIndicators = [
        "thank-you",
        "thankyou",
        "success",
        "confirmation",
        "submitted",
      ];

      var currentUrl = window.location.href.toLowerCase();
      var isThankYouPage = thankYouIndicators.some(function (indicator) {
        return currentUrl.indexOf(indicator) !== -1;
      });

      if (isThankYouPage) {
        // Check for form ID in URL or referrer
        var formId = this.extractFormIdFromUrl(document.referrer || currentUrl);
        if (formId) {
          this.trackFormEvent("form_submission_success", formId, {
            thank_you_page: currentUrl,
            referrer: document.referrer,
          });
        }
      }
    },

    /**
     * Handle form submission
     */
    handleFormSubmission: function (data) {
      var formId = data.formId || data.form_id;
      if (!formId) return;

      this.trackFormEvent("form_submitted", formId, data);

      // Send submission data to server
      this.sendSubmissionToServer(formId, data);
    },

    /**
     * Handle form loaded event
     */
    handleFormLoaded: function (data) {
      var formId = data.formId || data.form_id;
      if (formId && !this.forms[formId]) {
        this.forms[formId] = {
          formId: formId,
          populated: false,
          submitted: false,
        };
        this.populateForm(formId);
      }
    },

    /**
     * Handle form error
     */
    handleFormError: function (data) {
      var formId = data.formId || data.form_id;
      this.trackFormEvent("form_error", formId, data);
    },

    /**
     * Handle URL changes
     */
    handleUrlChange: function (oldUrl, newUrl) {
      // Check if navigated to a thank you page
      var thankYouIndicators = [
        "thank-you",
        "thankyou",
        "success",
        "confirmation",
      ];
      var isThankYou = thankYouIndicators.some(function (indicator) {
        return newUrl.toLowerCase().indexOf(indicator) !== -1;
      });

      if (isThankYou) {
        var formId = this.extractFormIdFromUrl(oldUrl);
        if (formId) {
          this.trackFormEvent("form_submission_redirect", formId, {
            old_url: oldUrl,
            new_url: newUrl,
          });
        }
      }
    },

    /**
     * Check for submission success
     */
    checkForSubmissionSuccess: function (formId) {
      var formData = this.forms[formId];
      if (!formData || formData.submitted) return;

      // Look for success indicators
      var successIndicators = [
        ".success-message",
        ".thank-you",
        ".confirmation",
        '[data-success="true"]',
      ];

      var hasSuccess = successIndicators.some(function (selector) {
        return document.querySelector(selector) !== null;
      });

      if (hasSuccess) {
        formData.submitted = true;
        this.trackFormEvent("form_submission_detected", formId);
      }
    },

    /**
     * Track form events
     */
    trackFormEvent: function (eventType, formId, additionalData) {
      var eventData = $.extend(
        {
          form_id: formId,
          page_url: window.location.href,
          timestamp: Date.now(),
        },
        additionalData || {}
      );

      // Use main tracking system
      if (
        window.AffiliateClientTracker &&
        window.AffiliateClientTracker.initialized
      ) {
        window.AffiliateClientTracker.trackEvent(
          "zoho_" + eventType,
          eventData
        );
      }

      this.log("Tracked event:", eventType, eventData);
    },

    /**
     * Send submission data to server
     */
    sendSubmissionToServer: function (formId, submissionData) {
      var self = this;
      var trackingData = this.config.trackingData;

      var payload = {
        action: "affiliate_client_zoho_webhook",
        nonce: this.config.nonce,
        webhook_data: $.extend(
          {
            form_id: formId,
            page_url: window.location.href,
            referrer: document.referrer,
            timestamp: new Date().toISOString(),
          },
          submissionData,
          trackingData
        ),
      };

      $.ajax({
        url: this.config.ajaxUrl,
        method: "POST",
        data: payload,
        success: function (response) {
          self.log("Submission sent to server:", response);
        },
        error: function (xhr, status, error) {
          self.log("Failed to send submission:", error);
        },
      });
    },

    /**
     * Get form ID from element
     */
    getFormIdFromElement: function (element) {
      return (
        element.getAttribute("data-zoho-form") ||
        element.getAttribute("data-form-id") ||
        element.id ||
        this.extractFormIdFromUrl(element.action)
      );
    },

    /**
     * Extract form ID from URL
     */
    extractFormIdFromUrl: function (url) {
      if (!url) return null;

      var patterns = [
        /form[\/=]([a-zA-Z0-9]+)/,
        /formId[=]([a-zA-Z0-9]+)/,
        /\/([a-zA-Z0-9]+)\/formperma/,
        /form_id[=]([a-zA-Z0-9]+)/,
      ];

      for (var i = 0; i < patterns.length; i++) {
        var match = url.match(patterns[i]);
        if (match) {
          return match[1];
        }
      }

      return null;
    },

    /**
     * Generate tracking URL for Zoho forms
     */
    generateTrackingUrl: function (baseUrl, customParams) {
      var trackingData = this.config.trackingData;
      var urlParams = new URLSearchParams();

      // Add affiliate tracking parameters
      if (trackingData.affiliateId) {
        urlParams.append("SingleLine", trackingData.affiliateId);
        urlParams.append("ref", trackingData.affiliateId);
      }

      if (trackingData.visitId) {
        urlParams.append("SingleLine1", trackingData.visitId);
      }

      if (trackingData.utmSource) {
        urlParams.append("SingleLine2", trackingData.utmSource);
        urlParams.append("utm_source", trackingData.utmSource);
      }

      if (trackingData.utmCampaign) {
        urlParams.append("SingleLine3", trackingData.utmCampaign);
        urlParams.append("utm_campaign", trackingData.utmCampaign);
      }

      // Add custom parameters
      if (customParams) {
        Object.keys(customParams).forEach(function (key) {
          urlParams.append(key, customParams[key]);
        });
      }

      var separator = baseUrl.indexOf("?") !== -1 ? "&" : "?";
      return baseUrl + separator + urlParams.toString();
    },

    /**
     * Create Zoho form with tracking
     */
    createTrackedForm: function (formId, options) {
      var defaults = {
        width: "100%",
        height: "600px",
        autoPopulate: true,
        trackSubmissions: true,
      };

      var settings = $.extend(defaults, options);
      var baseUrl =
        "https://forms.zohopublic.com/yourorganization/form/" +
        formId +
        "/formperma";
      var trackedUrl = settings.autoPopulate
        ? this.generateTrackingUrl(baseUrl)
        : baseUrl;

      var iframe = $("<iframe>")
        .attr({
          src: trackedUrl,
          width: settings.width,
          height: settings.height,
          frameborder: "0",
          marginheight: "0",
          marginwidth: "0",
          scrolling: "auto",
        })
        .addClass("affiliate-zoho-form");

      if (settings.trackSubmissions) {
        this.registerForm(iframe[0], formId);
      }

      return iframe;
    },

    /**
     * Get integration statistics
     */
    getStats: function () {
      return {
        totalForms: Object.keys(this.forms).length,
        populatedForms: Object.values(this.forms).filter(function (f) {
          return f.populated;
        }).length,
        submittedForms: Object.values(this.forms).filter(function (f) {
          return f.submitted;
        }).length,
        trackingData: this.config.trackingData,
        observers: this.observers.length,
      };
    },

    /**
     * Cleanup observers
     */
    cleanup: function () {
      this.observers.forEach(function (observer) {
        observer.disconnect();
      });
      this.observers = [];
    },

    /**
     * Log debug messages
     */
    log: function () {
      if (window.console && console.log) {
        var args = Array.prototype.slice.call(arguments);
        args.unshift("[Affiliate Client Zoho]");
        console.log.apply(console, args);
      }
    },
  };

  // Auto-initialize when config is available
  $(document).ready(function () {
    if (typeof affiliateClientZoho !== "undefined") {
      AffiliateClientZoho.init(affiliateClientZoho);
    }
  });

  // Cleanup on page unload
  $(window).on("beforeunload", function () {
    if (window.AffiliateClientZoho) {
      AffiliateClientZoho.cleanup();
    }
  });

  // Expose to global scope
  window.AffiliateClientZoho = AffiliateClientZoho;
})(jQuery);
