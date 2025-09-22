<?php
/**
 * Affiliate Form Inline Template
 * File: /wp-content/plugins/affiliate-client-integration/templates/affiliate-form-inline.php
 * Plugin: Affiliate Client Integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Template variables
$form_id = $args['form_id'] ?? 'aci_inline_form_' . uniqid();
$show_title = $args['show_title'] ?? true;
$title = $args['title'] ?? __('Get Your Discount', 'affiliate-client-integration');
$description = $args['description'] ?? __('Enter your affiliate code to receive an instant discount on your purchase.', 'affiliate-client-integration');
$button_text = $args['button_text'] ?? __('Apply Discount', 'affiliate-client-integration');
$placeholder = $args['placeholder'] ?? __('Enter affiliate code', 'affiliate-client-integration');
$redirect_url = $args['redirect_url'] ?? '';
$auto_apply = $args['auto_apply'] ?? false;
$show_benefits = $args['show_benefits'] ?? true;
$current_affiliate = ACI_Session_Manager::get_affiliate_code();
$css_class = $args['css_class'] ?? '';
?>

<div class="aci-affiliate-form-inline <?php echo esc_attr($css_class); ?>" id="<?php echo esc_attr($form_id); ?>">
    <?php if ($show_title && $title): ?>
        <div class="aci-form-header">
            <h3 class="aci-form-title"><?php echo esc_html($title); ?></h3>
            <?php if ($description): ?>
                <p class="aci-form-description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form class="aci-form aci-affiliate-validation-form" method="post" data-form-id="<?php echo esc_attr($form_id); ?>">
        <?php wp_nonce_field('aci_validate_affiliate', 'aci_nonce'); ?>
        
        <div class="aci-form-fields">
            <div class="aci-field-group">
                <div class="aci-input-wrapper">
                    <input type="text" 
                           id="affiliate_code_<?php echo esc_attr($form_id); ?>"
                           name="affiliate_code" 
                           class="aci-affiliate-code-input aci-input" 
                           placeholder="<?php echo esc_attr($placeholder); ?>"
                           value="<?php echo esc_attr($current_affiliate); ?>"
                           data-validate="required|affiliateCode"
                           <?php echo $current_affiliate ? 'readonly' : ''; ?>
                           autocomplete="off"
                           spellcheck="false">
                    <span class="aci-validation-indicator"></span>
                </div>
                
                <button type="submit" 
                        class="aci-apply-discount aci-button aci-button-primary"
                        <?php echo $current_affiliate ? 'disabled' : ''; ?>>
                    <span class="aci-button-text">
                        <?php echo $current_affiliate ? __('Applied', 'affiliate-client-integration') : esc_html($button_text); ?>
                    </span>
                    <span class="aci-button-loader" style="display: none;">
                        <span class="aci-spinner"></span>
                    </span>
                </button>
            </div>

            <?php if ($current_affiliate): ?>
                <div class="aci-current-affiliate">
                    <div class="aci-affiliate-notice aci-notice-success">
                        <i class="aci-icon-check"></i>
                        <span><?php printf(__('Affiliate code "%s" is active', 'affiliate-client-integration'), esc_html($current_affiliate)); ?></span>
                        <button type="button" class="aci-clear-affiliate aci-button-link" data-action="clear_affiliate">
                            <?php _e('Clear', 'affiliate-client-integration'); ?>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="aci-form-messages">
            <!-- Messages will be inserted here via JavaScript -->
        </div>

        <?php if ($show_benefits && !$current_affiliate): ?>
            <div class="aci-benefits-section">
                <div class="aci-benefits-list">
                    <div class="aci-benefit-item">
                        <i class="aci-icon-discount"></i>
                        <span><?php _e('Instant discount applied', 'affiliate-client-integration'); ?></span>
                    </div>
                    <div class="aci-benefit-item">
                        <i class="aci-icon-secure"></i>
                        <span><?php _e('Secure and trusted', 'affiliate-client-integration'); ?></span>
                    </div>
                    <div class="aci-benefit-item">
                        <i class="aci-icon-support"></i>
                        <span><?php _e('Priority customer support', 'affiliate-client-integration'); ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Hidden fields -->
        <input type="hidden" name="action" value="aci_validate_affiliate">
        <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>">
        <input type="hidden" name="redirect_url" value="<?php echo esc_attr($redirect_url); ?>">
        <input type="hidden" name="auto_apply" value="<?php echo esc_attr($auto_apply ? '1' : '0'); ?>">
        <input type="hidden" name="current_url" value="<?php echo esc_url(home_url(add_query_arg(null, null))); ?>">
    </form>
</div>

<style>
.aci-affiliate-form-inline {
    background: #fff;
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    padding: 24px;
    margin: 20px 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.aci-form-header {
    text-align: center;
    margin-bottom: 20px;
}

.aci-form-title {
    margin: 0 0 8px 0;
    color: #2c3e50;
    font-size: 1.4em;
    font-weight: 600;
}

.aci-form-description {
    margin: 0;
    color: #6c757d;
    font-size: 0.95em;
    line-height: 1.4;
}

.aci-field-group {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    margin-bottom: 16px;
}

.aci-input-wrapper {
    flex: 1;
    position: relative;
}

.aci-affiliate-code-input {
    width: 100%;
    padding: 12px 40px 12px 16px;
    border: 2px solid #e1e5e9;
    border-radius: 6px;
    font-size: 16px;
    transition: border-color 0.3s ease;
    background: #fff;
}

.aci-affiliate-code-input:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 3px rgba(0, 124, 186, 0.1);
}

.aci-affiliate-code-input.aci-error {
    border-color: #dc3545;
}

.aci-affiliate-code-input.aci-success {
    border-color: #28a745;
}

.aci-affiliate-code-input[readonly] {
    background-color: #f8f9fa;
    color: #6c757d;
}

.aci-validation-indicator {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-weight: bold;
    font-size: 16px;
}

.aci-button {
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 48px;
    white-space: nowrap;
}

.aci-button-primary {
    background: #007cba;
    color: #fff;
}

.aci-button-primary:hover:not(:disabled) {
    background: #005a87;
}

.aci-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.aci-button-loader {
    margin-left: 8px;
}

.aci-spinner {
    width: 16px;
    height: 16px;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: aci-spin 1s linear infinite;
}

@keyframes aci-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.aci-current-affiliate {
    margin-bottom: 16px;
}

.aci-affiliate-notice {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    border-radius: 6px;
    font-size: 14px;
    gap: 8px;
}

.aci-notice-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.aci-clear-affiliate {
    margin-left: auto;
    background: none;
    border: none;
    color: #007cba;
    text-decoration: underline;
    cursor: pointer;
    font-size: 12px;
}

.aci-benefits-section {
    border-top: 1px solid #e1e5e9;
    padding-top: 16px;
    margin-top: 16px;
}

.aci-benefits-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.aci-benefit-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #6c757d;
}

.aci-benefit-item i {
    color: #28a745;
    width: 16px;
    text-align: center;
}

.aci-form-messages {
    margin-top: 16px;
}

.aci-message {
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 12px;
    font-size: 14px;
}

.aci-message-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.aci-message-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.aci-message-info {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

/* Icons */
.aci-icon-check::before { content: "✓"; }
.aci-icon-discount::before { content: "🏷️"; }
.aci-icon-secure::before { content: "🔒"; }
.aci-icon-support::before { content: "🎧"; }

/* Responsive design */
@media (max-width: 768px) {
    .aci-field-group {
        flex-direction: column;
        gap: 12px;
    }
    
    .aci-benefits-list {
        gap: 12px;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .aci-affiliate-form-inline {
        background: #2c3e50;
        border-color: #34495e;
        color: #ecf0f1;
    }
    
    .aci-form-title {
        color: #ecf0f1;
    }
    
    .aci-affiliate-code-input {
        background: #34495e;
        border-color: #4a5f7a;
        color: #ecf0f1;
    }
    
    .aci-affiliate-code-input:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    const $form = $('#<?php echo esc_js($form_id); ?>');
    
    // Handle form submission
    $form.on('submit', function(e) {
        e.preventDefault();
        
        const $submitBtn = $form.find('.aci-apply-discount');
        const $input = $form.find('.aci-affiliate-code-input');
        const $loader = $form.find('.aci-button-loader');
        const $buttonText = $form.find('.aci-button-text');
        const code = $input.val().trim();
        
        if (!code) {
            ACI.showMessage($form, '<?php echo esc_js(__('Please enter an affiliate code', 'affiliate-client-integration')); ?>', 'error');
            return;
        }
        
        // Show loading state
        $submitBtn.prop('disabled', true);
        $loader.show();
        $buttonText.text('<?php echo esc_js(__('Validating...', 'affiliate-client-integration')); ?>');
        
        // Validate affiliate code
        $.ajax({
            url: aci_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'aci_validate_affiliate',
                affiliate_code: code,
                nonce: aci_ajax.nonce,
                form_id: '<?php echo esc_js($form_id); ?>',
                url: window.location.href
            },
            success: function(response) {
                if (response.success) {
                    ACI.showMessage($form, response.data.message || '<?php echo esc_js(__('Affiliate code applied successfully!', 'affiliate-client-integration')); ?>', 'success');
                    
                    // Update button state
                    $buttonText.text('<?php echo esc_js(__('Applied', 'affiliate-client-integration')); ?>');
                    $input.prop('readonly', true);
                    
                    // Trigger affiliate set event
                    $(document).trigger('aci:affiliate_set', [response.data]);
                    
                    // Redirect if specified
                    <?php if ($redirect_url): ?>
                    setTimeout(function() {
                        window.location.href = '<?php echo esc_js($redirect_url); ?>';
                    }, 1500);
                    <?php endif; ?>
                    
                } else {
                    ACI.showMessage($form, response.data || '<?php echo esc_js(__('Invalid affiliate code', 'affiliate-client-integration')); ?>', 'error');
                }
            },
            error: function() {
                ACI.showMessage($form, '<?php echo esc_js(__('Unable to validate affiliate code. Please try again.', 'affiliate-client-integration')); ?>', 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false);
                $loader.hide();
                if (!$input.prop('readonly')) {
                    $buttonText.text('<?php echo esc_js($button_text); ?>');
                }
            }
        });
    });
    
    // Handle clear affiliate
    $form.on('click', '.aci-clear-affiliate', function(e) {
        e.preventDefault();
        
        if (confirm('<?php echo esc_js(__('Are you sure you want to clear the affiliate code?', 'affiliate-client-integration')); ?>')) {
            // Clear affiliate data
            $(document).trigger('aci:clear_affiliate');
            
            // Reset form
            $form.find('.aci-affiliate-code-input').val('').prop('readonly', false);
            $form.find('.aci-apply-discount').prop('disabled', false);
            $form.find('.aci-button-text').text('<?php echo esc_js($button_text); ?>');
            $form.find('.aci-current-affiliate').remove();
            $form.find('.aci-form-messages').empty();
        }
    });
    
    // Auto-apply if affiliate code is provided in URL
    <?php if ($auto_apply && !$current_affiliate): ?>
    const urlParams = new URLSearchParams(window.location.search);
    const urlAffiliate = urlParams.get('aff') || urlParams.get('affiliate') || urlParams.get('ref');
    
    if (urlAffiliate) {
        $form.find('.aci-affiliate-code-input').val(urlAffiliate);
        $form.submit();
    }
    <?php endif; ?>
});

// Helper function to show messages
window.ACI = window.ACI || {};
ACI.showMessage = function($form, message, type) {
    const $messages = $form.find('.aci-form-messages');
    const messageHtml = `<div class="aci-message aci-message-${type}">${message}</div>`;
    
    $messages.html(messageHtml);
    
    // Auto-hide success messages after 5 seconds
    if (type === 'success') {
        setTimeout(function() {
            $messages.fadeOut();
        }, 5000);
    }
};
</script>