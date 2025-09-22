<?php
/**
 * Affiliate Form Popup Template
 * File: /wp-content/plugins/affiliate-client-integration/templates/affiliate-form-popup.php
 * Plugin: Affiliate Client Integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Template variables
$popup_id = $args['popup_id'] ?? 'aci_popup_' . uniqid();
$title = $args['title'] ?? __('Special Discount Available!', 'affiliate-client-integration');
$description = $args['description'] ?? __('Enter your affiliate code to receive an exclusive discount on your purchase.', 'affiliate-client-integration');
$button_text = $args['button_text'] ?? __('Apply Discount', 'affiliate-client-integration');
$placeholder = $args['placeholder'] ?? __('Enter affiliate code', 'affiliate-client-integration');
$show_benefits = $args['show_benefits'] ?? true;
$show_close = $args['show_close'] ?? true;
$popup_style = $args['style'] ?? 'default';
$popup_size = $args['size'] ?? 'medium';
$animation = $args['animation'] ?? 'fade-in';
$auto_close = $args['auto_close'] ?? false;
$backdrop_close = $args['backdrop_close'] ?? true;
$current_affiliate = '';

// Check for existing affiliate
if (class_exists('ACI_Session_Manager')) {
    $session_manager = new ACI_Session_Manager();
    $current_affiliate = $session_manager->get_affiliate_code();
}
?>

<div class="aci-popup-overlay aci-popup-<?php echo esc_attr($animation); ?>" 
     id="<?php echo esc_attr($popup_id); ?>" 
     data-popup-style="<?php echo esc_attr($popup_style); ?>"
     data-backdrop-close="<?php echo $backdrop_close ? 'true' : 'false'; ?>"
     role="dialog" 
     aria-modal="true"
     aria-labelledby="<?php echo esc_attr($popup_id); ?>-title"
     aria-describedby="<?php echo esc_attr($popup_id); ?>-description">
    
    <div class="aci-popup aci-popup-<?php echo esc_attr($popup_size); ?> aci-popup-style-<?php echo esc_attr($popup_style); ?>">
        
        <?php if ($show_close): ?>
        <button type="button" 
                class="aci-popup-close" 
                aria-label="<?php esc_attr_e('Close popup', 'affiliate-client-integration'); ?>"
                data-action="close">
            <span aria-hidden="true">&times;</span>
        </button>
        <?php endif; ?>

        <!-- Popup Header -->
        <div class="aci-popup-header">
            <h2 id="<?php echo esc_attr($popup_id); ?>-title" class="aci-popup-title">
                <?php echo esc_html($title); ?>
            </h2>
            
            <?php if ($description): ?>
            <p id="<?php echo esc_attr($popup_id); ?>-description" class="aci-popup-subtitle">
                <?php echo esc_html($description); ?>
            </p>
            <?php endif; ?>
        </div>

        <!-- Popup Body -->
        <div class="aci-popup-body">
            
            <?php if ($current_affiliate): ?>
                <!-- Already has affiliate code -->
                <div class="aci-popup-success-state">
                    <div class="aci-success-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="12" r="10" fill="#28a745"/>
                            <path d="M9 12l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h3 class="aci-success-title">
                        <?php _e('Discount Active!', 'affiliate-client-integration'); ?>
                    </h3>
                    <p class="aci-success-message">
                        <?php printf(
                            __('Your affiliate code "%s" is already applied and your discount will be automatically calculated at checkout.', 'affiliate-client-integration'),
                            '<strong>' . esc_html($current_affiliate) . '</strong>'
                        ); ?>
                    </p>
                    <div class="aci-success-actions">
                        <button type="button" class="aci-popup-button aci-button-secondary" data-action="close">
                            <?php _e('Continue Shopping', 'affiliate-client-integration'); ?>
                        </button>
                        <button type="button" class="aci-popup-button-link" data-action="clear_affiliate">
                            <?php _e('Change Code', 'affiliate-client-integration'); ?>
                        </button>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Affiliate code form -->
                <form class="aci-popup-form" method="post" data-popup-id="<?php echo esc_attr($popup_id); ?>">
                    <?php wp_nonce_field('aci_popup_submit', 'aci_popup_nonce'); ?>
                    
                    <div class="aci-popup-field">
                        <label for="<?php echo esc_attr($popup_id); ?>-affiliate-code" class="aci-popup-label">
                            <?php _e('Affiliate Code', 'affiliate-client-integration'); ?>
                            <span class="aci-required" aria-label="<?php esc_attr_e('Required', 'affiliate-client-integration'); ?>">*</span>
                        </label>
                        
                        <div class="aci-input-group">
                            <input type="text" 
                                   id="<?php echo esc_attr($popup_id); ?>-affiliate-code" 
                                   name="affiliate_code" 
                                   class="aci-popup-input" 
                                   placeholder="<?php echo esc_attr($placeholder); ?>"
                                   autocomplete="off"
                                   spellcheck="false"
                                   data-validate="required|affiliate_code"
                                   aria-describedby="<?php echo esc_attr($popup_id); ?>-code-help"
                                   required>
                            <div class="aci-input-feedback" role="status" aria-live="polite"></div>
                        </div>
                        
                        <div id="<?php echo esc_attr($popup_id); ?>-code-help" class="aci-field-help">
                            <?php _e('Enter the affiliate code provided by your referrer', 'affiliate-client-integration'); ?>
                        </div>
                    </div>

                    <div class="aci-popup-actions">
                        <button type="submit" class="aci-popup-button aci-button-primary">
                            <span class="aci-popup-button-text">
                                <?php echo esc_html($button_text); ?>
                            </span>
                            <span class="aci-popup-loader" style="display: none;" aria-hidden="true">
                                <span class="aci-popup-spinner"></span>
                            </span>
                        </button>
                    </div>

                    <div class="aci-popup-message" role="alert" aria-live="assertive" style="display: none;">
                        <!-- Messages will be inserted here via JavaScript -->
                    </div>

                    <!-- Hidden fields -->
                    <input type="hidden" name="action" value="aci_popup_submit">
                    <input type="hidden" name="popup_type" value="<?php echo esc_attr($popup_style); ?>">
                    <input type="hidden" name="current_url" value="<?php echo esc_url(home_url(add_query_arg(null, null))); ?>">
                    <input type="hidden" name="referrer" value="<?php echo esc_url(wp_get_referer()); ?>">
                </form>

                <?php if ($show_benefits): ?>
                <!-- Benefits Section -->
                <div class="aci-popup-benefits">
                    <h3 class="aci-benefits-title">
                        <?php _e('Why enter your affiliate code?', 'affiliate-client-integration'); ?>
                    </h3>
                    <ul class="aci-benefits-list">
                        <li class="aci-benefit-item">
                            <span class="aci-benefit-icon" aria-hidden="true">💰</span>
                            <span><?php _e('Instant discount applied to your order', 'affiliate-client-integration'); ?></span>
                        </li>
                        <li class="aci-benefit-item">
                            <span class="aci-benefit-icon" aria-hidden="true">🔒</span>
                            <span><?php _e('Secure and encrypted processing', 'affiliate-client-integration'); ?></span>
                        </li>
                        <li class="aci-benefit-item">
                            <span class="aci-benefit-icon" aria-hidden="true">⚡</span>
                            <span><?php _e('No account creation required', 'affiliate-client-integration'); ?></span>
                        </li>
                        <li class="aci-benefit-item">
                            <span class="aci-benefit-icon" aria-hidden="true">🎧</span>
                            <span><?php _e('Priority customer support access', 'affiliate-client-integration'); ?></span>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
                
            <?php endif; ?>
        </div>

        <!-- Popup Footer -->
        <div class="aci-popup-footer">
            <div class="aci-popup-trust-indicators">
                <div class="aci-trust-item">
                    <span class="aci-trust-icon" aria-hidden="true">🛡️</span>
                    <span class="aci-trust-text"><?php _e('SSL Secured', 'affiliate-client-integration'); ?></span>
                </div>
                <div class="aci-trust-item">
                    <span class="aci-trust-icon" aria-hidden="true">⭐</span>
                    <span class="aci-trust-text"><?php _e('Trusted by 10,000+', 'affiliate-client-integration'); ?></span>
                </div>
                <div class="aci-trust-item">
                    <span class="aci-trust-icon" aria-hidden="true">📞</span>
                    <span class="aci-trust-text"><?php _e('24/7 Support', 'affiliate-client-integration'); ?></span>
                </div>
            </div>
            
            <div class="aci-popup-legal">
                <p class="aci-legal-text">
                    <?php _e('By applying an affiliate code, you agree to our', 'affiliate-client-integration'); ?>
                    <a href="<?php echo esc_url(get_privacy_policy_url()); ?>" target="_blank" rel="noopener">
                        <?php _e('Privacy Policy', 'affiliate-client-integration'); ?>
                    </a>
                    <?php _e('and', 'affiliate-client-integration'); ?>
                    <a href="<?php echo esc_url(get_permalink(get_option('woocommerce_terms_page_id'))); ?>" target="_blank" rel="noopener">
                        <?php _e('Terms of Service', 'affiliate-client-integration'); ?>
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const popup = document.getElementById('<?php echo esc_js($popup_id); ?>');
    if (!popup) return;

    const form = popup.querySelector('.aci-popup-form');
    const closeBtn = popup.querySelector('.aci-popup-close');
    const overlay = popup;
    
    // Close button handler
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            closePopup();
        });
    }
    
    // Backdrop close handler
    if (<?php echo $backdrop_close ? 'true' : 'false'; ?>) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                closePopup();
            }
        });
    }
    
    // Escape key handler
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && popup.classList.contains('aci-active')) {
            closePopup();
        }
    });
    
    // Form submission handler
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm();
        });
        
        // Real-time validation
        const codeInput = form.querySelector('input[name="affiliate_code"]');
        if (codeInput) {
            let validationTimeout;
            codeInput.addEventListener('input', function() {
                clearTimeout(validationTimeout);
                validationTimeout = setTimeout(() => {
                    validateAffiliateCode(this.value.trim());
                }, 500);
            });
        }
    }
    
    // Clear affiliate handler
    const clearBtn = popup.querySelector('[data-action="clear_affiliate"]');
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            clearAffiliate();
        });
    }
    
    // Auto-close timer
    <?php if ($auto_close && is_numeric($auto_close)): ?>
    setTimeout(function() {
        closePopup();
    }, <?php echo intval($auto_close); ?>);
    <?php endif; ?>
    
    function closePopup() {
        popup.classList.remove('aci-active');
        document.body.classList.remove('aci-popup-open');
        
        // Track close event
        if (typeof ACI !== 'undefined' && ACI.PopupManager) {
            ACI.PopupManager.trackInteraction('<?php echo esc_js($popup_style); ?>', 'close');
        }
        
        // Remove from DOM after animation
        setTimeout(() => {
            if (popup.parentNode) {
                popup.parentNode.removeChild(popup);
            }
        }, 300);
    }
    
    function submitForm() {
        const submitBtn = form.querySelector('button[type="submit"]');
        const loader = form.querySelector('.aci-popup-loader');
        const buttonText = form.querySelector('.aci-popup-button-text');
        const messageDiv = form.querySelector('.aci-popup-message');
        const codeInput = form.querySelector('input[name="affiliate_code"]');
        
        const code = codeInput.value.trim();
        
        if (!code) {
            showMessage('error', '<?php echo esc_js(__('Please enter an affiliate code', 'affiliate-client-integration')); ?>');
            return;
        }
        
        // Show loading state
        submitBtn.disabled = true;
        loader.style.display = 'inline-block';
        buttonText.textContent = '<?php echo esc_js(__('Validating...', 'affiliate-client-integration')); ?>';
        messageDiv.style.display = 'none';
        
        // Submit via AJAX
        const formData = new FormData(form);
        formData.append('action', 'aci_popup_interaction');
        formData.append('action_type', 'submit_code');
        formData.append('nonce', '<?php echo wp_create_nonce('aci_popup_interaction'); ?>');
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('success', data.data.message || '<?php echo esc_js(__('Affiliate code applied successfully!', 'affiliate-client-integration')); ?>');
                
                // Update popup to success state
                setTimeout(() => {
                    location.reload(); // Refresh to show updated state
                }, 1500);
                
                // Track successful conversion
                if (typeof ACI !== 'undefined' && ACI.PopupManager) {
                    ACI.PopupManager.trackConversion('<?php echo esc_js($popup_style); ?>', code, true);
                }
                
            } else {
                showMessage('error', data.data || '<?php echo esc_js(__('Invalid affiliate code', 'affiliate-client-integration')); ?>');
                
                // Track failed conversion
                if (typeof ACI !== 'undefined' && ACI.PopupManager) {
                    ACI.PopupManager.trackConversion('<?php echo esc_js($popup_style); ?>', code, false);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('error', '<?php echo esc_js(__('Unable to validate affiliate code. Please try again.', 'affiliate-client-integration')); ?>');
        })
        .finally(() => {
            // Reset button state
            submitBtn.disabled = false;
            loader.style.display = 'none';
            buttonText.textContent = '<?php echo esc_js($button_text); ?>';
        });
    }
    
    function validateAffiliateCode(code) {
        const feedback = form.querySelector('.aci-input-feedback');
        const input = form.querySelector('input[name="affiliate_code"]');
        
        if (!code || code.length < 2) {
            input.classList.remove('aci-success', 'aci-error');
            feedback.textContent = '';
            return;
        }
        
        // Basic format validation
        if (!/^[a-zA-Z0-9_-]+$/.test(code)) {
            input.classList.remove('aci-success');
            input.classList.add('aci-error');
            feedback.textContent = '<?php echo esc_js(__('Invalid format', 'affiliate-client-integration')); ?>';
            return;
        }
        
        input.classList.remove('aci-error');
        input.classList.add('aci-success');
        feedback.textContent = '<?php echo esc_js(__('Valid format', 'affiliate-client-integration')); ?>';
    }
    
    function clearAffiliate() {
        if (confirm('<?php echo esc_js(__('Are you sure you want to clear the affiliate code?', 'affiliate-client-integration')); ?>')) {
            // Clear affiliate via AJAX
            const formData = new FormData();
            formData.append('action', 'aci_clear_affiliate');
            formData.append('nonce', '<?php echo wp_create_nonce('aci_clear_affiliate'); ?>');
            
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // Refresh to show updated state
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    }
    
    function showMessage(type, message) {
        const messageDiv = form.querySelector('.aci-popup-message');
        messageDiv.className = 'aci-popup-message aci-' + type;
        messageDiv.textContent = message;
        messageDiv.style.display = 'block';
        
        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(() => {
                messageDiv.style.display = 'none';
            }, 5000);
        }
    }
    
    // Track popup view
    if (typeof ACI !== 'undefined' && ACI.PopupManager) {
        ACI.PopupManager.trackInteraction('<?php echo esc_js($popup_style); ?>', 'view');
    }
});
</script>