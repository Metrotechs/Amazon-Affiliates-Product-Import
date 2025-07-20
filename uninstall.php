<?php
/**
 * Uninstall script for Amazon Affiliate Product Importer
 * 
 * This file is executed when the plugin is uninstalled (deleted) from WordPress.
 * It cleans up the database tables and options created by the plugin.
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete custom database table
global $wpdb;

$table_name = $wpdb->prefix . 'amazon_affiliate_products';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// Delete plugin options
delete_option('amazon_affiliate_importer_settings');

// Delete any transients
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_amazon_importer_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_amazon_importer_%'");

// Delete post meta for imported products
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_amazon_asin', '_amazon_url', '_imported_from_amazon')");

// Clear any cached data
wp_cache_flush();
