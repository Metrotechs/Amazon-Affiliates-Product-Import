<?php
/**
 * Test Amazon Product Video Extraction and Display
 * 
 * This script demonstrates the video extraction and display functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

echo "<h1>Amazon Product Video System Test</h1>";

// Test Amazon URLs that might have videos
$test_urls = array(
    'https://amazon.com/dp/B08N5WRWNW', // Example product that might have videos
    'https://amazon.com/dp/B07ZPKN6YR', // Another example
);

foreach ($test_urls as $url) {
    echo "<h2>Testing URL: " . esc_html($url) . "</h2>";
    
    // Initialize classes
    $scraper = new Amazon_Affiliate_Scraper();
    $product_importer = new Amazon_Affiliate_Product();
    
    echo "<h3>1. Video Extraction Test</h3>";
    
    // Get page content
    $html = $scraper->get_page_content($url);
    
    if (!is_wp_error($html)) {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        
        // Extract videos (this would call the private method - for testing we'd need to make it public)
        // $videos = $scraper->extract_videos($xpath, $url);
        
        echo "<p><em>Video extraction would be called here. The system looks for:</em></p>";
        echo "<ul>";
        echo "<li><strong>Video Player Elements:</strong> Direct video/source tags</li>";
        echo "<li><strong>Video Thumbnails:</strong> Elements with data-video-url attributes</li>";
        echo "<li><strong>JSON Data:</strong> JavaScript containing video URLs</li>";
        echo "<li><strong>Embedded Videos:</strong> YouTube, Vimeo, and other iframe videos</li>";
        echo "</ul>";
        
        // Simulate found videos for demonstration
        $demo_videos = array(
            array(
                'url' => 'https://example.com/sample-video.mp4',
                'type' => 'mp4',
                'thumbnail' => 'https://example.com/video-thumb.jpg',
                'source' => 'html_element'
            ),
            array(
                'url' => 'https://youtube.com/watch?v=dQw4w9WgXcQ',
                'type' => 'youtube',
                'thumbnail' => '',
                'source' => 'iframe'
            )
        );
        
        echo "<h4>Demo Video Data Structure:</h4>";
        echo "<pre>" . esc_html(print_r($demo_videos, true)) . "</pre>";
        
    } else {
        echo "<p style='color: red;'>Error fetching page: " . esc_html($html->get_error_message()) . "</p>";
    }
    
    echo "<hr>";
}

echo "<h2>Video Display Features</h2>";

echo "<h3>1. Product Summary Integration</h3>";
echo "<p>Videos are automatically displayed on product pages using the <code>woocommerce_single_product_summary</code> hook.</p>";

echo "<h3>2. Product Tabs Integration</h3>";
echo "<p>A dedicated 'Videos' tab is added to WooCommerce product tabs for better organization.</p>";

echo "<h3>3. Video Player Support</h3>";
echo "<ul>";
echo "<li><strong>YouTube:</strong> Embedded iframe players</li>";
echo "<li><strong>Vimeo:</strong> Embedded iframe players</li>";
echo "<li><strong>MP4/WebM:</strong> HTML5 video players with controls</li>";
echo "<li><strong>Amazon Videos:</strong> Direct video file support</li>";
echo "<li><strong>Generic Videos:</strong> Clickable thumbnail links</li>";
echo "</ul>";

echo "<h3>4. Video Data Storage</h3>";
echo "<p>Videos are stored as post meta:</p>";
echo "<ul>";
echo "<li><code>_amazon_videos</code> - Array of video data</li>";
echo "<li><code>_amazon_has_videos</code> - Boolean flag for quick checking</li>";
echo "</ul>";

echo "<h3>5. Video Data Structure</h3>";
echo "<pre>";
echo "array(
    'url' => 'https://example.com/video.mp4',
    'type' => 'mp4|youtube|vimeo|amazon_video',
    'thumbnail' => 'https://example.com/thumb.jpg',
    'source' => 'json_data|html_element|iframe'
)";
echo "</pre>";

echo "<h2>Video Extraction Methods</h2>";

echo "<h3>1. HTML Element Detection</h3>";
echo "<ul>";
echo "<li>Direct video/source tags in the page</li>";
echo "<li>Elements with data-video-url attributes</li>";
echo "<li>Video thumbnail containers</li>";
echo "</ul>";

echo "<h3>2. JSON Data Parsing</h3>";
echo "<ul>";
echo "<li>JavaScript variables containing video URLs</li>";
echo "<li>VideoBlockATF and similar objects</li>";
echo "<li>Thumbnail and URL pair extraction</li>";
echo "</ul>";

echo "<h3>3. Iframe Video Detection</h3>";
echo "<ul>";
echo "<li>YouTube embedded videos</li>";
echo "<li>Vimeo embedded videos</li>";
echo "<li>Generic video iframes</li>";
echo "</ul>";

echo "<h2>Display Customization</h2>";

echo "<h3>CSS Styling</h3>";
echo "<p>The system includes comprehensive CSS for:</p>";
echo "<ul>";
echo "<li>Responsive video containers</li>";
echo "<li>Play button overlays for thumbnails</li>";
echo "<li>Mobile-friendly responsive design</li>";
echo "<li>Professional styling that matches WooCommerce themes</li>";
echo "</ul>";

echo "<h3>Display Options</h3>";
echo "<ul>";
echo "<li><strong>Summary Display:</strong> Shows videos directly on product page</li>";
echo "<li><strong>Tab Display:</strong> Organizes videos in a dedicated tab</li>";
echo "<li><strong>Thumbnail Links:</strong> For videos without embed support</li>";
echo "<li><strong>Direct Players:</strong> For supported video formats</li>";
echo "</ul>";

echo "<h2>Amazon Compliance</h2>";

echo "<h3>Video Handling Compliance</h3>";
echo "<ul>";
echo "<li>✅ Videos link to Amazon content when possible</li>";
echo "<li>✅ External video links open in new tabs</li>";
echo "<li>✅ No downloading or hosting of Amazon video content</li>";
echo "<li>✅ Proper attribution maintained</li>";
echo "</ul>";

echo "<h2>Benefits for Amazon Affiliate Sites</h2>";

echo "<h3>Enhanced Product Presentation</h3>";
echo "<ul>";
echo "<li><strong>Rich Media:</strong> Videos provide detailed product demonstrations</li>";
echo "<li><strong>Increased Engagement:</strong> Video content keeps visitors on your site longer</li>";
echo "<li><strong>Better Conversions:</strong> Product videos help customers make informed decisions</li>";
echo "<li><strong>Professional Appearance:</strong> Video integration makes your site look more professional</li>";
echo "</ul>";

echo "<h3>SEO Benefits</h3>";
echo "<ul>";
echo "<li><strong>Rich Snippets:</strong> Video content can appear in search results</li>";
echo "<li><strong>Engagement Metrics:</strong> Longer time on page improves SEO rankings</li>";
echo "<li><strong>Content Variety:</strong> Mixed media content is favored by search engines</li>";
echo "</ul>";

echo "<h2>Usage Instructions</h2>";

echo "<h3>For Site Administrators</h3>";
echo "<ol>";
echo "<li>Import Amazon products as usual - videos are automatically detected and extracted</li>";
echo "<li>Videos will appear on product pages automatically</li>";
echo "<li>Check the 'Videos' tab on products that have video content</li>";
echo "<li>Customize styling through CSS if needed</li>";
echo "</ol>";

echo "<h3>For Customers</h3>";
echo "<ol>";
echo "<li>View videos directly on product pages</li>";
echo "<li>Click the 'Videos' tab for a dedicated video viewing experience</li>";
echo "<li>Videos may link to Amazon for additional content</li>";
echo "<li>Mobile-friendly responsive playback</li>";
echo "</ol>";

?>
