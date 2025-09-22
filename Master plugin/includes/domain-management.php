<?php
/**
 * Domain Management for WP Affiliate Cross Domain Plugin Suite
 *
 * Administrative interface for managing authorised client domains,
 * API keys, webhook configurations, and domain-specific settings.
 *
 * Filename: admin/domain-management.php
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

class AFFCD_Domain_Management {

    /**
     * Domain manager instance
     *
     * @var AFFCD_Domain_Manager
     */
    private $domain_manager;

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_post_affcd_save_domain_settings', [$this, 'save_domain_settings']);
        add_action('admin_post_affcd_add_domain', [$this, 'add_domain']);
        add_action('admin_post_affcd_remove_domain', [$this, 'remove_domain']);
        add_action('wp_ajax_affcd_test_domain_connection', [$this, 'ajax_test_domain_connection']);
        add_action('wp_ajax_affcd_generate_api_key', [$this, 'ajax_generate_api_key']);
        add_action('wp_ajax_affcd_send_test_webhook', [$this, 'ajax_send_test_webhook']);
        add_action('wp_ajax_affcd_verify_domain', [$this, 'ajax_verify_domain']);
        add_action('wp_ajax_affcd_toggle_domain_status', [$this, 'ajax_toggle_domain_status']);
        add_action('wp_ajax_affcd_delete_domain', [$this, 'ajax_delete_domain']);
        add_action('wp_ajax_affcd_bulk_domain_action', [$this, 'ajax_bulk_domain_action']);
        add_action('wp_ajax_affcd_refresh_domain_list', [$this, 'ajax_refresh_domain_list']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // Initialise domain manager
        $this->domain_manager = new AFFCD_Domain_Manager();
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'affiliate-wp',
            __('Domain Management', 'affiliate-cross-domain'),
            __('Domain Management', 'affiliate-cross-domain'),
            'manage_affiliates',
            'affcd-domain-management',
            [$this, 'render_domain_management_page']
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ('affiliate-wp_page_affcd-domain-management' !== $hook) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_script('affcd-domain-management', 
            AFFCD_PLUGIN_URL . 'assets/js/domain-management.js', 
            ['jquery', 'jquery-ui-dialog'], 
            AFFCD_VERSION, 
            true
        );

        wp_enqueue_style('wp-jquery-ui-dialog');
        wp_enqueue_style('affcd-domain-management', 
            AFFCD_PLUGIN_URL . 'assets/css/domain-management.css', 
            [], 
            AFFCD_VERSION
        );

        wp_localize_script('affcd-domain-management', 'affcdAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('affcd_ajax_nonce'),
            'strings' => [
                'testing' => __('Testing...', 'affiliate-cross-domain'),
                'success' => __('Success!', 'affiliate-cross-domain'),
                'error' => __('Error', 'affiliate-cross-domain'),
                'confirmRemove' => __('Are you sure you want to remove this domain?', 'affiliate-cross-domain'),
                'confirmBulkAction' => __('Are you sure you want to apply "%s" to %d selected domains?', 'affiliate-cross-domain'),
                'selectBulkAction' => __('Please select a bulk action.', 'affiliate-cross-domain'),
                'selectDomains' => __('Please select at least one domain.', 'affiliate-cross-domain'),
                'processing' => __('Processing...', 'affiliate-cross-domain'),
                'verifying' => __('Verifying...', 'affiliate-cross-domain'),
                'verified' => __('Verified', 'affiliate-cross-domain'),
                'verificationFailed' => __('Verification failed', 'affiliate-cross-domain'),
                'verificationError' => __('Verification error occurred', 'affiliate-cross-domain'),
                'addDomain' => __('Add Domain', 'affiliate-cross-domain'),
                'updateDomain' => __('Update Domain', 'affiliate-cross-domain'),
                'updating' => __('Updating...', 'affiliate-cross-domain'),
                'deleting' => __('Deleting...', 'affiliate-cross-domain'),
                'delete' => __('Delete', 'affiliate-cross-domain'),
                'verify' => __('Verify', 'affiliate-cross-domain'),
                'statusUpdateFailed' => __('Failed to update domain status', 'affiliate-cross-domain'),
                'statusUpdateError' => __('Error updating domain status', 'affiliate-cross-domain'),
                'bulkActionFailed' => __('Bulk action failed', 'affiliate-cross-domain'),
                'bulkActionError' => __('Error performing bulk action', 'affiliate-cross-domain'),
                'addDomainFailed' => __('Failed to add domain', 'affiliate-cross-domain'),
                'addDomainError' => __('Error adding domain', 'affiliate-cross-domain'),
                'updateDomainFailed' => __('Failed to update domain', 'affiliate-cross-domain'),
                'updateDomainError' => __('Error updating domain', 'affiliate-cross-domain'),
                'deleteDomainFailed' => __('Failed to delete domain', 'affiliate-cross-domain'),
                'deleteDomainError' => __('Error deleting domain', 'affiliate-cross-domain'),
            ]
        ]);
    }

    /**
     * Render domain management page
     */
    public function render_domain_management_page() {
        $active_tab = $_GET['tab'] ?? 'domains';
        $domains = $this->domain_manager->get_all_domains();
        $domain_stats = $this->get_domain_statistics();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php $this->render_notices(); ?>
            
            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url(admin_url('admin.php?page=affcd-domain-management&tab=domains')); ?>" 
                   class="nav-tab <?php echo $active_tab === 'domains' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Domains', 'affiliate-cross-domain'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=affcd-domain-management&tab=settings')); ?>" 
                   class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Settings', 'affiliate-cross-domain'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=affcd-domain-management&tab=security')); ?>" 
                   class="nav-tab <?php echo $active_tab === 'security' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Security', 'affiliate-cross-domain'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=affcd-domain-management&tab=analytics')); ?>" 
                   class="nav-tab <?php echo $active_tab === 'analytics' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Analytics', 'affiliate-cross-domain'); ?>
                </a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    case 'security':
                        $this->render_security_tab();
                        break;
                    case 'analytics':
                        $this->render_analytics_tab($domain_stats);
                        break;
                    case 'domains':
                    default:
                        $this->render_domains_tab($domains);
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render notices
     */
    private function render_notices() {
        $message = $_GET['message'] ?? '';
        
        switch ($message) {
            case 'domain_added':
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Domain added successfully.', 'affiliate-cross-domain') . '</p></div>';
                break;
            case 'domain_updated':
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Domain updated successfully.', 'affiliate-cross-domain') . '</p></div>';
                break;
            case 'domain_removed':
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Domain removed successfully.', 'affiliate-cross-domain') . '</p></div>';
                break;
            case 'invalid_domain':
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Invalid domain URL provided.', 'affiliate-cross-domain') . '</p></div>';
                break;
            case 'domain_exists':
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Domain already exists.', 'affiliate-cross-domain') . '</p></div>';
                break;
            case 'settings_saved':
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully.', 'affiliate-cross-domain') . '</p></div>';
                break;
        }
    }

    /**
     * Render domains tab
     */
    private function render_domains_tab($domains) {
        ?>
        <div class="domain-management-content">
            <div class="domain-stats-summary">
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo count($domains); ?></span>
                        <span class="stat-label"><?php _e('Total Domains', 'affiliate-cross-domain'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo count(array_filter($domains, function($d) { return $d->status === 'active'; })); ?></span>
                        <span class="stat-label"><?php _e('Active Domains', 'affiliate-cross-domain'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo count(array_filter($domains, function($d) { return $d->verification_status === 'verified'; })); ?></span>
                        <span class="stat-label"><?php _e('Verified Domains', 'affiliate-cross-domain'); ?></span>
                    </div>
                </div>
            </div>

            <div class="domain-management-section">
                <h2><?php _e('Add New Domain', 'affiliate-cross-domain'); ?></h2>
                
                <form id="add-domain-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('affcd_add_domain', 'affcd_nonce'); ?>
                    <input type="hidden" name="action" value="affcd_add_domain">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="domain_url"><?php _e('Domain URL', 'affiliate-cross-domain'); ?></label>
                            </th>
                            <td>
                                <input type="url" 
                                       id="domain_url" 
                                       name="domain_url" 
                                       class="regular-text" 
                                       placeholder="https://example.com"
                                       required>
                                <p class="description"><?php _e('Enter the full domain URL including https://', 'affiliate-cross-domain'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="domain_name"><?php _e('Domain Name', 'affiliate-cross-domain'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="domain_name" 
                                       name="domain_name" 
                                       class="regular-text" 
                                       placeholder="Example Site">
                                <p class="description"><?php _e('Friendly name for this domain', 'affiliate-cross-domain'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="owner_email"><?php _e('Owner Email', 'affiliate-cross-domain'); ?></label>
                            </th>
                            <td>
                                <input type="email" 
                                       id="owner_email" 
                                       name="owner_email" 
                                       class="regular-text" 
                                       placeholder="owner@example.com">
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="owner_name"><?php _e('Owner Name', 'affiliate-cross-domain'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="owner_name" 
                                       name="owner_name" 
                                       class="regular-text" 
                                       placeholder="John Doe">
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="status"><?php _e('Initial Status', 'affiliate-cross-domain'); ?></label>
                            </th>
                            <td>
                                <select id="status" name="status">
                                    <option value="pending"><?php _e('Pending', 'affiliate-cross-domain'); ?></option>
                                    <option value="active"><?php _e('Active', 'affiliate-cross-domain'); ?></option>
                                    <option value="inactive"><?php _e('Inactive', 'affiliate-cross-domain'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Add Domain', 'affiliate-cross-domain')); ?>
                </form>
            </div>

            <div class="domain-management-section">
                <h2><?php _e('authorised Domains', 'affiliate-cross-domain'); ?></h2>
                
                <?php if (empty($domains)): ?>
                    <p><?php _e('No domains configured yet. Add your first domain above.', 'affiliate-cross-domain'); ?></p>
                <?php else: ?>
                    <div class="tablenav top">
                        <div class="alignleft actions bulkactions">
                            <select id="bulk-actions" name="action">
                                <option value="-1"><?php _e('Bulk Actions', 'affiliate-cross-domain'); ?></option>
                                <option value="activate"><?php _e('Activate', 'affiliate-cross-domain'); ?></option>
                                <option value="deactivate"><?php _e('Deactivate', 'affiliate-cross-domain'); ?></option>
                                <option value="verify"><?php _e('Verify', 'affiliate-cross-domain'); ?></option>
                                <option value="delete"><?php _e('Delete', 'affiliate-cross-domain'); ?></option>
                            </select>
                            <button type="button" id="apply-bulk-action" class="button action">
                                <?php _e('Apply', 'affiliate-cross-domain'); ?>
                            </button>
                        </div>
                        <div class="tablenav-pages">
                            <span class="displaying-num"><?php printf(_n('%s item', '%s items', count($domains), 'affiliate-cross-domain'), count($domains)); ?></span>
                        </div>
                    </div>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <td class="manage-column column-cb check-column">
                                    <input type="checkbox" id="select-all-domains">
                                </td>
                                <th class="manage-column"><?php _e('Domain', 'affiliate-cross-domain'); ?></th>
                                <th class="manage-column"><?php _e('Status', 'affiliate-cross-domain'); ?></th>
                                <th class="manage-column"><?php _e('Verification', 'affiliate-cross-domain'); ?></th>
                                <th class="manage-column"><?php _e('API Key', 'affiliate-cross-domain'); ?></th>
                                <th class="manage-column"><?php _e('Last Activity', 'affiliate-cross-domain'); ?></th>
                                <th class="manage-column"><?php _e('Actions', 'affiliate-cross-domain'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($domains as $domain): ?>
                                <tr class="domain-list-item" data-domain-id="<?php echo esc_attr($domain->id); ?>">
                                    <th class="check-column">
                                        <input type="checkbox" name="domain_ids[]" value="<?php echo esc_attr($domain->id); ?>" class="domain-select">
                                    </th>
                                    <td>
                                        <strong><?php echo esc_html($domain->domain_name ?: $domain->domain_url); ?></strong>
                                        <div class="domain-info">
                                            <code><?php echo esc_html($domain->domain_url); ?></code>
                                            <?php if ($domain->owner_name): ?>
                                                <br><small><?php echo esc_html($domain->owner_name); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo esc_attr($domain->status); ?>">
                                            <?php echo esc_html(ucfirst($domain->status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="verification-badge verification-<?php echo esc_attr($domain->verification_status); ?>">
                                            <?php echo esc_html(ucfirst($domain->verification_status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <code class="api-key-display">
                                            <?php echo esc_html(substr($domain->api_key, 0, 8) . '...'); ?>
                                        </code>
                                        <button type="button" class="button-link copy-api-key" data-api-key="<?php echo esc_attr($domain->api_key); ?>">
                                            <?php _e('Copy Full Key', 'affiliate-cross-domain'); ?>
                                        </button>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($domain->last_activity_at) {
                                            echo esc_html(affcd_time_ago($domain->last_activity_at));
                                        } else {
                                            _e('Never', 'affiliate-cross-domain');
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="domain-actions">
                                            <button type="button" class="button button-small test-domain-connection" 
                                                    data-domain-id="<?php echo esc_attr($domain->id); ?>">
                                                <?php _e('Test', 'affiliate-cross-domain'); ?>
                                            </button>
                                            <button type="button" class="button button-small verify-domain" 
                                                    data-domain-id="<?php echo esc_attr($domain->id); ?>">
                                                <?php _e('Verify', 'affiliate-cross-domain'); ?>
                                            </button>
                                            <button type="button" class="button button-small edit-domain" 
                                                    data-domain-id="<?php echo esc_attr($domain->id); ?>">
                                                <?php _e('Edit', 'affiliate-cross-domain'); ?>
                                            </button>
                                            <?php if ($domain->status !== 'active'): ?>
                                                <button type="button" class="button button-small activate-domain" 
                                                        data-domain-id="<?php echo esc_attr($domain->id); ?>">
                                                    <?php _e('Activate', 'affiliate-cross-domain'); ?>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="button button-small deactivate-domain" 
                                                        data-domain-id="<?php echo esc_attr($domain->id); ?>">
                                                    <?php _e('Deactivate', 'affiliate-cross-domain'); ?>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="button button-small button-link-delete delete-domain" 
                                                    data-domain-id="<?php echo esc_attr($domain->id); ?>"
                                                    data-domain-name="<?php echo esc_attr($domain->domain_name ?: $domain->domain_url); ?>">
                                                <?php _e('Delete', 'affiliate-cross-domain'); ?>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Edit Domain Modal -->
        <div id="edit-domain-modal" class="domain-modal" style="display: none;">
            <div class="domain-modal-content">
                <span class="domain-modal-close">&times;</span>
                <h2><?php _e('Edit Domain', 'affiliate-cross-domain'); ?></h2>
                <form id="edit-domain-form">
                    <input type="hidden" id="edit-domain-id" name="domain_id">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="edit-domain-name"><?php _e('Domain Name', 'affiliate-cross-domain'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="edit-domain-name" name="domain_name" class="regular-text">
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="edit-owner-email"><?php _e('Owner Email', 'affiliate-cross-domain'); ?></label>
                            </th>
                            <td>
                                <input type="email" id="edit-owner-email" name="owner_email" class="regular-text">
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="edit-owner-name"><?php _e('Owner Name', 'affiliate-cross-domain'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="edit-owner-name" name="owner_name" class="regular-text">
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="edit-status"><?php _e('Status', 'affiliate-cross-domain'); ?></label>
                            </th>
                            <td>
                                <select id="edit-status" name="status">
                                    <option value="active"><?php _e('Active', 'affiliate-cross-domain'); ?></option>
                                    <option value="inactive"><?php _e('Inactive', 'affiliate-cross-domain'); ?></option>
                                    <option value="suspended"><?php _e('Suspended', 'affiliate-cross-domain'); ?></option>
                                    <option value="pending"><?php _e('Pending', 'affiliate-cross-domain'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php _e('Update Domain', 'affiliate-cross-domain'); ?></button>
                        <button type="button" class="button" onclick="closeDomainModal()"><?php _e('Cancel', 'affiliate-cross-domain'); ?></button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings tab
     */
    private function render_settings_tab() {
        $settings = get_option('affcd_domain_settings', []);
        ?>
        <div class="domain-settings-content">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('affcd_save_domain_settings', 'affcd_nonce'); ?>
                <input type="hidden" name="action" value="affcd_save_domain_settings">
                
                <h2><?php _e('Global Domain Settings', 'affiliate-cross-domain'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Default Rate Limits', 'affiliate-cross-domain'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="number" name="default_rate_limit_minute" value="<?php echo esc_attr($settings['default_rate_limit_minute'] ?? 60); ?>" min="1" max="1000">
                                    <?php _e('Requests per minute', 'affiliate-cross-domain'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="number" name="default_rate_limit_hour" value="<?php echo esc_attr($settings['default_rate_limit_hour'] ?? 1000); ?>" min="1" max="10000">
                                    <?php _e('Requests per hour', 'affiliate-cross-domain'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="number" name="default_rate_limit_daily" value="<?php echo esc_attr($settings['default_rate_limit_daily'] ?? 10000); ?>" min="1" max="100000">
                                    <?php _e('Requests per day', 'affiliate-cross-domain'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Security Settings', 'affiliate-cross-domain'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="require_https" value="1" <?php checked(!empty($settings['require_https'])); ?>>
                                    <?php _e('Require HTTPS for all API requests', 'affiliate-cross-domain'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="enable_ip_whitelist" value="1" <?php checked(!empty($settings['enable_ip_whitelist'])); ?>>
                                    <?php _e('Enable IP address whitelisting', 'affiliate-cross-domain'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="log_all_requests" value="1" <?php checked(!empty($settings['log_all_requests'])); ?>>
                                    <?php _e('Log all API requests', 'affiliate-cross-domain'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Webhook Settings', 'affiliate-cross-domain'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="number" name="webhook_timeout" value="<?php echo esc_attr($settings['webhook_timeout'] ?? 30); ?>" min="5" max="120">
                                    <?php _e('Webhook timeout (seconds)', 'affiliate-cross-domain'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="number" name="webhook_retry_attempts" value="<?php echo esc_attr($settings['webhook_retry_attempts'] ?? 3); ?>" min="1" max="10">
                                    <?php _e('Retry attempts on failure', 'affiliate-cross-domain'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Settings', 'affiliate-cross-domain')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render security tab
     */
    private function render_security_tab() {
        $security_logs = $this->get_security_logs();
        $blocked_ips = get_option('affcd_blocked_ips', []);
        ?>
        <div class="security-content">
            <h2><?php _e('Security Dashboard', 'affiliate-cross-domain'); ?></h2>
            
            <div class="security-stats">
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo count($security_logs); ?></span>
                        <span class="stat-label"><?php _e('Security Events (24h)', 'affiliate-cross-domain'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo count($blocked_ips); ?></span>
                        <span class="stat-label"><?php _e('Blocked IPs', 'affiliate-cross-domain'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $this->get_failed_requests_count(); ?></span>
                        <span class="stat-label"><?php _e('Failed Requests', 'affiliate-cross-domain'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="security-section">
                <h3><?php _e('IP Whitelist Management', 'affiliate-cross-domain'); ?></h3>
                <form id="ip-whitelist-form">
                    <?php wp_nonce_field('affcd_manage_ips', 'affcd_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="new-ip-address"><?php _e('Add IP Address', 'affiliate-cross-domain'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="new-ip-address" name="ip_address" class="regular-text" 
                                       pattern="^(\d{1,3}\.){3}\d{1,3}$" placeholder="192.168.1.1">
                                <button type="button" id="add-ip-address" class="button"><?php _e('Add', 'affiliate-cross-domain'); ?></button>
                            </td>
                        </tr>
                    </table>
                    
                    <?php if (!empty($blocked_ips)): ?>
                        <h4><?php _e('Current IP Whitelist', 'affiliate-cross-domain'); ?></h4>
                        <table class="wp-list-table widefat">
                            <thead>
                                <tr>
                                    <th><?php _e('IP Address', 'affiliate-cross-domain'); ?></th>
                                    <th><?php _e('Added', 'affiliate-cross-domain'); ?></th>
                                    <th><?php _e('Actions', 'affiliate-cross-domain'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($blocked_ips as $ip => $data): ?>
                                    <tr>
                                        <td><code><?php echo esc_html($ip); ?></code></td>
                                        <td><?php echo esc_html(date_i18n(get_option('date_format'), $data['added'])); ?></td>
                                        <td>
                                            <button type="button" class="button button-small remove-ip" data-ip="<?php echo esc_attr($ip); ?>">
                                                <?php _e('Remove', 'affiliate-cross-domain'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="security-section">
                <h3><?php _e('Security Event Log', 'affiliate-cross-domain'); ?></h3>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Timestamp', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Event Type', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Domain', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('IP Address', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Status', 'affiliate-cross-domain'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($security_logs)): ?>
                            <tr>
                                <td colspan="5"><?php _e('No security events recorded', 'affiliate-cross-domain'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($security_logs as $log): ?>
                                <tr>
                                    <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($log['timestamp']))); ?></td>
                                    <td><?php echo esc_html($log['event']); ?></td>
                                    <td><?php echo esc_html($log['domain']); ?></td>
                                    <td><?php echo esc_html($log['ip_address']); ?></td>
                                    <td>
                                        <span class="status-<?php echo esc_attr($log['status']); ?>">
                                            <?php echo esc_html(ucfirst($log['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if (!empty($security_logs)): ?>
                    <p style="margin-top: 15px;">
                        <button type="button" class="button" onclick="affcdClearSecurityLogs()">
                            <?php _e('Clear Logs', 'affiliate-cross-domain'); ?>
                        </button>
                        <button type="button" class="button" onclick="affcdExportSecurityLogs()">
                            <?php _e('Export Logs', 'affiliate-cross-domain'); ?>
                        </button>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render analytics tab
     */
    private function render_analytics_tab($stats) {
        ?>
        <div class="analytics-content">
            <h2><?php _e('Domain Analytics', 'affiliate-cross-domain'); ?></h2>
            
            <div class="analytics-filters">
                <form id="analytics-filter-form">
                    <label for="date-range"><?php _e('Date Range:', 'affiliate-cross-domain'); ?></label>
                    <select id="date-range" name="date_range">
                        <option value="7"><?php _e('Last 7 days', 'affiliate-cross-domain'); ?></option>
                        <option value="30" selected><?php _e('Last 30 days', 'affiliate-cross-domain'); ?></option>
                        <option value="90"><?php _e('Last 90 days', 'affiliate-cross-domain'); ?></option>
                        <option value="365"><?php _e('Last year', 'affiliate-cross-domain'); ?></option>
                    </select>
                    
                    <label for="domain-filter"><?php _e('Domain:', 'affiliate-cross-domain'); ?></label>
                    <select id="domain-filter" name="domain">
                        <option value=""><?php _e('All Domains', 'affiliate-cross-domain'); ?></option>
                        <?php
                        $domains = $this->domain_manager->get_all_domains();
                        foreach ($domains as $domain): ?>
                            <option value="<?php echo esc_attr($domain->id); ?>">
                                <?php echo esc_html($domain->domain_name ?: $domain->domain_url); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="button" id="update-analytics" class="button"><?php _e('Update', 'affiliate-cross-domain'); ?></button>
                </form>
            </div>
            
            <div class="analytics-dashboard">
                <div class="analytics-grid">
                    <div class="analytics-card">
                        <h3><?php _e('API Requests', 'affiliate-cross-domain'); ?></h3>
                        <div class="metric-value"><?php echo number_format($stats['total_requests'] ?? 0); ?></div>
                        <div class="metric-change positive">+12.5% <?php _e('from last period', 'affiliate-cross-domain'); ?></div>
                    </div>
                    
                    <div class="analytics-card">
                        <h3><?php _e('Successful Validations', 'affiliate-cross-domain'); ?></h3>
                        <div class="metric-value"><?php echo number_format($stats['successful_validations'] ?? 0); ?></div>
                        <div class="metric-change positive">+8.3% <?php _e('from last period', 'affiliate-cross-domain'); ?></div>
                    </div>
                    
                    <div class="analytics-card">
                        <h3><?php _e('Error Rate', 'affiliate-cross-domain'); ?></h3>
                        <div class="metric-value"><?php echo number_format(($stats['error_rate'] ?? 0) * 100, 2); ?>%</div>
                        <div class="metric-change negative">-2.1% <?php _e('from last period', 'affiliate-cross-domain'); ?></div>
                    </div>
                    
                    <div class="analytics-card">
                        <h3><?php _e('Avg Response Time', 'affiliate-cross-domain'); ?></h3>
                        <div class="metric-value"><?php echo number_format($stats['avg_response_time'] ?? 0); ?>ms</div>
                        <div class="metric-change positive">-15ms <?php _e('from last period', 'affiliate-cross-domain'); ?></div>
                    </div>
                </div>
                
                <div class="analytics-charts">
                    <div class="chart-container">
                        <h4><?php _e('Request Volume Over Time', 'affiliate-cross-domain'); ?></h4>
                        <div id="requests-chart" style="height: 300px; background: #f9f9f9; display: flex; align-items: center; justify-content: center;">
                            <p><?php _e('Chart will be rendered here via JavaScript', 'affiliate-cross-domain'); ?></p>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <h4><?php _e('Top Performing Domains', 'affiliate-cross-domain'); ?></h4>
                        <div id="domains-chart" style="height: 300px; background: #f9f9f9; display: flex; align-items: center; justify-content: center;">
                            <p><?php _e('Chart will be rendered here via JavaScript', 'affiliate-cross-domain'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Add domain
     */
    public function add_domain() {
        if (!current_user_can('manage_affiliates') || !wp_verify_nonce($_POST['affcd_nonce'], 'affcd_add_domain')) {
            wp_die(__('Permission denied.', 'affiliate-cross-domain'));
        }

        $domain_url = sanitize_url($_POST['domain_url']);
        $domain_name = sanitize_text_field($_POST['domain_name']);
        $owner_email = sanitize_email($_POST['owner_email']);
        $owner_name = sanitize_text_field($_POST['owner_name']);
        $status = sanitize_text_field($_POST['status']);

        if (!filter_var($domain_url, FILTER_VALIDATE_URL)) {
            wp_redirect(add_query_arg('message', 'invalid_domain', wp_get_referer()));
            exit;
        }

        // Check if domain already exists
        $existing_domain = $this->domain_manager->get_domain_by_url($domain_url);
        if ($existing_domain) {
            wp_redirect(add_query_arg('message', 'domain_exists', wp_get_referer()));
            exit;
        }

        $domain_data = [
            'domain_url' => $domain_url,
            'domain_name' => $domain_name,
            'owner_email' => $owner_email,
            'owner_name' => $owner_name,
            'status' => $status,
        ];

        $result = $this->domain_manager->add_domain($domain_data);

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg('message', 'add_failed', wp_get_referer()));
        } else {
            wp_redirect(add_query_arg('message', 'domain_added', wp_get_referer()));
        }
        exit;
    }

    /**
     * Remove domain
     */
    public function remove_domain() {
        if (!current_user_can('manage_affiliates') || !wp_verify_nonce($_POST['affcd_nonce'], 'affcd_remove_domain')) {
            wp_die(__('Permission denied.', 'affiliate-cross-domain'));
        }

        $domain_id = absint($_POST['domain_id']);
        $result = $this->domain_manager->delete_domain($domain_id);

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg('message', 'remove_failed', wp_get_referer()));
        } else {
            wp_redirect(add_query_arg('message', 'domain_removed', wp_get_referer()));
        }
        exit;
    }

    /**
     * Save domain settings
     */
    public function save_domain_settings() {
        if (!current_user_can('manage_affiliates') || !wp_verify_nonce($_POST['affcd_nonce'], 'affcd_save_domain_settings')) {
            wp_die(__('Permission denied.', 'affiliate-cross-domain'));
        }

        $settings = [
            'default_rate_limit_minute' => absint($_POST['default_rate_limit_minute'] ?? 60),
            'default_rate_limit_hour' => absint($_POST['default_rate_limit_hour'] ?? 1000),
            'default_rate_limit_daily' => absint($_POST['default_rate_limit_daily'] ?? 10000),
            'require_https' => !empty($_POST['require_https']),
            'enable_ip_whitelist' => !empty($_POST['enable_ip_whitelist']),
            'log_all_requests' => !empty($_POST['log_all_requests']),
            'webhook_timeout' => absint($_POST['webhook_timeout'] ?? 30),
            'webhook_retry_attempts' => absint($_POST['webhook_retry_attempts'] ?? 3),
        ];

        update_option('affcd_domain_settings', $settings);
        wp_redirect(add_query_arg('message', 'settings_saved', wp_get_referer()));
        exit;
    }

    /**
     * AJAX: Test domain connection
     */
    public function ajax_test_domain_connection() {
        check_ajax_referer('affcd_ajax_nonce', 'nonce');

        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliate-cross-domain'));
        }

        $domain_id = absint($_POST['domain_id'] ?? 0);
        $domain = $this->domain_manager->get_domain($domain_id);

        if (!$domain) {
            wp_send_json_error(__('Domain not found', 'affiliate-cross-domain'));
        }

        $test_result = $this->test_domain_connection($domain);
        
        if ($test_result['success']) {
            wp_send_json_success($test_result);
        } else {
            wp_send_json_error($test_result);
        }
    }

    /**
     * AJAX: Generate API key
     */
    public function ajax_generate_api_key() {
        check_ajax_referer('affcd_ajax_nonce', 'nonce');

        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliate-cross-domain'));
        }

        $domain_id = absint($_POST['domain_id'] ?? 0);
        $new_api_key = $this->domain_manager->regenerate_api_key($domain_id);

        if (is_wp_error($new_api_key)) {
            wp_send_json_error($new_api_key->get_error_message());
        }

        wp_send_json_success([
            'api_key' => $new_api_key,
            'message' => __('API key regenerated successfully', 'affiliate-cross-domain')
        ]);
    }

    /**
     * AJAX: Send test webhook
     */
    public function ajax_send_test_webhook() {
        check_ajax_referer('affcd_ajax_nonce', 'nonce');

        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliate-cross-domain'));
        }

        $domain_id = absint($_POST['domain_id'] ?? 0);
        $domain = $this->domain_manager->get_domain($domain_id);

        if (!$domain || !$domain->webhook_url) {
            wp_send_json_error(__('Domain or webhook URL not found', 'affiliate-cross-domain'));
        }

        $test_payload = [
            'event' => 'test_webhook',
            'timestamp' => time(),
            'domain_id' => $domain_id,
            'test_data' => 'This is a test webhook from AffiliateWP Cross Domain'
        ];

        $result = $this->send_webhook($domain->webhook_url, $test_payload, $domain->webhook_secret);
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => __('Test webhook sent successfully', 'affiliate-cross-domain'),
                'response_code' => $result['response_code']
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message'],
                'response_code' => $result['response_code'] ?? 0
            ]);
        }
    }

    /**
     * AJAX: Verify domain
     */
    public function ajax_verify_domain() {
        check_ajax_referer('affcd_ajax_nonce', 'nonce');

        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliate-cross-domain'));
        }

        $domain_id = absint($_POST['domain_id'] ?? 0);
        $result = $this->domain_manager->verify_domain($domain_id);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Toggle domain status
     */
    public function ajax_toggle_domain_status() {
        check_ajax_referer('affcd_ajax_nonce', 'nonce');

        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliate-cross-domain'));
        }

        $domain_id = absint($_POST['domain_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');

        if (!in_array($status, ['active', 'inactive'])) {
            wp_send_json_error(__('Invalid status', 'affiliate-cross-domain'));
        }

        $result = $this->domain_manager->update_domain($domain_id, ['status' => $status]);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'message' => sprintf(__('Domain status updated to %s', 'affiliate-cross-domain'), $status)
        ]);
    }

    /**
     * AJAX: Delete domain
     */
    public function ajax_delete_domain() {
        check_ajax_referer('affcd_ajax_nonce', 'nonce');

        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliate-cross-domain'));
        }

        $domain_id = absint($_POST['domain_id'] ?? 0);
        $result = $this->domain_manager->delete_domain($domain_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'message' => __('Domain deleted successfully', 'affiliate-cross-domain')
        ]);
    }

    /**
     * AJAX: Bulk domain action
     */
    public function ajax_bulk_domain_action() {
        check_ajax_referer('affcd_ajax_nonce', 'nonce');

        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliate-cross-domain'));
        }

        $action = sanitize_text_field($_POST['bulk_action'] ?? '');
        $domain_ids = array_map('absint', $_POST['domain_ids'] ?? []);

        if (empty($action) || empty($domain_ids)) {
            wp_send_json_error(__('Invalid action or no domains selected', 'affiliate-cross-domain'));
        }

        $success_count = 0;
        $total_count = count($domain_ids);

        foreach ($domain_ids as $domain_id) {
            $result = false;
            
            switch ($action) {
                case 'activate':
                    $result = $this->domain_manager->update_domain($domain_id, ['status' => 'active']);
                    break;
                case 'deactivate':
                    $result = $this->domain_manager->update_domain($domain_id, ['status' => 'inactive']);
                    break;
                case 'verify':
                    $verify_result = $this->domain_manager->verify_domain($domain_id);
                    $result = $verify_result['success'] ? true : false;
                    break;
                case 'delete':
                    $result = $this->domain_manager->delete_domain($domain_id);
                    break;
            }
            
            if (!is_wp_error($result) && $result !== false) {
                $success_count++;
            }
        }

        if ($success_count === $total_count) {
            wp_send_json_success([
                'message' => sprintf(__('Bulk action completed successfully on %d domains', 'affiliate-cross-domain'), $success_count)
            ]);
        } else {
            wp_send_json_success([
                'message' => sprintf(__('Bulk action completed on %d of %d domains', 'affiliate-cross-domain'), $success_count, $total_count)
            ]);
        }
    }

    /**
     * AJAX: Refresh domain list
     */
    public function ajax_refresh_domain_list() {
        check_ajax_referer('affcd_ajax_nonce', 'nonce');

        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliate-cross-domain'));
        }

        $domains = $this->domain_manager->get_all_domains();
        
        ob_start();
        foreach ($domains as $domain) {
            // Render domain row HTML
            echo '<tr class="domain-list-item" data-domain-id="' . esc_attr($domain->id) . '">';
            // ... domain row content (same as in render_domains_tab)
            echo '</tr>';
        }
        $html = ob_get_clean();

        wp_send_json_success([
            'html' => $html,
            'total_domains' => count($domains)
        ]);
    }

    /**
     * Test domain connection
     */
    private function test_domain_connection($domain) {
        $test_url = trailingslashit($domain->domain_url) . 'wp-json/affiliate-client/v1/health';
        
        $response = wp_remote_get($test_url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $domain->api_key,
                'User-Agent' => 'AffiliateWP-CrossDomain/' . AFFCD_VERSION
            ]
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => __('Connection failed: ', 'affiliate-cross-domain') . $response->get_error_message(),
                'details' => $response->get_error_code()
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code === 200) {
            $data = json_decode($response_body, true);
            if (isset($data['status']) && $data['status'] === 'ok') {
                return [
                    'success' => true,
                    'message' => __('Connection successful', 'affiliate-cross-domain'),
                    'response_time' => $data['response_time'] ?? null,
                    'plugin_version' => $data['plugin_version'] ?? null
                ];
            }
        }

        return [
            'success' => false,
            'message' => sprintf(__('Connection failed with response code: %d', 'affiliate-cross-domain'), $response_code),
            'response_code' => $response_code
        ];
    }

    /**
     * Send webhook
     */
    private function send_webhook($webhook_url, $payload, $secret = '') {
        $body = json_encode($payload);
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'AffiliateWP-CrossDomain/' . AFFCD_VERSION
        ];

        if ($secret) {
            $signature = hash_hmac('sha256', $body, $secret);
            $headers['X-Signature-SHA256'] = 'sha256=' . $signature;
        }

        $response = wp_remote_post($webhook_url, [
            'timeout' => 30,
            'headers' => $headers,
            'body' => $body
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        
        return [
            'success' => $response_code >= 200 && $response_code < 300,
            'response_code' => $response_code,
            'message' => $response_code >= 200 && $response_code < 300 
                ? __('Webhook sent successfully', 'affiliate-cross-domain')
                : sprintf(__('Webhook failed with code: %d', 'affiliate-cross-domain'), $response_code)
        ];
    }

    /**
     * Get domain statistics
     */
    private function get_domain_statistics() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'affcd_api_logs';
        
        return [
            'total_requests' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
            'successful_validations' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'success' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
            'error_rate' => $wpdb->get_var("SELECT COUNT(*) / (SELECT COUNT(*) FROM {$table_name} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) FROM {$table_name} WHERE status = 'error' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
            'avg_response_time' => $wpdb->get_var("SELECT AVG(response_time) FROM {$table_name} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")
        ];
    }

    /**
     * Get security logs
     */
    private function get_security_logs($limit = 50) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'affcd_security_logs';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$table_name} 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY created_at DESC 
            LIMIT %d
        ", $limit), ARRAY_A);
    }

    /**
     * Get failed requests count
     */
    private function get_failed_requests_count() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'affcd_api_logs';
        
        return $wpdb->get_var("
            SELECT COUNT(*) FROM {$table_name} 
            WHERE status IN ('error', 'blocked', 'rate_limited') 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
    }
}

// Initialise the domain management class
new AFFCD_Domain_Management();