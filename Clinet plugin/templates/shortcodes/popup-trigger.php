<?php
/**
 * Popup Trigger Shortcode Template
 * 
 * Plugin: Affiliate Client Integration (Satellite)
 * File: /wp-content/plugins/affiliate-client-integration/templates/shortcodes/popup-trigger.php
 * Author: Richard King, starneconsulting.com
 * Email: r.king@starneconsulting.com
 * 
 * Renders a button or link that triggers the affiliate code popup.
 * Used by [affiliate_popup_trigger] shortcode.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Extract shortcode attributes
$button_text = !empty($text) ? esc_html($text) : __('Apply Discount Code', 'affiliate-client-integration');
$button_style = !empty($style) ? esc_attr($style) : 'button';
$button_size = !empty($size) ? esc_attr($size) : 'medium';
$button_color = !empty($color) ? esc_attr($color) : 'primary';
$icon = !empty($icon) ? esc_attr($icon) : 'tag';
$position = !empty($position) ? esc_attr($position) : 'inline';
$popup_id = !empty($popup_id) ? esc_attr($popup_id) : 'aci-popup-container';
$trigger_event = !empty($trigger) ? esc_attr($trigger) : 'click';
$element_id = 'aci-popup-trigger-' . wp_generate_uuid4();
$css_class = !empty($class) ? esc_attr($class) : '';

// Check if user already has discount applied
$has_discount = false;
if (class_exists('ACI_Session_Manager')) {
    $session = new ACI_Session_Manager();
    $has_discount = $session->has('affiliate_code');
}

// If user has discount and hide_when_active is true, return empty
if ($has_discount && !empty($hide_when_active)) {
    return '';
}

// Icon mapping
$icon_map = [
    'tag' => '🏷️',
    'gift' => '🎁',
    'star' => '⭐',
    'percent' => '%',
    'dollar' => ',
    'ticket' => '🎫',
    'cart' => '🛒',
    'none' => ''
];

$icon_display = isset($icon_map[$icon]) ? $icon_map[$icon] : $icon_map['tag'];

// Build CSS classes
$trigger_classes = [
    'aci-popup-trigger',
    'aci-trigger-' . $button_style,
    'aci-trigger-' . $button_size,
    'aci-trigger-' . $button_color,
    'aci-trigger-' . $position,
];

if ($css_class) {
    $trigger_classes[] = $css_class;
}

if ($has_discount) {
    $trigger_classes[] = 'aci-has-discount';
}

$trigger_class_string = implode(' ', $trigger_classes);

// Render trigger based on style
?>

<?php if ($button_style === 'link'): ?>
    <!-- Link Style Trigger -->
    <a href="#" 
       id="<?php echo $element_id; ?>"
       class="<?php echo $trigger_class_string; ?>"
       data-popup-id="<?php echo $popup_id; ?>"
       data-trigger-event="<?php echo $trigger_event; ?>"
       role="button"
       aria-label="<?php echo esc_attr($button_text); ?>">
        <?php if ($icon_display): ?>
            <span class="aci-trigger-icon"><?php echo $icon_display; ?></span>
        <?php endif; ?>
        <span class="aci-trigger-text"><?php echo $button_text; ?></span>
    </a>

<?php elseif ($button_style === 'floating'): ?>
    <!-- Floating Button Style -->
    <div id="<?php echo $element_id; ?>"
         class="<?php echo $trigger_class_string; ?>"
         data-popup-id="<?php echo $popup_id; ?>"
         data-trigger-event="<?php echo $trigger_event; ?>"
         role="button"
         tabindex="0"
         aria-label="<?php echo esc_attr($button_text); ?>">
        <div class="aci-floating-content">
            <?php if ($icon_display): ?>
                <span class="aci-trigger-icon"><?php echo $icon_display; ?></span>
            <?php endif; ?>
            <?php if (!empty($show_text_on_floating)): ?>
                <span class="aci-trigger-text"><?php echo $button_text; ?></span>
            <?php endif; ?>
        </div>
        <?php if ($has_discount): ?>
            <span class="aci-trigger-badge">✓</span>
        <?php endif; ?>
    </div>

<?php elseif ($button_style === 'banner'): ?>
    <!-- Banner Style Trigger -->
    <div id="<?php echo $element_id; ?>"
         class="<?php echo $trigger_class_string; ?>"
         data-popup-id="<?php echo $popup_id; ?>"
         data-trigger-event="<?php echo $trigger_event; ?>">
        <div class="aci-banner-content">
            <?php if ($icon_display): ?>
                <span class="aci-trigger-icon"><?php echo $icon_display; ?></span>
            <?php endif; ?>
            <div class="aci-banner-text">
                <div class="aci-banner-title"><?php echo $button_text; ?></div>
                <?php if (!empty($subtitle)): ?>
                    <div class="aci-banner-subtitle"><?php echo esc_html($subtitle); ?></div>
                <?php endif; ?>
            </div>
            <button type="button" class="aci-banner-button" role="button">
                <?php echo !empty($button_label) ? esc_html($button_label) : __('Apply Now', 'affiliate-client-integration'); ?>
            </button>
        </div>
        <?php if (!empty($dismissible)): ?>
            <button type="button" class="aci-banner-dismiss" aria-label="<?php _e('Dismiss', 'affiliate-client-integration'); ?>">×</button>
        <?php endif; ?>
    </div>

<?php elseif ($button_style === 'icon-only'): ?>
    <!-- Icon Only Trigger -->
    <button type="button"
            id="<?php echo $element_id; ?>"
            class="<?php echo $trigger_class_string; ?>"
            data-popup-id="<?php echo $popup_id; ?>"
            data-trigger-event="<?php echo $trigger_event; ?>"
            aria-label="<?php echo esc_attr($button_text); ?>"
            title="<?php echo esc_attr($button_text); ?>">
        <span class="aci-trigger-icon"><?php echo $icon_display; ?></span>
        <?php if ($has_discount): ?>
            <span class="aci-trigger-badge">✓</span>
        <?php endif; ?>
    </button>

<?php else: ?>
    <!-- Default Button Style Trigger -->
    <button type="button"
            id="<?php echo $element_id; ?>"
            class="<?php echo $trigger_class_string; ?>"
            data-popup-id="<?php echo $popup_id; ?>"
            data-trigger-event="<?php echo $trigger_event; ?>"
            aria-label="<?php echo esc_attr($button_text); ?>">
        <?php if ($icon_display): ?>
            <span class="aci-trigger-icon"><?php echo $icon_display; ?></span>
        <?php endif; ?>
        <span class="aci-trigger-text"><?php echo $button_text; ?></span>
        <?php if ($has_discount): ?>
            <span class="aci-trigger-badge">✓</span>
        <?php endif; ?>
    </button>
<?php endif; ?>

<?php if (!empty($animate_on_scroll)): ?>
<script type="text/javascript">
(function($) {
    'use strict';
    
    $(document).ready(function() {
        const $trigger = $('#<?php echo $element_id; ?>');
        
        // Animate on scroll into view
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    $trigger.addClass('aci-animate-in');
                }
            });
        }, {
            threshold: 0.1
        });
        
        observer.observe($trigger[0]);
    });
})(jQuery);
</script>
<?php endif; ?>

<?php if ($trigger_event === 'hover' || $trigger_event === 'delay'): ?>
<script type="text/javascript">
(function($) {
    'use strict';
    
    $(document).ready(function() {
        const $trigger = $('#<?php echo $element_id; ?>');
        const popupId = $trigger.data('popup-id');
        const triggerEvent = $trigger.data('trigger-event');
        
        if (triggerEvent === 'hover') {
            // Show popup on hover after delay
            let hoverTimeout;
            $trigger.on('mouseenter', function() {
                hoverTimeout = setTimeout(function() {
                    if (window.ACI_Frontend && typeof window.ACI_Frontend.showPopup === 'function') {
                        window.ACI_Frontend.showPopup(popupId);
                    }
                }, <?php echo !empty($hover_delay) ? intval($hover_delay) : 1000; ?>);
            });
            
            $trigger.on('mouseleave', function() {
                clearTimeout(hoverTimeout);
            });
        } else if (triggerEvent === 'delay') {
            // Show popup after delay
            setTimeout(function() {
                if (window.ACI_Frontend && typeof window.ACI_Frontend.showPopup === 'function') {
                    window.ACI_Frontend.showPopup(popupId);
                }
            }, <?php echo !empty($delay) ? intval($delay) * 1000 : 3000; ?>);
        }
    });
})(jQuery);
</script>
<?php endif; ?>

<?php if ($button_style === 'banner' && !empty($dismissible)): ?>
<script type="text/javascript">
(function($) {
    'use strict';
    
    $(document).ready(function() {
        const $banner = $('#<?php echo $element_id; ?>');
        
        // Handle dismiss button
        $banner.find('.aci-banner-dismiss').on('click', function(e) {
            e.stopPropagation();
            $banner.fadeOut(300, function() {
                $(this).remove();
            });
            
            // Set cookie to remember dismissal
            document.cookie = 'aci_banner_dismissed=1;path=/;max-age=<?php echo !empty($dismiss_duration) ? intval($dismiss_duration) : 86400; ?>';
        });
        
        // Check if banner was previously dismissed
        if (document.cookie.indexOf('aci_banner_dismissed=1') !== -1) {
            $banner.hide();
        }
    });
})(jQuery);
</script>
<?php endif; ?>

<style type="text/css">
/* Inline styles for trigger positioning and animations */
<?php if ($position === 'fixed-bottom-right'): ?>
    #<?php echo $element_id; ?> {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 9998;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
<?php elseif ($position === 'fixed-bottom-left'): ?>
    #<?php echo $element_id; ?> {
        position: fixed;
        bottom: 20px;
        left: 20px;
        z-index: 9998;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
<?php elseif ($position === 'fixed-top-right'): ?>
    #<?php echo $element_id; ?> {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9998;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
<?php elseif ($position === 'sticky-top'): ?>
    #<?php echo $element_id; ?> {
        position: sticky;
        top: 0;
        z-index: 9998;
        width: 100%;
    }
<?php endif; ?>

<?php if (!empty($animate_on_scroll)): ?>
    #<?php echo $element_id; ?> {
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.5s ease;
    }
    
    #<?php echo $element_id; ?>.aci-animate-in {
        opacity: 1;
        transform: translateY(0);
    }
<?php endif; ?>

<?php if (!empty($custom_css)): ?>
    <?php echo esc_html($custom_css); ?>
<?php endif; ?>
</style>