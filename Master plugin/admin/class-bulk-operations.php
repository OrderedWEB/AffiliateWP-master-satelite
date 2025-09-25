<?php
/**
 * Complete Bulk Operations Class
 * File: /wp-content/plugins/affiliate-cross-domain-full/admin/class-bulk-operations.php
 * Plugin: AffiliateWP Cross Domain Full
 * Author: Richard King, Starne Consulting
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AFFCD_Bulk_Operations handles bulk operations for vanity codes, domains,
 * and other entities with progress tracking and error handling.
 */
class AFFCD_Bulk_Operations {

    /**
     * Database manager instance
     *
     * @var AFFCD_Database_Manager
     */
    private $database_manager;

    /**
     * Vanity code manager instance
     *
     * @var AFFCD_Vanity_Code_Manager
     */
    private $vanity_code_manager;

    /**
     * Maximum batch size for processing
     *
     * @var int
     */
    private $max_batch_size = 100;

    /**
     * Progress option key prefix
     *
     * @var string
     */
    private $progress_key_prefix = 'affcd_bulk_progress_';

    /**
     * Constructor
     */
    public function __construct() {
        $this->database_manager   = new AFFCD_Database_Manager();
        $this->vanity_code_manager = new AFFCD_Vanity_Code_Manager();

        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // AJAX handlers for bulk operations
        add_action('wp_ajax_affcd_bulk_vanity_codes',        [$this, 'ajax_bulk_vanity_codes']);
        add_action('wp_ajax_affcd_bulk_domains',             [$this, 'ajax_bulk_domains']);
        add_action('wp_ajax_affcd_bulk_import_codes',        [$this, 'ajax_bulk_import_codes']);
        add_action('wp_ajax_affcd_bulk_export_data',         [$this, 'ajax_bulk_export_data']);
        add_action('wp_ajax_affcd_get_bulk_progress',        [$this, 'ajax_get_bulk_progress']);
        add_action('wp_ajax_affcd_cancel_bulk_operation',    [$this, 'ajax_cancel_bulk_operation']);

        // Background processing hooks
        add_action('affcd_process_bulk_operation',           [$this, 'process_bulk_operation'], 10, 2);
        add_action('affcd_cleanup_bulk_operations',          [$this, 'cleanup_expired_operations']);

        // Schedule cleanup
        if (!wp_next_scheduled('affcd_cleanup_bulk_operations')) {
            wp_schedule_event(time(), 'daily', 'affcd_cleanup_bulk_operations');
        }
    }

    /**
     * AJAX handler for bulk vanity code operations
     */
    public function ajax_bulk_vanity_codes() {
        check_ajax_referer('affcd_bulk_nonce', 'nonce');

        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'affiliate-cross-domain-full')]);
        }

        $operation       = sanitize_text_field($_POST['operation'] ?? '');
        $code_ids        = array_map('absint', $_POST['code_ids'] ?? []);
        $operation_data  = is_array($_POST['operation_data'] ?? null) ? $_POST['operation_data'] : [];

        if (empty($operation) || empty($code_ids)) {
            wp_send_json_error(['message' => __('Invalid operation or no items selected.', 'affiliate-cross-domain-full')]);
        }

        $batch_id = $this->start_bulk_operation('vanity_codes', $operation, [
            'code_ids'       => $code_ids,
            'operation_data' => $operation_data,
            'user_id'        => get_current_user_id()
        ]);

        if (count($code_ids) <= 50) {
            // Process small batches immediately
            $result = $this->process_vanity_codes_bulk($operation, $code_ids, $operation_data, $batch_id);
            wp_send_json_success($result);
        } else {
            // Schedule background processing for large batches
            wp_schedule_single_event(time(), 'affcd_process_bulk_operation', ['vanity_codes', $batch_id]);

            wp_send_json_success([
                'batch_id'   => $batch_id,
                'background' => true,
                'message'    => __('Bulk operation started in background. You can check progress in the operations panel.', 'affiliate-cross-domain-full')
            ]);
        }
    }

    /**
     * AJAX handler for bulk domain operations
     */
    public function ajax_bulk_domains() {
        check_ajax_referer('affcd_bulk_nonce', 'nonce');

        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'affiliate-cross-domain-full')]);
        }

        $operation      = sanitize_text_field($_POST['operation'] ?? '');
        $domain_ids     = array_map('absint', $_POST['domain_ids'] ?? []);
        $operation_data = is_array($_POST['operation_data'] ?? null) ? $_POST['operation_data'] : [];

        if (empty($operation) || empty($domain_ids)) {
            wp_send_json_error(['message' => __('Invalid operation or no items selected.', 'affiliate-cross-domain-full')]);
        }

        $result = $this->process_domains_bulk($operation, $domain_ids, $operation_data);
        wp_send_json_success($result);
    }

    /**
     * AJAX handler for bulk import
     */
    public function ajax_bulk_import_codes() {
        check_ajax_referer('affcd_bulk_nonce', 'nonce');

        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'affiliate-cross-domain-full')]);
        }

        if (empty($_FILES['import_file'])) {
            wp_send_json_error(['message' => __('No import file provided.', 'affiliate-cross-domain-full')]);
        }

        $file           = $_FILES['import_file'];
        $import_options = is_array($_POST['import_options'] ?? null) ? $_POST['import_options'] : [];

        // Validate file
        $validation = $this->validate_import_file($file);
        if (is_wp_error($validation)) {
            wp_send_json_error(['message' => $validation->get_error_message()]);
        }

        // Parse import file
        $import_data = $this->parse_import_file($file);
        if (is_wp_error($import_data)) {
            wp_send_json_error(['message' => $import_data->get_error_message()]);
        }

        $batch_id = $this->start_bulk_operation('import_codes', 'import', [
            'import_data'    => $import_data,
            'import_options' => $import_options,
            'user_id'        => get_current_user_id()
        ]);

        if (count($import_data) <= 100) {
            // Process small imports immediately
            $result = $this->process_import_codes($import_data, $import_options, $batch_id);
            wp_send_json_success($result);
        } else {
            // Schedule background processing for large imports
            wp_schedule_single_event(time(), 'affcd_process_bulk_operation', ['import_codes', $batch_id]);

            wp_send_json_success([
                'batch_id'       => $batch_id,
                'background'     => true,
                'total_records'  => count($import_data),
                'message'        => __('Import started in background. You can check progress in the operations panel.', 'affiliate-cross-domain-full')
            ]);
        }
    }

    /**
     * AJAX handler for bulk export
     */
    public function ajax_bulk_export_data() {
        check_ajax_referer('affcd_bulk_nonce', 'nonce');

        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'affiliate-cross-domain-full')]);
        }

        $export_type    = sanitize_text_field($_POST['export_type'] ?? '');
        $export_options = is_array($_POST['export_options'] ?? null) ? $_POST['export_options'] : [];

        if (empty($export_type)) {
            wp_send_json_error(['message' => __('Export type not specified.', 'affiliate-cross-domain-full')]);
        }

        $batch_id = $this->start_bulk_operation('export_data', 'export', [
            'export_type'    => $export_type,
            'export_options' => $export_options,
            'user_id'        => get_current_user_id()
        ]);

        // Always process exports in background due to potential size
        wp_schedule_single_event(time(), 'affcd_process_bulk_operation', ['export_data', $batch_id]);

        wp_send_json_success([
            'batch_id'   => $batch_id,
            'background' => true,
            'message'    => __('Export started. You will receive a download link when complete.', 'affiliate-cross-domain-full')
        ]);
    }

    /**
     * AJAX handler for getting bulk operation progress
     */
    public function ajax_get_bulk_progress() {
        check_ajax_referer('affcd_bulk_nonce', 'nonce');

        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'affiliate-cross-domain-full')]);
        }

        $batch_id = sanitize_text_field($_POST['batch_id'] ?? '');

        if (empty($batch_id)) {
            wp_send_json_error(['message' => __('Batch ID required.', 'affiliate-cross-domain-full')]);
        }

        $progress = $this->get_operation_progress($batch_id);
        wp_send_json_success($progress);
    }

    /**
     * AJAX handler for canceling bulk operations
     */
    public function ajax_cancel_bulk_operation() {
        check_ajax_referer('affcd_bulk_nonce', 'nonce');

        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'affiliate-cross-domain-full')]);
        }

        $batch_id = sanitize_text_field($_POST['batch_id'] ?? '');

        if (empty($batch_id)) {
            wp_send_json_error(['message' => __('Batch ID required.', 'affiliate-cross-domain-full')]);
        }

        $result = $this->cancel_bulk_operation($batch_id);

        if ($result) {
            wp_send_json_success(['message' => __('Operation cancelled successfully.', 'affiliate-cross-domain-full')]);
        } else {
            wp_send_json_error(['message' => __('Failed to cancel operation.', 'affiliate-cross-domain-full')]);
        }
    }

    /**
     * Process bulk vanity code operations
     *
     * @param string $operation Operation type
     * @param array  $code_ids  Code IDs
     * @param array  $operation_data Operation data
     * @param string $batch_id  Batch ID
     * @return array Operation result
     */
    private function process_vanity_codes_bulk($operation, $code_ids, $operation_data, $batch_id) {
        $results = [
            'operation'  => $operation,
            'batch_id'   => $batch_id,
            'total_items'=> count($code_ids),
            'processed'  => 0,
            'successful' => 0,
            'failed'     => 0,
            'errors'     => [],
            'warnings'   => []
        ];

        $this->update_operation_progress($batch_id, 0, 'processing', 'Starting bulk operation...');

        switch ($operation) {
            case 'activate':
                $results = $this->bulk_activate_codes($code_ids, $batch_id, $results);
                break;

            case 'deactivate':
                $results = $this->bulk_deactivate_codes($code_ids, $batch_id, $results);
                break;

            case 'delete':
                $results = $this->bulk_delete_codes($code_ids, $batch_id, $results);
                break;

            case 'update_expiry':
                $expiry_date = sanitize_text_field($operation_data['expiry_date'] ?? '');
                $results = $this->bulk_update_expiry($code_ids, $expiry_date, $batch_id, $results);
                break;

            case 'update_usage_limit':
                // No column exists in schema; store in metadata JSON
                $usage_limit = absint($operation_data['usage_limit'] ?? 0);
                $results = $this->bulk_update_usage_limit($code_ids, $usage_limit, $batch_id, $results);
                break;

            case 'reset_usage':
                $results = $this->bulk_reset_usage($code_ids, $batch_id, $results);
                break;

            case 'duplicate':
                $duplicate_options = is_array($operation_data['duplicate_options'] ?? null) ? $operation_data['duplicate_options'] : [];
                $results = $this->bulk_duplicate_codes($code_ids, $duplicate_options, $batch_id, $results);
                break;

            default:
                $results['errors'][] = __('Unknown bulk operation.', 'affiliate-cross-domain-full');
        }

        $results['completed_at'] = current_time('mysql');
        $this->update_operation_progress($batch_id, 100, 'completed', 'Bulk operation completed successfully');

        // Log bulk operation
        if (function_exists('affcd_log_analytics_event')) {
            affcd_log_analytics_event('bulk_operation_completed', [
                'entity_type' => 'bulk_operation',
                'operation'   => $operation,
                'total_items' => $results['total_items'],
                'successful'  => $results['successful'],
                'failed'      => $results['failed']
            ]);
        }

        return $results;
    }

    /**
     * Process bulk domain operations
     *
     * @param string $operation Operation type
     * @param array  $domain_ids Domain IDs
     * @param array  $operation_data Operation data
     * @return array Operation result
     */
    private function process_domains_bulk($operation, $domain_ids, $operation_data) {
        global $wpdb;
        $domains_table = $this->database_manager->get_table_name('authorized_domains');

        $results = [
            'operation'  => $operation,
            'total_items'=> count($domain_ids),
            'processed'  => 0,
            'successful' => 0,
            'failed'     => 0,
            'errors'     => []
        ];

        foreach ($domain_ids as $domain_id) {
            $results['processed']++;

            try {
                switch ($operation) {
                    case 'activate':
                        $updated = $wpdb->update(
                            $domains_table,
                            ['status' => 'active', 'updated_at' => current_time('mysql')],
                            ['id' => $domain_id],
                            ['%s','%s'],
                            ['%d']
                        );
                        break;

                    case 'deactivate':
                        $updated = $wpdb->update(
                            $domains_table,
                            ['status' => 'inactive', 'updated_at' => current_time('mysql')],
                            ['id' => $domain_id],
                            ['%s','%s'],
                            ['%d']
                        );
                        break;

                    case 'suspend':
                        $reason  = sanitize_text_field($operation_data['suspend_reason'] ?? '');
                        $updated = $wpdb->update(
                            $domains_table,
                            [
                                'status'           => 'suspended',
                                'suspended_at'     => current_time('mysql'),
                                'suspended_reason' => $reason,
                                'suspended_by'     => get_current_user_id(),
                                'updated_at'       => current_time('mysql')
                            ],
                            ['id' => $domain_id],
                            ['%s','%s','%s','%d','%s'],
                            ['%d']
                        );
                        break;

                    case 'delete':
                        $updated = $wpdb->delete(
                            $domains_table,
                            ['id' => $domain_id],
                            ['%d']
                        );
                        break;

                    case 'regenerate_api_key':
                        $new_api_key = function_exists('affcd_generate_api_key') ? affcd_generate_api_key() : wp_generate_password(40, false);
                        $updated = $wpdb->update(
                            $domains_table,
                            ['api_key' => $new_api_key, 'updated_at' => current_time('mysql')],
                            ['id' => $domain_id],
                            ['%s','%s'],
                            ['%d']
                        );
                        break;

                    default:
                        $results['errors'][] = sprintf(__('Unknown operation for domain ID %d', 'affiliate-cross-domain-full'), $domain_id);
                        continue 2;
                }

                if ($updated !== false) {
                    $results['successful']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = sprintf(__('Failed to %s domain ID %d', 'affiliate-cross-domain-full'), $operation, $domain_id);
                }

            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = sprintf(__('Error processing domain ID %d: %s', 'affiliate-cross-domain-full'), $domain_id, $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Process import codes operation
     */
    private function process_import_codes($import_data, $import_options, $batch_id) {
        $results = [
            'operation'   => 'import',
            'batch_id'    => $batch_id,
            'total_items' => count($import_data),
            'processed'   => 0,
            'successful'  => 0,
            'failed'      => 0,
            'errors'      => [],
            'warnings'    => []
        ];

        $this->update_operation_progress($batch_id, 0, 'processing', 'Starting import...');

        $update_existing     = !empty($import_options['update_existing']);
        $validate_affiliates = !empty($import_options['validate_affiliates']);
        $default_status      = $import_options['default_status'] ?? 'active';

        foreach ($import_data as $index => $code_data) {
            $results['processed']++;
            $progress = round(($results['processed'] / $results['total_items']) * 100);

            $this->update_operation_progress(
                $batch_id,
                $progress,
                'processing',
                sprintf(__('Processing row %d of %d', 'affiliate-cross-domain-full'), $results['processed'], $results['total_items'])
            );

            try {
                // Validate required fields
                if (empty($code_data['code']) || empty($code_data['affiliate_id'])) {
                    $results['failed']++;
                    $results['errors'][] = sprintf(__('Row %d: Missing required fields (code, affiliate_id)', 'affiliate-cross-domain-full'), $index + 1);
                    continue;
                }

                // Validate affiliate if enabled
                if ($validate_affiliates && function_exists('affcd_get_affiliate') && !affcd_get_affiliate($code_data['affiliate_id'])) {
                    $results['failed']++;
                    $results['errors'][] = sprintf(__('Row %d: Invalid affiliate ID %d', 'affiliate-cross-domain-full'), $index + 1, $code_data['affiliate_id']);
                    continue;
                }

                // Check if code exists
                $existing_code = $this->vanity_code_manager->get_vanity_code_by_code($code_data['code']);

                if ($existing_code) {
                    if ($update_existing) {
                        $update_result = $this->vanity_code_manager->update_vanity_code($existing_code->id, $code_data);
                        if (is_wp_error($update_result)) {
                            $results['failed']++;
                            $results['errors'][] = sprintf(__('Row %d: Update failed - %s', 'affiliate-cross-domain-full'), $index + 1, $update_result->get_error_message());
                        } else {
                            $results['successful']++;
                        }
                    } else {
                        $results['warnings'][] = sprintf(__('Row %d: Code "%s" already exists, skipped', 'affiliate-cross-domain-full'), $index + 1, $code_data['code']);
                    }
                } else {
                    // Set default status if not provided
                    if (empty($code_data['status'])) {
                        $code_data['status'] = $default_status;
                    }

                    $create_result = $this->vanity_code_manager->create_vanity_code($code_data);
                    if (is_wp_error($create_result)) {
                        $results['failed']++;
                        $results['errors'][] = sprintf(__('Row %d: Creation failed - %s', 'affiliate-cross-domain-full'), $index + 1, $create_result->get_error_message());
                    } else {
                        $results['successful']++;
                    }
                }

            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = sprintf(__('Row %d: Exception - %s', 'affiliate-cross-domain-full'), $index + 1, $e->getMessage());
            }
        }

        $results['completed_at'] = current_time('mysql');
        $this->update_operation_progress($batch_id, 100, 'completed', 'Import completed successfully');

        return $results;
    }

    /**
     * Bulk activate vanity codes
     */
    private function bulk_activate_codes($code_ids, $batch_id, $results) {
        global $wpdb;
        $table_name = $this->database_manager->get_table_name('vanity_codes');

        foreach ($code_ids as $code_id) {
            $results['processed']++;
            $progress = round(($results['processed'] / $results['total_items']) * 100);

            $this->update_operation_progress($batch_id, $progress, 'processing',
                sprintf(__('Activating code %d of %d', 'affiliate-cross-domain-full'), $results['processed'], $results['total_items'])
            );

            $updated = $wpdb->update(
                $table_name,
                ['status' => 'active', 'updated_at' => current_time('mysql')],
                ['id' => $code_id],
                ['%s','%s'],
                ['%d']
            );

            if ($updated !== false) {
                $results['successful']++;
            } else {
                $results['failed']++;
                $results['errors'][] = sprintf(__('Failed to activate code ID %d', 'affiliate-cross-domain-full'), $code_id);
            }
        }

        return $results;
    }

    /**
     * Bulk deactivate vanity codes
     */
    private function bulk_deactivate_codes($code_ids, $batch_id, $results) {
        global $wpdb;
        $table_name = $this->database_manager->get_table_name('vanity_codes');

        foreach ($code_ids as $code_id) {
            $results['processed']++;
            $progress = round(($results['processed'] / $results['total_items']) * 100);

            $this->update_operation_progress($batch_id, $progress, 'processing',
                sprintf(__('Deactivating code %d of %d', 'affiliate-cross-domain-full'), $results['processed'], $results['total_items'])
            );

            $updated = $wpdb->update(
                $table_name,
                ['status' => 'inactive', 'updated_at' => current_time('mysql')],
                ['id' => $code_id],
                ['%s','%s'],
                ['%d']
            );

            if ($updated !== false) {
                $results['successful']++;
            } else {
                $results['failed']++;
                $results['errors'][] = sprintf(__('Failed to deactivate code ID %d', 'affiliate-cross-domain-full'), $code_id);
            }
        }

        return $results;
    }

    /**
     * Bulk delete vanity codes
     */
    private function bulk_delete_codes($code_ids, $batch_id, $results) {
        foreach ($code_ids as $code_id) {
            $results['processed']++;
            $progress = round(($results['processed'] / $results['total_items']) * 100);

            $this->update_operation_progress($batch_id, $progress, 'processing',
                sprintf(__('Deleting code %d of %d', 'affiliate-cross-domain-full'), $results['processed'], $results['total_items'])
            );

            $delete_result = $this->vanity_code_manager->delete_vanity_code($code_id);

            if (is_wp_error($delete_result)) {
                $results['failed']++;
                $results['errors'][] = sprintf(__('Failed to delete code ID %d: %s', 'affiliate-cross-domain-full'), $code_id, $delete_result->get_error_message());
            } else {
                $results['successful']++;
            }
        }

        return $results;
    }

    /**
     * Bulk update expiry dates
     */
    private function bulk_update_expiry($code_ids, $expiry_date, $batch_id, $results) {
        global $wpdb;
        $table_name = $this->database_manager->get_table_name('vanity_codes');

        // Validate expiry date
        if (!empty($expiry_date) && strtotime($expiry_date) === false) {
            $results['errors'][] = __('Invalid expiry date format.', 'affiliate-cross-domain-full');
            return $results;
        }

        foreach ($code_ids as $code_id) {
            $results['processed']++;
            $progress = round(($results['processed'] / $results['total_items']) * 100);

            $this->update_operation_progress($batch_id, $progress, 'processing',
                sprintf(__('Updating expiry %d of %d', 'affiliate-cross-domain-full'), $results['processed'], $results['total_items'])
            );

            $updated = $wpdb->update(
                $table_name,
                [
                    'expires_at' => !empty($expiry_date) ? $expiry_date : null,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $code_id],
                ['%s','%s'],
                ['%d']
            );

            if ($updated !== false) {
                $results['successful']++;
            } else {
                $results['failed']++;
                $results['errors'][] = sprintf(__('Failed to update expiry for code ID %d', 'affiliate-cross-domain-full'), $code_id);
            }
        }

        return $results;
    }

    /**
     * Bulk update usage limits
     * (No dedicated column in schema; store in metadata JSON as {"usage_limit": <int>}).
     */
    private function bulk_update_usage_limit($code_ids, $usage_limit, $batch_id, $results) {
        global $wpdb;
        $table_name = $this->database_manager->get_table_name('vanity_codes');

        foreach ($code_ids as $code_id) {
            $results['processed']++;
            $progress = round(($results['processed'] / $results['total_items']) * 100);

            $this->update_operation_progress($batch_id, $progress, 'processing',
                sprintf(__('Updating usage limit %d of %d', 'affiliate-cross-domain-full'), $results['processed'], $results['total_items'])
            );

            // Load current metadata
            $meta = $wpdb->get_var($wpdb->prepare("SELECT metadata FROM {$table_name} WHERE id = %d", $code_id));
            $meta_arr = [];
            if (!empty($meta)) {
                $decoded = json_decode($meta, true);
                if (is_array($decoded)) {
                    $meta_arr = $decoded;
                }
            }
            $meta_arr['usage_limit'] = (int) $usage_limit;

            $updated = $wpdb->update(
                $table_name,
                [
                    'metadata'   => wp_json_encode($meta_arr),
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $code_id],
                ['%s','%s'],
                ['%d']
            );

            if ($updated !== false) {
                $results['successful']++;
            } else {
                $results['failed']++;
                $results['errors'][] = sprintf(__('Failed to update usage limit for code ID %d', 'affiliate-cross-domain-full'), $code_id);
            }
        }

        return $results;
    }

    /**
     * Bulk reset usage counts (align with schema)
     */
    private function bulk_reset_usage($code_ids, $batch_id, $results) {
        global $wpdb;
        $table_name = $this->database_manager->get_table_name('vanity_codes');

        foreach ($code_ids as $code_id) {
            $results['processed']++;
            $progress = round(($results['processed'] / $results['total_items']) * 100);

            $this->update_operation_progress($batch_id, $progress, 'processing',
                sprintf(__('Resetting usage %d of %d', 'affiliate-cross-domain-full'), $results['processed'], $results['total_items'])
            );

            $updated = $wpdb->update(
                $table_name,
                [
                    'usage_count'       => 0,
                    'conversion_count'  => 0,
                    'revenue_generated' => 0.0000,
                    'updated_at'        => current_time('mysql')
                ],
                ['id' => $code_id],
                ['%d','%d','%f','%s'],
                ['%d']
            );

            if ($updated !== false) {
                $results['successful']++;
            } else {
                $results['failed']++;
                $results['errors'][] = sprintf(__('Failed to reset usage for code ID %d', 'affiliate-cross-domain-full'), $code_id);
            }
        }

        return $results;
    }

    /**
     * Bulk duplicate vanity codes
     */
    private function bulk_duplicate_codes($code_ids, $duplicate_options, $batch_id, $results) {
        $code_suffix      = sanitize_text_field($duplicate_options['suffix'] ?? '_copy');
        $reset_usage      = !empty($duplicate_options['reset_usage']);
        $new_affiliate_id = absint($duplicate_options['new_affiliate_id'] ?? 0);

        foreach ($code_ids as $code_id) {
            $results['processed']++;
            $progress = round(($results['processed'] / $results['total_items']) * 100);

            $this->update_operation_progress($batch_id, $progress, 'processing',
                sprintf(__('Duplicating code %d of %d', 'affiliate-cross-domain-full'), $results['processed'], $results['total_items'])
            );

            // Get original code data
            $original_code = $this->vanity_code_manager->get_vanity_code_by_id($code_id);
            if (!$original_code) {
                $results['failed']++;
                $results['errors'][] = sprintf(__('Original code ID %d not found', 'affiliate-cross-domain-full'), $code_id);
                continue;
            }

            // Prepare duplicate data (align with schema fields)
            $duplicate_data = [
                'code'               => $original_code->code . $code_suffix,
                'affiliate_id'       => $new_affiliate_id ?: $original_code->affiliate_id,
                'description'        => trim(($original_code->description ?? '') . ' (Copy)'),
                'status'             => $original_code->status,
                'starts_at'          => $original_code->starts_at,
                'expires_at'         => $original_code->expires_at,
                'commission_rate'    => $original_code->commission_rate,
                'commission_type'    => $original_code->commission_type,
                'target_url'         => $original_code->target_url,
                'metadata'           => $original_code->metadata,
                'notes'              => $original_code->notes,
            ];

            if ($reset_usage) {
                // usage-related fields will start at 0 by default on creation
            }

            // Create duplicate
            $create_result = $this->vanity_code_manager->create_vanity_code($duplicate_data);

            if (is_wp_error($create_result)) {
                $results['failed']++;
                $results['errors'][] = sprintf(__('Failed to duplicate code ID %d: %s', 'affiliate-cross-domain-full'), $code_id, $create_result->get_error_message());
            } else {
                $results['successful']++;
            }
        }

        return $results;
    }

    /**
     * Start bulk operation and return batch ID
     */
    private function start_bulk_operation($operation_type, $operation, $operation_data) {
        $batch_id = 'bulk_' . time() . '_' . wp_generate_password(8, false);

        $progress_data = [
            'operation_type' => $operation_type,
            'operation'      => $operation,
            'status'         => 'started',
            'progress'       => 0,
            'message'        => 'Operation initialized...',
            'started_at'     => current_time('mysql'),
            'user_id'        => get_current_user_id(),
            'operation_data' => $operation_data
        ];

        update_option($this->progress_key_prefix . $batch_id, $progress_data);

        return $batch_id;
    }

    /**
     * Update operation progress
     */
    private function update_operation_progress($batch_id, $progress, $status, $message) {
        $current_data = get_option($this->progress_key_prefix . $batch_id, []);

        $current_data['progress']   = $progress;
        $current_data['status']     = $status;
        $current_data['message']    = $message;
        $current_data['updated_at'] = current_time('mysql');

        update_option($this->progress_key_prefix . $batch_id, $current_data);
    }

    /**
     * Get operation progress
     */
    private function get_operation_progress($batch_id) {
        return get_option($this->progress_key_prefix . $batch_id, false);
    }

    /**
     * Cancel bulk operation
     */
    private function cancel_bulk_operation($batch_id) {
        $progress_data = get_option($this->progress_key_prefix . $batch_id, false);

        if (!$progress_data) {
            return false;
        }

        $progress_data['status']       = 'cancelled';
        $progress_data['message']      = 'Operation cancelled by user';
        $progress_data['cancelled_at'] = current_time('mysql');

        update_option($this->progress_key_prefix . $batch_id, $progress_data);

        return true;
    }

    /**
     * Validate import file
     */
    private function validate_import_file($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', __('File upload error.', 'affiliate-cross-domain-full'));
        }

        // Check file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            return new WP_Error('file_too_large', __('File too large. Maximum size is 10MB.', 'affiliate-cross-domain-full'));
        }

        // Check file type
        $allowed_types = ['text/csv', 'application/csv', 'text/plain'];
        $file_type     = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : '';

        if (!in_array($file_type, $allowed_types, true)) {
            // Also check file extension as fallback
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($file_ext !== 'csv') {
                return new WP_Error('invalid_file_type', __('Invalid file type. Only CSV files are allowed.', 'affiliate-cross-domain-full'));
            }
        }

        return true;
    }

    /**
     * Parse import file
     */
    private function parse_import_file($file) {
        if (!function_exists('fgetcsv')) {
            return new WP_Error('function_missing', __('CSV parsing function not available.', 'affiliate-cross-domain-full'));
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            return new WP_Error('file_open_error', __('Could not open import file.', 'affiliate-cross-domain-full'));
        }

        $import_data = [];
        $headers     = [];
        $row_number  = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $row_number++;

            if ($row_number === 1) {
                // First row contains headers
                $headers = array_map('trim', $row);
                continue;
            }

            if (empty(array_filter($row))) {
                // Skip empty rows
                continue;
            }

            // Map row data to headers
            $row_data = [];
            foreach ($headers as $index => $header) {
                $row_data[strtolower(str_replace(' ', '_', $header))] = isset($row[$index]) ? trim($row[$index]) : '';
            }

            $import_data[] = $row_data;
        }

        fclose($handle);

        if (empty($import_data)) {
            return new WP_Error('empty_file', __('Import file contains no data.', 'affiliate-cross-domain-full'));
        }

        return $import_data;
    }

    /**
     * Background processing handler
     */
    public function process_bulk_operation($operation_type, $batch_id) {
        $progress_data = get_option($this->progress_key_prefix . $batch_id, false);

        if (!$progress_data || $progress_data['status'] === 'cancelled') {
            return;
        }

        $this->update_operation_progress($batch_id, 0, 'processing', 'Starting background processing...');

        try {
            switch ($operation_type) {
                case 'vanity_codes':
                    $this->process_background_vanity_codes($batch_id, $progress_data);
                    break;

                case 'import_codes':
                    $this->process_background_import($batch_id, $progress_data);
                    break;

                case 'export_data':
                    $this->process_background_export($batch_id, $progress_data);
                    break;

                default:
                    $this->update_operation_progress($batch_id, 0, 'failed', 'Unknown operation type');
            }
        } catch (Exception $e) {
            $this->update_operation_progress($batch_id, 0, 'failed', 'Error: ' . $e->getMessage());
            error_log('AFFCD Bulk Operation Error: ' . $e->getMessage());
        }
    }

    /**
     * Process background vanity codes operation
     */
    private function process_background_vanity_codes($batch_id, $progress_data) {
        $operation_data = $progress_data['operation_data'];
        $code_ids       = $operation_data['code_ids'];
        $operation      = $progress_data['operation'];

        // Process in batches to avoid memory issues
        $batches       = array_chunk($code_ids, $this->max_batch_size);
        $total_batches = count($batches);

        $overall_results = [
            'operation'   => $operation,
            'batch_id'    => $batch_id,
            'total_items' => count($code_ids),
            'processed'   => 0,
            'successful'  => 0,
            'failed'      => 0,
            'errors'      => []
        ];

        foreach ($batches as $batch_index => $batch_code_ids) {
            // Check if operation was cancelled
            $current_progress = get_option($this->progress_key_prefix . $batch_id);
            if ($current_progress['status'] === 'cancelled') {
                return;
            }

            $batch_progress = round((($batch_index + 1) / $total_batches) * 100);
            $this->update_operation_progress(
                $batch_id,
                $batch_progress,
                'processing',
                sprintf(__('Processing batch %d of %d', 'affiliate-cross-domain-full'), $batch_index + 1, $total_batches)
            );

            $batch_results = $this->process_vanity_codes_bulk(
                $operation,
                $batch_code_ids,
                $operation_data['operation_data'] ?? [],
                $batch_id . '_batch_' . $batch_index
            );

            // Merge batch results with overall results
            $overall_results['processed']  += $batch_results['processed'];
            $overall_results['successful'] += $batch_results['successful'];
            $overall_results['failed']     += $batch_results['failed'];
            $overall_results['errors']      = array_merge($overall_results['errors'], $batch_results['errors']);
        }

        $overall_results['completed_at'] = current_time('mysql');

        // Store final results
        $progress_data['results']      = $overall_results;
        $progress_data['status']       = 'completed';
        $progress_data['progress']     = 100;
        $progress_data['message']      = 'Background operation completed successfully';
        $progress_data['completed_at'] = current_time('mysql');

        update_option($this->progress_key_prefix . $batch_id, $progress_data);

        // Send notification email if requested
        $this->send_completion_notification($batch_id, $progress_data);
    }

    /**
     * Process background import
     */
    private function process_background_import($batch_id, $progress_data) {
        $operation_data = $progress_data['operation_data'];
        $import_data    = $operation_data['import_data'];
        $import_options = $operation_data['import_options'];

        $result = $this->process_import_codes($import_data, $import_options, $batch_id);

        // Store final results
        $progress_data['results']      = $result;
        $progress_data['status']       = 'completed';
        $progress_data['progress']     = 100;
        $progress_data['message']      = 'Import completed successfully';
        $progress_data['completed_at'] = current_time('mysql');

        update_option($this->progress_key_prefix . $batch_id, $progress_data);

        // Send notification
        $this->send_completion_notification($batch_id, $progress_data);
    }

    /**
     * Process background export
     */
    private function process_background_export($batch_id, $progress_data) {
        $operation_data = $progress_data['operation_data'];
        $export_type    = $operation_data['export_type'];
        $export_options = $operation_data['export_options'];

        $this->update_operation_progress($batch_id, 10, 'processing', 'Preparing export data...');

        // Generate export data based on type
        switch ($export_type) {
            case 'vanity_codes':
                $export_data = $this->vanity_code_manager->export_codes($export_options);
                break;

            case 'analytics':
                $export_data = function_exists('affcd_export_analytics_data') ? affcd_export_analytics_data($export_options) : [];
                break;

            case 'domains':
                $export_data = $this->export_domains_data($export_options);
                break;

            default:
                $this->update_operation_progress($batch_id, 0, 'failed', 'Unknown export type');
                return;
        }

        $this->update_operation_progress($batch_id, 50, 'processing', 'Generating export file...');

        // Create export file
        $filename = $this->create_export_file($export_type, $export_data, $export_options);

        if (!$filename) {
            $this->update_operation_progress($batch_id, 0, 'failed', 'Failed to create export file');
            return;
        }

        $this->update_operation_progress($batch_id, 90, 'processing', 'Finalizing export...');

        // Store export result
        $export_path = $this->get_export_file_path($filename);
        $export_result = [
            'export_type'       => $export_type,
            'filename'          => $filename,
            'file_url'          => $this->get_export_file_url($filename),
            'file_size'         => file_exists($export_path) ? filesize($export_path) : 0,
            'records_exported'  => is_array($export_data) ? count($export_data) : 0,
            'created_at'        => current_time('mysql')
        ];

        $progress_data['results']      = $export_result;
        $progress_data['status']       = 'completed';
        $progress_data['progress']     = 100;
        $progress_data['message']      = 'Export completed successfully';
        $progress_data['completed_at'] = current_time('mysql');

        update_option($this->progress_key_prefix . $batch_id, $progress_data);

        // Send notification with download link
        $this->send_completion_notification($batch_id, $progress_data);
    }

    /**
     * Create export file
     */
    private function create_export_file($export_type, $export_data, $export_options) {
        $upload_dir = wp_upload_dir();
        $export_dir = trailingslashit($upload_dir['basedir']) . 'affcd-exports/';

        // Create export directory if it doesn't exist
        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);

            // Add .htaccess for security (Apache)
            @file_put_contents($export_dir . '.htaccess', "Order deny,allow\nDeny from all\n");
        }

        $format   = $export_options['format'] ?? 'csv';
        $filename = $export_type . '_' . date('Y-m-d_H-i-s') . '.' . $format;
        $filepath = $export_dir . $filename;

        try {
            switch ($format) {
                case 'json':
                    $content = is_string($export_data) ? $export_data : wp_json_encode($export_data, JSON_PRETTY_PRINT);
                    break;

                case 'csv':
                default:
                    $content = is_string($export_data) ? $export_data : $this->convert_to_csv($export_data);
                    break;
            }

            if (file_put_contents($filepath, $content) !== false) {
                return $filename;
            }

        } catch (Exception $e) {
            error_log('Export file creation error: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Convert array data to CSV
     */
    private function convert_to_csv($data) {
        if (empty($data) || !is_array($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Add headers
        if (isset($data[0]) && is_array($data[0])) {
            fputcsv($output, array_keys($data[0]));
        }

        // Add data rows
        foreach ($data as $row) {
            if (is_array($row)) {
                // Convert complex values to strings
                $csv_row = [];
                foreach ($row as $value) {
                    if (is_array($value) || is_object($value)) {
                        $csv_row[] = wp_json_encode($value);
                    } else {
                        $csv_row[] = $value;
                    }
                }
                fputcsv($output, $csv_row);
            }
        }

        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);

        return $csv_content;
    }

    /**
     * Export domains data
     */
    private function export_domains_data($export_options) {
        global $wpdb;
        $domains_table = $this->database_manager->get_table_name('authorized_domains');

        $where_clauses = [];
        $params        = [];

        if (!empty($export_options['status'])) {
            $where_clauses[] = 'status = %s';
            $params[]        = $export_options['status'];
        }

        if (!empty($export_options['verification_status'])) {
            $where_clauses[] = 'verification_status = %s';
            $params[]        = $export_options['verification_status'];
        }

        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

        if (!empty($params)) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$domains_table} {$where_sql} ORDER BY created_at DESC",
                $params
            );
        } else {
            $query = "SELECT * FROM {$domains_table} {$where_sql} ORDER BY created_at DESC";
        }

        $domains = $wpdb->get_results($query, ARRAY_A);

        return $domains ?: [];
    }

    /**
     * Get export file URL
     */
    private function get_export_file_url($filename) {
        $upload_dir = wp_upload_dir();
        return trailingslashit($upload_dir['baseurl']) . 'affcd-exports/' . $filename;
    }

    /**
     * Get export file path
     */
    private function get_export_file_path($filename) {
        $upload_dir = wp_upload_dir();
        return trailingslashit($upload_dir['basedir']) . 'affcd-exports/' . $filename;
    }

    /**
     * Send completion notification
     */
    private function send_completion_notification($batch_id, $progress_data) {
        $user = get_user_by('id', $progress_data['user_id']);

        if (!$user || !$user->user_email) {
            return;
        }

        $operation_name = ucwords(str_replace('_', ' ', $progress_data['operation']));
        $subject = sprintf(__('[%s] Bulk Operation Completed: %s', 'affiliate-cross-domain-full'), get_bloginfo('name'), $operation_name);

        $message = sprintf(
            __("Hello %s,\n\nYour bulk operation has been completed.\n\nOperation: %s\nStatus: %s\nStarted: %s\nCompleted: %s\n\n", 'affiliate-cross-domain-full'),
            $user->display_name,
            $operation_name,
            ucfirst($progress_data['status']),
            $progress_data['started_at'],
            $progress_data['completed_at']
        );

        if (isset($progress_data['results'])) {
            $results = $progress_data['results'];

            if (isset($results['total_items'])) {
                $message .= sprintf(
                    __("Results:\n- Total items: %d\n- Successful: %d\n- Failed: %d\n\n", 'affiliate-cross-domain-full'),
                    $results['total_items'],
                    $results['successful'],
                    $results['failed']
                );
            }

            if (isset($results['file_url'])) {
                $message .= sprintf(__("Download your export: %s\n\n", 'affiliate-cross-domain-full'), $results['file_url']);
            }
        }

        $message .= sprintf(__("View details: %s\n\nBest regards,\n%s", 'affiliate-cross-domain-full'),
            admin_url('admin.php?page=affcd-bulk-operations&batch_id=' . rawurlencode($batch_id)),
            get_bloginfo('name')
        );

        wp_mail($user->user_email, $subject, $message);
    }

    /**
     * Clean up expired bulk operations
     */
    public function cleanup_expired_operations() {
        global $wpdb;

        // Get all bulk operation options
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options}
             WHERE option_name LIKE %s",
            $this->progress_key_prefix . '%'
        ));

        $deleted_count = 0;
        $cutoff_date   = strtotime('-7 days');

        foreach ($results as $result) {
            $option_name   = $result->option_name;
            $progress_data = get_option($option_name, false);

            if (!$progress_data || !isset($progress_data['started_at'])) {
                continue;
            }

            $started_timestamp = strtotime($progress_data['started_at']);

            // Delete operations older than 7 days
            if ($started_timestamp < $cutoff_date) {
                delete_option($option_name);
                $deleted_count++;
            }
        }

        // Clean up export files older than 30 days
        $upload_dir = wp_upload_dir();
        $export_dir = trailingslashit($upload_dir['basedir']) . 'affcd-exports/';

        if (is_dir($export_dir)) {
            $files           = glob($export_dir . '*');
            $file_cutoff_date = strtotime('-30 days');

            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $file_cutoff_date) {
                    @unlink($file);
                }
            }
        }

        if ($deleted_count > 0) {
            error_log("AFFCD: Cleaned up {$deleted_count} expired bulk operations");
        }
    }

    /**
     * Get active bulk operations for current user
     */
    public function get_user_bulk_operations() {
        global $wpdb;

        $user_id    = get_current_user_id();
        $operations = [];

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options}
             WHERE option_name LIKE %s",
            $this->progress_key_prefix . '%'
        ));

        foreach ($results as $result) {
            $progress_data = maybe_unserialize($result->option_value);

            if ($progress_data && isset($progress_data['user_id']) && (int) $progress_data['user_id'] === (int) $user_id) {
                $batch_id = str_replace($this->progress_key_prefix, '', $result->option_name);
                $progress_data['batch_id'] = $batch_id;
                $operations[] = $progress_data;
            }
        }

        // Sort by started date, newest first
        usort($operations, function($a, $b) {
            return strtotime($b['started_at']) - strtotime($a['started_at']);
        });

        return $operations;
    }
}