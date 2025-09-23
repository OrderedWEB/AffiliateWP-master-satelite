<?php
/**
 * Vanity Codes List Table for Affiliate Cross Domain System
 * 
 * Plugin: Affiliate Cross Domain System (Master)
 * 
 * Provides the admin interface list table for managing vanity codes
 * with bulk operations, sorting, and filtering capabilities.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class AFFCD_Vanity_Codes_List_Table extends WP_List_Table {

    private $vanity_manager;
    private $per_page = 20;

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct([
            'singular' => 'vanity_code',
            'plural'   => 'vanity_codes',
            'ajax'     => true
        ]);
        
        $this->vanity_manager = new AFFCD_Vanity_Code_Manager();
    }

    /**
     * Get columns
     */
    public function get_columns() {
        return [
            'cb'                => '<input type="checkbox" />',
            'vanity_code'       => __('Vanity Code', 'affiliatewp-cross-domain-plugin-suite'),
            'affiliate_info'    => __('Affiliate', 'affiliatewp-cross-domain-plugin-suite'),
            'usage_stats'       => __('Usage Stats', 'affiliatewp-cross-domain-plugin-suite'),
            'revenue'           => __('Revenue', 'affiliatewp-cross-domain-plugin-suite'),
            'status'            => __('Status', 'affiliatewp-cross-domain-plugin-suite'),
            'expires_at'        => __('Expires', 'affiliatewp-cross-domain-plugin-suite'),
            'created_at'        => __('Created', 'affiliatewp-cross-domain-plugin-suite'),
            'actions'           => __('Actions', 'affiliatewp-cross-domain-plugin-suite')
        ];
    }

    /**
     * Get sortable columns
     */
    public function get_sortable_columns() {
        return [
            'vanity_code'       => ['vanity_code', false],
            'affiliate_info'    => ['affiliate_id', false],
            'usage_stats'       => ['usage_count', false],
            'revenue'           => ['revenue_generated', false],
            'status'            => ['status', false],
            'expires_at'        => ['expires_at', false],
            'created_at'        => ['created_at', true] // true means already sorted
        ];
    }

    /**
     * Get bulk actions
     */
    public function get_bulk_actions() {
        $actions = [];
        
        if (current_user_can('manage_affiliates')) {
            $actions['activate']   = __('Activate', 'affiliatewp-cross-domain-plugin-suite');
            $actions['deactivate'] = __('Deactivate', 'affiliatewp-cross-domain-plugin-suite');
            $actions['delete']     = __('Delete', 'affiliatewp-cross-domain-plugin-suite');
        }
        
        return $actions;
    }

    /**
     * Prepare items
     */
    public function prepare_items() {
        // Handle bulk actions
        $this->process_bulk_action();
        
        // Set up columns
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        // Get current page and search term
        $current_page = $this->get_pagenum();
        $search = Sanitise_text_field($_GET['s'] ?? '');
        
        // Get sorting parameters
        $orderby = Sanitise_text_field($_GET['orderby'] ?? 'created_at');
        $order = Sanitise_text_field($_GET['order'] ?? 'desc');
        
        // Get filter parameters
        $status_filter = Sanitise_text_field($_GET['status'] ?? '');
        $affiliate_filter = absint($_GET['affiliate_id'] ?? 0);
        
        // Prepare query arguments
        $args = [
            'per_page' => $this->per_page,
            'page' => $current_page,
            'orderby' => $orderby,
            'order' => $order,
            'search' => $search,
            'status' => $status_filter,
            'affiliate_id' => $affiliate_filter
        ];

        // Get vanity codes data
        $data = $this->vanity_manager->get_vanity_codes_list($args);
        
        // Set items and pagination
        $this->items = $data['items'];
        
        $this->set_pagination_args([
            'total_items' => $data['total_items'],
            'per_page' => $this->per_page,
            'total_pages' => $data['total_pages']
        ]);
    }

    /**
     * Default column display
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'vanity_code':
                return $this->column_vanity_code($item);
            case 'affiliate_info':
                return $this->column_affiliate_info($item);
            case 'usage_stats':
                return $this->column_usage_stats($item);
            case 'revenue':
                return $this->column_revenue($item);
            case 'status':
                return $this->column_status($item);
            case 'expires_at':
                return $this->column_expires_at($item);
            case 'created_at':
                return $this->column_created_at($item);
            case 'actions':
                return $this->column_actions($item);
            default:
                return print_r($item, true);
        }
    }

    /**
     * Checkbox column
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="vanity_ids[]" value="%s" />',
            $item->id
        );
    }

    /**
     * Vanity code column
     */
    public function column_vanity_code($item) {
        $edit_url = admin_url('admin.php?page=affcd-vanity-codes&action=edit&vanity_id=' . $item->id);
        $delete_url = wp_nonce_url(
            admin_url('admin.php?page=affcd-vanity-codes&action=delete&vanity_id=' . $item->id),
            'delete_vanity_code_' . $item->id
        );

        $actions = [];
        
        if (current_user_can('manage_affiliates')) {
            $actions['edit'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url($edit_url),
                __('Edit', 'affiliatewp-cross-domain-plugin-suite')
            );
            
            $actions['delete'] = sprintf(
                '<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
                esc_url($delete_url),
                esc_js(__('Are you sure you want to delete this vanity code?', 'affiliatewp-cross-domain-plugin-suite')),
                __('Delete', 'affiliatewp-cross-domain-plugin-suite')
            );
        }

        $actions['view_stats'] = sprintf(
            '<a href="#" onclick="viewVanityStats(%d)">%s</a>',
            $item->id,
            __('View Stats', 'affiliatewp-cross-domain-plugin-suite')
        );

        return sprintf(
            '<strong><a href="%s" class="row-title">%s</a></strong><br>
            <span class="description">%s</span>%s',
            esc_url($edit_url),
            esc_html($item->vanity_code),
            esc_html($item->description ?: __('No description', 'affiliatewp-cross-domain-plugin-suite')),
            $this->row_actions($actions)
        );
    }

    /**
     * Affiliate info column
     */
    public function column_affiliate_info($item) {
        $affiliate = affwp_get_affiliate($item->affiliate_id);
        
        if (!$affiliate) {
            return '<span class="error">' . __('Invalid Affiliate', 'affiliatewp-cross-domain-plugin-suite') . '</span>';
        }

        $user = get_userdata($affiliate->user_id);
        $affiliate_name = $user ? $user->display_name : __('Unknown', 'affiliatewp-cross-domain-plugin-suite');
        
        return sprintf(
            '<strong>%s</strong><br>
            <span class="description">ID: %d | Code: %s</span>',
            esc_html($affiliate_name),
            $item->affiliate_id,
            esc_html($item->affiliate_code)
        );
    }

    /**
     * Usage stats column
     */
    public function column_usage_stats($item) {
        $conversion_rate = $item->usage_count > 0 ? 
            round(($item->conversion_count / $item->usage_count) * 100, 2) : 0;

        return sprintf(
            '<div class="usage-stats">
                <div><strong>%s</strong> %s</div>
                <div><strong>%s</strong> %s</div>
                <div><span class="conversion-rate">%s%% %s</span></div>
            </div>',
            number_format($item->usage_count),
            __('uses', 'affiliatewp-cross-domain-plugin-suite'),
            number_format($item->conversion_count),
            __('conversions', 'affiliatewp-cross-domain-plugin-suite'),
            $conversion_rate,
            __('conversion rate', 'affiliatewp-cross-domain-plugin-suite')
        );
    }

    /**
     * Revenue column
     */
    public function column_revenue($item) {
        $currency = function_exists('affwp_get_currency') ? affwp_get_currency() : 'USD';
        
        return sprintf(
            '<strong>%s %s</strong>',
            $currency,
            number_format($item->revenue_generated, 2)
        );
    }

    /**
     * Status column
     */
    public function column_status($item) {
        $status_classes = [
            'active' => 'status-active',
            'inactive' => 'status-inactive',
            'expired' => 'status-expired',
            'suspended' => 'status-suspended'
        ];

        $status_labels = [
            'active' => __('Active', 'affiliatewp-cross-domain-plugin-suite'),
            'inactive' => __('Inactive', 'affiliatewp-cross-domain-plugin-suite'),
            'expired' => __('Expired', 'affiliatewp-cross-domain-plugin-suite'),
            'suspended' => __('Suspended', 'affiliatewp-cross-domain-plugin-suite')
        ];

        $class = $status_classes[$item->status] ?? 'status-unknown';
        $label = $status_labels[$item->status] ?? ucfirst($item->status);

        return sprintf(
            '<span class="status-indicator %s">%s</span>',
            esc_attr($class),
            esc_html($label)
        );
    }

    /**
     * Expires at column
     */
    public function column_expires_at($item) {
        if (!$item->expires_at) {
            return '<span class="never-expires">' . __('Never', 'affiliatewp-cross-domain-plugin-suite') . '</span>';
        }

        $expires_timestamp = strtotime($item->expires_at);
        $current_time = time();
        
        if ($expires_timestamp < $current_time) {
            return sprintf(
                '<span class="expired">%s<br><small>%s</small></span>',
                __('Expired', 'affiliatewp-cross-domain-plugin-suite'),
                human_time_diff($expires_timestamp) . ' ' . __('ago', 'affiliatewp-cross-domain-plugin-suite')
            );
        } else {
            return sprintf(
                '<span class="expires-future">%s<br><small>%s</small></span>',
                date_i18n(get_option('date_format'), $expires_timestamp),
                human_time_diff($expires_timestamp) . ' ' . __('remaining', 'affiliatewp-cross-domain-plugin-suite')
            );
        }
    }

    /**
     * Created at column
     */
    public function column_created_at($item) {
        $created_timestamp = strtotime($item->created_at);
        
        return sprintf(
            '<span title="%s">%s<br><small>%s</small></span>',
            date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $created_timestamp),
            date_i18n(get_option('date_format'), $created_timestamp),
            human_time_diff($created_timestamp) . ' ' . __('ago', 'affiliatewp-cross-domain-plugin-suite')
        );
    }

    /**
     * Actions column
     */
    public function column_actions($item) {
        $actions = [];
        
        if (current_user_can('manage_affiliates')) {
            // Quick status toggles
            if ($item->status === 'active') {
                $actions[] = sprintf(
                    '<button type="button" class="button button-small" onclick="toggleVanityStatus(%d, \'inactive\')">%s</button>',
                    $item->id,
                    __('Deactivate', 'affiliatewp-cross-domain-plugin-suite')
                );
            } else {
                $actions[] = sprintf(
                    '<button type="button" class="button button-small" onclick="toggleVanityStatus(%d, \'active\')">%s</button>',
                    $item->id,
                    __('Activate', 'affiliatewp-cross-domain-plugin-suite')
                );
            }
        }

        // Analytics button
        $actions[] = sprintf(
            '<button type="button" class="button button-small" onclick="showVanityAnalytics(%d)">%s</button>',
            $item->id,
            __('Analytics', 'affiliatewp-cross-domain-plugin-suite')
        );

        return implode(' ', $actions);
    }

    /**
     * Process bulk actions
     */
    public function process_bulk_action() {
        $action = $this->current_action();
        
        if (!$action) {
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'bulk-' . $this->_args['plural'])) {
            return;
        }

        if (!current_user_can('manage_affiliates')) {
            wp_die(__('You do not have sufficient permissions.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $vanity_ids = array_map('absint', $_GET['vanity_ids'] ?? []);
        
        if (empty($vanity_ids)) {
            return;
        }

        $results = $this->vanity_manager->bulk_operations($action, $vanity_ids);
        
        $success_count = 0;
        $error_count = 0;
        
        foreach ($results as $result) {
            if (is_wp_error($result)) {
                $error_count++;
            } else {
                $success_count++;
            }
        }

        // Add admin notice
        $message = sprintf(
            __('%d items processed successfully', 'affiliatewp-cross-domain-plugin-suite'),
            $success_count
        );
        
        if ($error_count > 0) {
            $message .= sprintf(
                __(', %d errors occurred', 'affiliatewp-cross-domain-plugin-suite'),
                $error_count
            );
        }

        add_action('admin_notices', function() use ($message) {
            printf(
                '<div class="notice notice-info is-dismissible"><p>%s</p></div>',
                esc_html($message)
            );
        });
    }

    /**
     * Display table navigation
     */
    protected function display_tablenav($which) {
        if ('top' === $which) {
            $this->extra_tablenav($which);
        }
        
        parent::display_tablenav($which);
    }

    /**
     * Extra table navigation (filters)
     */
    protected function extra_tablenav($which) {
        if ('top' !== $which) {
            return;
        }

        ?>
        <div class="alignleft actions">
            <?php $this->status_filter_dropdown(); ?>
            <?php $this->affiliate_filter_dropdown(); ?>
            <?php submit_button(__('Filter', 'affiliatewp-cross-domain-plugin-suite'), 'secondary', 'filter', false); ?>
            
            <?php if (!empty($_GET['status']) || !empty($_GET['affiliate_id']) || !empty($_GET['s'])): ?>
                <a href="<?php echo admin_url('admin.php?page=affcd-vanity-codes'); ?>" class="button">
                    <?php _e('Clear Filters', 'affiliatewp-cross-domain-plugin-suite'); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Status filter dropdown
     */
    private function status_filter_dropdown() {
        $current_status = $_GET['status'] ?? '';
        
        $statuses = [
            '' => __('All Statuses', 'affiliatewp-cross-domain-plugin-suite'),
            'active' => __('Active', 'affiliatewp-cross-domain-plugin-suite'),
            'inactive' => __('Inactive', 'affiliatewp-cross-domain-plugin-suite'),
            'expired' => __('Expired', 'affiliatewp-cross-domain-plugin-suite'),
            'suspended' => __('Suspended', 'affiliatewp-cross-domain-plugin-suite')
        ];
        
        echo '<select name="status" id="status-filter">';
        foreach ($statuses as $value => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($value),
                selected($current_status, $value, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }

    /**
     * Affiliate filter dropdown
     */
    private function affiliate_filter_dropdown() {
        $current_affiliate = $_GET['affiliate_id'] ?? '';
        
        // Get affiliates list
        $affiliates = affwp_get_affiliates([
            'number' => 100,
            'status' => 'active'
        ]);
        
        echo '<select name="affiliate_id" id="affiliate-filter">';
        echo '<option value="">' . esc_html__('All Affiliates', 'affiliatewp-cross-domain-plugin-suite') . '</option>';
        
        foreach ($affiliates as $affiliate) {
            $user = get_userdata($affiliate->user_id);
            $name = $user ? $user->display_name : __('Unknown', 'affiliatewp-cross-domain-plugin-suite');
            
            printf(
                '<option value="%d"%s>%s (ID: %d)</option>',
                $affiliate->affiliate_id,
                selected($current_affiliate, $affiliate->affiliate_id, false),
                esc_html($name),
                $affiliate->affiliate_id
            );
        }
        echo '</select>';
    }

    /**
     * No items message
     */
    public function no_items() {
        $search = $_GET['s'] ?? '';
        
        if (!empty($search)) {
            printf(
                __('No vanity codes found matching "%s".', 'affiliatewp-cross-domain-plugin-suite'),
                esc_html($search)
            );
        } else {
            _e('No vanity codes found.', 'affiliatewp-cross-domain-plugin-suite');
        }
    }

    /**
     * Generate the table navigation above or below the table
     */
    protected function pagination($which) {
        if (empty($this->_pagination_args)) {
            return;
        }

        $total_items = $this->_pagination_args['total_items'];
        $total_pages = $this->_pagination_args['total_pages'];
        $infinite_scroll = false;

        if (isset($this->_pagination_args['infinite_scroll'])) {
            $infinite_scroll = $this->_pagination_args['infinite_scroll'];
        }

        if ('top' === $which && $total_pages > 1) {
            $this->screen->render_screen_reader_content('heading_pagination');
        }

        $output = '<span class="displaying-num">' . sprintf(
            _n('%s item', '%s items', $total_items, 'affiliatewp-cross-domain-plugin-suite'),
            number_format_i18n($total_items)
        ) . '</span>';

        $current = $this->get_pagenum();

        $page_links = paginate_links([
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => $total_pages,
            'current' => $current
        ]);

        if ($page_links) {
            $output .= '<span class="pagination-links">' . $page_links . '</span>';
        }

        if ($total_pages > 1) {
            echo "<div class='tablenav-pages{$infinite_scroll_class}'>$output</div>";
        }
    }
}