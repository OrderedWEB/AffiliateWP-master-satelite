<?php
/**
 * Fullscreen Popup Template
 * Plugin: Affiliate Client Integration
 * Path: /wp-content/plugins/affiliate-client-integration/templates/popups/fullscreen-popup.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get configuration
$popup_config = ACI_Popup_Manager::get_popup_config('fullscreen');
$form_fields = ACI_Settings::get_setting('form_fields', []);
$popup_id = !empty($popup_id) ? $popup_id : 'aci-fullscreen-popup-' . wp_generate_uuid4();
$animation = !empty($animation) ? $animation : 'slideUp';
$background_image = ACI_Settings::get_setting('fullscreen_background_image', '');
$background_color = ACI_Settings::get_setting('fullscreen_background_color', '#0073aa');
$text_color = ACI_Settings::get_setting('fullscreen_text_color', '#ffffff');
?>

<div class="aci-popup-overlay aci-fullscreen-overlay" 
     data-animation="<?php echo esc_attr($animation); ?>"
     style="<?php if ($background_image): ?>background-image: url('<?php echo esc_url($background_image); ?>');<?php else: ?>background-color: <?php echo esc_attr($background_color); ?>;<?php endif; ?>">
     
    <!-- Background Pattern/Overlay -->
    <div class="aci-fullscreen-background">
        <?php if ($background_image): ?>
            <div class="aci-background-overlay" style="background-color: <?php echo esc_attr($background_color); ?>80;"></div>
        <?php endif; ?>
        
        <!-- Decorative Elements -->
        <div class="aci-decorative-elements">
            <div class="aci-floating-shape aci-shape-1"></div>
            <div class="aci-floating-shape aci-shape-2"></div>
            <div class="aci-floating-shape aci-shape-3"></div>
        </div>
    </div>

    <div class="aci-popup-content aci-fullscreen-content" style="color: <?php echo esc_attr($text_color); ?>;">
        <!-- Close Button -->
        <button type="button" class="aci-popup-close aci-fullscreen-close" 
                aria-label="<?php esc_attr_e('Close popup', 'affiliate-client-integration'); ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
            </svg>
        </button>

        <!-- Main Content Container -->
        <div class="aci-fullscreen-container">
            <!-- Hero Section -->
            <div class="aci-hero-section">
                <!-- Logo/Brand -->
                <?php $logo = ACI_Settings::get_setting('fullscreen_logo', ''); ?>
                <?php if ($logo): ?>
                    <div class="aci-brand-logo">
                        <img src="<?php echo esc_url($logo); ?>" alt="<?php esc_attr_e('Brand Logo', 'affiliate-client-integration'); ?>">
                    </div>
                <?php endif; ?>

                <!-- Main Title -->
                <h1 class="aci-hero-title" id="<?php echo $popup_id; ?>-title">
                    <?php echo esc_html(ACI_Settings::get_setting('fullscreen_title', __('Unlock Your Exclusive Discount', 'affiliate-client-integration'))); ?>
                </h1>

                <!-- Subtitle -->
                <p class="aci-hero-subtitle" id="<?php echo $popup_id; ?>-description">
                    <?php echo wp_kses_post(ACI_Settings::get_setting('fullscreen_subtitle', __('Enter your affiliate code and save big on your purchase today', 'affiliate-client-integration'))); ?>
                </p>

                <!-- Benefits List -->
                <?php $benefits = ACI_Settings::get_setting('fullscreen_benefits', []); ?>
                <?php if (!empty($benefits)): ?>
                    <div class="aci-benefits-list">
                        <?php foreach ($benefits as $benefit): ?>
                            <div class="aci-benefit-item">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" class="aci-benefit-icon">
                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                </svg>
                                <span><?php echo esc_html($benefit); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Form Section -->
            <div class="aci-form-section">
                <div class="aci-form-container">
                    <!-- Form Card -->
                    <div class="aci-form-card">
                        <div class="aci-form-header">
                            <h2 class="aci-form-title">
                                <?php _e('Enter Your Code', 'affiliate-client-integration'); ?>
                            </h2>
                            <p class="aci-form-description">
                                <?php _e('Enter the affiliate code you received to apply your discount', 'affiliate-client-integration'); ?>
                            </p>
                        </div>

                        <!-- Affiliate Form -->
                        <form class="aci-affiliate-form aci-fullscreen-form" method="post" data-popup-id="<?php echo $popup_id; ?>">
                            <?php wp_nonce_field('aci_validate_affiliate', 'aci_nonce'); ?>
                            
                            <!-- Primary Code Input -->
                            <div class="aci-form-group aci-primary-input">
                                <label for="<?php echo $popup_id; ?>-affiliate-code" class="aci-form-label">
                                    <?php _e('Affiliate Code', 'affiliate-client-integration'); ?>
                                    <span class="aci-required" aria-label="<?php esc_attr_e('Required', 'affiliate-client-integration'); ?>">*</span>
                                </label>
                                <div class="aci-input-wrapper">
                                    <input 
                                        type="text" 
                                        id="<?php echo $popup_id; ?>-affiliate-code" 
                                        name="affiliate_code" 
                                        class="aci-form-control aci-affiliate-code-input" 
                                        placeholder="<?php esc_attr_e('Enter your code here', 'affiliate-client-integration'); ?>"
                                        required
                                        autocomplete="off"
                                        spellcheck="false"
                                        aria-describedby="<?php echo $popup_id; ?>-code-help"
                                    >
                                    <div class="aci-input-icon">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12.87,15.07L10.33,12.56L10.36,12.53C12.1,10.59 13.34,8.36 14.07,6H17V4H10V2H8V4H1V6H12.17C11.5,7.92 10.44,9.75 9,11.35C8.07,10.32 7.3,9.19 6.69,8H4.69C5.42,9.63 6.42,11.17 7.67,12.56L2.58,17.58L4,19L9,14L12.11,17.11L12.87,15.07Z"/>
                                        </svg>
                                    </div>
                                </div>
                                <div id="<?php echo $popup_id; ?>-code-help" class="aci-form-help">
                                    <?php _e('This code was provided by your affiliate partner', 'affiliate-client-integration'); ?>
                                </div>
                            </div>

                            <!-- Additional Fields -->
                            <?php if (!empty($form_fields)): ?>
                                <div class="aci-additional-fields">
                                    <div class="aci-fields-grid">
                                        <?php foreach ($form_fields as $field_key => $field_config): ?>
                                            <?php if ($field_config['enabled'] && $field_key !== 'affiliate_code'): ?>
                                            <div class="aci-form-group">
                                                <label for="<?php echo $popup_id . '-' . $field_key; ?>" class="aci-form-label">
                                                    <?php echo esc_html($field_config['label']); ?>
                                                    <?php if ($field_config['required']): ?>
                                                        <span class="aci-required" aria-label="<?php esc_attr_e('Required', 'affiliate-client-integration'); ?>">*</span>
                                                    <?php endif; ?>
                                                </label>
                                                
                                                <?php if ($field_config['type'] === 'text' || $field_config['type'] === 'email'): ?>
                                                    <input 
                                                        type="<?php echo esc_attr($field_config['type']); ?>"
                                                        id="<?php echo $popup_id . '-' . $field_key; ?>"
                                                        name="<?php echo esc_attr($field_key); ?>"
                                                        class="aci-form-control"
                                                        placeholder="<?php echo esc_attr($field_config['placeholder'] ?? ''); ?>"
                                                        <?php echo $field_config['required'] ? 'required' : ''; ?>
                                                    >
                                                <?php elseif ($field_config['type'] === 'select'): ?>
                                                    <select 
                                                        id="<?php echo $popup_id . '-' . $field_key; ?>"
                                                        name="<?php echo esc_attr($field_key); ?>"
                                                        class="aci-form-control"
                                                        <?php echo $field_config['required'] ? 'required' : ''; ?>
                                                    >
                                                        <option value=""><?php _e('Select...', 'affiliate-client-integration'); ?></option>
                                                        <?php if (!empty($field_config['options'])): ?>
                                                            <?php foreach ($field_config['options'] as $option_value => $option_label): ?>
                                                                <option value="<?php echo esc_attr($option_value); ?>">
                                                                    <?php echo esc_html($option_label); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </select>
                                                <?php elseif ($field_config['type'] === 'textarea'): ?>
                                                    <textarea 
                                                        id="<?php echo $popup_id . '-' . $field_key; ?>"
                                                        name="<?php echo esc_attr($field_key); ?>"
                                                        class="aci-form-control"
                                                        placeholder="<?php echo esc_attr($field_config['placeholder'] ?? ''); ?>"
                                                        rows="3"
                                                        <?php echo $field_config['required'] ? 'required' : ''; ?>
                                                    ></textarea>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($field_config['help'])): ?>
                                                    <div class="aci-form-help"><?php echo esc_html($field_config['help']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Submit Button -->
                            <div class="aci-form-actions">
                                <button type="submit" class="aci-btn aci-btn-primary aci-btn-large aci-submit-btn">
                                    <span class="aci-btn-text"><?php _e('Apply Discount', 'affiliate-client-integration'); ?></span>
                                    <span class="aci-btn-loading" style="display: none;">
                                        <svg class="aci-spinner" width="20" height="20" viewBox="0 0 50 50">
                                            <circle class="path" cx="25" cy="25" r="20" fill="none" stroke="currentColor" 
                                                    stroke-width="5" stroke-miterlimit="10"/>
                                        </svg>
                                    </span>
                                    <svg class="aci-btn-arrow" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M4,11V13H16L10.5,18.5L11.92,19.92L19.84,12L11.92,4.08L10.5,5.5L16,11H4Z"/>
                                    </svg>
                                </button>
                            </div>

                            <!-- Form Messages -->
                            <div class="aci-form-messages">
                                <div class="aci-success-message" style="display: none;" role="alert"></div>
                                <div class="aci-error-message" style="display: none;" role="alert"></div>
                            </div>

                            <!-- Progress Indicator -->
                            <div class="aci-progress-bar" style="display: none;">
                                <div class="aci-progress-fill"></div>
                            </div>
                        </form>

                        <!-- Security Badge -->
                        <div class="aci-security-badge">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12,1L3,5V11C3,16.55 6.84,21.74 12,23C17.16,21.74 21,16.55 21,11V5L12,1M10,17L6,13L7.41,11.59L10,14.17L16.59,7.58L18,9L10,17Z"/>
                            </svg>
                            <span><?php _e('100% Secure & Encrypted', 'affiliate-client-integration'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Section -->
            <div class="aci-footer-section">
                <p class="aci-footer-text">
                    <?php echo wp_kses_post(ACI_Settings::get_setting('fullscreen_footer_text', __('Questions? Contact our support team for assistance.', 'affiliate-client-integration'))); ?>
                </p>
                
                <?php $support_link = ACI_Settings::get_setting('support_link', ''); ?>
                <?php if ($support_link): ?>
                    <a href="<?php echo esc_url($support_link); ?>" class="aci-support-link" target="_blank">
                        <?php _e('Get Support', 'affiliate-client-integration'); ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M14,3V5H17.59L7.76,14.83L9.17,16.24L19,6.41V10H21V3M19,19H5V5H12V3H5C3.89,3 3,3.9 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V12H19V19Z"/>
                        </svg>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.aci-fullscreen-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    animation-duration: 0.5s;
    animation-fill-mode: both;
}

.aci-fullscreen-background {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1;
}

.aci-background-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    backdrop-filter: blur(2px);
}

.aci-decorative-elements {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    pointer-events: none;
}

.aci-floating-shape {
    position: absolute;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    animation: aci-float 6s ease-in-out infinite;
}

.aci-shape-1 {
    width: 200px;
    height: 200px;
    top: 20%;
    left: 10%;
    animation-delay: 0s;
}

.aci-shape-2 {
    width: 150px;
    height: 150px;
    top: 60%;
    right: 15%;
    animation-delay: 2s;
}

.aci-shape-3 {
    width: 100px;
    height: 100px;
    bottom: 20%;
    left: 20%;
    animation-delay: 4s;
}

@keyframes aci-float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    33% { transform: translateY(-20px) rotate(120deg); }
    66% { transform: translateY(10px) rotate(240deg); }
}

.aci-fullscreen-content {
    position: relative;
    z-index: 2;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    max-width: 1200px;
    padding: 40px;
    box-sizing: border-box;
}

.aci-fullscreen-close {
    position: absolute;
    top: 30px;
    right: 30px;
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: currentColor;
    cursor: pointer;
    padding: 12px;
    border-radius: 50%;
    transition: all 0.3s ease;
    z-index: 10;
    backdrop-filter: blur(10px);
}

.aci-fullscreen-close:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    transform: scale(1.1);
}

.aci-fullscreen-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
    height: 100%;
    min-height: 600px;
}

/* Hero Section */
.aci-hero-section {
    padding-right: 20px;
}

.aci-brand-logo {
    margin-bottom: 30px;
}

.aci-brand-logo img {
    max-height: 60px;
    width: auto;
}

.aci-hero-title {
    font-size: 48px;
    font-weight: 700;
    line-height: 1.2;
    margin: 0 0 20px 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.aci-hero-subtitle {
    font-size: 20px;
    line-height: 1.5;
    margin: 0 0 30px 0;
    opacity: 0.9;
}

.aci-benefits-list {
    margin-top: 40px;
}

.aci-benefit-item {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 15px;
    font-size: 16px;
    font-weight: 500;
}

.aci-benefit-icon {
    flex-shrink: 0;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    padding: 4px;
}

/* Form Section */
.aci-form-section {
    display: flex;
    justify-content: center;
    align-items: center;
}

.aci-form-container {
    width: 100%;
    max-width: 500px;
}

.aci-form-card {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.aci-form-header {
    text-align: center;
    margin-bottom: 30px;
}

.aci-form-title {
    font-size: 28px;
    font-weight: 600;
    color: #333;
    margin: 0 0 10px 0;
}

.aci-form-description {
    color: #666;
    font-size: 16px;
    margin: 0;
    line-height: 1.5;
}

/* Form Styling */
.aci-fullscreen-form .aci-form-group {
    margin-bottom: 25px;
}

.aci-form-label {
    display: block;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
    font-size: 16px;
}

.aci-primary-input .aci-input-wrapper {
    position: relative;
}

.aci-primary-input .aci-form-control {
    padding: 16px 50px 16px 16px;
    font-size: 18px;
    font-weight: 500;
}

.aci-input-icon {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
}

.aci-form-control {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    font-size: 16px;
    transition: all 0.3s ease;
    background: white;
    color: #333;
}

.aci-form-control:focus {
    outline: none;
    border-color: #0073aa;
    box-shadow: 0 0 0 4px rgba(0, 115, 170, 0.1);
}

.aci-form-control:invalid {
    border-color: #dc3232;
}

.aci-affiliate-code-input {
    text-transform: uppercase;
    letter-spacing: 2px;
    font-family: 'Courier New', monospace;
    text-align: center;
}

.aci-additional-fields {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.aci-fields-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.aci-fields-grid .aci-form-group {
    margin-bottom: 15px;
}

.aci-form-actions {
    margin-top: 30px;
}

.aci-btn-large {
    width: 100%;
    padding: 18px 24px;
    font-size: 18px;
    font-weight: 600;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    position: relative;
    overflow: hidden;
}

.aci-btn-primary {
    background: linear-gradient(135deg, #0073aa 0%, #005a87 100%);
    color: white;
}

.aci-btn-primary:hover {
    background: linear-gradient(135deg, #005a87 0%, #004066 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 115, 170, 0.3);
}

.aci-btn-primary:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.aci-btn-arrow {
    transition: transform 0.3s ease;
}

.aci-btn-primary:hover .aci-btn-arrow {
    transform: translateX(5px);
}

.aci-form-help {
    font-size: 14px;
    color: #666;
    margin-top: 6px;
    line-height: 1.4;
}

.aci-required {
    color: #dc3232;
    margin-left: 3px;
}

/* Messages */
.aci-form-messages {
    margin-top: 20px;
}

.aci-success-message,
.aci-error-message {
    padding: 15px;
    border-radius: 8px;
    font-size: 15px;
    line-height: 1.4;
    text-align: center;
}

.aci-success-message {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.aci-error-message {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Progress Bar */
.aci-progress-bar {
    height: 4px;
    background: #eee;
    border-radius: 2px;
    overflow: hidden;
    margin-top: 20px;
}

.aci-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #0073aa, #005a87);
    width: 0%;
    transition: width 0.3s ease;
    animation: aci-progress-shimmer 2s infinite;
}

@keyframes aci-progress-shimmer {
    0% { background-position: -200px 0; }
    100% { background-position: calc(200px + 100%) 0; }
}

.aci-security-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 20px;
    color: #46b450;
    font-size: 14px;
    font-weight: 500;
}

/* Footer Section */
.aci-footer-section {
    text-align: center;
    margin-top: auto;
    padding-top: 20px;
}

.aci-footer-text {
    margin: 0 0 15px 0;
    opacity: 0.8;
    font-size: 16px;
}

.aci-support-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: currentColor;
    text-decoration: none;
    padding: 8px 16px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.aci-support-link:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-2px);
}

/* Spinner Animation */
.aci-spinner {
    animation: aci-spin 1s linear infinite;
}

.aci-spinner .path {
    stroke-dasharray: 90, 150;
    stroke-dashoffset: 0;
    stroke-linecap: round;
    animation: aci-dash 1.5s ease-in-out infinite;
}

@keyframes aci-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@keyframes aci-dash {
    0% {
        stroke-dasharray: 1, 150;
        stroke-dashoffset: 0;
    }
    50% {
        stroke-dasharray: 90, 150;
        stroke-dashoffset: -35;
    }
    100% {
        stroke-dasharray: 90, 150;
        stroke-dashoffset: -124;
    }
}

/* Animations */
.aci-animate-slideUp {
    animation-name: aci-slideUp;
}

.aci-animate-slideDown {
    animation-name: aci-slideDown;
}

.aci-animate-fadeIn {
    animation-name: aci-fadeIn;
}

.aci-animate-zoomIn {
    animation-name: aci-zoomIn;
}

@keyframes aci-slideUp {
    from {
        opacity: 0;
        transform: translateY(50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes aci-slideDown {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes aci-fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes aci-zoomIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* Responsive Design */
@media (max-width: 1024px) {
    .aci-fullscreen-container {
        grid-template-columns: 1fr;
        gap: 40px;
        text-align: center;
    }
    
    .aci-hero-section {
        padding-right: 0;
        order: 2;
    }
    
    .aci-form-section {
        order: 1;
    }
    
    .aci-hero-title {
        font-size: 36px;
    }
    
    .aci-hero-subtitle {
        font-size: 18px;
    }
}

@media (max-width: 768px) {
    .aci-fullscreen-content {
        padding: 20px;
    }
    
    .aci-fullscreen-close {
        top: 20px;
        right: 20px;
        padding: 10px;
    }
    
    .aci-form-card {
        padding: 30px 25px;
    }
    
    .aci-hero-title {
        font-size: 28px;
    }
    
    .aci-hero-subtitle {
        font-size: 16px;
    }
    
    .aci-form-title {
        font-size: 24px;
    }
    
    .aci-fields-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    
    .aci-floating-shape {
        display: none;
    }
}

@media (max-width: 480px) {
    .aci-fullscreen-content {
        padding: 15px;
    }
    
    .aci-form-card {
        padding: 25px 20px;
        border-radius: 15px;
    }
    
    .aci-hero-title {
        font-size: 24px;
    }
    
    .aci-benefit-item {
        font-size: 14px;
    }
    
    .aci-form-control {
        font-size: 16px; /* Prevent zoom on iOS */
    }
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .aci-form-card {
        border: 2px solid;
    }
    
    .aci-form-control:focus {
        outline: 2px solid;
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    .aci-fullscreen-overlay {
        animation: none;
    }
    
    .aci-floating-shape {
        animation: none;
    }
    
    .aci-progress-fill {
        animation: none;
    }
    
    .aci-spinner {
        animation: none;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .aci-form-card {
        background: rgba(30, 30, 30, 0.95);
        color: #fff;
    }
    
    .aci-form-title,
    .aci-form-label {
        color: #fff;
    }
    
    .aci-form-description,
    .aci-form-help {
        color: #ccc;
    }
    
    .aci-form-control {
        background: rgba(255, 255, 255, 0.1);
        border-color: rgba(255, 255, 255, 0.2);
        color: #fff;
    }
    
    .aci-form-control:focus {
        border-color: #0073aa;
        background: rgba(255, 255, 255, 0.15);
    }
}
</style>