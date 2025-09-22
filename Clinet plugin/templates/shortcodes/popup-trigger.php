<?php
/**
 * Popup Trigger Shortcode Template
 * Plugin: Affiliate Client Integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Extract shortcode attributes
$button_text = !empty($button_text) ? esc_html($button_text) : __('Get Discount', 'affiliate-client-integration');
$button_class = !empty($button_class) ? esc_attr($button_class) : 'aci-popup-trigger-btn';
$popup_type = !empty($popup_type) ? esc_attr($popup_type) : 'compact';
$trigger_id = 'aci-trigger-' . wp_generate_uuid4();
$popup_id = 'aci-popup-' . wp_generate_uuid4();
$theme = !empty($theme) ? esc_attr($theme) : 'default';
$position = !empty($position) ? esc_attr($position) : 'center';
$animation = !empty($animation) ? esc_attr($animation) : 'fadeIn';
?>

<div class="aci-popup-trigger-wrapper" data-theme="<?php echo $theme; ?>">
    <button 
        type="button" 
        id="<?php echo $trigger_id; ?>"
        class="<?php echo $button_class; ?> aci-btn aci-btn-<?php echo $theme; ?>" 
        data-popup-type="<?php echo $popup_type; ?>"
        data-popup-id="<?php echo $popup_id; ?>"
        data-position="<?php echo $position; ?>"
        data-animation="<?php echo $animation; ?>"
        aria-label="<?php esc_attr_e('Open affiliate discount form', 'affiliate-client-integration'); ?>"
        role="button"
    >
        <?php if (!empty($icon) && $icon !== 'none'): ?>
            <span class="aci-btn-icon aci-icon-<?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
        <?php endif; ?>
        <span class="aci-btn-text"><?php echo $button_text; ?></span>
        <?php if (!empty($loading_icon) && $loading_icon === 'true'): ?>
            <span class="aci-btn-loading" style="display: none;" aria-hidden="true">
                <svg class="aci-spinner" width="16" height="16" viewBox="0 0 50 50">
                    <circle class="path" cx="25" cy="25" r="20" fill="none" stroke="currentColor" 
                            stroke-width="5" stroke-miterlimit="10"/>
                </svg>
            </span>
        <?php endif; ?>
    </button>
</div>

<!-- Popup Container (will be populated dynamically) -->
<div 
    id="<?php echo $popup_id; ?>" 
    class="aci-popup-container aci-popup-<?php echo $popup_type; ?> aci-theme-<?php echo $theme; ?>"
    style="display: none;"
    role="dialog"
    aria-modal="true"
    aria-labelledby="<?php echo $popup_id; ?>-title"
    aria-describedby="<?php echo $popup_id; ?>-description"
    data-popup-type="<?php echo $popup_type; ?>"
    data-position="<?php echo $position; ?>"
    data-animation="<?php echo $animation; ?>"
>
    <!-- Popup content will be loaded dynamically via AJAX -->
</div>

<script type="text/javascript">
(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Initialize popup trigger
        $('#<?php echo $trigger_id; ?>').on('click', function(e) {
            e.preventDefault();
            
            const $trigger = $(this);
            const $popup = $('#<?php echo $popup_id; ?>');
            const popupType = $trigger.data('popup-type');
            const position = $trigger.data('position');
            const animation = $trigger.data('animation');
            
            // Prevent multiple clicks during loading
            if ($trigger.hasClass('loading')) {
                return;
            }
            
            // Show loading state
            $trigger.addClass('loading');
            $trigger.find('.aci-btn-text').hide();
            $trigger.find('.aci-btn-loading').show();
            
            // Load popup content if not already loaded
            if ($popup.children().length === 0) {
                loadPopupContent($popup, popupType, {
                    position: position,
                    animation: animation,
                    trigger_id: '<?php echo $trigger_id; ?>'
                });
            } else {
                showPopup($popup, animation);
                $trigger.removeClass('loading');
                $trigger.find('.aci-btn-text').show();
                $trigger.find('.aci-btn-loading').hide();
            }
        });
        
        /**
         * Load popup content via AJAX
         */
        function loadPopupContent($popup, popupType, options) {
            const $trigger = $('#<?php echo $trigger_id; ?>');
            
            $.ajax({
                url: aci_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'aci_load_popup_content',
                    popup_type: popupType,
                    position: options.position,
                    animation: options.animation,
                    nonce: aci_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $popup.html(response.data.content);
                        showPopup($popup, options.animation);
                        
                        // Initialize form handlers
                        initializePopupForm($popup);
                    } else {
                        console.error('Failed to load popup content:', response.data);
                        showError($trigger, response.data.message || 'Failed to load content');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    showError($trigger, 'Network error occurred');
                },
                complete: function() {
                    $trigger.removeClass('loading');
                    $trigger.find('.aci-btn-text').show();
                    $trigger.find('.aci-btn-loading').hide();
                }
            });
        }
        
        /**
         * Show popup with animation
         */
        function showPopup($popup, animation) {
            $popup.show();
            $popup.addClass('aci-popup-visible');
            
            // Add animation class
            if (animation) {
                $popup.addClass('aci-animate-' + animation);
            }
            
            // Focus management for accessibility
            const firstInput = $popup.find('input, button, select, textarea').first();
            if (firstInput.length) {
                firstInput.focus();
            }
            
            // Add overlay click handler
            $(document).on('click.aci-popup', function(e) {
                if ($(e.target).hasClass('aci-popup-overlay')) {
                    closePopup($popup);
                }
            });
            
            // Add escape key handler
            $(document).on('keydown.aci-popup', function(e) {
                if (e.key === 'Escape') {
                    closePopup($popup);
                }
            });
        }
        
        /**
         * Close popup
         */
        function closePopup($popup) {
            $popup.removeClass('aci-popup-visible');
            
            setTimeout(function() {
                $popup.hide();
            }, 300);
            
            // Remove event handlers
            $(document).off('click.aci-popup keydown.aci-popup');
            
            // Return focus to trigger
            $('#<?php echo $trigger_id; ?>').focus();
        }
        
        /**
         * Initialize popup form handlers
         */
        function initializePopupForm($popup) {
            const $form = $popup.find('.aci-affiliate-form');
            
            if ($form.length) {
                $form.on('submit', function(e) {
                    e.preventDefault();
                    
                    // Form validation and submission handled by main form handler
                    if (typeof window.aciFormHandler !== 'undefined') {
                        window.aciFormHandler.submitForm($(this));
                    }
                });
            }
            
            // Close button handler
            $popup.on('click', '.aci-popup-close', function(e) {
                e.preventDefault();
                closePopup($popup);
            });
        }
        
        /**
         * Show error message
         */
        function showError($trigger, message) {
            const errorHtml = '<div class="aci-error-notice">' + message + '</div>';
            $trigger.after(errorHtml);
            
            setTimeout(function() {
                $trigger.next('.aci-error-notice').fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    });
})(jQuery);
</script>

<style>
.aci-popup-trigger-wrapper {
    display: inline-block;
    position: relative;
}

.aci-popup-trigger-btn {
    background: #0073aa;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 4px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    user-select: none;
}

.aci-popup-trigger-btn:hover {
    background: #005a87;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,115,170,0.3);
}

.aci-popup-trigger-btn:active {
    transform: translateY(0);
}

.aci-popup-trigger-btn.loading {
    opacity: 0.7;
    cursor: wait;
}

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

.aci-error-notice {
    background: #dc3232;
    color: white;
    padding: 8px 12px;
    border-radius: 4px;
    margin-top: 8px;
    font-size: 14px;
}

/* Theme variations */
.aci-btn-primary {
    background: #0073aa;
}

.aci-btn-secondary {
    background: #666;
}

.aci-btn-success {
    background: #46b450;
}

.aci-btn-danger {
    background: #dc3232;
}

.aci-btn-dark {
    background: #23282d;
}

/* Responsive */
@media (max-width: 768px) {
    .aci-popup-trigger-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>