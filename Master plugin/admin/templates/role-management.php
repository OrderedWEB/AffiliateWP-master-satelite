<?php
/**
 * Role Management Admin Template
 *
 * @package AffiliateWP_Cross_Domain_Plugin_Suite
 * @subpackage Templates/Admin
 * @version 1.0.0
 * @author Richard King, Starne Consulting
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Security check
if (!current_user_can('manage_affcd_roles')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'affiliatewp-cross-domain-plugin-suite'));
}

// Get role manager instance
$role_manager = AffiliateWP_Cross_Domain_Role_Manager::get_instance();
$current_tab = sanitize_key($_GET['tab'] ?? 'roles');
$available_capabilities = $role_manager->get_available_capabilities();
$custom_roles = $role_manager->get_custom_roles();
$role_assignments = $role_manager->get_role_assignments();
?>

<div class="wrap affcd-role-management">
    <h1 class="wp-heading-inline">
        <?php _e('Role Management', 'affiliatewp-cross-domain-plugin-suite'); ?>
    </h1>
    
    <?php if (current_user_can('create_affcd_roles')): ?>
        <a href="#" class="page-title-action" id="add-new-role-btn">
            <?php _e('Add New Role', 'affiliatewp-cross-domain-plugin-suite'); ?>
        </a>
    <?php endif; ?>

    <hr class="wp-header-end">

    <?php
    // Display admin notices
    if (isset($_GET['message'])) {
        $message = sanitize_key($_GET['message']);
        switch ($message) {
            case 'role_created':
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     esc_html__('Role created successfully.', 'affiliatewp-cross-domain-plugin-suite') . 
                     '</p></div>';
                break;
            case 'role_updated':
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     esc_html__('Role updated successfully.', 'affiliatewp-cross-domain-plugin-suite') . 
                     '</p></div>';
                break;
            case 'role_deleted':
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     esc_html__('Role deleted successfully.', 'affiliatewp-cross-domain-plugin-suite') . 
                     '</p></div>';
                break;
            case 'permissions_updated':
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     esc_html__('Permissions updated successfully.', 'affiliatewp-cross-domain-plugin-suite') . 
                     '</p></div>';
                break;
            case 'error':
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('An error occurred. Please try again.', 'affiliatewp-cross-domain-plugin-suite') . 
                     '</p></div>';
                break;
        }
    }
    ?>

    <!-- Navigation Tabs -->
    <nav class="nav-tab-wrapper wp-clearfix">
        <a href="<?php echo esc_url(add_query_arg('tab', 'roles', admin_url('admin.php?page=affcd-role-management'))); ?>" 
           class="nav-tab <?php echo $current_tab === 'roles' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Roles', 'affiliatewp-cross-domain-plugin-suite'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'capabilities', admin_url('admin.php?page=affcd-role-management'))); ?>" 
           class="nav-tab <?php echo $current_tab === 'capabilities' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Capabilities', 'affiliatewp-cross-domain-plugin-suite'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'assignments', admin_url('admin.php?page=affcd-role-management'))); ?>" 
           class="nav-tab <?php echo $current_tab === 'assignments' ? 'nav-tab-active' : ''; ?>">
            <?php _e('User Assignments', 'affiliatewp-cross-domain-plugin-suite'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'audit', admin_url('admin.php?page=affcd-role-management'))); ?>" 
           class="nav-tab <?php echo $current_tab === 'audit' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Audit Log', 'affiliatewp-cross-domain-plugin-suite'); ?>
        </a>
    </nav>

    <div class="affcd-tab-content">
        <?php
        switch ($current_tab) {
            case 'roles':
                include_once dirname(__FILE__) . '/role-management/roles-tab.php';
                break;
            case 'capabilities':
                include_once dirname(__FILE__) . '/role-management/capabilities-tab.php';
                break;
            case 'assignments':
                include_once dirname(__FILE__) . '/role-management/assignments-tab.php';
                break;
            case 'audit':
                include_once dirname(__FILE__) . '/role-management/audit-tab.php';
                break;
            default:
                include_once dirname(__FILE__) . '/role-management/roles-tab.php';
                break;
        }
        ?>
    </div>
</div>

<!-- Roles Tab Content -->
<?php if ($current_tab === 'roles'): ?>
<div class="affcd-roles-section">
    <div class="affcd-section-header">
        <h2><?php _e('Role Management', 'affiliatewp-cross-domain-plugin-suite'); ?></h2>
        <p class="description">
            <?php _e('Manage user roles and their associated capabilities for the affiliate system.', 'affiliatewp-cross-domain-plugin-suite'); ?>
        </p>
    </div>

    <!-- Quick Actions -->
    <div class="affcd-quick-actions">
        <div class="affcd-action-buttons">
            <button type="button" class="button" id="bulk-edit-roles" disabled>
                <?php _e('Bulk Edit', 'affiliatewp-cross-domain-plugin-suite'); ?>
            </button>
            <button type="button" class="button" id="export-roles">
                <?php _e('Export Roles', 'affiliatewp-cross-domain-plugin-suite'); ?>
            </button>
            <button type="button" class="button" id="import-roles">
                <?php _e('Import Roles', 'affiliatewp-cross-domain-plugin-suite'); ?>
            </button>
        </div>
        <div class="affcd-search-filter">
            <input type="search" id="role-search" placeholder="<?php esc_attr_e('Search roles...', 'affiliatewp-cross-domain-plugin-suite'); ?>" class="search-input">
            <select id="role-filter" class="filter-select">
                <option value=""><?php _e('All Roles', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                <option value="custom"><?php _e('Custom Roles', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                <option value="system"><?php _e('System Roles', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                <option value="active"><?php _e('Active Only', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
            </select>
        </div>
    </div>

    <!-- Roles Table -->
    <div class="affcd-table-container">
        <table class="wp-list-table widefat fixed striped roles-table" id="affcd-roles-table">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-cb check-column">
                        <input type="checkbox" id="select-all-roles" />
                    </th>
                    <th scope="col" class="manage-column column-name column-primary">
                        <?php _e('Role Name', 'affiliatewp-cross-domain-plugin-suite'); ?>
                    </th>
                    <th scope="col" class="manage-column column-display-name">
                        <?php _e('Display Name', 'affiliatewp-cross-domain-plugin-suite'); ?>
                    </th>
                    <th scope="col" class="manage-column column-capabilities">
                        <?php _e('Capabilities', 'affiliatewp-cross-domain-plugin-suite'); ?>
                    </th>
                    <th scope="col" class="manage-column column-users">
                        <?php _e('Users', 'affiliatewp-cross-domain-plugin-suite'); ?>
                    </th>
                    <th scope="col" class="manage-column column-type">
                        <?php _e('Type', 'affiliatewp-cross-domain-plugin-suite'); ?>
                    </th>
                    <th scope="col" class="manage-column column-status">
                        <?php _e('Status', 'affiliatewp-cross-domain-plugin-suite'); ?>
                    </th>
                    <th scope="col" class="manage-column column-actions">
                        <?php _e('Actions', 'affiliatewp-cross-domain-plugin-suite'); ?>
                    </th>
                </tr>
            </thead>
            <tbody id="roles-table-body">
                <?php
                $all_roles = wp_roles()->get_names();
                foreach ($all_roles as $role_slug => $role_name):
                    $role = get_role($role_slug);
                    $user_count = count_users()['avail_roles'][$role_slug] ?? 0;
                    $is_custom = isset($custom_roles[$role_slug]);
                    $role_capabilities = $role ? array_keys($role->capabilities) : [];
                    $affcd_capabilities = array_filter($role_capabilities, function($cap) {
                        return strpos($cap, 'affcd_') === 0 || strpos($cap, 'manage_affiliate') === 0;
                    });
                ?>
                <tr class="role-row" data-role="<?php echo esc_attr($role_slug); ?>">
                    <th scope="row" class="check-column">
                        <input type="checkbox" name="selected_roles[]" value="<?php echo esc_attr($role_slug); ?>" />
                    </th>
                    <td class="name column-name column-primary">
                        <strong><?php echo esc_html($role_slug); ?></strong>
                        <div class="row-actions">
                            <span class="edit">
                                <a href="#" class="edit-role" data-role="<?php echo esc_attr($role_slug); ?>">
                                    <?php _e('Edit', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </a> |
                            </span>
                            <span class="capabilities">
                                <a href="#" class="view-capabilities" data-role="<?php echo esc_attr($role_slug); ?>">
                                    <?php _e('View Capabilities', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </a>
                                <?php if ($is_custom): ?> |
                                <span class="delete">
                                    <a href="#" class="delete-role text-danger" data-role="<?php echo esc_attr($role_slug); ?>">
                                        <?php _e('Delete', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                    </a>
                                </span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </td>
                    <td class="display-name column-display-name">
                        <?php echo esc_html($role_name); ?>
                    </td>
                    <td class="capabilities column-capabilities">
                        <span class="capability-count">
                            <?php printf(_n('%d capability', '%d capabilities', count($affcd_capabilities), 'affiliatewp-cross-domain-plugin-suite'), count($affcd_capabilities)); ?>
                        </span>
                        <?php if (count($affcd_capabilities) > 0): ?>
                            <div class="capability-preview">
                                <?php
                                $preview_caps = array_slice($affcd_capabilities, 0, 3);
                                echo esc_html(implode(', ', $preview_caps));
                                if (count($affcd_capabilities) > 3) {
                                    echo '...';
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="users column-users">
                        <span class="user-count"><?php echo esc_html($user_count); ?></span>
                        <?php if ($user_count > 0): ?>
                            <a href="<?php echo esc_url(admin_url('users.php?role=' . urlencode($role_slug))); ?>" class="view-users">
                                <?php _e('View Users', 'affiliatewp-cross-domain-plugin-suite'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                    <td class="type column-type">
                        <span class="role-type <?php echo $is_custom ? 'custom' : 'system'; ?>">
                            <?php echo $is_custom ? __('Custom', 'affiliatewp-cross-domain-plugin-suite') : __('System', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </span>
                    </td>
                    <td class="status column-status">
                        <span class="status-indicator active">
                            <?php _e('Active', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </span>
                    </td>
                    <td class="actions column-actions">
                        <div class="action-buttons">
                            <button type="button" class="button button-small edit-role-btn" data-role="<?php echo esc_attr($role_slug); ?>">
                                <?php _e('Edit', 'affiliatewp-cross-domain-plugin-suite'); ?>
                            </button>
                            <button type="button" class="button button-small clone-role-btn" data-role="<?php echo esc_attr($role_slug); ?>">
                                <?php _e('Clone', 'affiliatewp-cross-domain-plugin-suite'); ?>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Role Statistics -->
    <div class="affcd-role-statistics">
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo esc_html(count($all_roles)); ?></h3>
                <p><?php _e('Total Roles', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
            </div>
            <div class="stat-card">
                <h3><?php echo esc_html(count($custom_roles)); ?></h3>
                <p><?php _e('Custom Roles', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
            </div>
            <div class="stat-card">
                <h3><?php echo esc_html(array_sum(array_values(count_users()['avail_roles']))); ?></h3>
                <p><?php _e('Total Users', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
            </div>
            <div class="stat-card">
                <h3><?php echo esc_html(count($available_capabilities)); ?></h3>
                <p><?php _e('Available Capabilities', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- New Role Modal -->
<div id="new-role-modal" class="affcd-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php _e('Create New Role', 'affiliatewp-cross-domain-plugin-suite'); ?></h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <form id="new-role-form" method="post" action="<?php echo esc_url(admin_url('admin.php?page=affcd-role-management')); ?>">
            <?php wp_nonce_field('affcd_create_role', 'affcd_role_nonce'); ?>
            <input type="hidden" name="action" value="create_role">
            
            <div class="modal-body">
                <div class="form-section">
                    <h3><?php _e('Basic Information', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                    
                    <div class="form-row">
                        <label for="role-slug">
                            <?php _e('Role Slug:', 'affiliatewp-cross-domain-plugin-suite'); ?>
                            <span class="required">*</span>
                        </label>
                        <input type="text" id="role-slug" name="role_slug" required 
                               pattern="[a-z0-9_]+" 
                               title="<?php esc_attr_e('Only lowercase letters, numbers, and underscores allowed', 'affiliatewp-cross-domain-plugin-suite'); ?>">
                        <small class="description">
                            <?php _e('Unique identifier for the role. Use lowercase letters, numbers, and underscores only.', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </small>
                    </div>

                    <div class="form-row">
                        <label for="role-display-name">
                            <?php _e('Display Name:', 'affiliatewp-cross-domain-plugin-suite'); ?>
                            <span class="required">*</span>
                        </label>
                        <input type="text" id="role-display-name" name="role_display_name" required>
                        <small class="description">
                            <?php _e('Human-readable name for the role.', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </small>
                    </div>

                    <div class="form-row">
                        <label for="role-description">
                            <?php _e('Description:', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </label>
                        <textarea id="role-description" name="role_description" rows="3"></textarea>
                        <small class="description">
                            <?php _e('Optional description of the role and its purpose.', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </small>
                    </div>
                </div>

                <div class="form-section">
                    <h3><?php _e('Base Role Template', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                    
                    <div class="form-row">
                        <label for="base-role">
                            <?php _e('Copy Capabilities From:', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </label>
                        <select id="base-role" name="base_role">
                            <option value=""><?php _e('Start with no capabilities', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                            <?php foreach ($all_roles as $role_slug => $role_name): ?>
                                <option value="<?php echo esc_attr($role_slug); ?>">
                                    <?php echo esc_html($role_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="description">
                            <?php _e('Optionally start with capabilities from an existing role.', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </small>
                    </div>
                </div>

                <div class="form-section">
                    <h3><?php _e('Affiliate System Capabilities', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                    
                    <div class="capabilities-grid">
                        <?php
                        $capability_groups = [
                            'Core Management' => [
                                'manage_affcd' => __('Manage Cross Domain System', 'affiliatewp-cross-domain-plugin-suite'),
                                'manage_affcd_settings' => __('Manage System Settings', 'affiliatewp-cross-domain-plugin-suite'),
                                'view_affcd_dashboard' => __('View Dashboard', 'affiliatewp-cross-domain-plugin-suite'),
                            ],
                            'Vanity Codes' => [
                                'manage_affcd_codes' => __('Manage Vanity Codes', 'affiliatewp-cross-domain-plugin-suite'),
                                'create_affcd_codes' => __('Create Vanity Codes', 'affiliatewp-cross-domain-plugin-suite'),
                                'edit_affcd_codes' => __('Edit Vanity Codes', 'affiliatewp-cross-domain-plugin-suite'),
                                'delete_affcd_codes' => __('Delete Vanity Codes', 'affiliatewp-cross-domain-plugin-suite'),
                            ],
                            'Domain Management' => [
                                'manage_affcd_domains' => __('Manage Authorised Domains', 'affiliatewp-cross-domain-plugin-suite'),
                                'create_affcd_domains' => __('Add New Domains', 'affiliatewp-cross-domain-plugin-suite'),
                                'edit_affcd_domains' => __('Edit Domain Settings', 'affiliatewp-cross-domain-plugin-suite'),
                                'delete_affcd_domains' => __('Remove Domains', 'affiliatewp-cross-domain-plugin-suite'),
                            ],
                            'Analytics & Reports' => [
                                'view_affcd_analytics' => __('View Analytics', 'affiliatewp-cross-domain-plugin-suite'),
                                'export_affcd_reports' => __('Export Reports', 'affiliatewp-cross-domain-plugin-suite'),
                                'view_affcd_logs' => __('View System Logs', 'affiliatewp-cross-domain-plugin-suite'),
                            ],
                            'User Management' => [
                                'manage_affcd_roles' => __('Manage Roles', 'affiliatewp-cross-domain-plugin-suite'),
                                'create_affcd_roles' => __('Create Roles', 'affiliatewp-cross-domain-plugin-suite'),
                                'edit_affcd_roles' => __('Edit Roles', 'affiliatewp-cross-domain-plugin-suite'),
                                'delete_affcd_roles' => __('Delete Roles', 'affiliatewp-cross-domain-plugin-suite'),
                                'assign_affcd_roles' => __('Assign Roles to Users', 'affiliatewp-cross-domain-plugin-suite'),
                            ],
                        ];

                        foreach ($capability_groups as $group_name => $capabilities):
                        ?>
                        <div class="capability-group">
                            <h4><?php echo esc_html($group_name); ?></h4>
                            <div class="capability-checkboxes">
                                <?php foreach ($capabilities as $capability => $description): ?>
                                <label class="capability-checkbox">
                                    <input type="checkbox" name="capabilities[]" value="<?php echo esc_attr($capability); ?>">
                                    <span class="capability-name"><?php echo esc_html($description); ?></span>
                                    <small class="capability-slug"><?php echo esc_html($capability); ?></small>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="button button-secondary modal-close">
                    <?php _e('Cancel', 'affiliatewp-cross-domain-plugin-suite'); ?>
                </button>
                <button type="submit" class="button button-primary">
                    <?php _e('Create Role', 'affiliatewp-cross-domain-plugin-suite'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Role Modal -->
<div id="edit-role-modal" class="affcd-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php _e('Edit Role', 'affiliatewp-cross-domain-plugin-suite'); ?></h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <form id="edit-role-form" method="post">
            <?php wp_nonce_field('affcd_edit_role', 'affcd_edit_role_nonce'); ?>
            <input type="hidden" name="action" value="edit_role">
            <input type="hidden" name="role_slug" id="edit-role-slug">
            
            <div class="modal-body">
                <div id="edit-role-content">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="button button-secondary modal-close">
                    <?php _e('Cancel', 'affiliatewp-cross-domain-plugin-suite'); ?>
                </button>
                <button type="submit" class="button button-primary">
                    <?php _e('Update Role', 'affiliatewp-cross-domain-plugin-suite'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Role Details Modal -->
<div id="role-details-modal" class="affcd-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h2 id="role-details-title"><?php _e('Role Details', 'affiliatewp-cross-domain-plugin-suite'); ?></h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="role-details-content">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="button button-secondary modal-close">
                <?php _e('Close', 'affiliatewp-cross-domain-plugin-suite'); ?>
            </button>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialise role management interface
    if (typeof AffiliateWPCrossDomain !== 'undefined' && AffiliateWPCrossDomain.RoleManager) {
        AffiliateWPCrossDomain.RoleManager.init();
    }
});
</script>