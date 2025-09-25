/**
 * Affiliate Client Full - Addon Event Handlers
 *
 * Enhanced tracking for supported WordPress plugins and addons.
 * Provides specialized tracking for e-commerce, forms, and membership plugins.
 *
 * @package AffiliateClientFull
 * @version 1.0.0
 */

(function ($) {
  "use strict";

  // Addon tracking object
  window.AffiliateClientAddons = {
    // Configuration
    config: {},

    // Supported addons
    addons: {},

    /**
     * Initialize addon handlers
     */
    init: function (addons) {
      this.addons = addons || {};

      // Initialize each enabled addon
      for (var addon in this.addons) {
        if (this.addons[addon].enabled) {
          this.initAddon(addon);
        }
      }

      // Set up universal handlers
      this.setupUniversalHandlers();

      console.log(
        "[Affiliate Client Addons] Initialized with addons:",
        Object.keys(this.addons)
      );
    },

    /**
     * Initialize specific addon
     */
    initAddon: function (addonName) {
      switch (addonName) {
        case "woocommerce":
          this.initWooCommerce();
          break;
        case "easy_digital_downloads":
          this.initEDD();
          break;
        case "memberpress":
          this.initMemberPress();
          break;
        case "lifter_lms":
          this.initLifterLMS();
          break;
        case "gravity_forms":
          this.initGravityForms();
          break;
        case "contact_form_7":
          this.initContactForm7();
          break;
      }
    },

    /**
     * WooCommerce integration
     */
    initWooCommerce: function () {
      var self = this;

      // Track add to cart events
      $(document).on(
        "added_to_cart",
        function (event, fragments, cart_hash, button) {
          var productId = button.data("product_id");
          var quantity = button.data("quantity") || 1;
          var productName = button
            .closest(".product")
            .find(".woocommerce-loop-product__title, h1.product_title")
            .text();
          var productPrice = self.extractPrice(
            button.closest(".product").find(".price .amount").text()
          );

          self.trackEvent("wc_add_to_cart", {
            product_id: productId,
            product_name: productName,
            product_price: productPrice,
            quantity: quantity,
            cart_hash: cart_hash,
          });
        }
      );

      // Track remove from cart
      $(document).on("click", ".remove", function () {
        var $row = $(this).closest("tr");
        var productName = $row.find(".product-name a").text();

        self.trackEvent("wc_remove_from_cart", {
          product_name: productName,
        });
      });

      // Track checkout events
      $("form.checkout").on("submit", function () {
        var checkoutData = self.getWooCommerceCheckoutData();
        self.trackEvent("wc_checkout_submit", checkoutData);
      });

      // Track payment method selection
      $(document).on("change", 'input[name="payment_method"]', function () {
        self.trackEvent("wc_payment_method_selected", {
          payment_method: $(this).val(),
        });
      });

      // Track coupon usage
      $(document).on(
        "applied_coupon_in_checkout",
        function (event, couponCode) {
          self.trackEvent("wc_coupon_applied", {
            coupon_code: couponCode,
          });
        }
      );

      // Track product view
      if ($("body").hasClass("single-product")) {
        var productId = this.getWooCommerceProductId();
        var productData = this.getWooCommerceProductData();

        this.trackEvent("wc_product_view", {
          product_id: productId,
          product_data: productData,
        });
      }

      // Track category view
      if ($("body").hasClass("tax-product_cat")) {
        var categoryName =
          $(".page-title .taxonomy-description").text() || document.title;
        this.trackEvent("wc_category_view", {
          category_name: categoryName,
        });
      }
    },

    /**
     * Easy Digital Downloads integration
     */
    initEDD: function () {
      var self = this;

      // Track add to cart
      $(document).on("edd_cart_item_added", function (event, response) {
        self.trackEvent("edd_add_to_cart", {
          download_id: response.download_id,
          download_name: response.download_name || "",
          cart_total: response.cart_total,
        });
      });

      // Track remove from cart
      $(document).on("edd_cart_item_removed", function (event, response) {
        self.trackEvent("edd_remove_from_cart", {
          download_id: response.download_id,
          cart_total: response.cart_total,
        });
      });

      // Track discount application
      $(document).on("edd_discount_applied", function (event, response) {
        self.trackEvent("edd_discount_applied", {
          discount_code: response.code,
          discount_amount: response.amount,
        });
      });

      // Track checkout
      $(".edd-checkout").on("submit", function () {
        var checkoutData = self.getEDDCheckoutData();
        self.trackEvent("edd_checkout_submit", checkoutData);
      });

      // Track download view
      if ($("body").hasClass("single-download")) {
        var downloadId = this.getEDDDownloadId();
        var downloadData = this.getEDDDownloadData();

        this.trackEvent("edd_download_view", {
          download_id: downloadId,
          download_data: downloadData,
        });
      }
    },

    /**
     * MemberPress integration
     */
    initMemberPress: function () {
      var self = this;

      // Track membership level view
      if ($("body").hasClass("single-memberpressproduct")) {
        var membershipId = this.getMemberPressProductId();
        this.trackEvent("mp_membership_view", {
          membership_id: membershipId,
          membership_title: document.title,
        });
      }

      // Track signup form submission
      $(".mepr-signup-form").on("submit", function () {
        var membershipId = $(this).find('input[name="mepr_product_id"]').val();
        self.trackEvent("mp_signup_submit", {
          membership_id: membershipId,
        });
      });

      // Track login
      $(".mepr-login-form").on("submit", function () {
        self.trackEvent("mp_login_attempt");
      });
    },

    /**
     * LifterLMS integration
     */
    initLifterLMS: function () {
      var self = this;

      // Track course view
      if ($("body").hasClass("single-course")) {
        var courseId = this.getLLMSCourseId();
        this.trackEvent("llms_course_view", {
          course_id: courseId,
          course_title: document.title,
        });
      }

      // Track lesson view
      if ($("body").hasClass("single-lesson")) {
        var lessonId = this.getLLMSLessonId();
        this.trackEvent("llms_lesson_view", {
          lesson_id: lessonId,
          lesson_title: document.title,
        });
      }

      // Track enrollment button clicks
      $(document).on(
        "click",
        '.llms-button-action[href*="enroll"]',
        function () {
          var courseId = $(this).data("id") || self.getLLMSCourseId();
          self.trackEvent("llms_enroll_click", {
            course_id: courseId,
          });
        }
      );

      // Track quiz start
      $(document).on(
        "click",
        ".llms-button-primary.llms-start-quiz",
        function () {
          var quizId = $(this).data("id");
          self.trackEvent("llms_quiz_start", {
            quiz_id: quizId,
          });
        }
      );
    },

    /**
     * Gravity Forms integration
     */
    initGravityForms: function () {
      var self = this;

      // Track form submissions
      $(document).on("gform_confirmation_loaded", function (event, formId) {
        self.trackEvent("gf_form_completed", {
          form_id: formId,
          form_title: $("#gform_title_" + formId).text(),
        });
      });

      // Track form validation errors
      $(document).on(
        "gform_post_render",
        function (event, formId, currentPage) {
          var $form = $("#gform_" + formId);
          if ($form.find(".validation_error").length > 0) {
            self.trackEvent("gf_validation_error", {
              form_id: formId,
              current_page: currentPage,
            });
          }
        }
      );

      // Track multi-page navigation
      $(document).on(
        "click",
        ".gform_next_button, .gform_previous_button",
        function () {
          var $form = $(this).closest("form");
          var formId = $form.attr("id").replace("gform_", "");
          var direction = $(this).hasClass("gform_next_button")
            ? "next"
            : "previous";

          self.trackEvent("gf_page_navigation", {
            form_id: formId,
            direction: direction,
          });
        }
      );
    },

    /**
     * Contact Form 7 integration
     */
    initContactForm7: function () {
      var self = this;

      // Track form submissions
      $(document).on("wpcf7mailsent", function (event) {
        var $form = $(event.target);
        var formId = $form.find('input[name="_wpcf7"]').val();

        self.trackEvent("cf7_form_sent", {
          form_id: formId,
          form_title: $form.find(".wpcf7-form-title").text() || "Contact Form",
        });
      });

      // Track form errors
      $(document).on("wpcf7invalid", function (event) {
        var $form = $(event.target);
        var formId = $form.find('input[name="_wpcf7"]').val();

        self.trackEvent("cf7_form_error", {
          form_id: formId,
        });
      });

      // Track spam detection
      $(document).on("wpcf7spam", function (event) {
        var $form = $(event.target);
        var formId = $form.find('input[name="_wpcf7"]').val();

        self.trackEvent("cf7_spam_detected", {
          form_id: formId,
        });
      });
    },

    /**
     * Setup universal handlers for all addons
     */
    setupUniversalHandlers: function () {
      var self = this;

      // Track video interactions
      $("video").on("play", function () {
        self.trackEvent("video_play", {
          video_src: this.src || this.currentSrc,
          video_duration: this.duration,
        });
      });

      $("video").on("ended", function () {
        self.trackEvent("video_completed", {
          video_src: this.src || this.currentSrc,
          video_duration: this.duration,
        });
      });

      // Track PDF downloads
      $(document).on("click", 'a[href$=".pdf"]', function () {
        self.trackEvent("pdf_download", {
          pdf_url: this.href,
          pdf_name: this.href.split("/").pop(),
        });
      });

      // Track search usage
      $('.search-form, [role="search"] form').on("submit", function () {
        var query = $(this).find('input[type="search"], input[name="s"]').val();
        if (query) {
          self.trackEvent("site_search", {
            search_query: query,
          });
        }
      });

      // Track newsletter signups
      $(
        'form[id*="newsletter"], form[class*="newsletter"], form[id*="subscribe"], form[class*="subscribe"]'
      ).on("submit", function () {
        var email = $(this).find('input[type="email"]').val();
        self.trackEvent("newsletter_signup", {
          has_email: !!email,
        });
      });

      // Track social media clicks
      $(
        'a[href*="facebook.com"], a[href*="twitter.com"], a[href*="instagram.com"], a[href*="linkedin.com"], a[href*="youtube.com"]'
      ).on("click", function () {
        var platform = this.hostname.replace("www.", "").split(".")[0];
        self.trackEvent("social_click", {
          platform: platform,
          url: this.href,
        });
      });
    },

    /**
     * Track addon-specific event
     */
    trackEvent: function (eventType, data) {
      if (
        window.AffiliateClientTracker &&
        window.AffiliateClientTracker.initialized
      ) {
        window.AffiliateClientTracker.trackEvent(
          eventType,
          $.extend(
            {
              addon_tracking: true,
              timestamp: Math.floor(Date.now() / 1000),
            },
            data || {}
          )
        );
      }
    },

    /**
     * Extract price from text
     */
    extractPrice: function (priceText) {
      if (!priceText) return 0;
      var cleanPrice = priceText.replace(/[^\d.,]/g, "");
      return parseFloat(cleanPrice) || 0;
    },

    /**
     * Get WooCommerce product ID
     */
    getWooCommerceProductId: function () {
      var productId =
        $('input[name="product_id"]').val() ||
        $('button[name="add-to-cart"]').val() ||
        $("form.cart").find('input[name="add-to-cart"]').val();
      return productId || null;
    },

    /**
     * Get WooCommerce product data
     */
    getWooCommerceProductData: function () {
      return {
        name: $(".product_title").text(),
        price: this.extractPrice($(".price .amount").first().text()),
        sku: $(".sku").text(),
        categories: $(".posted_in a")
          .map(function () {
            return $(this).text();
          })
          .get(),
        tags: $(".tagged_as a")
          .map(function () {
            return $(this).text();
          })
          .get(),
        in_stock: !$(".out-of-stock").length,
      };
    },

    /**
     * Get WooCommerce checkout data
     */
    getWooCommerceCheckoutData: function () {
      var data = {
        billing_country: $("#billing_country").val(),
        shipping_country: $("#shipping_country").val(),
        payment_method: $('input[name="payment_method"]:checked').val(),
        has_account: $("#createaccount:checked").length > 0,
        cart_total: this.extractPrice($(".order-total .amount").text()),
      };

      // Get applied coupons
      var coupons = $(".cart-discount .coupon .amount")
        .map(function () {
          return $(this).text();
        })
        .get();
      if (coupons.length > 0) {
        data.coupons = coupons;
      }

      return data;
    },

    /**
     * Get EDD download ID
     */
    getEDDDownloadId: function () {
      var downloadId =
        $('input[name="edd_download_id"]').val() ||
        $('button[name="edd_purchase_download"]').val();
      return downloadId || null;
    },

    /**
     * Get EDD download data
     */
    getEDDDownloadData: function () {
      return {
        name: $(".edd_download_title, h1").first().text(),
        price: this.extractPrice($(".edd_price").text()),
        categories: $(".edd_download_categories a")
          .map(function () {
            return $(this).text();
          })
          .get(),
        tags: $(".edd_download_tags a")
          .map(function () {
            return $(this).text();
          })
          .get(),
      };
    },

    /**
     * Get EDD checkout data
     */
    getEDDCheckoutData: function () {
      return {
        payment_method: $("#edd-gateway option:selected").val(),
        discount_code: $("#edd-discount").val(),
        cart_total: this.extractPrice(
          $("#edd_final_total_wrap .edd_cart_amount").text()
        ),
        has_account: $("#edd_register_account:checked").length > 0,
      };
    },

    /**
     * Get MemberPress product ID
     */
    getMemberPressProductId: function () {
      var productId =
        $('input[name="mepr_product_id"]').val() ||
        $(".mepr-product-id").data("product-id");
      return productId || null;
    },

    /**
     * Get LifterLMS course ID
     */
    getLLMSCourseId: function () {
      var courseId =
        $(".llms-course").data("id") ||
        $("body")
          .attr("class")
          .match(/postid-(\d+)/);
      return courseId ? courseId[1] || courseId : null;
    },

    /**
     * Get LifterLMS lesson ID
     */
    getLLMSLessonId: function () {
      var lessonId =
        $(".llms-lesson").data("id") ||
        $("body")
          .attr("class")
          .match(/postid-(\d+)/);
      return lessonId ? lessonId[1] || lessonId : null;
    },

    /**
     * Enhanced e-commerce tracking
     */
    trackEcommerceEvent: function (action, data) {
      var ecommerceData = $.extend(
        {
          ecommerce_action: action,
          currency: this.detectCurrency(),
          timestamp: Math.floor(Date.now() / 1000),
        },
        data || {}
      );

      this.trackEvent("ecommerce_" + action, ecommerceData);
    },

    /**
     * Detect currency from page
     */
    detectCurrency: function () {
      // Try WooCommerce
      var wcCurrency = $(".woocommerce-Price-currencySymbol").first().text();
      if (wcCurrency) return wcCurrency;

      // Try EDD
      var eddCurrency = $(".edd-cart-meta .currency").first().text();
      if (eddCurrency) return eddCurrency;

      // Try data attributes
      var dataCurrency = $("[data-currency]").first().data("currency");
      if (dataCurrency) return dataCurrency;

      // Default
      return "USD";
    },

    /**
     * Track form abandonment
     */
    setupFormAbandonmentTracking: function () {
      var self = this;
      var formData = {};

      // Track form interactions
      $("form").each(function () {
        var $form = $(this);
        var formId = this.id || "form_" + $("form").index(this);

        formData[formId] = {
          interacted: false,
          startTime: null,
          fields: {},
        };

        $form.on("focusin", "input, select, textarea", function () {
          if (!formData[formId].interacted) {
            formData[formId].interacted = true;
            formData[formId].startTime = Date.now();

            self.trackEvent("form_started", {
              form_id: formId,
              form_action: $form.attr("action") || "",
              field_count: $form.find("input, select, textarea").length,
            });
          }
        });

        $form.on("change", "input, select, textarea", function () {
          var fieldName = this.name || this.id || "unnamed_field";
          formData[formId].fields[fieldName] = true;
        });
      });

      // Track abandonments on page unload
      $(window).on("beforeunload", function () {
        for (var formId in formData) {
          var form = formData[formId];
          if (form.interacted && form.startTime) {
            var timeSpent = Math.floor((Date.now() - form.startTime) / 1000);
            var fieldsCompleted = Object.keys(form.fields).length;

            if (timeSpent > 10 && fieldsCompleted > 0) {
              // Minimum interaction threshold
              self.trackEvent("form_abandoned", {
                form_id: formId,
                time_spent_seconds: timeSpent,
                fields_completed: fieldsCompleted,
              });
            }
          }
        }
      });
    },

    /**
     * Track scroll milestones
     */
    setupScrollMilestones: function () {
      var self = this;
      var milestones = [25, 50, 75, 90, 100];
      var tracked = {};

      $(window).on(
        "scroll",
        $.throttle(500, function () {
          var scrollTop = $(window).scrollTop();
          var documentHeight = $(document).height();
          var windowHeight = $(window).height();
          var scrollPercent = Math.round(
            (scrollTop / (documentHeight - windowHeight)) * 100
          );

          milestones.forEach(function (milestone) {
            if (scrollPercent >= milestone && !tracked[milestone]) {
              tracked[milestone] = true;
              self.trackEvent("scroll_milestone", {
                milestone_percent: milestone,
                page_url: window.location.href,
              });
            }
          });
        })
      );
    },

    /**
     * Track engagement time
     */
    setupEngagementTracking: function () {
      var self = this;
      var startTime = Date.now();
      var isActive = true;
      var totalActiveTime = 0;
      var lastActiveTime = startTime;

      // Track active/inactive states
      $(document).on("visibilitychange", function () {
        var now = Date.now();

        if (document.hidden) {
          if (isActive) {
            totalActiveTime += now - lastActiveTime;
            isActive = false;
          }
        } else {
          isActive = true;
          lastActiveTime = now;
        }
      });

      // Track on mouse/keyboard activity
      var activityTimeout;
      $(document).on("mousemove keypress scroll click", function () {
        var now = Date.now();

        if (!isActive) {
          isActive = true;
          lastActiveTime = now;
        }

        clearTimeout(activityTimeout);
        activityTimeout = setTimeout(function () {
          if (isActive) {
            totalActiveTime += Date.now() - lastActiveTime;
            isActive = false;
          }
        }, 30000); // 30 seconds of inactivity
      });

      // Send engagement data periodically
      setInterval(function () {
        var currentActiveTime = totalActiveTime;
        if (isActive) {
          currentActiveTime += Date.now() - lastActiveTime;
        }

        if (currentActiveTime > 10000) {
          // Only track if more than 10 seconds
          self.trackEvent("engagement_time", {
            active_time_seconds: Math.floor(currentActiveTime / 1000),
            total_time_seconds: Math.floor((Date.now() - startTime) / 1000),
          });
        }
      }, 60000); // Every minute
    },
  };

  // Auto-initialize when addon config is available
  $(document).ready(function () {
    if (typeof affiliateClientAddons !== "undefined") {
      AffiliateClientAddons.init(affiliateClientAddons);
    }

    // Setup additional tracking features
    AffiliateClientAddons.setupFormAbandonmentTracking();
    AffiliateClientAddons.setupScrollMilestones();
    AffiliateClientAddons.setupEngagementTracking();
  });

  // jQuery throttle function
  $.throttle =
    $.throttle ||
    function (delay, func) {
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

  // Expose to global scope
  window.AffiliateClientAddons = AffiliateClientAddons;
})(jQuery);
