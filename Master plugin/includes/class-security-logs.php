<?php
/**
 * Security Logs Management for Affiliate Cross Domain System
 * 
 * Path: /wp-content/plugins/affiliate-cross-domain-system/admin/class-security-logs.php
 * Plugin: Affiliate Cross Domain System (Master)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Security_Logs {

    private $table_name;
    private $max_log_entries = 10000;
    private $log_retention_days = 90;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'affcd_security_logs';
        
        add_action('init', [$this, 'init_hooks']);
        add_action('wp_ajax_affcd_get_security_logs', [$this, 'ajax_get_security_logs']);
        add_action('wp_ajax_affcd_clear_security_logs', [$this, 'ajax_clear_security_logs']);
        add_action('wp_ajax_affcd_export_security_logs', [$this, 'ajax_export_security_logs']);
        add_action('affcd_daily_cleanup', [$this, 'cleanup_old_logs']);
    }

    /**
     * Initialize hooks
     */
    public function init_hooks() {
        add_action('wp_login_failed', [$this, 'log_failed_login']);
        add_action('wp_login', [$this, 'log_successful_login'], 10, 2);
        add_action('admin_init', [$this, 'maybe_create_table']);
    }

    /**
     * Create security logs table if it doesn't exist
     */
    public function maybe_create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            severity enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
            user_id bigint(20) unsigned NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            event_data longtext,
            domain varchar(255),
            endpoint varchar(255),
            status_code int(11),
            response_time float,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_type (event_type),
            KEY idx_severity (severity),
            KEY idx_ip_address (ip_address),
            KEY idx_created_at (created_at),
            KEY idx_domain (domain),
            KEY idx_user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Log security event
     */
    public function log_event($event_type, $severity = 'medium', $data = [], $user_id = null) {
        global $wpdb;

        // Sanitize inputs
        $event_type = sanitize_text_field($event_type);
        $severity = in_array($severity, ['low', 'medium', 'high', 'critical'], true) ? $severity : 'medium';
        
        // Get client information
        $ip_address = $this->get_client_ip();
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');
        $domain = sanitize_text_field($_SERVER['HTTP_HOST'] ?? '');
        $endpoint = sanitize_text_field($_SERVER['REQUEST_URI'] ?? '');
        
        // Prepare event data
        $event_data = wp_json_encode([
            'timestamp'       => current_time('mysql'),
            'request_method'  => $_SERVER['REQUEST_METHOD'] ?? '',
            'referer'         => wp_get_referer(),
            'data'            => $data
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Insert log entry
        $result = $wpdb->insert(
            $this->table_name,
            [
                'event_type'  => $event_type,
                'severity'    => $severity,
                'user_id'     => $user_id ?: (get_current_user_id() ?: null),
                'ip_address'  => $ip_address,
                'user_agent'  => $user_agent,
                'event_data'  => $event_data,
                'domain'      => $domain,
                'endpoint'    => $endpoint,
                'created_at'  => current_time('mysql')
            ],
            ['%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        // Clean up old entries if we're approaching the limit
        $this->maybe_cleanup_logs();

        // Trigger alerts for critical events
        if ($severity === 'critical') {
            $this->trigger_security_alert($event_type, $data);
        }

        return $result !== false;
    }

    /**
     * Log API access
     */
    public function log_api_access($endpoint, $status_code, $response_time, $data = []) {
        $severity = 'low';
        
        // Determine severity based on status code
        if ($status_code >= 400 && $status_code < 500) {
            $severity = 'medium';
        } elseif ($status_code >= 500) {
            $severity = 'high';
        }

        global $wpdb;
        $wpdb->insert(
            $this->table_name,
            [
                'event_type'    => 'api_access',
                'severity'      => $severity,
                'ip_address'    => $this->get_client_ip(),
                'user_agent'    => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'event_data'    => wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'domain'        => sanitize_text_field($_SERVER['HTTP_HOST'] ?? ''),
                'endpoint'      => sanitize_text_field($endpoint),
                'status_code'   => (int) $status_code,
                'response_time' => (float) $response_time,
                'created_at'    => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s']
        );
    }

    /**
     * Log failed login attempt
     */
    public function log_failed_login($username) {
        $this->log_event('login_failed', 'medium', [
            'username'      => sanitize_text_field($username),
            'attempt_count' => $this->get_recent_failed_attempts()
        ]);
    }

    /**
     * Log successful login
     */
    public function log_successful_login($user_login, $user) {
        $this->log_event('login_success', 'low', [
            'username' => sanitize_text_field($user_login),
            'user_id'  => $user->ID
        ], $user->ID);
    }

    /**
     * Log suspicious activity
     */
    public function log_suspicious_activity($activity_type, $details = []) {
        $this->log_event('suspicious_activity', 'high', [
            'activity_type' => sanitize_text_field($activity_type),
            'details'       => $details
        ]);
    }

    /**
     * Log rate limit violations
     */
    public function log_rate_limit_violation($endpoint, $limit_type, $current_count, $limit) {
        $this->log_event('rate_limit_violation', 'medium', [
            'endpoint'      => sanitize_text_field($endpoint),
            'limit_type'    => sanitize_text_field($limit_type),
            'current_count' => (int) $current_count,
            'limit'         => (int) $limit
        ]);
    }

    /**
     * Get recent security logs
     */
    public function get_logs($limit = 100, $offset = 0, $filters = []) {
        global $wpdb;
        
        $where_conditions = ['1=1'];
        $where_values = [];

        // Apply filters
        if (!empty($filters['event_type'])) {
            $where_conditions[] = 'event_type = %s';
            $where_values[] = sanitize_text_field($filters['event_type']);
        }

        if (!empty($filters['severity'])) {
            $where_conditions[] = 'severity = %s';
            $where_values[] = sanitize_text_field($filters['severity']);
        }

        if (!empty($filters['ip_address'])) {
            $where_conditions[] = 'ip_address = %s';
            $where_values[] = sanitize_text_field($filters['ip_address']);
        }

        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = sanitize_text_field($filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = sanitize_text_field($filters['date_to']);
        }

        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "SELECT * FROM {$this->table_name} 
                WHERE {$where_clause} 
                ORDER BY created_at DESC 
                LIMIT %d OFFSET %d";
        
        $where_values[] = (int) $limit;
        $where_values[] = (int) $offset;
        
        return $wpdb->get_results($wpdb->prepare($sql, $where_values));
    }

    /**
     * Get total logs count with filters (for pagination)
     */
    private function get_logs_total($filters = []) {
        global $wpdb;

        $where_conditions = ['1=1'];
        $where_values = [];

        if (!empty($filters['event_type'])) {
            $where_conditions[] = 'event_type = %s';
            $where_values[] = sanitize_text_field($filters['event_type']);
        }

        if (!empty($filters['severity'])) {
            $where_conditions[] = 'severity = %s';
            $where_values[] = sanitize_text_field($filters['severity']);
        }

        if (!empty($filters['ip_address'])) {
            $where_conditions[] = 'ip_address = %s';
            $where_values[] = sanitize_text_field($filters['ip_address']);
        }

        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = sanitize_text_field($filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = sanitize_text_field($filters['date_to']);
        }

        $where_clause = implode(' AND ', $where_conditions);
        $sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";

        if ($where_values) {
            return (int) $wpdb->get_var($wpdb->prepare($sql, $where_values));
        }
        return (int) $wpdb->get_var($sql);
    }

    /**
     * Get security statistics
     */
    public function get_security_stats($days = 30) {
        global $wpdb;
        
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $stats = [];
        
        // Total events by severity
        $severity_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT severity, COUNT(*) as count 
             FROM {$this->table_name} 
             WHERE created_at >= %s 
             GROUP BY severity",
            $date_from
        ));
        
        $stats['by_severity'] = [];
        foreach ($severity_stats as $stat) {
            $stats['by_severity'][$stat->severity] = (int) $stat->count;
        }
        
        // Events by type
        $type_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, COUNT(*) as count 
             FROM {$this->table_name} 
             WHERE created_at >= %s 
             GROUP BY event_type 
             ORDER BY count DESC 
             LIMIT 10",
            $date_from
        ));
        
        $stats['by_type'] = [];
        foreach ($type_stats as $stat) {
            $stats['by_type'][$stat->event_type] = (int) $stat->count;
        }
        
        // Top IP addresses
        $ip_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT ip_address, COUNT(*) as count 
             FROM {$this->table_name} 
             WHERE created_at >= %s 
             GROUP BY ip_address 
             ORDER BY count DESC 
             LIMIT 10",
            $date_from
        ));
        
        $stats['by_ip'] = [];
        foreach ($ip_stats as $stat) {
            $stats['by_ip'][$stat->ip_address] = (int) $stat->count;
        }
        
        // Daily activity
        $daily_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM {$this->table_name} 
             WHERE created_at >= %s 
             GROUP BY DATE(created_at) 
             ORDER BY date DESC",
            $date_from
        ));
        
        $stats['daily_activity'] = [];
        foreach ($daily_stats as $stat) {
            $stats['daily_activity'][$stat->date] = (int) $stat->count;
        }
        
        return $stats;
    }

    /**
     * AJAX handler for getting security logs
     */
    public function ajax_get_security_logs() {
        check_ajax_referer('affcd_security_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_die('Unauthorized');
        }
        
        $page     = (int) ($_POST['page'] ?? 1);
        $per_page = (int) ($_POST['per_page'] ?? 25);
        $filters  = $_POST['filters'] ?? [];
        
        $offset = ($page - 1) * $per_page;
        $logs   = $this->get_logs($per_page, $offset, $filters);
        $total  = $this->get_logs_total($filters);
        
        wp_send_json_success([
            'logs'        => $logs,
            'total'       => (int) $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil($total / $per_page)
        ]);
    }

    /**
     * AJAX handler for clearing security logs
     */
    public function ajax_clear_security_logs() {
        check_ajax_referer('affcd_security_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        $result = $wpdb->query("TRUNCATE TABLE {$this->table_name}");
        
        if ($result !== false) {
            wp_send_json_success(['message' => 'Security logs cleared successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to clear security logs']);
        }
    }

    /**
     * AJAX handler for exporting security logs
     */
    public function ajax_export_security_logs() {
        check_ajax_referer('affcd_security_nonce', 'nonce');
        
        if (!current_user_can('manage_affiliates')) {
            wp_die('Unauthorized');
        }
        
        $filters = $_POST['filters'] ?? [];
        $logs = $this->get_logs(5000, 0, $filters); // Max 5000 entries
        
        $csv_data = [];
        $csv_data[] = ['ID', 'Event Type', 'Severity', 'IP Address', 'User Agent', 'Domain', 'Endpoint', 'Status Code', 'Response Time', 'Created At', 'Event Data'];
        
        foreach ($logs as $log) {
            $csv_data[] = [
                $log->id,
                $log->event_type,
                $log->severity,
                $log->ip_address,
                $log->user_agent,
                $log->domain,
                $log->endpoint,
                $log->status_code,
                $log->response_time,
                $log->created_at,
                $log->event_data
            ];
        }
        
        $filename = 'security-logs-' . date('Y-m-d-H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        foreach ($csv_data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Get recent failed login attempts for current IP
     */
    private function get_recent_failed_attempts() {
        global $wpdb;
        
        $ip_address = $this->get_client_ip();
        $since = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE event_type = 'login_failed' 
               AND ip_address = %s 
               AND created_at > %s",
            $ip_address,
            $since
        ));
    }

    /**
     * Clean up old log entries
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        // Remove entries older than retention period
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$this->log_retention_days} days"));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE created_at < %s",
            $cutoff_date
        ));
    }

    /**
     * Maybe cleanup logs if approaching limit
     */
    private function maybe_cleanup_logs() {
        global $wpdb;
        
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        if ($count > $this->max_log_entries) {
            // Remove oldest 1000 entries
            $wpdb->query(
                "DELETE FROM {$this->table_name} 
                 ORDER BY created_at ASC 
                 LIMIT 1000"
            );
        }
    }

    /**
     * Trigger security alert for critical events
     */
    private function trigger_security_alert($event_type, $data) {
        // Get admin email
        $admin_email = get_option('admin_email');
        
        // Prepare alert message
        $subject = sprintf(
            __('[Security Alert] %s - %s', 'affiliatewp-cross-domain-plugin-suite'), 
            get_site_url(), 
            ucwords(str_replace('_', ' ', $event_type))
        );
        
        $message = sprintf(
            __("A critical security event has been detected on your affiliate system:\n\nEvent Type: %s\nIP Address: %s\nTime: %s\nDetails: %s\n\nPlease review your security logs immediately.", 'affiliatewp-cross-domain-plugin-suite'),
            $event_type,
            $this->get_client_ip(),
            current_time('mysql'),
            wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
        
        // Send email notification
        wp_mail($admin_email, $subject, $message);
        
        // Log the alert sending
        error_log("AFFCD Security Alert: {$event_type} from IP {$this->get_client_ip()}");
    }
}
