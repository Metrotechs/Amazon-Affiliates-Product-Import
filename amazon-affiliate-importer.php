<?php
/**
 * Plugin Name: Amazon Affiliate Product Importer
 * Plugin URI: https://example.com/amazon-affiliate-importer
 * Description: Import Amazon products to WooCommerce with affiliate links for Amazon Associates program.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: amazon-affiliate-importer
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AMAZON_AFFILIATE_IMPORTER_VERSION', '1.0.0');
define('AMAZON_AFFILIATE_IMPORTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AMAZON_AFFILIATE_IMPORTER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>Amazon Affiliate Product Importer</strong> requires WooCommerce to be installed and active.</p></div>';
    });
    return;
}

// Include the install class early for activation hook
require_once AMAZON_AFFILIATE_IMPORTER_PLUGIN_DIR . 'includes/class-install.php';

// Main plugin class
class AmazonAffiliateImporter {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load text domain
        load_plugin_textdomain('amazon-affiliate-importer', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Include required files
        $this->includes();
        
        // Initialize admin
        if (is_admin()) {
            new AmazonAffiliateImporter_Admin();
        }
        
        // Initialize product importer
        new AmazonAffiliateImporter_Product();
        
        // Initialize category manager
        new AmazonAffiliateImporter_Categories();
    }
    
    private function includes() {
        require_once AMAZON_AFFILIATE_IMPORTER_PLUGIN_DIR . 'includes/class-admin.php';
        require_once AMAZON_AFFILIATE_IMPORTER_PLUGIN_DIR . 'includes/class-product.php';
        require_once AMAZON_AFFILIATE_IMPORTER_PLUGIN_DIR . 'includes/class-scraper.php';
        require_once AMAZON_AFFILIATE_IMPORTER_PLUGIN_DIR . 'includes/class-categories.php';
    }
    
    public function activate() {
        AmazonAffiliateImporter_Install::install();
    }
    
    public function deactivate() {
        // Clean up if needed
    }
}

// Initialize the plugin
new AmazonAffiliateImporter();
