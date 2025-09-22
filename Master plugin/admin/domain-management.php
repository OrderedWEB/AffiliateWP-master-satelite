<?php
/**
 * Domain Management for Affiliate Cross Domain Full
 *
 * Administrative interface for managing authorized client domains,
 * API keys, webhook configurations, and domain-specific settings.
 *
 * @package AffiliateWP_Cross_Domain_Full
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Domain_Management {

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
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
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
        wp_enqueue_script('affcd-domain-management', 
            AFFCD_PLUGIN_URL . 'assets/js/domain-management.js', 
            ['jquery'], 
            AFFCD_VERSION, 
            true
        );

        wp_localize_script('affcd-domain-management', 'affcdAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('affcd_ajax_nonce'),
            'strings' => [
                'testing' => __('Testing...', 'affiliate-cross-domain'),
                'success' => __('Success!', 'affiliate-cross-domain'),
                'error' => __('Error', 'affiliate-cross-domain'),
                'confirmRemove' => __('Are you sure you want to remove this domain?', 'affiliate-cross-domain'),
            ]
        ]);
    }

    /**
     * Render domain management page
     */
    public function render_domain_management_page() {
        $active_tab = $_GET['tab'] ?? 'domains';
        ?>
        <div class="wrap">
            <h1><?php _e('Cross-Domain Management', 'affiliate-cross-domain'); ?></h1>

            <?php $this->display_admin_notices(); ?>

            <!-- Tabs -->
            <nav class="nav-tab-wrapper">
                <a href="?page=affcd-domain-management&tab=domains" 
                   class="nav-tab <?php echo $active_tab === 'domains' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Authorized Domains', 'affiliate-cross-domain'); ?>
                </a>
                <a href="?page=affcd-domain-management&tab=api" 
                   class="nav-tab <?php echo $active_tab === 'api' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('API Configuration', 'affiliate-cross-domain'); ?>
                </a>
                <a href="?page=affcd-domain-management&tab=webhooks" 
                   class="nav-tab <?php echo $active_tab === 'webhooks' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Webhook Settings', 'affiliate-cross-domain'); ?>
                </a>
                <a href="?page=affcd-domain-management&tab=security" 
                   class="nav-tab <?php echo $active_tab === 'security' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Security', 'affiliate-cross-domain'); ?>
                </a>
            </nav>

            <!-- Tab Content -->
            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'domains':
                        $this->render_domains_tab();
                        break;
                    case 'api':
                        $this->render_api_tab();
                        break;
                    case 'webhooks':
                        $this->render_webhooks_tab();
                        break;
                    case 'security':
                        $this->render_security_tab();
                        break;
                }
                ?>
            </div>
        </div>

        <?php $this->render_admin_styles(); ?>
        <?php
    }

    /**
     * Display admin notices
     */
    private function display_admin_notices() {
        if (isset($_GET['message'])) {
            $message = '';
            $type = 'success';
            
            switch ($_GET['message']) {
                case 'domain_added':
                    $message = __('Domain added successfully.', 'affiliate-cross-domain');
                    break;
                case 'domain_removed':
                    $message = __('Domain removed successfully.', 'affiliate-cross-domain');
                    break;
                case 'settings_saved':
                    $message = __('Settings saved successfully.', 'affiliate-cross-domain');
                    break;
                case 'error':
                    $message = __('An error occurred. Please try again.', 'affiliate-cross-domain');
                    $type = 'error';
                    break;
                case 'invalid_domain':
                    $message = __('Invalid domain URL provided.', 'affiliate-cross-domain');
                    $type = 'error';
                    break;
            }

            if ($message) {
                printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', 
                    esc_attr($type), 
                    esc_html($message)
                );
            }
        }
    }

    /**
     * Render domains tab
     */
    private function render_domains_tab() {
        $allowed_domains = get_option('affcd_allowed_domains', []);
        ?>
        <div class="domains-section">
            <!-- Add New Domain -->
            <div class="postbox">
                <h3 class="hndle"><?php _e('Add New Domain', 'affiliate-cross-domain'); ?></h3>
                <div class="inside">
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action" value="affcd_add_domain">
                        <?php wp_nonce_field('affcd_add_domain', 'affcd_nonce'); ?>
                        
                        <table class="form-table">
                            <tbody>
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
                                        <p class="description">
                                            <?php _e('Full URL including https:// protocol', 'affiliate-cross-domain'); ?>
                                        </p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="domain_name"><?php _e('Display Name', 'affiliate-cross-domain'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" 
                                               id="domain_name" 
                                               name="domain_name" 
                                               class="regular-text" 
                                               placeholder="Client Site Name">
                                        <p class="description">
                                            <?php _e('Optional friendly name for this domain', 'affiliate-cross-domain'); ?>
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="auto_verify"><?php _e('Auto-Verify', 'affiliate-cross-domain'); ?></label>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox" id="auto_verify" name="auto_verify" value="1" checked>
                                            <?php _e('Automatically test domain connection after adding', 'affiliate-cross-domain'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <p class="submit">
                            <input type="submit" class="button-primary" value="<?php _e('Add Domain', 'affiliate-cross-domain'); ?>">
                        </p>
                    </form>
                </div>
            </div>

            <!-- Existing Domains -->
            <div class="postbox">
                <h3 class="hndle">
                    <?php _e('Authorized Domains', 'affiliate-cross-domain'); ?>
                    <span class="domain-count">(<?php echo count($allowed_domains); ?>)</span>
                </h3>
                <div class="inside">
                    <?php if (empty($allowed_domains)): ?>
                        <p><?php _e('No domains configured yet. Add your first domain above.', 'affiliate-cross-domain'); ?></p>
                    <?php else: ?>
                        <div class="domain-list">
                            <?php foreach ($allowed_domains as $index => $domain): ?>
                                <?php $this->render_domain_item($domain, $index); ?>
                            <?php endforeach; ?>
                        </div>

                        <div style="margin-top: 20px;">
                            <button type="button" class="button" onclick="affcdTestAllDomains()">
                                <?php _e('Test All Domains', 'affiliate-cross-domain'); ?>
                            </button>
                            <button type="button" class="button" onclick="affcdBulkRemoveDomains()">
                                <?php _e('Bulk Remove', 'affiliate-cross-domain'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Domain Statistics -->
            <?php $this->render_domain_statistics(); ?>
        </div>
        <?php
    }

    /**
     * Render individual domain item
     */
    private function render_domain_item($domain, $index) {
        $domain_stats = $this->get_domain_statistics($domain);
        $last_check = get_option("affcd_domain_last_check_{$index}", null);
        $check_status = get_option("affcd_domain_status_{$index}", 'pending');
        $domain_name = isset($domain['name']) && !empty($domain['name']) ? $domain['name'] : '';
        $domain_url = is_array($domain) ? $domain['url'] : $domain;
        ?>
        <div class="domain-list-item <?php echo $check_status; ?>" data-domain-index="<?php echo $index; ?>">
            <div class="domain-info">
                <div class="domain-url">
                    <a href="<?php echo esc_url($domain_url); ?>" target="_blank">
                        <?php echo $domain_name ? esc_html($domain_name) : esc_html(parse_url($domain_url, PHP_URL_HOST)); ?>
                    </a>
                    <?php if ($domain_name): ?>
                        <small>(<?php echo esc_html(parse_url($domain_url, PHP_URL_HOST)); ?>)</small>
                    <?php endif; ?>
                    <span class="status-indicator status-<?php echo $check_status; ?>">
                        <?php 
                        switch ($check_status) {
                            case 'verified': _e('Verified', 'affiliate-cross-domain'); break;
                            case 'error': _e('Error', 'affiliate-cross-domain'); break;
                            default: _e('Pending', 'affiliate-cross-domain'); break;
                        }
                        ?>
                    </span>
                </div>
                <div class="domain-stats">
                    <?php if ($domain_stats && ($domain_stats['visits'] > 0 || $domain_stats['conversions'] > 0)): ?>
                        <?php printf(
                            __('%s visits, %s conversions (last 30 days)', 'affiliate-cross-domain'),
                            number_format($domain_stats['visits']),
                            number_format($domain_stats['conversions'])
                        ); ?>
                    <?php else: ?>
                        <?php _e('No activity yet', 'affiliate-cross-domain'); ?>
                    <?php endif; ?>
                    
                    <?php if ($last_check): ?>
                        | <?php printf(__('Last checked: %s', 'affiliate-cross-domain'), 
                              human_time_diff(strtotime($last_check)) . ' ago'); ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="domain-actions">
                <button type="button" 
                        class="button button-small" 
                        onclick="affcdTestDomainConnection(<?php echo $index; ?>)">
                    <?php _e('Test', 'affiliate-cross-domain'); ?>
                </button>
                <button type="button" 
                        class="button button-small" 
                        onclick="affcdEditDomain(<?php echo $index; ?>)">
                    <?php _e('Edit', 'affiliate-cross-domain'); ?>
                </button>
                <button type="button" 
                        class="button button-small button-link-delete" 
                        onclick="affcdRemoveDomain(<?php echo $index; ?>)">
                    <?php _e('Remove', 'affiliate-cross-domain'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Render API configuration tab
     */
    private function render_api_tab() {
        $api_key = get_option('affcd_api_key', '');
        $api_enabled = get_option('affcd_api_enabled', false);
        $rate_limit = get_option('affcd_api_rate_limit', 1000);
        ?>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="affcd_save_domain_settings">
            <input type="hidden" name="tab" value="api">
            <?php wp_nonce_field('affcd_save_settings', 'affcd_nonce'); ?>

            <div class="postbox">
                <h3 class="hndle"><?php _e('API Configuration', 'affiliate-cross-domain'); ?></h3>
                <div class="inside">
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <?php _e('API Status', 'affiliate-cross-domain'); ?>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="api_enabled" value="1" <?php checked($api_enabled, true); ?>>
                                        <?php _e('Enable API access for cross-domain requests', 'affiliate-cross-domain'); ?>
                                    </label>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="api_key"><?php _e('API Key', 'affiliate-cross-domain'); ?></label>
                                </th>
                                <td>
                                    <div class="api-key-display">
                                        <code><?php echo $api_key ? esc_html($api_key) : __('No API key generated', 'affiliate-cross-domain'); ?></code>
                                    </div>
                                    <p class="description">
                                        <?php _e('This key is used to authenticate cross-domain requests.', 'affiliate-cross-domain'); ?>
                                    </p>
                                    <button type="button" class="button" onclick="affcdGenerateApiKey()">
                                        <?php _e('Generate New Key', 'affiliate-cross-domain'); ?>
                                    </button>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="api_rate_limit"><?php _e('Rate Limit', 'affiliate-cross-domain'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="api_rate_limit" 
                                           name="api_rate_limit" 
                                           value="<?php echo esc_attr($rate_limit); ?>" 
                                           min="100" 
                                           max="10000" 
                                           class="small-text"> 
                                    <?php _e('requests per hour', 'affiliate-cross-domain'); ?>
                                    <p class="description">
                                        <?php _e('Maximum number of API requests allowed per domain per hour.', 'affiliate-cross-domain'); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php _e('Save API Settings', 'affiliate-cross-domain'); ?>">
                    </p>
                </div>
            </div>

            <!-- API Usage Statistics -->
            <?php $this->render_api_statistics(); ?>
        </form>
        <?php
    }

    /**
     * Render webhooks tab
     */
    private function render_webhooks_tab() {
        $webhook_url = get_option('affcd_webhook_url', '');
        $webhook_enabled = get_option('affcd_webhook_enabled', false);
        $webhook_events = get_option('affcd_webhook_events', ['conversion', 'visit']);
        ?>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="affcd_save_domain_settings">
            <input type="hidden" name="tab" value="webhooks">
            <?php wp_nonce_field('affcd_save_settings', 'affcd_nonce'); ?>

            <div class="postbox">
                <h3 class="hndle"><?php _e('Webhook Configuration', 'affiliate-cross-domain'); ?></h3>
                <div class="inside">
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <?php _e('Webhook Status', 'affiliate-cross-domain'); ?>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="webhook_enabled" value="1" <?php checked($webhook_enabled, true); ?>>
                                        <?php _e('Enable webhook notifications', 'affiliate-cross-domain'); ?>
                                    </label>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="webhook_url"><?php _e('Webhook URL', 'affiliate-cross-domain'); ?></label>
                                </th>
                                <td>
                                    <input type="url" 
                                           id="webhook_url" 
                                           name="webhook_url" 
                                           value="<?php echo esc_attr($webhook_url); ?>" 
                                           class="large-text" 
                                           placeholder="https://your-site.com/webhook-endpoint">
                                    <p class="description">
                                        <?php _e('URL to receive webhook notifications for cross-domain events.', 'affiliate-cross-domain'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <?php _e('Events to Send', 'affiliate-cross-domain'); ?>
                                </th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox" name="webhook_events[]" value="visit" 
                                                   <?php checked(in_array('visit', $webhook_events)); ?>>
                                            <?php _e('Visits', 'affiliate-cross-domain'); ?>
                                        </label><br>

                                        <label>
                                            <input type="checkbox" name="webhook_events[]" value="conversion" 
                                                   <?php checked(in_array('conversion', $webhook_events)); ?>>
                                            <?php _e('Conversions', 'affiliate-cross-domain'); ?>
                                        </label><br>

                                        <label>
                                            <input type="checkbox" name="webhook_events[]" value="referral" 
                                                   <?php checked(in_array('referral', $webhook_events)); ?>>
                                            <?php _e('Referrals', 'affiliate-cross-domain'); ?>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php _e('Save Webhook Settings', 'affiliate-cross-domain'); ?>">
                        <button type="button" class="button" onclick="affcdSendTestWebhook()">
                            <?php _e('Send Test Webhook', 'affiliate-cross-domain'); ?>
                        </button>
                    </p>

                    <div id="webhook-test-result" class="webhook-test-result" style="display: none;"></div>
                </div>
            </div>
        </form>
        <?php
    }

    /**
     * Render security tab
     */
    private function render_security_tab() {
        $allowed_origins = get_option('affcd_allowed_origins', '*');
        $ssl_required = get_option('affcd_ssl_required', true);
        $token_expiry = get_option('affcd_token_expiry', 3600);
        $log_requests = get_option('affcd_log_requests', true);
        ?>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="affcd_save_domain_settings">
            <input type="hidden" name="tab" value="security">
            <?php wp_nonce_field('affcd_save_settings', 'affcd_nonce'); ?>

            <div class="postbox">
                <h3 class="hndle"><?php _e('Security Settings', 'affiliate-cross-domain'); ?></h3>
                <div class="inside">
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="allowed_origins"><?php _e('CORS Origins', 'affiliate-cross-domain'); ?></label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="allowed_origins" 
                                           name="allowed_origins" 
                                           value="<?php echo esc_attr($allowed_origins); ?>" 
                                           class="large-text">
                                    <p class="description">
                                        <?php _e('Allowed origins for CORS requests. Use * for all origins or comma-separated domains.', 'affiliate-cross-domain'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <?php _e('SSL/HTTPS', 'affiliate-cross-domain'); ?>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="ssl_required" value="1" <?php checked($ssl_required, true); ?>>
                                        <?php _e('Require HTTPS for all cross-domain requests', 'affiliate-cross-domain'); ?>
                                    </label>
                                    <p class="description">
                                        <?php _e('Recommended for production environments.', 'affiliate-cross-domain'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="token_expiry"><?php _e('Token Expiry', 'affiliate-cross-domain'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="token_expiry" 
                                           name="token_expiry" 
                                           value="<?php echo esc_attr($token_expiry); ?>" 
                                           min="300" 
                                           max="86400" 
                                           class="small-text"> 
                                    <?php _e('seconds', 'affiliate-cross-domain'); ?>
                                    <p class="description">
                                        <?php _e('How long authentication tokens remain valid (300-86400 seconds).', 'affiliate-cross-domain'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <?php _e('Request Logging', 'affiliate-cross-domain'); ?>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="log_requests" value="1" <?php checked($log_requests, true); ?>>
                                        <?php _e('Log cross-domain requests for monitoring and debugging', 'affiliate-cross-domain'); ?>
                                    </label>
                                    <p class="description">
                                        <?php _e('Logs are automatically cleaned up after 30 days.', 'affiliate-cross-domain'); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php _e('Save Security Settings', 'affiliate-cross-domain'); ?>">
                    </p>
                </div>
            </div>

            <!-- Security Logs -->
            <?php $this->render_security_logs(); ?>
        </form>
        <?php
    }

    /**
     * Render domain statistics
     */
    private function render_domain_statistics() {
        $stats = $this->get_overall_statistics();
        ?>
        <div class="postbox">
            <h3 class="hndle"><?php _e('Domain Statistics', 'affiliate-cross-domain'); ?></h3>
            <div class="inside">
                <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div class="stat-item">
                        <h4><?php _e('Total Domains', 'affiliate-cross-domain'); ?></h4>
                        <span class="stat-number"><?php echo $stats['total_domains']; ?></span>
                    </div>
                    <div class="stat-item">
                        <h4><?php _e('Active Domains', 'affiliate-cross-domain'); ?></h4>
                        <span class="stat-number"><?php echo $stats['active_domains']; ?></span>
                    </div>
                    <div class="stat-item">
                        <h4><?php _e('Total Visits', 'affiliate-cross-domain'); ?></h4>
                        <span class="stat-number"><?php echo number_format($stats['total_visits']); ?></span>
                    </div>
                    <div class="stat-item">
                        <h4><?php _e('Conversions', 'affiliate-cross-domain'); ?></h4>
                        <span class="stat-number"><?php echo number_format($stats['total_conversions']); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render API statistics
     */
    private function render_api_statistics() {
        $api_stats = $this->get_api_statistics();
        ?>
        <div class="postbox">
            <h3 class="hndle"><?php _e('API Usage Statistics', 'affiliate-cross-domain'); ?></h3>
            <div class="inside">
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Domain', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Requests Today', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Requests This Hour', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Last Request', 'affiliate-cross-domain'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($api_stats)): ?>
                            <tr>
                                <td colspan="4"><?php _e('No API usage data available.', 'affiliate-cross-domain'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($api_stats as $domain => $stats): ?>
                                <tr>
                                    <td><?php echo esc_html($domain); ?></td>
                                    <td><?php echo number_format($stats['requests_today']); ?></td>
                                    <td><?php echo number_format($stats['requests_hour']); ?></td>
                                    <td><?php echo $stats['last_request'] ? human_time_diff(strtotime($stats['last_request'])) . ' ago' : __('Never', 'affiliate-cross-domain'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Render security logs
     */
    private function render_security_logs() {
        $logs = $this->get_security_logs();
        ?>
        <div class="postbox">
            <h3 class="hndle"><?php _e('Recent Security Events', 'affiliate-cross-domain'); ?></h3>
            <div class="inside">
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Time', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Event', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Domain', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('IP Address', 'affiliate-cross-domain'); ?></th>
                            <th><?php _e('Status', 'affiliate-cross-domain'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5"><?php _e('No security events logged recently.', 'affiliate-cross-domain'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
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
                
                <?php if (!empty($logs)): ?>
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
     * Add domain
     */
    public function add_domain() {
        if (!current_user_can('manage_affiliates') || !wp_verify_nonce($_POST['affcd_nonce'], 'affcd_add_domain')) {
            wp_die(__('Permission denied.', 'affiliate-cross-domain'));
        }

        $domain_url = sanitize_url($_POST['domain_url']);
        $domain_name = sanitize_text_field($_POST['domain_name']);
        $auto_verify = isset($_POST['auto_verify']);

        if (!filter_var($domain_url, FILTER_VALIDATE_URL)) {
            wp_redirect(add_query_arg('message', 'invalid_domain', wp_get_referer()));
            exit;
        }

        $allowed_domains = get_option('affcd_allowed_domains', []);
        
        // Check if domain already exists
        foreach ($allowed_domains as $existing_domain) {
            $existing_url = is_array($existing_domain) ? $existing_domain['url'] : $existing_domain;
            if (parse_url($existing_url, PHP_URL_HOST) === parse_url($domain_url, PHP_URL_HOST)) {
                wp_redirect(add_query_arg('message', 'domain_exists', wp_get_referer()));
                exit;
            }
        }

        $domain_data = [
            'url' => $domain_url,
            'name' => $domain_name,
            'added' => current_time('mysql'),
            'added_by' => get_current_user_id(),
        ];

        $allowed_domains[] = $domain_data;
        update_option('affcd_allowed_domains', $allowed_domains);

        // Auto-verify if requested
        if ($auto_verify) {
            $domain_index = count($allowed_domains) - 1;
            $this->test_domain_connection($domain_index, false);
        }

        wp_redirect(add_query_arg('message', 'domain_added', wp_get_referer()));
        exit;
    }

    /**
     * Remove domain
     */
    public function remove_domain() {
        if (!current_user_can('manage_affiliates') || !wp_verify_nonce($_GET['nonce'], 'affcd_remove_domain')) {
            wp_die(__('Permission denied.', 'affiliate-cross-domain'));
        }

        $domain_index = intval($_GET['index']);
        $allowed_domains = get_option('affcd_allowed_domains', []);

        if (isset($allowed_domains[$domain_index])) {
            unset($allowed_domains[$domain_index]);
            $allowed_domains = array_values($allowed_domains); // Re-index array
            update_option('affcd_allowed_domains', $allowed_domains);

            // Clean up domain-specific options
            delete_option("affcd_domain_last_check_{$domain_index}");
            delete_option("affcd_domain_status_{$domain_index}");
        }

        wp_redirect(add_query_arg('message', 'domain_removed', wp_get_referer()));
        exit;
    }

    /**
     * Save domain settings
     */
    public function save_domain_settings() {
        if (!current_user_can('manage_affiliates') || !wp_verify_nonce($_POST['affcd_nonce'], 'affcd_save_settings')) {
            wp_die(__('Permission denied.', 'affiliate-cross-domain'));
        }

        $tab = sanitize_text_field($_POST['tab']);

        switch ($tab) {
            case 'api':
                update_option('affcd_api_enabled', isset($_POST['api_enabled']));
                update_option('affcd_api_rate_limit', intval($_POST['api_rate_limit']));
                break;

            case 'webhooks':
                update_option('affcd_webhook_enabled', isset($_POST['webhook_enabled']));
                update_option('affcd_webhook_url', sanitize_url($_POST['webhook_url']));
                update_option('affcd_webhook_events', isset($_POST['webhook_events']) ? $_POST['webhook_events'] : []);
                break;

            case 'security':
                update_option('affcd_allowed_origins', sanitize_text_field($_POST['allowed_origins']));
                update_option('affcd_ssl_required', isset($_POST['ssl_required']));
                update_option('affcd_token_expiry', intval($_POST['token_expiry']));
                update_option('affcd_log_requests', isset($_POST['log_requests']));
                break;
        }

        wp_redirect(add_query_arg(['message' => 'settings_saved', 'tab' => $tab], wp_get_referer()));
        exit;
    }

    /**
     * AJAX: Test domain connection
     */
    public function ajax_test_domain_connection() {
        if (!wp_verify_nonce($_POST['nonce'], 'affcd_ajax_nonce') || !current_user_can('manage_affiliates')) {
            wp_die();
        }

        $domain_index = intval($_POST['domain_index']);
        $result = $this->test_domain_connection($domain_index, true);

        wp_send_json($result);
    }

    /**
     * AJAX: Generate API key
     */
    public function ajax_generate_api_key() {
        if (!wp_verify_nonce($_POST['nonce'], 'affcd_ajax_nonce') || !current_user_can('manage_affiliates')) {
            wp_die();
        }

        $api_key = $this->generate_api_key();
        update_option('affcd_api_key', $api_key);

        wp_send_json_success(['api_key' => $api_key]);
    }

    /**
     * AJAX: Send test webhook
     */
    public function ajax_send_test_webhook() {
        if (!wp_verify_nonce($_POST['nonce'], 'affcd_ajax_nonce') || !current_user_can('manage_affiliates')) {
            wp_die();
        }

        $webhook_url = get_option('affcd_webhook_url');
        if (empty($webhook_url)) {
            wp_send_json_error(['message' => __('No webhook URL configured.', 'affiliate-cross-domain')]);
        }

        $test_data = [
            'event' => 'test',
            'timestamp' => current_time('c'),
            'data' => [
                'message' => 'This is a test webhook from Affiliate Cross Domain Full',
                'source' => home_url(),
            ]
        ];

        $response = wp_remote_post($webhook_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'AFFCD-Webhook/1.0',
            ],
            'body' => json_encode($test_data),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code >= 200 && $response_code < 300) {
            wp_send_json_success([
                'message' => sprintf(__('Webhook test successful (HTTP %d)', 'affiliate-cross-domain'), $response_code),
                'response' => $response_body,
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(__('Webhook test failed (HTTP %d)', 'affiliate-cross-domain'), $response_code),
                'response' => $response_body,
            ]);
        }
    }

    /**
     * Test domain connection
     */
    private function test_domain_connection($domain_index, $return_result = false) {
        $allowed_domains = get_option('affcd_allowed_domains', []);
        
        if (!isset($allowed_domains[$domain_index])) {
            if ($return_result) {
                return ['success' => false, 'message' => __('Domain not found.', 'affiliate-cross-domain')];
            }
            return false;
        }

        $domain = $allowed_domains[$domain_index];
        $domain_url = is_array($domain) ? $domain['url'] : $domain;
        
        // Test URL: append our test endpoint
        $test_url = rtrim($domain_url, '/') . '/wp-json/affcd/v1/test';

        $response = wp_remote_get($test_url, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'AFFCD-Test/1.0',
            ],
        ]);

        $status = 'error';
        $message = __('Connection failed', 'affiliate-cross-domain');

        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            if ($response_code === 200) {
                $data = json_decode($response_body, true);
                if (isset($data['affcd']) && $data['affcd'] === true) {
                    $status = 'verified';
                    $message = __('Connection verified', 'affiliate-cross-domain');
                }
            } elseif ($response_code === 404) {
                $message = __('Plugin not installed or activated on target domain', 'affiliate-cross-domain');
            } else {
                $message = sprintf(__('HTTP %d error', 'affiliate-cross-domain'), $response_code);
            }
        } else {
            $message = $response->get_error_message();
        }

        // Update domain status
        update_option("affcd_domain_status_{$domain_index}", $status);
        update_option("affcd_domain_last_check_{$domain_index}", current_time('mysql'));

        if ($return_result) {
            return [
                'success' => $status === 'verified',
                'status' => $status,
                'message' => $message,
            ];
        }

        return $status === 'verified';
    }

    /**
     * Generate API key
     */
    private function generate_api_key() {
        return 'affcd_' . wp_generate_password(32, false, false);
    }

    /**
     * Get domain statistics
     */
    private function get_domain_statistics($domain) {
        global $wpdb;
        
        $domain_url = is_array($domain) ? $domain['url'] : $domain;
        $domain_host = parse_url($domain_url, PHP_URL_HOST);
        
        // Get visits and conversions from the last 30 days
        $table_name = $wpdb->prefix . 'affcd_tracking';
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as visits,
                SUM(CASE WHEN conversion_id IS NOT NULL THEN 1 ELSE 0 END) as conversions
            FROM {$table_name}
            WHERE domain = %s 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ", $domain_host));

        return $stats ? [
            'visits' => intval($stats->visits),
            'conversions' => intval($stats->conversions),
        ] : ['visits' => 0, 'conversions' => 0];
    }

    /**
     * Get overall statistics
     */
    private function get_overall_statistics() {
        global $wpdb;
        
        $allowed_domains = get_option('affcd_allowed_domains', []);
        $total_domains = count($allowed_domains);
        
        // Count active domains (verified status)
        $active_domains = 0;
        for ($i = 0; $i < $total_domains; $i++) {
            $status = get_option("affcd_domain_status_{$i}", 'pending');
            if ($status === 'verified') {
                $active_domains++;
            }
        }

        // Get total visits and conversions
        $table_name = $wpdb->prefix . 'affcd_tracking';
        $totals = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_visits,
                SUM(CASE WHEN conversion_id IS NOT NULL THEN 1 ELSE 0 END) as total_conversions
            FROM {$table_name}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");

        return [
            'total_domains' => $total_domains,
            'active_domains' => $active_domains,
            'total_visits' => $totals ? intval($totals->total_visits) : 0,
            'total_conversions' => $totals ? intval($totals->total_conversions) : 0,
        ];
    }

    /**
     * Get API statistics
     */
    private function get_api_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_api_logs';
        
        $stats = $wpdb->get_results("
            SELECT 
                domain,
                SUM(CASE WHEN created_at >= CURDATE() THEN 1 ELSE 0 END) as requests_today,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 ELSE 0 END) as requests_hour,
                MAX(created_at) as last_request
            FROM {$table_name}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY domain
            ORDER BY requests_today DESC
        ");

        $formatted_stats = [];
        foreach ($stats as $stat) {
            $formatted_stats[$stat->domain] = [
                'requests_today' => intval($stat->requests_today),
                'requests_hour' => intval($stat->requests_hour),
                'last_request' => $stat->last_request,
            ];
        }

        return $formatted_stats;
    }

    /**
     * Get security logs
     */
    private function get_security_logs() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affcd_security_logs';
        
        $logs = $wpdb->get_results("
            SELECT *
            FROM {$table_name}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY created_at DESC
            LIMIT 50
        ", ARRAY_A);

        return $logs;
    }

    /**
     * Render admin styles
     */
    private function render_admin_styles() {
        ?>
        <style>
        .tab-content {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-top: none;
            padding: 20px;
        }
        .domain-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 1px solid #ddd;
            margin-bottom: 10px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .domain-list-item.verified {
            border-color: #46b450;
            background: #f0fff0;
        }
        .domain-list-item.error {
            border-color: #dc3232;
            background: #fff0f0;
        }
        .domain-info {
            flex: 1;
        }
        .domain-url {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
        }
        .domain-url a {
            text-decoration: none;
            color: #0073aa;
        }
        .domain-url a:hover {
            text-decoration: underline;
        }
        .domain-url small {
            font-weight: normal;
            color: #666;
            margin-left: 10px;
        }
        .domain-stats {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .domain-actions {
            display: flex;
            gap: 5px;
        }
        .status-indicator {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        .status-verified {
            background: #46b450;
            color: white;
        }
        .status-pending {
            background: #ffb900;
            color: black;
        }
        .status-error {
            background: #dc3232;
            color: white;
        }
        .api-key-display {
            font-family: monospace;
            background: #f0f0f0;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
            word-break: break-all;
            margin-bottom: 10px;
        }
        .webhook-test-result {
            margin-top: 10px;
            padding: 10px;
            border-radius: 3px;
        }
        .webhook-test-result.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .webhook-test-result.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-item {
            text-align: center;
            padding: 20px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .stat-item h4 {
            margin: 0 0 10px 0;
            color: #555;
            font-size: 14px;
        }
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #0073aa;
        }
        .domain-count {
            font-weight: normal;
            color: #666;
        }
        .button-small {
            height: auto;
            padding: 3px 8px;
            font-size: 12px;
        }
        .status-success {
            color: #155724;
        }
        .status-warning {
            color: #856404;
        }
        .status-blocked {
            color: #721c24;
        }
        </style>
        <?php
    }
}

// Initialize the domain management class
new AFFCD_Domain_Management();