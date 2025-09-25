<?php
/**
 * Admin Dashboard Template for Affiliate Cross Domain System
 * 
 * Path: /wp-content/plugins/affiliate-cross-domain-system/admin/templates/dashboard.php
 * Plugin: Affiliate Cross Domain System (Master)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get dashboard data
$stats = AFFCD_Analytics::get_dashboard_stats();
$recent_activity = AFFCD_Security_Logs::get_logs(10);
$domain_health = AFFCD_Domain_Health::check_all_domains();
$system_status = AFFCD_System_Health::get_status();
?>

<div class="wrap affcd-dashboard">
    <h1><?php _e('Affiliate Cross-Domain Dashboard', 'affiliatewp-cross-domain-plugin-suite'); ?></h1>

    <!-- System Status Bar -->
    <div class="affcd-status-bar">
        <div class="status-item <?php echo $system_status['overall'] === 'healthy' ? 'status-healthy' : 'status-warning'; ?>">
            <span class="status-icon"></span>
            <span class="status-text">
                <?php printf(__('System Status: %s', 'affiliatewp-cross-domain-plugin-suite'), 
                           ucfirst($system_status['overall'])); ?>
            </span>
        </div>
        
        <div class="status-item">
            <span class="dashicons dashicons-networking"></span>
            <span class="status-text">
                <?php printf(__('%d Active Domains', 'affiliatewp-cross-domain-plugin-suite'), 
                           count($domain_health['active'])); ?>
            </span>
        </div>
        
        <div class="status-item">
            <span class="dashicons dashicons-chart-line"></span>
            <span class="status-text">
                <?php printf(__('%d API Calls Today', 'affiliatewp-cross-domain-plugin-suite'), 
                           $stats['api_calls_today']); ?>
            </span>
        </div>
        
        <div class="status-item">
            <span class="dashicons dashicons-shield"></span>
            <span class="status-text">
                <?php printf(__('%d Security Events', 'affiliatewp-cross-domain-plugin-suite'), 
                           $stats['security_events_today']); ?>
            </span>
        </div>
    </div>

    <!-- Main Dashboard Grid -->
    <div class="affcd-dashboard-grid">
        
        <!-- Quick Stats -->
        <div class="dashboard-widget quick-stats">
            <h3><?php _e('Quick Statistics', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($stats['total_validations']); ?></div>
                    <div class="stat-label"><?php _e('Total Validations', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                    <div class="stat-change <?php echo $stats['validations_change'] >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo $stats['validations_change'] >= 0 ? '+' : ''; ?><?php echo $stats['validations_change']; ?>%
                    </div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($stats['successful_validations']); ?></div>
                    <div class="stat-label"><?php _e('Successful Validations', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                    <div class="stat-rate">
                        <?php echo round(($stats['successful_validations'] / max($stats['total_validations'], 1)) * 100, 1); ?>% success rate
                    </div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($stats['unique_domains']); ?></div>
                    <div class="stat-label"><?php _e('Active Domains', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                    <div class="stat-change">
                        <?php echo count($domain_health['healthy']); ?> healthy
                    </div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['avg_response_time']; ?>ms</div>
                    <div class="stat-label"><?php _e('Avg Response Time', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                    <div class="stat-change <?php echo $stats['response_time_change'] <= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo $stats['response_time_change'] <= 0 ? '' : '+'; ?><?php echo $stats['response_time_change']; ?>%
                    </div>
                </div>
            </div>
        </div>

        <!-- Domain Health Status -->
        <div class="dashboard-widget domain-health">
            <h3><?php _e('Domain Health Status', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
            <div class="domain-health-list">
                <?php if (empty($domain_health['all'])): ?>
                    <p><?php _e('No domains configured yet.', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=affcd-domain-management'); ?>" class="button button-primary">
                        <?php _e('Add Your First Domain', 'affiliatewp-cross-domain-plugin-suite'); ?>
                    </a>
                <?php else: ?>
                    <?php foreach ($domain_health['all'] as $domain): ?>
                        <div class="domain-health-item status-<?php echo $domain['status']; ?>">
                            <div class="domain-info">
                                <div class="domain-name"><?php echo esc_html($domain['name']); ?></div>
                                <div class="domain-url"><?php echo esc_html($domain['url']); ?></div>
                            </div>
                            <div class="domain-status">
                                <span class="status-indicator status-<?php echo $domain['status']; ?>">
                                    <?php echo ucfirst($domain['status']); ?>
                                </span>
                                <?php if ($domain['last_check']): ?>
                                    <div class="last-check">
                                        <?php printf(__('Checked %s ago', 'affiliatewp-cross-domain-plugin-suite'), 
                                                   human_time_diff(strtotime($domain['last_check']))); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="domain-actions">
                                <button type="button" class="button button-small test-domain" 
                                        data-domain="<?php echo esc_attr($domain['url']); ?>">
                                    <?php _e('Test', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="domain-actions-bar">
                        <a href="<?php echo admin_url('admin.php?page=affcd-domain-management'); ?>" class="button">
                            <?php _e('Manage Domains', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </a>
                        <button type="button" class="button test-all-domains">
                            <?php _e('Test All Domains', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="dashboard-widget recent-activity">
            <h3><?php _e('Recent Security Activity', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
            <div class="activity-list">
                <?php if (empty($recent_activity)): ?>
                    <p><?php _e('No recent security activity.', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                <?php else: ?>
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="activity-item severity-<?php echo $activity->severity; ?>">
                            <div class="activity-icon">
                                <?php
                                switch ($activity->event_type) {
                                    case 'login_failed':
                                        echo '<span class="dashicons dashicons-warning"></span>';
                                        break;
                                    case 'api_access':
                                        echo '<span class="dashicons dashicons-admin-network"></span>';
                                        break;
                                    case 'suspicious_activity':
                                        echo '<span class="dashicons dashicons-shield-alt"></span>';
                                        break;
                                    case 'rate_limit_violation':
                                        echo '<span class="dashicons dashicons-clock"></span>';
                                        break;
                                    default:
                                        echo '<span class="dashicons dashicons-info"></span>';
                                        break;
                                }
                                ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    <?php echo esc_html(ucwords(str_replace('_', ' ', $activity->event_type))); ?>
                                </div>
                                <div class="activity-details">
                                    IP: <?php echo esc_html($activity->ip_address); ?>
                                    <?php if ($activity->domain): ?>
                                        | Domain: <?php echo esc_html($activity->domain); ?>
                                    <?php endif; ?>
                                    <?php if ($activity->endpoint): ?>
                                        | Endpoint: <?php echo esc_html($activity->endpoint); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-time">
                                    <?php echo human_time_diff(strtotime($activity->created_at)) . ' ago'; ?>
                                </div>
                            </div>
                            <div class="activity-severity">
                                <span class="severity-badge severity-<?php echo $activity->severity; ?>">
                                    <?php echo ucfirst($activity->severity); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="activity-actions">
                        <a href="<?php echo admin_url('admin.php?page=affcd-security-dashboard'); ?>" class="button">
                            <?php _e('View All Security Logs', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Performance Metrics -->
        <div class="dashboard-widget performance-metrics">
            <h3><?php _e('Performance Metrics (Last 24 Hours)', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
            <div class="metrics-chart-container">
                <canvas id="performance-chart" width="400" height="200"></canvas>
            </div>
            <div class="metrics-summary">
                <div class="metric-item">
                    <span class="metric-label"><?php _e('Peak Response Time', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                    <span class="metric-value"><?php echo $stats['peak_response_time']; ?>ms</span>
                </div>
                <div class="metric-item">
                    <span class="metric-label"><?php _e('Total Requests', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                    <span class="metric-value"><?php echo number_format($stats['total_requests_24h']); ?></span>
                </div>
                <div class="metric-item">
                    <span class="metric-label"><?php _e('Error Rate', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                    <span class="metric-value"><?php echo $stats['error_rate_24h']; ?>%</span>
                </div>
            </div>
        </div>

        <!-- API Usage -->
        <div class="dashboard-widget api-usage">
            <h3><?php _e('API Usage Overview', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
            <div class="api-stats">
                <div class="api-stat-item">
                    <div class="api-stat-number"><?php echo number_format($stats['api_calls_today']); ?></div>
                    <div class="api-stat-label"><?php _e('Calls Today', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                </div>
                <div class="api-stat-item">
                    <div class="api-stat-number"><?php echo number_format($stats['api_calls_week']); ?></div>
                    <div class="api-stat-label"><?php _e('Calls This Week', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                </div>
                <div class="api-stat-item">
                    <div class="api-stat-number"><?php echo $stats['api_success_rate']; ?>%</div>
                    <div class="api-stat-label"><?php _e('Success Rate', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                </div>
            </div>
            
            <div class="api-endpoints">
                <h4><?php _e('Most Used Endpoints', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                <?php if (!empty($stats['top_endpoints'])): ?>
                    <ul class="endpoint-list">
                        <?php foreach ($stats['top_endpoints'] as $endpoint): ?>
                            <li class="endpoint-item">
                                <span class="endpoint-path"><?php echo esc_html($endpoint['path']); ?></span>
                                <span class="endpoint-count"><?php echo number_format($endpoint['count']); ?> calls</span>
                                <span class="endpoint-avg-time"><?php echo $endpoint['avg_time']; ?>ms avg</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p><?php _e('No API usage data available.', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- System Alerts -->
        <div class="dashboard-widget system-alerts">
            <h3><?php _e('System Alerts & Notifications', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
            <div class="alerts-container">
                <?php
                $alerts = AFFCD_System_Health::get_active_alerts();
                if (empty($alerts)): ?>
                    <div class="no-alerts">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <p><?php _e('All systems operational', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($alerts as $alert): ?>
                        <div class="alert-item alert-<?php echo $alert['severity']; ?>">
                            <div class="alert-icon">
                                <?php if ($alert['severity'] === 'critical'): ?>
                                    <span class="dashicons dashicons-warning"></span>
                                <?php elseif ($alert['severity'] === 'warning'): ?>
                                    <span class="dashicons dashicons-info"></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-flag"></span>
                                <?php endif; ?>
                            </div>
                            <div class="alert-content">
                                <div class="alert-title"><?php echo esc_html($alert['title']); ?></div>
                                <div class="alert-message"><?php echo esc_html($alert['message']); ?></div>
                                <div class="alert-time"><?php echo human_time_diff(strtotime($alert['created_at'])) . ' ago'; ?></div>
                            </div>
                            <div class="alert-actions">
                                <?php if ($alert['action_url']): ?>
                                    <a href="<?php echo esc_url($alert['action_url']); ?>" class="button button-small">
                                        <?php echo esc_html($alert['action_text'] ?: __('Resolve', 'affiliatewp-cross-domain-plugin-suite')); ?>
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="button button-small dismiss-alert" 
                                        data-alert-id="<?php echo $alert['id']; ?>">
                                    <?php _e('Dismiss', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="dashboard-widget quick-actions">
            <h3><?php _e('Quick Actions', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
            <div class="actions-grid">
                <a href="<?php echo admin_url('admin.php?page=affcd-domain-management&tab=domains'); ?>" 
                   class="action-button">
                    <span class="dashicons dashicons-networking"></span>
                    <span class="action-label"><?php _e('Add New Domain', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=affcd-domain-management&tab=api'); ?>" 
                   class="action-button">
                    <span class="dashicons dashicons-admin-network"></span>
                    <span class="action-label"><?php _e('Generate API Key', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=affcd-analytics-reports'); ?>" 
                   class="action-button">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <span class="action-label"><?php _e('View Analytics', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=affcd-security-dashboard'); ?>" 
                   class="action-button">
                    <span class="dashicons dashicons-shield"></span>
                    <span class="action-label"><?php _e('Security Logs', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                </a>
                
                <button type="button" class="action-button test-all-connections">
                    <span class="dashicons dashicons-update"></span>
                    <span class="action-label"><?php _e('Test All Connections', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                </button>
                
                <a href="<?php echo admin_url('admin.php?page=affcd-bulk-operations'); ?>" 
                   class="action-button">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <span class="action-label"><?php _e('Bulk Operations', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                </a>
            </div>
        </div>
    </div>

    <!-- Dashboard Footer -->
    <div class="affcd-dashboard-footer">
        <div class="footer-stats">
            <span><?php printf(__('Last updated: %s', 'affiliatewp-cross-domain-plugin-suite'), 
                             current_time('M j, Y g:i A')); ?></span>
            <span><?php printf(__('System uptime: %s', 'affiliatewp-cross-domain-plugin-suite'), 
                             $system_status['uptime']); ?></span>
            <span><?php printf(__('Plugin version: %s', 'affiliatewp-cross-domain-plugin-suite'), 
                             AFFCD_VERSION); ?></span>
        </div>
        <div class="footer-actions">
            <button type="button" class="button refresh-dashboard">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Refresh Dashboard', 'affiliatewp-cross-domain-plugin-suite'); ?>
            </button>
            <a href="<?php echo admin_url('admin.php?page=affcd-settings'); ?>" class="button">
                <?php _e('Settings', 'affiliatewp-cross-domain-plugin-suite'); ?>
            </a>
        </div>
    </div>
</div>

<style>
.affcd-dashboard {
    background: #f1f1f1;
    margin: -20px -20px 0 -10px;
    padding: 20px;
}

.affcd-status-bar {
    display: flex;
    gap: 20px;
    background: white;
    padding: 15px 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.status-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
}

.status-healthy .status-icon {
    width: 8px;
    height: 8px;
    background: #46b450;
    border-radius: 50%;
}

.status-warning .status-icon {
    width: 8px;
    height: 8px;
    background: #ffb900;
    border-radius: 50%;
}

.affcd-dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.dashboard-widget {
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

.dashboard-widget h3 {
    margin: 0;
    padding: 20px;
    border-bottom: 1px solid #e5e5e5;
    font-size: 16px;
    font-weight: 600;
}

.dashboard-widget .inside {
    padding: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    padding: 20px;
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #1e40af;
    line-height: 1;
}

.stat-label {
    font-size: 14px;
    color: #666;
    margin-top: 5px;
}

.stat-change {
    font-size: 12px;
    margin-top: 5px;
}

.stat-change.positive {
    color: #46b450;
}

.stat-change.negative {
    color: #dc3232;
}

.domain-health-list {
    padding: 20px;
}

.domain-health-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px;
    border: 1px solid #e5e5e5;
    border-radius: 6px;
    margin-bottom: 10px;
}

.domain-health-item.status-healthy {
    border-color: #46b450;
    background: rgba(70, 180, 80, 0.05);
}

.domain-health-item.status-warning {
    border-color: #ffb900;
    background: rgba(255, 185, 0, 0.05);
}

.domain-health-item.status-error {
    border-color: #dc3232;
    background: rgba(220, 50, 50, 0.05);
}

.domain-name {
    font-weight: 600;
    font-size: 14px;
}

.domain-url {
    font-size: 12px;
    color: #666;
    margin-top: 2px;
}

.status-indicator {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-indicator.status-healthy {
    background: #46b450;
    color: white;
}

.status-indicator.status-warning {
    background: #ffb900;
    color: black;
}

.status-indicator.status-error {
    background: #dc3232;
    color: white;
}

.activity-list {
    padding: 20px;
    max-height: 400px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #f0f0f0;
}

.severity-critical .activity-icon {
    background: #dc3232;
    color: white;
}

.severity-high .activity-icon {
    background: #ff6900;
    color: white;
}

.severity-medium .activity-icon {
    background: #ffb900;
    color: black;
}

.activity-content {
    flex: 1;
}

.activity-title {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 4px;
}

.activity-details {
    font-size: 12px;
    color: #666;
    margin-bottom: 4px;
}

.activity-time {
    font-size: 11px;
    color: #999;
}

.severity-badge {
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
}

.severity-badge.severity-critical {
    background: #dc3232;
    color: white;
}

.severity-badge.severity-high {
    background: #ff6900;
    color: white;
}

.severity-badge.severity-medium {
    background: #ffb900;
    color: black;
}

.severity-badge.severity-low {
    background: #46b450;
    color: white;
}

.metrics-chart-container {
    padding: 20px;
    height: 200px;
}

.metrics-summary {
    display: flex;
    justify-content: space-around;
    padding: 0 20px 20px;
    border-top: 1px solid #e5e5e5;
}

.metric-item {
    text-align: center;
}

.metric-label {
    display: block;
    font-size: 12px;
    color: #666;
}

.metric-value {
    display: block;
    font-size: 18px;
    font-weight: 600;
    color: #1e40af;
    margin-top: 4px;
}

.api-stats {
    display: flex;
    justify-content: space-around;
    padding: 20px;
    border-bottom: 1px solid #e5e5e5;
}

.api-stat-item {
    text-align: center;
}

.api-stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #1e40af;
}

.api-stat-label {
    font-size: 12px;
    color: #666;
    margin-top: 4px;
}

.api-endpoints {
    padding: 20px;
}

.endpoint-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.endpoint-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.endpoint-path {
    font-family: monospace;
    font-weight: 600;
}

.endpoint-count {
    font-size: 12px;
    color: #666;
}

.endpoint-avg-time {
    font-size: 12px;
    color: #999;
}

.alerts-container {
    padding: 20px;
}

.no-alerts {
    text-align: center;
    padding: 40px 20px;
    color: #46b450;
}

.no-alerts .dashicons {
    font-size: 48px;
    margin-bottom: 10px;
}

.alert-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 10px;
}

.alert-critical {
    background: rgba(220, 50, 50, 0.1);
    border: 1px solid #dc3232;
}

.alert-warning {
    background: rgba(255, 185, 0, 0.1);
    border: 1px solid #ffb900;
}

.alert-info {
    background: rgba(0, 123, 255, 0.1);
    border: 1px solid #007cba;
}

.alert-content {
    flex: 1;
}

.alert-title {
    font-weight: 600;
    margin-bottom: 4px;
}

.alert-message {
    font-size: 14px;
    margin-bottom: 4px;
}

.alert-time {
    font-size: 12px;
    color: #666;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    padding: 20px;
}

.action-button {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 20px;
    text-decoration: none;
    background: #f8f9fa;
    border: 1px solid #e5e5e5;
    border-radius: 6px;
    transition: all 0.2s;
    cursor: pointer;
}

.action-button:hover {
    background: #e9ecef;
    border-color: #007cba;
    text-decoration: none;
}

.action-button .dashicons {
    font-size: 24px;
    color: #007cba;
}

.action-label {
    font-size: 12px;
    font-weight: 600;
    text-align: center;
    color: #333;
}

.affcd-dashboard-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: white;
    padding: 15px 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-top: 20px;
}

.footer-stats {
    display: flex;
    gap: 20px;
    font-size: 12px;
    color: #666;
}

.footer-actions {
    display: flex;
    gap: 10px;
}

@media (max-width: 768px) {
    .affcd-dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .affcd-status-bar {
        flex-direction: column;
        gap: 10px;
    }
    
    .actions-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .affcd-dashboard-footer {
        flex-direction: column;
        gap: 15px;
    }
    
    .footer-stats {
        flex-direction: column;
        gap: 5px;
        text-align: center;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Refresh dashboard
    $('.refresh-dashboard').on('click', function() {
        location.reload();
    });
    
    // Test individual domain
    $('.test-domain').on('click', function() {
        const $button = $(this);
        const domain = $button.data('domain');
        const $item = $button.closest('.domain-health-item');
        
        $button.prop('disabled', true).text('Testing...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'affcd_test_domain_connection',
                domain: domain,
                nonce: affcd_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $item.removeClass('status-error status-warning')
                         .addClass('status-healthy');
                    $item.find('.status-indicator')
                         .removeClass('status-error status-warning')
                         .addClass('status-healthy')
                         .text('Healthy');
                } else {
                    $item.removeClass('status-healthy status-warning')
                         .addClass('status-error');
                    $item.find('.status-indicator')
                         .removeClass('status-healthy status-warning')
                         .addClass('status-error')
                         .text('Error');
                }
            },
            complete: function() {
                $button.prop('disabled', false).text('Test');
            }
        });
    });
    
    // Test all domains
    $('.test-all-domains').on('click', function() {
        const $button = $(this);
        $button.prop('disabled', true).text('Testing All...');
        
        $('.test-domain').each(function() {
            $(this).trigger('click');
        });
        
        setTimeout(function() {
            $button.prop('disabled', false).text('Test All Domains');
        }, 3000);
    });
    
    // Dismiss alerts
    $('.dismiss-alert').on('click', function() {
        const $button = $(this);
        const alertId = $button.data('alert-id');
        const $alert = $button.closest('.alert-item');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'affcd_dismiss_alert',
                alert_id: alertId,
                nonce: affcd_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $alert.fadeOut();
                }
            }
        });
    });
    
    // Initialize performance chart if Chart.js is available
    if (typeof Chart !== 'undefined') {
        const ctx = document.getElementById('performance-chart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo wp_json_encode($stats['chart_labels'] ?? []); ?>,
                    datasets: [{
                        label: 'Response Time (ms)',
                        data: <?php echo wp_json_encode($stats['chart_data'] ?? []); ?>,
                        borderColor: '#007cba',
                        backgroundColor: 'rgba(0, 124, 186, 0.1)',
                        tension: 0.1
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
    }
});
</script>