<?php
/**
 * Test script for Amazon variation functionality
 * 
 * This script tests the variation extraction and creation functionality
 * Run this in a WordPress environment where the plugin is active
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Test Amazon URLs with variations
$test_urls = array(
    'https://amazon.com/dp/B08N5WRWNW', // Example product with color/size variations
    'https://amazon.com/dp/B07ZPKN6YR', // Another example with variations
);

echo "<h1>Amazon Variation Import Test</h1>";

foreach ($test_urls as $url) {
    echo "<h2>Testing URL: " . esc_html($url) . "</h2>";
    
    // Initialize classes
    $scraper = new Amazon_Affiliate_Scraper();
    $product_importer = new Amazon_Affiliate_Product();
    
    // Test variation extraction
    echo "<h3>1. Testing Variation Extraction</h3>";
    $variations = $scraper->extract_variations($url);
    
    echo "<p>Found " . count($variations) . " variations:</p>";
    
    if (!empty($variations)) {
        echo "<ul>";
        foreach ($variations as $i => $variation) {
            echo "<li>";
            echo "<strong>Variation " . ($i + 1) . ":</strong><br>";
            echo "ASIN: " . esc_html($variation['asin']) . "<br>";
            
            if (!empty($variation['title_suffix'])) {
                echo "Title: " . esc_html($variation['title_suffix']) . "<br>";
            }
            
            if (!empty($variation['url'])) {
                echo "URL: " . esc_html($variation['url']) . "<br>";
            }
            
            if (!empty($variation['price'])) {
                echo "Price: $" . esc_html($variation['price']) . "<br>";
            }
            
            if (!empty($variation['attributes'])) {
                echo "Attributes: ";
                foreach ($variation['attributes'] as $attr_name => $attr_value) {
                    echo esc_html($attr_name) . ": " . esc_html($attr_value) . " ";
                }
                echo "<br>";
            }
            
            if (!empty($variation['image'])) {
                echo "Image: <img src='" . esc_url($variation['image']) . "' style='max-width: 50px; max-height: 50px;'><br>";
            }
            
            echo "</li>";
        }
        echo "</ul>";
        
        // Test product creation with variations
        echo "<h3>2. Testing Variable Product Creation</h3>";
        echo "<p><em>Note: Actual product creation is commented out to prevent duplicate imports during testing.</em></p>";
        
        /*
        // Uncomment to actually test product creation
        $result = $product_importer->import_product($url);
        
        if (is_wp_error($result)) {
            echo "<p style='color: red;'>Error: " . esc_html($result->get_error_message()) . "</p>";
        } else {
            echo "<p style='color: green;'>Success! Product ID: " . esc_html($result) . "</p>";
            echo "<p><a href='" . get_permalink($result) . "' target='_blank'>View Product</a></p>";
        }
        */
        
    } else {
        echo "<p><em>No variations found. This product may not have variations or the selectors need adjustment.</em></p>";
    }
    
    echo "<hr>";
}

echo "<h2>Functionality Summary</h2>";
echo "<ul>";
echo "<li><strong>Variation Detection:</strong> Uses multiple CSS selectors to find Amazon product variations</li>";
echo "<li><strong>Data Extraction:</strong> Extracts ASIN, title, image, price, and attributes for each variation</li>";
echo "<li><strong>Product Creation:</strong> Creates WooCommerce Variable Products with proper variations</li>";
echo "<li><strong>Attribute Management:</strong> Automatically creates product attributes (Color, Size, Style, etc.)</li>";
echo "<li><strong>Amazon Redirect:</strong> AJAX-based system redirects users to specific Amazon variation URLs</li>";
echo "<li><strong>Compliance:</strong> Maintains Amazon affiliate compliance by redirecting to Amazon for purchases</li>";
echo "</ul>";

echo "<h2>JavaScript Integration</h2>";
echo "<p>The plugin includes JavaScript that:</p>";
echo "<ul>";
echo "<li>Detects when users select variation options</li>";
echo "<li>Overrides the 'Add to Cart' button behavior</li>";
echo "<li>Redirects users to the specific Amazon variation URL</li>";
echo "<li>Maintains affiliate tags throughout the process</li>";
echo "</ul>";

?>
