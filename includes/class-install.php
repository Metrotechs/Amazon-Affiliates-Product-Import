<?php
/**
 * Installation and activation hooks for Amazon Affiliate Product Importer
 */

if (!defined('ABSPATH')) {
    exit;
}

class AmazonAffiliateImporter_Install {
    
    /**
     * Run installation
     */
    public static function install() {
        self::create_tables();
        self::create_default_options();
        self::create_capabilities();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create custom database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'amazon_affiliate_products';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            amazon_url text NOT NULL,
            product_id bigint(20) NOT NULL,
            asin varchar(20) NOT NULL,
            affiliate_tag varchar(50) NOT NULL,
            imported_date datetime DEFAULT CURRENT_TIMESTAMP,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'active',
            notes text,
            PRIMARY KEY (id),
            UNIQUE KEY product_id (product_id),
            KEY asin (asin),
            KEY affiliate_tag (affiliate_tag),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Store database version
        add_option('amazon_affiliate_importer_db_version', '1.0');
    }
    
    /**
     * Create default options
     */
    private static function create_default_options() {
        $default_settings = array(
            'default_category' => '',
            'auto_publish' => 'draft',
            'image_import' => true,
            'price_sync' => false,
            'max_images' => 5,
            'timeout' => 30,
            'user_agent' => 'Mozilla/5.0 (compatible; WordPress Amazon Importer)',
            'rate_limit' => 2, // seconds between requests
            'enable_logging' => false
        );
        
        add_option('amazon_affiliate_importer_settings', $default_settings);
        add_option('amazon_affiliate_importer_version', AMAZON_AFFILIATE_IMPORTER_VERSION);
    }
    
    /**
     * Create user capabilities
     */
    private static function create_capabilities() {
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('manage_amazon_importer');
        }
        
        $role = get_role('shop_manager');
        if ($role) {
            $role->add_cap('manage_amazon_importer');
        }
    }
    
    /**
     * Run uninstallation
     */
    public static function uninstall() {
        // This is handled by uninstall.php
    }
    
    /**
     * Check if database needs updating
     */
    public static function check_version() {
        $current_version = get_option('amazon_affiliate_importer_version');
        
        if (version_compare($current_version, AMAZON_AFFILIATE_IMPORTER_VERSION, '<')) {
            self::update_database();
        }
    }
    
    /**
     * Update database structure
     */
    private static function update_database() {
        // Future database updates will go here
        update_option('amazon_affiliate_importer_version', AMAZON_AFFILIATE_IMPORTER_VERSION);
    }
}
