<?php
/**
 * HPOS Compatibility Fix Script for Amazon Affiliate Product Importer
 * 
 * Run this script once to force WordPress to recognize the HPOS compatibility.
 * Place this file in your WordPress root directory and visit it in your browser once.
 * Then delete the file for security.
 */

// Basic WordPress bootstrap (adjust path if needed)
require_once('wp-config.php');
require_once('wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied. You need administrator privileges.');
}

echo "<h1>Amazon Affiliate Product Importer - HPOS Compatibility Fix</h1>";

// Force plugin reactivation to refresh compatibility status
$plugin_file = 'amazon-affiliate-importer/amazon-affiliate-importer.php';

if (is_plugin_active($plugin_file)) {
    echo "<p>Deactivating plugin...</p>";
    deactivate_plugins($plugin_file);
    
    echo "<p>Reactivating plugin...</p>";
    activate_plugin($plugin_file);
    
    echo "<p style='color: green;'>✓ Plugin reactivated successfully!</p>";
    
    // Clear any object cache
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
        echo "<p>✓ Cache cleared.</p>";
    }
    
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ul>";
    echo "<li>Go to WooCommerce → Settings → Advanced → Features</li>";
    echo "<li>Check if 'High-performance order storage' shows Amazon Affiliate Product Importer as compatible</li>";
    echo "<li>If still showing as incompatible, try disabling and re-enabling HPOS</li>";
    echo "</ul>";
    
    echo "<p style='background: #fff3cd; padding: 10px; border: 1px solid #ffeaa7;'>";
    echo "<strong>Important:</strong> Delete this fix script file for security reasons.";
    echo "</p>";
    
} else {
    echo "<p style='color: red;'>Plugin is not active. Please activate it first.</p>";
}

echo "<p><a href='/wp-admin/plugins.php'>← Back to Plugins</a></p>";
?>
