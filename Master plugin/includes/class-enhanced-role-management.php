<?php
/**
 * Enhanced Role Management Class - Fixed Version
 * 
 * File: /wp-content/plugins/Affiliate Cross Domain MasterZ/includes/class-enhanced-role-management.php
 * Plugin: AffiliateWP Cross-Domain Plugin Suite (Master)
 * Author: Richard King, starneconsulting.com
 * Email: r.king@starneconsulting.com
 * Version: 1.0.0
 * 
 * Handles advanced user role management with enhanced capabilities for affiliate management,
 * role-based access control, and permission management across the cross-domain system.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied');
}

class EnhancedRoleManagement {

    /**
     * Plugin main file path
     * @var string
     */
    private static $plugin_file;

    /**
     * Instance holder
     * @var EnhancedRoleManagement
     */
    private static $instance = null;

    /**
     * Database manager instance
     * @var AFFCD_Database_Manager
     */
    private $db_manager;

    /**
     * Default role capabilities
     * @var array
     */
    private $default_capabilities = [
        'affiliate_manager' => [
            'read' => true,
            'manage_affiliate_codes' => true,
            'view_affiliate_analytics' => true,
            'edit_affiliate_settings' => true,
            'export_affiliate_data' => true,
            'manage_affiliate_users' => true,
            'manage_affiliate_domains' => true,
            'access_affiliate_reports' => true,
            'manage_vanity_codes' => true,
            'configure_affiliate_system' => true
        ],
        'affiliate_analyst' => [
            'read' => true,
            'view_affiliate_analytics' => true,
            'export_affiliate_reports' => true,
            'access_affiliate_reports' => true,
            'view_affiliate_codes' => true
        ],
        'affiliate_editor' => [
            'read' => true,
            'manage_affiliate_codes' => true,
            'view_affiliate_analytics' => true,
            'edit_affiliate_codes' => true,
            'create_affiliate_codes' => true,
            'delete_affiliate_codes' => true
        ],
        'affiliate_viewer' => [
            'read' => true,
            'view_affiliate_codes' => true,
            'view_affiliate_analytics' => true
        ]
    ];

    /**
     * Protected roles that cannot be deleted
     * @var array
     */
    private $protected_roles = [
        'administrator',
        'editor',
        'author',
        'contributor',
        'subscriber'
    ];

    /**
     * Constructor
     * 
     * @param string $plugin_file Main plugin file path
     */
    public function __construct($plugin_file = null) {
        // Set plugin file path
        if ($plugin_file) {
            self::$plugin_file = $plugin_file;
        } else {
            // Fallback - try to determine plugin file from backtrace
            $trace = debug_backtrace();
            foreach ($trace as $frame) {
                if (isset($frame['file']) && strpos($frame['file'], 'affiliate-cross-domain-full.php') !== false) {
                    self::$plugin_file = $frame['file'];
                    break;
                }
            }
            
            // Final fallback
            if (!self::$plugin_file) {
                self::$plugin_file = AFFCD_PLUGIN_FILE ?? __FILE__;
            }
        }

        $this->init();
    }

    /**
     * Get singleton instance
     * 
     * @param string $plugin_file Main plugin file path
     * @return EnhancedRoleManagement
     */
    public static function instance($plugin_file = null) {
        if (self::$instance === null) {
            self::$instance = new self($plugin_file);
        }
        return self::$instance;
    }

/**
 * Initialize the role management system
 */
public function init() {
    // DON'T get database manager here - causes circular dependency during plugin loading
    // It will be retrieved lazily when needed via get_db_manager()

    // Register hooks
    add_action('init', [$this, 'register_custom_roles'], 0);
    add_action('admin_init', [$this, 'admin_init']);
    add_action('wp_ajax_erm_create_role', [$this, 'ajax_create_role']);
    add_action('wp_ajax_erm_delete_role', [$this, 'ajax_delete_role']);
    add_action('wp_ajax_erm_update_role_capabilities', [$this, 'ajax_update_role_capabilities']);
    add_action('wp_ajax_erm_assign_user_role', [$this, 'ajax_assign_user_role']);

    // User profile hooks
    add_action('show_user_profile', [$this, 'add_user_role_fields']);
    add_action('edit_user_profile', [$this, 'add_user_role_fields']);
    add_action('personal_options_update', [$this, 'save_user_role_fields']);
    add_action('edit_user_profile_update', [$this, 'save_user_role_fields']);

    // Register activation hook with proper plugin file
    if (self::$plugin_file && file_exists(self::$plugin_file)) {
        register_activation_hook(self::$plugin_file, [$this, 'create_role_management_tables']);
    }
}

/**
 * Get database manager lazily when needed
 * @return AFFCD_Database_Manager|null
 */
private function get_db_manager() {
    if (!$this->db_manager && class_exists('AffiliateWP_Cross_Domain_Full')) {
        $plugin = AffiliateWP_Cross_Domain_Full::instance();
        if (method_exists($plugin, 'get_database_manager')) {
            $this->db_manager = $plugin->get_database_manager();
        }
    }
    return $this->db_manager;
}

    /**
     * Admin initialization
     */
    public function admin_init() {
        // Check if we need to create/update roles
        $current_version = get_option('erm_roles_version', '0');
        $target_version = '1.0.0';
        
        if (version_compare($current_version, $target_version, '<')) {
            $this->register_custom_roles();
            update_option('erm_roles_version', $target_version);
        }
    }

    /**
     * Register custom affiliate roles
     */
    public function register_custom_roles() {
        foreach ($this->default_capabilities as $role_name => $capabilities) {
            $role_display_name = $this->get_role_display_name($role_name);
            
            // Remove role if it exists to ensure clean slate
            remove_role($role_name);
            
            // Add role with capabilities
            add_role($role_name, $role_display_name, $capabilities);
        }
    }

    /**
     * Get role display name
     * 
     * @param string $role_name Role slug
     * @return string Display name
     */
    private function get_role_display_name($role_name) {
        $display_names = [
            'affiliate_manager' => __('Affiliate Manager', 'affiliatewp-cross-domain-plugin-suite'),
            'affiliate_analyst' => __('Affiliate Analyst', 'affiliatewp-cross-domain-plugin-suite'),
            'affiliate_editor' => __('Affiliate Editor', 'affiliatewp-cross-domain-plugin-suite'),
            'affiliate_viewer' => __('Affiliate Viewer', 'affiliatewp-cross-domain-plugin-suite')
        ];

        return $display_names[$role_name] ?? ucwords(str_replace('_', ' ', $role_name));
    }

    /**
     * Create role management tables
     */
    public static function create_role_management_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Role permissions audit table
        $table_name = $wpdb->prefix . 'affcd_role_audit';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            role_name varchar(50) NOT NULL,
            action varchar(50) NOT NULL,
            old_capabilities longtext,
            new_capabilities longtext,
            changed_by bigint(20) unsigned NOT NULL,
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY role_name (role_name),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Role sessions table for tracking active role usage
        $sessions_table = $wpdb->prefix . 'affcd_role_sessions';
        $sessions_sql = "CREATE TABLE IF NOT EXISTS $sessions_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            role_name varchar(50) NOT NULL,
            session_token varchar(255) NOT NULL,
            capabilities longtext,
            ip_address varchar(45),
            user_agent text,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_activity datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at datetime,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY session_token (session_token),
            KEY user_id (user_id),
            KEY role_name (role_name),
            KEY expires_at (expires_at),
            KEY is_active (is_active)
        ) $charset_collate;";

        dbDelta($sessions_sql);

        // Update plugin version
        update_option('erm_db_version', '1.0.0');
    }

    /**
     * Create custom role
     * 
     * @param string $role_name Role slug
     * @param string $display_name Role display name
     * @param array $capabilities Role capabilities
     * @return bool|WP_Error Success status or error
     */
    public function create_custom_role($role_name, $display_name, $capabilities = []) {
        // Validate inputs
        if (empty($role_name) || empty($display_name)) {
            return new WP_Error('invalid_input', __('Role name and display name are required.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        // Sanitize role name
        $role_name = sanitize_key($role_name);
        
        // Check if role already exists
        if (get_role($role_name)) {
            return new WP_Error('role_exists', __('Role already exists.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        // Validate capabilities
        $validated_capabilities = $this->validate_capabilities($capabilities);

        // Add role
        $result = add_role($role_name, $display_name, $validated_capabilities);
        
        if ($result) {
            // Log role creation
            $this->log_role_action('create_role', [
                'role_name' => $role_name,
                'display_name' => $display_name,
                'capabilities' => $validated_capabilities
            ]);

            return true;
        }

        return new WP_Error('creation_failed', __('Failed to create role.', 'affiliatewp-cross-domain-plugin-suite'));
    }

    /**
     * Delete custom role
     * 
     * @param string $role_name Role slug
     * @return bool|WP_Error Success status or error
     */
    public function delete_custom_role($role_name) {
        // Validate input
        if (empty($role_name)) {
            return new WP_Error('invalid_input', __('Role name is required.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        // Check if role is protected
        if (in_array($role_name, $this->protected_roles)) {
            return new WP_Error('protected_role', __('Cannot delete protected WordPress roles.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        // Check if role exists
        $role = get_role($role_name);
        if (!$role) {
            return new WP_Error('role_not_found', __('Role not found.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        // Check if any users have this role
        $users_with_role = get_users(['role' => $role_name, 'number' => 1]);
        if (!empty($users_with_role)) {
            return new WP_Error('role_in_use', __('Cannot delete role that is assigned to users.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        // Remove role
        remove_role($role_name);

        // Log role deletion
        $this->log_role_action('delete_role', [
            'role_name' => $role_name,
            'capabilities' => $role->capabilities
        ]);

        return true;
    }

    /**
     * Update role capabilities
     * 
     * @param string $role_name Role slug
     * @param array $capabilities New capabilities
     * @return bool|WP_Error Success status or error
     */
    public function update_role_capabilities($role_name, $capabilities) {
        // Validate input
        if (empty($role_name)) {
            return new WP_Error('invalid_input', __('Role name is required.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        // Get role
        $role = get_role($role_name);
        if (!$role) {
            return new WP_Error('role_not_found', __('Role not found.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        // Store old capabilities for audit
        $old_capabilities = $role->capabilities;

        // Validate new capabilities
        $validated_capabilities = $this->validate_capabilities($capabilities);

        // Remove all current capabilities
        foreach ($role->capabilities as $capability => $granted) {
            $role->remove_cap($capability);
        }

        // Add new capabilities
        foreach ($validated_capabilities as $capability => $granted) {
            if ($granted) {
                $role->add_cap($capability);
            }
        }

        // Log capability update
        $this->log_role_action('update_capabilities', [
            'role_name' => $role_name,
            'old_capabilities' => $old_capabilities,
            'new_capabilities' => $validated_capabilities
        ]);

        return true;
    }

    /**
     * Assign role to user
     * 
     * @param int $user_id User ID
     * @param string $role_name Role slug
     * @param bool $replace_existing Whether to replace existing roles
     * @return bool|WP_Error Success status or error
     */
    public function assign_user_role($user_id, $role_name, $replace_existing = false) {
        // Validate inputs
        if (empty($user_id) || empty($role_name)) {
            return new WP_Error('invalid_input', __('User ID and role name are required.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        // Get user
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return new WP_Error('user_not_found', __('User not found.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        // Check if role exists
        if (!get_role($role_name)) {
            return new WP_Error('role_not_found', __('Role not found.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        // Store old roles for audit
        $old_roles = $user->roles;

        // Assign role
        if ($replace_existing) {
            // Remove all roles first
            foreach ($user->roles as $existing_role) {
                $user->remove_role($existing_role);
            }
        }

        $user->add_role($role_name);

        // Log role assignment
        $this->log_role_action('assign_role', [
            'user_id' => $user_id,
            'role_name' => $role_name,
            'old_roles' => $old_roles,
            'new_roles' => $user->roles,
            'replaced_existing' => $replace_existing
        ]);

        return true;
    }

    /**
     * Get user role analytics
     * 
     * @param int $user_id Optional user ID
     * @return array Role analytics data
     */
    public function get_role_analytics($user_id = null) {
        global $wpdb;

        $analytics = [
            'total_users' => 0,
            'role_distribution' => [],
            'recent_changes' => [],
            'active_sessions' => 0
        ];

        // Get total users
        $analytics['total_users'] = count_users()['total_users'];

        // Get role distribution
        $all_roles = wp_roles()->roles;
        foreach ($all_roles as $role_name => $role_data) {
            $user_count = count(get_users(['role' => $role_name]));
            $analytics['role_distribution'][$role_name] = [
                'display_name' => $role_data['name'],
                'user_count' => $user_count,
                'percentage' => $analytics['total_users'] > 0 ? round(($user_count / $analytics['total_users']) * 100, 2) : 0
            ];
        }

        // Get recent role changes
        $audit_table = $wpdb->prefix . 'affcd_role_audit';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $audit_table)) === $audit_table) {
            $where_clause = $user_id ? $wpdb->prepare('WHERE user_id = %d', $user_id) : '';
            
            $recent_changes = $wpdb->get_results("
                SELECT * FROM {$audit_table} 
                {$where_clause}
                ORDER BY created_at DESC 
                LIMIT 20
            ");

            $analytics['recent_changes'] = array_map([$this, 'format_audit_record'], $recent_changes);
        }

        // Get active sessions count
        $sessions_table = $wpdb->prefix . 'affcd_role_sessions';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $sessions_table)) === $sessions_table) {
            $where_clause = $user_id ? $wpdb->prepare('AND user_id = %d', $user_id) : '';
            
            $analytics['active_sessions'] = (int) $wpdb->get_var("
                SELECT COUNT(*) FROM {$sessions_table} 
                WHERE is_active = 1 AND expires_at > NOW() 
                {$where_clause}
            ");
        }

        return $analytics;
    }

    /**
     * Validate capabilities array
     * 
     * @param array $capabilities Raw capabilities
     * @return array Validated capabilities
     */
    private function validate_capabilities($capabilities) {
        if (!is_array($capabilities)) {
            return [];
        }

        $valid_capabilities = [];
        $all_caps = $this->get_all_available_capabilities();

        foreach ($capabilities as $capability => $granted) {
            $capability = sanitize_text_field($capability);
            
            // Only allow known capabilities
            if (in_array($capability, $all_caps)) {
                $valid_capabilities[$capability] = (bool) $granted;
            }
        }

        return $valid_capabilities;
    }

    /**
     * Get all available capabilities
     * 
     * @return array All available capabilities
     */
    private function get_all_available_capabilities() {
        $capabilities = [];

        // WordPress core capabilities
        $core_caps = [
            'read', 'edit_posts', 'edit_others_posts', 'edit_published_posts',
            'publish_posts', 'delete_posts', 'delete_others_posts',
            'delete_published_posts', 'manage_categories', 'manage_links',
            'edit_pages', 'edit_others_pages', 'edit_published_pages',
            'publish_pages', 'delete_pages', 'delete_others_pages',
            'delete_published_pages', 'read_private_pages', 'read_private_posts',
            'upload_files', 'edit_files', 'moderate_comments',
            'manage_options', 'switch_themes', 'edit_themes', 'install_themes',
            'delete_themes', 'edit_plugins', 'install_plugins', 'delete_plugins',
            'edit_users', 'create_users', 'delete_users', 'list_users',
            'promote_users', 'remove_users'
        ];

        // Custom affiliate capabilities
        $affiliate_caps = [
            'manage_affiliate_codes', 'view_affiliate_analytics',
            'edit_affiliate_settings', 'export_affiliate_data',
            'manage_affiliate_users', 'manage_affiliate_domains',
            'access_affiliate_reports', 'manage_vanity_codes',
            'configure_affiliate_system', 'view_affiliate_codes',
            'edit_affiliate_codes', 'create_affiliate_codes',
            'delete_affiliate_codes', 'export_affiliate_reports'
        ];

        return array_merge($core_caps, $affiliate_caps);
    }

    /**
     * Log role-related actions
     * 
     * @param string $action Action performed
     * @param array $data Action data
     */
    private function log_role_action($action, $data) {
        if (!$this->db_manager) {
            return;
        }

        global $wpdb;
        
        $audit_table = $wpdb->prefix . 'affcd_role_audit';
        
        // Check if table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $audit_table)) !== $audit_table) {
            return;
        }

        $current_user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $wpdb->insert(
            $audit_table,
            [
                'user_id' => $data['user_id'] ?? 0,
                'role_name' => $data['role_name'] ?? '',
                'action' => $action,
                'old_capabilities' => isset($data['old_capabilities']) ? wp_json_encode($data['old_capabilities']) : null,
                'new_capabilities' => isset($data['new_capabilities']) ? wp_json_encode($data['new_capabilities']) : null,
                'changed_by' => $current_user_id,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'created_at' => current_time('mysql')
            ],
            [
                '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'
            ]
        );
    }

    /**
     * Get client IP address
     * 
     * @return string Client IP address
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Format audit record for display
     * 
     * @param object $record Audit record
     * @return array Formatted record
     */
    private function format_audit_record($record) {
        $user = get_user_by('id', $record->changed_by);
        $target_user = $record->user_id ? get_user_by('id', $record->user_id) : null;

        return [
            'id' => $record->id,
            'action' => $record->action,
            'role_name' => $record->role_name,
            'changed_by' => $user ? $user->display_name : __('Unknown User', 'affiliatewp-cross-domain-plugin-suite'),
            'target_user' => $target_user ? $target_user->display_name : null,
            'created_at' => $record->created_at,
            'human_time' => human_time_diff(strtotime($record->created_at), current_time('timestamp')) . ' ago'
        ];
    }

    /**
     * Add user role fields to profile
     * 
     * @param WP_User $user User object
     */
    public function add_user_role_fields($user) {
        if (!current_user_can('promote_users')) {
            return;
        }

        $available_roles = wp_roles()->roles;
        $user_roles = $user->roles;
        ?>
        <h2><?php _e('Affiliate Role Management', 'affiliatewp-cross-domain-plugin-suite'); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="affiliate_roles"><?php _e('Affiliate Roles', 'affiliatewp-cross-domain-plugin-suite'); ?></label></th>
                <td>
                    <?php foreach ($available_roles as $role_name => $role_data): ?>
                        <?php if (strpos($role_name, 'affiliate_') === 0): ?>
                            <label>
                                <input type="checkbox" 
                                       name="affiliate_roles[]" 
                                       value="<?php echo esc_attr($role_name); ?>"
                                       <?php checked(in_array($role_name, $user_roles)); ?>>
                                <?php echo esc_html($role_data['name']); ?>
                            </label><br>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <p class="description">
                        <?php _e('Select affiliate-specific roles for this user.', 'affiliatewp-cross-domain-plugin-suite'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save user role fields
     * 
     * @param int $user_id User ID
     */
    public function save_user_role_fields($user_id) {
        if (!current_user_can('promote_users') || !wp_verify_nonce($_POST['_wpnonce'], 'update-user_' . $user_id)) {
            return;
        }

        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }

        $affiliate_roles = $_POST['affiliate_roles'] ?? [];
        $current_affiliate_roles = array_filter($user->roles, function($role) {
            return strpos($role, 'affiliate_') === 0;
        });

        // Remove current affiliate roles
        foreach ($current_affiliate_roles as $role) {
            $user->remove_role($role);
        }

        // Add selected affiliate roles
        foreach ($affiliate_roles as $role) {
            if (strpos(sanitize_text_field($role), 'affiliate_') === 0 && get_role($role)) {
                $user->add_role($role);
            }
        }

        // Log role changes
        $this->log_role_action('profile_update', [
            'user_id' => $user_id,
            'old_roles' => $current_affiliate_roles,
            'new_roles' => $affiliate_roles
        ]);
    }

    /**
     * AJAX: Create role
     */
    public function ajax_create_role() {
        check_ajax_referer('erm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(wp_json_encode(['error' => __('Insufficient permissions.', 'affiliatewp-cross-domain-plugin-suite')]), 403);
        }

        $role_name = sanitize_text_field($_POST['role_name'] ?? '');
        $display_name = sanitize_text_field($_POST['display_name'] ?? '');
        $capabilities = $_POST['capabilities'] ?? [];

        $result = $this->create_custom_role($role_name, $display_name, $capabilities);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(__('Role created successfully.', 'affiliatewp-cross-domain-plugin-suite'));
        }
    }

    /**
     * AJAX: Delete role
     */
    public function ajax_delete_role() {
        check_ajax_referer('erm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(wp_json_encode(['error' => __('Insufficient permissions.', 'affiliatewp-cross-domain-plugin-suite')]), 403);
        }

        $role_name = sanitize_text_field($_POST['role_name'] ?? '');
        $result = $this->delete_custom_role($role_name);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(__('Role deleted successfully.', 'affiliatewp-cross-domain-plugin-suite'));
        }
    }

    /**
     * AJAX: Update role capabilities
     */
    public function ajax_update_role_capabilities() {
        check_ajax_referer('erm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(wp_json_encode(['error' => __('Insufficient permissions.', 'affiliatewp-cross-domain-plugin-suite')]), 403);
        }

        $role_name = sanitize_text_field($_POST['role_name'] ?? '');
        $capabilities = $_POST['capabilities'] ?? [];

        $result = $this->update_role_capabilities($role_name, $capabilities);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(__('Role capabilities updated successfully.', 'affiliatewp-cross-domain-plugin-suite'));
        }
    }

    /**
     * AJAX: Assign user role
     */
    public function ajax_assign_user_role() {
        check_ajax_referer('erm_nonce', 'nonce');
        
        if (!current_user_can('promote_users')) {
            wp_die(wp_json_encode(['error' => __('Insufficient permissions.', 'affiliatewp-cross-domain-plugin-suite')]), 403);
        }

        $user_id = intval($_POST['user_id'] ?? 0);
        $role_name = sanitize_text_field($_POST['role_name'] ?? '');
        $replace_existing = !empty($_POST['replace_existing']);

        $result = $this->assign_user_role($user_id, $role_name, $replace_existing);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(__('User role assigned successfully.', 'affiliatewp-cross-domain-plugin-suite'));
        }
    }

    /**
     * Get role management dashboard data
     * 
     * @return array Dashboard data
     */
    public function get_dashboard_data() {
        $dashboard_data = [];

        // Get role analytics
        $dashboard_data['analytics'] = $this->get_role_analytics();

        // Get all custom roles
        $all_roles = wp_roles()->roles;
        $custom_roles = [];
        
        foreach ($all_roles as $role_name => $role_data) {
            if (strpos($role_name, 'affiliate_') === 0 || !in_array($role_name, $this->protected_roles)) {
                $user_count = count(get_users(['role' => $role_name]));
                
                $custom_roles[$role_name] = [
                    'name' => $role_data['name'],
                    'capabilities' => $role_data['capabilities'],
                    'user_count' => $user_count,
                    'is_custom' => strpos($role_name, 'affiliate_') === 0,
                    'is_protected' => in_array($role_name, $this->protected_roles)
                ];
            }
        }
        
        $dashboard_data['roles'] = $custom_roles;

        // Get recent activity
        $dashboard_data['recent_activity'] = $this->get_recent_role_activity(10);

        // Get system health
        $dashboard_data['system_health'] = $this->get_system_health();

        return $dashboard_data;
    }

    /**
     * Get recent role activity
     * 
     * @param int $limit Number of records to retrieve
     * @return array Recent activity records
     */
    private function get_recent_role_activity($limit = 10) {
        if (!$this->db_manager) {
            return [];
        }

        global $wpdb;
        
        $audit_table = $wpdb->prefix . 'affcd_role_audit';
        
        // Check if table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $audit_table)) !== $audit_table) {
            return [];
        }

        $records = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$audit_table} 
             ORDER BY created_at DESC 
             LIMIT %d",
            $limit
        ));

        return array_map([$this, 'format_audit_record'], $records);
    }

    /**
     * Get system health metrics
     * 
     * @return array System health data
     */
    private function get_system_health() {
        $health = [
            'tables_exist' => false,
            'roles_registered' => false,
            'capabilities_valid' => true,
            'user_assignments' => 0,
            'orphaned_capabilities' => [],
            'overall_score' => 0
        ];

        // Check if tables exist
        global $wpdb;
        $audit_table = $wpdb->prefix . 'affcd_role_audit';
        $sessions_table = $wpdb->prefix . 'affcd_role_sessions';
        
        $health['tables_exist'] = (
            $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $audit_table)) === $audit_table &&
            $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $sessions_table)) === $sessions_table
        );

        // Check if custom roles are registered
        $custom_roles_count = 0;
        foreach ($this->default_capabilities as $role_name => $capabilities) {
            if (get_role($role_name)) {
                $custom_roles_count++;
            }
        }
        $health['roles_registered'] = $custom_roles_count === count($this->default_capabilities);

        // Count user assignments
        $health['user_assignments'] = 0;
        foreach (array_keys($this->default_capabilities) as $role_name) {
            $health['user_assignments'] += count(get_users(['role' => $role_name]));
        }

        // Check for orphaned capabilities
        $all_roles = wp_roles()->roles;
        $valid_capabilities = $this->get_all_available_capabilities();
        
        foreach ($all_roles as $role_name => $role_data) {
            if (strpos($role_name, 'affiliate_') === 0) {
                foreach (array_keys($role_data['capabilities']) as $capability) {
                    if (!in_array($capability, $valid_capabilities)) {
                        $health['orphaned_capabilities'][] = [
                            'role' => $role_name,
                            'capability' => $capability
                        ];
                    }
                }
            }
        }

        $health['capabilities_valid'] = empty($health['orphaned_capabilities']);

        // Calculate overall score
        $score = 0;
        $score += $health['tables_exist'] ? 25 : 0;
        $score += $health['roles_registered'] ? 25 : 0;
        $score += $health['capabilities_valid'] ? 25 : 0;
        $score += $health['user_assignments'] > 0 ? 25 : 0;
        
        $health['overall_score'] = $score;

        return $health;
    }

    /**
     * Export role configuration
     * 
     * @return array Exportable role configuration
     */
    public function export_role_configuration() {
        $export_data = [
            'export_date' => current_time('c'),
            'plugin_version' => defined('AFFCD_VERSION') ? AFFCD_VERSION : '1.0.0',
            'roles' => [],
            'user_assignments' => []
        ];

        // Export custom roles
        $all_roles = wp_roles()->roles;
        foreach ($all_roles as $role_name => $role_data) {
            if (strpos($role_name, 'affiliate_') === 0) {
                $export_data['roles'][$role_name] = [
                    'display_name' => $role_data['name'],
                    'capabilities' => $role_data['capabilities']
                ];
            }
        }

        // Export user assignments for custom roles
        foreach (array_keys($export_data['roles']) as $role_name) {
            $users = get_users(['role' => $role_name]);
            $export_data['user_assignments'][$role_name] = array_map(function($user) {
                return [
                    'user_login' => $user->user_login,
                    'user_email' => $user->user_email,
                    'display_name' => $user->display_name
                ];
            }, $users);
        }

        return $export_data;
    }

    /**
     * Import role configuration
     * 
     * @param array $import_data Role configuration data
     * @return bool|WP_Error Import result
     */
    public function import_role_configuration($import_data) {
        if (!is_array($import_data) || empty($import_data['roles'])) {
            return new WP_Error('invalid_data', __('Invalid import data format.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $imported_roles = 0;
        $skipped_roles = 0;
        $errors = [];

        // Import roles
        foreach ($import_data['roles'] as $role_name => $role_config) {
            // Validate role name
            if (strpos($role_name, 'affiliate_') !== 0) {
                $skipped_roles++;
                continue;
            }

            // Remove existing role if it exists
            if (get_role($role_name)) {
                remove_role($role_name);
            }

            // Create role
            $result = add_role(
                $role_name,
                $role_config['display_name'],
                $role_config['capabilities']
            );

            if ($result) {
                $imported_roles++;
                
                // Log role import
                $this->log_role_action('import_role', [
                    'role_name' => $role_name,
                    'capabilities' => $role_config['capabilities']
                ]);
            } else {
                $errors[] = sprintf(__('Failed to import role: %s', 'affiliatewp-cross-domain-plugin-suite'), $role_name);
            }
        }

        if (!empty($errors)) {
            return new WP_Error('import_errors', implode(', ', $errors));
        }

        return [
            'imported_roles' => $imported_roles,
            'skipped_roles' => $skipped_roles,
            'success' => true
        ];
    }

    /**
     * Clean up role management data
     * 
     * @param int $days_old Days old for cleanup
     * @return int Number of records cleaned
     */
    public function cleanup_old_data($days_old = 90) {
        if (!$this->db_manager) {
            return 0;
        }

        global $wpdb;
        
        $audit_table = $wpdb->prefix . 'affcd_role_audit';
        $sessions_table = $wpdb->prefix . 'affcd_role_sessions';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
        
        $cleaned_records = 0;

        // Clean old audit records
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $audit_table)) === $audit_table) {
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$audit_table} WHERE created_at < %s",
                $cutoff_date
            ));
            $cleaned_records += $deleted;
        }

        // Clean expired sessions
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $sessions_table)) === $sessions_table) {
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$sessions_table} WHERE expires_at < %s OR last_activity < %s",
                current_time('mysql'),
                $cutoff_date
            ));
            $cleaned_records += $deleted;
        }

        return $cleaned_records;
    }

    /**
     * Reset all custom roles to default
     * 
     * @return bool Success status
     */
    public function reset_roles_to_default() {
        // Remove all custom roles
        foreach (array_keys($this->default_capabilities) as $role_name) {
            remove_role($role_name);
        }

        // Re-register default roles
        $this->register_custom_roles();

        // Log reset action
        $this->log_role_action('reset_roles', [
            'roles_reset' => array_keys($this->default_capabilities)
        ]);

        return true;
    }

    /**
     * Check if user has affiliate role
     * 
     * @param int $user_id User ID
     * @return bool Whether user has affiliate role
     */
    public function user_has_affiliate_role($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        foreach ($user->roles as $role) {
            if (strpos($role, 'affiliate_') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get user's affiliate capabilities
     * 
     * @param int $user_id User ID
     * @return array User's affiliate capabilities
     */
    public function get_user_affiliate_capabilities($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return [];
        }

        $affiliate_capabilities = [];
        $all_caps = $this->get_all_available_capabilities();

        foreach ($user->get_role_caps() as $capability => $granted) {
            if ($granted && in_array($capability, $all_caps) && strpos($capability, 'affiliate_') === 0) {
                $affiliate_capabilities[] = $capability;
            }
        }

        return $affiliate_capabilities;
    }

    /**
     * Validate user permission for action
     * 
     * @param int $user_id User ID
     * @param string $capability Required capability
     * @return bool Whether user has permission
     */
    public function validate_user_permission($user_id, $capability) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        return $user->has_cap($capability);
    }
}
