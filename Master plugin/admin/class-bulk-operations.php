<?php
/**
 * Bulk Operations for Affiliate Cross Domain System
 * 
 * Plugin: Affiliate Cross Domain System (Master)
 * File: /wp-content/plugins/affiliate-cross-domain-system/admin/class-bulk-operations.php
 * 
 * Handles bulk operations for vanity codes, domains, and analytics
 * with comprehensive validation, progress tracking, and rollback capabilities.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Bulk_Operations {

    private $vanity_manager;
    private $security_manager;
    private $operations_log_table;
    private $batch_size = 50;
    private $max_execution_time = 120; // 2 minutes

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->vanity_manager = new AFFCD_Vanity_Code_Manager();
        $this->security_manager = new AFFCD_Security_Manager();
        $this->operations_log_table = $wpdb->prefix . 'affcd_bulk_operations_log';
        
        // Initialize hooks
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_ajax_affcd_bulk_operation', [$this, 'ajax_bulk_operation']);
        add_action('wp_ajax_affcd_bulk_progress', [$this, 'ajax_bulk_progress']);
        add_action('wp_ajax_affcd_bulk_cancel', [$this, 'ajax_bulk_cancel']);
        add_action('wp_ajax_affcd_bulk_rollback', [$this, 'ajax_bulk_rollback']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Background processing
        add_action('affcd_process_bulk_operation', [$this, 'process_bulk_operation_background'], 10, 2);
        
        // Cleanup old operations
        add_action('affcd_cleanup_bulk_operations', [$this, 'cleanup_old_operations']);
        
        // Schedule cleanup
        if (!wp_next_scheduled('affcd_cleanup_bulk_operations')) {
            wp_schedule_event(time(), 'daily', 'affcd_cleanup_bulk_operations');
        }
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
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'affcd-bulk-operations') === false) {
            return;
        }

        wp_enqueue_script(
            'affcd-bulk-operations',
            AFFCD_PLUGIN_URL . 'admin/js/bulk-operations.js',
            ['jquery', 'wp-util'],
            AFFCD_VERSION,
            true
        );

        wp_enqueue_style(
            'affcd-bulk-operations',
            AFFCD_PLUGIN_URL . 'admin/css/bulk-operations.css',
            [],
            AFFCD_VERSION
        );

                wp_localize_script('affcd-bulk-operations', 'affcdBulk', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('affcd_bulk_nonce'),
            'batchSize' => $this->batch_size,
            'i18n' => [
                'processing' => __('Processing...', 'affiliate-cross-domain'),
                'completed' => __('Operation completed successfully', 'affiliate-cross-domain'),
                'error' => __('An error occurred', 'affiliate-cross-domain'),
                'cancelled' => __('Operation cancelled', 'affiliate-cross-domain'),
                'confirmCancel' => __('Are you sure you want to cancel this operation?', 'affiliate-cross-domain'),
                'confirmRollback' => __('Are you sure you want to rollback this operation? This cannot be undone.', 'affiliate-cross-domain'),
                'selectItems' => __('Please select items to process', 'affiliate-cross-domain'),
                'selectOperation' => __('Please select an operation', 'affiliate-cross-domain')
            ]
        ]);
    }

    /**
     * Render bulk operations page
     */
    public function render_bulk_operations_page() {
        $current_tab = $_GET['tab'] ?? 'vanity-codes';
        ?>
        <div class="wrap affcd-bulk-operations">
            <h1><?php _e('Bulk Operations', 'affiliate-cross-domain'); ?></h1>

            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper">
                <a href="?page=affcd-bulk-operations&tab=vanity-codes" 
                   class="nav-tab <?php echo $current_tab === 'vanity-codes' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Vanity Codes', 'affiliate-cross-domain'); ?>
                </a>
                <a href="?page=affcd-bulk-operations&tab=domains" 
                   class="nav-tab <?php echo $current_tab === 'domains' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Domains', 'affiliate-cross-domain'); ?>
                </a>
                <a href="?page=affcd-bulk-operations&tab=import-export" 
                   class="nav-tab <?php echo $current_tab === 'import-export' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Import/Export', 'affiliate-cross-domain'); ?>
                </a>
                <a href="?page=affcd-bulk-operations&tab=operations-log" 
                   class="nav-tab <?php echo $current_tab === 'operations-log' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Operations Log', 'affiliate-cross-domain'); ?>
                </a>
            </nav>

            <!-- Progress Bar -->
            <div id="affcd-bulk-progress" class="affcd-progress-container" style="display: none;">
                <div class="affcd-progress-header">
                    <h3 id="affcd-progress-title"><?php _e('Processing...', 'affiliate-cross-domain'); ?></h3>
                    <button type="button" id="affcd-cancel-operation" class="button"><?php _e('Cancel', 'affiliate-cross-domain'); ?></button>
                </div>
                <div class="affcd-progress-bar">
                    <div class="affcd-progress-fill" style="width: 0%;"></div>
                </div>
                <div class="affcd-progress-info">
                    <span id="affcd-progress-text">0 / 0</span>
                    <span id="affcd-progress-percentage">0%</span>
                </div>
                <div id="affcd-progress-log" class="affcd-progress-log"></div>
            </div>

            <!-- Tab Content -->
            <div class="tab-content">
                <?php
                switch ($current_tab) {
                    case 'vanity-codes':
                        $this->render_vanity_codes_tab();
                        break;
                    case 'domains':
                        $this->render_domains_tab();
                        break;
                    case 'import-export':
                        $this->render_import_export_tab();
                        break;
                    case 'operations-log':
                        $this->render_operations_log_tab();
                        break;
                }
                ?>
            </div>
        </div>

        <style>
        .affcd-bulk-operations {
            margin: 20px 20px 0 2px;
        }
        .affcd-progress-container {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        .affcd-progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .affcd-progress-bar {
            width: 100%;
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .affcd-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #0073aa, #005177);
            transition: width 0.3s ease;
        }
        .affcd-progress-info {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #666;
        }
        .affcd-progress-log {
            max-height: 200px;
            overflow-y: auto;
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 10px;
            margin-top: 15px;
            font-family: monospace;
            font-size: 12px;
        }
        .affcd-operation-section {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        .affcd-operation-controls {
            display: flex;
            gap: 15px;
            align-items: end;
            margin-bottom: 20px;
        }
        .affcd-operation-controls .form-group {
            flex: 1;
            min-width: 200px;
        }
        .affcd-operation-controls label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .affcd-bulk-list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            background: #fafafa;
        }
        .affcd-bulk-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .affcd-bulk-item:last-child {
            border-bottom: none;
        }
        .affcd-bulk-item input[type="checkbox"] {
            margin-right: 10px;
        }
        .affcd-bulk-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .affcd-stat-box {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            text-align: center;
        }
        .affcd-stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
        .affcd-stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        </style>
        <?php
    }

    /**
     * Render vanity codes tab
     */
    private function render_vanity_codes_tab() {
        $vanity_codes = $this->get_vanity_codes_for_bulk();
        ?>
        <div class="vanity-codes-tab">
            <div class="affcd-operation-section">
                <h3><?php _e('Bulk Vanity Code Operations', 'affiliate-cross-domain'); ?></h3>
                
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
                    <button type="button" id="execute-vanity-operation" class="button button-primary" disabled>
                        <?php _e('Execute Operation', 'affiliate-cross-domain'); ?>
                    </button>
                </div>

                <div class="affcd-bulk-list" id="vanity-codes-list">
                    <div class="affcd-bulk-item">
                        <input type="checkbox" id="select-all-vanity" />
                        <label for="select-all-vanity"><strong><?php _e('Select All', 'affiliate-cross-domain'); ?></strong></label>
                    </div>
                    <?php foreach ($vanity_codes as $code): ?>
                        <div class="affcd-bulk-item">
                            <input type="checkbox" name="vanity_codes[]" value="<?php echo esc_attr($code->id); ?>" id="vanity-<?php echo esc_attr($code->id); ?>">
                            <label for="vanity-<?php echo esc_attr($code->id); ?>">
                                <strong><?php echo esc_html($code->vanity_code); ?></strong>
                                - <?php echo esc_html($code->status); ?>
                                (<?php echo number_format($code->usage_count); ?> uses, 
                                 <?php echo number_format($code->conversion_count); ?> conversions)
                                <?php if ($code->expires_at): ?>
                                    - Expires: <?php echo date('Y-m-d H:i', strtotime($code->expires_at)); ?>
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="affcd-bulk-stats">
                    <div class="affcd-stat-box">
                        <div class="affcd-stat-value" id="vanity-total-count"><?php echo count($vanity_codes); ?></div>
                        <div class="affcd-stat-label"><?php _e('Total Codes', 'affiliate-cross-domain'); ?></div>
                    </div>
                    <div class="affcd-stat-box">
                        <div class="affcd-stat-value" id="vanity-selected-count">0</div>
                        <div class="affcd-stat-label"><?php _e('Selected', 'affiliate-cross-domain'); ?></div>
                    </div>
                    <div class="affcd-stat-box">
                        <div class="affcd-stat-value" id="vanity-active-count">
                            <?php echo $this->count_vanity_codes_by_status('active'); ?>
                        </div>
                        <div class="affcd-stat-label"><?php _e('Active', 'affiliate-cross-domain'); ?></div>
                    </div>
                    <div class="affcd-stat-box">
                        <div class="affcd-stat-value" id="vanity-expired-count">
                            <?php echo $this->count_expired_vanity_codes(); ?>
                        </div>
                        <div class="affcd-stat-label"><?php _e('Expired', 'affiliate-cross-domain'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render domains tab
     */
    private function render_domains_tab() {
        $domains = $this->get_authorized_domains();
        ?>
        <div class="domains-tab">
            <div class="affcd-operation-section">
                <h3><?php _e('Bulk Domain Operations', 'affiliate-cross-domain'); ?></h3>
                
                <div class="affcd-operation-controls">
                    <div class="form-group">
                        <label for="domain-operation"><?php _e('Operation', 'affiliate-cross-domain'); ?></label>
                        <select id="domain-operation" name="operation">
                            <option value=""><?php _e('Select Operation', 'affiliate-cross-domain'); ?></option>
                            <option value="verify_all"><?php _e('Verify All Domains', 'affiliate-cross-domain'); ?></option>
                            <option value="regenerate_keys"><?php _e('Regenerate API Keys', 'affiliate-cross-domain'); ?></option>
                            <option value="suspend"><?php _e('Suspend Domains', 'affiliate-cross-domain'); ?></option>
                            <option value="reactivate"><?php _e('Reactivate Domains', 'affiliate-cross-domain'); ?></option>
                            <option value="remove"><?php _e('Remove Domains', 'affiliate-cross-domain'); ?></option>
                            <option value="export_stats"><?php _e('Export Statistics', 'affiliate-cross-domain'); ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="domain-filter"><?php _e('Filter', 'affiliate-cross-domain'); ?></label>
                        <select id="domain-filter" name="filter">
                            <option value="all"><?php _e('All Domains', 'affiliate-cross-domain'); ?></option>
                            <option value="active"><?php _e('Active Only', 'affiliate-cross-domain'); ?></option>
                            <option value="inactive"><?php _e('Inactive Only', 'affiliate-cross-domain'); ?></option>
                            <option value="suspended"><?php _e('Suspended Only', 'affiliate-cross-domain'); ?></option>
                            <option value="unverified"><?php _e('Unverified Only', 'affiliate-cross-domain'); ?></option>
                        </select>
                    </div>
                    
                    <button type="button" id="load-domains" class="button"><?php _e('Load Domains', 'affiliate-cross-domain'); ?></button>
                    <button type="button" id="execute-domain-operation" class="button button-primary" disabled>
                        <?php _e('Execute Operation', 'affiliate-cross-domain'); ?>
                    </button>
                </div>

                <div class="affcd-bulk-list" id="domains-list">
                    <div class="affcd-bulk-item">
                        <input type="checkbox" id="select-all-domains" />
                        <label for="select-all-domains"><strong><?php _e('Select All', 'affiliate-cross-domain'); ?></strong></label>
                    </div>
                    <?php foreach ($domains as $domain): ?>
                        <div class="affcd-bulk-item">
                            <input type="checkbox" name="domains[]" value="<?php echo esc_attr($domain->id); ?>" id="domain-<?php echo esc_attr($domain->id); ?>">
                            <label for="domain-<?php echo esc_attr($domain->id); ?>">
                                <strong><?php echo esc_html($domain->domain); ?></strong>
                                - <?php echo esc_html($domain->status); ?>
                                (<?php echo number_format($domain->total_requests); ?> requests, 
                                 <?php echo number_format($domain->blocked_requests); ?> blocked)
                                <?php if ($domain->last_verified): ?>
                                    - Last verified: <?php echo human_time_diff(strtotime($domain->last_verified)); ?> ago
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="affcd-bulk-stats">
                    <div class="affcd-stat-box">
                        <div class="affcd-stat-value"><?php echo count($domains); ?></div>
                        <div class="affcd-stat-label"><?php _e('Total Domains', 'affiliate-cross-domain'); ?></div>
                    </div>
                    <div class="affcd-stat-box">
                        <div class="affcd-stat-value" id="domain-selected-count">0</div>
                        <div class="affcd-stat-label"><?php _e('Selected', 'affiliate-cross-domain'); ?></div>
                    </div>
                    <div class="affcd-stat-box">
                        <div class="affcd-stat-value"><?php echo $this->count_domains_by_status('active'); ?></div>
                        <div class="affcd-stat-label"><?php _e('Active', 'affiliate-cross-domain'); ?></div>
                    </div>
                    <div class="affcd-stat-box">
                        <div class="affcd-stat-value"><?php echo $this->count_unverified_domains(); ?></div>
                        <div class="affcd-stat-label"><?php _e('Unverified', 'affiliate-cross-domain'); ?></div>
                    </div>
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
                            <option value="usage_data"><?php _e('Usage Data', 'affiliate-cross-domain'); ?></option>
                            <option value="domains"><?php _e('Authorized Domains', 'affiliate-cross-domain'); ?></option>
                            <option value="analytics"><?php _e('Analytics Data', 'affiliate-cross-domain'); ?></option>
                            <option value="security_logs"><?php _e('Security Logs', 'affiliate-cross-domain'); ?></option>
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
                            <option value="7d"><?php _e('Last 7 Days', 'affiliate-cross-domain'); ?></option>
                            <option value="30d"><?php _e('Last 30 Days', 'affiliate-cross-domain'); ?></option>
                            <option value="90d"><?php _e('Last 90 Days', 'affiliate-cross-domain'); ?></option>
                            <option value="custom"><?php _e('Custom Range', 'affiliate-cross-domain'); ?></option>
                        </select>
                    </div>
                    
                    <button type="button" id="execute-export" class="button button-primary">
                        <?php _e('Export Data', 'affiliate-cross-domain'); ?>
                    </button>
                </div>

                <div id="custom-date-range" style="display: none; margin-top: 15px;">
                    <div class="affcd-operation-controls">
                        <div class="form-group">
                            <label for="export-start-date"><?php _e('Start Date', 'affiliate-cross-domain'); ?></label>
                            <input type="date" id="export-start-date" name="start_date">
                        </div>
                        <div class="form-group">
                            <label for="export-end-date"><?php _e('End Date', 'affiliate-cross-domain'); ?></label>
                            <input type="date" id="export-end-date" name="end_date">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Import Section -->
            <div class="affcd-operation-section">
                <h3><?php _e('Import Data', 'affiliate-cross-domain'); ?></h3>
                
                <form id="import-form" enctype="multipart/form-data">
                    <div class="affcd-operation-controls">
                        <div class="form-group">
                            <label for="import-type"><?php _e('Data Type', 'affiliate-cross-domain'); ?></label>
                            <select id="import-type" name="import_type">
                                <option value="vanity_codes"><?php _e('Vanity Codes', 'affiliate-cross-domain'); ?></option>
                                <option value="domains"><?php _e('Authorized Domains', 'affiliate-cross-domain'); ?></option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="import-file"><?php _e('File', 'affiliate-cross-domain'); ?></label>
                            <input type="file" id="import-file" name="import_file" accept=".csv,.json,.xml" required>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="import-update-existing" name="update_existing" value="1">
                                <?php _e('Update existing records', 'affiliate-cross-domain'); ?>
                            </label>
                        </div>
                        
                        <button type="button" id="execute-import" class="button button-primary">
                            <?php _e('Import Data', 'affiliate-cross-domain'); ?>
                        </button>
                    </div>
                </form>

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
                
                <div class="affcd-operation-controls">
                    <div class="form-group">
                        <label for="log-filter"><?php _e('Filter by Status', 'affiliate-cross-domain'); ?></label>
                        <select id="log-filter" name="filter">
                            <option value="all"><?php _e('All Operations', 'affiliate-cross-domain'); ?></option>
                            <option value="completed"><?php _e('Completed', 'affiliate-cross-domain'); ?></option>
                            <option value="failed"><?php _e('Failed', 'affiliate-cross-domain'); ?></option>
                            <option value="cancelled"><?php _e('Cancelled', 'affiliate-cross-domain'); ?></option>
                            <option value="running"><?php _e('Running', 'affiliate-cross-domain'); ?></option>
                        </select>
                    </div>
                    
                    <button type="button" id="refresh-log" class="button"><?php _e('Refresh', 'affiliate-cross-domain'); ?></button>
                    <button type="button" id="clear-old-logs" class="button"><?php _e('Clear Old Logs', 'affiliate-cross-domain'); ?></button>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Operation', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Type', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Items', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Progress', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Status', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Started', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Duration', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Actions', 'affiliate-cross-domain'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($operations as $operation): ?>
                            <tr data-operation-id="<?php echo esc_attr($operation->id); ?>">
                                <td>
                                    <strong><?php echo esc_html($operation->operation_name); ?></strong>
                                    <?php if ($operation->user_id): ?>
                                        <?php $user = get_userdata($operation->user_id); ?>
                                        <br><small>by <?php echo esc_html($user ? $user->display_name : 'Unknown'); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($operation->operation_type); ?></td>
                                <td>
                                    <?php echo number_format($operation->processed_items); ?> / 
                                    <?php echo number_format($operation->total_items); ?>
                                </td>
                                <td>
                                    <div class="progress-bar-small">
                                        <div class="progress-fill-small" style="width: <?php echo esc_attr($operation->progress_percentage); ?>%;"></div>
                                    </div>
                                    <small><?php echo esc_html($operation->progress_percentage); ?>%</small>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($operation->status); ?>">
                                        <?php echo esc_html(ucfirst($operation->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo human_time_diff(strtotime($operation->started_at)); ?> <?php _e('ago', 'affiliate-cross-domain'); ?>
                                </td>
                                <td>
                                    <?php if ($operation->completed_at): ?>
                                        <?php 
                                        $duration = strtotime($operation->completed_at) - strtotime($operation->started_at);
                                        echo gmdate('H:i:s', $duration);
                                        ?>
                                    <?php elseif ($operation->status === 'running'): ?>
                                        <em><?php _e('Running...', 'affiliate-cross-domain'); ?></em>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($operation->status === 'completed' && $operation->can_rollback): ?>
                                        <button type="button" class="button button-small rollback-operation" 
                                                data-operation-id="<?php echo esc_attr($operation->id); ?>">
                                            <?php _e('Rollback', 'affiliate-cross-domain'); ?>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($operation->status === 'running'): ?>
                                        <button type="button" class="button button-small cancel-operation" 
                                                data-operation-id="<?php echo esc_attr($operation->id); ?>">
                                            <?php _e('Cancel', 'affiliate-cross-domain'); ?>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="button button-small view-details" 
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
        </style>
        <?php
    }

    /**
     * Get vanity codes for bulk operations
     */
    private function get_vanity_codes_for_bulk($filter = 'all', $limit = 100) {
        global $wpdb;
        
        $vanity_table = $wpdb->prefix . 'affcd_vanity_codes';
        
        $where_clause = '1=1';
        switch ($filter) {
            case 'active':
                $where_clause = "status = 'active'";
                break;
            case 'inactive':
                $where_clause = "status = 'inactive'";
                break;
            case 'expired':
                $where_clause = "expires_at IS NOT NULL AND expires_at < NOW()";
                break;
            case 'unused':
                $where_clause = "usage_count = 0";
                break;
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, vanity_code, status, usage_count, conversion_count, expires_at 
             FROM {$vanity_table} 
             WHERE {$where_clause} 
             ORDER BY created_at DESC 
             LIMIT %d",
            $limit
        ));
    }

    /**
     * Get authorized domains
     */
    private function get_authorized_domains($filter = 'all', $limit = 100) {
        global $wpdb;
        
        $domains_table = $wpdb->prefix . 'affcd_authorized_domains';
        
        $where_clause = '1=1';
        switch ($filter) {
            case 'active':
                $where_clause = "status = 'active'";
                break;
            case 'inactive':
                $where_clause = "status = 'inactive'";
                break;
            case 'suspended':
                $where_clause = "status = 'suspended'";
                break;
            case 'unverified':
                $where_clause = "last_verified IS NULL OR verification_failures > 0";
                break;
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, domain, status, total_requests, blocked_requests, last_verified 
             FROM {$domains_table} 
             WHERE {$where_clause} 
             ORDER BY created_at DESC 
             LIMIT %d",
            $limit
        ));
    }

    /**
     * Get recent operations
     */
    private function get_recent_operations($limit = 50) {
        global $wpdb;
        
        $this->maybe_create_operations_log_table();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->operations_log_table} 
             ORDER BY started_at DESC 
             LIMIT %d",
            $limit
        ));
    }

    /**
     * Count vanity codes by status
     */
    private function count_vanity_codes_by_status($status) {
        global $wpdb;
        
        $vanity_table = $wpdb->prefix . 'affcd_vanity_codes';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$vanity_table} WHERE status = %s",
            $status
        ));
    }

    /**
     * Count expired vanity codes
     */
    private function count_expired_vanity_codes() {
        global $wpdb;
        
        $vanity_table = $wpdb->prefix . 'affcd_vanity_codes';
        
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$vanity_table} 
             WHERE expires_at IS NOT NULL AND expires_at < NOW()"
        );
    }

    /**
     * Count domains by status
     */
    private function count_domains_by_status($status) {
        global $wpdb;
        
        $domains_table = $wpdb->prefix . 'affcd_authorized_domains';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$domains_table} WHERE status = %s",
            $status
        ));
    }

    /**
     * Count unverified domains
     */
    private function count_unverified_domains() {
        global $wpdb;
        
        $domains_table = $wpdb->prefix . 'affcd_authorized_domains';
        
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$domains_table} 
             WHERE last_verified IS NULL OR verification_failures > 0"
        );
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
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $recent_files = array_slice($files, 0, 10);
        
        if (empty($recent_files)) {
            echo '<p>' . __('No downloads available.', 'affiliate-cross-domain') . '</p>';
            return;
        }
        
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
     * AJAX: Execute bulk operation
     */
    public function ajax_bulk_operation() {
        check_ajax_referer('affcd_bulk_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_die(__('Insufficient permissions.', 'affiliate-cross-domain'));
        }

        $operation_type = sanitize_text_field($_POST['operation_type'] ?? '');
        $operation = sanitize_text_field($_POST['operation'] ?? '');
        $items = array_map('absint', $_POST['items'] ?? []);
        $options = array_map('sanitize_text_field', $_POST['options'] ?? []);

        if (empty($operation_type) || empty($operation) || empty($items)) {
            wp_send_json_error(__('Invalid operation parameters.', 'affiliate-cross-domain'));
        }

        // Create operation log entry
        $operation_id = $this->create_operation_log($operation_type, $operation, $items, $options);
        
        if (!$operation_id) {
            wp_send_json_error(__('Failed to create operation log.', 'affiliate-cross-domain'));
        }

        // Start background processing
        wp_schedule_single_event(time(), 'affcd_process_bulk_operation', [$operation_id, $operation_type]);

        wp_send_json_success([
            'operation_id' => $operation_id,
            'message' => __('Operation started successfully.', 'affiliate-cross-domain')
        ]);
    }

    /**
     * AJAX: Get operation progress
     */
    public function ajax_bulk_progress() {
        check_ajax_referer('affcd_bulk_nonce', 'nonce');
        
        $operation_id = absint($_POST['operation_id'] ?? 0);
        
        if (!$operation_id) {
            wp_send_json_error(__('Invalid operation ID.', 'affiliate-cross-domain'));
        }

        $progress = $this->get_operation_progress($operation_id);
        
        if (!$progress) {
            wp_send_json_error(__('Operation not found.', 'affiliate-cross-domain'));
        }

        wp_send_json_success($progress);
    }

    /**
     * AJAX: Cancel operation
     */
    public function ajax_bulk_cancel() {
        check_ajax_referer('affcd_bulk_nonce', 'nonce');
        
        $operation_id = absint($_POST['operation_id'] ?? 0);
        
        if (!$operation_id) {
            wp_send_json_error(__('Invalid operation ID.', 'affiliate-cross-domain'));
        }

        $result = $this->cancel_operation($operation_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Operation cancelled successfully.', 'affiliate-cross-domain'));
    }

    /**
     * AJAX: Rollback operation
     */
    public function ajax_bulk_rollback() {
        check_ajax_referer('affcd_bulk_nonce', 'nonce');
        
        $operation_id = absint($_POST['operation_id'] ?? 0);
        
        if (!$operation_id) {
            wp_send_json_error(__('Invalid operation ID.', 'affiliate-cross-domain'));
        }

        $result = $this->rollback_operation($operation_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Operation rolled back successfully.', 'affiliate-cross-domain'));
    }

    /**
     * Create operation log entry
     */
    private function create_operation_log($operation_type, $operation, $items, $options = []) {
        global $wpdb;
        
        $this->maybe_create_operations_log_table();
        
        $operation_data = [
            'operation_type' => $operation_type,
            'operation_name' => $operation,
            'total_items' => count($items),
            'items_data' => json_encode($items),
            'options_data' => json_encode($options),
            'status' => 'pending',
            'user_id' => get_current_user_id(),
            'started_at' => current_time('mysql')
        ];

        $result = $wpdb->insert($this->operations_log_table, $operation_data, [
            '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s'
        ]);

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Process bulk operation in background
     */
    public function process_bulk_operation_background($operation_id, $operation_type) {
        global $wpdb;
        
        // Set longer execution time
        ini_set('max_execution_time', $this->max_execution_time);
        
        $operation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->operations_log_table} WHERE id = %d",
            $operation_id
        ));

        if (!$operation) {
            return;
        }

        // Update status to running
        $this->update_operation_status($operation_id, 'running');
        
        $items = json_decode($operation->items_data, true);
        $options = json_decode($operation->options_data, true);
        $processed = 0;
        $errors = [];
        $rollback_data = [];

        try {
            foreach ($items as $item_id) {
                // Check if operation was cancelled
                if ($this->is_operation_cancelled($operation_id)) {
                    $this->update_operation_status($operation_id, 'cancelled');
                    return;
                }

                $result = $this->process_single_item($operation_type, $operation->operation_name, $item_id, $options);
                
                if (is_wp_error($result)) {
                    $errors[] = [
                        'item_id' => $item_id,
                        'error' => $result->get_error_message()
                    ];
                } else {
                    // Store rollback data
                    if ($result && isset($result['rollback_data'])) {
                        $rollback_data[$item_id] = $result['rollback_data'];
                    }
                }

                $processed++;
                
                // Update progress
                $this->update_operation_progress($operation_id, $processed, count($items));
                
                // Small delay to prevent overwhelming the server
                usleep(10000); // 10ms
            }

            // Mark as completed
            $this->complete_operation($operation_id, $errors, $rollback_data);
            
        } catch (Exception $e) {
            $this->fail_operation($operation_id, $e->getMessage());
        }
    }

    /**
     * Process single item
     */
    private function process_single_item($operation_type, $operation, $item_id, $options) {
        switch ($operation_type) {
            case 'vanity_codes':
                return $this->process_vanity_code_operation($operation, $item_id, $options);
            case 'domains':
                return $this->process_domain_operation($operation, $item_id, $options);
            default:
                return new WP_Error('invalid_type', 'Invalid operation type');
        }
    }

    /**
     * Process vanity code operation
     */
    private function process_vanity_code_operation($operation, $code_id, $options) {
        global $wpdb;
        
        $vanity_table = $wpdb->prefix . 'affcd_vanity_codes';
        
        // Get current data for rollback
        $current_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$vanity_table} WHERE id = %d",
            $code_id
        ), ARRAY_A);

        if (!$current_data) {
            return new WP_Error('not_found', 'Vanity code not found');
        }

        switch ($operation) {
            case 'activate':
                $result = $wpdb->update(
                    $vanity_table,
                    ['status' => 'active'],
                    ['id' => $code_id],
                    ['%s'],
                    ['%d']
                );
                break;
                
            case 'deactivate':
                $result = $wpdb->update(
                    $vanity_table,
                    ['status' => 'inactive'],
                    ['id' => $code_id],
                    ['%s'],
                    ['%d']
                );
                break;
                
            case 'delete':
                $result = $wpdb->delete(
                    $vanity_table,
                    ['id' => $code_id],
                    ['%d']
                );
                break;
                
            case 'update_expiry':
                if (empty($options['expiry_date'])) {
                    return new WP_Error('missing_date', 'Expiry date is required');
                }
                
                $result = $wpdb->update(
                    $vanity_table,
                    ['expires_at' => $options['expiry_date']],
                    ['id' => $code_id],
                    ['%s'],
                    ['%d']
                );
                break;
                
            case 'reset_stats':
                $result = $wpdb->update(
                    $vanity_table,
                    [
                        'usage_count' => 0,
                        'conversion_count' => 0,
                        'revenue_generated' => 0
                    ],
                    ['id' => $code_id],
                    ['%d', '%d', '%f'],
                    ['%d']
                );
                break;
                
            default:
                return new WP_Error('invalid_operation', 'Invalid operation');
        }

        if ($result === false) {
            return new WP_Error('db_error', 'Database operation failed');
        }

        return [
            'success' => true,
            'rollback_data' => $current_data
        ];
    }

    /**
     * Process domain operation
     */
    private function process_domain_operation($operation, $domain_id, $options) {
        global $wpdb;
        
        $domains_table = $wpdb->prefix . 'affcd_authorized_domains';
        
        // Get current data for rollback
        $current_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$domains_table} WHERE id = %d",
            $domain_id
        ), ARRAY_A);

        if (!$current_data) {
            return new WP_Error('not_found', 'Domain not found');
        }

        switch ($operation) {
            case 'verify_all':
                // Implement domain verification logic
                $verification_result = $this->verify_domain($current_data['domain']);
                
                $result = $wpdb->update(
                    $domains_table,
                    [
                        'last_verified' => current_time('mysql'),
                        'verification_failures' => $verification_result ? 0 : 1
                    ],
                    ['id' => $domain_id],
                    ['%s', '%d'],
                    ['%d']
                );
                break;
                
            case 'regenerate_keys':
                $new_api_key = $this->generate_api_key();
                
                $result = $wpdb->update(
                    $domains_table,
                    ['api_key' => $new_api_key],
                    ['id' => $domain_id],
                    ['%s'],
                    ['%d']
                );
                break;
                
            case 'suspend':
                $result = $wpdb->update(
                    $domains_table,
                    ['status' => 'suspended'],
                    ['id' => $domain_id],
                    ['%s'],
                    ['%d']
                );
                break;
                
            case 'reactivate':
                $result = $wpdb->update(
                    $domains_table,
                    ['status' => 'active'],
                    ['id' => $domain_id],
                    ['%s'],
                    ['%d']
                );
                break;
                
            case 'remove':
                $result = $wpdb->delete(
                    $domains_table,
                    ['id' => $domain_id],
                    ['%d']
                );
                break;
                
            default:
                return new WP_Error('invalid_operation', 'Invalid operation');
        }

        if ($result === false) {
            return new WP_Error('db_error', 'Database operation failed');
        }

        return [
            'success' => true,
            'rollback_data' => $current_data
        ];
    }

    /**
     * Maybe create operations log table
     */
    private function maybe_create_operations_log_table() {
        global $wpdb;
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->operations_log_table}'") === $this->operations_log_table) {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->operations_log_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            operation_type varchar(50) NOT NULL,
            operation_name varchar(100) NOT NULL,
            total_items int unsigned NOT NULL,
            processed_items int unsigned DEFAULT 0,
            progress_percentage decimal(5,2) DEFAULT 0.00,
            items_data longtext NOT NULL,
            options_data longtext,
            rollback_data longtext,
            errors_data longtext,
            status enum('pending','running','completed','failed','cancelled') DEFAULT 'pending',
            can_rollback tinyint(1) DEFAULT 0,
            user_id bigint(20) unsigned,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY user_id (user_id),
            KEY started_at (started_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Update operation status
     */
    private function update_operation_status($operation_id, $status) {
        global $wpdb;
        
        $update_data = ['status' => $status];
        
        if ($status === 'completed' || $status === 'failed' || $status === 'cancelled') {
            $update_data['completed_at'] = current_time('mysql');
        }
        
        $wpdb->update(
            $this->operations_log_table,
            $update_data,
            ['id' => $operation_id],
            array_fill(0, count($update_data), '%s'),
            ['%d']
        );
    }

    /**
     * Update operation progress
     */
    private function update_operation_progress($operation_id, $processed, $total) {
        global $wpdb;
        
        $percentage = $total > 0 ? round(($processed / $total) * 100, 2) : 0;
        
        $wpdb->update(
            $this->operations_log_table,
            [
                'processed_items' => $processed,
                'progress_percentage' => $percentage
            ],
            ['id' => $operation_id],
            ['%d', '%f'],
            ['%d']
        );
    }

    /**
     * Get operation progress
     */
    private function get_operation_progress($operation_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT processed_items, total_items, progress_percentage, status 
             FROM {$this->operations_log_table} 
             WHERE id = %d",
            $operation_id
        ), ARRAY_A);
    }

    /**
     * Complete operation
     */
    private function complete_operation($operation_id, $errors, $rollback_data) {
        global $wpdb;
        
        $can_rollback = !empty($rollback_data) && empty($errors);
        
        $wpdb->update(
            $this->operations_log_table,
            [
                'status' => empty($errors) ? 'completed' : 'failed',
                'errors_data' => json_encode($errors),
                'rollback_data' => json_encode($rollback_data),
                'can_rollback' => $can_rollback ? 1 : 0,
                'completed_at' => current_time('mysql')
            ],
            ['id' => $operation_id],
            ['%s', '%s', '%s', '%d', '%s'],
            ['%d']
        );
    }

    /**
     * Fail operation
     */
    private function fail_operation($operation_id, $error_message) {
        global $wpdb;
        
        $wpdb->update(
            $this->operations_log_table,
            [
                'status' => 'failed',
                'errors_data' => json_encode([['error' => $error_message]]),
                'completed_at' => current_time('mysql')
            ],
            ['id' => $operation_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }

  

    /**
     * Cancel operation
     */
    private function cancel_operation($operation_id) {
        global $wpdb;
        
        $operation = $wpdb->get_row($wpdb->prepare(
            "SELECT status FROM {$this->operations_log_table} WHERE id = %d",
            $operation_id
        ));

        if (!$operation) {
            return new WP_Error('not_found', 'Operation not found');
        }

        if ($operation->status !== 'running' && $operation->status !== 'pending') {
            return new WP_Error('invalid_status', 'Operation cannot be cancelled');
        }

        $this->update_operation_status($operation_id, 'cancelled');
        
        return true;
    }

    /**
     * Rollback operation
     */
    private function rollback_operation($operation_id) {
        global $wpdb;
        
        $operation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->operations_log_table} WHERE id = %d",
            $operation_id
        ));

        if (!$operation || !$operation->can_rollback) {
            return new WP_Error('cannot_rollback', 'Operation cannot be rolled back');
        }

        $rollback_data = json_decode($operation->rollback_data, true);
        if (empty($rollback_data)) {
            return new WP_Error('no_rollback_data', 'No rollback data available');
        }

        // Process rollback
        foreach ($rollback_data as $item_id => $original_data) {
            $this->restore_item_data($operation->operation_type, $item_id, $original_data);
        }

        // Mark as rolled back
        $wpdb->update(
            $this->operations_log_table,
            ['can_rollback' => 0],
            ['id' => $operation_id],
            ['%d'],
            ['%d']
        );

        return true;
    }

    /**
     * Restore item data
     */
    private function restore_item_data($operation_type, $item_id, $original_data) {
        global $wpdb;
        
        switch ($operation_type) {
            case 'vanity_codes':
                $table = $wpdb->prefix . 'affcd_vanity_codes';
                break;
            case 'domains':
                $table = $wpdb->prefix . 'affcd_authorized_domains';
                break;
            default:
                return;
        }
        
        // Remove id from data to prevent conflicts
        unset($original_data['id']);
        
        $wpdb->update(
            $table,
            $original_data,
            ['id' => $item_id],
            array_fill(0, count($original_data), '%s'),
            ['%d']
        );
    }

    /**
     * Cleanup old operations
     */
    public function cleanup_old_operations() {
        global $wpdb;
        
        $retention_days = get_option('affcd_operations_retention_days', 30);
        $cutoff_date = date('Y-m-d H:i:s', time() - ($retention_days * DAY_IN_SECONDS));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->operations_log_table} 
             WHERE started_at < %s AND status IN ('completed', 'failed', 'cancelled')",
            $cutoff_date
        ));
    }

    /**
     * Verify domain (placeholder)
     */
    private function verify_domain($domain) {
        // Implement actual domain verification logic
        // This could involve checking DNS records, making HTTP requests, etc.
        return true;
    }

    /**
     * Generate API key
     */
    private function generate_api_key() {
        return 'affcd_' . wp_generate_password(32, false);
    }
    <?php


    /**
     * Check if operation is cancelled
     */
    private function is_operation_cancelled($operation_id) {
        global $wpdb;
        
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$this->operations_log_table} WHERE id = %d",
            $operation_id
        ));
        
        return $status === 'cancelled';
    }

    

    /**
     * Rollback operation
     */
    private function rollback_operation($operation_id) {
        global $wpdb;
        
        $operation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->operations_log_table} WHERE id = %d",
            $operation_id
        ));

        if (!$operation) {
            return new WP_Error('not_found', __('Operation not found', 'affiliate-cross-domain'));
        }

        if (!$operation->can_rollback) {
            return new WP_Error('cannot_rollback', __('Operation cannot be rolled back', 'affiliate-cross-domain'));
        }

        $rollback_data = json_decode($operation->rollback_data, true);
        if (empty($rollback_data)) {
            return new WP_Error('no_rollback_data', __('No rollback data available', 'affiliate-cross-domain'));
        }

        $errors = [];
        foreach ($rollback_data as $item_id => $data) {
            $result = $this->rollback_single_item($operation->operation_type, $operation->operation_name, $item_id, $data);
            if (is_wp_error($result)) {
                $errors[] = [
                    'item_id' => $item_id,
                    'error' => $result->get_error_message()
                ];
            }
        }

        // Update operation status
        $wpdb->update(
            $this->operations_log_table,
            [
                'status' => empty($errors) ? 'rolled_back' : 'rollback_failed',
                'rollback_errors' => json_encode($errors),
                'rolled_back_at' => current_time('mysql')
            ],
            ['id' => $operation_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        return empty($errors);
    }

    /**
     * Rollback single item
     */
    private function rollback_single_item($operation_type, $operation, $item_id, $rollback_data) {
        switch ($operation_type) {
            case 'vanity_codes':
                return $this->rollback_vanity_code_operation($operation, $item_id, $rollback_data);
            case 'domains':
                return $this->rollback_domain_operation($operation, $item_id, $rollback_data);
            default:
                return new WP_Error('invalid_type', 'Invalid operation type');
        }
    }

    /**
     * Process vanity code operation
     */
    private function process_vanity_code_operation($operation, $code_id, $options) {
        global $wpdb;
        
        $vanity_table = $wpdb->prefix . 'affcd_vanity_codes';
        $rollback_data = [];

        // Get current state for rollback
        $current_state = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$vanity_table} WHERE id = %d",
            $code_id
        ), ARRAY_A);

        if (!$current_state) {
            return new WP_Error('not_found', __('Vanity code not found', 'affiliate-cross-domain'));
        }

        $rollback_data['original_state'] = $current_state;

        switch ($operation) {
            case 'activate':
                $result = $wpdb->update(
                    $vanity_table,
                    ['status' => 'active'],
                    ['id' => $code_id],
                    ['%s'],
                    ['%d']
                );
                break;

            case 'deactivate':
                $result = $wpdb->update(
                    $vanity_table,
                    ['status' => 'inactive'],
                    ['id' => $code_id],
                    ['%s'],
                    ['%d']
                );
                break;

            case 'expire':
                $result = $wpdb->update(
                    $vanity_table,
                    [
                        'status' => 'expired',
                        'expires_at' => current_time('mysql')
                    ],
                    ['id' => $code_id],
                    ['%s', '%s'],
                    ['%d']
                );
                break;

            case 'delete':
                // Soft delete by setting status
                $result = $wpdb->update(
                    $vanity_table,
                    ['status' => 'deleted'],
                    ['id' => $code_id],
                    ['%s'],
                    ['%d']
                );
                $rollback_data['deleted'] = true;
                break;

            case 'update_discount':
                if (isset($options['discount_value']) && isset($options['discount_type'])) {
                    $result = $wpdb->update(
                        $vanity_table,
                        [
                            'discount_type' => $options['discount_type'],
                            'discount_value' => floatval($options['discount_value'])
                        ],
                        ['id' => $code_id],
                        ['%s', '%f'],
                        ['%d']
                    );
                } else {
                    return new WP_Error('missing_data', __('Missing discount data', 'affiliate-cross-domain'));
                }
                break;

            default:
                return new WP_Error('invalid_operation', __('Invalid operation', 'affiliate-cross-domain'));
        }

        if ($result === false) {
            return new WP_Error('db_error', __('Database operation failed', 'affiliate-cross-domain'));
        }

        return ['rollback_data' => $rollback_data];
    }

    /**
     * Process domain operation
     */
    private function process_domain_operation($operation, $domain_id, $options) {
        global $wpdb;
        
        $domains_table = $wpdb->prefix . 'affcd_authorized_domains';
        $rollback_data = [];

        // Get current state for rollback
        $current_state = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$domains_table} WHERE id = %d",
            $domain_id
        ), ARRAY_A);

        if (!$current_state) {
            return new WP_Error('not_found', __('Domain not found', 'affiliate-cross-domain'));
        }

        $rollback_data['original_state'] = $current_state;

        switch ($operation) {
            case 'activate':
                $result = $wpdb->update(
                    $domains_table,
                    ['status' => 'active'],
                    ['id' => $domain_id],
                    ['%s'],
                    ['%d']
                );
                break;

            case 'suspend':
                $result = $wpdb->update(
                    $domains_table,
                    [
                        'status' => 'suspended',
                        'suspended_at' => current_time('mysql'),
                        'suspended_reason' => $options['reason'] ?? 'Bulk operation'
                    ],
                    ['id' => $domain_id],
                    ['%s', '%s', '%s'],
                    ['%d']
                );
                break;

            case 'verify':
                // Trigger domain verification
                $domain_manager = new AFFCD_Domain_Manager();
                $verification_result = $domain_manager->verify_domain($domain_id);
                
                if ($verification_result['success']) {
                    $result = true;
                } else {
                    return new WP_Error('verification_failed', $verification_result['message']);
                }
                break;

            case 'regenerate_api_key':
                $new_api_key = affcd_generate_api_key();
                $result = $wpdb->update(
                    $domains_table,
                    ['api_key' => $new_api_key],
                    ['id' => $domain_id],
                    ['%s'],
                    ['%d']
                );
                $rollback_data['new_api_key'] = $new_api_key;
                break;

            default:
                return new WP_Error('invalid_operation', __('Invalid operation', 'affiliate-cross-domain'));
        }

        if ($result === false) {
            return new WP_Error('db_error', __('Database operation failed', 'affiliate-cross-domain'));
        }

        return ['rollback_data' => $rollback_data];
    }

    /**
     * Rollback vanity code operation
     */
    private function rollback_vanity_code_operation($operation, $code_id, $rollback_data) {
        global $wpdb;
        
        $vanity_table = $wpdb->prefix . 'affcd_vanity_codes';
        $original_state = $rollback_data['original_state'];

        if (isset($rollback_data['deleted']) && $rollback_data['deleted']) {
            // Restore from soft delete
            $result = $wpdb->update(
                $vanity_table,
                ['status' => $original_state['status']],
                ['id' => $code_id],
                ['%s'],
                ['%d']
            );
        } else {
            // Restore original values
            $result = $wpdb->update(
                $vanity_table,
                [
                    'status' => $original_state['status'],
                    'discount_type' => $original_state['discount_type'],
                    'discount_value' => $original_state['discount_value'],
                    'expires_at' => $original_state['expires_at']
                ],
                ['id' => $code_id],
                ['%s', '%s', '%f', '%s'],
                ['%d']
            );
        }

        return $result !== false;
    }

    /**
     * Rollback domain operation
     */
    private function rollback_domain_operation($operation, $domain_id, $rollback_data) {
        global $wpdb;
        
        $domains_table = $wpdb->prefix . 'affcd_authorized_domains';
        $original_state = $rollback_data['original_state'];

        $result = $wpdb->update(
            $domains_table,
            [
                'status' => $original_state['status'],
                'api_key' => $original_state['api_key'],
                'suspended_at' => $original_state['suspended_at'],
                'suspended_reason' => $original_state['suspended_reason']
            ],
            ['id' => $domain_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Render tab content for vanity codes
     */
    private function render_vanity_codes_tab() {
        ?>
        <div class="affcd-operation-section">
            <h3><?php _e('Vanity Code Operations', 'affiliate-cross-domain'); ?></h3>
            
            <form id="bulk-vanity-form" method="post">
                <?php wp_nonce_field('affcd_bulk_nonce', 'affcd_bulk_nonce'); ?>
                
                <div class="form-group">
                    <label for="vanity-operation"><?php _e('Operation', 'affiliate-cross-domain'); ?></label>
                    <select id="vanity-operation" name="operation" required>
                        <option value=""><?php _e('Select Operation', 'affiliate-cross-domain'); ?></option>
                        <option value="activate"><?php _e('Activate', 'affiliate-cross-domain'); ?></option>
                        <option value="deactivate"><?php _e('Deactivate', 'affiliate-cross-domain'); ?></option>
                        <option value="expire"><?php _e('Expire', 'affiliate-cross-domain'); ?></option>
                        <option value="delete"><?php _e('Delete', 'affiliate-cross-domain'); ?></option>
                        <option value="update_discount"><?php _e('Update Discount', 'affiliate-cross-domain'); ?></option>
                    </select>
                </div>

                <div id="discount-options" class="form-group" style="display: none;">
                    <label for="discount-type"><?php _e('Discount Type', 'affiliate-cross-domain'); ?></label>
                    <select id="discount-type" name="options[discount_type]">
                        <option value="percentage"><?php _e('Percentage', 'affiliate-cross-domain'); ?></option>
                        <option value="fixed"><?php _e('Fixed Amount', 'affiliate-cross-domain'); ?></option>
                    </select>
                    
                    <label for="discount-value"><?php _e('Discount Value', 'affiliate-cross-domain'); ?></label>
                    <input type="number" id="discount-value" name="options[discount_value]" step="0.01" min="0">
                </div>

                <div class="form-group">
                    <label><?php _e('Select Codes', 'affiliate-cross-domain'); ?></label>
                    <?php $this->render_vanity_codes_list(); ?>
                </div>

                <button type="submit" class="button button-primary">
                    <?php _e('Execute Operation', 'affiliate-cross-domain'); ?>
                </button>
            </form>
        </div>
        <?php
    }

    /**
     * Render tab content for domains
     */
    private function render_domains_tab() {
        ?>
        <div class="affcd-operation-section">
            <h3><?php _e('Domain Operations', 'affiliate-cross-domain'); ?></h3>
            
            <form id="bulk-domain-form" method="post">
                <?php wp_nonce_field('affcd_bulk_nonce', 'affcd_bulk_nonce'); ?>
                
                <div class="form-group">
                    <label for="domain-operation"><?php _e('Operation', 'affiliate-cross-domain'); ?></label>
                    <select id="domain-operation" name="operation" required>
                        <option value=""><?php _e('Select Operation', 'affiliate-cross-domain'); ?></option>
                        <option value="activate"><?php _e('Activate', 'affiliate-cross-domain'); ?></option>
                        <option value="suspend"><?php _e('Suspend', 'affiliate-cross-domain'); ?></option>
                        <option value="verify"><?php _e('Verify Connection', 'affiliate-cross-domain'); ?></option>
                        <option value="regenerate_api_key"><?php _e('Regenerate API Key', 'affiliate-cross-domain'); ?></option>
                    </select>
                </div>

                <div id="suspend-options" class="form-group" style="display: none;">
                    <label for="suspend-reason"><?php _e('Suspension Reason', 'affiliate-cross-domain'); ?></label>
                    <textarea id="suspend-reason" name="options[reason]" placeholder="<?php _e('Enter reason for suspension', 'affiliate-cross-domain'); ?>"></textarea>
                </div>

                <div class="form-group">
                    <label><?php _e('Select Domains', 'affiliate-cross-domain'); ?></label>
                    <?php $this->render_domains_list(); ?>
                </div>

                <button type="submit" class="button button-primary">
                    <?php _e('Execute Operation', 'affiliate-cross-domain'); ?>
                </button>
            </form>
        </div>
        <?php
    }

    /**
     * Render import/export tab
     */
    private function render_import_export_tab() {
        ?>
        <div class="affcd-operation-section">
            <h3><?php _e('Export Data', 'affiliate-cross-domain'); ?></h3>
            
            <form id="export-form" method="post">
                <?php wp_nonce_field('affcd_bulk_nonce', 'affcd_bulk_nonce'); ?>
                
                <div class="form-group">
                    <label for="export-type"><?php _e('Export Type', 'affiliate-cross-domain'); ?></label>
                    <select id="export-type" name="export_type" required>
                        <option value=""><?php _e('Select Export Type', 'affiliate-cross-domain'); ?></option>
                        <option value="vanity_codes"><?php _e('Vanity Codes', 'affiliate-cross-domain'); ?></option>
                        <option value="domains"><?php _e('Authorized Domains', 'affiliate-cross-domain'); ?></option>
                        <option value="analytics"><?php _e('Analytics Data', 'affiliate-cross-domain'); ?></option>
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
                    <label for="date-range"><?php _e('Date Range', 'affiliate-cross-domain'); ?></label>
                    <input type="date" id="date-from" name="date_from" placeholder="<?php _e('From', 'affiliate-cross-domain'); ?>">
                    <input type="date" id="date-to" name="date_to" placeholder="<?php _e('To', 'affiliate-cross-domain'); ?>">
                </div>

                <button type="button" id="execute-export" class="button button-primary">
                    <?php _e('Export Data', 'affiliate-cross-domain'); ?>
                </button>
            </form>
        </div>

        <div class="affcd-operation-section">
            <h3><?php _e('Import Data', 'affiliate-cross-domain'); ?></h3>
            
            <form id="import-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('affcd_bulk_nonce', 'affcd_bulk_nonce'); ?>
                
                <div class="form-group">
                    <label for="import-type"><?php _e('Import Type', 'affiliate-cross-domain'); ?></label>
                    <select id="import-type" name="import_type" required>
                        <option value=""><?php _e('Select Import Type', 'affiliate-cross-domain'); ?></option>
                        <option value="vanity_codes"><?php _e('Vanity Codes', 'affiliate-cross-domain'); ?></option>
                        <option value="domains"><?php _e('Authorized Domains', 'affiliate-cross-domain'); ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="import-file"><?php _e('File', 'affiliate-cross-domain'); ?></label>
                    <input type="file" id="import-file" name="import_file" accept=".csv,.json,.xml" required>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="import-update-existing" name="update_existing" value="1">
                        <?php _e('Update existing records', 'affiliate-cross-domain'); ?>
                    </label>
                </div>
                
                <button type="button" id="execute-import" class="button button-primary">
                    <?php _e('Import Data', 'affiliate-cross-domain'); ?>
                </button>
            </form>

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
                                <th><?php _e('Actions', 'affiliate-cross-domain'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($operations)): ?>
                                <tr>
                                    <td colspan="6"><?php _e('No operations found.', 'affiliate-cross-domain'); ?></td>
                                </tr>
                            <?php else: ?>
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
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?php echo esc_attr($operation->progress_percentage ?? 0); ?>%"></div>
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
                                            <?php if (in_array($operation->status, ['pending', 'running'])): ?>
                                                <button type="button" class="button button-small cancel-operation" data-operation-id="<?php echo esc_attr($operation->id); ?>">
                                                    <?php _e('Cancel', 'affiliate-cross-domain'); ?>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($operation->can_rollback): ?>
                                                <button type="button" class="button button-small rollback-operation" data-operation-id="<?php echo esc_attr($operation->id); ?>">
                                                    <?php _e('Rollback', 'affiliate-cross-domain'); ?>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button type="button" class="button button-small view-operation-details" data-operation-id="<?php echo esc_attr($operation->id); ?>">
                                                <?php _e('Details', 'affiliate-cross-domain'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render vanity codes list for selection
     */
    private function render_vanity_codes_list() {
        global $wpdb;
        
        $vanity_table = $wpdb->prefix . 'affcd_vanity_codes';
        $codes = $wpdb->get_results(
            "SELECT id, vanity_code, status, affiliate_id, usage_count 
             FROM {$vanity_table} 
             WHERE status != 'deleted' 
             ORDER BY created_at DESC 
             LIMIT 100"
        );

        if (empty($codes)) {
            echo '<p>' . __('No vanity codes found.', 'affiliate-cross-domain') . '</p>';
            return;
        }

        echo '<div class="codes-selection-list">';
        echo '<label><input type="checkbox" id="select-all-codes"> ' . __('Select All', 'affiliate-cross-domain') . '</label>';
        
        foreach ($codes as $code) {
            echo '<div class="code-item">';
            echo '<label>';
            echo '<input type="checkbox" name="codes[]" value="' . esc_attr($code->id) . '">';
            echo '<strong>' . esc_html($code->vanity_code) . '</strong> ';
            echo '<span class="status-' . esc_attr($code->status) . '">' . esc_html(ucwords($code->status)) . '</span> ';
            echo '<small>(' . sprintf(__('Used %d times', 'affiliate-cross-domain'), $code->usage_count) . ')</small>';
            echo '</label>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    /**
     * Render domains list for selection
     */
    private function render_domains_list() {
        global $wpdb;
        
        $domains_table = $wpdb->prefix . 'affcd_authorized_domains';
        $domains = $wpdb->get_results(
            "SELECT id, domain_url, domain_name, status, verification_status 
             FROM {$domains_table} 
             ORDER BY created_at DESC"
        );

        if (empty($domains)) {
            echo '<p>' . __('No domains found.', 'affiliate-cross-domain') . '</p>';
            return;
        }

        echo '<div class="domains-selection-list">';
        echo '<label><input type="checkbox" id="select-all-domains"> ' . __('Select All', 'affiliate-cross-domain') . '</label>';
        
        foreach ($domains as $domain) {
            echo '<div class="domain-item">';
            echo '<label>';
            echo '<input type="checkbox" name="domains[]" value="' . esc_attr($domain->id) . '">';
            echo '<strong>' . esc_html($domain->domain_url) . '</strong> ';
            if ($domain->domain_name) {
                echo '(' . esc_html($domain->domain_name) . ') ';
            }
            echo '<span class="status-' . esc_attr($domain->status) . '">' . esc_html(ucwords($domain->status)) . '</span> ';
            echo '<span class="verification-' . esc_attr($domain->verification_status) . '">' . esc_html(ucwords($domain->verification_status)) . '</span>';
            echo '</label>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    /**
     * Get recent operations
     */
    private function get_recent_operations($limit = 20) {
        global $wpdb;
        
        $this->maybe_create_operations_log_table();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->operations_log_table} 
             ORDER BY started_at DESC 
             LIMIT %d",
            $limit
        ));
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
    public function cleanup_old_operations() {
        global $wpdb;
        
        // Clean operations older than 30 days
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->operations_log_table} 
             WHERE status IN ('completed', 'failed', 'cancelled', 'rolled_back') 
             AND started_at < %s",
            $cutoff_date
        ));

        if ($deleted > 0) {
            affcd_log_activity('bulk_operations_cleanup', [
                'deleted_operations' => $deleted,
                'cutoff_date' => $cutoff_date
            ]);
        }
    }

    /**
     * Export data
     */
    public function export_data($export_type, $format = 'csv', $options = []) {
        global $wpdb;
        
        $data = [];
        $filename = '';
        
        switch ($export_type) {
            case 'vanity_codes':
                $filename = 'vanity-codes-' . date('Y-m-d');
                $table = $wpdb->prefix . 'affcd_vanity_codes';
                $data = $wpdb->get_results("SELECT * FROM {$table} WHERE status != 'deleted'", ARRAY_A);
                break;
                
            case 'domains':
                $filename = 'authorized-domains-' . date('Y-m-d');
                $table = $wpdb->prefix . 'affcd_authorized_domains';
                $data = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);
                
                // Remove sensitive data
                foreach ($data as &$row) {
                    unset($row['api_secret']);
                    $row['api_key'] = substr($row['api_key'], 0, 8) . '...';
                }
                break;
                
            case 'analytics':
                $filename = 'analytics-data-' . date('Y-m-d');
                $table = $wpdb->prefix . 'affcd_analytics';
                $where_clause = '';
                
                if (!empty($options['date_from'])) {
                    $where_clause .= $wpdb->prepare(" AND created_at >= %s", $options['date_from']);
                }
                
                if (!empty($options['date_to'])) {
                    $where_clause .= $wpdb->prepare(" AND created_at <= %s", $options['date_to'] . ' 23:59:59');
                }
                
                $data = $wpdb->get_results("SELECT * FROM {$table} WHERE 1=1 {$where_clause} ORDER BY created_at DESC", ARRAY_A);
                break;
                
            default:
                return new WP_Error('invalid_export_type', __('Invalid export type', 'affiliate-cross-domain'));
        }

        if (empty($data)) {
            return new WP_Error('no_data', __('No data available for export', 'affiliate-cross-domain'));
        }

        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/affcd-exports/';
        
        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
        }

        $file_path = $export_dir . $filename . '.' . $format;
        
        switch ($format) {
            case 'csv':
                $result = $this->export_to_csv($data, $file_path);
                break;
                
            case 'json':
                $result = $this->export_to_json($data, $file_path);
                break;
                
            case 'xml':
                $result = $this->export_to_xml($data, $file_path, $export_type);
                break;
                
            default:
                return new WP_Error('invalid_format', __('Invalid export format', 'affiliate-cross-domain'));
        }

        if (is_wp_error($result)) {
            return $result;
        }

        $file_url = $upload_dir['baseurl'] . '/affcd-exports/' . basename($file_path);
        
        return [
            'file_path' => $file_path,
            'file_url' => $file_url,
            'filename' => basename($file_path),
            'records_exported' => count($data)
        ];
    }

    /**
     * Export to CSV
     */
    private function export_to_csv($data, $file_path) {
        $fp = fopen($file_path, 'w');
        
        if (!$fp) {
            return new WP_Error('file_error', __('Could not create export file', 'affiliate-cross-domain'));
        }
        
        // Write headers
        if (!empty($data)) {
            fputcsv($fp, array_keys($data[0]));
            
            // Write data
            foreach ($data as $row) {
                fputcsv($fp, $row);
            }
        }
        
        fclose($fp);
        return true;
    }

    /**
     * Export to JSON
     */
    private function export_to_json($data, $file_path) {
        $json = wp_json_encode($data, JSON_PRETTY_PRINT);
        
        if (file_put_contents($file_path, $json) === false) {
            return new WP_Error('file_error', __('Could not create export file', 'affiliate-cross-domain'));
        }
        
        return true;
    }

    /**
     * Export to XML
     */
    private function export_to_xml($data, $file_path, $root_element) {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><' . $root_element . '></' . $root_element . '>');
        
        foreach ($data as $row) {
            $item = $xml->addChild('item');
            foreach ($row as $key => $value) {
                $item->addChild(htmlspecialchars($key), htmlspecialchars($value));
            }
        }
        
        if ($xml->asXML($file_path) === false) {
            return new WP_Error('file_error', __('Could not create export file', 'affiliate-cross-domain'));
        }
        
        return true;
    }

    /**
     * Import data from file
     */
    public function import_data($import_type, $file_path, $options = []) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', __('Import file not found', 'affiliate-cross-domain'));
        }

        $extension = pathinfo($file_path, PATHINFO_EXTENSION);
        $data = [];
        
        switch ($extension) {
            case 'csv':
                $data = $this->parse_csv_file($file_path);
                break;
                
            case 'json':
                $data = $this->parse_json_file($file_path);
                break;
                
            case 'xml':
                $data = $this->parse_xml_file($file_path);
                break;
                
            default:
                return new WP_Error('invalid_format', __('Unsupported file format', 'affiliate-cross-domain'));
        }

        if (is_wp_error($data)) {
            return $data;
        }

        if (empty($data)) {
            return new WP_Error('no_data', __('No data found in import file', 'affiliate-cross-domain'));
        }

        // Validate and process data based on import type
        return $this->process_import_data($import_type, $data, $options);
    }

    /**
     * Parse CSV file
     */
    private function parse_csv_file($file_path) {
        $data = [];
        $headers = [];
        
        if (($handle = fopen($file_path, 'r')) !== false) {
            $row_count = 0;
            while (($row = fgetcsv($handle)) !== false) {
                if ($row_count === 0) {
                    $headers = $row;
                } else {
                    $data[] = array_combine($headers, $row);
                }
                $row_count++;
            }
            fclose($handle);
        } else {
            return new WP_Error('file_error', __('Could not read import file', 'affiliate-cross-domain'));
        }
        
        return $data;
    }

    /**
     * Parse JSON file
     */
    private function parse_json_file($file_path) {
        $content = file_get_contents($file_path);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', __('Invalid JSON format', 'affiliate-cross-domain'));
        }
        
        return $data;
    }

    /**
     * Parse XML file
     */
    private function parse_xml_file($file_path) {
        $xml = simplexml_load_file($file_path);
        
        if ($xml === false) {
            return new WP_Error('xml_error', __('Invalid XML format', 'affiliate-cross-domain'));
        }
        
        $data = [];
        foreach ($xml->item as $item) {
            $row = [];
            foreach ($item as $key => $value) {
                $row[$key] = (string) $value;
            }
            $data[] = $row;
        }
        
        return $data;
    }

    /**
     * Process import data
     */
    private function process_import_data($import_type, $data, $options = []) {
        $imported = 0;
        $updated = 0;
        $errors = [];
        $update_existing = !empty($options['update_existing']);

        foreach ($data as $index => $row) {
            $result = $this->import_single_record($import_type, $row, $update_existing);
            
            if (is_wp_error($result)) {
                $errors[] = [
                    'row' => $index + 1,
                    'error' => $result->get_error_message(),
                    'data' => $row
                ];
            } else {
                if ($result['action'] === 'created') {
                    $imported++;
                } else {
                    $updated++;
                }
            }
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
            'total_processed' => count($data)
        ];
    }

    /**
     * Import single record
     */
    private function import_single_record($import_type, $data, $update_existing) {
        global $wpdb;
        
        switch ($import_type) {
            case 'vanity_codes':
                return $this->import_vanity_code($data, $update_existing);
                
            case 'domains':
                return $this->import_domain($data, $update_existing);
                
            default:
                return new WP_Error('invalid_type', __('Invalid import type', 'affiliate-cross-domain'));
        }
    }

    /**
     * Import vanity code
     */
    private function import_vanity_code($data, $update_existing) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'affcd_vanity_codes';
        
        // Required fields
        if (empty($data['vanity_code']) || empty($data['affiliate_id'])) {
            return new WP_Error('missing_data', __('Missing required fields: vanity_code, affiliate_id', 'affiliate-cross-domain'));
        }

        // Check if code exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE vanity_code = %s",
            $data['vanity_code']
        ));

        if ($existing) {
            if (!$update_existing) {
                return new WP_Error('duplicate', __('Vanity code already exists', 'affiliate-cross-domain'));
            }
            
            // Update existing
            $result = $wpdb->update(
                $table,
                [
                    'affiliate_id' => $data['affiliate_id'],
                    'description' => $data['description'] ?? '',
                    'discount_type' => $data['discount_type'] ?? 'percentage',
                    'discount_value' => $data['discount_value'] ?? 0,
                    'status' => $data['status'] ?? 'active'
                ],
                ['id' => $existing],
                ['%d', '%s', '%s', '%f', '%s'],
                ['%d']
            );
            
            return $result !== false ? ['action' => 'updated'] : new WP_Error('update_failed', __('Failed to update record', 'affiliate-cross-domain'));
        } else {
            // Insert new
            $result = $wpdb->insert(
                $table,
                [
                    'vanity_code' => $data['vanity_code'],
                    'affiliate_id' => $data['affiliate_id'],
                    'affiliate_code' => $data['affiliate_code'] ?? $data['vanity_code'],
                    'description' => $data['description'] ?? '',
                    'discount_type' => $data['discount_type'] ?? 'percentage',
                    'discount_value' => $data['discount_value'] ?? 0,
                    'status' => $data['status'] ?? 'active',
                    'created_by' => get_current_user_id()
                ],
                ['%s', '%d', '%s', '%s', '%s', '%f', '%s', '%d']
            );
            
            return $result !== false ? ['action' => 'created'] : new WP_Error('insert_failed', __('Failed to create record', 'affiliate-cross-domain'));
        }
    }

    /**
     * Import domain
     */
    private function import_domain($data, $update_existing) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'affcd_authorized_domains';
        
        // Required fields
        if (empty($data['domain_url'])) {
            return new WP_Error('missing_data', __('Missing required field: domain_url', 'affiliate-cross-domain'));
        }

        // Check if domain exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE domain_url = %s",
            $data['domain_url']
        ));

        if ($existing) {
            if (!$update_existing) {
                return new WP_Error('duplicate', __('Domain already exists', 'affiliate-cross-domain'));
            }
            
            // Update existing (don't overwrite API credentials)
            $result = $wpdb->update(
                $table,
                [
                    'domain_name' => $data['domain_name'] ?? '',
                    'status' => $data['status'] ?? 'active',
                    'owner_email' => $data['owner_email'] ?? '',
                    'owner_name' => $data['owner_name'] ?? ''
                ],
                ['id' => $existing],
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );
            
            return $result !== false ? ['action' => 'updated'] : new WP_Error('update_failed', __('Failed to update record', 'affiliate-cross-domain'));
        } else {
            // Insert new
            $result = $wpdb->insert(
                $table,
                [
                    'domain_url' => $data['domain_url'],
                    'domain_name' => $data['domain_name'] ?? '',
                    'api_key' => affcd_generate_api_key(),
                    'api_secret' => affcd_hash_api_secret(affcd_generate_api_secret()),
                    'status' => $data['status'] ?? 'pending',
                    'owner_email' => $data['owner_email'] ?? '',
                    'owner_name' => $data['owner_name'] ?? '',
                    'created_by' => get_current_user_id()
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
            );
            
            return $result !== false ? ['action' => 'created'] : new WP_Error('insert_failed', __('Failed to create record', 'affiliate-cross-domain'));
        }
    }

    /**
     * Get dashboard statistics
     */
    private function get_dashboard_statistics() {
        global $wpdb;
        
        $stats = [];
        
        // Vanity codes stats
        $vanity_table = $wpdb->prefix . 'affcd_vanity_codes';
        $stats['vanity_codes'] = [
            'total' => $this->count_vanity_codes_by_status('all'),
            'active' => $this->count_vanity_codes_by_status('active'),
            'inactive' => $this->count_vanity_codes_by_status('inactive'),
            'expired' => $this->count_expired_vanity_codes()
        ];
        
        // Domains stats
        $domains_table = $wpdb->prefix . 'affcd_authorized_domains';
        $stats['domains'] = [
            'total' => $this->count_domains_by_status('all'),
            'active' => $this->count_domains_by_status('active'),
            'inactive' => $this->count_domains_by_status('inactive'),
            'unverified' => $this->count_unverified_domains()
        ];
        
        return $stats;
    }


}