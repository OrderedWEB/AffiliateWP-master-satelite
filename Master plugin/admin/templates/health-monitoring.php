<?php
/**
 * Health Monitoring Template
 * File: admin/templates/health-monitoring.php
 * Plugin: AffiliateWP Cross Domain Plugin Suite Master
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = affcd();
$health_monitor = $plugin->health_monitor;

if (!$health_monitor) {
    wp_die(__('Health Monitor component not available.', 'affiliatewp-cross-domain-plugin-suite'));
}

$health_report = $health_report ?? $health_monitor->generate_health_report();
$uptime_data = $uptime_data ?? $health_monitor->calculate_monitoring_uptime();
$schedule_info = $health_monitor->get_health_check_schedule();
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Health Monitoring', 'affiliatewp-cross-domain-plugin-suite'); ?>
    </h1>
    
    <div class="page-title-action">
        <button id="manual-health-check" class="button button-primary">
            <?php _e('Run Health Check Now', 'affiliatewp-cross-domain-plugin-suite'); ?>
        </button>
    </div>

    <hr class="wp-header-end">

    <!-- Health Overview -->
    <div class="affcd-health-overview">
        <div class="affcd-health-cards">
            <div class="affcd-health-card affcd-uptime">
                <div class="affcd-card-header">
                    <h3><?php _e('System Uptime', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                    <span class="dashicons dashicons-arrow-up-alt"></span>
                </div>
                <div class="affcd-card-content">
                    <div class="affcd-big-number"><?php echo esc_html($health_report['system_uptime']['uptime_percentage']); ?>%</div>
                    <p class="affcd-card-detail">
                        <?php printf(__('%s successful checks out of %s total', 'affiliatewp-cross-domain-plugin-suite'), 
                            number_format($health_report['system_uptime']['successful_checks']),
                            number_format($health_report['system_uptime']['total_checks'])
                        ); ?>
                    </p>
                </div>
            </div>

            <div class="affcd-health-card affcd-performance">
                <div class="affcd-card-header">
                    <h3><?php _e('Performance', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                    <span class="dashicons dashicons-performance"></span>
                </div>
                <div class="affcd-card-content">
                    <div class="affcd-big-number"><?php echo esc_html($health_report['performance_metrics']['response_time']['average']); ?>ms</div>
                    <p class="affcd-card-detail">
                        <?php _e('Average response time', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        <span class="affcd-status-indicator affcd-<?php echo esc_attr($health_report['performance_metrics']['response_time']['status']); ?>"></span>
                    </p>
                </div>
            </div>

            <div class="affcd-health-card affcd-memory">
                <div class="affcd-card-header">
                    <h3><?php _e('Memory Usage', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                    <span class="dashicons dashicons-database"></span>
                </div>
                <div class="affcd-card-content">
                    <div class="affcd-big-number"><?php echo esc_html($health_report['performance_metrics']['memory_usage']['peak_mb']); ?>MB</div>
                    <p class="affcd-card-detail">
                        <?php printf(__('Peak usage: %sMB average', 'affiliatewp-cross-domain-plugin-suite'), 
                            esc_html($health_report['performance_metrics']['memory_usage']['average_mb'])
                        ); ?>
                    </p>
                </div>
            </div>

            <div class="affcd-health-card affcd-security">
                <div class="affcd-card-header">
                    <h3><?php _e('Security Incidents', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                    <span class="dashicons dashicons-shield-alt"></span>
                </div>
                <div class="affcd-card-content">
                    <div class="affcd-big-number"><?php echo esc_html($health_report['security_incidents']['total_count']); ?></div>
                    <p class="affcd-card-detail">
                        <?php _e('In last 7 days', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        <span class="affcd-severity affcd-<?php echo esc_attr($health_report['security_incidents']['severity_level']); ?>">
                            <?php echo esc_html(ucfirst($health_report['security_incidents']['severity_level'])); ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Health Details Tabs -->
    <div class="affcd-health-tabs">
        <nav class="nav-tab-wrapper">
            <a href="#api-endpoints" class="nav-tab nav-tab-active"><?php _e('API Endpoints', 'affiliatewp-cross-domain-plugin-suite'); ?></a>
            <a href="#database-health" class="nav-tab"><?php _e('Database Health', 'affiliatewp-cross-domain-plugin-suite'); ?></a>
            <a href="#system-resources" class="nav-tab"><?php _e('System Resources', 'affiliatewp-cross-domain-plugin-suite'); ?></a>
            <a href="#security-log" class="nav-tab"><?php _e('Security Log', 'affiliatewp-cross-domain-plugin-suite'); ?></a>
            <a href="#recommendations" class="nav-tab"><?php _e('Recommendations', 'affiliatewp-cross-domain-plugin-suite'); ?></a>
        </nav>

        <!-- API Endpoints Tab -->
        <div id="api-endpoints" class="tab-content active">
            <div class="affcd-section">
                <h3><?php _e('API Endpoint Health', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Endpoint', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Status', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Requests (24h)', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Success Rate', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Avg Response', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Last Request', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($health_report['api_endpoints'] as $endpoint => $data): ?>
                        <tr>
                            <td><code><?php echo esc_html($endpoint); ?></code></td>
                            <td>
                                <span class="affcd-status-badge affcd-<?php echo esc_attr($data['status']); ?>">
                                    <?php echo esc_html(ucfirst($data['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo number_format($data['total_requests']); ?></td>
                            <td><?php echo esc_html($data['success_rate']); ?>%</td>
                            <td><?php echo esc_html($data['avg_response_time']); ?>ms</td>
                            <td><?php echo $data['last_request'] ? human_time_diff(strtotime($data['last_request']), current_time('timestamp')) . ' ago' : __('Never', 'affiliatewp-cross-domain-plugin-suite'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Database Health Tab -->
        <div id="database-health" class="tab-content">
            <div class="affcd-section">
                <h3><?php _e('Database Performance', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                
                <div class="affcd-db-stats">
                    <div class="affcd-stat-card">
                        <h4><?php _e('Connection Status', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                        <p class="affcd-stat-value affcd-<?php echo esc_attr($health_report['database_health']['status']); ?>">
                            <?php echo esc_html(ucfirst($health_report['database_health']['status'])); ?>
                        </p>
                    </div>
                    <div class="affcd-stat-card">
                        <h4><?php _e('Slow Queries (1h)', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                        <p class="affcd-stat-value"><?php echo esc_html($health_report['database_health']['slow_queries_last_hour']); ?></p>
                    </div>
                    <div class="affcd-stat-card">
                        <h4><?php _e('Connection Usage', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                        <p class="affcd-stat-value"><?php echo esc_html($health_report['database_health']['connection_usage_percent']); ?>%</p>
                    </div>
                    <div class="affcd-stat-card">
                        <h4><?php _e('Total DB Size', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                        <p class="affcd-stat-value"><?php echo esc_html($health_report['database_health']['total_size_mb']); ?>MB</p>
                    </div>
                </div>

                <h4><?php _e('Table Information', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Table Name', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Rows', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Size (MB)', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($health_report['database_health']['tables'] as $table): ?>
                        <tr>
                            <td><code><?php echo esc_html($table->table_name); ?></code></td>
                            <td><?php echo number_format($table->table_rows); ?></td>
                            <td><?php echo esc_html($table->size_mb); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- System Resources Tab -->
        <div id="system-resources" class="tab-content">
            <div class="affcd-section">
                <h3><?php _e('System Resource Usage', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                
                <div class="affcd-resource-grid">
                    <div class="affcd-resource-item">
                        <h4><?php _e('Memory Usage', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                        <div class="affcd-progress-bar">
                            <div class="affcd-progress-fill" style="width: <?php echo esc_attr($health_report['resource_usage']['memory']['usage_percent']); ?>%"></div>
                        </div>
                        <p><?php printf(__('%s MB / %s MB (%s%%)', 'affiliatewp-cross-domain-plugin-suite'),
                            esc_html($health_report['resource_usage']['memory']['current_mb']),
                            esc_html($health_report['resource_usage']['memory']['limit_mb']),
                            esc_html($health_report['resource_usage']['memory']['usage_percent'])
                        ); ?></p>
                    </div>

                    <div class="affcd-resource-item">
                        <h4><?php _e('Disk Space', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                        <div class="affcd-progress-bar">
                            <div class="affcd-progress-fill" style="width: <?php echo esc_attr($health_report['resource_usage']['disk_space']['used_percent']); ?>%"></div>
                        </div>
                        <p><?php printf(__('%s GB / %s GB (%s%% used)', 'affiliatewp-cross-domain-plugin-suite'),
                            esc_html(number_format($health_report['resource_usage']['disk_space']['total_gb'] - $health_report['resource_usage']['disk_space']['free_gb'], 2)),
                            esc_html($health_report['resource_usage']['disk_space']['total_gb']),
                            esc_html($health_report['resource_usage']['disk_space']['used_percent'])
                        ); ?></p>
                    </div>

                    <div class="affcd-resource-item">
                        <h4><?php _e('Server Load', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                        <p class="affcd-server-load"><?php echo esc_html($health_report['resource_usage']['server_load']); ?></p>
                        <small><?php _e('1 min, 5 min, 15 min averages', 'affiliatewp-cross-domain-plugin-suite'); ?></small>
                    </div>

                    <div class="affcd-resource-item">
                        <h4><?php _e('System Info', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                        <p><?php printf(__('PHP %s', 'affiliatewp-cross-domain-plugin-suite'), esc_html($health_report['resource_usage']['php_version'])); ?></p>
                        <p><?php printf(__('WordPress %s', 'affiliatewp-cross-domain-plugin-suite'), esc_html($health_report['resource_usage']['wordpress_version'])); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Log Tab -->
        <div id="security-log" class="tab-content">
            <div class="affcd-section">
                <h3><?php _e('Recent Security Incidents', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                
                <?php if (!empty($health_report['security_incidents']['incidents'])): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Type', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Count', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Status', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Details', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Date', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($health_report['security_incidents']['incidents'] as $incident): ?>
                        <tr>
                            <td><?php echo esc_html(ucwords(str_replace('_', ' ', $incident->action))); ?></td>
                            <td><?php echo esc_html($incident->incident_count); ?></td>
                            <td>
                                <span class="affcd-status-badge affcd-<?php echo esc_attr($incident->status); ?>">
                                    <?php echo esc_html(ucfirst($incident->status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(wp_trim_words($incident->details, 10)); ?></td>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($incident->created_at))); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="affcd-no-incidents"><?php _e('No security incidents reported in the last 7 days.', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recommendations Tab -->
        <div id="recommendations" class="tab-content">
            <div class="affcd-section">
                <h3><?php _e('Health Recommendations', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                
                <?php if (!empty($health_report['recommendations'])): ?>
                <div class="affcd-recommendations">
                    <?php foreach ($health_report['recommendations'] as $recommendation): ?>
                    <div class="affcd-recommendation affcd-priority-<?php echo esc_attr($recommendation['priority']); ?>">
                        <div class="affcd-rec-header">
                            <span class="affcd-rec-priority"><?php echo esc_html(ucfirst($recommendation['priority'])); ?></span>
                            <span class="affcd-rec-category"><?php echo esc_html(ucfirst($recommendation['category'])); ?></span>
                        </div>
                        <p class="affcd-rec-message"><?php echo esc_html($recommendation['message']); ?></p>
                        <?php if (!empty($recommendation['action'])): ?>
                        <button class="button button-small affcd-rec-action" data-action="<?php echo esc_attr($recommendation['action']); ?>">
                            <?php _e('Take Action', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="affcd-no-recommendations">
                    <p><?php _e('No recommendations at this time. Your system is running optimally!', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Health Schedule Information -->
    <div class="affcd-health-schedule">
        <h3><?php _e('Monitoring Schedule', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
        <div class="affcd-schedule-info">
            <div class="affcd-schedule-item">
                <strong><?php _e('Next Check:', 'affiliatewp-cross-domain-plugin-suite'); ?></strong>
                <?php echo esc_html($schedule_info['next_run']); ?>
            </div>
            <div class="affcd-schedule-item">
                <strong><?php _e('Frequency:', 'affiliatewp-cross-domain-plugin-suite'); ?></strong>
                <?php echo esc_html($schedule_info['frequency']); ?>
            </div>
            <div class="affcd-schedule-item">
                <strong><?php _e('Last Check:', 'affiliatewp-cross-domain-plugin-suite'); ?></strong>
                <?php echo esc_html($schedule_info['last_run']); ?>
            </div>
        </div>
        
        <div class="affcd-schedule-actions">
            <button id="cleanup-logs" class="button">
                <?php _e('Clean Up Old Logs', 'affiliatewp-cross-domain-plugin-suite'); ?>
            </button>
            <label>
                <input type="number" id="cleanup-days" value="30" min="1" max="365" style="width: 60px;">
                <?php _e('days old', 'affiliatewp-cross-domain-plugin-suite'); ?>
            </label>
        </div>
    </div>
</div>

<style>
.affcd-health-overview {
    margin-bottom: 30px;
}

.affcd-health-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.affcd-health-card {
    background: white;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.affcd-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.affcd-card-header h3 {
    margin: 0;
    font-size: 16px;
    color: #333;
}

.affcd-card-header .dashicons {
    font-size: 24px;
    color: #666;
}

.affcd-big-number {
    font-size: 32px;
    font-weight: bold;
    color: #0073aa;
    margin-bottom: 10px;
}

.affcd-card-detail {
    color: #666;
    margin: 0;
    font-size: 14px;
}

.affcd-status-indicator {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-left: 5px;
}

.affcd-status-indicator.affcd-healthy { background: #28a745; }
.affcd-status-indicator.affcd-warning { background: #ffc107; }
.affcd-status-indicator.affcd-critical { background: #dc3545; }

.affcd-severity {
    font-weight: bold;
    margin-left: 5px;
}

.affcd-severity.affcd-low { color: #28a745; }
.affcd-severity.affcd-medium { color: #ffc107; }
.affcd-severity.affcd-high { color: #dc3545; }
.affcd-severity.affcd-critical { color: #dc3545; }

.affcd-health-tabs {
    background: white;
    border: 1px solid #e1e1e1;
    border-radius: 4px;
    margin-top: 20px;
}

.tab-content {
    display: none;
    padding: 20px;
}

.tab-content.active {
    display: block;
}

.affcd-section h3 {
    margin-top: 0;
    margin-bottom: 20px;
}

.affcd-status-badge {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}

.affcd-status-badge.affcd-healthy { background: #d4edda; color: #155724; }
.affcd-status-badge.affcd-warning { background: #fff3cd; color: #856404; }
.affcd-status-badge.affcd-critical { background: #f8d7da; color: #721c24; }

.affcd-db-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.affcd-stat-card {
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e1e1e1;
}

.affcd-stat-card h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
}

.affcd-stat-value {
    font-size: 24px;
    font-weight: bold;
    margin: 0;
}

.affcd-stat-value.affcd-healthy { color: #28a745; }
.affcd-stat-value.affcd-warning { color: #ffc107; }
.affcd-stat-value.affcd-critical { color: #dc3545; }

.affcd-resource-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
}

.affcd-resource-item {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #e1e1e1;
}

.affcd-resource-item h4 {
    margin: 0 0 15px 0;
    color: #333;
}

.affcd-progress-bar {
    width: 100%;
    height: 20px;
    background: #e1e1e1;
    border-radius: 10px;
    overflow: hidden;
    margin: 10px 0;
}

.affcd-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #20c997);
    transition: width 0.3s ease;
}

.affcd-server-load {
    font-family: monospace;
    font-size: 18px;
    font-weight: bold;
    color: #0073aa;
    margin: 10px 0;
}

.affcd-no-incidents {
    text-align: center;
    padding: 40px;
    color: #28a745;
    font-weight: bold;
}

.affcd-recommendations {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.affcd-recommendation {
    border-left: 4px solid;
    padding: 15px 20px;
    background: #f8f9fa;
    border-radius: 0 4px 4px 0;
}

.affcd-priority-high { border-left-color: #dc3545; }
.affcd-priority-medium { border-left-color: #ffc107; }
.affcd-priority-low { border-left-color: #28a745; }

.affcd-rec-header {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}

.affcd-rec-priority,
.affcd-rec-category {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.affcd-priority-high .affcd-rec-priority { background: #dc3545; color: white; }
.affcd-priority-medium .affcd-rec-priority { background: #ffc107; color: #333; }
.affcd-priority-low .affcd-rec-priority { background: #28a745; color: white; }

.affcd-rec-category {
    background: #6c757d;
    color: white;
}

.affcd-rec-message {
    margin: 10px 0;
    color: #333;
}

.affcd-no-recommendations {
    text-align: center;
    padding: 40px;
    color: #28a745;
}

.affcd-health-schedule {
    margin-top: 30px;
    padding: 20px;
    background: white;
    border: 1px solid #e1e1e1;
    border-radius: 4px;
}

.affcd-schedule-info {
    display: flex;
    gap: 30px;
    margin: 15px 0;
    flex-wrap: wrap;
}

.affcd-schedule-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.affcd-schedule-actions {
    margin-top: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.affcd-schedule-actions label {
    display: flex;
    align-items: center;
    gap: 5px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        const target = $(this).attr('href');
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.tab-content').removeClass('active');
        $(target).addClass('active');
    });
    
    // Manual health check
    $('#manual-health-check').on('click', function() {
        const $button = $(this);
        const originalText = $button.text();
        
        $button.prop('disabled', true).text('<?php _e("Running Health Check...", "affiliatewp-cross-domain-plugin-suite"); ?>');
        
        $.post(ajaxurl, {
            action: 'affiliate_run_health_check',
            nonce: '<?php echo wp_create_nonce("affiliate_admin_nonce"); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('<?php _e("Health check failed:", "affiliatewp-cross-domain-plugin-suite"); ?> ' + (response.data.message || '<?php _e("Unknown error", "affiliatewp-cross-domain-plugin-suite"); ?>'));
            }
        }).fail(function() {
            alert('<?php _e("Failed to run health check. Please try again.", "affiliatewp-cross-domain-plugin-suite"); ?>');
        }).always(function() {
            $button.prop('disabled', false).text(originalText);
        });
    });
    
    // Log cleanup
    $('#cleanup-logs').on('click', function() {
        const days = $('#cleanup-days').val();
        const $button = $(this);
        const originalText = $button.text();
        
        if (!confirm('<?php _e("Are you sure you want to clean up logs older than", "affiliatewp-cross-domain-plugin-suite"); ?> ' + days + ' <?php _e("days?", "affiliatewp-cross-domain-plugin-suite"); ?>')) {
            return;
        }
        
        $button.prop('disabled', true).text('<?php _e("Cleaning...", "affiliatewp-cross-domain-plugin-suite"); ?>');
        
        $.post(ajaxurl, {
            action: 'affiliate_cleanup_logs',
            days: days,
            nonce: '<?php echo wp_create_nonce("affiliate_admin_nonce"); ?>'
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert('<?php _e("Failed to clean up logs:", "affiliatewp-cross-domain-plugin-suite"); ?> ' + (response.data.message || '<?php _e("Unknown error", "affiliatewp-cross-domain-plugin-suite"); ?>'));
            }
        }).fail(function() {
            alert('<?php _e("Failed to clean up logs. Please try again.", "affiliatewp-cross-domain-plugin-suite"); ?>');
        }).always(function() {
            $button.prop('disabled', false).text(originalText);
        });
    });
    
    // Recommendation actions
    $('.affcd-rec-action').on('click', function() {
        const action = $(this).data('action');
        const $button = $(this);
        
        $button.prop('disabled', true).text('<?php _e("Processing...", "affiliatewp-cross-domain-plugin-suite"); ?>');
        
        // Handle different recommendation actions
        switch(action) {
            case 'optimise_response_time':
                // Implementation for response time optimization
                break;
            case 'increase_memory_limit':
                // Implementation for memory limit increase
                break;
            case 'optimise_database_queries':
                // Implementation for database optimization
                break;
            case 'free_disk_space':
                // Implementation for disk space cleanup
                break;
        }
        
        setTimeout(function() {
            $button.prop('disabled', false).text('<?php _e("Take Action", "affiliatewp-cross-domain-plugin-suite"); ?>');
        }, 2000);
    });
});
</script>