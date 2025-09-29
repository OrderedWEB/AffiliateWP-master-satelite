<?php
/**
 * Discount Display Shortcode Template
 * 
 * Plugin: Affiliate Client Integration (Satellite)
 * File: /wp-content/plugins/affiliate-client-integration/templates/shortcodes/discount-display.php
 * Author: Richard King, starneconsulting.com
 * Email: r.king@starneconsulting.com
 * 
 * Displays active discount information including code, type, value, and savings.
 * Used by [affiliate_discount_display] shortcode.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Extract shortcode attributes
$display_style = !empty($style) ? esc_attr($style) : 'default';
$show_code = !empty($show_code) ? (bool)$show_code : true;
$show_savings = !empty($show_savings) ? (bool)$show_savings : true;
$show_expiry = !empty($show_expiry) ? (bool)$show_expiry : false;
$theme = !empty($theme) ? esc_attr($theme) : 'light';
$element_id = 'aci-discount-display-' . wp_generate_uuid4();

// Get discount data from session or cookies
$discount_data = null;

// Try session manager first
if (class_exists('ACI_Session_Manager')) {
    $session = new ACI_Session_Manager();
    $affiliate_code = $session->get('affiliate_code');
    
    if ($affiliate_code) {
        $discount_data = [
            'code' => $affiliate_code,
            'affiliate_id' => $session->get('affiliate_id'),
            'discount_type' => $session->get('discount_type', 'percentage'),
            'discount_value' => $session->get('discount_value', 0),
            'original_price' => $session->get('original_price'),
            'discounted_price' => $session->get('discounted_price'),
            'savings' => $session->get('savings'),
            'expires' => $session->get('discount_expires')
        ];
    }
}

// Fallback to cookies
if (!$discount_data) {
    if (!empty($_COOKIE['aci_affiliate_code'])) {
        $discount_data = [
            'code' => sanitize_text_field($_COOKIE['aci_affiliate_code']),
            'discount_type' => !empty($_COOKIE['aci_discount_type']) ? sanitize_text_field($_COOKIE['aci_discount_type']) : 'percentage',
            'discount_value' => !empty($_COOKIE['aci_discount_value']) ? floatval($_COOKIE['aci_discount_value']) : 0,
        ];
    }
}

// If no discount active, optionally hide or show message
if (!$discount_data) {
    if (!empty($hide_when_empty)) {
        return '';
    }
    
    // Show "no discount" message
    ?>
    <div id="<?php echo $element_id; ?>" 
         class="aci-discount-display aci-no-discount aci-theme-<?php echo $theme; ?>"
         data-has-discount="false">
        <div class="aci-discount-empty">
            <span class="aci-discount-icon">ℹ️</span>
            <span class="aci-discount-message">
                <?php echo !empty($empty_message) ? esc_html($empty_message) : __('No discount code applied', 'affiliate-client-integration'); ?>
            </span>
        </div>
    </div>
    <?php
    return;
}

// Calculate display values
$discount_formatted = $discount_data['discount_type'] === 'percentage' 
    ? $discount_data['discount_value'] . '%' 
    : '$' . number_format($discount_data['discount_value'], 2);

$savings_formatted = null;
if ($show_savings && !empty($discount_data['savings'])) {
    $savings_formatted = '$' . number_format($discount_data['savings'], 2);
}

// Render based on display style
?>

<div id="<?php echo $element_id; ?>" 
     class="aci-discount-display aci-discount-active aci-style-<?php echo $display_style; ?> aci-theme-<?php echo $theme; ?>"
     data-has-discount="true"
     data-discount-code="<?php echo esc_attr($discount_data['code']); ?>"
     data-discount-type="<?php echo esc_attr($discount_data['discount_type']); ?>"
     data-discount-value="<?php echo esc_attr($discount_data['discount_value']); ?>">

    <?php if ($display_style === 'banner'): ?>
        <!-- Banner Style -->
        <div class="aci-discount-banner">
            <div class="aci-discount-icon">🎉</div>
            <div class="aci-discount-content">
                <div class="aci-discount-title">
                    <?php _e('Discount Active!', 'affiliate-client-integration'); ?>
                </div>
                <div class="aci-discount-details">
                    <?php if ($show_code): ?>
                        <span class="aci-discount-code"><?php echo esc_html($discount_data['code']); ?></span>
                    <?php endif; ?>
                    <span class="aci-discount-separator">•</span>
                    <span class="aci-discount-amount"><?php echo esc_html($discount_formatted); ?> <?php _e('off', 'affiliate-client-integration'); ?></span>
                    <?php if ($savings_formatted): ?>
                        <span class="aci-discount-separator">•</span>
                        <span class="aci-discount-savings"><?php _e('Save', 'affiliate-client-integration'); ?> <?php echo esc_html($savings_formatted); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif ($display_style === 'badge'): ?>
        <!-- Badge Style -->
        <div class="aci-discount-badge">
            <span class="aci-discount-icon">💰</span>
            <span class="aci-discount-value"><?php echo esc_html($discount_formatted); ?></span>
            <?php if ($show_code): ?>
                <span class="aci-discount-code-label"><?php echo esc_html($discount_data['code']); ?></span>
            <?php endif; ?>
        </div>

    <?php elseif ($display_style === 'card'): ?>
        <!-- Card Style -->
        <div class="aci-discount-card">
            <div class="aci-discount-header">
                <span class="aci-discount-icon">✓</span>
                <h4 class="aci-discount-title"><?php _e('Discount Applied', 'affiliate-client-integration'); ?></h4>
            </div>
            <div class="aci-discount-body">
                <?php if ($show_code): ?>
                    <div class="aci-discount-row">
                        <span class="aci-discount-label"><?php _e('Code:', 'affiliate-client-integration'); ?></span>
                        <span class="aci-discount-value-text"><?php echo esc_html($discount_data['code']); ?></span>
                    </div>
                <?php endif; ?>
                <div class="aci-discount-row">
                    <span class="aci-discount-label"><?php _e('Discount:', 'affiliate-client-integration'); ?></span>
                    <span class="aci-discount-value-text aci-highlight"><?php echo esc_html($discount_formatted); ?></span>
                </div>
                <?php if ($savings_formatted): ?>
                    <div class="aci-discount-row">
                        <span class="aci-discount-label"><?php _e('You Save:', 'affiliate-client-integration'); ?></span>
                        <span class="aci-discount-value-text aci-success"><?php echo esc_html($savings_formatted); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($show_expiry && !empty($discount_data['expires'])): ?>
                    <div class="aci-discount-row aci-discount-expiry">
                        <span class="aci-discount-label"><?php _e('Expires:', 'affiliate-client-integration'); ?></span>
                        <span class="aci-discount-value-text"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($discount_data['expires']))); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($display_style === 'inline'): ?>
        <!-- Inline Style -->
        <div class="aci-discount-inline">
            <span class="aci-discount-icon">✓</span>
            <span class="aci-discount-text">
                <?php 
                if ($show_code) {
                    printf(
                        __('Code <strong>%s</strong> applied - %s off', 'affiliate-client-integration'),
                        esc_html($discount_data['code']),
                        esc_html($discount_formatted)
                    );
                } else {
                    printf(
                        __('Discount applied - %s off', 'affiliate-client-integration'),
                        esc_html($discount_formatted)
                    );
                }
                
                if ($savings_formatted) {
                    echo ' (' . sprintf(__('Save %s', 'affiliate-client-integration'), esc_html($savings_formatted)) . ')';
                }
                ?>
            </span>
        </div>

    <?php else: ?>
        <!-- Default Style -->
        <div class="aci-discount-default">
            <div class="aci-discount-header">
                <span class="aci-discount-icon">🎉</span>
                <h4 class="aci-discount-title"><?php _e('Active Discount', 'affiliate-client-integration'); ?></h4>
            </div>
            <div class="aci-discount-content">
                <?php if ($show_code): ?>
                    <div class="aci-discount-code-display">
                        <span class="aci-discount-code"><?php echo esc_html($discount_data['code']); ?></span>
                    </div>
                <?php endif; ?>
                <div class="aci-discount-amount-display">
                    <span class="aci-discount-amount"><?php echo esc_html($discount_formatted); ?></span>
                    <span class="aci-discount-label"><?php _e('discount', 'affiliate-client-integration'); ?></span>
                </div>
                <?php if ($savings_formatted): ?>
                    <div class="aci-discount-savings-display">
                        <span class="aci-discount-savings-label"><?php _e('Total Savings:', 'affiliate-client-integration'); ?></span>
                        <span class="aci-discount-savings-amount"><?php echo esc_html($savings_formatted); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($show_expiry && !empty($discount_data['expires'])): ?>
                <div class="aci-discount-footer">
                    <span class="aci-discount-expiry-label"><?php _e('Valid until:', 'affiliate-client-integration'); ?></span>
                    <span class="aci-discount-expiry-date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($discount_data['expires']))); ?></span>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($show_remove_button)): ?>
        <button class="aci-remove-discount" 
                data-discount-code="<?php echo esc_attr($discount_data['code']); ?>"
                type="button">
            <span class="dashicons dashicons-no"></span>
            <?php _e('Remove', 'affiliate-client-integration'); ?>
        </button>
    <?php endif; ?>

</div>

<?php if (!empty($auto_update)): ?>
<script type="text/javascript">
(function($) {
    'use strict';
    
    $(document).ready(function() {
        const $display = $('#<?php echo $element_id; ?>');
        
        // Listen for discount updates
        $(window).on('aci:discount-applied aci:discount-updated', function(e, data) {
            if (data && data.discount_value) {
                // Update display
                $display.attr('data-has-discount', 'true');
                $display.removeClass('aci-no-discount').addClass('aci-discount-active');
                
                // Update values
                if (data.code) {
                    $display.find('.aci-discount-code').text(data.code);
                }
                if (data.discount_formatted) {
                    $display.find('.aci-discount-amount').text(data.discount_formatted);
                }
                if (data.savings_formatted) {
                    $display.find('.aci-discount-savings-amount').text(data.savings_formatted);
                }
                
                // Show with animation
                $display.hide().fadeIn(300);
            }
        });
        
        // Listen for discount removal
        $(window).on('aci:discount-removed', function() {
            $display.attr('data-has-discount', 'false');
            $display.removeClass('aci-discount-active').addClass('aci-no-discount');
            <?php if (!empty($hide_when_empty)): ?>
                $display.fadeOut(300);
            <?php endif; ?>
        });
        
        // Handle remove button
        $display.on('click', '.aci-remove-discount', function(e) {
            e.preventDefault();
            
            if (confirm('<?php _e('Remove discount code?', 'affiliate-client-integration'); ?>')) {
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    method: 'POST',
                    data: {
                        action: 'aci_remove_discount',
                        nonce: '<?php echo wp_create_nonce('aci_remove_discount'); ?>',
                        code: $(this).data('discount-code')
                    },
                    success: function(response) {
                        if (response.success) {
                            $(window).trigger('aci:discount-removed');
                            window.location.reload();
                        }
                    }
                });
            }
        });
    });
})(jQuery);
</script>
<?php endif; ?>