<?php
/**
 * Plugin Name: Amazon Affiliate Product Importer
 * Plugin URI: https://example.com/amazon-affiliate-importer
 * Description: Import Amazon products to WooCommerce with affiliate links for Amazon Associates program.
 * Version: 1.0.1
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: amazon-affiliate-importer
 * Requires at least: 5.0
 * Tested up to: 6.6
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.3
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AMAZON_AFFILIATE_IMPORTER_VERSION', '1.0.1');
define('AMAZON_AFFILIATE_IMPORTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AMAZON_AFFILIATE_IMPORTER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Check if WooCommerce is active and version compatible
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__('Amazon Affiliate Product Importer', 'amazon-affiliate-importer') . '</strong> ' . esc_html__('requires WooCommerce to be installed and active.', 'amazon-affiliate-importer') . ' <a href="' . admin_url('plugin-install.php?s=woocommerce&tab=search&type=term') . '" class="button-primary">' . esc_html__('Install WooCommerce', 'amazon-affiliate-importer') . '</a></p></div>';
    });
    return;
}

// Check WooCommerce version compatibility
add_action('admin_init', function() {
    if (defined('WC_VERSION') && version_compare(WC_VERSION, '6.0', '<')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__('Amazon Affiliate Product Importer', 'amazon-affiliate-importer') . '</strong> ' . sprintf(esc_html__('requires WooCommerce version 6.0 or higher for optimal performance. Current version: %s. Please update WooCommerce for the best experience.', 'amazon-affiliate-importer'), esc_html(WC_VERSION)) . '</p></div>';
        });
    }
    
    // Check for potential plugin conflicts
    $conflicting_plugins = array(
        'another-amazon-plugin/another-amazon-plugin.php',
        'old-amazon-importer/old-amazon-importer.php'
    );
    
    $active_conflicting = array_intersect($conflicting_plugins, apply_filters('active_plugins', get_option('active_plugins')));
    
    if (!empty($active_conflicting)) {
        add_action('admin_notices', function() use ($active_conflicting) {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__('Amazon Affiliate Product Importer', 'amazon-affiliate-importer') . '</strong> ' . esc_html__('may conflict with other Amazon import plugins. Please deactivate conflicting plugins for optimal performance.', 'amazon-affiliate-importer') . '</p></div>';
        });
    }
});

// Declare WooCommerce HPOS compatibility early
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

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
        require_once AMAZON_AFFILIATE_IMPORTER_PLUGIN_DIR . 'includes/class-compatibility.php';
        require_once AMAZON_AFFILIATE_IMPORTER_PLUGIN_DIR . 'includes/class-system-status.php';
        require_once AMAZON_AFFILIATE_IMPORTER_PLUGIN_DIR . 'includes/class-admin.php';
        require_once AMAZON_AFFILIATE_IMPORTER_PLUGIN_DIR . 'includes/class-product.php';
        require_once AMAZON_AFFILIATE_IMPORTER_PLUGIN_DIR . 'includes/class-scraper.php';
        require_once AMAZON_AFFILIATE_IMPORTER_PLUGIN_DIR . 'includes/class-categories.php';
        // Add ratings/display helper
        require_once AMAZON_AFFILIATE_IMPORTER_PLUGIN_DIR . 'includes/class-ratings.php';
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
