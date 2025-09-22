<?php
/**
 * Tracking Reports Admin Page
 *
 * Provides comprehensive tracking and conversion reporting for the
 * affiliate cross-domain system with advanced filtering and analytics.
 *
 * @package AffiliateWP_Cross_Domain_Plugin_Suite_Master
 * @version 1.0.0
 * @author Richard King, Starne Consulting
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Tracking_Reports {

    /**
     * Page slug
     *
     * @var string
     */
    private $page_slug = 'affcd-tracking-reports';

    /**
     * Analytics dashboard instance
     *
     * @var AFFCD_Analytics_Dashboard
     */
    private $analytics;

    /**
     * Constructor
     */
    public function __construct() {
        $this->analytics = new AFFCD_Analytics_Dashboard();
        
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_affcd_get_tracking_data', [$this, 'ajax_get_tracking_data']);
        add_action('wp_ajax_affcd_export_tracking_report', [$this, 'ajax_export_tracking_report']);
        add_action('wp_ajax_affcd_update_tracking_settings', [$this, 'ajax_update_tracking_settings']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'affiliate-wp',
            __('Tracking Reports', 'affiliatewp-cross-domain-plugin-suite'),
            __('Tracking Reports', 'affiliatewp-cross-domain-plugin-suite'),
            'manage_affiliates',
            $this->page_slug,
            [$this, 'render_reports_page']
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook_suffix) {
        if (strpos($hook_suffix, $this->page_slug) === false) {
            return;
        }

        // Chart.js for data visualization
        wp_enqueue_script(
            'chartjs',
            'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js',
            [],
            '3.9.1',
            true
        );

        // Date range picker
        wp_enqueue_script(
            'daterangepicker',
            'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-daterangepicker/3.0.5/daterangepicker.min.js',
            ['jquery', 'moment'],
            '3.0.5',
            true
        );

        wp_enqueue_script(
            'moment',
            'https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js',
            [],
            '2.29.4',
            true
        );

        // Custom tracking reports script
        wp_enqueue_script(
            'affcd-tracking-reports',
            AFFCD_PLUGIN_URL . 'admin/js/tracking-reports.js',
            ['jquery', 'chartjs', 'daterangepicker'],
            AFFCD_VERSION,
            true
        );

        wp_enqueue_style(
            'daterangepicker',