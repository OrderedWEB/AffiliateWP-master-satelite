<?php
/**
 * Settings Page Template
 * File: /wp-content/plugins/affiliate-client-integration/admin/templates/settings-page.php
 * Plugin: Affiliate Client Integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$master_domain = get_option('aci_master_domain', '');
$api_key = get_option('aci_api_key', '');
$connection_status = !empty($master_domain) && !empty($api_key);
$last_sync = get_option('aci_last_sync', '');
?>

<div class="wrap aci-settings-wrap">
    <h1 class="aci-page-title">
        <span class="aci-icon">🔗</span>
        <?php _e('Affiliate Integration Settings', 'affiliate-client-integration'); ?>
    </h1>

    <?php if (isset($_GET['settings-updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Settings saved successfully!', 'affiliate-client-integration'); ?></p>
        </div>
    <?php endif; ?>

    <!-- Connection Status Banner -->
    <div class="aci-status-banner <?php echo $connection_status ? 'aci-connected' : 'aci-disconnected'; ?>">
        <div class="aci-status-content">
            <div class="aci-status-indicator">
                <?php if ($connection_status): ?>
                    <span class="aci-status-icon aci-success">✓</span>
                    <span class="aci-status-text"><?php _e('Connected to Master Domain', 'affiliate-client-integration'); ?></span>
                <?php else: ?>
                    <span class="aci-status-icon aci-warning">⚠</span>
                    <span class="aci-status-text"><?php _e('Not Connected', 'affiliate-client-integration'); ?></span>
                <?php endif; ?>
            </div>
            
            <?php if ($connection_status): ?>
                <div class="aci-status-details">
                    <span class="aci-status-detail">
                        <strong><?php _e('Domain:', 'affiliate-client-integration'); ?></strong>
                        <?php echo esc_html(parse_url($master_domain, PHP_URL_HOST)); ?>
                    </span>
                    <?php if ($last_sync): ?>
                        <span class="aci-status-detail">
                            <strong><?php _e('Last Sync:', 'affiliate-client-integration'); ?></strong>
                            <?php echo human_time_diff(strtotime($last_sync)); ?> <?php _e('ago', 'affiliate-client-integration'); ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="aci-status-actions">
                <button type="button" id="aci-test-connection" class="button button-secondary">
                    <?php _e('Test Connection', 'affiliate-client-integration'); ?>
                </button>
                <?php if ($connection_status): ?>
                    <button type="button" id="aci-sync-now" class="button button-secondary">
                        <?php _e('Sync Now', 'affiliate-client-integration'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <div id="aci-connection-result" class="aci-connection-result" style="display: none;">
            <!-- Connection test results will appear here -->
        </div>
    </div>

    <!-- Main Settings Form -->
    <form method="post" action="options.php" id="aci-settings-form">
        <?php
        settings_fields('aci_settings');
        ?>
        
        <div class="aci-settings-grid">
            <!-- Connection Settings -->
            <div class="aci-settings-section">
                <h2 class="aci-section-title">
                    <span class="aci-section-icon">🌐</span>
                    <?php _e('Master Domain Connection', 'affiliate-client-integration'); ?>
                </h2>
                
                <div class="aci-section-description">
                    <p><?php _e('Configure the connection to your master affiliate domain where affiliate codes are managed.', 'affiliate-client-integration'); ?></p>
                </div>

                <table class="form-table aci-form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="aci_master_domain"><?php _e('Master Domain URL', 'affiliate-client-integration'); ?></label>
                                <p class="description"><?php _e('The URL of your master affiliate domain', 'affiliate-client-integration'); ?></p>
                            </th>
                            <td>
                                <input type="url" 
                                       id="aci_master_domain" 
                                       name="aci_master_domain" 
                                       value="<?php echo esc_attr($master_domain); ?>"
                                       class="regular-text aci-input"
                                       placeholder="https://your-affiliate-domain.com"
                                       required>
                                <div class="aci-field-help">
                                    <?php _e('Enter the full URL including https:// but without trailing slash', 'affiliate-client-integration'); ?>
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aci_api_key"><?php _e('API Key', 'affiliate-client-integration'); ?></label>
                                <p class="description"><?php _e('Provided by your master domain administrator', 'affiliate-client-integration'); ?></p>
                            </th>
                            <td>
                                <input type="text" 
                                       id="aci_api_key" 
                                       name="aci_api_key" 
                                       value="<?php echo esc_attr($api_key); ?>"
                                       class="regular-text aci-input"
                                       autocomplete="off"
                                       required>
                                <div class="aci-field-help">
                                    <?php _e('This key authenticates your site with the master domain', 'affiliate-client-integration'); ?>
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aci_api_secret"><?php _e('API Secret', 'affiliate-client-integration'); ?></label>
                                <p class="description"><?php _e('Optional secret for enhanced security', 'affiliate-client-integration'); ?></p>
                            </th>
                            <td>
                                <input type="password" 
                                       id="aci_api_secret" 
                                       name="aci_api_secret" 
                                       value="<?php echo esc_attr(get_option('aci_api_secret', '')); ?>"
                                       class="regular-text aci-input"
                                       autocomplete="off">
                                <div class="aci-field-help">
                                    <?php _e('Leave empty if not provided by your master domain', 'affiliate-client-integration'); ?>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Display Settings -->
            <div class="aci-settings-section">
                <h2 class="aci-section-title">
                    <span class="aci-section-icon">🎨</span>
                    <?php _e('Display & Behavior', 'affiliate-client-integration'); ?>
                </h2>
                
                <div class="aci-section-description">
                    <p><?php _e('Control how affiliate elements appear and behave on your website.', 'affiliate-client-integration'); ?></p>
                </div>

                <?php
                $display_settings = get_option('aci_display_settings', [
                    'show_affiliate_notice' => true,
                    'show_discount_amount' => true,
                    'affiliate_notice_position' => 'top',
                    'auto_apply_discounts' => true
                ]);
                ?>

                <table class="form-table aci-form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><?php _e('Affiliate Notices', 'affiliate-client-integration'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" 
                                               name="aci_display_settings[show_affiliate_notice]" 
                                               value="1" 
                                               <?php checked($display_settings['show_affiliate_notice'] ?? true); ?>>
                                        <?php _e('Show notice when affiliate code is active', 'affiliate-client-integration'); ?>
                                    </label>
                                    <br><br>
                                    
                                    <label>
                                        <input type="checkbox" 
                                               name="aci_display_settings[show_discount_amount]" 
                                               value="1" 
                                               <?php checked($display_settings['show_discount_amount'] ?? true); ?>>
                                        <?php _e('Display discount amount in notices', 'affiliate-client-integration'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="affiliate_notice_position"><?php _e('Notice Position', 'affiliate-client-integration'); ?></label>
                            </th>
                            <td>
                                <select name="aci_display_settings[affiliate_notice_position]" id="affiliate_notice_position" class="aci-select">
                                    <option value="top" <?php selected($display_settings['affiliate_notice_position'] ?? 'top', 'top'); ?>><?php _e('Top of page', 'affiliate-client-integration'); ?></option>
                                    <option value="bottom" <?php selected($display_settings['affiliate_notice_position'] ?? 'top', 'bottom'); ?>><?php _e('Bottom of page', 'affiliate-client-integration'); ?></option>
                                    <option value="floating" <?php selected($display_settings['affiliate_notice_position'] ?? 'top', 'floating'); ?>><?php _e('Floating overlay', 'affiliate-client-integration'); ?></option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Auto-Apply Discounts', 'affiliate-client-integration'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="aci_auto_apply_discounts" 
                                           value="1" 
                                           <?php checked(get_option('aci_auto_apply_discounts', true)); ?>>
                                    <?php _e('Automatically apply affiliate discounts when codes are detected', 'affiliate-client-integration'); ?>
                                </label>
                                <div class="aci-field-help">
                                    <?php _e('When enabled, discounts are applied immediately when affiliate codes are found in URLs', 'affiliate-client-integration'); ?>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Performance Settings -->
            <div class="aci-settings-section">
                <h2 class="aci-section-title">
                    <span class="aci-section-icon">⚡</span>
                    <?php _e('Performance & Caching', 'affiliate-client-integration'); ?>
                </h2>
                
                <div class="aci-section-description">
                    <p><?php _e('Optimize plugin performance and caching behavior.', 'affiliate-client-integration'); ?></p>
                </div>

                <table class="form-table aci-form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="aci_cache_duration"><?php _e('Cache Duration', 'affiliate-client-integration'); ?></label>
                                <p class="description"><?php _e('How long to cache validation results', 'affiliate-client-integration'); ?></p>
                            </th>
                            <td>
                                <input type="number" 
                                       id="aci_cache_duration" 
                                       name="aci_cache_duration" 
                                       value="<?php echo esc_attr(get_option('aci_cache_duration', 300)); ?>"
                                       min="60" 
                                       max="3600"
                                       class="small-text aci-input">
                                <span class="aci-input-suffix"><?php _e('seconds', 'affiliate-client-integration'); ?></span>
                                <div class="aci-field-help">
                                    <?php _e('Range: 60-3600 seconds. Lower values = more real-time, higher values = better performance', 'affiliate-client-integration'); ?>
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Debug Logging', 'affiliate-client-integration'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="aci_enable_logging" 
                                           value="1" 
                                           <?php checked(get_option('aci_enable_logging', false)); ?>>
                                    <?php _e('Enable debug logging for troubleshooting', 'affiliate-client-integration'); ?>
                                </label>
                                <div class="aci-field-help">
                                    <?php _e('Logs are written to the WordPress debug.log file. Only enable when troubleshooting issues.', 'affiliate-client-integration'); ?>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Advanced Actions -->
            <div class="aci-settings-section">
                <h2 class="aci-section-title">
                    <span class="aci-section-icon">🔧</span>
                    <?php _e('Advanced Actions', 'affiliate-client-integration'); ?>
                </h2>
                
                <div class="aci-section-description">
                    <p><?php _e('Advanced tools for managing the plugin data and connections.', 'affiliate-client-integration'); ?></p>
                </div>

                <div class="aci-actions-grid">
                    <div class="aci-action-card">
                        <h3><?php _e('Sync Data', 'affiliate-client-integration'); ?></h3>
                        <p><?php _e('Manually sync affiliate codes and settings from the master domain.', 'affiliate-client-integration'); ?></p>
                        <button type="button" id="aci-sync-codes" class="button button-secondary">
                            <span class="aci-button-icon">🔄</span>
                            <?php _e('Sync Affiliate Codes', 'affiliate-client-integration'); ?>
                        </button>
                    </div>
                    
                    <div class="aci-action-card">
                        <h3><?php _e('Clear Cache', 'affiliate-client-integration'); ?></h3>
                        <p><?php _e('Clear all cached affiliate validation results and force fresh lookups.', 'affiliate-client-integration'); ?></p>
                        <button type="button" id="aci-clear-cache" class="button button-secondary">
                            <span class="aci-button-icon">🗑️</span>
                            <?php _e('Clear Cache', 'affiliate-client-integration'); ?>
                        </button>
                    </div>
                    
                    <div class="aci-action-card">
                        <h3><?php _e('Export Settings', 'affiliate-client-integration'); ?></h3>
                        <p><?php _e('Export your current configuration for backup or migration.', 'affiliate-client-integration'); ?></p>
                        <button type="button" id="aci-export-settings" class="button button-secondary">
                            <span class="aci-button-icon">📥</span>
                            <?php _e('Export Settings', 'affiliate-client-integration'); ?>
                        </button>
                    </div>
                    
                    <div class="aci-action-card">
                        <h3><?php _e('View Logs', 'affiliate-client-integration'); ?></h3>
                        <p><?php _e('View recent activity logs and debug information.', 'affiliate-client-integration'); ?></p>
                        <button type="button" id="aci-view-logs" class="button button-secondary">
                            <span class="aci-button-icon">📊</span>
                            <?php _e('View Logs', 'affiliate-client-integration'); ?>
                        </button>
                    </div>
                </div>
                
                <div id="aci-action-results" class="aci-action-results" style="display: none;">
                    <!-- Action results will appear here -->
                </div>
            </div>
        </div>

        <!-- Save Settings Button -->
        <div class="aci-submit-section">
            <?php submit_button(__('Save Settings', 'affiliate-client-integration'), 'primary large', 'submit', true, ['id' => 'aci-save-settings']); ?>
        </div>
    </form>

    <!-- Sidebar -->
    <div class="aci-sidebar">
        <!-- Plugin Info -->
        <div class="aci-sidebar-card">
            <h3><?php _e('Plugin Information', 'affiliate-client-integration'); ?></h3>
            <div class="aci-info-grid">
                <div class="aci-info-item">
                    <span class="aci-info-label"><?php _e('Version:', 'affiliate-client-integration'); ?></span>
                    <span class="aci-info-value"><?php echo defined('ACI_VERSION') ? ACI_VERSION : '1.0.0'; ?></span>
                </div>
                <div class="aci-info-item">
                    <span class="aci-info-label"><?php _e('Cache Status:', 'affiliate-client-integration'); ?></span>
                    <span class="aci-info-value <?php echo wp_using_ext_object_cache() ? 'aci-status-good' : 'aci-status-basic'; ?>">
                        <?php echo wp_using_ext_object_cache() ? __('External Cache', 'affiliate-client-integration') : __('File Cache', 'affiliate-client-integration'); ?>
                    </span>
                </div>
                <div class="aci-info-item">
                    <span class="aci-info-label"><?php _e('PHP Version:', 'affiliate-client-integration'); ?></span>
                    <span class="aci-info-value"><?php echo PHP_VERSION; ?></span>
                </div>
                <div class="aci-info-item">
                    <span class="aci-info-label"><?php _e('WordPress:', 'affiliate-client-integration'); ?></span>
                    <span class="aci-info-value"><?php echo get_bloginfo('version'); ?></span>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="aci-sidebar-card">
            <h3><?php _e('Documentation', 'affiliate-client-integration'); ?></h3>
            <ul class="aci-link-list">
                <li>
                    <a href="#" target="_blank" class="aci-external-link">
                        <span class="aci-link-icon">📖</span>
                        <?php _e('Setup Guide', 'affiliate-client-integration'); ?>
                    </a>
                </li>
                <li>
                    <a href="#" target="_blank" class="aci-external-link">
                        <span class="aci-link-icon">🏷️</span>
                        <?php _e('Shortcode Reference', 'affiliate-client-integration'); ?>
                    </a>
                </li>
                <li>
                    <a href="#" target="_blank" class="aci-external-link">
                        <span class="aci-link-icon">🔗</span>
                        <?php _e('API Documentation', 'affiliate-client-integration'); ?>
                    </a>
                </li>
                <li>
                    <a href="#" target="_blank" class="aci-external-link">
                        <span class="aci-link-icon">🚨</span>
                        <?php _e('Troubleshooting', 'affiliate-client-integration'); ?>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Support -->
        <div class="aci-sidebar-card">
            <h3><?php _e('Need Help?', 'affiliate-client-integration'); ?></h3>
            <p><?php _e('If you\'re experiencing issues or need assistance with setup, we\'re here to help.', 'affiliate-client-integration'); ?></p>
            <div class="aci-support-actions">
                <a href="#" class="button button-secondary aci-support-button" target="_blank">
                    <span class="aci-button-icon">💬</span>
                    <?php _e('Get Support', 'affiliate-client-integration'); ?>
                </a>
                <a href="#" class="button button-secondary aci-support-button" target="_blank">
                    <span class="aci-button-icon">🐛</span>
                    <?php _e('Report Bug', 'affiliate-client-integration'); ?>
                </a>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="aci-sidebar-card">
            <h3><?php _e('Recent Activity', 'affiliate-client-integration'); ?></h3>
            <div class="aci-activity-list">
                <?php
                $recent_activity = get_option('aci_recent_activity', []);
                if (!empty($recent_activity)):
                    foreach (array_slice($recent_activity, 0, 5) as $activity):
                ?>
                    <div class="aci-activity-item">
                        <span class="aci-activity-time"><?php echo human_time_diff(strtotime($activity['timestamp'])); ?> <?php _e('ago', 'affiliate-client-integration'); ?></span>
                        <span class="aci-activity-text"><?php echo esc_html($activity['message']); ?></span>
                    </div>
                <?php
                    endforeach;
                else:
                ?>
                    <div class="aci-activity-empty">
                        <p><?php _e('No recent activity', 'affiliate-client-integration'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Modals -->
<div id="aci-logs-modal" class="aci-modal" style="display: none;">
    <div class="aci-modal-content">
        <div class="aci-modal-header">
            <h2><?php _e('Activity Logs', 'affiliate-client-integration'); ?></h2>
            <button type="button" class="aci-modal-close">&times;</button>
        </div>
        <div class="aci-modal-body">
            <div id="aci-logs-content">
                <div class="aci-loading"><?php _e('Loading logs...', 'affiliate-client-integration'); ?></div>
            </div>
        </div>
        <div class="aci-modal-footer">
            <button type="button" class="button button-secondary aci-modal-close"><?php _e('Close', 'affiliate-client-integration'); ?></button>
            <button type="button" id="aci-download-logs" class="button button-primary"><?php _e('Download Logs', 'affiliate-client-integration'); ?></button>
        </div>
    </div>
</div>

<style>
/* Settings Page Styles */
.aci-settings-wrap {
    max-width: 1200px;
    margin: 20px 0;
}

.aci-page-title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 28px;
    margin-bottom: 24px;
    color: #1d2327;
}

.aci-page-title .aci-icon {
    font-size: 32px;
}

/* Status Banner */
.aci-status-banner {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.aci-status-banner.aci-connected {
    border-color: #00a32a;
    background: linear-gradient(90deg, #f6fff6 0%, #fff 100%);
}

.aci-status-banner.aci-disconnected {
    border-color: #dba617;
    background: linear-gradient(90deg, #fffbf0 0%, #fff 100%);
}

.aci-status-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
}

.aci-status-indicator {
    display: flex;
    align-items: center;
    gap: 12px;
}

.aci-status-icon {
    font-size: 20px;
    font-weight: bold;
}

.aci-status-icon.aci-success {
    color: #00a32a;
}

.aci-status-icon.aci-warning {
    color: #dba617;
}

.aci-status-text {
    font-size: 16px;
    font-weight: 600;
    color: #1d2327;
}

.aci-status-details {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.aci-status-detail {
    font-size: 14px;
    color: #646970;
}

.aci-status-actions {
    display: flex;
    gap: 12px;
}

.aci-connection-result {
    margin-top: 16px;
    padding: 12px;
    border-radius: 6px;
    font-size: 14px;
}

.aci-connection-result.aci-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.aci-connection-result.aci-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Settings Grid */
.aci-settings-grid {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 24px;
    margin-bottom: 24px;
}

.aci-settings-section {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    padding: 24px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.aci-section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 16px;
    font-size: 18px;
    color: #1d2327;
}

.aci-section-icon {
    font-size: 20px;
}

.aci-section-description {
    margin-bottom: 20px;
    color: #646970;
    font-size: 14px;
    line-height: 1.5;
}

.aci-form-table {
    margin-top: 0;
}

.aci-form-table th {
    width: 200px;
    padding: 12px 0;
    vertical-align: top;
}

.aci-form-table td {
    padding: 12px 0;
}

.aci-input {
    border-radius: 6px;
    border: 1px solid #8c8f94;
}

.aci-input:focus {
    border-color: #2271b1;
    box-shadow: 0 0 0 1px #2271b1;
}

.aci-select {
    border-radius: 6px;
    border: 1px solid #8c8f94;
    min-width: 200px;
}

.aci-field-help {
    font-size: 13px;
    color: #646970;
    margin-top: 6px;
    line-height: 1.4;
}

.aci-input-suffix {
    margin-left: 8px;
    color: #646970;
    font-size: 14px;
}

/* Actions Grid */
.aci-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
}

.aci-action-card {
    border: 1px solid #e2e4e7;
    border-radius: 8px;
    padding: 20px;
    background: #f9f9f9;
    transition: all 0.2s ease;
}

.aci-action-card:hover {
    border-color: #c3c4c7;
    background: #fff;
}

.aci-action-card h3 {
    margin: 0 0 8px;
    font-size: 16px;
    color: #1d2327;
}

.aci-action-card p {
    margin: 0 0 16px;
    font-size: 14px;
    color: #646970;
    line-height: 1.4;
}

.aci-button-icon {
    margin-right: 6px;
}

.aci-action-results {
    margin-top: 20px;
    padding: 16px;
    border-radius: 6px;
    font-size: 14px;
}

.aci-action-results.aci-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.aci-action-results.aci-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Sidebar */
.aci-sidebar {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.aci-sidebar-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.aci-sidebar-card h3 {
    margin: 0 0 16px;
    font-size: 16px;
    color: #1d2327;
}

.aci-info-grid {
    display: grid;
    gap: 8px;
}

.aci-info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 14px;
}

.aci-info-label {
    color: #646970;
}

.aci-info-value {
    font-weight: 600;
    color: #1d2327;
}

.aci-info-value.aci-status-good {
    color: #00a32a;
}

.aci-info-value.aci-status-basic {
    color: #dba617;
}

.aci-link-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.aci-link-list li {
    margin-bottom: 8px;
}

.aci-external-link {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #2271b1;
    text-decoration: none;
    font-size: 14px;
    padding: 8px 0;
    border-radius: 4px;
    transition: color 0.2s ease;
}

.aci-external-link:hover {
    color: #135e96;
}

.aci-link-icon {
    font-size: 16px;
}

.aci-support-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 12px;
}

.aci-support-button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-decoration: none;
}

.aci-activity-list {
    max-height: 200px;
    overflow-y: auto;
}

.aci-activity-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f1;
    font-size: 13px;
}

.aci-activity-item:last-child {
    border-bottom: none;
}

.aci-activity-time {
    color: #646970;
    font-size: 12px;
}

.aci-activity-text {
    color: #1d2327;
}

.aci-activity-empty {
    text-align: center;
    color: #646970;
    font-style: italic;
    padding: 20px 0;
}

/* Submit Section */
.aci-submit-section {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

/* Modal Styles */
.aci-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.aci-modal-content {
    background: #fff;
    border-radius: 8px;
    max-width: 80%;
    max-height: 80%;
    width: 600px;
    display: flex;
    flex-direction: column;
}

.aci-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e2e4e7;
}

.aci-modal-header h2 {
    margin: 0;
    font-size: 20px;
    color: #1d2327;
}

.aci-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #646970;
    padding: 4px;
}

.aci-modal-body {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
}

.aci-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 20px;
    border-top: 1px solid #e2e4e7;
}

.aci-loading {
    text-align: center;
    color: #646970;
    font-style: italic;
    padding: 40px 0;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .aci-settings-grid {
        grid-template-columns: 1fr;
    }
    
    .aci-sidebar {
        order: -1;
    }
    
    .aci-actions-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }
}

@media (max-width: 768px) {
    .aci-status-content {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .aci-status-actions {
        width: 100%;
        justify-content: flex-start;
    }
    
    .aci-actions-grid {
        grid-template-columns: 1fr;
    }
    
    .aci-modal-content {
        max-width: 95%;
        max-height: 95%;
    }
}
</style>