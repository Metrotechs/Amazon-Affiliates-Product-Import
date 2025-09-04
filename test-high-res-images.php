<?php
/**
 * Test High-Resolution Image Extraction
 * 
 * This script tests the enhanced image extraction functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

echo "<h1>High-Resolution Image Extraction Test</h1>";

// Test Amazon URLs 
$test_urls = array(
    'https://amazon.com/dp/B08N5WRWNW', // Example product
    'https://amazon.com/dp/B07ZPKN6YR', // Another example
);

foreach ($test_urls as $url) {
    echo "<h2>Testing URL: " . esc_html($url) . "</h2>";
    
    // Initialize scraper
    $scraper = new Amazon_Affiliate_Scraper();
    
    // Test image extraction
    echo "<h3>Extracting Product Images</h3>";
    
    // Get the HTML content first
    $html = $scraper->get_page_content($url);
    
    if (!is_wp_error($html)) {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        
        // Extract images using the enhanced method
        $images = $scraper->extract_images($xpath); // This calls the private method, would need to make it public for testing
        
        echo "<p>Found " . count($images) . " high-resolution images:</p>";
        
        if (!empty($images)) {
            echo "<div style='display: flex; flex-wrap: wrap; gap: 10px;'>";
            foreach ($images as $i => $image_url) {
                echo "<div style='border: 1px solid #ccc; padding: 10px; max-width: 300px;'>";
                echo "<h4>Image " . ($i + 1) . "</h4>";
                echo "<img src='" . esc_url($image_url) . "' style='max-width: 100%; height: auto; max-height: 200px;'><br>";
                echo "<small style='word-break: break-all;'>" . esc_html($image_url) . "</small>";
                
                // Show URL analysis
                echo "<div style='margin-top: 5px; font-size: 12px;'>";
                if (strpos($image_url, '_SL1500_') !== false) {
                    echo "<span style='color: green;'>✓ Large format detected</span><br>";
                }
                if (strpos($image_url, 'hiRes') !== false) {
                    echo "<span style='color: green;'>✓ High-res source</span><br>";
                }
                if (preg_match('/\d{3,4}x\d{3,4}/', $image_url)) {
                    echo "<span style='color: green;'>✓ High dimensions detected</span><br>";
                }
                echo "</div>";
                
                echo "</div>";
            }
            echo "</div>";
        } else {
            echo "<p><em>No images found. Check the URL or scraping selectors.</em></p>";
        }
    } else {
        echo "<p style='color: red;'>Error fetching page: " . esc_html($html->get_error_message()) . "</p>";
    }
    
    echo "<hr>";
}

echo "<h2>Image Enhancement Features</h2>";
echo "<ul>";
echo "<li><strong>Multi-Source Extraction:</strong> Extracts from HTML img tags, JSON data, and data-a-dynamic-image attributes</li>";
echo "<li><strong>High-Resolution Priority:</strong> Prioritizes hiRes and large image versions from Amazon's JSON data</li>";
echo "<li><strong>URL Enhancement:</strong> Removes size restrictions from Amazon image URLs (._AC_SL300_ patterns)</li>";
echo "<li><strong>Quality Filtering:</strong> Validates image URLs and filters out 1x1 tracking pixels</li>";
echo "<li><strong>Dynamic Image Support:</strong> Parses data-a-dynamic-image JSON to find largest available sizes</li>";
echo "<li><strong>Variation Images:</strong> Enhanced image extraction for product variations</li>";
echo "</ul>";

echo "<h2>Amazon Image URL Patterns</h2>";
echo "<p>The enhanced system handles various Amazon image URL patterns:</p>";
echo "<ul>";
echo "<li><strong>Original:</strong> <code>https://m.media-amazon.com/images/I/51abc123._AC_SL300_.jpg</code></li>";
echo "<li><strong>Enhanced:</strong> <code>https://m.media-amazon.com/images/I/51abc123.jpg</code></li>";
echo "<li><strong>Benefits:</strong> Removes size restrictions, provides full-resolution images</li>";
echo "</ul>";

echo "<h2>JSON Data Sources</h2>";
echo "<p>The system extracts images from multiple JavaScript data sources:</p>";
echo "<ul>";
echo "<li><strong>ImageBlockATF:</strong> Main product image data</li>";
echo "<li><strong>colorImages:</strong> Variation-specific images</li>";
echo "<li><strong>hiRes objects:</strong> High-resolution image sources</li>";
echo "<li><strong>data-a-dynamic-image:</strong> Dynamic image loading data</li>";
echo "</ul>";

?>
