<?php
/**
 * Data Backflow Management Template
 * 
 * File: /wp-content/plugins/affiliatewp-cross-domain-plugin-suite/admin/templates/data-backflow.php
 * Plugin: AffiliateWP Cross-Domain Plugin Suite (Master)
 * Author: Richard King, starneconsulting.com
 * Email: r.king@starneconsulting.com
 * Version: 1.0.0
 * 
 * Displays and manages data synchronisation between master and satellite domains.
 * Handles analytics aggregation, performance monitoring, and data flow analysis.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied');
}

// Verify user permissions
if (!current_user_can('manage_affiliates') && !current_user_can('manage_options')) {
    wp_die(__('Access denied. Insufficient permissions.', 'affiliatewp-cross-domain-plugin-suite'));
}

// Get data backflow manager instance
$backflow_manager = AFFCD_Master_Plugin::get_instance()->get_backflow_manager();
$database_manager = AFFCD_Master_Plugin::get_instance()->get_database_manager();

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce($_POST['affcd_backflow_nonce'] ?? '', 'affcd_backflow_action')) {
    $action = sanitize_text_field($_POST['action'] ?? '');
    
    switch ($action) {
        case 'sync_domain_data':
            $domain_id = intval($_POST['domain_id'] ?? 0);
            if ($domain_id > 0) {
                $sync_result = $backflow_manager->sync_domain_data($domain_id);
                if ($sync_result) {
                    add_settings_error('affcd_messages', 'sync_success', 
                        __('Domain data synchronised successfully.', 'affiliatewp-cross-domain-plugin-suite'), 'success');
                } else {
                    add_settings_error('affcd_messages', 'sync_error', 
                        __('Domain data synchronisation failed.', 'affiliatewp-cross-domain-plugin-suite'), 'error');
                }
            }
            break;
            
        case 'purge_old_data':
            $days_to_keep = intval($_POST['days_to_keep'] ?? 90);
            $purge_result = $backflow_manager->purge_old_backflow_data($days_to_keep);
            if ($purge_result !== false) {
                add_settings_error('affcd_messages', 'purge_success', 
                    sprintf(__('Purged %d old records successfully.', 'affiliatewp-cross-domain-plugin-suite'), $purge_result), 'success');
            } else {
                add_settings_error('affcd_messages', 'purge_error', 
                    __('Data purge operation failed.', 'affiliatewp-cross-domain-plugin-suite'), 'error');
            }
            break;
            
        case 'rebuild_analytics':
            $rebuild_result = $backflow_manager->rebuild_analytics_cache();
            if ($rebuild_result) {
                add_settings_error('affcd_messages', 'rebuild_success', 
                    __('Analytics cache rebuilt successfully.', 'affiliatewp-cross-domain-plugin-suite'), 'success');
            } else {
                add_settings_error('affcd_messages', 'rebuild_error', 
                    __('Analytics cache rebuild failed.', 'affiliatewp-cross-domain-plugin-suite'), 'error');
            }
            break;
            
        case 'update_sync_settings':
            $sync_settings = [
                'auto_sync_enabled' => !empty($_POST['auto_sync_enabled']),
                'sync_interval' => intval($_POST['sync_interval'] ?? 15),
                'batch_size' => intval($_POST['batch_size'] ?? 100),
                'retry_attempts' => intval($_POST['retry_attempts'] ?? 3),
                'data_retention_days' => intval($_POST['data_retention_days'] ?? 365)
            ];
            
            update_option('affcd_backflow_settings', $sync_settings);
            add_settings_error('affcd_messages', 'settings_updated', 
                __('Synchronisation settings updated successfully.', 'affiliatewp-cross-domain-plugin-suite'), 'success');
            break;
    }
    
    // Redirect to prevent form resubmission
    wp_redirect(admin_url('admin.php?page=affcd-data-backflow'));
    exit;
}

// Get current backflow statistics
$backflow_stats = $backflow_manager->get_backflow_statistics();
$domain_performance = $backflow_manager->get_domain_performance_data();
$sync_health = $backflow_manager->get_synchronisation_health();
$recent_activities = $backflow_manager->get_recent_backflow_activities(50);

// Get sync settings
$sync_settings = get_option('affcd_backflow_settings', [
    'auto_sync_enabled' => true,
    'sync_interval' => 15,
    'batch_size' => 100,
    'retry_attempts' => 3,
    'data_retention_days' => 365
]);

// Get authorised domains for sync operations
$authorised_domains = $database_manager->get_authorised_domains();

?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Data Backflow Management', 'affiliatewp-cross-domain-plugin-suite'); ?>
    </h1>
    
    <p class="description">
        <?php _e('Monitor and manage data synchronisation between master and satellite domains. Track analytics flow, performance metrics, and synchronisation health.', 'affiliatewp-cross-domain-plugin-suite'); ?>
    </p>

    <?php settings_errors('affcd_messages'); ?>

    <div class="affcd-backflow-container">
        
        <!-- Summary Dashboard -->
        <div class="affcd-stats-grid">
            <div class="affcd-stat-card">
                <div class="affcd-stat-icon">
                    <span class="dashicons dashicons-update"></span>
                </div>
                <div class="affcd-stat-content">
                    <div class="affcd-stat-number"><?php echo number_format($backflow_stats['total_sync_operations'] ?? 0); ?></div>
                    <div class="affcd-stat-label"><?php _e('Total Sync Operations', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                </div>
            </div>
            
            <div class="affcd-stat-card">
                <div class="affcd-stat-icon">
                    <span class="dashicons dashicons-database-view"></span>
                </div>
                <div class="affcd-stat-content">
                    <div class="affcd-stat-number"><?php echo number_format($backflow_stats['records_processed_today'] ?? 0); ?></div>
                    <div class="affcd-stat-label"><?php _e('Records Today', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                </div>
            </div>
            
            <div class="affcd-stat-card">
                <div class="affcd-stat-icon">
                    <span class="dashicons dashicons-networking"></span>
                </div>
                <div class="affcd-stat-content">
                    <div class="affcd-stat-number"><?php echo count($authorised_domains); ?></div>
                    <div class="affcd-stat-label"><?php _e('Active Domains', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                </div>
            </div>
            
            <div class="affcd-stat-card">
                <div class="affcd-stat-icon">
                    <span class="dashicons dashicons-<?php echo ($sync_health['overall_health'] ?? 0) > 90 ? 'yes-alt' : (($sync_health['overall_health'] ?? 0) > 70 ? 'warning' : 'dismiss'); ?>"></span>
                </div>
                <div class="affcd-stat-content">
                    <div class="affcd-stat-number"><?php echo round($sync_health['overall_health'] ?? 0, 1); ?>%</div>
                    <div class="affcd-stat-label"><?php _e('Sync Health', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <nav class="nav-tab-wrapper wp-clearfix">
            <a href="#sync-overview" class="nav-tab nav-tab-active" data-tab="sync-overview">
                <?php _e('Sync Overview', 'affiliatewp-cross-domain-plugin-suite'); ?>
            </a>
            <a href="#domain-performance" class="nav-tab" data-tab="domain-performance">
                <?php _e('Domain Performance', 'affiliatewp-cross-domain-plugin-suite'); ?>
            </a>
            <a href="#data-analytics" class="nav-tab" data-tab="data-analytics">
                <?php _e('Data Analytics', 'affiliatewp-cross-domain-plugin-suite'); ?>
            </a>
            <a href="#sync-settings" class="nav-tab" data-tab="sync-settings">
                <?php _e('Sync Settings', 'affiliatewp-cross-domain-plugin-suite'); ?>
            </a>
        </nav>

        <!-- Sync Overview Tab -->
        <div id="sync-overview" class="tab-content active">
            <div class="affcd-two-column-layout">
                
                <!-- Recent Synchronisation Activities -->
                <div class="affcd-column">
                    <div class="affcd-panel">
                        <h3 class="affcd-panel-title">
                            <?php _e('Recent Synchronisation Activities', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </h3>
                        
                        <div class="affcd-activities-list">
                            <?php if (!empty($recent_activities)): ?>
                                <?php foreach (array_slice($recent_activities, 0, 10) as $activity): ?>
                                    <div class="affcd-activity-item">
                                        <div class="affcd-activity-icon">
                                            <span class="dashicons dashicons-<?php echo $activity['status'] === 'success' ? 'yes-alt' : ($activity['status'] === 'warning' ? 'warning' : 'dismiss'); ?>"></span>
                                        </div>
                                        <div class="affcd-activity-content">
                                            <div class="affcd-activity-title">
                                                <?php echo esc_html($activity['description'] ?? __('Sync Operation', 'affiliatewp-cross-domain-plugin-suite')); ?>
                                            </div>
                                            <div class="affcd-activity-meta">
                                                <span class="affcd-activity-domain"><?php echo esc_html($activity['domain'] ?? 'Unknown'); ?></span>
                                                <span class="affcd-activity-time"><?php echo esc_html(human_time_diff(strtotime($activity['created_at'] ?? ''), current_time('timestamp')) . ' ago'); ?></span>
                                            </div>
                                        </div>
                                        <div class="affcd-activity-records">
                                            <?php if (isset($activity['records_count'])): ?>
                                                <span class="affcd-records-badge"><?php echo number_format($activity['records_count']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="affcd-no-activities">
                                    <span class="dashicons dashicons-info"></span>
                                    <p><?php _e('No recent synchronisation activities found.', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Synchronisation Health Monitor -->
                <div class="affcd-column">
                    <div class="affcd-panel">
                        <h3 class="affcd-panel-title">
                            <?php _e('Synchronisation Health Monitor', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </h3>
                        
                        <div class="affcd-health-metrics">
                            <div class="affcd-health-item">
                                <div class="affcd-health-label"><?php _e('API Connectivity', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                                <div class="affcd-health-bar">
                                    <div class="affcd-health-progress" style="width: <?php echo ($sync_health['api_connectivity'] ?? 0); ?>%"></div>
                                </div>
                                <div class="affcd-health-value"><?php echo round($sync_health['api_connectivity'] ?? 0, 1); ?>%</div>
                            </div>
                            
                            <div class="affcd-health-item">
                                <div class="affcd-health-label"><?php _e('Data Accuracy', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                                <div class="affcd-health-bar">
                                    <div class="affcd-health-progress" style="width: <?php echo ($sync_health['data_accuracy'] ?? 0); ?>%"></div>
                                </div>
                                <div class="affcd-health-value"><?php echo round($sync_health['data_accuracy'] ?? 0, 1); ?>%</div>
                            </div>
                            
                            <div class="affcd-health-item">
                                <div class="affcd-health-label"><?php _e('Sync Frequency', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                                <div class="affcd-health-bar">
                                    <div class="affcd-health-progress" style="width: <?php echo ($sync_health['sync_frequency'] ?? 0); ?>%"></div>
                                </div>
                                <div class="affcd-health-value"><?php echo round($sync_health['sync_frequency'] ?? 0, 1); ?>%</div>
                            </div>
                            
                            <div class="affcd-health-item">
                                <div class="affcd-health-label"><?php _e('Error Rate', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                                <div class="affcd-health-bar">
                                    <div class="affcd-health-progress error-rate" style="width: <?php echo ($sync_health['error_rate'] ?? 0); ?>%"></div>
                                </div>
                                <div class="affcd-health-value"><?php echo round($sync_health['error_rate'] ?? 0, 1); ?>%</div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="affcd-quick-actions">
                            <form method="post" style="display: inline;">
                                <?php wp_nonce_field('affcd_backflow_action', 'affcd_backflow_nonce'); ?>
                                <input type="hidden" name="action" value="rebuild_analytics">
                                <button type="submit" class="button button-secondary">
                                    <span class="dashicons dashicons-update-alt"></span>
                                    <?php _e('Rebuild Analytics', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </button>
                            </form>
                            
                            <button type="button" class="button button-secondary" id="test-connectivity">
                                <span class="dashicons dashicons-networking"></span>
                                <?php _e('Test Connectivity', 'affiliatewp-cross-domain-plugin-suite'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Domain Performance Tab -->
        <div id="domain-performance" class="tab-content">
            <div class="affcd-panel">
                <h3 class="affcd-panel-title">
                    <?php _e('Domain Performance Analytics', 'affiliatewp-cross-domain-plugin-suite'); ?>
                </h3>
                
                <div class="affcd-performance-table-wrapper">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col" class="manage-column"><?php _e('Domain', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                <th scope="col" class="manage-column"><?php _e('Status', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                <th scope="col" class="manage-column"><?php _e('Last Sync', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                <th scope="col" class="manage-column"><?php _e('Records Today', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                <th scope="col" class="manage-column"><?php _e('Success Rate', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                <th scope="col" class="manage-column"><?php _e('Revenue Impact', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                <th scope="col" class="manage-column"><?php _e('Actions', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($domain_performance)): ?>
                                <?php foreach ($domain_performance as $domain_data): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($domain_data['domain_name'] ?? 'Unknown'); ?></strong>
                                            <div class="affcd-domain-meta">
                                                <?php echo esc_html($domain_data['domain_url'] ?? ''); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="affcd-status-badge affcd-status-<?php echo esc_attr($domain_data['connection_status'] ?? 'unknown'); ?>">
                                                <?php 
                                                $status_labels = [
                                                    'connected' => __('Connected', 'affiliatewp-cross-domain-plugin-suite'),
                                                    'disconnected' => __('Disconnected', 'affiliatewp-cross-domain-plugin-suite'),
                                                    'error' => __('Error', 'affiliatewp-cross-domain-plugin-suite'),
                                                    'syncing' => __('Syncing', 'affiliatewp-cross-domain-plugin-suite')
                                                ];
                                                echo esc_html($status_labels[$domain_data['connection_status'] ?? 'unknown'] ?? ucfirst($domain_data['connection_status'] ?? 'Unknown'));
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($domain_data['last_sync_at'] ?? null): ?>
                                                <span title="<?php echo esc_attr(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($domain_data['last_sync_at']))); ?>">
                                                    <?php echo esc_html(human_time_diff(strtotime($domain_data['last_sync_at']), current_time('timestamp')) . ' ago'); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="affcd-no-data"><?php _e('Never', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo number_format($domain_data['records_today'] ?? 0); ?></strong>
                                            <?php if (isset($domain_data['records_yesterday'])): ?>
                                                <div class="affcd-trend">
                                                    <?php 
                                                    $trend = $domain_data['records_today'] - $domain_data['records_yesterday'];
                                                    if ($trend > 0): ?>
                                                        <span class="affcd-trend-up">+<?php echo number_format($trend); ?></span>
                                                    <?php elseif ($trend < 0): ?>
                                                        <span class="affcd-trend-down"><?php echo number_format($trend); ?></span>
                                                    <?php else: ?>
                                                        <span class="affcd-trend-neutral">±0</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="affcd-success-rate">
                                                <div class="affcd-rate-bar">
                                                    <div class="affcd-rate-progress" style="width: <?php echo ($domain_data['success_rate'] ?? 0); ?>%"></div>
                                                </div>
                                                <span class="affcd-rate-text"><?php echo round($domain_data['success_rate'] ?? 0, 1); ?>%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <strong>$<?php echo number_format($domain_data['revenue_today'] ?? 0, 2); ?></strong>
                                            <div class="affcd-commission-meta">
                                                <?php _e('Commission:', 'affiliatewp-cross-domain-plugin-suite'); ?> 
                                                $<?php echo number_format($domain_data['commission_today'] ?? 0, 2); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="affcd-domain-actions">
                                                <form method="post" style="display: inline;">
                                                    <?php wp_nonce_field('affcd_backflow_action', 'affcd_backflow_nonce'); ?>
                                                    <input type="hidden" name="action" value="sync_domain_data">
                                                    <input type="hidden" name="domain_id" value="<?php echo intval($domain_data['domain_id'] ?? 0); ?>">
                                                    <button type="submit" class="button button-small" title="<?php _e('Sync Now', 'affiliatewp-cross-domain-plugin-suite'); ?>">
                                                        <span class="dashicons dashicons-update"></span>
                                                    </button>
                                                </form>
                                                
                                                <button type="button" class="button button-small" 
                                                        data-domain-id="<?php echo intval($domain_data['domain_id'] ?? 0); ?>"
                                                        onclick="viewDomainDetails(this)" 
                                                        title="<?php _e('View Details', 'affiliatewp-cross-domain-plugin-suite'); ?>">
                                                    <span class="dashicons dashicons-visibility"></span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="affcd-no-data-row">
                                        <span class="dashicons dashicons-info"></span>
                                        <?php _e('No domain performance data available.', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Data Analytics Tab -->
        <div id="data-analytics" class="tab-content">
            <div class="affcd-analytics-grid">
                
                <!-- Data Flow Chart -->
                <div class="affcd-chart-container">
                    <h3 class="affcd-chart-title"><?php _e('Data Flow Trends', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                    <canvas id="data-flow-chart" width="400" height="200"></canvas>
                </div>
                
                <!-- Revenue Analytics -->
                <div class="affcd-chart-container">
                    <h3 class="affcd-chart-title"><?php _e('Revenue Attribution', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                    <canvas id="revenue-attribution-chart" width="400" height="200"></canvas>
                </div>
                
                <!-- Sync Performance -->
                <div class="affcd-chart-container">
                    <h3 class="affcd-chart-title"><?php _e('Synchronisation Performance', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                    <canvas id="sync-performance-chart" width="400" height="200"></canvas>
                </div>
                
                <!-- Error Analysis -->
                <div class="affcd-chart-container">
                    <h3 class="affcd-chart-title"><?php _e('Error Analysis', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                    <div class="affcd-error-summary">
                        <?php 
                        $error_stats = $backflow_manager->get_error_statistics();
                        if (!empty($error_stats)): ?>
                            <div class="affcd-error-types">
                                <?php foreach ($error_stats as $error_type => $count): ?>
                                    <div class="affcd-error-item">
                                        <span class="affcd-error-type"><?php echo esc_html(ucfirst(str_replace('_', ' ', $error_type))); ?></span>
                                        <span class="affcd-error-count"><?php echo number_format($count); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="affcd-no-errors">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <p><?php _e('No errors detected in the last 24 hours.', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Data Management Actions -->
            <div class="affcd-panel">
                <h3 class="affcd-panel-title"><?php _e('Data Management', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                
                <div class="affcd-data-actions">
                    <div class="affcd-action-group">
                        <h4><?php _e('Data Cleanup', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                        <form method="post" onsubmit="return confirm('<?php _e('Are you sure you want to purge old data? This action cannot be undone.', 'affiliatewp-cross-domain-plugin-suite'); ?>');">
                            <?php wp_nonce_field('affcd_backflow_action', 'affcd_backflow_nonce'); ?>
                            <input type="hidden" name="action" value="purge_old_data">
                            <p>
                                <label for="days_to_keep"><?php _e('Keep data from the last:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                <select name="days_to_keep" id="days_to_keep">
                                    <option value="30"><?php _e('30 days', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                    <option value="90" selected><?php _e('90 days', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                    <option value="180"><?php _e('180 days', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                    <option value="365"><?php _e('1 year', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                </select>
                                <button type="submit" class="button button-secondary">
                                    <span class="dashicons dashicons-trash"></span>
                                    <?php _e('Purge Old Data', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </button>
                            </p>
                        </form>
                    </div>
                    
                    <div class="affcd-action-group">
                        <h4><?php _e('Export Data', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                        <p><?php _e('Export backflow data for external analysis or backup purposes.', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                        <button type="button" class="button button-secondary" id="export-backflow-data">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('Export Data', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sync Settings Tab -->
        <div id="sync-settings" class="tab-content">
            <form method="post" id="sync-settings-form">
                <?php wp_nonce_field('affcd_backflow_action', 'affcd_backflow_nonce'); ?>
                <input type="hidden" name="action" value="update_sync_settings">
                
                <div class="affcd-settings-section">
                    <h3 class="affcd-section-title"><?php _e('Automatic Synchronisation', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="auto_sync_enabled"><?php _e('Enable Auto Sync', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" name="auto_sync_enabled" id="auto_sync_enabled" value="1" 
                                       <?php checked($sync_settings['auto_sync_enabled']); ?>>
                                <p class="description"><?php _e('Automatically synchronise data between domains at regular intervals.', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="sync_interval"><?php _e('Sync Interval (minutes)', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="sync_interval" id="sync_interval" 
                                       value="<?php echo intval($sync_settings['sync_interval']); ?>" min="5" max="1440" class="small-text">
                                <p class="description"><?php _e('How often to synchronise data (5-1440 minutes).', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="affcd-settings-section">
                    <h3 class="affcd-section-title"><?php _e('Performance Settings', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="batch_size"><?php _e('Batch Size', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="batch_size" id="batch_size" 
                                       value="<?php echo intval($sync_settings['batch_size']); ?>" min="10" max="1000" class="small-text">
                                <p class="description"><?php _e('Number of records to process per batch (10-1000).', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="retry_attempts"><?php _e('Retry Attempts', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="retry_attempts" id="retry_attempts" 
                                       value="<?php echo intval($sync_settings['retry_attempts']); ?>" min="1" max="10" class="small-text">
                                <p class="description"><?php _e('Number of retry attempts for failed synchronisation operations.', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="affcd-settings-section">
                    <h3 class="affcd-section-title"><?php _e('Data Retention', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="data_retention_days"><?php _e('Retention Period (days)', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="data_retention_days" id="data_retention_days" 
                                       value="<?php echo intval($sync_settings['data_retention_days']); ?>" min="30" max="3650" class="regular-text">
                                <p class="description"><?php _e('How long to keep synchronised data before automatic cleanup (30-3650 days).', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(__('Save Sync Settings', 'affiliatewp-cross-domain-plugin-suite'), 'primary'); ?>
            </form>
        </div>
    </div>

    <!-- Domain Details Modal -->
    <div id="domain-details-modal" class="affcd-modal" style="display: none;">
        <div class="affcd-modal-content">
            <div class="affcd-modal-header">
                <h2><?php _e('Domain Synchronisation Details', 'affiliatewp-cross-domain-plugin-suite'); ?></h2>
                <button type="button" class="affcd-modal-close">&times;</button>
            </div>
            <div class="affcd-modal-body">
                <div id="domain-details-content">
                    <div class="affcd-loading">
                        <span class="spinner is-active"></span>
                        <?php _e('Loading domain details...', 'affiliatewp-cross-domain-plugin-suite'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Data Backflow Styles */
.affcd-backflow-container {
    max-width: 1200px;
}

.affcd-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0 30px 0;
}

.affcd-stat-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
}

.affcd-stat-icon {
    font-size: 32px;
    color: #0073aa;
    opacity: 0.8;
}

.affcd-stat-content {
    flex: 1;
}

.affcd-stat-number {
    font-size: 24px;
    font-weight: 600;
    color: #1d2327;
    line-height: 1.2;
}

.affcd-stat-label {
    font-size: 13px;
    color: #646970;
    margin-top: 4px;
}

.tab-content {
    display: none;
    margin-top: 20px;
}

.tab-content.active {
    display: block;
}

.affcd-two-column-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin: 20px 0;
}

.affcd-column {
    min-height: 400px;
}

.affcd-panel {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
}

.affcd-panel-title {
    margin: 0;
    padding: 15px 20px;
    background: #f6f7f7;
    border-bottom: 1px solid #c3c4c7;
    font-size: 16px;
    font-weight: 600;
}

.affcd-activities-list {
    padding: 20px;
    max-height: 400px;
    overflow-y: auto;
}

.affcd-activity-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
}

.affcd-activity-item:last-child {
    border-bottom: none;
}

.affcd-activity-icon {
    font-size: 16px;
    margin-top: 2px;
}

.affcd-activity-icon .dashicons-yes-alt {
    color: #00a32a;
}

.affcd-activity-icon .dashicons-warning {
    color: #dba617;
}

.affcd-activity-icon .dashicons-dismiss {
    color: #d63638;
}

.affcd-activity-content {
    flex: 1;
}

.affcd-activity-title {
    font-weight: 500;
    color: #1d2327;
    margin-bottom: 4px;
}

.affcd-activity-meta {
    font-size: 12px;
    color: #646970;
}

.affcd-activity-domain {
    font-weight: 500;
}

.affcd-activity-time::before {
    content: " • ";
}

.affcd-activity-records {
    align-self: center;
}

.affcd-records-badge {
    background: #f0f0f0;
    color: #646970;
    padding: 4px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 500;
}

.affcd-no-activities {
    text-align: center;
    padding: 40px 20px;
    color: #646970;
}

.affcd-health-metrics {
    padding: 20px;
}

.affcd-health-item {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.affcd-health-label {
    width: 120px;
    font-size: 13px;
    font-weight: 500;
    color: #1d2327;
}

.affcd-health-bar {
    flex: 1;
    height: 8px;
    background: #f0f0f0;
    border-radius: 4px;
    overflow: hidden;
}

.affcd-health-progress {
    height: 100%;
    background: linear-gradient(90deg, #00a32a 0%, #4caf50 50%, #8bc34a 100%);
    transition: width 0.3s ease;
}

.affcd-health-progress.error-rate {
    background: linear-gradient(90deg, #d63638 0%, #f44336 50%, #ff5722 100%);
}

.affcd-health-value {
    width: 45px;
    text-align: right;
    font-size: 12px;
    font-weight: 600;
    color: #1d2327;
}

.affcd-quick-actions {
    padding: 15px 20px;
    background: #f9f9f9;
    border-top: 1px solid #c3c4c7;
    display: flex;
    gap: 10px;
}

.affcd-performance-table-wrapper {
    margin: 20px 0;
    overflow-x: auto;
}

.affcd-status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
}

.affcd-status-connected {
    background: #d7eddb;
    color: #00a32a;
}

.affcd-status-disconnected {
    background: #f0f0f0;
    color: #646970;
}

.affcd-status-error {
    background: #f8d7da;
    color: #d63638;
}

.affcd-status-syncing {
    background: #e7f3ff;
    color: #0073aa;
}

.affcd-domain-meta {
    font-size: 12px;
    color: #646970;
    margin-top: 2px;
}

.affcd-trend {
    font-size: 11px;
    margin-top: 2px;
}

.affcd-trend-up {
    color: #00a32a;
}

.affcd-trend-down {
    color: #d63638;
}

.affcd-trend-neutral {
    color: #646970;
}

.affcd-success-rate {
    display: flex;
    align-items: center;
    gap: 8px;
}

.affcd-rate-bar {
    width: 60px;
    height: 6px;
    background: #f0f0f0;
    border-radius: 3px;
    overflow: hidden;
}

.affcd-rate-progress {
    height: 100%;
    background: linear-gradient(90deg, #00a32a 0%, #4caf50 100%);
}

.affcd-rate-text {
    font-size: 11px;
    font-weight: 500;
    color: #1d2327;
}

.affcd-commission-meta {
    font-size: 12px;
    color: #646970;
    margin-top: 2px;
}

.affcd-domain-actions {
    display: flex;
    gap: 5px;
}

.affcd-no-data-row {
    text-align: center;
    padding: 40px 20px;
    color: #646970;
}

.affcd-analytics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.affcd-chart-container {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
}

.affcd-chart-title {
    margin: 0;
    padding: 15px 20px;
    background: #f6f7f7;
    border-bottom: 1px solid #c3c4c7;
    font-size: 14px;
    font-weight: 600;
}

.affcd-error-summary {
    padding: 20px;
}

.affcd-error-types {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.affcd-error-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: #f8d7da;
    border-radius: 4px;
    border-left: 3px solid #d63638;
}

.affcd-error-type {
    font-weight: 500;
    color: #721c24;
}

.affcd-error-count {
    font-weight: 600;
    color: #d63638;
}

.affcd-no-errors {
    text-align: center;
    padding: 40px 20px;
    color: #00a32a;
}

.affcd-data-actions {
    padding: 20px;
    display: flex;
    gap: 40px;
}

.affcd-action-group h4 {
    margin: 0 0 10px 0;
    color: #1d2327;
}

.affcd-settings-section {
    margin-bottom: 30px;
}

.affcd-section-title {
    margin: 0 0 15px 0;
    padding: 10px 0;
    border-bottom: 1px solid #c3c4c7;
    font-size: 16px;
    font-weight: 600;
}

.affcd-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.affcd-modal-content {
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    overflow: hidden;
}

.affcd-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: #f6f7f7;
    border-bottom: 1px solid #c3c4c7;
}

.affcd-modal-header h2 {
    margin: 0;
    font-size: 18px;
}

.affcd-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #646970;
}

.affcd-modal-body {
    padding: 20px;
    max-height: calc(90vh - 80px);
    overflow-y: auto;
}

.affcd-loading {
    text-align: center;
    padding: 40px;
    color: #646970;
}

@media (max-width: 768px) {
    .affcd-two-column-layout {
        grid-template-columns: 1fr;
    }
    
    .affcd-data-actions {
        flex-direction: column;
        gap: 20px;
    }
    
    .affcd-analytics-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    'use strict';
    
    // Tab switching functionality
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var targetTab = $(this).data('tab');
        
        // Update nav tabs
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show target tab content
        $('.tab-content').removeClass('active');
        $('#' + targetTab).addClass('active');
        
        // Initialize charts if switching to analytics tab
        if (targetTab === 'data-analytics') {
            initializeAnalyticsCharts();
        }
    });
    
    // Test connectivity functionality
    $('#test-connectivity').on('click', function() {
        var button = $(this);
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="spinner is-active" style="float:left;margin:0 5px 0 0;"></span>Testing...');
        
        $.post(ajaxurl, {
            action: 'affcd_test_connectivity',
            nonce: affcd_admin.nonce
        })
        .done(function(response) {
            if (response.success) {
                button.html('<span class="dashicons dashicons-yes-alt"></span>Connected');
                setTimeout(function() {
                    button.prop('disabled', false).html(originalText);
                }, 2000);
            } else {
                button.html('<span class="dashicons dashicons-dismiss"></span>Failed');
                setTimeout(function() {
                    button.prop('disabled', false).html(originalText);
                }, 2000);
            }
        })
        .fail(function() {
            button.html('<span class="dashicons dashicons-dismiss"></span>Error');
            setTimeout(function() {
                button.prop('disabled', false).html(originalText);
            }, 2000);
        });
    });
    
    // Export data functionality
    $('#export-backflow-data').on('click', function() {
        var button = $(this);
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="spinner is-active" style="float:left;margin:0 5px 0 0;"></span>Exporting...');
        
        window.location.href = ajaxurl + '?action=affcd_export_backflow_data&nonce=' + encodeURIComponent(affcd_admin.nonce);
        
        setTimeout(function() {
            button.prop('disabled', false).html(originalText);
        }, 3000);
    });
    
    // Modal functionality
    $('.affcd-modal-close').on('click', function() {
        $('.affcd-modal').hide();
    });
    
    $(document).on('click', function(e) {
        if ($(e.target).hasClass('affcd-modal')) {
            $('.affcd-modal').hide();
        }
    });
    
    // Initialize analytics charts
    function initializeAnalyticsCharts() {
        if (typeof Chart === 'undefined') {
            console.warn('Chart.js not loaded');
            return;
        }
        
        // Data flow trends chart
        var dataFlowCtx = document.getElementById('data-flow-chart');
        if (dataFlowCtx && !dataFlowCtx.chartInstance) {
            dataFlowCtx.chartInstance = new Chart(dataFlowCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($backflow_manager->get_chart_labels('data_flow', 7)); ?>,
                    datasets: [{
                        label: 'Records Processed',
                        data: <?php echo json_encode($backflow_manager->get_chart_data('data_flow', 7)); ?>,
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        // Revenue attribution chart
        var revenueCtx = document.getElementById('revenue-attribution-chart');
        if (revenueCtx && !revenueCtx.chartInstance) {
            revenueCtx.chartInstance = new Chart(revenueCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($backflow_manager->get_chart_labels('revenue_attribution')); ?>,
                    datasets: [{
                        data: <?php echo json_encode($backflow_manager->get_chart_data('revenue_attribution')); ?>,
                        backgroundColor: [
                            '#0073aa', '#00a32a', '#dba617', '#d63638', '#6c757d'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        // Sync performance chart
        var syncCtx = document.getElementById('sync-performance-chart');
        if (syncCtx && !syncCtx.chartInstance) {
            syncCtx.chartInstance = new Chart(syncCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($backflow_manager->get_chart_labels('sync_performance', 7)); ?>,
                    datasets: [{
                        label: 'Success Rate (%)',
                        data: <?php echo json_encode($backflow_manager->get_chart_data('sync_performance', 7)); ?>,
                        backgroundColor: '#00a32a'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }
    }
});

// Global functions for domain details modal
function viewDomainDetails(button) {
    var domainId = button.getAttribute('data-domain-id');
    var modal = document.getElementById('domain-details-modal');
    var contentDiv = document.getElementById('domain-details-content');
    
    // Show modal with loading state
    modal.style.display = 'flex';
    contentDiv.innerHTML = '<div class="affcd-loading"><span class="spinner is-active"></span>Loading domain details...</div>';
    
    // Load domain details via AJAX
    jQuery.post(ajaxurl, {
        action: 'affcd_get_domain_details',
        domain_id: domainId,
        nonce: affcd_admin.nonce
    })
    .done(function(response) {
        if (response.success) {
            contentDiv.innerHTML = response.data.html;
        } else {
            contentDiv.innerHTML = '<div class="notice notice-error"><p>Failed to load domain details.</p></div>';
        }
    })
    .fail(function() {
        contentDiv.innerHTML = '<div class="notice notice-error"><p>Network error occurred while loading domain details.</p></div>';
    });
}
</script>