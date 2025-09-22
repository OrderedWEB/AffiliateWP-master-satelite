<?php
/**
 * Affiliate Discount Display Template
 * File: /wp-content/plugins/affiliate-client-integration/templates/affiliate-discount-display.php
 * Plugin: Affiliate Client Integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Template variables
$display_id = $args['display_id'] ?? 'aci_discount_' . uniqid();
$show_percentage = $args['show_percentage'] ?? true;
$show_amount = $args['show_amount'] ?? true;
$show_code = $args['show_code'] ?? true;
$show_countdown = $args['show_countdown'] ?? false;
$animate = $args['animate'] ?? true;
$style = $args['style'] ?? 'default';
$size = $args['size'] ?? 'medium';
$position = $args['position'] ?? 'inline';
$auto_hide = $args['auto_hide'] ?? false;

// Get current affiliate data
$affiliate_data = null;
$affiliate_code = '';
$discount_info = null;

if (class_exists('ACI_Session_Manager')) {
    $session_manager = new ACI_Session_Manager();
    $affiliate_data = $session_manager->get_affiliate_data();
    $affiliate_code = $session_manager->get_affiliate_code();
}

// Get discount information if affiliate is active
if ($affiliate_code && class_exists('ACI_Price_Calculator')) {
    $price_calculator = new ACI_Price_Calculator();
    // This would typically come from the master domain API
    $discount_info = apply_filters('aci_get_affiliate_discount_info', null, $affiliate_code);
}

// Don't show if no discount available
if (!$discount_info && !$affiliate_code) {
    return;
}

// Default discount info structure for demo
if (!$discount_info) {
    $discount_info = [
        'type' => 'percentage',
        'value' => 10,
        'max_amount' => 100,
        'min_order' => 50,
        'expires' => null,
        'description' => __('Affiliate Discount', 'affiliate-client-integration')
    ];
}

$discount_value = $discount_info['value'] ?? 0;
$discount_type = $discount_info['type'] ?? 'percentage';
$discount_description = $discount_info['description'] ?? '';
$expires = $discount_info['expires'] ?? null;

// Calculate display values
$display_text = '';
if ($discount_type === 'percentage') {
    $display_text = $discount_value . '%';
    $savings_text = sprintf(__('%s%% OFF', 'affiliate-client-integration'), $discount_value);
} else {
    $display_text = '$' . number_format($discount_value, 2);
    $savings_text = sprintf(__('$%s OFF', 'affiliate-client-integration'), number_format($discount_value, 2));
}

$css_classes = [
    'aci-discount-display',
    'aci-discount-' . $style,
    'aci-discount-' . $size,
    'aci-discount-' . $position
];

if ($animate) {
    $css_classes[] = 'aci-discount-animated';
}
?>

<div class="<?php echo esc_attr(implode(' ', $css_classes)); ?>" 
     id="<?php echo esc_attr($display_id); ?>"
     data-affiliate-code="<?php echo esc_attr($affiliate_code); ?>"
     data-discount-type="<?php echo esc_attr($discount_type); ?>"
     data-discount-value="<?php echo esc_attr($discount_value); ?>"
     role="region"
     aria-label="<?php esc_attr_e('Affiliate discount information', 'affiliate-client-integration'); ?>">

    <div class="aci-discount-content">
        
        <!-- Discount Badge -->
        <div class="aci-discount-badge">
            <div class="aci-badge-content">
                <?php if ($show_amount || $show_percentage): ?>
                <div class="aci-discount-amount" aria-label="<?php echo esc_attr($savings_text); ?>">
                    <?php echo esc_html($display_text); ?>
                </div>
                <div class="aci-discount-label">
                    <?php echo esc_html($discount_type === 'percentage' ? __('OFF', 'affiliate-client-integration') : __('DISCOUNT', 'affiliate-client-integration')); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Discount Information -->
        <div class="aci-discount-info">
            <div class="aci-discount-title">
                <?php if ($discount_description): ?>
                    <?php echo esc_html($discount_description); ?>
                <?php else: ?>
                    <?php _e('Your Discount is Active!', 'affiliate-client-integration'); ?>
                <?php endif; ?>
            </div>
            
            <?php if ($show_code && $affiliate_code): ?>
            <div class="aci-discount-code-display">
                <span class="aci-code-label"><?php _e('Code:', 'affiliate-client-integration'); ?></span>
                <span class="aci-code-value"><?php echo esc_html($affiliate_code); ?></span>
                <button type="button" 
                        class="aci-code-copy" 
                        data-code="<?php echo esc_attr($affiliate_code); ?>"
                        title="<?php esc_attr_e('Copy code to clipboard', 'affiliate-client-integration'); ?>">
                    <span class="aci-copy-icon" aria-hidden="true">📋</span>
                    <span class="aci-sr-only"><?php _e('Copy code', 'affiliate-client-integration'); ?></span>
                </button>
            </div>
            <?php endif; ?>

            <!-- Discount Details -->
            <div class="aci-discount-details">
                <?php if (!empty($discount_info['min_order'])): ?>
                <div class="aci-discount-condition">
                    <span class="aci-condition-icon" aria-hidden="true">🛒</span>
                    <span class="aci-condition-text">
                        <?php printf(
                            __('Minimum order: $%s', 'affiliate-client-integration'),
                            number_format($discount_info['min_order'], 2)
                        ); ?>
                    </span>
                </div>
                <?php endif; ?>

                <?php if (!empty($discount_info['max_amount']) && $discount_type === 'percentage'): ?>
                <div class="aci-discount-condition">
                    <span class="aci-condition-icon" aria-hidden="true">🎯</span>
                    <span class="aci-condition-text">
                        <?php printf(
                            __('Maximum discount: $%s', 'affiliate-client-integration'),
                            number_format($discount_info['max_amount'], 2)
                        ); ?>
                    </span>
                </div>
                <?php endif; ?>

                <?php if ($expires && $show_countdown): ?>
                <div class="aci-discount-condition">
                    <span class="aci-condition-icon" aria-hidden="true">⏰</span>
                    <span class="aci-condition-text">
                        <?php _e('Limited time offer', 'affiliate-client-integration'); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Countdown Timer -->
            <?php if ($expires && $show_countdown): ?>
            <div class="aci-countdown-section">
                <div class="aci-countdown-title">
                    <?php _e('Offer expires in:', 'affiliate-client-integration'); ?>
                </div>
                <div class="aci-countdown-timer" 
                     data-expires="<?php echo esc_attr($expires); ?>"
                     aria-live="polite">
                    <div class="aci-countdown-unit">
                        <span class="aci-countdown-value" data-unit="days">00</span>
                        <span class="aci-countdown-label"><?php _e('Days', 'affiliate-client-integration'); ?></span>
                    </div>
                    <div class="aci-countdown-separator">:</div>
                    <div class="aci-countdown-unit">
                        <span class="aci-countdown-value" data-unit="hours">00</span>
                        <span class="aci-countdown-label"><?php _e('Hours', 'affiliate-client-integration'); ?></span>
                    </div>
                    <div class="aci-countdown-separator">:</div>
                    <div class="aci-countdown-unit">
                        <span class="aci-countdown-value" data-unit="minutes">00</span>
                        <span class="aci-countdown-label"><?php _e('Min', 'affiliate-client-integration'); ?></span>
                    </div>
                    <div class="aci-countdown-separator">:</div>
                    <div class="aci-countdown-unit">
                        <span class="aci-countdown-value" data-unit="seconds">00</span>
                        <span class="aci-countdown-label"><?php _e('Sec', 'affiliate-client-integration'); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="aci-discount-actions">
                <button type="button" class="aci-discount-button aci-button-primary" data-action="continue">
                    <?php _e('Continue Shopping', 'affiliate-client-integration'); ?>
                </button>
                
                <?php if ($auto_hide): ?>
                <button type="button" 
                        class="aci-discount-button aci-button-secondary" 
                        data-action="hide"
                        aria-label="<?php esc_attr_e('Hide discount display', 'affiliate-client-integration'); ?>">
                    <?php _e('Hide', 'affiliate-client-integration'); ?>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Floating Elements -->
        <div class="aci-discount-decorations" aria-hidden="true">
            <div class="aci-decoration aci-decoration-1">💰</div>
            <div class="aci-decoration aci-decoration-2">✨</div>
            <div class="aci-decoration aci-decoration-3">🎉</div>
            <div class="aci-decoration aci-decoration-4">⭐</div>
        </div>

        <!-- Progress Bar for Minimum Order -->
        <?php if (!empty($discount_info['min_order'])): ?>
        <div class="aci-progress-section" data-min-order="<?php echo esc_attr($discount_info['min_order']); ?>">
            <div class="aci-progress-title">
                <span class="aci-progress-text"><?php _e('Add more to qualify for discount', 'affiliate-client-integration'); ?></span>
                <span class="aci-progress-amount">$<span data-progress-remaining><?php echo number_format($discount_info['min_order'], 2); ?></span></span>
            </div>
            <div class="aci-progress-bar">
                <div class="aci-progress-fill" data-progress-fill style="width: 0%"></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Close Button -->
        <?php if ($auto_hide): ?>
        <button type="button" 
                class="aci-discount-close" 
                data-action="close"
                aria-label="<?php esc_attr_e('Close discount display', 'affiliate-client-integration'); ?>">
            <span aria-hidden="true">&times;</span>
        </button>
        <?php endif; ?>
    </div>

    <!-- Success Animation Overlay -->
    <div class="aci-success-overlay" data-success-overlay style="display: none;">
        <div class="aci-success-content">
            <div class="aci-success-icon">✅</div>
            <div class="aci-success-message">
                <?php _e('Discount Applied!', 'affiliate-client-integration'); ?>
            </div>
        </div>
    </div>
</div>

<!-- Inline JavaScript for Immediate Functionality -->
<script>
(function() {
    'use strict';
    
    const discountDisplay = document.getElementById('<?php echo esc_js($display_id); ?>');
    if (!discountDisplay) return;
    
    // Initialize functionality
    initializeDiscountDisplay();
    
    function initializeDiscountDisplay() {
        // Copy code functionality
        const copyButton = discountDisplay.querySelector('.aci-code-copy');
        if (copyButton) {
            copyButton.addEventListener('click', handleCodeCopy);
        }
        
        // Countdown timer
        const countdownTimer = discountDisplay.querySelector('.aci-countdown-timer');
        if (countdownTimer) {
            initializeCountdown(countdownTimer);
        }
        
        // Action buttons
        const actionButtons = discountDisplay.querySelectorAll('[data-action]');
        actionButtons.forEach(button => {
            button.addEventListener('click', handleAction);
        });
        
        // Progress tracking for minimum order
        const progressSection = discountDisplay.querySelector('.aci-progress-section');
        if (progressSection) {
            initializeProgressTracking(progressSection);
        }
        
        // Animation entrance
        if (discountDisplay.classList.contains('aci-discount-animated')) {
            setTimeout(() => {
                discountDisplay.classList.add('aci-discount-visible');
            }, 100);
        }
    }
    
    function handleCodeCopy(event) {
        const code = event.currentTarget.dataset.code;
        if (!code) return;
        
        // Try modern clipboard API first
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(code).then(() => {
                showCopySuccess(event.currentTarget);
            }).catch(() => {
                fallbackCopyCode(code, event.currentTarget);
            });
        } else {
            fallbackCopyCode(code, event.currentTarget);
        }
    }
    
    function fallbackCopyCode(code, button) {
        const textarea = document.createElement('textarea');
        textarea.value = code;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        
        try {
            document.execCommand('copy');
            showCopySuccess(button);
        } catch (err) {
            console.warn('Copy failed:', err);
        }
        
        document.body.removeChild(textarea);
    }
    
    function showCopySuccess(button) {
        const originalText = button.innerHTML;
        button.innerHTML = '<span class="aci-copy-icon">✅</span><span class="aci-sr-only">Copied!</span>';
        button.classList.add('aci-copied');
        
        setTimeout(() => {
            button.innerHTML = originalText;
            button.classList.remove('aci-copied');
        }, 2000);
    }
    
    function initializeCountdown(timer) {
        const expires = timer.dataset.expires;
        if (!expires) return;
        
        const expirationDate = new Date(expires);
        
        function updateCountdown() {
            const now = new Date();
            const timeLeft = expirationDate - now;
            
            if (timeLeft <= 0) {
                // Expired
                timer.innerHTML = '<div class="aci-countdown-expired"><?php esc_html_e('Offer Expired', 'affiliate-client-integration'); ?></div>';
                return;
            }
            
            const days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
            const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
            
            timer.querySelector('[data-unit="days"]').textContent = days.toString().padStart(2, '0');
            timer.querySelector('[data-unit="hours"]').textContent = hours.toString().padStart(2, '0');
            timer.querySelector('[data-unit="minutes"]').textContent = minutes.toString().padStart(2, '0');
            timer.querySelector('[data-unit="seconds"]').textContent = seconds.toString().padStart(2, '0');
        }
        
        updateCountdown();
        setInterval(updateCountdown, 1000);
    }
    
    function initializeProgressTracking(progressSection) {
        const minOrder = parseFloat(progressSection.dataset.minOrder || '0');
        if (minOrder <= 0) return;
        
        // This would integrate with the cart/pricing system
        function updateProgress() {
            // Get current cart total (this would be site-specific)
            const currentTotal = getCurrentCartTotal(); // Custom function
            const progress = Math.min((currentTotal / minOrder) * 100, 100);
            const remaining = Math.max(minOrder - currentTotal, 0);
            
            const progressFill = progressSection.querySelector('[data-progress-fill]');
            const remainingAmount = progressSection.querySelector('[data-progress-remaining]');
            
            if (progressFill) {
                progressFill.style.width = progress + '%';
            }
            
            if (remainingAmount) {
                remainingAmount.textContent = remaining.toFixed(2);
            }
            
            // Update message based on progress
            const progressText = progressSection.querySelector('.aci-progress-text');
            if (progressText && progress >= 100) {
                progressText.textContent = '<?php esc_html_e('Discount qualifies!', 'affiliate-client-integration'); ?>';
                progressSection.classList.add('aci-progress-qualified');
            }
        }
        
        // Initial update
        updateProgress();
        
        // Listen for cart changes (site-specific events)
        document.addEventListener('cartUpdated', updateProgress);
        document.addEventListener('priceChanged', updateProgress);
    }
    
    function getCurrentCartTotal() {
        // This would be implemented based on the specific e-commerce platform
        // For now, return a demo value
        return 0;
    }
    
    function handleAction(event) {
        const action = event.currentTarget.dataset.action;
        
        switch (action) {
            case 'hide':
            case 'close':
                hideDiscountDisplay();
                break;
            case 'continue':
                // Could scroll to products, redirect, etc.
                break;
        }
    }
    
    function hideDiscountDisplay() {
        discountDisplay.classList.add('aci-discount-hiding');
        setTimeout(() => {
            discountDisplay.style.display = 'none';
        }, 300);
        
        // Store preference
        if (typeof(Storage) !== 'undefined') {
            localStorage.setItem('aci_discount_hidden_' + '<?php echo esc_js($affiliate_code); ?>', '1');
        }
    }
    
    // Public API
    window.ACIDiscountDisplay = window.ACIDiscountDisplay || {};
    window.ACIDiscountDisplay['<?php echo esc_js($display_id); ?>'] = {
        show: function() {
            discountDisplay.style.display = 'block';
            discountDisplay.classList.remove('aci-discount-hiding');
        },
        hide: hideDiscountDisplay,
        updateProgress: function() {
            const progressSection = discountDisplay.querySelector('.aci-progress-section');
            if (progressSection) {
                initializeProgressTracking(progressSection);
            }
        }
    };
})();
</script>

<?php
// Fire action for additional customization
do_action('aci_after_discount_display', $affiliate_code, $discount_info, $args);
?>