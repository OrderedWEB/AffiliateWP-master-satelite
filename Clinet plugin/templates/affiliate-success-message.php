<?php
/**
 * Affiliate Success Message Template
 * File: /wp-content/plugins/affiliate-client-integration/templates/affiliate-success-message.php
 * Plugin: Affiliate Client Integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Template variables
$message_id = $args['message_id'] ?? 'aci_success_' . uniqid();
$title = $args['title'] ?? __('Success!', 'affiliate-client-integration');
$message = $args['message'] ?? __('Your affiliate code has been applied successfully.', 'affiliate-client-integration');
$affiliate_code = $args['affiliate_code'] ?? '';
$discount_info = $args['discount_info'] ?? null;
$show_details = $args['show_details'] ?? true;
$show_next_steps = $args['show_next_steps'] ?? true;
$auto_close = $args['auto_close'] ?? false;
$auto_close_delay = $args['auto_close_delay'] ?? 5000;
$redirect_url = $args['redirect_url'] ?? '';
$style = $args['style'] ?? 'default';
$animate = $args['animate'] ?? true;

// Get discount details if available
$discount_amount = '';
$discount_type = '';
$savings_text = '';

if ($discount_info) {
    $discount_type = $discount_info['type'] ?? 'percentage';
    $discount_value = $discount_info['value'] ?? 0;
    
    if ($discount_type === 'percentage') {
        $discount_amount = $discount_value . '%';
        $savings_text = sprintf(__('You\'ll save %s%% on your order!', 'affiliate-client-integration'), $discount_value);
    } else {
        $discount_amount = '$' . number_format($discount_value, 2);
        $savings_text = sprintf(__('You\'ll save $%s on your order!', 'affiliate-client-integration'), number_format($discount_value, 2));
    }
}

$css_classes = [
    'aci-success-message',
    'aci-success-' . $style
];

if ($animate) {
    $css_classes[] = 'aci-success-animated';
}
?>

<div class="<?php echo esc_attr(implode(' ', $css_classes)); ?>" 
     id="<?php echo esc_attr($message_id); ?>"
     role="alert"
     aria-live="polite"
     data-auto-close="<?php echo $auto_close ? 'true' : 'false'; ?>"
     data-auto-close-delay="<?php echo esc_attr($auto_close_delay); ?>">

    <div class="aci-success-content">
        
        <!-- Success Icon -->
        <div class="aci-success-icon" aria-hidden="true">
            <svg class="aci-success-checkmark" viewBox="0 0 52 52">
                <circle class="aci-checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
                <path class="aci-checkmark-check" fill="none" d="m14.1 27.2l7.1 7.2 16.7-16.8"/>
            </svg>
        </div>

        <!-- Success Text -->
        <div class="aci-success-text">
            <h3 class="aci-success-title"><?php echo esc_html($title); ?></h3>
            <p class="aci-success-description"><?php echo esc_html($message); ?></p>
            
            <?php if ($savings_text): ?>
            <p class="aci-success-savings"><?php echo esc_html($savings_text); ?></p>
            <?php endif; ?>
        </div>

        <!-- Close Button -->
        <button type="button" 
                class="aci-success-close" 
                aria-label="<?php esc_attr_e('Close message', 'affiliate-client-integration'); ?>"
                data-action="close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

    <?php if ($show_details && ($affiliate_code || $discount_info)): ?>
    <!-- Success Details -->
    <div class="aci-success-details">
        <?php if ($affiliate_code): ?>
        <div class="aci-detail-item">
            <span class="aci-detail-icon" aria-hidden="true">🏷️</span>
            <div class="aci-detail-content">
                <span class="aci-detail-label"><?php _e('Affiliate Code:', 'affiliate-client-integration'); ?></span>
                <span class="aci-detail-value"><?php echo esc_html($affiliate_code); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($discount_amount): ?>
        <div class="aci-detail-item">
            <span class="aci-detail-icon" aria-hidden="true">💰</span>
            <div class="aci-detail-content">
                <span class="aci-detail-label"><?php _e('Discount:', 'affiliate-client-integration'); ?></span>
                <span class="aci-detail-value aci-discount-highlight"><?php echo esc_html($discount_amount); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($discount_info['expires'])): ?>
        <div class="aci-detail-item">
            <span class="aci-detail-icon" aria-hidden="true">⏰</span>
            <div class="aci-detail-content">
                <span class="aci-detail-label"><?php _e('Valid Until:', 'affiliate-client-integration'); ?></span>
                <span class="aci-detail-value"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($discount_info['expires']))); ?></span>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($show_next_steps): ?>
    <!-- Next Steps -->
    <div class="aci-success-next-steps">
        <h4 class="aci-next-steps-title"><?php _e('What happens next?', 'affiliate-client-integration'); ?></h4>
        <ul class="aci-next-steps-list">
            <li class="aci-next-step">
                <span class="aci-step-number">1</span>
                <span class="aci-step-text"><?php _e('Continue shopping and add items to your cart', 'affiliate-client-integration'); ?></span>
            </li>
            <li class="aci-next-step">
                <span class="aci-step-number">2</span>
                <span class="aci-step-text"><?php _e('Your discount will be automatically applied at checkout', 'affiliate-client-integration'); ?></span>
            </li>
            <li class="aci-next-step">
                <span class="aci-step-number">3</span>
                <span class="aci-step-text"><?php _e('Complete your purchase and enjoy your savings!', 'affiliate-client-integration'); ?></span>
            </li>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Action Buttons -->
    <div class="aci-success-actions">
        <?php if ($redirect_url): ?>
        <a href="<?php echo esc_url($redirect_url); ?>" 
           class="aci-success-button aci-button-primary">
            <?php _e('Continue Shopping', 'affiliate-client-integration'); ?>
        </a>
        <?php else: ?>
        <button type="button" 
                class="aci-success-button aci-button-primary" 
                data-action="continue">
            <?php _e('Continue Shopping', 'affiliate-client-integration'); ?>
        </button>
        <?php endif; ?>
        
        <button type="button" 
                class="aci-success-button aci-button-secondary" 
                data-action="share">
            <span class="aci-button-icon" aria-hidden="true">📤</span>
            <?php _e('Share This Deal', 'affiliate-client-integration'); ?>
        </button>
    </div>

    <!-- Progress Bar for Auto-close -->
    <?php if ($auto_close): ?>
    <div class="aci-success-progress">
        <div class="aci-progress-bar">
            <div class="aci-progress-fill" data-duration="<?php echo esc_attr($auto_close_delay); ?>"></div>
        </div>
        <div class="aci-progress-text">
            <?php printf(__('This message will close in %s seconds', 'affiliate-client-integration'), '<span class="aci-countdown-seconds"></span>'); ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
/* Success Message Styles */
.aci-success-message {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: #fff;
    border-radius: 12px;
    padding: 24px;
    margin: 20px 0;
    box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
    position: relative;
    overflow: hidden;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.aci-success-message::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    animation: aci-success-shine 2s ease-out;
}

@keyframes aci-success-shine {
    0% { left: -100%; }
    100% { left: 100%; }
}

.aci-success-content {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    position: relative;
    z-index: 2;
}

.aci-success-icon {
    flex-shrink: 0;
    width: 52px;
    height: 52px;
}

.aci-success-checkmark {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    display: block;
    stroke-width: 2;
    stroke: #fff;
    stroke-miterlimit: 10;
    box-shadow: inset 0px 0px 0px #28a745;
}

.aci-checkmark-circle {
    stroke-dasharray: 166;
    stroke-dashoffset: 166;
    stroke-width: 2;
    stroke-miterlimit: 10;
    stroke: #fff;
    fill: none;
    animation: aci-stroke-circle 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
}

.aci-checkmark-check {
    transform-origin: 50% 50%;
    stroke-dasharray: 48;
    stroke-dashoffset: 48;
    stroke-width: 3;
    stroke: #fff;
    animation: aci-stroke-check 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
}

@keyframes aci-stroke-circle {
    0% { stroke-dashoffset: 166; }
    100% { stroke-dashoffset: 0; }
}

@keyframes aci-stroke-check {
    0% { stroke-dashoffset: 48; }
    100% { stroke-dashoffset: 0; }
}

.aci-success-text {
    flex: 1;
}

.aci-success-title {
    margin: 0 0 8px 0;
    font-size: 20px;
    font-weight: 600;
    line-height: 1.2;
}

.aci-success-description {
    margin: 0 0 8px 0;
    font-size: 16px;
    line-height: 1.4;
    opacity: 0.95;
}

.aci-success-savings {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #fff3cd;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.aci-success-close {
    position: absolute;
    top: 12px;
    right: 12px;
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
    border: none;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    cursor: pointer;
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s ease;
}

.aci-success-close:hover {
    background: rgba(255, 255, 255, 0.3);
}

.aci-success-details {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    display: grid;
    gap: 12px;
}

.aci-detail-item {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
}

.aci-detail-icon {
    font-size: 18px;
    width: 24px;
    text-align: center;
}

.aci-detail-content {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
}

.aci-detail-label {
    opacity: 0.8;
    font-weight: 500;
}

.aci-detail-value {
    font-weight: 600;
}

.aci-discount-highlight {
    background: rgba(255, 255, 255, 0.2);
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 16px;
}

.aci-success-next-steps {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
}

.aci-next-steps-title {
    margin: 0 0 12px 0;
    font-size: 16px;
    font-weight: 600;
    opacity: 0.95;
}

.aci-next-steps-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 8px;
}

.aci-next-step {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
    opacity: 0.9;
}

.aci-step-number {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    flex-shrink: 0;
}

.aci-step-text {
    line-height: 1.3;
}

.aci-success-actions {
    margin-top: 20px;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.aci-success-button {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.3);
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
    backdrop-filter: blur(5px);
}

.aci-success-button:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-1px);
    color: #fff;
    text-decoration: none;
}

.aci-button-primary {
    background: rgba(255, 255, 255, 0.3);
}

.aci-button-secondary {
    background: rgba(0, 0, 0, 0.1);
}

.aci-button-icon {
    font-size: 16px;
}

.aci-success-progress {
    margin-top: 16px;
    padding-top: 12px;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
}

.aci-progress-bar {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    height: 6px;
    overflow: hidden;
    margin-bottom: 8px;
}

.aci-progress-fill {
    background: rgba(255, 255, 255, 0.8);
    height: 100%;
    border-radius: 10px;
    width: 100%;
    transform-origin: left;
    animation: aci-progress-countdown linear;
}

@keyframes aci-progress-countdown {
    from { transform: scaleX(1); }
    to { transform: scaleX(0); }
}

.aci-progress-text {
    font-size: 12px;
    text-align: center;
    opacity: 0.8;
}

.aci-countdown-seconds {
    font-weight: 600;
}

/* Animated variant */
.aci-success-animated {
    animation: aci-success-slide-in 0.5s ease-out;
}

@keyframes aci-success-slide-in {
    from { 
        opacity: 0; 
        transform: translateY(-20px) scale(0.95); 
    }
    to { 
        opacity: 1; 
        transform: translateY(0) scale(1); 
    }
}

/* Style variants */
.aci-success-minimal {
    background: #fff;
    color: #333;
    border: 2px solid #28a745;
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);
}

.aci-success-minimal .aci-success-checkmark {
    stroke: #28a745;
}

.aci-success-minimal .aci-checkmark-circle,
.aci-success-minimal .aci-checkmark-check {
    stroke: #28a745;
}

.aci-success-minimal .aci-success-close {
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
}

.aci-success-minimal .aci-success-button {
    background: #28a745;
    color: #fff;
    border-color: #28a745;
}

.aci-success-minimal .aci-success-button:hover {
    background: #218838;
    color: #fff;
}

.aci-success-compact {
    padding: 16px;
    margin: 12px 0;
}

.aci-success-compact .aci-success-content {
    gap: 12px;
}

.aci-success-compact .aci-success-icon {
    width: 40px;
    height: 40px;
}

.aci-success-compact .aci-success-title {
    font-size: 16px;
}

.aci-success-compact .aci-success-description {
    font-size: 14px;
}

/* Responsive design */
@media (max-width: 768px) {
    .aci-success-message {
        padding: 20px;
        margin: 16px 0;
        border-radius: 8px;
    }
    
    .aci-success-content {
        flex-direction: column;
        text-align: center;
        gap: 12px;
    }
    
    .aci-success-close {
        top: 8px;
        right: 8px;
        width: 28px;
        height: 28px;
        font-size: 16px;
    }
    
    .aci-success-title {
        font-size: 18px;
    }
    
    .aci-success-description {
        font-size: 15px;
    }
    
    .aci-success-actions {
        flex-direction: column;
    }
    
    .aci-success-button {
        width: 100%;
        justify-content: center;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .aci-success-message {
        padding: 16px;
        margin: 12px 0;
    }
    
    .aci-detail-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
    
    .aci-detail-content {
        width: 100%;
        justify-content: space-between;
    }
    
    .aci-next-step {
        align-items: flex-start;
        gap: 8px;
    }
    
    .aci-step-number {
        margin-top: 2px;
    }
}

/* Accessibility */
@media (prefers-reduced-motion: reduce) {
    .aci-success-message,
    .aci-success-checkmark,
    .aci-checkmark-circle,
    .aci-checkmark-check,
    .aci-progress-fill {
        animation: none !important;
        transition: none !important;
    }
}

/* High contrast mode */
@media (prefers-contrast: high) {
    .aci-success-message {
        border: 2px solid #000;
    }
    
    .aci-success-button {
        border: 2px solid #000;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const successMessage = document.getElementById('<?php echo esc_js($message_id); ?>');
    if (!successMessage) return;

    // Handle auto-close
    const autoClose = successMessage.dataset.autoClose === 'true';
    const autoCloseDelay = parseInt(successMessage.dataset.autoCloseDelay) || 5000;
    
    if (autoClose) {
        initializeAutoClose(autoCloseDelay);
    }

    // Handle button actions
    successMessage.addEventListener('click', function(e) {
        const action = e.target.dataset.action;
        
        switch (action) {
            case 'close':
                closeMessage();
                break;
                
            case 'continue':
                // Continue shopping - could scroll to products or redirect
                <?php if ($redirect_url): ?>
                window.location.href = '<?php echo esc_js($redirect_url); ?>';
                <?php else: ?>
                closeMessage();
                <?php endif; ?>
                break;
                
            case 'share':
                shareDiscount();
                break;
        }
    });

    function initializeAutoClose(delay) {
        const progressFill = successMessage.querySelector('.aci-progress-fill');
        const countdownElement = successMessage.querySelector('.aci-countdown-seconds');
        
        if (progressFill) {
            progressFill.style.animationDuration = (delay / 1000) + 's';
        }
        
        if (countdownElement) {
            let seconds = Math.ceil(delay / 1000);
            countdownElement.textContent = seconds;
            
            const countdownInterval = setInterval(() => {
                seconds--;
                countdownElement.textContent = seconds;
                
                if (seconds <= 0) {
                    clearInterval(countdownInterval);
                }
            }, 1000);
        }
        
        setTimeout(() => {
            closeMessage();
        }, delay);
    }

    function closeMessage() {
        successMessage.style.transform = 'translateY(-20px)';
        successMessage.style.opacity = '0';
        
        setTimeout(() => {
            if (successMessage.parentNode) {
                successMessage.parentNode.removeChild(successMessage);
            }
        }, 300);
        
        // Track close event
        if (typeof ACI !== 'undefined' && ACI.App) {
            ACI.App.trackEvent('success_message_closed', {
                affiliate_code: '<?php echo esc_js($affiliate_code); ?>',
                message_type: 'affiliate_success'
            });
        }
    }

    function shareDiscount() {
        const shareData = {
            title: '<?php echo esc_js(__('Great Discount Available!', 'affiliate-client-integration')); ?>',
            text: '<?php echo esc_js(__('Check out this great discount I found', 'affiliate-client-integration')); ?>',
            url: window.location.href
        };
        
        if (navigator.share && navigator.canShare && navigator.canShare(shareData)) {
            navigator.share(shareData)
                .then(() => {
                    // Track successful share
                    if (typeof ACI !== 'undefined' && ACI.App) {
                        ACI.App.trackEvent('discount_shared', {
                            affiliate_code: '<?php echo esc_js($affiliate_code); ?>',
                            method: 'native_share'
                        });
                    }
                })
                .catch(err => console.log('Error sharing:', err));
        } else {
            // Fallback to copy URL
            copyCurrentUrl();
        }
    }

    function copyCurrentUrl() {
        const url = window.location.href;
        
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(url).then(() => {
                showCopySuccess();
            }).catch(() => {
                fallbackCopyUrl(url);
            });
        } else {
            fallbackCopyUrl(url);
        }
    }

    function fallbackCopyUrl(url) {
        const textArea = document.createElement('textarea');
        textArea.value = url;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            showCopySuccess();
        } catch (err) {
            console.error('Failed to copy URL: ', err);
        }
        
        document.body.removeChild(textArea);
    }

    function showCopySuccess() {
        const shareButton = successMessage.querySelector('[data-action="share"]');
        const originalText = shareButton.innerHTML;
        
        shareButton.innerHTML = '<span class="aci-button-icon">✓</span> <?php echo esc_js(__('Link Copied!', 'affiliate-client-integration')); ?>';
        shareButton.style.background = 'rgba(255, 255, 255, 0.4)';
        
        setTimeout(() => {
            shareButton.innerHTML = originalText;
            shareButton.style.background = '';
        }, 2000);
        
        // Track copy action
        if (typeof ACI !== 'undefined' && ACI.App) {
            ACI.App.trackEvent('discount_url_copied', {
                affiliate_code: '<?php echo esc_js($affiliate_code); ?>',
                method: 'clipboard'
            });
        }
    }

    // Track message view
    if (typeof ACI !== 'undefined' && ACI.App) {
        ACI.App.trackEvent('success_message_viewed', {
            affiliate_code: '<?php echo esc_js($affiliate_code); ?>',
            message_type: 'affiliate_success',
            auto_close: autoClose
        });
    }
});
</script>