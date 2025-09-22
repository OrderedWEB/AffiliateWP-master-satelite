<?php
/**
 * Default Popup Template for Affiliate Client Integration
 * 
 * Path: /wp-content/plugins/affiliate-client-integration/templates/popups/default-popup.php
 * Plugin: Affiliate Client Integration (Satellite)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$plugin = aci_init();
$settings = $plugin->get_settings();
?>

<div id="aci-popup-container" class="aci-popup-container" style="display: none;">
    <div class="aci-popup-overlay"></div>
    <div class="aci-popup-content">
        
        <!-- Popup Header -->
        <div class="aci-popup-header">
            <h3 class="aci-popup-title">
                <?php _e('Have an Affiliate Code?', 'affiliate-client-integration'); ?>
            </h3>
            <button type="button" class="aci-popup-close" aria-label="<?php esc_attr_e('Close', 'affiliate-client-integration'); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

        <!-- Popup Body -->
        <div class="aci-popup-body">
            <p class="aci-popup-description">
                <?php _e('Enter your affiliate code below to get an exclusive discount on your purchase.', 'affiliate-client-integration'); ?>
            </p>

            <form class="aci-affiliate-form aci-popup-form" method="post">
                <?php wp_nonce_field('aci_validate_code', 'aci_nonce'); ?>
                
                <div class="aci-form-group">
                    <label for="aci-popup-code-input" class="aci-form-label">
                        <?php _e('Affiliate Code', 'affiliate-client-integration'); ?>
                    </label>
                    <div class="aci-input-wrapper">
                        <input 
                            type="text" 
                            id="aci-popup-code-input"
                            name="affiliate_code"
                            class="aci-code-input aci-form-control" 
                            placeholder="<?php esc_attr_e('Enter your code here', 'affiliate-client-integration'); ?>"
                            autocomplete="off"
                            maxlength="50"
                            aria-describedby="aci-code-help"
                        />
                        <div class="aci-input-status">
                            <span class="aci-status-icon aci-status-valid" aria-hidden="true">✓</span>
                            <span class="aci-status-icon aci-status-invalid" aria-hidden="true">✗</span>
                            <span class="aci-status-icon aci-status-loading" aria-hidden="true">
                                <span class="aci-spinner"></span>
                            </span>
                        </div>
                    </div>
                    <small id="aci-code-help" class="aci-form-help">
                        <?php _e('Enter the code exactly as provided by your affiliate', 'affiliate-client-integration'); ?>
                    </small>
                </div>

                <div class="aci-form-group">
                    <button type="submit" class="aci-apply-btn aci-btn-primary">
                        <span class="aci-btn-text"><?php _e('Apply Code', 'affiliate-client-integration'); ?></span>
                        <span class="aci-btn-loading" style="display: none;">
                            <span class="aci-spinner"></span>
                            <?php _e('Validating...', 'affiliate-client-integration'); ?>
                        </span>
                    </button>
                </div>

                <!-- Message Container -->
                <div class="aci-message-container" role="alert" aria-live="polite"></div>
            </form>

            <!-- Benefits Section -->
            <div class="aci-benefits-section">
                <h4 class="aci-benefits-title">
                    <?php _e('Why use an affiliate code?', 'affiliate-client-integration'); ?>
                </h4>
                <ul class="aci-benefits-list">
                    <li class="aci-benefit-item">
                        <span class="aci-benefit-icon">💰</span>
                        <span class="aci-benefit-text"><?php _e('Exclusive discounts and savings', 'affiliate-client-integration'); ?></span>
                    </li>
                    <li class="aci-benefit-item">
                        <span class="aci-benefit-icon">🎁</span>
                        <span class="aci-benefit-text"><?php _e('Special offers not available elsewhere', 'affiliate-client-integration'); ?></span>
                    </li>
                    <li class="aci-benefit-item">
                        <span class="aci-benefit-icon">⚡</span>
                        <span class="aci-benefit-text"><?php _e('Instant discount application', 'affiliate-client-integration'); ?></span>
                    </li>
                    <li class="aci-benefit-item">
                        <span class="aci-benefit-icon">🔒</span>
                        <span class="aci-benefit-text"><?php _e('Secure and verified codes', 'affiliate-client-integration'); ?></span>
                    </li>
                </ul>
            </div>

            <!-- FAQ Section -->
            <div class="aci-faq-section">
                <button type="button" class="aci-faq-toggle" aria-expanded="false">
                    <span><?php _e('Frequently Asked Questions', 'affiliate-client-integration'); ?></span>
                    <span class="aci-faq-arrow">▼</span>
                </button>
                <div class="aci-faq-content" style="display: none;">
                    <div class="aci-faq-item">
                        <h5 class="aci-faq-question"><?php _e('Where do I get an affiliate code?', 'affiliate-client-integration'); ?></h5>
                        <p class="aci-faq-answer">
                            <?php _e('Affiliate codes are provided by our partners, influencers, or through promotional campaigns. Check your email or the source that referred you to our site.', 'affiliate-client-integration'); ?>
                        </p>
                    </div>
                    <div class="aci-faq-item">
                        <h5 class="aci-faq-question"><?php _e('How long are codes valid?', 'affiliate-client-integration'); ?></h5>
                        <p class="aci-faq-answer">
                            <?php _e('Code validity varies. Most codes are valid for 30 days, but some special promotions may have different expiration dates.', 'affiliate-client-integration'); ?>
                        </p>
                    </div>
                    <div class="aci-faq-item">
                        <h5 class="aci-faq-question"><?php _e('Can I use multiple codes?', 'affiliate-client-integration'); ?></h5>
                        <p class="aci-faq-answer">
                            <?php _e('Generally, only one affiliate code can be used per order. The system will apply the best available discount for you.', 'affiliate-client-integration'); ?>
                        </p>
                    </div>
                    <div class="aci-faq-item">
                        <h5 class="aci-faq-question"><?php _e('What if my code doesn\'t work?', 'affiliate-client-integration'); ?></h5>
                        <p class="aci-faq-answer">
                            <?php _e('Please check that you\'ve entered the code correctly. If it still doesn\'t work, the code may have expired or reached its usage limit.', 'affiliate-client-integration'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Popup Footer -->
        <div class="aci-popup-footer">
            <div class="aci-security-badge">
                <span class="aci-security-icon">🔒</span>
                <span class="aci-security-text">
                    <?php _e('Secure validation powered by SSL encryption', 'affiliate-client-integration'); ?>
                </span>
            </div>
            <button type="button" class="aci-skip-code" data-action="close">
                <?php _e('Continue without code', 'affiliate-client-integration'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Popup Styles -->
<style>
.aci-popup-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 999999;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
}

.aci-popup-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
    animation: aci-fade-in 0.3s ease-out;
}

.aci-popup-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    animation: aci-slide-up 0.3s ease-out;
}

.aci-popup-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px 24px 0;
    border-bottom: 1px solid #e1e5e9;
    margin-bottom: 24px;
}

.aci-popup-title {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
    color: #1a202c;
}

.aci-popup-close {
    background: none;
    border: none;
    font-size: 28px;
    color: #a0aec0;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.aci-popup-close:hover {
    background: #f7fafc;
    color: #4a5568;
}

.aci-popup-body {
    padding: 0 24px 24px;
}

.aci-popup-description {
    color: #4a5568;
    margin-bottom: 24px;
    line-height: 1.6;
}

.aci-form-group {
    margin-bottom: 20px;
}

.aci-form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #2d3748;
    font-size: 14px;
}

.aci-input-wrapper {
    position: relative;
}

.aci-code-input {
    width: 100%;
    padding: 12px 48px 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 16px;
    font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
    transition: all 0.2s ease;
    background: #ffffff;
}

.aci-code-input:focus {
    outline: none;
    border-color: #3182ce;
    box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1);
}

.aci-code-input.aci-valid {
    border-color: #38a169;
    background: #f0fff4;
}

.aci-code-input.aci-invalid {
    border-color: #e53e3e;
    background: #fff5f5;
}

.aci-input-status {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
}

.aci-status-icon {
    display: none;
    font-size: 18px;
}

.aci-code-input.aci-valid ~ .aci-input-status .aci-status-valid {
    display: inline-block;
    color: #38a169;
}

.aci-code-input.aci-invalid ~ .aci-input-status .aci-status-invalid {
    display: inline-block;
    color: #e53e3e;
}

.aci-validating ~ .aci-input-status .aci-status-loading {
    display: inline-block;
}

.aci-form-help {
    display: block;
    margin-top: 6px;
    font-size: 12px;
    color: #718096;
}

.aci-apply-btn {
    width: 100%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 14px 24px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}

.aci-apply-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.aci-apply-btn:active {
    transform: translateY(0);
}

.aci-apply-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.aci-btn-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.aci-spinner {
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top: 2px solid white;
    border-radius: 50%;
    animation: aci-spin 1s linear infinite;
}

.aci-message-container {
    min-height: 20px;
}

.aci-message {
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 16px;
    font-size: 14px;
    font-weight: 500;
}

.aci-message-success {
    background: #c6f6d5;
    color: #22543d;
    border: 1px solid #9ae6b4;
}

.aci-message-error {
    background: #fed7d7;
    color: #742a2a;
    border: 1px solid #fc8181;
}

.aci-message-info {
    background: #bee3f8;
    color: #2a4365;
    border: 1px solid #90cdf4;
}

.aci-benefits-section {
    margin-top: 32px;
    padding: 20px;
    background: #f7fafc;
    border-radius: 8px;
}

.aci-benefits-title {
    margin: 0 0 16px 0;
    font-size: 16px;
    font-weight: 600;
    color: #2d3748;
}

.aci-benefits-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.aci-benefit-item {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
    gap: 12px;
}

.aci-benefit-item:last-child {
    margin-bottom: 0;
}

.aci-benefit-icon {
    font-size: 20px;
    flex-shrink: 0;
}

.aci-benefit-text {
    color: #4a5568;
    font-size: 14px;
    line-height: 1.5;
}

.aci-faq-section {
    margin-top: 24px;
    border-top: 1px solid #e2e8f0;
    padding-top: 24px;
}

.aci-faq-toggle {
    width: 100%;
    background: none;
    border: none;
    padding: 8px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: #4a5568;
    transition: color 0.2s ease;
}

.aci-faq-toggle:hover {
    color: #2d3748;
}

.aci-faq-arrow {
    transition: transform 0.2s ease;
}

.aci-faq-toggle[aria-expanded="true"] .aci-faq-arrow {
    transform: rotate(180deg);
}

.aci-faq-content {
    margin-top: 16px;
}

.aci-faq-item {
    margin-bottom: 16px;
}

.aci-faq-item:last-child {
    margin-bottom: 0;
}

.aci-faq-question {
    margin: 0 0 8px 0;
    font-size: 14px;
    font-weight: 600;
    color: #2d3748;
}

.aci-faq-answer {
    margin: 0;
    font-size: 13px;
    color: #4a5568;
    line-height: 1.5;
}

.aci-popup-footer {
    padding: 16px 24px;
    border-top: 1px solid #e2e8f0;
    background: #f7fafc;
    border-radius: 0 0 12px 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.aci-security-badge {
    display: flex;
    align-items: center;
    gap: 6px;
}

.aci-security-icon {
    font-size: 14px;
}

.aci-security-text {
    font-size: 12px;
    color: #718096;
}

.aci-skip-code {
    background: none;
    border: none;
    color: #718096;
    font-size: 12px;
    cursor: pointer;
    text-decoration: underline;
    transition: color 0.2s ease;
}

.aci-skip-code:hover {
    color: #4a5568;
}

/* Animations */
@keyframes aci-fade-in {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes aci-slide-up {
    from {
        opacity: 0;
        transform: translate(-50%, -40%);
    }
    to {
        opacity: 1;
        transform: translate(-50%, -50%);
    }
}

@keyframes aci-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 768px) {
    .aci-popup-content {
        width: 95%;
        max-height: 95vh;
        margin: 2.5vh auto;
        top: 0;
        left: 50%;
        transform: translateX(-50%);
        position: relative;
    }
    
    .aci-popup-header {
        padding: 20px 20px 0;
    }
    
    .aci-popup-title {
        font-size: 20px;
    }
    
    .aci-popup-body {
        padding: 0 20px 20px;
    }
    
    .aci-popup-footer {
        padding: 12px 20px;
        flex-direction: column;
        text-align: center;
    }
    
    .aci-benefits-section {
        padding: 16px;
    }
}

@media (max-width: 480px) {
    .aci-popup-content {
        width: 100%;
        height: 100vh;
        max-height: 100vh;
        border-radius: 0;
        top: 0;
        transform: translateX(-50%);
    }
    
    .aci-popup-header {
        padding: 16px 16px 0;
    }
    
    .aci-popup-body {
        padding: 0 16px 16px;
    }
    
    .aci-popup-footer {
        padding: 12px 16px;
        border-radius: 0;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .aci-popup-content {
        background: #1a202c;
        color: #e2e8f0;
    }
    
    .aci-popup-title {
        color: #f7fafc;
    }
    
    .aci-popup-header {
        border-bottom-color: #2d3748;
    }
    
    .aci-popup-description {
        color: #a0aec0;
    }
    
    .aci-form-label {
        color: #e2e8f0;
    }
    
    .aci-code-input {
        background: #2d3748;
        border-color: #4a5568;
        color: #f7fafc;
    }
    
    .aci-code-input:focus {
        border-color: #63b3ed;
    }
    
    .aci-benefits-section {
        background: #2d3748;
    }
    
    .aci-benefits-title {
        color: #f7fafc;
    }
    
    .aci-benefit-text {
        color: #a0aec0;
    }
    
    .aci-faq-section {
        border-top-color: #4a5568;
    }
    
    .aci-faq-toggle {
        color: #a0aec0;
    }
    
    .aci-faq-toggle:hover {
        color: #e2e8f0;
    }
    
    .aci-faq-question {
        color: #f7fafc;
    }
    
    .aci-faq-answer {
        color: #a0aec0;
    }
    
    .aci-popup-footer {
        background: #2d3748;
        border-top-color: #4a5568;
    }
    
    .aci-security-text {
        color: #a0aec0;
    }
    
    .aci-skip-code {
        color: #a0aec0;
    }
    
    .aci-skip-code:hover {
        color: #e2e8f0;
    }
}

/* High contrast mode */
@media (prefers-contrast: high) {
    .aci-popup-content {
        border: 2px solid #000;
    }
    
    .aci-code-input {
        border-width: 3px;
    }
    
    .aci-apply-btn {
        background: #000;
        border: 2px solid #000;
    }
    
    .aci-apply-btn:hover {
        background: #333;
        border-color: #333;
    }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
    .aci-popup-overlay,
    .aci-popup-content,
    .aci-apply-btn,
    .aci-faq-arrow,
    * {
        animation: none !important;
        transition: none !important;
    }
}
</style>

<!-- Popup JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // FAQ toggle functionality
    const faqToggle = document.querySelector('.aci-faq-toggle');
    const faqContent = document.querySelector('.aci-faq-content');
    
    if (faqToggle && faqContent) {
        faqToggle.addEventListener('click', function() {
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', !isExpanded);
            faqContent.style.display = isExpanded ? 'none' : 'block';
        });
    }
    
    // Skip code functionality
    const skipBtn = document.querySelector('.aci-skip-code');
    if (skipBtn) {
        skipBtn.addEventListener('click', function() {
            if (typeof ACI !== 'undefined' && ACI.closePopup) {
                ACI.closePopup();
            }
        });
    }
    
    // Enhanced accessibility
    const popup = document.getElementById('aci-popup-container');
    if (popup) {
        // Trap focus within popup
        const focusableElements = popup.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        const firstFocusable = focusableElements[0];
        const lastFocusable = focusableElements[focusableElements.length - 1];
        
        popup.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                if (e.shiftKey) {
                    if (document.activeElement === firstFocusable) {
                        e.preventDefault();
                        lastFocusable.focus();
                    }
                } else {
                    if (document.activeElement === lastFocusable) {
                        e.preventDefault();
                        firstFocusable.focus();
                    }
                }
            }
        });
    }
});
</script>