<?php
/**
 * Health Monitor Dashboard Template
 * 
 * File: admin/templates/health-monitor-dashboard.php
 */

if (!defined('ABSPATH')) {
    exit;
}

$health_data = $args['health_data'] ?? [];
$overall_status = $health_data['overall_status'] ?? 'unknown';
?>

<div class="wrap">
    <h1><?php _e('Affiliate Link Health Monitor', 'affiliatewp-cross-domain-plugin-suite'); ?></h1>
    
    <div class="health-status-card status-<?php echo esc_attr($overall_status); ?>">
        <h2><?php _e('System Status:', 'affiliatewp-cross-domain-plugin-suite'); ?> 
            <span class="status-badge"><?php echo esc_html(ucfirst($overall_status)); ?></span>
        </h2>
        
        <div class="health-stats">
            <div class="stat-box">
                <span class="stat-number"><?php echo intval($health_data['total_domains'] ?? 0); ?></span>
                <span class="stat-label"><?php _e('Total Domains', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
            </div>
            
            <div class="stat-box healthy">
                <span class="stat-number"><?php echo intval($health_data['healthy_domains'] ?? 0); ?></span>
                <span class="stat-label"><?php _e('Healthy', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
            </div>
            
            <div class="stat-box warning">
                <span class="stat-number"><?php echo intval($health_data['warning_domains'] ?? 0); ?></span>
                <span class="stat-label"><?php _e('Warnings', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
            </div>
            
            <div class="stat-box critical">
                <span class="stat-number"><?php echo intval($health_data['critical_domains'] ?? 0); ?></span>
                <span class="stat-label"><?php _e('Critical', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
            </div>
        </div>
    </div>
    
    <?php if (empty($health_data['domains'])): ?>
        <div class="notice notice-info">
            <p><?php _e('No domains configured yet. Add domains in Domain Management to start monitoring.', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
            <p><a href="<?php echo admin_url('admin.php?page=affcd-domain-management'); ?>" class="button button-primary">
                <?php _e('Add Domains', 'affiliatewp-cross-domain-plugin-suite'); ?>
            </a></p>
        </div>
    <?php else: ?>
        <h2><?php _e('Domain Health Details', 'affiliatewp-cross-domain-plugin-suite'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Domain', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                    <th><?php _e('Status', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                    <th><?php _e('Links Checked', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                    <th><?php _e('Issues', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                    <th><?php _e('Last Check', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($health_data['domains'] as $domain): ?>
                    <tr>
                        <td><strong><?php echo esc_html($domain['name']); ?></strong></td>
                        <td><span class="status-badge status-<?php echo esc_attr($domain['status']); ?>">
                            <?php echo esc_html(ucfirst($domain['status'])); ?>
                        </span></td>
                        <td><?php echo intval($domain['links_checked'] ?? 0); ?></td>
                        <td><?php echo intval($domain['issues'] ?? 0); ?></td>
                        <td><?php echo esc_html($domain['last_check'] ?? 'Never'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <p class="description">
        <?php _e('Last updated:', 'affiliatewp-cross-domain-plugin-suite'); ?> 
        <?php echo esc_html($health_data['last_check'] ?? 'Never'); ?>
    </p>
</div>

<style>
.health-status-card { background: #fff; padding: 20px; margin: 20px 0; border-left: 4px solid #46b450; }
.health-status-card.status-warning { border-color: #ffb900; }
.health-status-card.status-critical { border-color: #dc3232; }
.health-stats { display: flex; gap: 20px; margin-top: 15px; }
.stat-box { flex: 1; text-align: center; padding: 15px; background: #f0f0f1; border-radius: 4px; }
.stat-number { display: block; font-size: 32px; font-weight: bold; color: #2271b1; }
.stat-label { display: block; font-size: 13px; color: #646970; margin-top: 5px; }
.status-badge { padding: 4px 12px; border-radius: 3px; font-size: 13px; font-weight: 600; }
.status-badge.status-healthy { background: #d7f0db; color: #00a32a; }
.status-badge.status-warning { background: #fff6d5; color: #996800; }
.status-badge.status-critical { background: #f8d7da; color: #d63638; }
</style>