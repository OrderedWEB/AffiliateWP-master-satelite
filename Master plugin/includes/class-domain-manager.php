<?php
/**
 * Domain Management for WP Affiliate Cross Domain Plugin Suite
 *
 * Administrative interface for managing Authorized client domains,
 * API keys, webhook configurations, and domain-specific settings.
 *
 * Filename: admin/domain-management.php
 * Plugin: WP Affiliate Cross Domain Plugin Suite (Master)
 *
 * @package AffiliateWP_Cross_Domain_Plugin_Suite_Master
 * @version 1.0.0
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
        
        // Initialize domain manager
        $this->domain_manager = new AFFCD_Domain_Manager();
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'affiliate-wp',
            __('Domain Management', 'affiliatewp-cross-domain-plugin-suite'),
            __('Domain Management', 'affiliatewp-cross-domain-plugin-suite'),
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
                'testing' => __('Testing...', 'affiliatewp-cross-domain-plugin-suite'),
                'success' => __('Success!', 'affiliatewp-cross-domain-plugin-suite'),
                'error' => __('Error', 'affiliatewp-cross-domain-plugin-suite'),
                'confirmRemove' => __('Are you sure you want to remove this domain?', 'affiliatewp-cross-domain-plugin-suite'),
                'confirmBulkAction' => __('Are you sure you want to apply "%s" to %d selected domains?', 'affiliatewp-cross-domain-plugin-suite'),
                'selectBulkAction' => __('Please select a bulk action.', 'affiliatewp-cross-domain-plugin-suite'),
                'selectDomains' => __('Please select at least one domain.', 'affiliatewp-cross-domain-plugin-suite'),
                'processing' => __('Processing...', 'affiliatewp-cross-domain-plugin-suite'),
                'verifying' => __('Verifying...', 'affiliatewp-cross-domain-plugin-suite'),
                'verified' => __('Verified', 'affiliatewp-cross-domain-plugin-suite'),
                'verificationFailed' => __('Verification failed', 'affiliatewp-cross-domain-plugin-suite'),
                'verificationError' => __('Verification error occurred', 'affiliatewp-cross-domain-plugin-suite'),
                'addDomain' => __('Add Domain', 'affiliatewp-cross-domain-plugin-suite'),
                'updateDomain' => __('Update Domain', 'affiliatewp-cross-domain-plugin-suite'),
                'updating' => __('Updating...', 'affiliatewp-cross-domain-plugin-suite'),
                'deleting' => __('Deleting...', 'affiliatewp-cross-domain-plugin-suite'),
                'delete' => __('Delete', 'affiliatewp-cross-domain-plugin-suite'),
                'verify' => __('Verify', 'affiliatewp-cross-domain-plugin-suite'),
                'statusUpdateFailed' => __('Failed to update domain status', 'affiliatewp-cross-domain-plugin-suite'),
                'statusUpdateError' => __('Error updating domain status', 'affiliatewp-cross-domain-plugin-suite'),
                'bulkActionFailed' => __('Bulk action failed', 'affiliatewp-cross-domain-plugin-suite'),
                'bulkActionError' => __('Error performing bulk action', 'affiliatewp-cross-domain-plugin-suite'),
                'addDomainFailed' => __('Failed to add domain', 'affiliatewp-cross-domain-plugin-suite'),
                'addDomainError' => __('Error adding domain', 'affiliatewp-cross-domain-plugin-suite'),
                'updateDomainFailed' => __('Failed to update domain', 'affiliatewp-cross-domain-plugin-suite'),
                'updateDomainError' => __('Error updating domain', 'affiliatewp-cross-domain-plugin-suite'),
                'deleteDomainFailed' => __('Failed to delete domain', 'affiliatewp-cross-domain-plugin-suite'),
                'deleteDomainError' => __('Error deleting domain', 'affiliatewp-cross-domain-plugin-suite'),
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
                    <?php _e('Domains', 'affiliatewp-cross-domain-plugin-suite'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=affcd-domain-management&tab=settings')); ?>" 
                   class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Settings', 'affiliatewp-cross-domain-plugin-suite'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=affcd-domain-management&tab=security')); ?>" 
                   class="nav-tab <?php echo $active_tab === 'security' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Security', 'affiliatewp-cross-domain-plugin-suite'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=affcd-domain-management&tab=analytics')); ?>" 
                   class="nav-tab <?php echo $active_tab === 'analytics' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Analytics', 'affiliatewp-cross-domain-plugin-suite'); ?>
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
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Domain added successfully.', 'affiliatewp-cross-domain-plugin-suite') . '</p></div>';
                break;
            case 'domain_updated':
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Domain updated successfully.', 'affiliatewp-cross-domain-plugin-suite') . '</p></div>';
                break;
            case 'domain_removed':
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Domain removed successfully.', 'affiliatewp-cross-domain-plugin-suite') . '</p></div>';
                break;
            case 'invalid_domain':
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Invalid domain URL provided.', 'affiliatewp-cross-domain-plugin-suite') . '</p></div>';
                break;
            case 'domain_exists':
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Domain already exists.', 'affiliatewp-cross-domain-plugin-suite') . '</p></div>';
                break;
            case 'settings_saved':
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully.', 'affiliatewp-cross-domain-plugin-suite') . '</p></div>';
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
                        <span class="stat-number"><?php echo (int) count($domains); ?></span>
                        <span class="stat-label"><?php _e('Total Domains', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo (int) count(array_filter($domains, function($d) { return $d->status === 'active'; })); ?></span>
                        <span class="stat-label"><?php _e('Active Domains', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo (int) count(array_filter($domains, function($d) { return ($d->verification_status ?? '') === 'verified'; })); ?></span>
                        <span class="stat-label"><?php _e('Verified Domains', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                    </div>
                </div>
            </div>

            <div class="domain-management-section">
                <h2><?php _e('Add New Domain', 'affiliatewp-cross-domain-plugin-suite'); ?></h2>
                
                <form id="add-domain-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('affcd_add_domain', 'affcd_nonce'); ?>
                    <input type="hidden" name="action" value="affcd_add_domain">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="domain_url"><?php _e('Domain URL', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                            </th>
                            <td>
                                <input type="url" 
                                       id="domain_url" 
                                       name="domain_url" 
                                       class="regular-text" 
                                       placeholder="https://example.com"
                                       required>
                                <p class="description"><?php _e('Enter the full domain URL including https://', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="domain_name"><?php _e('Domain Name', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="domain_name" 
                                       name="domain_name" 
                                       class="regular-text" 
                                       placeholder="Example Site">
                                <p class="description"><?php _e('Friendly name for this domain', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="owner_email"><?php _e('Owner Email', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
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
                                <label for="owner_name"><?php _e('Owner Name', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
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
                                <label for="status"><?php _e('Initial Status', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                            </th>
                            <td>
                                <select id="status" name="status">
                                    <option value="pending"><?php _e('Pending', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                    <option value="active"><?php _e('Active', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                    <option value="inactive"><?php _e('Inactive', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Add Domain', 'affiliatewp-cross-domain-plugin-suite')); ?>
                </form>
            </div>

            <div class="domain-management-section">
                <h2><?php _e('Authorized Domains', 'affiliatewp-cross-domain-plugin-suite'); ?></h2>
                
                <?php if (empty($domains)): ?>
                    <p><?php _e('No domains configured yet. Add your first domain above.', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                <?php else: ?>
                    <div class="tablenav top">
                        <div class="alignleft actions bulkactions">
                            <select id="bulk-actions" name="action">
                                <option value="-1"><?php _e('Bulk Actions', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                <option value="activate"><?php _e('Activate', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                <option value="deactivate"><?php _e('Deactivate', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                <option value="verify"><?php _e('Verify', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                <option value="delete"><?php _e('Delete', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                            </select>
                            <button type="button" id="apply-bulk-action" class="button action">
                                <?php _e('Apply', 'affiliatewp-cross-domain-plugin-suite'); ?>
                            </button>
                        </div>
                        <div class="tablenav-pages">
                            <span class="displaying-num"><?php printf(_n('%s item', '%s items', count($domains), 'affiliatewp-cross-domain-plugin-suite'), number_format_i18n(count($domains))); ?></span>
                        </div>
                    </div>
                    
                    <table class="wp-list-table widefat fixed striped" id="affcd-domain-table">
                        <thead>
                            <tr>
                                <td class="manage-column column-cb check-column">
                                    <input type="checkbox" id="select-all-domains">
                                </td>
                                <th class="manage-column"><?php _e('Domain', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                <th class="manage-column"><?php _e('Status', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                <th class="manage-column"><?php _e('Verification', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                <th class="manage-column"><?php _e('API Key', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                <th class="manage-column"><?php _e('Last Activity', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                <th class="manage-column"><?php _e('Actions', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="affcd-domain-table-body">
                            <?php foreach ($domains as $domain): ?>
                                <tr class="domain-list-item" data-domain-id="<?php echo esc_attr($domain->id); ?>">
                                    <th class="check-column">
                                        <input type="checkbox" name="domain_ids[]" value="<?php echo esc_attr($domain->id); ?>" class="domain-select">
                                    </th>
                                    <td>
                                        <strong><?php echo esc_html($domain->domain_name ?: $domain->domain_url); ?></strong>
                                        <div class="domain-info">
                                            <code><?php echo esc_html($domain->domain_url); ?></code>
                                            <?php if (!empty($domain->owner_name)): ?>
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
                                        <span class="verification-badge verification-<?php echo esc_attr($domain->verification_status ?? 'pending'); ?>">
                                            <?php echo esc_html(ucfirst($domain->verification_status ?? 'pending')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <code class="api-key-display">
                                            <?php echo esc_html(substr($domain->api_key, 0, 8) . '...'); ?>
                                        </code>
                                        <button type="button" class="button-link copy-api-key" data-api-key="<?php echo esc_attr($domain->api_key); ?>">
                                            <?php _e('Copy Full Key', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                        </button>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!empty($domain->last_activity_at)) {
                                            echo esc_html(affcd_time_ago($domain->last_activity_at));
                                        } else {
                                            _e('Never', 'affiliatewp-cross-domain-plugin-suite');
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="domain-actions">
                                            <button type="button" class="button button-small test-domain-connection" 
                                                    data-domain-id="<?php echo esc_attr($domain->id); ?>">
                                                <?php _e('Test', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                            </button>
                                            <button type="button" class="button button-small verify-domain" 
                                                    data-domain-id="<?php echo esc_attr($domain->id); ?>">
                                                <?php _e('Verify', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                            </button>
                                            <button type="button" class="button button-small edit-domain" 
                                                    data-domain-id="<?php echo esc_attr($domain->id); ?>">
                                                <?php _e('Edit', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                            </button>
                                            <?php if ($domain->status !== 'active'): ?>
                                                <button type="button" class="button button-small activate-domain" 
                                                        data-domain-id="<?php echo esc_attr($domain->id); ?>">
                                                    <?php _e('Activate', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="button button-small deactivate-domain" 
                                                        data-domain-id="<?php echo esc_attr($domain->id); ?>">
                                                    <?php _e('Deactivate', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="button button-small button-link-delete delete-domain" 
                                                    data-domain-id="<?php echo esc_attr($domain->id); ?>"
                                                    data-domain-name="<?php echo esc_attr($domain->domain_name ?: $domain->domain_url); ?>">
                                                <?php _e('Delete', 'affiliatewp-cross-domain-plugin-suite'); ?>
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
                <h2><?php _e('Edit Domain', 'affiliatewp-cross-domain-plugin-suite'); ?></h2>
                <form id="edit-domain-form">
                    <input type="hidden" id="edit-domain-id" name="domain_id">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="edit-domain-name"><?php _e('Domain Name', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="edit-domain-name" name="domain_name" class="regular-text">
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="edit-owner-email"><?php _e('Owner Email', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                            </th>
                            <td>
                                <input type="email" id="edit-owner-email" name="owner_email" class="regular-text">
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="edit-owner-name"><?php _e('Owner Name', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="edit-owner-name" name="owner_name" class="regular-text">
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="edit-status"><?php _e('Status', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                            </th>
                            <td>
                                <select id="edit-status" name="status">
                                    <option value="active"><?php _e('Active', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                    <option value="inactive"><?php _e('Inactive', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                    <option value="suspended"><?php _e('Suspended', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                    <option value="pending"><?php _e('Pending', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php _e('Update Domain', 'affiliatewp-cross-domain-plugin-suite'); ?></button>
                        <button type="button" class="button" onclick="closeDomainModal()"><?php _e('Cancel', 'affiliatewp-cross-domain-plugin-suite'); ?></button>
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
                
                <h2><?php _e('Global Domain Settings', 'affiliatewp-cross-domain-plugin-suite'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Default Rate Limits', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="number" name="default_rate_limit_minute" value="<?php echo esc_attr($settings['default_rate_limit_minute'] ?? 60); ?>" min="1" max="1000">
                                    <?php _e('Requests per minute', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="number" name="default_rate_limit_hour" value="<?php echo esc_attr($settings['default_rate_limit_hour'] ?? 1000); ?>" min="1" max="10000">
                                    <?php _e('Requests per hour', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="number" name="default_rate_limit_daily" value="<?php echo esc_attr($settings['default_rate_limit_daily'] ?? 10000); ?>" min="1" max="100000">
                                    <?php _e('Requests per day', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Security Settings', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="require_https" value="1" <?php checked(!empty($settings['require_https'])); ?>>
                                    <?php _e('Require HTTPS for all API requests', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="enable_ip_whitelist" value="1" <?php checked(!empty($settings['enable_ip_whitelist'])); ?>>
                                    <?php _e('Enable IP address whitelisting', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="log_all_requests" value="1" <?php checked(!empty($settings['log_all_requests'])); ?>>
                                    <?php _e('Log all API requests', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Webhook Settings', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="number" name="webhook_timeout" value="<?php echo esc_attr($settings['webhook_timeout'] ?? 30); ?>" min="5" max="120">
                                    <?php _e('Webhook timeout (seconds)', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="number" name="webhook_retry_attempts" value="<?php echo esc_attr($settings['webhook_retry_attempts'] ?? 3); ?>" min="1" max="10">
                                    <?php _e('Retry attempts on failure', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Settings', 'affiliatewp-cross-domain-plugin-suite')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render security tab
     */
    private function render_security_tab() {
        $security_logs = $this->get_security_logs();
        // Use the same option used by the security manager
        $whitelisted_ips = get_option('affcd_whitelisted_ips', []);
        ?>
        <div class="security-content">
            <h2><?php _e('Security Dashboard', 'affiliatewp-cross-domain-plugin-suite'); ?></h2>
            
            <div class="security-stats">
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo (int) count($security_logs); ?></span>
                        <span class="stat-label"><?php _e('Security Events (24h)', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo (int) count($whitelisted_ips); ?></span>
                        <span class="stat-label"><?php _e('Whitelisted IPs', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo (int) $this->get_failed_requests_count(); ?></span>
                        <span class="stat-label"><?php _e('Failed Requests', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="security-section">
                <h3><?php _e('IP Whitelist Management', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                <form id="ip-whitelist-form">
                    <?php wp_nonce_field('affcd_manage_ips', 'affcd_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="new-ip-address"><?php _e('Add IP Address', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="new-ip-address" name="ip_address" class="regular-text" 
                                       pattern="^(\d{1,3}\.){3}\d{1,3}$" placeholder="192.168.1.1">
                                <button type="button" id="add-ip-address" class="button"><?php _e('Add', 'affiliatewp-cross-domain-plugin-suite'); ?></button>
                            </td>
                        </tr>
                    </table>
                    
                    <?php if (!empty($whitelisted_ips)): ?>
                        <h4><?php _e('Current IP Whitelist', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                        <table class="wp-list-table widefat">
                            <thead>
                                <tr>
                                    <th><?php _e('IP Address', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Added', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Actions', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($whitelisted_ips as $ip => $data): ?>
                                    <tr>
                                        <td><code><?php echo esc_html($ip); ?></code></td>
                                        <td><?php 
                                            $added_at = $data['added_at'] ?? ($data['added'] ?? '');
                                            echo esc_html($added_at ? date_i18n(get_option('date_format'), strtotime($added_at)) : '—');
                                        ?></td>
                                        <td>
                                            <button type="button" class="button button-small remove-ip" data-ip="<?php echo esc_attr($ip); ?>">
                                                <?php _e('Remove', 'affiliatewp-cross-domain-plugin-suite'); ?>
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
                <h3><?php _e('Security Event Log', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Timestamp', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Event Type', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Domain', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('IP Address', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Severity', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($security_logs)): ?>
                            <tr>
                                <td colspan="5"><?php _e('No security events recorded', 'affiliatewp-cross-domain-plugin-suite'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($security_logs as $log): ?>
                                <tr>
                                    <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($log['created_at']))); ?></td>
                                    <td><?php echo esc_html($log['event_type'] ?? ''); ?></td>
                                    <td><?php echo esc_html($log['domain'] ?? ''); ?></td>
                                    <td><?php echo esc_html($log['source_ip'] ?? $log['ip_address'] ?? ''); ?></td>
                                    <td>
                                        <span class="status-<?php echo esc_attr($log['severity']); ?>">
                                            <?php echo esc_html(ucfirst($log['severity'])); ?>
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
                            <?php _e('Clear Logs', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </button>
                        <button type="button" class="button" onclick="affcdExportSecurityLogs()">
                            <?php _e('Export Logs', 'affiliatewp-cross-domain-plugin-suite'); ?>
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
            <h2><?php _e('Domain Analytics', 'affiliatewp-cross-domain-plugin-suite'); ?></h2>
            
            <div class="analytics-filters">
                <form id="analytics-filter-form">
                    <label for="date-range"><?php _e('Date Range:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                    <select id="date-range" name="date_range">
                        <option value="7"><?php _e('Last 7 days', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                        <option value="30" selected><?php _e('Last 30 days', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                        <option value="90"><?php _e('Last 90 days', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                        <option value="365"><?php _e('Last year', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                    </select>
                    
                    <label for="domain-filter"><?php _e('Domain:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                    <select id="domain-filter" name="domain">
                        <option value=""><?php _e('All Domains', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                        <?php
                        $domains = $this->domain_manager->get_all_domains();
                        foreach ($domains as $domain): ?>
                            <option value="<?php echo esc_attr($domain->id); ?>">
                                <?php echo esc_html($domain->domain_name ?: $domain->domain_url); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="button" id="update-analytics" class="button"><?php _e('Update', 'affiliatewp-cross-domain-plugin-suite'); ?></button>
                </form>
            </div>
            
            <div class="analytics-dashboard">
                <div class="analytics-grid">
                    <div class="analytics-card">
                        <h3><?php _e('API Requests', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                        <div class="metric-value"><?php echo number_format_i18n($stats['total_requests'] ?? 0); ?></div>
                        <div class="metric-change positive">+12.5% <?php _e('from last period', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                    </div>
                    
                    <div class="analytics-card">
                        <h3><?php _e('Successful Validations', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                        <div class="metric-value"><?php echo number_format_i18n($stats['successful_validations'] ?? 0); ?></div>
                        <div class="metric-change positive">+8.3% <?php _e('from last period', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                    </div>
                    
                    <div class="analytics-card">
                        <h3><?php _e('Error Rate', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                        <div class="metric-value"><?php echo number_format_i18n(round(($stats['error_rate'] ?? 0) * 100, 2)); ?>%</div>
                        <div class="metric-change negative">-2.1% <?php _e('from last period', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                    </div>
                    
                    <div class="analytics-card">
                        <h3><?php _e('Avg Response Time', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                        <div class="metric-value"><?php echo number_format_i18n($stats['avg_response_time'] ?? 0); ?>ms</div>
                        <div class="metric-change positive">-15ms <?php _e('from last period', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                    </div>
                </div>
                
                <div class="analytics-charts">
                    <div class="chart-container">
                        <h4><?php _e('Request Volume Over Time', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                        <div id="requests-chart" style="height: 300px; background: #f9f9f9; display: flex; align-items: center; justify-content: center;">
                            <p><?php _e('Chart will be rendered here via JavaScript', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <h4><?php _e('Top Performing Domains', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                        <div id="domains-chart" style="height: 300px; background: #f9f9f9; display: flex; align-items: center; justify-content: center;">
                            <p><?php _e('Chart will be rendered here via JavaScript', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
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
        if (!current_user_can('manage_affiliates') || !wp_verify_nonce($_POST['affcd_nonce'] ?? '', 'affcd_add_domain')) {
            wp_die(__('Permission denied.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $domain_url = sanitize_url($_POST['domain_url'] ?? '');
        $domain_name = sanitize_text_field($_POST['domain_name'] ?? '');
        $owner_email = sanitize_email($_POST['owner_email'] ?? '');
        $owner_name = sanitize_text_field($_POST['owner_name'] ?? '');
        $status = sanitize_text_field($_POST['status'] ?? 'pending');

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
        if (!current_user_can('manage_affiliates') || !wp_verify_nonce($_POST['affcd_nonce'] ?? '', 'affcd_remove_domain')) {
            wp_die(__('Permission denied.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $domain_id = absint($_POST['domain_id'] ?? 0);
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
        if (!current_user_can('manage_affiliates') || !wp_verify_nonce($_POST['affcd_nonce'] ?? '', 'affcd_save_domain_settings')) {
            wp_die(__('Permission denied.', 'affiliatewp-cross-domain-plugin-suite'));
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
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $domain_id = absint($_POST['domain_id'] ?? 0);
        $domain = $this->domain_manager->get_domain($domain_id);

        if (!$domain) {
            wp_send_json_error(__('Domain not found', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $test_result = $this->test_domain_connection($domain);
        
        if (!empty($test_result['success'])) {
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
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $domain_id = absint($_POST['domain_id'] ?? 0);
        $new_api_key = $this->domain_manager->regenerate_api_key($domain_id);

        if (is_wp_error($new_api_key)) {
            wp_send_json_error($new_api_key->get_error_message());
        }

        wp_send_json_success([
            'api_key' => $new_api_key,
            'message' => __('API key regenerated successfully', 'affiliatewp-cross-domain-plugin-suite')
        ]);
    }

    /**
     * AJAX: Send test webhook
     */
    public function ajax_send_test_webhook() {
        check_ajax_referer('affcd_ajax_nonce', 'nonce');

        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $domain_id = absint($_POST['domain_id'] ?? 0);
        $domain = $this->domain_manager->get_domain($domain_id);

        if (!$domain || empty($domain->webhook_url)) {
            wp_send_json_error(__('Domain or webhook URL not found', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $test_payload = [
            'event' => 'test_webhook',
            'timestamp' => time(),
            'domain_id' => $domain_id,
            'test_data' => 'This is a test webhook from AffiliateWP Cross Domain'
        ];

        $result = $this->send_webhook($domain->webhook_url, $test_payload, $domain->webhook_secret ?? '');
        
        if (!empty($result['success'])) {
            wp_send_json_success([
                'message' => __('Test webhook sent successfully', 'affiliatewp-cross-domain-plugin-suite'),
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
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $domain_id = absint($_POST['domain_id'] ?? 0);
        $result = $this->domain_manager->verify_domain($domain_id);

        if (!empty($result['success'])) {
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
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $domain_id = absint($_POST['domain_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');

        if (!in_array($status, ['active', 'inactive'], true)) {
            wp_send_json_error(__('Invalid status', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $result = $this->domain_manager->update_domain($domain_id, ['status' => $status]);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'message' => sprintf(__('Domain status updated to %s', 'affiliatewp-cross-domain-plugin-suite'), $status)
        ]);
    }

    /**
     * AJAX: Delete domain
     */
    public function ajax_delete_domain() {
        check_ajax_referer('affcd_ajax_nonce', 'nonce');

        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $domain_id = absint($_POST['domain_id'] ?? 0);
        $result = $this->domain_manager->delete_domain($domain_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'message' => __('Domain deleted successfully', 'affiliatewp-cross-domain-plugin-suite')
        ]);
    }

    /**
     * AJAX: Bulk domain action
     */
    public function ajax_bulk_domain_action() {
        check_ajax_referer('affcd_ajax_nonce', 'nonce');

        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $action = sanitize_text_field($_POST['bulk_action'] ?? '');
        $domain_ids = array_map('absint', $_POST['domain_ids'] ?? []);

        if (empty($action) || empty($domain_ids)) {
            wp_send_json_error(__('Invalid action or no domains selected', 'affiliatewp-cross-domain-plugin-suite'));
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
                    $result = !empty($verify_result['success']);
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
                'message' => sprintf(__('Bulk action completed successfully on %d domains', 'affiliatewp-cross-domain-plugin-suite'), $success_count)
            ]);
        } else {
            wp_send_json_success([
                'message' => sprintf(__('Bulk action completed on %d of %d domains', 'affiliatewp-cross-domain-plugin-suite'), $success_count, $total_count)
            ]);
        }
    }

    /**
     * AJAX: Refresh domain list (returns rendered <tr> rows)
     */
    public function ajax_refresh_domain_list() {
        check_ajax_referer('affcd_ajax_nonce', 'nonce');

        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $domains = $this->domain_manager->get_all_domains();
        
        ob_start();
        foreach ($domains as $domain) {
            ?>
            <tr class="domain-list-item" data-domain-id="<?php echo esc_attr($domain->id); ?>">
                <th class="check-column">
                    <input type="checkbox" name="domain_ids[]" value="<?php echo esc_attr($domain->id); ?>" class="domain-select">
                </th>
                <td>
                    <strong><?php echo esc_html($domain->domain_name ?: $domain->domain_url); ?></strong>
                    <div class="domain-info">
                        <code><?php echo esc_html($domain->domain_url); ?></code>
                        <?php if (!empty($domain->owner_name)): ?>
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
                    <span class="verification-badge verification-<?php echo esc_attr($domain->verification_status ?? 'pending'); ?>">
                        <?php echo esc_html(ucfirst($domain->verification_status ?? 'pending')); ?>
                    </span>
                </td>
                <td>
                    <code class="api-key-display">
                        <?php echo esc_html(substr($domain->api_key, 0, 8) . '...'); ?>
                    </code>
                    <button type="button" class="button-link copy-api-key" data-api-key="<?php echo esc_attr($domain->api_key); ?>">
                        <?php _e('Copy Full Key', 'affiliatewp-cross-domain-plugin-suite'); ?>
                    </button>
                </td>
                <td>
                    <?php 
                    if (!empty($domain->last_activity_at)) {
                        echo esc_html(affcd_time_ago($domain->last_activity_at));
                    } else {
                        _e('Never', 'affiliatewp-cross-domain-plugin-suite');
                    }
                    ?>
                </td>
                <td>
                    <div class="domain-actions">
                        <button type="button" class="button button-small test-domain-connection" 
                                data-domain-id="<?php echo esc_attr($domain->id); ?>">
                            <?php _e('Test', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </button>
                        <button type="button" class="button button-small verify-domain" 
                                data-domain-id="<?php echo esc_attr($domain->id); ?>">
                            <?php _e('Verify', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </button>
                        <button type="button" class="button button-small edit-domain" 
                                data-domain-id="<?php echo esc_attr($domain->id); ?>">
                            <?php _e('Edit', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </button>
                        <?php if ($domain->status !== 'active'): ?>
                            <button type="button" class="button button-small activate-domain" 
                                    data-domain-id="<?php echo esc_attr($domain->id); ?>">
                                <?php _e('Activate', 'affiliatewp-cross-domain-plugin-suite'); ?>
                            </button>
                        <?php else: ?>
                            <button type="button" class="button button-small deactivate-domain" 
                                    data-domain-id="<?php echo esc_attr($domain->id); ?>">
                                <?php _e('Deactivate', 'affiliatewp-cross-domain-plugin-suite'); ?>
                            </button>
                        <?php endif; ?>
                        <button type="button" class="button button-small button-link-delete delete-domain" 
                                data-domain-id="<?php echo esc_attr($domain->id); ?>"
                                data-domain-name="<?php echo esc_attr($domain->domain_name ?: $domain->domain_url); ?>">
                            <?php _e('Delete', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </button>
                    </div>
                </td>
            </tr>
            <?php
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
                'Authorization' => 'Bearer ' . $domain->api_key, // fixed header name
                'User-Agent' => 'AffiliateWP-CrossDomain/' . AFFCD_VERSION
            ]
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => __('Connection failed: ', 'affiliatewp-cross-domain-plugin-suite') . $response->get_error_message(),
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
                    'message' => __('Connection successful', 'affiliatewp-cross-domain-plugin-suite'),
                    'response_time' => $data['response_time'] ?? null,
                    'plugin_version' => $data['plugin_version'] ?? null
                ];
            }
        }

        return [
            'success' => false,
            'message' => sprintf(__('Connection failed with response code: %d', 'affiliatewp-cross-domain-plugin-suite'), $response_code),
            'response_code' => $response_code
        ];
    }

    /**
     * Send webhook
     */
    private function send_webhook($webhook_url, $payload, $secret = '') {
        $body = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
                ? __('Webhook sent successfully', 'affiliatewp-cross-domain-plugin-suite')
                : sprintf(__('Webhook failed with code: %d', 'affiliatewp-cross-domain-plugin-suite'), $response_code)
        ];
    }

    /**
     * Get domain statistics
     */
    private function get_domain_statistics() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'affcd_api_logs';

        // Avoid division by zero and ensure decimal output for error_rate
        $total_requests = (int) $wpdb->get_var("
            SELECT COUNT(*) FROM {$table_name} 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");

        $successful_validations = (int) $wpdb->get_var("
            SELECT COUNT(*) FROM {$table_name}
            WHERE status = 'success' 
              AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");

        $error_rate = (float) $wpdb->get_var("
            SELECT COALESCE(
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0),
                0
            ) AS error_rate
            FROM {$table_name}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");

        $avg_response_time = (float) $wpdb->get_var("
            SELECT AVG(response_time) FROM {$table_name}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        return [
            'total_requests' => $total_requests,
            'successful_validations' => $successful_validations,
            'error_rate' => $error_rate,
            'avg_response_time' => round($avg_response_time, 2)
        ];
    }

    /**
     * Get security logs (last 24h)
     */
    private function get_security_logs($limit = 50) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'affcd_security_logs';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT event_type, severity, domain, source_ip, created_at
            FROM {$table_name} 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY created_at DESC 
            LIMIT %d
        ", $limit), ARRAY_A);
    }

    /**
     * Get failed requests count (24h)
     */
    private function get_failed_requests_count() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'affcd_api_logs';
        
        return (int) $wpdb->get_var("
            SELECT COUNT(*) FROM {$table_name} 
            WHERE status IN ('error', 'blocked', 'rate_limited') 
              AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
    }
}

// Initialize the domain management class
new AFFCD_Domain_Management();
