/**
 * Affiliate Client Full - Discount Form Functionality
 * 
 * JavaScript for discount code input forms including validation,
 * real-time feedback, and e-commerce platform integration.
 * 
 * @package AffiliateClientFull
 * @version 1.0.0
 */

(function($) {
    'use strict';

    // Discount form functionality
    window.AffiliateClientDiscountForm = {

        // Configuration
        config: {
            ajaxUrl: '',
            restUrl: '',
            nonce: '',
            strings: {}
        },

        // State
        initialized: false,
        activeForms: {},
        validationCache: {},

        /**
         * Initialize discount form functionality
         */
        init: function(config) {
            this.config = $.extend(this.config, config || {});
            
            this.setupEventListeners();
            this.initializeForms();
            this.setupValidationCache();
            
            this.initialized = true;
            this.log('Discount form functionality initialized');
        },

        /**
         * Setup event listeners
         */
        setupEventListeners: function() {
            var self = this;

            // Form submissions
            $(document).on('submit', '.affiliate-discount-form .discount-code-form', function(e) {
                e.preventDefault();
                self.handleFormSubmit(this);
            });

            // Real-time validation
            $(document).on('input', '.affiliate-discount-form .discount-code-input', function() {
                self.handleInputChange(this);
            });

            // Input focus/blur events
            $(document).on('focus', '.affiliate-discount-form .discount-code-input', function() {
                self.handleInputFocus(this);
            });

            $(document).on('blur', '.affiliate-discount-form .discount-code-input', function() {
                self.handleInputBlur(this);
            });

            // Paste event
            $(document).on('paste', '.affiliate-discount-form .discount-code-input', function(e) {
                setTimeout(function() {
                    self.handleInputChange(e.target);
                }, 10);
            });

            // Enter key in input
            $(document).on('keypress', '.affiliate-discount-form .discount-code-input', function(e) {
                if (e.which === 13) { // Enter key
                    e.preventDefault();
                    $(this).closest('form').submit();
                }
            });
        },

        /**
         * Initialize all discount forms on page
         */
        initializeForms: function() {
            var self = this;
            
            $('.affiliate-discount-form').each(function() {
                var $form = $(this);
                var formId = this.id || 'discount-form-' + Math.random().toString(36).substr(2, 9);
                
                self.activeForms[formId] = {
                    element: this,
                    autoValidate: $form.data('auto-validate'),
                    trackUsage: $form.data('track-usage'),
                    redirectAfter: $form.data('redirect-after'),
                    successMessage: $form.data('success-message'),
                    errorMessage: $form.data('error-message'),
                    lastValidation: null,
                    isValidating: false,
                    isSubmitting: false
                };

                // Initialize form state
                self.resetFormState(formId);
            });
        },

        /**
         * Handle form submission
         */
        handleFormSubmit: function(form) {
            var $form = $(form);
            var $container = $form.closest('.affiliate-discount-form');
            var formId = $container.attr('id');
            var $input = $form.find('.discount-code-input');
            var $button = $form.find('.discount-apply-button');
            var code = $input.val().trim().toUpperCase();

            if (!code) {
                this.showFormMessage($container, this.config.strings.invalid || 'Please enter a discount code', 'error');
                $input.focus();
                return;
            }

            // Prevent double submission
            if (this.activeForms[formId] && this.activeForms[formId].isSubmitting) {
                return;
            }

            // Set submitting state
            this.setFormSubmitting($container, true);
            this.activeForms[formId].isSubmitting = true;

            // Track form submission
            this.trackFormAction(formId, 'submit', code);

            // Apply discount code
            this.applyDiscountCode(code).then(function(response) {
                this.setFormSubmitting($container, false);
                this.activeForms[formId].isSubmitting = false;

                if (response.success) {
                    this.handleApplicationSuccess($container, response, code);
                } else {
                    this.handleApplicationError($container, response, code);
                }
            }.bind(this)).catch(function(error) {
                this.setFormSubmitting($container, false);
                this.activeForms[formId].isSubmitting = false;
                this.handleApplicationError($container, { message: this.config.strings.failed || 'Application failed' }, code);
                this.log('Application error:', error);
            }.bind(this));
        },

        /**
         * Handle input change for real-time validation
         */
        handleInputChange: function(input) {
            var $input = $(input);
            var $container = $input.closest('.affiliate-discount-form');
            var formId = $container.attr('id');
            var formData = this.activeForms[formId];
            var code = $input.val().trim().toUpperCase();

            // Update input value to uppercase
            if ($input.val() !== code) {
                $input.val(code);
            }

            // Clear previous validation state
            this.clearValidationState($container);

            // Auto-validate if enabled and code is long enough
            if (formData && formData.autoValidate && code.length >= 3) {
                // Debounce validation
                clearTimeout(this.validationTimeout);
                this.validationTimeout = setTimeout(function() {
                    this.validateCode($container, code);
                }.bind(this), 500);
            }
        },

        /**
         * Handle input focus
         */
        handleInputFocus: function(input) {
            var $input = $(input);
            var $container = $input.closest('.affiliate-discount-form');
            
            $container.addClass('focused');
            this.trackFormAction($container.attr('id'), 'focus');
        },

        /**
         * Handle input blur
         */
        handleInputBlur: function(input) {
            var $input = $(input);
            var $container = $input.closest('.affiliate-discount-form');
            var code = $input.val().trim();
            
            $container.removeClass('focused');
            
            // Validate on blur if there's a code
            if (code.length >= 3) {
                this.validateCode($container, code.toUpperCase());
            }
        },

        /**
         * Validate discount code
         */
        validateCode: function($container, code) {
            var formId = $container.attr('id');
            var formData = this.activeForms[formId];

            if (!formData || formData.isValidating) {
                return;
            }

            // Check cache first
            if (this.validationCache[code]) {
                this.displayValidationResult($container, this.validationCache[code]);
                return;
            }

            // Set validating state
            this.setFormValidating($container, true);
            formData.isValidating = true;

            // Track validation attempt
            this.trackFormAction(formId, 'validate', code);

            // Send validation request
            this.sendValidationRequest(code).then(function(response) {
                this.setFormValidating($container, false);
                formData.isValidating = false;
                formData.lastValidation = response;

                // Cache result
                this.validationCache[code] = response;

                this.displayValidationResult($container, response);
            }.bind(this)).catch(function(error) {
                this.setFormValidating($container, false);
                formData.isValidating = false;
                this.log('Validation error:', error);
            }.bind(this));
        },

        /**
         * Send validation request
         */
        sendValidationRequest: function(code) {
            if (this.config.restUrl) {
                return $.ajax({
                    url: this.config.restUrl + 'discount/validate',
                    method: 'POST',
                    contentType: 'application/json',
                    headers: {
                        'X-WP-Nonce': this.config.nonce
                    },
                    data: JSON.stringify({ code: code })
                });
            } else {
                return $.ajax({
                    url: this.config.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'affiliate_client_validate_discount',
                        nonce: this.config.nonce,
                        code: code
                    }
                });
            }
        },

        /**
         * Apply discount code
         */
        applyDiscountCode: function(code) {
            if (this.config.restUrl) {
                return $.ajax({
                    url: this.config.restUrl + 'discount/apply-user',
                    method: 'POST',
                    contentType: 'application/json',
                    headers: {
                        'X-WP-Nonce': this.config.nonce
                    },
                    data: JSON.stringify({ code: code })
                });
            } else {
                return $.ajax({
                    url: this.config.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'affiliate_client_apply_user_discount',
                        nonce: this.config.nonce,
                        code: code
                    }
                });
            }
        },

        /**
         * Display validation result
         */
        displayValidationResult: function($container, response) {
            var $validationMessage = $container.find('.validation-message');
            var $validationIcon = $container.find('.validation-icon');

            if (response.valid) {
                $container.addClass('valid').removeClass('invalid');
                $validationIcon.html('✓');
                $validationMessage.text(response.message || this.config.strings.valid).addClass('success').removeClass('error');
            } else {
                $container.addClass('invalid').removeClass('valid');
                $validationIcon.html('✗');
                $validationMessage.text(response.message || this.config.strings.invalid).addClass('error').removeClass('success');
            }

            $validationMessage.slideDown(200);
        },

        /**
         * Handle successful application
         */
        handleApplicationSuccess: function($container, response, code) {
            var formId = $container.attr('id');
            var formData = this.activeForms[formId];
            
            // Show success message
            var successMessage = formData.successMessage || response.message || this.config.strings.applied;
            this.showFormMessage($container, successMessage, 'success');

            // Mark form as successful
            $container.addClass('success');

            // Track successful application
            this.trackFormAction(formId, 'apply_success', code);

            // Show additional info if available
            if (response.savings) {
                this.showSavingsInfo($container, response.savings);
            }

            // Handle redirect
            if (response.redirect_url || formData.redirectAfter) {
                var redirectUrl = response.redirect_url || formData.redirectAfter;
                this.showRedirectMessage($container, redirectUrl);
            }

            // Disable form after successful application
            this.disableForm($container);
        },

        /**
         * Handle application error
         */
        handleApplicationError: function($container, response, code) {
            var formId = $container.attr('id');
            var formData = this.activeForms[formId];
            
            // Show error message
            var errorMessage = formData.errorMessage || response.message || this.config.strings.failed;
            this.showFormMessage($container, errorMessage, 'error');

            // Track failed application
            this.trackFormAction(formId, 'apply_failed', code);

            // Focus back to input for retry
            $container.find('.discount-code-input').focus();
        },

        /**
         * Show form message
         */
        showFormMessage: function($container, message, type) {
            var $feedback = $container.find('.form-feedback');
            
            $feedback.removeClass('success error info warning')
                    .addClass(type)
                    .html('<span class="message-icon"></span><span class="message-text">' + message + '</span>')
                    .slideDown(300);

            // Auto-hide non-success messages
            if (type !== 'success') {
                setTimeout(function() {
                    $feedback.slideUp(300);
                }, 5000);
            }
        },

        /**
         * Show savings information
         */
        showSavingsInfo: function($container, savings) {
            if (!savings || savings <= 0) return;

            var savingsHtml = '<div class="savings-info">💰 You saved: <strong>' + this.formatCurrency(savings) + '</strong></div>';
            $container.find('.form-feedback').append(savingsHtml);
        },

        /**
         * Show redirect message
         */
        showRedirectMessage: function($container, redirectUrl) {
            var redirectHtml = '<div class="redirect-info">' +
                              '<p>Redirecting to checkout in <span class="countdown">3</span> seconds...</p>' +
                              '<button type="button" class="redirect-now-btn">Go Now</button>' +
                              '</div>';
            
            $container.find('.form-feedback').append(redirectHtml);

            // Countdown and redirect
            var countdown = 3;
            var countdownInterval = setInterval(function() {
                countdown--;
                $container.find('.countdown').text(countdown);
                
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    window.location.href = redirectUrl;
                }
            }, 1000);

            // Immediate redirect button
            $container.find('.redirect-now-btn').on('click', function() {
                clearInterval(countdownInterval);
                window.location.href = redirectUrl;
            });
        },

        /**
         * Set form submitting state
         */
        setFormSubmitting: function($container, isSubmitting) {
            var $button = $container.find('.discount-apply-button');
            var $input = $container.find('.discount-code-input');

            if (isSubmitting) {
                $container.addClass('submitting');
                $button.prop('disabled', true).addClass('loading');
                $input.prop('disabled', true);
                $button.find('.button-text').text(this.config.strings.applying || 'Applying...');
            } else {
                $container.removeClass('submitting');
                $button.prop('disabled', false).removeClass('loading');
                $input.prop('disabled', false);
                $button.find('.button-text').text($button.data('original-text') || 'Apply');
            }
        },

        /**
         * Set form validating state
         */
        setFormValidating: function($container, isValidating) {
            var $validationIcon = $container.find('.validation-icon');

            if (isValidating) {
                $container.addClass('validating');
                $validationIcon.html('<span class="spinner"></span>');
            } else {
                $container.removeClass('validating');
            }
        },

        /**
         * Clear validation state
         */
        clearValidationState: function($container) {
            $container.removeClass('valid invalid validating');
            $container.find('.validation-message').slideUp(200);
            $container.find('.validation-icon').empty();
        },

        /**
         * Reset form state
         */
        resetFormState: function(formId) {
            var formData = this.activeForms[formId];
            if (!formData) return;

            var $container = $(formData.element);
            var $button = $container.find('.discount-apply-button');

            // Store original button text
            if (!$button.data('original-text')) {
                $button.data('original-text', $button.find('.button-text').text());
            }

            this.clearValidationState($container);
            $container.removeClass('submitting success focused');
            $container.find('.form-feedback').hide().empty();
        },

        /**
         * Disable form after successful application
         */
        disableForm: function($container) {
            $container.find('.discount-code-input, .discount-apply-button').prop('disabled', true);
            $container.addClass('disabled');
        },

        /**
         * Setup validation cache with cleanup
         */
        setupValidationCache: function() {
            // Clear cache every 5 minutes to ensure fresh data
            setInterval(function() {
                this.validationCache = {};
            }.bind(this), 5 * 60 * 1000);
        },

        /**
         * Track form actions
         */
        trackFormAction: function(formId, action, code) {
            var formData = this.activeForms[formId];
            if (!formData || !formData.trackUsage) return;

            // Use main tracking system if available
            if (window.AffiliateClientTracker && window.AffiliateClientTracker.initialized) {
                window.AffiliateClientTracker.trackEvent('discount_form_interaction', {
                    form_id: formId,
                    action: action,
                    code: code || '',
                    page_url: window.location.href
                });
            }
        },

        /**
         * Format currency for display
         */
        formatCurrency: function(amount) {
            // Simple currency formatting - can be enhanced based on site settings
            return ' + parseFloat(amount).toFixed(2);
        },

        /**
         * Create discount form programmatically
         */
        createForm: function(options) {
            var defaults = {
                placeholder: 'Enter discount code',
                buttonText: 'Apply',
                style: 'default',
                size: 'medium',
                color: '#4CAF50',
                showValidation: true,
                autoValidate: true,
                trackUsage: true
            };

            var settings = $.extend(defaults, options);
            var formId = 'affiliate-discount-form-dynamic-' + Math.random().toString(36).substr(2, 9);

            var formHtml = '<div id="' + formId + '" class="affiliate-discount-form style-' + settings.style + ' size-' + settings.size + '"' +
                          ' data-auto-validate="' + settings.autoValidate + '"' +
                          ' data-track-usage="' + settings.trackUsage + '"' +
                          ' style="--primary-color: ' + settings.color + ';">' +
                          '<form class="discount-code-form" method="post">' +
                          '<div class="form-row">' +
                          '<div class="input-group">' +
                          '<input type="text" name="discount_code" class="discount-code-input"' +
                          ' placeholder="' + settings.placeholder + '" autocomplete="off" spellcheck="false" required>' +
                          (settings.showValidation ? '<div class="validation-indicator"><span class="validation-icon"></span></div>' : '') +
                          '</div>' +
                          '<button type="submit" class="discount-apply-button">' +
                          '<span class="button-text">' + settings.buttonText + '</span>' +
                          '<span class="loading-spinner"></span>' +
                          '</button>' +
                          '</div>' +
                          (settings.showValidation ? '<div class="validation-message" style="display: none;"></div>' : '') +
                          '</form>' +
                          '<div class="form-feedback" style="display: none;"></div>' +
                          '</div>';

            var $form = $(formHtml);

            // Register form
            this.activeForms[formId] = {
                element: $form[0],
                autoValidate: settings.autoValidate,
                trackUsage: settings.trackUsage,
                lastValidation: null,
                isValidating: false,
                isSubmitting: false
            };

            return $form;
        },

        /**
         * Get form statistics
         */
        getStats: function() {
            return {
                totalForms: Object.keys(this.activeForms).length,
                cacheSize: Object.keys(this.validationCache).length,
                activeForms: this.activeForms
            };
        },

        /**
         * Log debug messages
         */
        log: function() {
            if (window.console && console.log) {
                var args = Array.prototype.slice.call(arguments);
                args.unshift('[Affiliate Client Discount Form]');
                console.log.apply(console, args);
            }
        }
    };

    // Auto-initialize when config is available
    $(document).ready(function() {
        if (typeof affiliateClientDiscountForm !== 'undefined') {
            AffiliateClientDiscountForm.init(affiliateClientDiscountForm);
        }
    });

    // Expose to global scope
    window.AffiliateClientDiscountForm = AffiliateClientDiscountForm;

})(jQuery);