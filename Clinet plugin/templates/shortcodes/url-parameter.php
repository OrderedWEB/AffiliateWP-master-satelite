<?php
/**
 * URL Parameter Shortcode Template
 * Plugin: Affiliate Client Integration
 * Path: /wp-content/plugins/affiliate-client-integration/templates/shortcodes/url-parameter.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Extract shortcode attributes
$param_name = !empty($param_name) ? esc_attr($param_name) : 'affiliate_code';
$default_value = !empty($default_value) ? esc_html($default_value) : '';
$format = !empty($format) ? esc_attr($format) : 'text';
$prefix = !empty($prefix) ? esc_html($prefix) : '';
$suffix = !empty($suffix) ? esc_html($suffix) : '';
$cache_duration = !empty($cache_duration) ? (int)$cache_duration : 3600;
$element_id = 'aci-url-param-' . wp_generate_uuid4();

// Get URL parameter value
$param_value = '';
if (isset($_GET[$param_name])) {
    $param_value = Sanitise_text_field($_GET[$param_name]);
} elseif (isset($_POST[$param_name])) {
    $param_value = Sanitise_text_field($_POST[$param_name]);
}

// Use default if no value found
if (empty($param_value)) {
    $param_value = $default_value;
}

// Apply formatting
$formatted_value = $param_value;
switch ($format) {
    case 'uppercase':
        $formatted_value = strtoupper($param_value);
        break;
    case 'lowercase':
        $formatted_value = strtolower($param_value);
        break;
    case 'capitalize':
        $formatted_value = ucwords($param_value);
        break;
    case 'code':
        $formatted_value = '<code>' . esc_html($param_value) . '</code>';
        break;
    case 'badge':
        $formatted_value = '<span class="aci-badge">' . esc_html($param_value) . '</span>';
        break;
    case 'link':
        if (!empty($link_url)) {
            $link_url = str_replace('{value}', urlencode($param_value), esc_url($link_url));
            $formatted_value = '<a href="' . $link_url . '" class="aci-param-link">' . esc_html($param_value) . '</a>';
        }
        break;
}

// Add prefix and suffix
$final_output = $prefix . $formatted_value . $suffix;
?>

<span 
    id="<?php echo $element_id; ?>" 
    class="aci-url-parameter aci-format-<?php echo $format; ?>" 
    data-param-name="<?php echo $param_name; ?>"
    data-param-value="<?php echo esc_attr($param_value); ?>"
    data-cache-duration="<?php echo $cache_duration; ?>"
    <?php if (!empty($param_value)): ?>data-has-value="true"<?php endif; ?>
>
    <?php echo $final_output; ?>
</span>

<?php if (!empty($auto_update) && $auto_update === 'true'): ?>
<script type="text/javascript">
(function($) {
    'use strict';
    
    $(document).ready(function() {
        const $element = $('#<?php echo $element_id; ?>');
        const paramName = '<?php echo $param_name; ?>';
        const cacheDuration = <?php echo $cache_duration; ?>;
        
        // Auto-update functionality
        if (window.history && window.history.pushState) {
            // Listen for popstate events (back/forward buttons)
            $(window).on('popstate', function(event) {
                updateParameterValue($element, paramName);
            });
            
            // Listen for hash changes
            $(window).on('hashchange', function(event) {
                updateParameterValue($element, paramName);
            });
        }
        
        // Periodic check for URL changes (for single-page applications)
        if (cacheDuration > 0) {
            setInterval(function() {
                updateParameterValue($element, paramName);
            }, cacheDuration * 1000);
        }
        
        /**
         * Update parameter value from current URL
         */
        function updateParameterValue($element, paramName) {
            const urlParams = new URLSearchParams(window.location.search);
            const newValue = urlParams.get(paramName);
            const currentValue = $element.data('param-value');
            
            if (newValue && newValue !== currentValue) {
                // Update the display
                updateDisplay($element, newValue);
                
                // Trigger custom event
                $element.trigger('aci:parameter-updated', {
                    paramName: paramName,
                    oldValue: currentValue,
                    newValue: newValue
                });
            }
        }
        
        /**
         * Update the display with new value
         */
        function updateDisplay($element, newValue) {
            const format = $element.hasClass('aci-format-code') ? 'code' :
                          $element.hasClass('aci-format-badge') ? 'badge' :
                          $element.hasClass('aci-format-uppercase') ? 'uppercase' :
                          $element.hasClass('aci-format-lowercase') ? 'lowercase' :
                          $element.hasClass('aci-format-capitalize') ? 'capitalize' : 'text';
            
            let formattedValue = newValue;
            
            switch (format) {
                case 'uppercase':
                    formattedValue = newValue.toUpperCase();
                    break;
                case 'lowercase':
                    formattedValue = newValue.toLowerCase();
                    break;
                case 'capitalize':
                    formattedValue = newValue.replace(/\w\S*/g, function(txt) {
                        return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
                    });
                    break;
                case 'code':
                    formattedValue = '<code>' + escapeHtml(newValue) + '</code>';
                    break;
                case 'badge':
                    formattedValue = '<span class="aci-badge">' + escapeHtml(newValue) + '</span>';
                    break;
            }
            
            // Add prefix and suffix
            const prefix = '<?php echo addslashes($prefix); ?>';
            const suffix = '<?php echo addslashes($suffix); ?>';
            
            $element.html(prefix + formattedValue + suffix);
            $element.data('param-value', newValue);
            $element.attr('data-has-value', 'true');
            
            // Add updated class for CSS animations
            $element.addClass('aci-updated');
            setTimeout(function() {
                $element.removeClass('aci-updated');
            }, 1000);
        }
        
        /**
         * Escape HTML entities
         */
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    });
})(jQuery);
</script>
<?php endif; ?>

<?php if (!empty($show_copy_button) && $show_copy_button === 'true'): ?>
<button 
    type="button" 
    class="aci-copy-button" 
    data-copy-value="<?php echo esc_attr($param_value); ?>"
    aria-label="<?php esc_attr_e('Copy to clipboard', 'affiliate-client-integration'); ?>"
    title="<?php esc_attr_e('Copy to clipboard', 'affiliate-client-integration'); ?>"
>
    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
        <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
    </svg>
</button>

<script type="text/javascript">
(function($) {
    'use strict';
    
    $(document).ready(function() {
        $('.aci-copy-button').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const copyValue = $button.data('copy-value');
            
            // Try to copy to clipboard
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(copyValue).then(function() {
                    showCopySuccess($button);
                }).catch(function() {
                    fallbackCopyTextToClipboard(copyValue, $button);
                });
            } else {
                fallbackCopyTextToClipboard(copyValue, $button);
            }
        });
        
        function fallbackCopyTextToClipboard(text, $button) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            textArea.style.top = "-999999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                showCopySuccess($button);
            } catch (err) {
                console.error('Failed to copy: ', err);
                showCopyError($button);
            }
            
            document.body.removeChild(textArea);
        }
        
        function showCopySuccess($button) {
            const originalHtml = $button.html();
            $button.html('<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>');
            $button.addClass('aci-copy-success');
            
            setTimeout(function() {
                $button.html(originalHtml);
                $button.removeClass('aci-copy-success');
            }, 2000);
        }
        
        function showCopyError($button) {
            $button.addClass('aci-copy-error');
            setTimeout(function() {
                $button.removeClass('aci-copy-error');
            }, 2000);
        }
    });
})(jQuery);
</script>
<?php endif; ?>

<style>
.aci-url-parameter {
    display: inline-block;
    transition: all 0.3s ease;
}

.aci-url-parameter.aci-updated {
    background-color: #fff3cd;
    padding: 2px 4px;
    border-radius: 3px;
    animation: aci-highlight 1s ease-out;
}

@keyframes aci-highlight {
    0% { background-color: #fff3cd; }
    100% { background-color: transparent; }
}

.aci-url-parameter[data-has-value="false"] {
    opacity: 0.6;
    font-style: italic;
}

.aci-format-code code {
    background: #f4f4f4;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', Courier, monospace;
    font-size: 0.9em;
}

.aci-badge {
    background: #0073aa;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.85em;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.aci-param-link {
    color: #0073aa;
    text-decoration: underline;
}

.aci-param-link:hover {
    color: #005a87;
}

.aci-copy-button {
    background: none;
    border: 1px solid #ddd;
    padding: 4px 6px;
    margin-left: 8px;
    border-radius: 3px;
    cursor: pointer;
    color: #666;
    transition: all 0.3s ease;
    vertical-align: middle;
}

.aci-copy-button:hover {
    background: #f0f0f0;
    border-color: #999;
    color: #333;
}

.aci-copy-button.aci-copy-success {
    background: #46b450;
    color: white;
    border-color: #46b450;
}

.aci-copy-button.aci-copy-error {
    background: #dc3232;
    color: white;
    border-color: #dc3232;
}

/* Responsive */
@media (max-width: 768px) {
    .aci-copy-button {
        padding: 6px 8px;
        margin-left: 4px;
    }
}
</style>