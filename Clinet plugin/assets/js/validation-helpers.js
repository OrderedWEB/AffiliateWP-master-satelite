/**
 * Validation Helpers JavaScript
 * File: /wp-content/plugins/affiliate-client-integration/assets/js/validation-helpers.js
 * Plugin: Affiliate Client Integration
 */

(function ($) {
  "use strict";

  window.ACI = window.ACI || {};

  /**
   * Validation Helpers Class
   */
  ACI.Validation = {
    // Configuration
    config: {
      realTimeValidation: true,
      showErrorsInline: true,
      validateOnBlur: true,
      validateOnSubmit: true,
      errorClass: "aci-error",
      successClass: "aci-success",
      errorMessageClass: "aci-error-message",
    },

    // Validation rules
    rules: {
      required: {
        validate: (value) =>
          value !== null &&
          value !== undefined &&
          String(value).trim().length > 0,
        message: "This field is required",
      },
      email: {
        validate: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
        message: "Please enter a valid email address",
      },
      phone: {
        validate: (value) =>
          /^[\+]?[1-9][\d]{0,15}$/.test(value.replace(/[\s\-\(\)]/g, "")),
        message: "Please enter a valid phone number",
      },
      url: {
        validate: (value) => {
          try {
            new URL(value);
            return true;
          } catch {
            return false;
          }
        },
        message: "Please enter a valid URL",
      },
      number: {
        validate: (value) => !isNaN(value) && isFinite(value),
        message: "Please enter a valid number",
      },
      integer: {
        validate: (value) => Number.isInteger(Number(value)),
        message: "Please enter a whole number",
      },
      positive: {
        validate: (value) => Number(value) > 0,
        message: "Please enter a positive number",
      },
      min: {
        validate: (value, params) => Number(value) >= Number(params[0]),
        message: (params) => `Value must be at least ${params[0]}`,
      },
      max: {
        validate: (value, params) => Number(value) <= Number(params[0]),
        message: (params) => `Value must be no more than ${params[0]}`,
      },
      minLength: {
        validate: (value, params) => String(value).length >= Number(params[0]),
        message: (params) => `Must be at least ${params[0]} characters`,
      },
      maxLength: {
        validate: (value, params) => String(value).length <= Number(params[0]),
        message: (params) => `Must be no more than ${params[0]} characters`,
      },
      pattern: {
        validate: (value, params) => new RegExp(params[0]).test(value),
        message: (params) => params[1] || "Invalid format",
      },
      equalTo: {
        validate: (value, params, $field) => {
          const $targetField = $(params[0]);
          return value === $targetField.val();
        },
        message: "Fields must match",
      },
      affiliateCode: {
        validate: (value) => /^[a-zA-Z0-9_-]{2,50}$/.test(value),
        message:
          "Affiliate code must be 2-50 characters, letters, numbers, hyphens and underscores only",
      },
      creditCard: {
        validate: (value) => {
          const num = value.replace(/\s/g, "");
          return /^\d{13,19}$/.test(num) && ACI.Validation.luhnCheck(num);
        },
        message: "Please enter a valid credit card number",
      },
      cvv: {
        validate: (value) => /^\d{3,4}$/.test(value),
        message: "Please enter a valid CVV",
      },
      expiryDate: {
        validate: (value) => {
          const parts = value.split("/");
          if (parts.length !== 2) return false;

          const month = parseInt(parts[0]);
          const year = parseInt("20" + parts[1]);
          const now = new Date();
          const expiry = new Date(year, month - 1);

          return month >= 1 && month <= 12 && expiry > now;
        },
        message: "Please enter a valid expiry date (MM/YY)",
      },
      zipCode: {
        validate: (value, params, $field) => {
          const country =
            $field.closest("form").find('[data-validate*="country"]').val() ||
            "US";
          const patterns = {
            US: /^\d{5}(-\d{4})?$/,
            CA: /^[A-Za-z]\d[A-Za-z][ -]?\d[A-Za-z]\d$/,
            GB: /^[A-Z]{1,2}\d[A-Z\d]? ?\d[A-Z]{2}$/i,
            DE: /^\d{5}$/,
            FR: /^\d{5}$/,
          };

          const pattern = patterns[country] || patterns["US"];
          return pattern.test(value);
        },
        message: "Please enter a valid postal code",
      },
    },

    // Form states
    formStates: new Map(),

    /**
     * Initialize validation
     */
    init: function () {
      this.loadConfig();
      this.bindEvents();
      this.initializeForms();

      console.log("ACI Validation initialized");
    },

    /**
     * Load configuration
     */
    loadConfig: function () {
      if (typeof aci_validation_config !== "undefined") {
        this.config = $.extend(this.config, aci_validation_config);
      }
    },

    /**
     * Bind events
     */
    bindEvents: function () {
      // Real-time validation on input
      if (this.config.realTimeValidation) {
        $(document).on("input keyup", "[data-validate]", (e) => {
          const $field = $(e.target);
          this.debounce(() => this.validateField($field), 300)();
        });
      }

      // Validation on blur
      if (this.config.validateOnBlur) {
        $(document).on("blur", "[data-validate]", (e) => {
          this.validateField($(e.target));
        });
      }

      // Form submission validation
      if (this.config.validateOnSubmit) {
        $(document).on("submit", ".aci-form", (e) => {
          if (!this.validateForm($(e.target))) {
            e.preventDefault();
            return false;
          }
        });
      }

      // Clear validation on focus
      $(document).on("focus", "[data-validate]", (e) => {
        this.clearFieldValidation($(e.target));
      });

      // Custom validation triggers
      $(document).on("aci:validate_field", (e, fieldSelector) => {
        this.validateField($(fieldSelector));
      });

      $(document).on("aci:validate_form", (e, formSelector) => {
        this.validateForm($(formSelector));
      });

      // Affiliate code validation
      $(document).on("input", ".aci-affiliate-code-input", (e) => {
        this.validateAffiliateCode($(e.target));
      });
    },

    /**
     * Initialize forms
     */
    initializeForms: function () {
      $(".aci-form").each((index, form) => {
        this.initializeForm($(form));
      });
    },

    /**
     * Initialize individual form
     */
    initializeForm: function ($form) {
      const formId = $form.attr("id") || "form_" + Date.now();
      $form.attr("id", formId);

      this.formStates.set(formId, {
        isValid: false,
        validatedFields: new Set(),
        errors: new Map(),
      });

      // Add validation indicators
      $form.find("[data-validate]").each((index, field) => {
        this.setupFieldValidation($(field));
      });
    },

    /**
     * Setup field validation
     */
    setupFieldValidation: function ($field) {
      if (!$field.next(".aci-validation-indicator").length) {
        $field.after('<span class="aci-validation-indicator"></span>');
      }

      if (!$field.siblings("." + this.config.errorMessageClass).length) {
        $field.after(
          `<div class="${this.config.errorMessageClass}" style="display:none;"></div>`
        );
      }
    },

    /**
     * Validate field
     */
    validateField: function ($field) {
      const value = $field.val();
      const rules = this.parseValidationRules($field.data("validate"));
      const fieldName = $field.attr("name") || $field.attr("id") || "field";
      const errors = [];

      // Run validation rules
      for (const rule of rules) {
        const ruleName = rule.name;
        const ruleParams = rule.params;

        if (this.rules[ruleName]) {
          const isValid = this.rules[ruleName].validate(
            value,
            ruleParams,
            $field
          );

          if (!isValid) {
            let message = this.rules[ruleName].message;
            if (typeof message === "function") {
              message = message(ruleParams);
            }
            errors.push(message);
            break; // Stop at first error
          }
        }
      }

      // Update field state
      const formId = $field.closest(".aci-form").attr("id");
      if (formId) {
        const formState = this.formStates.get(formId);
        if (formState) {
          formState.validatedFields.add(fieldName);

          if (errors.length > 0) {
            formState.errors.set(fieldName, errors[0]);
          } else {
            formState.errors.delete(fieldName);
          }
        }
      }

      // Display validation result
      this.displayFieldValidation($field, errors);

      return errors.length === 0;
    },

    /**
     * Parse validation rules from data attribute
     */
    parseValidationRules: function (rulesString) {
      if (!rulesString) return [];

      const rules = [];
      const ruleParts = rulesString.split("|");

      for (const rulePart of ruleParts) {
        const [ruleName, ...params] = rulePart.split(":");
        rules.push({
          name: ruleName.trim(),
          params: params.length > 0 ? params.join(":").split(",") : [],
        });
      }

      return rules;
    },

    /**
     * Display field validation result
     */
    displayFieldValidation: function ($field, errors) {
      const $indicator = $field.next(".aci-validation-indicator");
      const $errorMessage = $field.siblings(
        "." + this.config.errorMessageClass
      );

      // Remove existing classes
      $field.removeClass(
        this.config.errorClass + " " + this.config.successClass
      );

      if (errors.length > 0) {
        // Show error state
        $field.addClass(this.config.errorClass);
        $indicator.html("✗").attr("title", errors[0]);

        if (this.config.showErrorsInline) {
          $errorMessage.text(errors[0]).show();
        }
      } else if ($field.val()) {
        // Show success state
        $field.addClass(this.config.successClass);
        $indicator.html("✓").attr("title", "Valid");
        $errorMessage.hide();
      } else {
        // Neutral state
        $indicator.html("").attr("title", "");
        $errorMessage.hide();
      }
    },

    /**
     * Clear field validation
     */
    clearFieldValidation: function ($field) {
      $field.removeClass(
        this.config.errorClass + " " + this.config.successClass
      );
      $field.next(".aci-validation-indicator").html("").attr("title", "");
      $field.siblings("." + this.config.errorMessageClass).hide();
    },

    /**
     * Validate entire form
     */
    validateForm: function ($form) {
      let isFormValid = true;
      const $fields = $form.find("[data-validate]");

      // Validate each field
      $fields.each((index, field) => {
        const $field = $(field);
        if (!this.validateField($field)) {
          isFormValid = false;
        }
      });

      // Update form state
      const formId = $form.attr("id");
      if (formId) {
        const formState = this.formStates.get(formId);
        if (formState) {
          formState.isValid = isFormValid;
        }
      }

      // Show form-level errors
      if (!isFormValid) {
        this.showFormErrors($form);
      } else {
        this.hideFormErrors($form);
      }

      // Trigger events
      const eventData = { valid: isFormValid, form: $form[0] };
      $(document).trigger("aci:form_validated", [eventData]);

      return isFormValid;
    },

    /**
     * Show form errors
     */
    showFormErrors: function ($form) {
      let $errorContainer = $form.find(".aci-form-errors");

      if (!$errorContainer.length) {
        $errorContainer = $('<div class="aci-form-errors"></div>');
        $form.prepend($errorContainer);
      }

      const formId = $form.attr("id");
      const formState = this.formStates.get(formId);

      if (formState && formState.errors.size > 0) {
        let errorHtml = "<ul>";
        formState.errors.forEach((error, fieldName) => {
          errorHtml += `<li>${error}</li>`;
        });
        errorHtml += "</ul>";

        $errorContainer
          .html(
            `
                    <div class="aci-error-message">
                        <strong>Please correct the following errors:</strong>
                        ${errorHtml}
                    </div>
                `
          )
          .show();

        // Scroll to first error
        const $firstError = $form.find("." + this.config.errorClass).first();
        if ($firstError.length) {
          $firstError[0].scrollIntoView({
            behavior: "smooth",
            block: "center",
          });
          $firstError.focus();
        }
      }
    },

    /**
     * Hide form errors
     */
    hideFormErrors: function ($form) {
      $form.find(".aci-form-errors").hide();
    },

    /**
     * Validate affiliate code
     */
    validateAffiliateCode: function ($field) {
      const code = $field.val().trim();

      if (!code) {
        this.clearFieldValidation($field);
        return;
      }

      // Basic format validation
      if (!this.rules.affiliateCode.validate(code)) {
        this.displayFieldValidation($field, [this.rules.affiliateCode.message]);
        return false;
      }

      // Server validation
      this.validateAffiliateCodeServer(code, $field);
    },

    /**
     * Validate affiliate code with server
     */
    validateAffiliateCodeServer: function (code, $field) {
      // Show loading state
      const $indicator = $field.next(".aci-validation-indicator");
      $indicator.html("⟳").attr("title", "Validating...");

      $.ajax({
        url: aci_ajax.ajax_url,
        type: "POST",
        data: {
          action: "aci_validate_affiliate",
          affiliate_code: code,
          nonce: aci_ajax.nonce,
        },
        timeout: 5000,
        success: (response) => {
          if (response.success && response.data.valid) {
            this.displayFieldValidation($field, []);
            $(document).trigger("aci:affiliate_validated", [
              code,
              response.data,
            ]);
          } else {
            this.displayFieldValidation($field, [
              "Invalid or expired affiliate code",
            ]);
          }
        },
        error: () => {
          this.displayFieldValidation($field, [
            "Unable to validate affiliate code",
          ]);
        },
      });
    },

    /**
     * Luhn algorithm for credit card validation
     */
    luhnCheck: function (num) {
      let sum = 0;
      let isEven = false;

      for (let i = num.length - 1; i >= 0; i--) {
        let digit = parseInt(num.charAt(i));

        if (isEven) {
          digit *= 2;
          if (digit > 9) {
            digit -= 9;
          }
        }

        sum += digit;
        isEven = !isEven;
      }

      return sum % 10 === 0;
    },

    /**
     * Debounce function
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

    /**
     * Add custom validation rule
     */
    addRule: function (name, validator, message) {
      this.rules[name] = {
        validate: validator,
        message: message,
      };
    },

    /**
     * Remove validation rule
     */
    removeRule: function (name) {
      delete this.rules[name];
    },

    /**
     * Get form validation state
     */
    getFormState: function ($form) {
      const formId = $form.attr("id");
      return this.formStates.get(formId);
    },

    /**
     * Reset form validation
     */
    resetForm: function ($form) {
      const formId = $form.attr("id");

      // Clear form state
      if (formId) {
        this.formStates.set(formId, {
          isValid: false,
          validatedFields: new Set(),
          errors: new Map(),
        });
      }

      // Clear field validations
      $form.find("[data-validate]").each((index, field) => {
        this.clearFieldValidation($(field));
      });

      // Hide form errors
      this.hideFormErrors($form);
    },

    /**
     * Validate specific value against rules
     */
    validateValue: function (value, rulesString) {
      const rules = this.parseValidationRules(rulesString);
      const errors = [];

      for (const rule of rules) {
        const ruleName = rule.name;
        const ruleParams = rule.params;

        if (this.rules[ruleName]) {
          const isValid = this.rules[ruleName].validate(value, ruleParams);

          if (!isValid) {
            let message = this.rules[ruleName].message;
            if (typeof message === "function") {
              message = message(ruleParams);
            }
            errors.push(message);
          }
        }
      }
    },

    /**
     * Enable/disable real-time validation
     */
    setRealTimeValidation: function (enabled) {
      this.config.realTimeValidation = enabled;
    },

    /**
     * Show validation summary
     */
    showValidationSummary: function ($form) {
      const formState = this.getFormState($form);
      if (!formState) return;

      const totalFields = $form.find("[data-validate]").length;
      const validatedFields = formState.validatedFields.size;
      const errors = formState.errors.size;

      let $summary = $form.find(".aci-validation-summary");
      if (!$summary.length) {
        $summary = $('<div class="aci-validation-summary"></div>');
        $form.append($summary);
      }

      $summary.html(`
                <div class="aci-validation-progress">
                    <div class="aci-progress-bar">
                        <div class="aci-progress-fill" style="width: ${
                          (validatedFields / totalFields) * 100
                        }%"></div>
                    </div>
                    <div class="aci-progress-text">
                        ${validatedFields}/${totalFields} fields validated
                        ${errors > 0 ? ` (${errors} errors)` : ""}
                    </div>
                </div>
            `);
    },

    /**
     * Validate password strength
     */
    validatePasswordStrength: function (password) {
      const checks = {
        length: password.length >= 8,
        lowercase: /[a-z]/.test(password),
        uppercase: /[A-Z]/.test(password),
        numbers: /\d/.test(password),
        special: /[!@#$%^&*(),.?":{}|<>]/.test(password),
      };

      const score = Object.values(checks).filter(Boolean).length;
      const strength = score < 2 ? "weak" : score < 4 ? "medium" : "strong";

      return {
        score: score,
        strength: strength,
        checks: checks,
        valid: score >= 3,
      };
    },

    /**
     * Format credit card number
     */
    formatCreditCard: function (value) {
      const v = value.replace(/\s+/g, "").replace(/[^0-9]/gi, "");
      const matches = v.match(/\d{4,16}/g);
      const match = (matches && matches[0]) || "";
      const parts = [];

      for (let i = 0, len = match.length; i < len; i += 4) {
        parts.push(match.substring(i, i + 4));
      }

      if (parts.length) {
        return parts.join(" ");
      } else {
        return v;
      }
    },

    /**
     * Format phone number
     */
    formatPhoneNumber: function (value, country = "US") {
      const cleaned = value.replace(/\D/g, "");

      if (country === "US") {
        const match = cleaned.match(/^(\d{3})(\d{3})(\d{4})$/);
        if (match) {
          return "(" + match[1] + ") " + match[2] + "-" + match[3];
        }
      }

      return value;
    },

    /**
     * Auto-format fields
     */
    autoFormat: function ($field) {
      const format = $field.data("format");
      const value = $field.val();

      switch (format) {
        case "credit-card":
          $field.val(this.formatCreditCard(value));
          break;
        case "phone":
          const country = $field.data("country") || "US";
          $field.val(this.formatPhoneNumber(value, country));
          break;
        case "currency":
          const num = parseFloat(value.replace(/[^0-9.]/g, ""));
          if (!isNaN(num)) {
            $field.val(num.toFixed(2));
          }
          break;
      }
    },
  };

  // Initialize when document is ready
  $(document).ready(function () {
    ACI.Validation.init();

    // Auto-format fields
    $(document).on("blur", "[data-format]", function () {
      ACI.Validation.autoFormat($(this));
    });

    // Password strength indicator
    $(document).on("input", '[data-validate*="password"]', function () {
      const $field = $(this);
      const password = $field.val();
      const strength = ACI.Validation.validatePasswordStrength(password);

      let $indicator = $field.siblings(".aci-password-strength");
      if (!$indicator.length) {
        $indicator = $('<div class="aci-password-strength"></div>');
        $field.after($indicator);
      }

      $indicator.html(`
                <div class="aci-strength-meter aci-strength-${
                  strength.strength
                }">
                    <div class="aci-strength-fill" style="width: ${
                      (strength.score / 5) * 100
                    }%"></div>
                </div>
                <div class="aci-strength-text">Password strength: ${
                  strength.strength
                }</div>
            `);
    });
  });

  // Export for external use
  window.ACI.Validation = ACI.Validation;
})(jQuery);
