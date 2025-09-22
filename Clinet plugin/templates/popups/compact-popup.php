<?php
/**
 * Compact Popup Template
 * Plugin: Affiliate Client Integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get configuration
$popup_config = ACI_Popup_Manager::get_popup_config('compact');
$form_fields = ACI_Settings::get_setting('form_fields', []);
$popup_id = !empty($popup_id) ? $popup_id : 'aci-compact-popup-' . wp_generate_uuid4();
$animation = !empty($animation) ? $animation : 'fadeIn';
$position = !empty($position) ? $position : 'center';
?>

<div class="aci-popup-overlay aci-compact-overlay" data-animation="<?php echo esc_attr($animation); ?>">
    <div class="aci-popup-content aci-compact-content" data-position="<?php echo esc_attr($position); ?>">
        <!-- Popup Header -->
        <div class="aci-popup-header">
            <h3 class="aci-popup-title" id="<?php echo $popup_id; ?>-title">
                <?php echo esc_html(ACI_Settings::get_setting('popup_title', __('Get Your Discount', 'affiliate-client-integration'))); ?>
            </h3>
            <button type="button" class="aci-popup-close" aria-label="<?php esc_attr_e('Close popup', 'affiliate-client-integration'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                </svg>
            </button>
        </div>

        <!-- Popup Body -->
        <div class="aci-popup-body">
            <div id="<?php echo $popup_id; ?>-description" class="aci-popup-description">
                <?php echo wp_kses_post(ACI_Settings::get_setting('popup_description', __('Enter your affiliate code to apply your discount', 'affiliate-client-integration'))); ?>
            </div>

            <!-- Affiliate Form -->
            <form class="aci-affiliate-form aci-compact-form" method="post" data-popup-id="<?php echo $popup_id; ?>">
                <?php wp_nonce_field('aci_validate_affiliate', 'aci_nonce'); ?>
                
                <div class="aci-form-row">
                    <div class="aci-form-group aci-form-code-input">
                        <label for="<?php echo $popup_id; ?>-affiliate-code" class="aci-sr-only">
                            <?php _e('Affiliate Code', 'affiliate-client-integration'); ?>
                        </label>
                        <input 
                            type="text" 
                            id="<?php echo $popup_id; ?>-affiliate-code" 
                            name="affiliate_code" 
                            class="aci-form-control aci-affiliate-code-input" 
                            placeholder="<?php esc_attr_e('Enter affiliate code', 'affiliate-client-integration'); ?>"
                            required
                            autocomplete="off"
                            spellcheck="false"
                            aria-describedby="<?php echo $popup_id; ?>-code-help"
                        >
                        <div id="<?php echo $popup_id; ?>-code-help" class="aci-form-help">
                            <?php _e('Enter the code you received from your affiliate', 'affiliate-client-integration'); ?>
                        </div>
                    </div>
                    
                    <div class="aci-form-group aci-form-submit">
                        <button type="submit" class="aci-btn aci-btn-primary aci-submit-btn">
                            <span class="aci-btn-text"><?php _e('Apply', 'affiliate-client-integration'); ?></span>
                            <span class="aci-btn-loading" style="display: none;">
                                <svg class="aci-spinner" width="16" height="16" viewBox="0 0 50 50">
                                    <circle class="path" cx="25" cy="25" r="20" fill="none" stroke="currentColor" 
                                            stroke-width="5" stroke-miterlimit="10"/>
                                </svg>
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Additional Fields (if configured) -->
                <?php if (!empty($form_fields)): ?>
                <div class="aci-additional-fields" style="display: none;">
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
                            <?php endif; ?>
                            
                            <?php if (!empty($field_config['help'])): ?>
                                <div class="aci-form-help"><?php echo esc_html($field_config['help']); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Form Messages -->
                <div class="aci-form-messages">
                    <div class="aci-success-message" style="display: none;" role="alert"></div>
                    <div class="aci-error-message" style="display: none;" role="alert"></div>
                </div>
            </form>

            <!-- Progress Indicator -->
            <div class="aci-progress-bar" style="display: none;">
                <div class="aci-progress-fill"></div>
            </div>
        </div>

        <!-- Popup Footer -->
        <div class="aci-popup-footer">
            <div class="aci-popup-info">
                <small class="aci-security-note">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12,1L3,5V11C3,16.55 6.84,21.74 12,23C17.16,21.74 21,16.55 21,11V5L12,1M10,17L6,13L7.41,11.59L10,14.17L16.59,7.58L18,9L10,17Z"/>
                    </svg>
                    <?php _e('Secure SSL encrypted', 'affiliate-client-integration'); ?>
                </small>
            </div>
        </div>
    </div>
</div>

<style>
.aci-compact-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(2px);
}

.aci-compact-content {
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    max-width: 400px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    animation-duration: 0.3s;
    animation-fill-mode: both;
}

.aci-compact-content[data-position="top"] {
    align-self: flex-start;
    margin-top: 5vh;
}

.aci-compact-content[data-position="bottom"] {
    align-self: flex-end;
    margin-bottom: 5vh;
}

/* Popup Header */
.aci-popup-header {
    padding: 20px 20px 0;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.aci-popup-title {
    font-size: 20px;
    font-weight: 600;
    color: #333;
    margin: 0;
    flex: 1;
}

.aci-popup-close {
    background: none;
    border: none;
    color: #999;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.aci-popup-close:hover {
    background: #f0f0f0;
    color: #666;
}

/* Popup Body */
.aci-popup-body {
    padding: 0 20px 20px;
}

.aci-popup-description {
    color: #666;
    margin-bottom: 20px;
    line-height: 1.5;
}

/* Compact Form */
.aci-compact-form .aci-form-row {
    display: flex;
    gap: 10px;
    align-items: flex-start;
}

.aci-form-code-input {
    flex: 1;
}

.aci-form-submit {
    flex-shrink: 0;
}

.aci-form-group {
    margin-bottom: 15px;
}

.aci-form-label {
    display: block;
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
    font-size: 14px;
}

.aci-form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 6px;
    font-size: 16px;
    transition: all 0.3s ease;
    background: white;
}

.aci-form-control:focus {
    outline: none;
    border-color: #0073aa;
    box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.1);
}

.aci-form-control:invalid {
    border-color: #dc3232;
}

.aci-affiliate-code-input {
    text-transform: uppercase;
    letter-spacing: 1px;
    font-family: monospace;
}

.aci-submit-btn {
    background: #0073aa;
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
}

.aci-submit-btn:hover {
    background: #005a87;
    transform: translateY(-1px);
}

.aci-submit-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
}

.aci-form-help {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
    line-height: 1.4;
}

.aci-required {
    color: #dc3232;
    margin-left: 2px;
}

.aci-sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* Additional Fields */
.aci-additional-fields {
    border-top: 1px solid #eee;
    padding-top: 15px;
    margin-top: 15px;
}

.aci-additional-fields .aci-form-group {
    margin-bottom: 12px;
}

.aci-additional-fields .aci-form-control {
    padding: 10px;
    font-size: 14px;
}

/* Messages */
.aci-form-messages {
    margin-top: 15px;
}

.aci-success-message,
.aci-error-message {
    padding: 12px;
    border-radius: 6px;
    font-size: 14px;
    line-height: 1.4;
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
    height: 3px;
    background: #eee;
    border-radius: 2px;
    overflow: hidden;
    margin-top: 15px;
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

/* Popup Footer */
.aci-popup-footer {
    padding: 15px 20px;
    border-top: 1px solid #eee;
    background: #fafafa;
    border-radius: 0 0 12px 12px;
}

.aci-security-note {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #666;
    font-size: 12px;
}

.aci-security-note svg {
    color: #46b450;
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
.aci-animate-fadeIn {
    animation-name: aci-fadeIn;
}

.aci-animate-slideDown {
    animation-name: aci-slideDown;
}

.aci-animate-slideUp {
    animation-name: aci-slideUp;
}

.aci-animate-zoomIn {
    animation-name: aci-zoomIn;
}

@keyframes aci-fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes aci-slideDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes aci-slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
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

/* Responsive */
@media (max-width: 768px) {
    .aci-compact-content {
        width: 95%;
        margin: 10px;
        max-height: calc(100vh - 40px);
    }
    
    .aci-compact-form .aci-form-row {
        flex-direction: column;
        gap: 15px;
    }
    
    .aci-popup-header {
        padding: 15px 15px 0;
    }
    
    .aci-popup-body {
        padding: 0 15px 15px;
    }
    
    .aci-popup-footer {
        padding: 12px 15px;
    }
    
    .aci-popup-title {
        font-size: 18px;
    }
    
    .aci-form-control {
        font-size: 16px; /* Prevent zoom on iOS */
    }
}

@media (max-width: 480px) {
    .aci-compact-content {
        width: 100%;
        margin: 0;
        border-radius: 0;
        max-height: 100vh;
    }
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .aci-compact-content {
        border: 2px solid;
    }
    
    .aci-form-control:focus {
        outline: 2px solid;
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    .aci-compact-content {
        animation: none;
    }
    
    .aci-progress-fill {
        animation: none;
    }
    
    .aci-spinner {
        animation: none;
    }
}
</style>