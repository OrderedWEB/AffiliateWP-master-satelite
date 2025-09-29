<?php
/**
 * Marketing Portal Template
 * File: admin/templates/marketing-portal.php
 * Plugin: AffiliateWP Cross Domain Plugin Suite Master
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = affcd();
$portal = $plugin->portal_enhancement;

if (!$portal) {
    wp_die(__('Portal Enhancement component not available.', 'affiliatewp-cross-domain-plugin-suite'));
}

$user_id = get_current_user_id();
$portal_data = $portal_data ?? $portal->get_portal_dashboard_data($user_id);
$custom_templates = $portal->get_custom_templates($user_id);
$performance_data = $portal->get_marketing_performance($user_id, '30_days');
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Marketing Portal', 'affiliatewp-cross-domain-plugin-suite'); ?>
    </h1>
    
    <div class="page-title-action">
        <button id="generate-materials" class="button button-primary">
            <?php _e('Generate New Materials', 'affiliatewp-cross-domain-plugin-suite'); ?>
        </button>
        <button id="export-package" class="button">
            <?php _e('Export Package', 'affiliatewp-cross-domain-plugin-suite'); ?>
        </button>
    </div>

    <hr class="wp-header-end">

    <!-- Portal Overview -->
    <div class="affcd-portal-overview">
        <div class="affcd-overview-cards">
            <div class="affcd-overview-card">
                <div class="affcd-card-icon">
                    <span class="dashicons dashicons-art"></span>
                </div>
                <div class="affcd-card-content">
                    <div class="affcd-card-number"><?php echo esc_html($portal_data['overview']['total_materials']); ?></div>
                    <div class="affcd-card-label"><?php _e('Available Materials', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                </div>
            </div>

            <div class="affcd-overview-card">
                <div class="affcd-card-icon">
                    <span class="dashicons dashicons-admin-customizer"></span>
                </div>
                <div class="affcd-card-content">
                    <div class="affcd-card-number"><?php echo esc_html($portal_data['overview']['custom_templates']); ?></div>
                    <div class="affcd-card-label"><?php _e('Custom Templates', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                </div>
            </div>

            <div class="affcd-overview-card">
                <div class="affcd-card-icon">
                    <span class="dashicons dashicons-chart-line"></span>
                </div>
                <div class="affcd-card-content">
                    <div class="affcd-card-number"><?php echo esc_html($portal_data['overview']['materials_used_this_month']); ?></div>
                    <div class="affcd-card-label"><?php _e('Used This Month', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                </div>
            </div>

            <div class="affcd-overview-card">
                <div class="affcd-card-icon">
                    <span class="dashicons dashicons-star-filled"></span>
                </div>
                <div class="affcd-card-content">
                    <div class="affcd-card-number">
                        <?php echo $portal_data['overview']['top_performing_material'] 
                            ? esc_html(ucwords(str_replace('_', ' ', $portal_data['overview']['top_performing_material']['material_type']))) 
                            : __('N/A', 'affiliatewp-cross-domain-plugin-suite'); ?>
                    </div>
                    <div class="affcd-card-label"><?php _e('Top Performer', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Marketing Tabs -->
    <div class="affcd-marketing-tabs">
        <nav class="nav-tab-wrapper">
            <a href="#material-generator" class="nav-tab nav-tab-active"><?php _e('Material Generator', 'affiliatewp-cross-domain-plugin-suite'); ?></a>
            <a href="#template-manager" class="nav-tab"><?php _e('Template Manager', 'affiliatewp-cross-domain-plugin-suite'); ?></a>
            <a href="#performance-analytics" class="nav-tab"><?php _e('Performance Analytics', 'affiliatewp-cross-domain-plugin-suite'); ?></a>
            <a href="#ab-testing" class="nav-tab"><?php _e('A/B Testing', 'affiliatewp-cross-domain-plugin-suite'); ?></a>
            <a href="#scheduling" class="nav-tab"><?php _e('Scheduling', 'affiliatewp-cross-domain-plugin-suite'); ?></a>
        </nav>

        <!-- Material Generator Tab -->
        <div id="material-generator" class="tab-content active">
            <div class="affcd-section">
                <h3><?php _e('Generate Marketing Materials', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                
                <div class="affcd-material-types">
                    <div class="affcd-material-type" data-type="banner">
                        <div class="affcd-type-header">
                            <span class="dashicons dashicons-format-image"></span>
                            <h4><?php _e('Banners', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                        </div>
                        <p><?php _e('Generate various sized banner advertisements for your affiliate links.', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                        <button class="button button-primary generate-type" data-type="banner">
                            <?php _e('Generate Banners', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </button>
                    </div>

                    <div class="affcd-material-type" data-type="email">
                        <div class="affcd-type-header">
                            <span class="dashicons dashicons-email-alt"></span>
                            <h4><?php _e('Email Templates', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                        </div>
                        <p><?php _e('Create professional email templates for your marketing campaigns.', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                        <button class="button button-primary generate-type" data-type="email">
                            <?php _e('Generate Emails', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </button>
                    </div>

                    <div class="affcd-material-type" data-type="social">
                        <div class="affcd-type-header">
                            <span class="dashicons dashicons-share"></span>
                            <h4><?php _e('Social Media', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                        </div>
                        <p><?php _e('Generate social media posts for Facebook, Instagram, Twitter, and LinkedIn.', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                        <button class="button button-primary generate-type" data-type="social">
                            <?php _e('Generate Social', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </button>
                    </div>

                    <div class="affcd-material-type" data-type="landing">
                        <div class="affcd-type-header">
                            <span class="dashicons dashicons-admin-page"></span>
                            <h4><?php _e('Landing Pages', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                        </div>
                        <p><?php _e('Create landing page components and snippets for high conversion.', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                        <button class="button button-primary generate-type" data-type="landing">
                            <?php _e('Generate Landing', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </button>
                    </div>
                </div>
                
                <div id="generated-materials" class="affcd-generated-materials" style="display: none;">
                    <h4><?php _e('Generated Materials', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                    <div id="materials-container"></div>
                </div>
            </div>
        </div>

        <!-- Template Manager Tab -->
        <div id="template-manager" class="tab-content">
            <div class="affcd-section">
                <div class="affcd-section-header">
                    <h3><?php _e('Custom Templates', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                    <button id="create-template" class="button button-primary">
                        <?php _e('Create New Template', 'affiliatewp-cross-domain-plugin-suite'); ?>
                    </button>
                </div>
                
                <?php if (!empty($custom_templates)): ?>
                <div class="affcd-templates-grid">
                    <?php foreach ($custom_templates as $template_id => $template): ?>
                    <div class="affcd-template-card" data-template-id="<?php echo esc_attr($template_id); ?>">
                        <div class="affcd-template-header">
                            <h4><?php echo esc_html($template['data']['name'] ?? __('Unnamed Template', 'affiliatewp-cross-domain-plugin-suite')); ?></h4>
                            <div class="affcd-template-actions">
                                <button class="button button-small edit-template" data-template-id="<?php echo esc_attr($template_id); ?>">
                                    <?php _e('Edit', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </button>
                                <button class="button button-small clone-template" data-template-id="<?php echo esc_attr($template_id); ?>">
                                    <?php _e('Clone', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </button>
                                <button class="button button-small button-link-delete delete-template" data-template-id="<?php echo esc_attr($template_id); ?>">
                                    <?php _e('Delete', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </button>
                            </div>
                        </div>
                        <div class="affcd-template-meta">
                            <span class="affcd-template-type"><?php echo esc_html(ucfirst($template['type'])); ?></span>
                            <span class="affcd-template-date"><?php echo esc_html(date('M j, Y', strtotime($template['created_at']))); ?></span>
                        </div>
                        <div class="affcd-template-preview">
                            <p><?php echo esc_html(wp_trim_words($template['data']['description'] ?? '', 20)); ?></p>
                        </div>
                        <div class="affcd-template-stats">
                            <?php 
                            $stats = $portal->get_template_usage_stats($user_id, $template_id);
                            ?>
                            <div class="affcd-stat">
                                <span class="affcd-stat-label"><?php _e('Uses:', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                                <span class="affcd-stat-value"><?php echo esc_html($stats['total_uses']); ?></span>
                            </div>
                            <div class="affcd-stat">
                                <span class="affcd-stat-label"><?php _e('Performance:', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                                <span class="affcd-stat-value"><?php echo esc_html($stats['performance_score']); ?>/100</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="affcd-no-templates">
                    <p><?php _e('No custom templates found. Create your first template to get started!', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Performance Analytics Tab -->
        <div id="performance-analytics" class="tab-content">
            <div class="affcd-section">
                <h3><?php _e('Marketing Performance Analytics', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                
                <div class="affcd-analytics-filters">
                    <label>
                        <?php _e('Time Period:', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        <select id="analytics-period">
                            <option value="7_days"><?php _e('Last 7 Days', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                            <option value="30_days" selected><?php _e('Last 30 Days', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                            <option value="90_days"><?php _e('Last 90 Days', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                        </select>
                    </label>
                </div>

                <div class="affcd-analytics-grid">
                    <!-- Usage Statistics -->
                    <div class="affcd-analytics-card">
                        <h4><?php _e('Material Usage', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                        <?php if (!empty($performance_data['usage_statistics'])): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Type', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Platform', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Uses', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Avg. Clicks', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Revenue', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($performance_data['usage_statistics'] as $stat): ?>
                                <tr>
                                    <td><?php echo esc_html(ucwords(str_replace('_', ' ', $stat->material_type))); ?></td>
                                    <td><?php echo esc_html(ucfirst($stat->platform)); ?></td>
                                    <td><?php echo esc_html($stat->usage_count); ?></td>
                                    <td><?php echo esc_html(round($stat->avg_clicks ?? 0)); ?></td>
                                    <td>$<?php echo esc_html(number_format($stat->total_revenue ?? 0, 2)); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p><?php _e('No usage statistics available yet.', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Platform Performance -->
                    <div class="affcd-analytics-card">
                        <h4><?php _e('Platform Performance', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                        <?php if (!empty($performance_data['platform_performance'])): ?>
                        <div class="affcd-platform-stats">
                            <?php foreach ($performance_data['platform_performance'] as $platform): ?>
                            <div class="affcd-platform-item">
                                <div class="affcd-platform-name"><?php echo esc_html(ucfirst($platform->platform)); ?></div>
                                <div class="affcd-platform-metrics">
                                    <div class="affcd-metric">
                                        <span class="affcd-metric-value"><?php echo esc_html($platform->campaigns); ?></span>
                                        <span class="affcd-metric-label"><?php _e('Campaigns', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                                    </div>
                                    <div class="affcd-metric">
                                        <span class="affcd-metric-value"><?php echo esc_html(number_format($platform->total_clicks)); ?></span>
                                        <span class="affcd-metric-label"><?php _e('Clicks', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                                    </div>
                                    <div class="affcd-metric">
                                        <span class="affcd-metric-value"><?php echo esc_html(round($platform->avg_conversion_rate, 2)); ?>%</span>
                                        <span class="affcd-metric-label"><?php _e('Conv. Rate', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p><?php _e('No platform performance data available yet.', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Conversion Funnel -->
                    <div class="affcd-analytics-card affcd-full-width">
                        <h4><?php _e('Conversion Funnel', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                        <?php if (!empty($performance_data['conversion_funnel'])): ?>
                        <div class="affcd-funnel-chart">
                            <?php 
                            $funnel = $performance_data['conversion_funnel'];
                            $max_value = max($funnel['impressions'], 1);
                            ?>
                            <div class="affcd-funnel-step">
                                <div class="affcd-funnel-bar" style="width: 100%;">
                                    <span class="affcd-funnel-label"><?php _e('Impressions', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                                    <span class="affcd-funnel-value"><?php echo number_format($funnel['impressions']); ?></span>
                                </div>
                            </div>
                            <div class="affcd-funnel-step">
                                <div class="affcd-funnel-bar" style="width: <?php echo ($funnel['clicks'] / $max_value) * 100; ?>%;">
                                    <span class="affcd-funnel-label"><?php _e('Clicks', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                                    <span class="affcd-funnel-value"><?php echo number_format($funnel['clicks']); ?> (<?php echo esc_html($funnel['click_through_rate']); ?>%)</span>
                                </div>
                            </div>
                            <div class="affcd-funnel-step">
                                <div class="affcd-funnel-bar" style="width: <?php echo ($funnel['leads'] / $max_value) * 100; ?>%;">
                                    <span class="affcd-funnel-label"><?php _e('Leads', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                                    <span class="affcd-funnel-value"><?php echo number_format($funnel['leads']); ?> (<?php echo esc_html($funnel['lead_conversion_rate']); ?>%)</span>
                                </div>
                            </div>
                            <div class="affcd-funnel-step">
                                <div class="affcd-funnel-bar" style="width: <?php echo ($funnel['conversions'] / $max_value) * 100; ?>%;">
                                    <span class="affcd-funnel-label"><?php _e('Conversions', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                                    <span class="affcd-funnel-value"><?php echo number_format($funnel['conversions']); ?> (<?php echo esc_html($funnel['purchase_conversion_rate']); ?>%)</span>
                                </div>
                            </div>
                        </div>
                        <div class="affcd-funnel-summary">
                            <div class="affcd-funnel-metric">
                                <span class="affcd-metric-label"><?php _e('Overall Conversion Rate:', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                                <span class="affcd-metric-value"><?php echo esc_html($funnel['overall_conversion_rate']); ?>%</span>
                            </div>
                            <div class="affcd-funnel-metric">
                                <span class="affcd-metric-label"><?php _e('Total Revenue:', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                                <span class="affcd-metric-value">$<?php echo number_format($funnel['revenue'], 2); ?></span>
                            </div>
                        </div>
                        <?php else: ?>
                        <p><?php _e('No conversion funnel data available yet.', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- A/B Testing Tab -->
        <div id="ab-testing" class="tab-content">
            <div class="affcd-section">
                <div class="affcd-section-header">
                    <h3><?php _e('A/B Testing', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                    <button id="create-ab-test" class="button button-primary">
                        <?php _e('Create A/B Test', 'affiliatewp-cross-domain-plugin-suite'); ?>
                    </button>
                </div>
                
                <div class="affcd-ab-testing-form" style="display: none;">
                    <h4><?php _e('Create New A/B Test', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                    <form id="ab-test-form">
                        <div class="affcd-form-row">
                            <label for="test-name"><?php _e('Test Name:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                            <input type="text" id="test-name" name="test_name" required>
                        </div>
                        
                        <div class="affcd-form-row">
                            <label for="base-template"><?php _e('Base Template:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                            <select id="base-template" name="base_template" required>
                                <option value=""><?php _e('Select template...', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                <option value="banner"><?php _e('Banner Template', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                <option value="email"><?php _e('Email Template', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                <option value="social"><?php _e('Social Template', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                <option value="landing"><?php _e('Landing Template', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                            </select>
                        </div>
                        
                        <div class="affcd-form-row">
                            <label><?php _e('Variations to Test:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                            <div class="affcd-variation-options">
                                <label><input type="checkbox" name="variations[]" value="headlines"> <?php _e('Headlines', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                <label><input type="checkbox" name="variations[]" value="colors"> <?php _e('Colors', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                <label><input type="checkbox" name="variations[]" value="call_to_actions"> <?php _e('Call-to-Actions', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                            </div>
                        </div>
                        
                        <div class="affcd-form-actions">
                            <button type="submit" class="button button-primary"><?php _e('Create Test', 'affiliatewp-cross-domain-plugin-suite'); ?></button>
                            <button type="button" id="cancel-ab-test" class="button"><?php _e('Cancel', 'affiliatewp-cross-domain-plugin-suite'); ?></button>
                        </div>
                    </form>
                </div>
                
                <div class="affcd-ab-tests-list">
                    <p><?php _e('No A/B tests created yet. Create your first test to compare material performance!', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                </div>
            </div>
        </div>

        <!-- Scheduling Tab -->
        <div id="scheduling" class="tab-content">
            <div class="affcd-section">
                <h3><?php _e('Automatic Material Generation', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                
                <div class="affcd-scheduling-form">
                    <h4><?php _e('Schedule Material Generation', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                    <form id="scheduling-form">
                        <div class="affcd-form-row">
                            <label for="schedule-frequency"><?php _e('Frequency:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                            <select id="schedule-frequency" name="frequency">
                                <option value=""><?php _e('Select frequency...', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                <option value="weekly"><?php _e('Weekly', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                <option value="monthly"><?php _e('Monthly', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                <option value="quarterly"><?php _e('Quarterly', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                            </select>
                        </div>
                        
                        <div class="affcd-form-row">
                            <label><?php _e('Material Types:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                            <div class="affcd-material-checkboxes">
                                <label><input type="checkbox" name="material_types[]" value="banner"> <?php _e('Banners', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                <label><input type="checkbox" name="material_types[]" value="email"> <?php _e('Email Templates', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                <label><input type="checkbox" name="material_types[]" value="social"> <?php _e('Social Media', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                <label><input type="checkbox" name="material_types[]" value="landing"> <?php _e('Landing Pages', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                            </div>
                        </div>
                        
                        <div class="affcd-form-actions">
                            <button type="submit" class="button button-primary"><?php _e('Schedule Generation', 'affiliatewp-cross-domain-plugin-suite'); ?></button>
                        </div>
                    </form>
                </div>
                
                <?php if (!empty($portal_data['upcoming_scheduled'])): ?>
                <div class="affcd-scheduled-info">
                    <h4><?php _e('Current Schedule', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                    <div class="affcd-schedule-details">
                        <p><strong><?php _e('Next Generation:', 'affiliatewp-cross-domain-plugin-suite'); ?></strong> <?php echo esc_html($portal_data['upcoming_scheduled']['next_generation']); ?></p>
                        <p><strong><?php _e('Frequency:', 'affiliatewp-cross-domain-plugin-suite'); ?></strong> <?php echo esc_html(ucfirst($portal_data['upcoming_scheduled']['frequency'])); ?></p>
                        <p><strong><?php _e('Material Types:', 'affiliatewp-cross-domain-plugin-suite'); ?></strong> <?php echo esc_html(implode(', ', $portal_data['upcoming_scheduled']['material_types'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Material Preview -->
<div id="material-preview-modal" class="affcd-modal" style="display: none;">
    <div class="affcd-modal-content">
        <div class="affcd-modal-header">
            <h3><?php _e('Material Preview', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
            <span class="affcd-modal-close">&times;</span>
        </div>
        <div class="affcd-modal-body">
            <div id="modal-material-content"></div>
        </div>
        <div class="affcd-modal-footer">
            <button class="button button-primary" id="copy-material"><?php _e('Copy Code', 'affiliatewp-cross-domain-plugin-suite'); ?></button>
            <button class="button" id="download-material"><?php _e('Download', 'affiliatewp-cross-domain-plugin-suite'); ?></button>
        </div>
    </div>
</div>

<style>
.affcd-portal-overview {
    margin-bottom: 30px;
}

.affcd-overview-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.affcd-overview-card {
    background: white;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
}

.affcd-card-icon {
    background: #0073aa;
    color: white;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.affcd-card-number {
    font-size: 28px;
    font-weight: bold;
    color: #0073aa;
    margin-bottom: 5px;
}

.affcd-card-label {
    color: #666;
    font-size: 14px;
}

.affcd-marketing-tabs {
    background: white;
    border: 1px solid #e1e1e1;
    border-radius: 4px;
}

.tab-content {
    display: none;
    padding: 30px;
}

.tab-content.active {
    display: block;
}

.affcd-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.affcd-material-types {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.affcd-material-type {
    background: #f8f9fa;
    border: 2px solid #e1e1e1;
    border-radius: 8px;
    padding: 25px;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
}

.affcd-material-type:hover {
    border-color: #0073aa;
    box-shadow: 0 4px 12px rgba(0,115,170,0.1);
}

.affcd-type-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-bottom: 15px;
}

.affcd-type-header .dashicons {
    font-size: 32px;
    color: #0073aa;
}

.affcd-type-header h4 {
    margin: 0;
    color: #333;
}

.affcd-material-type p {
    color: #666;
    margin: 15px 0 20px 0;
    line-height: 1.5;
}

.affcd-generated-materials {
    margin-top: 30px;
    padding-top: 30px;
    border-top: 2px solid #e1e1e1;
}

.affcd-templates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 25px;
}

.affcd-template-card {
    background: white;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.affcd-template-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 15px;
}

.affcd-template-header h4 {
    margin: 0;
    color: #333;
}

.affcd-template-actions {
    display: flex;
    gap: 5px;
}

.affcd-template-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
    font-size: 13px;
    color: #666;
}

.affcd-template-type {
    background: #0073aa;
    color: white;
    padding: 3px 8px;
    border-radius: 3px;
    font-weight: bold;
    text-transform: uppercase;
    font-size: 11px;
}

.affcd-template-preview {
    margin-bottom: 15px;
    color: #666;
    line-height: 1.5;
}

.affcd-template-stats {
    display: flex;
    justify-content: space-between;
    padding-top: 15px;
    border-top: 1px solid #f0f0f0;
}

.affcd-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    font-size: 13px;
}

.affcd-stat-label {
    color: #666;
    margin-bottom: 3px;
}

.affcd-stat-value {
    font-weight: bold;
    color: #0073aa;
}

.affcd-no-templates {
    text-align: center;
    padding: 60px 20px;
    color: #666;
    background: #f8f9fa;
    border-radius: 8px;
    border: 2px dashed #e1e1e1;
}

.affcd-analytics-filters {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e1e1e1;
}

.affcd-analytics-filters label {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: bold;
}

.affcd-analytics-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.affcd-analytics-card {
    background: #f8f9fa;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 25px;
}

.affcd-analytics-card h4 {
    margin: 0 0 20px 0;
    color: #333;
}

.affcd-full-width {
    grid-column: 1 / -1;
}

.affcd-platform-stats {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.affcd-platform-item {
    background: white;
    border: 1px solid #e1e1e1;
    border-radius: 6px;
    padding: 20px;
}

.affcd-platform-name {
    font-weight: bold;
    color: #333;
    margin-bottom: 15px;
    font-size: 16px;
}

.affcd-platform-metrics {
    display: flex;
    justify-content: space-around;
}

.affcd-metric {
    text-align: center;
}

.affcd-metric-value {
    display: block;
    font-size: 20px;
    font-weight: bold;
    color: #0073aa;
    margin-bottom: 5px;
}

.affcd-metric-label {
    font-size: 12px;
    color: #666;
}

.affcd-funnel-chart {
    margin-bottom: 20px;
}

.affcd-funnel-step {
    margin-bottom: 15px;
}

.affcd-funnel-bar {
    background: linear-gradient(90deg, #0073aa, #00a0d2);
    color: white;
    padding: 15px 20px;
    border-radius: 6px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-width: 200px;
    position: relative;
}

.affcd-funnel-label {
    font-weight: bold;
}

.affcd-funnel-value {
    font-size: 14px;
}

.affcd-funnel-summary {
    display: flex;
    justify-content: space-around;
    background: white;
    border: 1px solid #e1e1e1;
    border-radius: 6px;
    padding: 20px;
}

.affcd-funnel-metric {
    text-align: center;
}

.affcd-ab-testing-form {
    background: #f8f9fa;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 30px;
}

.affcd-form-row {
    margin-bottom: 20px;
}

.affcd-form-row label {
    display: block;
    font-weight: bold;
    margin-bottom: 8px;
    color: #333;
}

.affcd-form-row input,
.affcd-form-row select {
    width: 100%;
    max-width: 300px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.affcd-variation-options {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.affcd-variation-options label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: normal;
    margin-bottom: 0;
}

.affcd-material-checkboxes {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.affcd-material-checkboxes label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: normal;
    margin-bottom: 0;
}

.affcd-form-actions {
    display: flex;
    gap: 10px;
    margin-top: 25px;
}

.affcd-ab-tests-list {
    text-align: center;
    padding: 40px;
    color: #666;
    background: #f8f9fa;
    border-radius: 8px;
    border: 2px dashed #e1e1e1;
}

.affcd-scheduling-form {
    background: #f8f9fa;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 30px;
}

.affcd-scheduled-info {
    background: white;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 25px;
}

.affcd-schedule-details p {
    margin: 10px 0;
}

.affcd-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.8);
}

.affcd-modal-content {
    background: white;
    margin: 5% auto;
    padding: 0;
    border-radius: 8px;
    width: 80%;
    max-width: 800px;
    max-height: 80vh;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.affcd-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 1px solid #e1e1e1;
    background: #f8f9fa;
}

.affcd-modal-header h3 {
    margin: 0;
}

.affcd-modal-close {
    font-size: 28px;
    cursor: pointer;
    color: #666;
    line-height: 1;
}

.affcd-modal-close:hover {
    color: #333;
}

.affcd-modal-body {
    padding: 25px;
    max-height: 400px;
    overflow-y: auto;
}

.affcd-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 20px 25px;
    border-top: 1px solid #e1e1e1;
    background: #f8f9fa;
}

.affcd-loading {
    text-align: center;
    padding: 40px;
    color: #666;
}

.affcd-loading::before {
    content: "";
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #0073aa;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-right: 10px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@media (max-width: 768px) {
    .affcd-overview-cards {
        grid-template-columns: 1fr;
    }
    
    .affcd-material-types {
        grid-template-columns: 1fr;
    }
    
    .affcd-templates-grid {
        grid-template-columns: 1fr;
    }
    
    .affcd-analytics-grid {
        grid-template-columns: 1fr;
    }
    
    .affcd-platform-metrics {
        flex-direction: column;
        gap: 15px;
    }
    
    .affcd-variation-options,
    .affcd-material-checkboxes {
        flex-direction: column;
    }
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
    
    // Generate materials by type
    $('.generate-type').on('click', function() {
        const type = $(this).data('type');
        const $button = $(this);
        const originalText = $button.text();
        
        $button.prop('disabled', true).text('<?php _e("Generating...", "affiliatewp-cross-domain-plugin-suite"); ?>');
        
        $.post(ajaxurl, {
            action: 'affiliate_generate_materials',
            material_type: type,
            nonce: '<?php echo wp_create_nonce("affiliate_admin_nonce"); ?>'
        }, function(response) {
            if (response.success) {
                displayGeneratedMaterials(response.data.materials, type);
                $('#generated-materials').show();
            } else {
                alert('<?php _e("Failed to generate materials:", "affiliatewp-cross-domain-plugin-suite"); ?> ' + (response.data.message || '<?php _e("Unknown error", "affiliatewp-cross-domain-plugin-suite"); ?>'));
            }
        }).fail(function() {
            alert('<?php _e("Failed to generate materials. Please try again.", "affiliatewp-cross-domain-plugin-suite"); ?>');
        }).always(function() {
            $button.prop('disabled', false).text(originalText);
        });
    });
    
    // Display generated materials
    function displayGeneratedMaterials(materials, type) {
        const container = $('#materials-container');
        container.empty();
        
        $.each(materials, function(key, material) {
            const materialCard = $(`
                <div class="affcd-material-card" data-type="${type}" data-key="${key}">
                    <div class="affcd-material-header">
                        <h4>${material.name || key}</h4>
                        <div class="affcd-material-actions">
                            <button class="button button-small preview-material"><?php _e("Preview", "affiliatewp-cross-domain-plugin-suite"); ?></button>
                            <button class="button button-small copy-material"><?php _e("Copy", "affiliatewp-cross-domain-plugin-suite"); ?></button>
                            <button class="button button-small download-material"><?php _e("Download", "affiliatewp-cross-domain-plugin-suite"); ?></button>
                        </div>
                    </div>
                    <div class="affcd-material-preview">
                        ${material.preview || material.content || ''}
                    </div>
                    <div class="affcd-material-data" style="display: none;">
                        ${JSON.stringify(material)}
                    </div>
                </div>
            `);
            container.append(materialCard);
        });
    }
    
    // Preview material
    $(document).on('click', '.preview-material', function() {
        const $card = $(this).closest('.affcd-material-card');
        const materialData = JSON.parse($card.find('.affcd-material-data').text());
        
        let previewContent = '';
        if (materialData.html_code) {
            previewContent = `<div class="code-preview"><pre><code>${escapeHtml(materialData.html_code)}</code></pre></div>`;
        } else if (materialData.content) {
            previewContent = `<div class="text-preview">${escapeHtml(materialData.content)}</div>`;
        }
        
        $('#modal-material-content').html(previewContent);
        $('#material-preview-modal').show();
    });
    
    // Copy material code
    $(document).on('click', '.copy-material, #copy-material', function() {
        const $card = $(this).closest('.affcd-material-card');
        let textToCopy = '';
        
        if ($card.length) {
            const materialData = JSON.parse($card.find('.affcd-material-data').text());
            textToCopy = materialData.html_code || materialData.content || '';
        } else {
            textToCopy = $('#modal-material-content').text();
        }
        
        navigator.clipboard.writeText(textToCopy).then(function() {
            alert('<?php _e("Code copied to clipboard!", "affiliatewp-cross-domain-plugin-suite"); ?>');
        }).catch(function() {
            alert('<?php _e("Failed to copy code", "affiliatewp-cross-domain-plugin-suite"); ?>');
        });
    });
    
    // Modal controls
    $('.affcd-modal-close').on('click', function() {
        $(this).closest('.affcd-modal').hide();
    });
    
    $(window).on('click', function(e) {
        if ($(e.target).hasClass('affcd-modal')) {
            $('.affcd-modal').hide();
        }
    });
    
    // Template management
    $('#create-template').on('click', function() {
        // Open template creation modal/form
        alert('<?php _e("Template creation feature coming soon!", "affiliatewp-cross-domain-plugin-suite"); ?>');
    });
    
    $('.edit-template').on('click', function() {
        const templateId = $(this).data('template-id');
        alert('<?php _e("Template editing feature coming soon!", "affiliatewp-cross-domain-plugin-suite"); ?>');
    });
    
    $('.clone-template').on('click', function() {
        const templateId = $(this).data('template-id');
        const newName = prompt('<?php _e("Enter name for cloned template:", "affiliatewp-cross-domain-plugin-suite"); ?>');
        
        if (newName) {
            $.post(ajaxurl, {
                action: 'affiliate_clone_template',
                template_id: templateId,
                new_name: newName,
                nonce: '<?php echo wp_create_nonce("affiliate_admin_nonce"); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('<?php _e("Failed to clone template", "affiliatewp-cross-domain-plugin-suite"); ?>');
                }
            });
        }
    });
    
    $('.delete-template').on('click', function() {
        const templateId = $(this).data('template-id');
        
        if (confirm('<?php _e("Are you sure you want to delete this template?", "affiliatewp-cross-domain-plugin-suite"); ?>')) {
            $.post(ajaxurl, {
                action: 'affiliate_delete_template',
                template_id: templateId,
                nonce: '<?php echo wp_create_nonce("affiliate_admin_nonce"); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('<?php _e("Failed to delete template", "affiliatewp-cross-domain-plugin-suite"); ?>');
                }
            });
        }
    });
    
    // A/B Testing
    $('#create-ab-test').on('click', function() {
        $('.affcd-ab-testing-form').show();
        $(this).hide();
    });
    
    $('#cancel-ab-test').on('click', function() {
        $('.affcd-ab-testing-form').hide();
        $('#create-ab-test').show();
    });
    
    $('#ab-test-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            action: 'affiliate_ab_test_materials',
            test_name: $('#test-name').val(),
            base_template: $('#base-template').val(),
            variations: $('input[name="variations[]"]:checked').map(function() { return this.value; }).get(),
            nonce: '<?php echo wp_create_nonce("affiliate_admin_nonce"); ?>'
        };
        
        $.post(ajaxurl, formData, function(response) {
            if (response.success) {
                alert('<?php _e("A/B test created successfully!", "affiliatewp-cross-domain-plugin-suite"); ?>');
                location.reload();
            } else {
                alert('<?php _e("Failed to create A/B test", "affiliatewp-cross-domain-plugin-suite"); ?>');
            }
        });
    });
    
    // Scheduling
    $('#scheduling-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            action: 'affiliate_schedule_materials',
            frequency: $('#schedule-frequency').val(),
            material_types: $('input[name="material_types[]"]:checked').map(function() { return this.value; }).get(),
            nonce: '<?php echo wp_create_nonce("affiliate_admin_nonce"); ?>'
        };
        
        $.post(ajaxurl, formData, function(response) {
            if (response.success) {
                alert('<?php _e("Material generation scheduled successfully!", "affiliatewp-cross-domain-plugin-suite"); ?>');
                location.reload();
            } else {
                alert('<?php _e("Failed to schedule material generation", "affiliatewp-cross-domain-plugin-suite"); ?>');
            }
        });
    });
    
    // Export package
    $('#export-package').on('click', function() {
        const $button = $(this);
        const originalText = $button.text();
        
        $button.prop('disabled', true).text('<?php _e("Exporting...", "affiliatewp-cross-domain-plugin-suite"); ?>');
        
        $.post(ajaxurl, {
            action: 'affiliate_export_package',
            material_types: ['banner', 'email', 'social', 'landing'],
            nonce: '<?php echo wp_create_nonce("affiliate_admin_nonce"); ?>'
        }, function(response) {
            if (response.success) {
                // Create download link
                const link = document.createElement('a');
                link.href = response.data.download_url;
                link.download = response.data.filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } else {
                alert('<?php _e("Failed to export package", "affiliatewp-cross-domain-plugin-suite"); ?>');
            }
        }).always(function() {
            $button.prop('disabled', false).text(originalText);
        });
    });
    
    // Analytics period change
    $('#analytics-period').on('change', function() {
        const period = $(this).val();
        // Reload analytics data for selected period
        location.href = location.pathname + '?page=affiliate-marketing-portal&period=' + period;
    });
    
    // Utility function
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>