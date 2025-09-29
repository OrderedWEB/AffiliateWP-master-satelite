<?php  
AfiliateWP_Cross_Domain_Database_Installer {
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Health monitoring table
        $health_table = $wpdb->prefix . 'affiliate_health_log';
        $health_sql = "CREATE TABLE $health_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            action varchar(100) NOT NULL,
            status varchar(50) NOT NULL,
            details longtext,
            meta_key varchar(255),
            meta_value longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY action (action),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Marketing usage table
        $marketing_table = $wpdb->prefix . 'affiliate_marketing_usage';
        $marketing_sql = "CREATE TABLE $marketing_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            affiliate_id bigint(20) NOT NULL,
            material_type varchar(100) NOT NULL,
            platform varchar(100) NOT NULL,
            metrics longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY affiliate_id (affiliate_id),
            KEY material_type (material_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Commission calculations table
        $commission_table = $wpdb->prefix . 'affiliate_commission_calculations';
        $commission_sql = "CREATE TABLE $commission_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            affiliate_id bigint(20) NOT NULL,
            calculation_type varchar(100) NOT NULL,
            base_amount decimal(10,2) NOT NULL,
            calculated_amount decimal(10,2) NOT NULL,
            tier_level int(11) DEFAULT 1,
            calculation_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY affiliate_id (affiliate_id),
            KEY calculation_type (calculation_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Rate limiting table
        $rate_limit_table = $wpdb->prefix . 'affiliate_rate_limits';
        $rate_limit_sql = "CREATE TABLE $rate_limit_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            identifier varchar(255) NOT NULL,
            endpoint varchar(255) NOT NULL,
            request_count int(11) NOT NULL DEFAULT 0,
            window_start datetime NOT NULL,
            blocked_until datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY identifier_endpoint (identifier, endpoint),
            KEY window_start (window_start),
            KEY blocked_until (blocked_until)
        ) $charset_collate;";
        
        // Backflow data table
        $backflow_table = $wpdb->prefix . 'affiliate_backflow_data';
        $backflow_sql = "CREATE TABLE $backflow_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            source_domain varchar(255) NOT NULL,
            data_type varchar(100) NOT NULL,
            payload longtext NOT NULL,
            status varchar(50) DEFAULT 'pending',
            processed_at datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source_domain (source_domain),
            KEY data_type (data_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($health_sql);
        dbDelta($marketing_sql);
        dbDelta($commission_sql);
        dbDelta($rate_limit_sql);
        dbDelta($backflow_sql);
    }
}