<?php
/**
 * Bulk Operations Class for WP Affiliate Cross Domain Plugin Suite
 *
 * Handles bulk operations for vanity codes, domains, and other
 * administrative tasks with progress tracking and rollback capability.
 *
 * Filename: admin/class-bulk-operations.php
 * Plugin: WP Affiliate Cross Domain Plugin Suite (Master)
 *
 * @package AffiliateWP_Cross_Domain_Plugin_Suite_Master
 * @version 1.0.0
 * @author Richard King, Starne Consulting
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Bulk_Operations {

    /**
     * Operations log table name
     *
     * @var string
     */
    private $operations_log_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        
        $this->operations_log_table = $wpdb->prefix . 'affcd_bulk_operations_log';
        
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_affcd_execute_bulk_operation', [$this, 'ajax_execute_bulk_operation']);
        add_action('wp_ajax_affcd_get_operation_status', [$this, 'ajax_get_operation_status']);
        add_action('wp_ajax_affcd_cancel_operation', [$this, 'ajax_cancel_operation']);
        add_action('wp_ajax_affcd_rollback_operation', [$this, 'ajax_rollback_operation']);
        add_action('wp_ajax_affcd_load_vanity_codes', [$this, 'ajax_load_vanity_codes']);
        add_action('wp_ajax_affcd_load_domains', [$this, 'ajax_load_domains']);
        add_action('wp_ajax_affcd_export_data', [$this, 'ajax_export_data']);
        add_action('wp_ajax_affcd_import_preview', [$this, 'ajax_import_preview']);
        add_action('wp_ajax_affcd_execute_import', [$this, 'ajax_execute_import']);
        
        // Schedule cleanup of old operation logs
        add_action('affcd_cleanup_bulk_operations', [$this, 'cleanup_old_operations']);
        if (!wp_next_scheduled('affcd_cleanup_bulk_operations')) {
            wp_schedule_event(time(), 'daily', 'affcd_cleanup_bulk_operations');
        }
        
        $this->maybe_create_operations_log_table();
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'affiliate-wp',
            __('Bulk Operations', 'affiliate-cross-domain'),
            __('Bulk Operations', 'affiliate-cross-domain'),
            'manage_affiliates',
            'affcd-bulk-operations',
            [$this, 'render_bulk_operations_page']
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ('affiliate-wp_page_affcd-bulk-operations' !== $hook) {
            return;
        }

        wp_enqueue_script('affcd-bulk-operations', 
            AFFCD_PLUGIN_URL . 'assets/js/bulk-operations.js', 
            ['jquery'], 
            AFFCD_VERSION, 
            true
        );

        wp_enqueue_style('affcd-bulk-operations', 
            AFFCD_PLUGIN_URL . 'assets/css/bulk-operations.css', 
            [], 
            AFFCD_VERSION
        );

        wp_localize_script('affcd-bulk-operations', 'affcdBulkAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('affcd_bulk_nonce'),
            'strings' => [
                'processing' => __('Processing...', 'affiliate-cross-domain'),
                'completed' => __('Completed', 'affiliate-cross-domain'),
                'failed' => __('Failed', 'affiliate-cross-domain'),
                'cancelled' => __('Cancelled', 'affiliate-cross-domain'),
                'confirmCancel' => __('Are you sure you want to cancel this operation?', 'affiliate-cross-domain'),
                'confirmRollback' => __('Are you sure you want to rollback this operation? This cannot be undone.', 'affiliate-cross-domain'),
                'confirmBulkAction' => __('Are you sure you want to perform this bulk action?', 'affiliate-cross-domain'),
                'selectItems' => __('Please select at least one item.', 'affiliate-cross-domain'),
                'noItemsFound' => __('No items found matching your criteria.', 'affiliate-cross-domain'),
                'operationStarted' => __('Operation started successfully.', 'affiliate-cross-domain'),
                'operationFailed' => __('Failed to start operation.', 'affiliate-cross-domain'),
            ]
        ]);
    }

    /**
     * Render bulk operations page
     */
    public function render_bulk_operations_page() {
        $active_tab = $_GET['tab'] ?? 'vanity_codes';
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url(admin_url('admin.php?page=affcd-bulk-operations&tab=vanity_codes')); ?>" 
                   class="nav-tab <?php echo $active_tab === 'vanity_codes' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Vanity Codes', 'affiliate-cross-domain'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=affcd-bulk-operations&tab=domains')); ?>" 
                   class="nav-tab <?php echo $active_tab === 'domains' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Domains', 'affiliate-cross-domain'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=affcd-bulk-operations&tab=import_export')); ?>" 
                   class="nav-tab <?php echo $active_tab === 'import_export' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Import/Export', 'affiliate-cross-domain'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=affcd-bulk-operations&tab=operations_log')); ?>" 
                   class="nav-tab <?php echo $active_tab === 'operations_log' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Operations Log', 'affiliate-cross-domain'); ?>
                </a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'domains':
                        $this->render_domains_tab();
                        break;
                    case 'import_export':
                        $this->render_import_export_tab();
                        break;
                    case 'operations_log':
                        $this->render_operations_log_tab();
                        break;
                    case 'vanity_codes':
                    default:
                        $this->render_vanity_codes_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render vanity codes tab
     */
    private function render_vanity_codes_tab() {
        $stats = $this->get_vanity_code_statistics();
        ?>
        <div class="vanity-codes-tab">
            <div class="affcd-stats-grid">
                <div class="affcd-stat-card">
                    <h3><?php _e('Total Codes', 'affiliate-cross-domain'); ?></h3>
                    <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                </div>
                <div class="affcd-stat-card">
                    <h3><?php _e('Active Codes', 'affiliate-cross-domain'); ?></h3>
                    <div class="stat-number"><?php echo number_format($stats['active']); ?></div>
                </div>
                <div class="affcd-stat-card">
                    <h3><?php _e('Expired Codes', 'affiliate-cross-domain'); ?></h3>
                    <div class="stat-number"><?php echo number_format($stats['expired']); ?></div>
                </div>
                <div class="affcd-stat-card">
                    <h3><?php _e('Unused Codes', 'affiliate-cross-domain'); ?></h3>
                    <div class="stat-number"><?php echo number_format($stats['unused']); ?></div>
                </div>
            </div>

            <div class="affcd-operation-section">
                <h3><?php _e('Bulk Operations for Vanity Codes', 'affiliate-cross-domain'); ?></h3>
                
                <div class="affcd-operation-controls">
                    <div class="form-group">
                        <label for="vanity-operation"><?php _e('Operation', 'affiliate-cross-domain'); ?></label>
                        <select id="vanity-operation" name="operation">
                            <option value=""><?php _e('Select Operation', 'affiliate-cross-domain'); ?></option>
                            <option value="activate"><?php _e('Activate Codes', 'affiliate-cross-domain'); ?></option>
                            <option value="deactivate"><?php _e('Deactivate Codes', 'affiliate-cross-domain'); ?></option>
                            <option value="delete"><?php _e('Delete Codes', 'affiliate-cross-domain'); ?></option>
                            <option value="update_expiry"><?php _e('Update Expiry Date', 'affiliate-cross-domain'); ?></option>
                            <option value="export_data"><?php _e('Export Usage Data', 'affiliate-cross-domain'); ?></option>
                            <option value="reset_stats"><?php _e('Reset Statistics', 'affiliate-cross-domain'); ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="expiry-date-group" style="display: none;">
                        <label for="new-expiry-date"><?php _e('New Expiry Date', 'affiliate-cross-domain'); ?></label>
                        <input type="datetime-local" id="new-expiry-date" name="expiry_date">
                    </div>
                    
                    <div class="form-group">
                        <label for="vanity-filter"><?php _e('Filter', 'affiliate-cross-domain'); ?></label>
                        <select id="vanity-filter" name="filter">
                            <option value="all"><?php _e('All Codes', 'affiliate-cross-domain'); ?></option>
                            <option value="active"><?php _e('Active Only', 'affiliate-cross-domain'); ?></option>
                            <option value="inactive"><?php _e('Inactive Only', 'affiliate-cross-domain'); ?></option>
                            <option value="expired"><?php _e('Expired Only', 'affiliate-cross-domain'); ?></option>
                            <option value="unused"><?php _e('Unused Codes', 'affiliate-cross-domain'); ?></option>
                        </select>
                    </div>
                    
                    <button type="button" id="load-vanity-codes" class="button"><?php _e('Load Codes', 'affiliate-cross-domain'); ?></button>
                    <button type="button" id="execute-vanity-operation" class="button button-primary" disabled><?php _e('Execute Operation', 'affiliate-cross-domain'); ?></button>
                </div>
                
                <div id="vanity-codes-selection" class="affcd-selection-area" style="display: none;">
                    <h4><?php _e('Select Codes', 'affiliate-cross-domain'); ?></h4>
                    <div class="selection-controls">
                        <button type="button" id="select-all-vanity" class="button button-small"><?php _e('Select All', 'affiliate-cross-domain'); ?></button>
                        <button type="button" id="deselect-all-vanity" class="button button-small"><?php _e('Deselect All', 'affiliate-cross-domain'); ?></button>
                        <span class="selection-count">0 <?php _e('selected', 'affiliate-cross-domain'); ?></span>
                    </div>
                    <div id="vanity-codes-list" class="codes-list"></div>
                </div>
                
                <div id="vanity-operation-progress" class="affcd-progress" style="display: none;">
                    <div class="affcd-progress-bar" style="width: 0%"></div>
                    <div class="affcd-progress-text">0%</div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render domains tab
     */
    private function render_domains_tab() {
        $stats = $this->get_domain_statistics();
        ?>
        <div class="domains-tab">
            <div class="affcd-stats-grid">
                <div class="affcd-stat-card">
                    <h3><?php _e('Total Domains', 'affiliate-cross-domain'); ?></h3>
                    <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                </div>
                <div class="affcd-stat-card">
                    <h3><?php _e('Active Domains', 'affiliate-cross-domain'); ?></h3>
                    <div class="stat-number"><?php echo number_format($stats['active']); ?></div>
                </div>
                <div class="affcd-stat-card">
                    <h3><?php _e('Verified Domains', 'affiliate-cross-domain'); ?></h3>
                    <div class="stat-number"><?php echo number_format($stats['verified']); ?></div>
                </div>
                <div class="affcd-stat-card">
                    <h3><?php _e('Unverified Domains', 'affiliate-cross-domain'); ?></h3>
                    <div class="stat-number"><?php echo number_format($stats['unverified']); ?></div>
                </div>
            </div>

            <div class="affcd-operation-section">
                <h3><?php _e('Bulk Operations for Domains', 'affiliate-cross-domain'); ?></h3>
                
                <div class="affcd-operation-controls">
                    <div class="form-group">
                        <label for="domain-operation"><?php _e('Operation', 'affiliate-cross-domain'); ?></label>
                        <select id="domain-operation" name="operation">
                            <option value=""><?php _e('Select Operation', 'affiliate-cross-domain'); ?></option>
                            <option value="verify_all"><?php _e('Verify Domains', 'affiliate-cross-domain'); ?></option>
                            <option value="activate"><?php _e('Activate Domains', 'affiliate-cross-domain'); ?></option>
                            <option value="deactivate"><?php _e('Deactivate Domains', 'affiliate-cross-domain'); ?></option>
                            <option value="regenerate_keys"><?php _e('Regenerate API Keys', 'affiliate-cross-domain'); ?></option>
                            <option value="test_connections"><?php _e('Test Connections', 'affiliate-cross-domain'); ?></option>
                            <option value="export_domains"><?php _e('Export Domain Data', 'affiliate-cross-domain'); ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="domain-filter"><?php _e('Filter', 'affiliate-cross-domain'); ?></label>
                        <select id="domain-filter" name="filter">
                            <option value="all"><?php _e('All Domains', 'affiliate-cross-domain'); ?></option>
                            <option value="active"><?php _e('Active Only', 'affiliate-cross-domain'); ?></option>
                            <option value="inactive"><?php _e('Inactive Only', 'affiliate-cross-domain'); ?></option>
                            <option value="verified"><?php _e('Verified Only', 'affiliate-cross-domain'); ?></option>
                            <option value="unverified"><?php _e('Unverified Only', 'affiliate-cross-domain'); ?></option>
                        </select>
                    </div>
                    
                    <button type="button" id="load-domains" class="button"><?php _e('Load Domains', 'affiliate-cross-domain'); ?></button>
                    <button type="button" id="execute-domain-operation" class="button button-primary" disabled><?php _e('Execute Operation', 'affiliate-cross-domain'); ?></button>
                </div>
                
                <div id="domains-selection" class="affcd-selection-area" style="display: none;">
                    <h4><?php _e('Select Domains', 'affiliate-cross-domain'); ?></h4>
                    <div class="selection-controls">
                        <button type="button" id="select-all-domains" class="button button-small"><?php _e('Select All', 'affiliate-cross-domain'); ?></button>
                        <button type="button" id="deselect-all-domains" class="button button-small"><?php _e('Deselect All', 'affiliate-cross-domain'); ?></button>
                        <span class="selection-count">0 <?php _e('selected', 'affiliate-cross-domain'); ?></span>
                    </div>
                    <div id="domains-list" class="domains-list"></div>
                </div>
                
                <div id="domain-operation-progress" class="affcd-progress" style="display: none;">
                    <div class="affcd-progress-bar" style="width: 0%"></div>
                    <div class="affcd-progress-text">0%</div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render import/export tab
     */
    private function render_import_export_tab() {
        ?>
        <div class="import-export-tab">
            <!-- Export Section -->
            <div class="affcd-operation-section">
                <h3><?php _e('Export Data', 'affiliate-cross-domain'); ?></h3>
                
                <div class="affcd-operation-controls">
                    <div class="form-group">
                        <label for="export-type"><?php _e('Data Type', 'affiliate-cross-domain'); ?></label>
                        <select id="export-type" name="export_type">
                            <option value="vanity_codes"><?php _e('Vanity Codes', 'affiliate-cross-domain'); ?></option>
                            <option value="domains"><?php _e('authorised Domains', 'affiliate-cross-domain'); ?></option>
                            <option value="analytics"><?php _e('Analytics Data', 'affiliate-cross-domain'); ?></option>
                            <option value="security_logs"><?php _e('Security Logs', 'affiliate-cross-domain'); ?></option>
                            <option value="all"><?php _e('All Data', 'affiliate-cross-domain'); ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="export-format"><?php _e('Format', 'affiliate-cross-domain'); ?></label>
                        <select id="export-format" name="export_format">
                            <option value="csv"><?php _e('CSV', 'affiliate-cross-domain'); ?></option>
                            <option value="json"><?php _e('JSON', 'affiliate-cross-domain'); ?></option>
                            <option value="xml"><?php _e('XML', 'affiliate-cross-domain'); ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="export-date-range"><?php _e('Date Range', 'affiliate-cross-domain'); ?></label>
                        <select id="export-date-range" name="date_range">
                            <option value="all"><?php _e('All Time', 'affiliate-cross-domain'); ?></option>
                            <option value="last_30_days"><?php _e('Last 30 Days', 'affiliate-cross-domain'); ?></option>
                            <option value="last_90_days"><?php _e('Last 90 Days', 'affiliate-cross-domain'); ?></option>
                            <option value="custom"><?php _e('Custom Range', 'affiliate-cross-domain'); ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="custom-date-range" style="display: none;">
                        <label for="export-start-date"><?php _e('Start Date', 'affiliate-cross-domain'); ?></label>
                        <input type="date" id="export-start-date" name="start_date">
                        <label for="export-end-date"><?php _e('End Date', 'affiliate-cross-domain'); ?></label>
                        <input type="date" id="export-end-date" name="end_date">
                    </div>
                    
                    <button type="button" id="start-export" class="button button-primary"><?php _e('Start Export', 'affiliate-cross-domain'); ?></button>
                </div>
                
                <div id="export-progress" class="affcd-progress" style="display: none;">
                    <div class="affcd-progress-bar" style="width: 0%"></div>
                    <div class="affcd-progress-text">0%</div>
                </div>
            </div>

            <!-- Import Section -->
            <div class="affcd-operation-section">
                <h3><?php _e('Import Data', 'affiliate-cross-domain'); ?></h3>
                
                <div class="affcd-operation-controls">
                    <div class="form-group">
                        <label for="import-type"><?php _e('Data Type', 'affiliate-cross-domain'); ?></label>
                        <select id="import-type" name="import_type">
                            <option value="vanity_codes"><?php _e('Vanity Codes', 'affiliate-cross-domain'); ?></option>
                            <option value="domains"><?php _e('authorised Domains', 'affiliate-cross-domain'); ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="import-file"><?php _e('Import File', 'affiliate-cross-domain'); ?></label>
                        <input type="file" id="import-file" name="import_file" accept=".csv,.json,.xml">
                        <p class="description"><?php _e('Supported formats: CSV, JSON, XML', 'affiliate-cross-domain'); ?></p>
                    </div>
                    
                    <div class="form-group">
                        <label for="import-mode"><?php _e('Import Mode', 'affiliate-cross-domain'); ?></label>
                        <select id="import-mode" name="import_mode">
                            <option value="insert"><?php _e('Insert Only (Skip Existing)', 'affiliate-cross-domain'); ?></option>
                            <option value="update"><?php _e('Update Existing', 'affiliate-cross-domain'); ?></option>
                            <option value="replace"><?php _e('Replace All', 'affiliate-cross-domain'); ?></option>
                        </select>
                    </div>
                    
                    <button type="button" id="preview-import" class="button"><?php _e('Preview Import', 'affiliate-cross-domain'); ?></button>
                </div>
                
                <div id="import-preview" style="display: none; margin-top: 20px;">
                    <h4><?php _e('Import Preview', 'affiliate-cross-domain'); ?></h4>
                    <div id="import-preview-content"></div>
                    <div style="margin-top: 15px;">
                        <button type="button" id="confirm-import" class="button button-primary">
                            <?php _e('Confirm Import', 'affiliate-cross-domain'); ?>
                        </button>
                        <button type="button" id="cancel-import" class="button">
                            <?php _e('Cancel', 'affiliate-cross-domain'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Recent Downloads -->
            <div class="affcd-operation-section">
                <h3><?php _e('Recent Downloads', 'affiliate-cross-domain'); ?></h3>
                <div id="recent-downloads">
                    <?php $this->render_recent_downloads(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render operations log tab
     */
    private function render_operations_log_tab() {
        $operations = $this->get_recent_operations();
        ?>
        <div class="operations-log-tab">
            <div class="affcd-operation-section">
                <h3><?php _e('Recent Operations', 'affiliate-cross-domain'); ?></h3>
                
                <div class="affcd-operations-table">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Operation', 'affiliate-cross-domain'); ?></th>
                                <th><?php _e('Type', 'affiliate-cross-domain'); ?></th>
                                <th><?php _e('Status', 'affiliate-cross-domain'); ?></th>
                                <th><?php _e('Progress', 'affiliate-cross-domain'); ?></th>
                                <th><?php _e('Started', 'affiliate-cross-domain'); ?></th>
                                <th><?php _e('Completed', 'affiliate-cross-domain'); ?></th>
                                <th><?php _e('User', 'affiliate-cross-domain'); ?></th>
                                <th><?php _e('Actions', 'affiliate-cross-domain'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($operations as $operation): ?>
                                <tr>
                                    <td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $operation->operation_name))); ?></strong></td>
                                    <td><?php echo esc_html(ucwords(str_replace('_', ' ', $operation->operation_type))); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo esc_attr($operation->status); ?>">
                                            <?php echo esc_html(ucwords($operation->status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($operation->total_items > 0): ?>
                                            <div class="progress-bar-small">
                                                <div class="progress-fill-small" style="width: <?php echo esc_attr($operation->progress_percentage ?? 0); ?>%"></div>
                                            </div>
                                            <span class="progress-text">
                                                <?php echo intval($operation->processed_items ?? 0); ?>/<?php echo intval($operation->total_items); ?>
                                                (<?php echo number_format($operation->progress_percentage ?? 0, 1); ?>%)
                                            </span>
                                        <?php else: ?>
                                            <?php _e('N/A', 'affiliate-cross-domain'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html(mysql2date('Y-m-d H:i:s', $operation->started_at)); ?></td>
                                    <td>
                                        <?php if ($operation->completed_at): ?>
                                            <?php echo esc_html(mysql2date('Y-m-d H:i:s', $operation->completed_at)); ?>
                                        <?php else: ?>
                                            <?php _e('Running', 'affiliate-cross-domain'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $user = get_userdata($operation->user_id);
                                        echo $user ? esc_html($user->display_name) : __('Unknown', 'affiliate-cross-domain');
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (in_array($operation->status, ['pending', 'running'])): ?>
                                            <button type="button" class="button button-small cancel-operation" 
                                                    data-operation-id="<?php echo esc_attr($operation->id); ?>">
                                                <?php _e('Cancel', 'affiliate-cross-domain'); ?>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($operation->can_rollback && $operation->status === 'completed'): ?>
                                            <button type="button" class="button button-small rollback-operation" 
                                                    data-operation-id="<?php echo esc_attr($operation->id); ?>">
                                                <?php _e('Rollback', 'affiliate-cross-domain'); ?>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="button button-small view-operation-details" 
                                                data-operation-id="<?php echo esc_attr($operation->id); ?>">
                                            <?php _e('Details', 'affiliate-cross-domain'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($operations)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 20px;">
                                        <?php _e('No operations found.', 'affiliate-cross-domain'); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <style>
        .progress-bar-small {
            width: 100px;
            height: 8px;
            background: #f0f0f0;
            border-radius: 4px;
            overflow: hidden;
            display: inline-block;
            vertical-align: middle;
            margin-right: 5px;
        }
        .progress-fill-small {
            height: 100%;
            background: #0073aa;
            transition: width 0.3s ease;
        }
        .status-badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-completed { background: #46b450; color: white; }
        .status-failed { background: #dc3232; color: white; }
        .status-cancelled { background: #ffb900; color: black; }
        .status-running { background: #0073aa; color: white; }
        .status-rolled_back { background: #f56e28; color: white; }
        </style>
        <?php
    }

    /**
     * Render recent downloads
     */
    private function render_recent_downloads() {
        $uploads_dir = wp_upload_dir();
        $exports_dir = $uploads_dir['basedir'] . '/affcd-exports/';
        
        if (!is_dir($exports_dir)) {
            echo '<p>' . __('No downloads available.', 'affiliate-cross-domain') . '</p>';
            return;
        }
        
        $files = glob($exports_dir . '*');
        if (empty($files)) {
            echo '<p>' . __('No downloads available.', 'affiliate-cross-domain') . '</p>';
            return;
        }
        
        // Sort by modification time (newest first)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $recent_files = array_slice($files, 0, 10);
        
        echo '<ul class="recent-downloads-list">';
        foreach ($recent_files as $file) {
            $filename = basename($file);
            $file_url = $uploads_dir['baseurl'] . '/affcd-exports/' . $filename;
            $file_size = size_format(filesize($file));
            $file_date = date('Y-m-d H:i:s', filemtime($file));
            
            echo '<li>';
            echo '<strong><a href="' . esc_url($file_url) . '" target="_blank">' . esc_html($filename) . '</a></strong>';
            echo '<br><small>' . esc_html($file_size) . ' - ' . esc_html($file_date) . '</small>';
            echo '</li>';
        }
        echo '</ul>';
    }

    /**
     * Get vanity code statistics
     */
    private function get_vanity_code_statistics() {
        global $wpdb;
        
        $vanity_table = $wpdb->prefix . 'affcd_vanity_codes';
        
        return [
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$vanity_table}"),
            'active' => $wpdb->get_var("SELECT COUNT(*) FROM {$vanity_table} WHERE status = 'active'"),
            'inactive' => $wpdb->get_var("SELECT COUNT(*) FROM {$vanity_table} WHERE status = 'inactive'"),
            'expired' => $wpdb->get_var("SELECT COUNT(*) FROM {$vanity_table} WHERE expires_at IS NOT NULL AND expires_at < NOW()"),
            'unused' => $wpdb->get_var("SELECT COUNT(*) FROM {$vanity_table} WHERE usage_count = 0"),
        ];
    }

    /**
     * Get domain statistics
     */
    private function get_domain_statistics() {
        global $wpdb;
        
        $domains_table = $wpdb->prefix . 'affcd_authorised_domains';
        
        return [
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$domains_table}"),
            'active' => $wpdb->get_var("SELECT COUNT(*) FROM {$domains_table} WHERE status = 'active'"),
            'inactive' => $wpdb->get_var("SELECT COUNT(*) FROM {$domains_table} WHERE status != 'active'"),
            'verified' => $wpdb->get_var("SELECT COUNT(*) FROM {$domains_table} WHERE verification_status = 'verified'"),
            'unverified' => $wpdb->get_var("SELECT COUNT(*) FROM {$domains_table} WHERE verification_status != 'verified'"),
        ];
    }

    /**
     * Get recent operations
     */
    private function get_recent_operations($limit = 50) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->operations_log_table} 
             ORDER BY started_at DESC 
             LIMIT %d",
            $limit
        ));
    }

    /**
     * AJAX: Execute bulk operation
     */
    public function ajax_execute_bulk_operation() {
        check_ajax_referer('affcd_bulk_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions.', 'affiliate-cross-domain'));
        }

        $operation_type = sanitize_text_field($_POST['operation_type'] ?? '');
        $operation = sanitize_text_field($_POST['operation'] ?? '');
        $items = array_map('absint', $_POST['items'] ?? []);
        $options = $_POST['options'] ?? [];

        if (empty($operation_type) || empty($operation) || empty($items)) {
            wp_send_json_error(__('Missing required parameters.', 'affiliate-cross-domain'));
        }

        // Sanitize options
        $sanitized_options = [];
        foreach ($options as $key => $value) {
            $sanitized_options[sanitize_key($key)] = sanitize_text_field($value);
        }

        // Start the bulk operation
        $operation_id = $this->start_bulk_operation($operation_type, $operation, $items, $sanitized_options);
        
        if (is_wp_error($operation_id)) {
            wp_send_json_error($operation_id->get_error_message());
        }

        wp_send_json_success([
            'operation_id' => $operation_id,
            'message' => __('Operation started successfully.', 'affiliate-cross-domain')
        ]);
    }

    /**
     * AJAX: Get operation status
     */
    public function ajax_get_operation_status() {
        check_ajax_referer('affcd_bulk_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions.', 'affiliate-cross-domain'));
        }

        $operation_id = absint($_POST['operation_id'] ?? 0);
        $operation = $this->get_operation($operation_id);
        
        if (!$operation) {
            wp_send_json_error(__('Operation not found.', 'affiliate-cross-domain'));
        }

        wp_send_json_success($operation);
    }

    /**
     * AJAX: Cancel operation
     */
    public function ajax_cancel_operation() {
        check_ajax_referer('affcd_bulk_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions.', 'affiliate-cross-domain'));
        }

        $operation_id = absint($_POST['operation_id'] ?? 0);
        $result = $this->cancel_operation($operation_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'message' => __('Operation cancelled successfully.', 'affiliate-cross-domain')
        ]);
    }

    /**
     * AJAX: Rollback operation
     */
    public function ajax_rollback_operation() {
        check_ajax_referer('affcd_bulk_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions.', 'affiliate-cross-domain'));
        }

        $operation_id = absint($_POST['operation_id'] ?? 0);
        $result = $this->rollback_operation($operation_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'message' => __('Operation rolled back successfully.', 'affiliate-cross-domain')
        ]);
    }

    /**
     * AJAX: Load vanity codes
     */
    public function ajax_load_vanity_codes() {
        check_ajax_referer('affcd_bulk_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions.', 'affiliate-cross-domain'));
        }

        $filter = sanitize_text_field($_POST['filter'] ?? 'all');
        $codes = $this->get_vanity_codes_for_bulk($filter);
        
        wp_send_json_success([
            'codes' => $codes,
            'total' => count($codes)
        ]);
    }

    /**
     * AJAX: Load domains
     */
    public function ajax_load_domains() {
        check_ajax_referer('affcd_bulk_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions.', 'affiliate-cross-domain'));
        }

        $filter = sanitize_text_field($_POST['filter'] ?? 'all');
        $domains = $this->get_domains_for_bulk($filter);
        
        wp_send_json_success([
            'domains' => $domains,
            'total' => count($domains)
        ]);
    }

    /**
     * AJAX: Export data
     */
    public function ajax_export_data() {
        check_ajax_referer('affcd_bulk_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions.', 'affiliate-cross-domain'));
        }

        $export_type = sanitize_text_field($_POST['export_type'] ?? '');
        $export_format = sanitize_text_field($_POST['export_format'] ?? 'csv');
        $date_range = sanitize_text_field($_POST['date_range'] ?? 'all');
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date = sanitize_text_field($_POST['end_date'] ?? '');

        if (empty($export_type)) {
            wp_send_json_error(__('Export type is required.', 'affiliate-cross-domain'));
        }

        $export_id = $this->start_export($export_type, $export_format, $date_range, $start_date, $end_date);
        
        if (is_wp_error($export_id)) {
            wp_send_json_error($export_id->get_error_message());
        }

        wp_send_json_success([
            'export_id' => $export_id,
            'message' => __('Export started successfully.', 'affiliate-cross-domain')
        ]);
    }

    /**
     * AJAX: Import preview
     */
    public function ajax_import_preview() {
        check_ajax_referer('affcd_bulk_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions.', 'affiliate-cross-domain'));
        }

        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('Please select a valid file to import.', 'affiliate-cross-domain'));
        }

        $import_type = sanitize_text_field($_POST['import_type'] ?? '');
        $preview_data = $this->preview_import($_FILES['import_file'], $import_type);
        
        if (is_wp_error($preview_data)) {
            wp_send_json_error($preview_data->get_error_message());
        }

        wp_send_json_success($preview_data);
    }

    /**
     * AJAX: Execute import
     */
    public function ajax_execute_import() {
        check_ajax_referer('affcd_bulk_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions.', 'affiliate-cross-domain'));
        }

        $import_data = $_POST['import_data'] ?? [];
        $import_type = sanitize_text_field($_POST['import_type'] ?? '');
        $import_mode = sanitize_text_field($_POST['import_mode'] ?? 'insert');

        if (empty($import_data) || empty($import_type)) {
            wp_send_json_error(__('Missing import data or type.', 'affiliate-cross-domain'));
        }

        $import_id = $this->start_import($import_data, $import_type, $import_mode);
        
        if (is_wp_error($import_id)) {
            wp_send_json_error($import_id->get_error_message());
        }

        wp_send_json_success([
            'import_id' => $import_id,
            'message' => __('Import started successfully.', 'affiliate-cross-domain')
        ]);
    }

    /**
     * Start bulk operation
     */
    private function start_bulk_operation($operation_type, $operation, $items, $options = []) {
        global $wpdb;
        
        $operation_data = [
            'operation_type' => $operation_type,
            'operation_name' => $operation,
            'total_items' => count($items),
            'processed_items' => 0,
            'progress_percentage' => 0,
            'items_data' => wp_json_encode($items),
            'options_data' => wp_json_encode($options),
            'status' => 'pending',
            'user_id' => get_current_user_id(),
            'started_at' => current_time('mysql'),
            'can_rollback' => in_array($operation, ['activate', 'deactivate', 'update_expiry']) ? 1 : 0
        ];

        $result = $wpdb->insert($this->operations_log_table, $operation_data);
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create operation record.', 'affiliate-cross-domain'));
        }

        $operation_id = $wpdb->insert_id;
        
        // Schedule the operation to run in background
        wp_schedule_single_event(time() + 5, 'affcd_process_bulk_operation', [$operation_id]);
        
        return $operation_id;
    }

    /**
     * Get operation
     */
    private function get_operation($operation_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->operations_log_table} WHERE id = %d",
            $operation_id
        ), ARRAY_A);
    }

    /**
     * Cancel operation
     */
    private function cancel_operation($operation_id) {
        global $wpdb;
        
        $operation = $this->get_operation($operation_id);
        
        if (!$operation) {
            return new WP_Error('not_found', __('Operation not found.', 'affiliate-cross-domain'));
        }

        if (!in_array($operation['status'], ['pending', 'running'])) {
            return new WP_Error('invalid_status', __('Operation cannot be cancelled.', 'affiliate-cross-domain'));
        }

        $result = $wpdb->update(
            $this->operations_log_table,
            [
                'status' => 'cancelled',
                'completed_at' => current_time('mysql')
            ],
            ['id' => $operation_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to cancel operation.', 'affiliate-cross-domain'));
        }

        return true;
    }

    /**
     * Rollback operation
     */
    private function rollback_operation($operation_id) {
        global $wpdb;
        
        $operation = $this->get_operation($operation_id);
        
        if (!$operation || !$operation['can_rollback'] || $operation['status'] !== 'completed') {
            return new WP_Error('cannot_rollback', __('Operation cannot be rolled back.', 'affiliate-cross-domain'));
        }

        $rollback_data = json_decode($operation['rollback_data'], true);
        
        if (!$rollback_data) {
            return new WP_Error('no_rollback_data', __('No rollback data available.', 'affiliate-cross-domain'));
        }

        // Perform rollback based on operation type
        $rollback_result = $this->perform_rollback($operation['operation_type'], $operation['operation_name'], $rollback_data);
        
        if (is_wp_error($rollback_result)) {
            $wpdb->update(
                $this->operations_log_table,
                [
                    'status' => 'rollback_failed',
                    'rollback_errors' => wp_json_encode($rollback_result->get_error_messages())
                ],
                ['id' => $operation_id]
            );
            return $rollback_result;
        }

        $wpdb->update(
            $this->operations_log_table,
            [
                'status' => 'rolled_back',
                'rolled_back_at' => current_time('mysql')
            ],
            ['id' => $operation_id]
        );

        return true;
    }

    /**
     * Perform rollback
     */
    private function perform_rollback($operation_type, $operation, $rollback_data) {
        switch ($operation_type) {
            case 'vanity_codes':
                return $this->rollback_vanity_code_operation($operation, $rollback_data);
            case 'domains':
                return $this->rollback_domain_operation($operation, $rollback_data);
            default:
                return new WP_Error('unsupported', __('Rollback not supported for this operation type.', 'affiliate-cross-domain'));
        }
    }

    /**
     * Rollback vanity code operation
     */
    private function rollback_vanity_code_operation($operation, $rollback_data) {
        global $wpdb;
        
        $vanity_table = $wpdb->prefix . 'affcd_vanity_codes';
        $errors = [];
        
        foreach ($rollback_data as $code_id => $original_data) {
            $result = $wpdb->update(
                $vanity_table,
                $original_data,
                ['id' => $code_id]
            );
            
            if ($result === false) {
                $errors[] = sprintf(__('Failed to rollback code ID %d', 'affiliate-cross-domain'), $code_id);
            }
        }
        
        if (!empty($errors)) {
            return new WP_Error('rollback_failed', implode('; ', $errors));
        }
        
        return true;
    }

    /**
     * Rollback domain operation
     */
    private function rollback_domain_operation($operation, $rollback_data) {
        global $wpdb;
        
        $domains_table = $wpdb->prefix . 'affcd_authorised_domains';
        $errors = [];
        
        foreach ($rollback_data as $domain_id => $original_data) {
            $result = $wpdb->update(
                $domains_table,
                $original_data,
                ['id' => $domain_id]
            );
            
            if ($result === false) {
                $errors[] = sprintf(__('Failed to rollback domain ID %d', 'affiliate-cross-domain'), $domain_id);
            }
        }
        
        if (!empty($errors)) {
            return new WP_Error('rollback_failed', implode('; ', $errors));
        }
        
        return true;
    }

    /**
     * Get vanity codes for bulk operations
     */
    private function get_vanity_codes_for_bulk($filter = 'all', $limit = 1000) {
        global $wpdb;
        
        $vanity_table = $wpdb->prefix . 'affcd_vanity_codes';
        $where_clause = '';
        
        switch ($filter) {
            case 'active':
                $where_clause = "WHERE status = 'active'";
                break;
            case 'inactive':
                $where_clause = "WHERE status = 'inactive'";
                break;
            case 'expired':
                $where_clause = "WHERE expires_at IS NOT NULL AND expires_at < NOW()";
                break;
            case 'unused':
                $where_clause = "WHERE usage_count = 0";
                break;
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, code, affiliate_id, status, usage_count, expires_at 
             FROM {$vanity_table} 
             {$where_clause}
             ORDER BY created_at DESC 
             LIMIT %d",
            $limit
        ), ARRAY_A);
    }

    /**
     * Get domains for bulk operations
     */
    private function get_domains_for_bulk($filter = 'all', $limit = 1000) {
        global $wpdb;
        
        $domains_table = $wpdb->prefix . 'affcd_authorised_domains';
        $where_clause = '';
        
        switch ($filter) {
            case 'active':
                $where_clause = "WHERE status = 'active'";
                break;
            case 'inactive':
                $where_clause = "WHERE status != 'active'";
                break;
            case 'verified':
                $where_clause = "WHERE verification_status = 'verified'";
                break;
            case 'unverified':
                $where_clause = "WHERE verification_status != 'verified'";
                break;
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, domain_url, domain_name, status, verification_status 
             FROM {$domains_table} 
             {$where_clause}
             ORDER BY created_at DESC 
             LIMIT %d",
            $limit
        ), ARRAY_A);
    }

    /**
     * Start export
     */
    private function start_export($export_type, $format, $date_range, $start_date = '', $end_date = '') {
        $export_options = [
            'format' => $format,
            'date_range' => $date_range,
            'start_date' => $start_date,
            'end_date' => $end_date
        ];

        return $this->start_bulk_operation('export', $export_type, [], $export_options);
    }

    /**
     * Preview import
     */
    private function preview_import($file, $import_type) {
        $file_content = file_get_contents($file['tmp_name']);
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        
        // Parse file based on extension
        switch (strtolower($file_extension)) {
            case 'csv':
                $data = $this->parse_csv($file_content);
                break;
            case 'json':
                $data = json_decode($file_content, true);
                break;
            case 'xml':
                $data = $this->parse_xml($file_content);
                break;
            default:
                return new WP_Error('unsupported_format', __('Unsupported file format.', 'affiliate-cross-domain'));
        }
        
        if (empty($data)) {
            return new WP_Error('empty_file', __('File appears to be empty or invalid.', 'affiliate-cross-domain'));
        }
        
        // Validate data structure
        $validation_result = $this->validate_import_data($data, $import_type);
        
        return [
            'preview' => array_slice($data, 0, 10), // First 10 rows for preview
            'total_rows' => count($data),
            'validation' => $validation_result
        ];
    }

    /**
     * Parse CSV content
     */
    private function parse_csv($content) {
        $lines = explode("\n", trim($content));
        $headers = str_getcsv(array_shift($lines));
        $data = [];
        
        foreach ($lines as $line) {
            if (trim($line)) {
                $row = str_getcsv($line);
                $data[] = array_combine($headers, $row);
            }
        }
        
        return $data;
    }

    /**
     * Parse XML content
     */
    private function parse_xml($content) {
        $xml = simplexml_load_string($content);
        
        if ($xml === false) {
            return false;
        }
        
        return json_decode(json_encode($xml), true);
    }

    /**
     * Validate import data
     */
    private function validate_import_data($data, $import_type) {
        $errors = [];
        $warnings = [];
        
        switch ($import_type) {
            case 'vanity_codes':
                $required_fields = ['code', 'affiliate_id'];
                break;
            case 'domains':
                $required_fields = ['domain_url'];
                break;
            default:
                return ['errors' => [__('Invalid import type.', 'affiliate-cross-domain')]];
        }
        
        // Check required fields
        $first_row = reset($data);
        $missing_fields = array_diff($required_fields, array_keys($first_row));
        
        if (!empty($missing_fields)) {
            $errors[] = sprintf(__('Missing required fields: %s', 'affiliate-cross-domain'), implode(', ', $missing_fields));
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Start import
     */
    private function start_import($import_data, $import_type, $import_mode) {
        $import_options = [
            'import_mode' => $import_mode,
            'import_type' => $import_type
        ];

        return $this->start_bulk_operation('import', $import_type, $import_data, $import_options);
    }

    /**
     * Maybe create operations log table
     */
    private function maybe_create_operations_log_table() {
        global $wpdb;
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->operations_log_table}'") !== $this->operations_log_table) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE {$this->operations_log_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                operation_type varchar(50) NOT NULL,
                operation_name varchar(100) NOT NULL,
                total_items int unsigned NOT NULL DEFAULT 0,
                processed_items int unsigned NOT NULL DEFAULT 0,
                progress_percentage decimal(5,2) NOT NULL DEFAULT 0.00,
                items_data longtext,
                options_data longtext,
                status enum('pending','running','completed','failed','cancelled','rolled_back','rollback_failed') NOT NULL DEFAULT 'pending',
                errors_data longtext,
                rollback_data longtext,
                rollback_errors longtext,
                can_rollback tinyint(1) NOT NULL DEFAULT 0,
                user_id bigint(20) unsigned NOT NULL,
                started_at datetime NOT NULL,
                completed_at datetime DEFAULT NULL,
                rolled_back_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_status (status),
                KEY idx_user_id (user_id),
                KEY idx_started_at (started_at),
                KEY idx_operation_type (operation_type),
                KEY idx_can_rollback (can_rollback)
            ) {$charset_collate};";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * Cleanup old operations
     */
    public function cleanup_old_operations($days_old = 30) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->operations_log_table} 
             WHERE completed_at < %s AND status IN ('completed', 'failed', 'cancelled')",
            $cutoff_date
        ));
    }
}