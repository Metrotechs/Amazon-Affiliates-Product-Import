<?php
/**
 * Amazon Product Scraper for Amazon Affiliate Product Importer
 */

if (!defined('ABSPATH')) {
    exit;
}

class AmazonAffiliateImporter_Scraper {
    
    private $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
    
    /**
     * Scrape product data from Amazon URL
     */
    public function scrape_product($url) {
        // Resolve shortened URLs first
        $url = $this->resolve_short_url($url);
        
        if (is_wp_error($url)) {
            return $url;
        }
        
        // Get page content
        $html = $this->fetch_page_content($url);
        
        if (is_wp_error($html)) {
            return $html;
        }
        
        // Parse product data
        $product_data = $this->parse_product_data($html, $url);
        
        if (empty($product_data['title'])) {
            return new WP_Error('scrape_failed', __('Could not extract product information from Amazon page.', 'amazon-affiliate-importer'));
        }
        
        return $product_data;
    }
    
    /**
     * Get page content for category extraction
     */
    public function get_page_content($url) {
        // Resolve shortened URLs first
        $url = $this->resolve_short_url($url);
        
        if (is_wp_error($url)) {
            return $url;
        }
        
        // Get page content
        return $this->fetch_page_content($url);
    }
    
    /**
     * Resolve shortened URLs (amzn.to, etc.)
     */
    private function resolve_short_url($url) {
        if (strpos($url, 'amzn.to') !== false || strpos($url, 'amzn.com') !== false) {
            $response = wp_remote_head($url, array(
                'timeout' => 15,
                'redirection' => 5,
                'user-agent' => $this->user_agent
            ));
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $headers = wp_remote_retrieve_headers($response);
            if (isset($headers['location'])) {
                return $headers['location'];
            }
        }
        
        return $url;
    }
    
    /**
     * Fetch page content from URL
     */
    private function fetch_page_content($url) {
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'user-agent' => $this->user_agent,
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive'
            )
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('fetch_failed', __('Could not fetch Amazon page. Please check the URL and try again.', 'amazon-affiliate-importer'));
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            return new WP_Error('http_error', sprintf(__('HTTP Error %d when fetching Amazon page.', 'amazon-affiliate-importer'), $http_code));
        }
        
        $html = wp_remote_retrieve_body($response);
        
        if (empty($html)) {
            return new WP_Error('empty_response', __('Empty response from Amazon page.', 'amazon-affiliate-importer'));
        }
        
        return $html;
    }
    
    /**
     * Parse product data from HTML
     */
    private function parse_product_data($html, $url) {
        // Clean HTML first - remove scripts, styles, and other unwanted elements
        $html = $this->clean_html($html);
        
        // Create DOMDocument to parse HTML
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new DOMXPath($dom);
        
        $product_data = array(
            'title' => '',
            'description' => '',
            'short_description' => '',
            'price' => '',
            'images' => array(),
            'asin' => '',
            'features' => array()
        );
        
        // Extract ASIN
        $product_data['asin'] = $this->extract_asin_from_url($url);
        
        // Extract title
        $product_data['title'] = $this->extract_title($xpath);
        
        // Extract price
        $product_data['price'] = $this->extract_price($xpath);
        
        // Extract description and features
        $description_data = $this->extract_description($xpath);
        $product_data['description'] = $description_data['description'];
        $product_data['short_description'] = $description_data['short_description'];
        $product_data['features'] = $description_data['features'];
        
        // Extract images
        $product_data['images'] = $this->extract_images($xpath);
        
        // Extract videos
        $product_data['videos'] = $this->extract_videos($xpath, $url);
        
        // Extract ratings and review count
        $rating_data = $this->extract_rating_data($xpath);
        $product_data['rating'] = $rating_data['rating'];
        $product_data['review_count'] = $rating_data['review_count'];
        
        // Debug: Log rating extraction results
        error_log('Amazon Importer: Rating extracted - Rating: ' . 
                 ($rating_data['rating'] ?? 'none') . ', Review Count: ' . 
                 ($rating_data['review_count'] ?? 'none'));
        
        return $product_data;
    }
    
    /**
     * Clean HTML by removing unwanted elements
     */
    private function clean_html($html) {
        // Remove script and style tags completely
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);
        
        // Remove other unwanted elements that often contain CSS/JS
        $unwanted_elements = array(
            'noscript', 'iframe', 'embed', 'object', 'applet', 
            'link[rel="stylesheet"]', 'meta[name="viewport"]'
        );
        
        foreach ($unwanted_elements as $element) {
            $html = preg_replace('/<' . $element . '\b[^>]*>.*?<\/' . $element . '>/is', '', $html);
        }
        
        // Remove HTML comments
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        
        return $html;
    }
    
    /**
     * Extract product title
     */
    private function extract_title($xpath) {
        $title_selectors = array(
            '//span[@id="productTitle"]',
            '//h1[@id="title"]//span[@id="productTitle"]',
            '//div[@id="title_feature_div"]//h1//span',
            '//h1[contains(@class, "a-size-large")]//span',
            '//h1[contains(@class, "product-title")]'
        );
        
        foreach ($title_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $title = $this->clean_text($nodes->item(0)->textContent);
                // Make sure it's a real title, not CSS or other content
                if (!empty($title) && 
                    !$this->is_unwanted_content($title) &&
                    strlen($title) > 5 &&
                    strlen($title) < 200) {
                    return $title;
                }
            }
        }
        
        return '';
    }
    
    /**
     * Extract price
     */
    private function extract_price($xpath) {
        $price_selectors = array(
            // Try offscreen price first (most reliable with full price including cents)
            '//span[contains(@class, "a-price")]//span[@class="a-offscreen"]',
            '//span[@class="a-price a-text-price a-size-medium a-color-base"]//span[@class="a-offscreen"]',
            '//td[@class="a-span12"]//span[@class="a-price a-text-price a-size-medium a-color-base"]//span[@class="a-offscreen"]',
            // Fallback to visible price elements
            '//span[@id="priceblock_dealprice"]',
            '//span[@id="priceblock_ourprice"]',
            '//span[@id="price_inside_buybox"]',
            // Try to get whole price + fraction together
            '//span[contains(@class, "a-price-range")]',
            // Last resort: piece together whole and fraction
            '//span[contains(@class, "a-price-whole")]'
        );
        
        foreach ($price_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $price_text = $this->clean_text($nodes->item(0)->textContent);
                
                // Extract numeric price with proper decimal handling
                // Updated regex to properly capture dollars and cents
                if (preg_match('/[\$£€¥₹]?\s*(\d{1,3}(?:,\d{3})*(?:\.\d{2})?|\d+(?:\.\d{2})?)/', $price_text, $matches)) {
                    $price = preg_replace('/[^\d\.]/', '', $matches[1]);
                    // Remove extra dots but preserve the last one for decimals
                    $price_parts = explode('.', $price);
                    if (count($price_parts) > 2) {
                        // Multiple dots - keep only the last one as decimal
                        $whole_part = implode('', array_slice($price_parts, 0, -1));
                        $decimal_part = end($price_parts);
                        $price = $whole_part . '.' . $decimal_part;
                    }
                    
                    // Validate it's a reasonable price
                    if (is_numeric($price) && $price > 0 && $price < 999999) {
                        return $price;
                    }
                }
            }
        }
        
        // If standard selectors fail, try to piece together whole and fraction parts
        $whole_node = $xpath->query('//span[contains(@class, "a-price-whole")]');
        $fraction_node = $xpath->query('//span[contains(@class, "a-price-fraction")]');
        
        if ($whole_node->length > 0) {
            $whole_part = $this->clean_text($whole_node->item(0)->textContent);
            $whole_part = preg_replace('/[^\d]/', '', $whole_part);
            
            $fraction_part = '00'; // Default to 00 cents
            if ($fraction_node->length > 0) {
                $fraction_part = $this->clean_text($fraction_node->item(0)->textContent);
                $fraction_part = preg_replace('/[^\d]/', '', $fraction_part);
                // Ensure fraction is 2 digits
                $fraction_part = str_pad(substr($fraction_part, 0, 2), 2, '0', STR_PAD_RIGHT);
            }
            
            if (!empty($whole_part) && is_numeric($whole_part)) {
                $price = $whole_part . '.' . $fraction_part;
                if (is_numeric($price) && $price > 0 && $price < 999999) {
                    return $price;
                }
            }
        }
        
        return '';
    }
    
    /**
     * Extract description and features
     */
    private function extract_description($xpath) {
        $description = '';
        $short_description = '';
        $features = array();
        
        // Extract feature bullets (clean text only)
        $feature_selectors = array(
            '//div[@id="feature-bullets"]//span[@class="a-list-item"]',
            '//div[@id="featurebullets_feature_div"]//span[@class="a-list-item"]',
            '//div[@data-feature-name="featurebullets"]//span[@class="a-list-item"]'
        );
        
        foreach ($feature_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                foreach ($nodes as $node) {
                    $feature = $this->clean_text($node->textContent);
                    // Skip empty features, CSS content, and JavaScript
                    if (!empty($feature) && 
                        !$this->is_unwanted_content($feature) && 
                        !in_array($feature, $features) &&
                        strlen($feature) > 10) {
                        $features[] = $feature;
                    }
                }
                break;
            }
        }
        
        // Create short description from features
        if (!empty($features)) {
            // Take first 2-3 most relevant features for short description
            $short_features = array_slice($features, 0, 3);
            $short_description = implode(" • ", $short_features);
            
            // Ensure short description isn't too long
            if (strlen($short_description) > 300) {
                $short_features = array_slice($features, 0, 2);
                $short_description = implode(" • ", $short_features);
            }
        }
        
        // Extract detailed description (avoid CSS/JS content)
        $description_selectors = array(
            '//div[@id="productDescription"]//p',
            '//div[@id="aplus"]//p',
            '//div[contains(@class, "a-section a-spacing-small")]//p[not(contains(@class, "aplus-v2"))]',
            '//div[@id="productDescription"]//div[contains(@class, "a-section")]',
            '//div[@data-feature-name="productDescription"]//span'
        );
        
        foreach ($description_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $desc_parts = array();
                foreach ($nodes as $node) {
                    $text = $this->clean_text($node->textContent);
                    if (!empty($text) && 
                        !$this->is_unwanted_content($text) && 
                        strlen($text) > 20 &&
                        !$this->is_duplicate_content($text, $desc_parts)) {
                        $desc_parts[] = $text;
                    }
                }
                if (!empty($desc_parts)) {
                    $description = $this->format_description_paragraphs($desc_parts);
                    break;
                }
            }
        }
        
        // Fallback to features for description if no clean description found
        if (empty($description) && !empty($features)) {
            $description = implode("\n", $features);
        }
        
        return array(
            'description' => $description,
            'short_description' => $short_description,
            'features' => $features
        );
    }
    
    /**
     * Clean text content - remove extra whitespace, unwanted characters
     */
    private function clean_text($text) {
        // Remove extra whitespace and normalize
        $text = preg_replace('/\s+/', ' ', trim($text));
        
        // Remove common unwanted characters and sequences, but keep some formatting
        $text = preg_replace('/[^\x20-\x7E\x0A\x0D]/', '', $text); // Keep only printable ASCII + newlines
        $text = str_replace(array("\r\n", "\r"), "\n", $text);
        
        // Clean up excessive newlines but preserve paragraph breaks
        $text = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $text);
        $text = preg_replace('/^\s+|\s+$/m', '', $text); // Trim each line
        
        // Remove common Amazon-specific unwanted text
        $unwanted_phrases = array(
            'See more product details',
            'Click to expand',
            'Read more',
            'Show more',
            'Learn more',
            'Visit the Store',
            'See all items',
            'Brand Story'
        );
        
        foreach ($unwanted_phrases as $phrase) {
            $text = str_ireplace($phrase, '', $text);
        }
        
        return trim($text);
    }
    
    /**
     * Check if content is unwanted (CSS, JS, etc.)
     */
    private function is_unwanted_content($text) {
        $unwanted_patterns = array(
            '/^\s*[\{\}\(\)\[\];,\.]\s*$/',           // Just punctuation
            '/^\s*\d+(\.\d+)?\s*(px|em|rem|%)\s*$/',   // CSS measurements
            '/^\s*(margin|padding|border|width|height|background|color|font)[-\w]*\s*:/',  // CSS properties
            '/function\s*\(/i',                        // JavaScript functions
            '/^\s*var\s+\w+/i',                       // JavaScript variables
            '/window\.|document\.|\.prototype\./i',    // JavaScript objects
            '/getElementById|querySelector/i',         // JavaScript DOM methods
            '/aplus-v2|apm-|\.a-/i',                  // Amazon CSS classes
            '/logShoppableMetrics|AplusModule/i',      // Amazon JavaScript
            '/^\s*[A-Z_]{3,}\s*$/',                   // All caps constants
            '/^\s*\$\d+\.\d+\s*$/',                   // Just prices without context
            '/Add to Cart/i',                         // UI elements
            '/^\s*✓\s*$/',                            // Just checkmarks
        );
        
        foreach ($unwanted_patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extract product images
     */
    private function extract_images($xpath) {
        $images = array();
        
        // First priority: Extract high-res images from JSON data
        $json_images = $this->extract_images_from_json($xpath);
        if (!empty($json_images)) {
            $images = array_merge($images, $json_images);
        }
        
        // Secondary: Try different image selectors and enhance them
        $image_selectors = array(
            // Main product image
            '//img[@id="landingImage"]/@src',
            '//img[@id="landingImage"]/@data-old-hires',
            '//img[@id="landingImage"]/@data-a-dynamic-image',
            
            // Image block variations
            '//div[@id="imageBlock"]//img/@src',
            '//div[@id="imageBlock"]//img/@data-old-hires',
            '//div[@id="imgTagWrapperId"]//img/@src',
            '//div[@id="imgTagWrapperId"]//img/@data-old-hires',
            
            // Alternative image containers
            '//div[contains(@class, "imgTagWrapper")]//img/@src',
            '//div[contains(@class, "imgTagWrapper")]//img/@data-old-hires',
            '//div[@id="imageBlockNew"]//img/@src',
            '//div[@id="altImages"]//img/@src',
            
            // Thumbnail images that can be enhanced
            '//div[@id="altImages"]//span[@class="a-button-text"]//img/@src',
            '//div[contains(@class, "imageThumb")]//img/@src',
            '//ol[@id="thumbs"]//img/@src'
        );
        
        foreach ($image_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                foreach ($nodes as $node) {
                    $image_url = trim($node->textContent);
                    if (empty($image_url) || strpos($image_url, 'data:') === 0) {
                        continue; // Skip empty or data URLs
                    }
                    
                    // Convert to high resolution image
                    $image_url = $this->convert_to_high_res_image($image_url);
                    
                    // Validate and add unique images
                    if ($this->is_valid_image_url($image_url) && !in_array($image_url, $images)) {
                        $images[] = $image_url;
                    }
                }
            }
        }
        
        // Extract from data-a-dynamic-image attributes
        $dynamic_images = $this->extract_dynamic_images($xpath);
        if (!empty($dynamic_images)) {
            $images = array_merge($images, $dynamic_images);
        }
        
        return array_unique(array_filter($images));
    }
    
    /**
     * Convert Amazon image URL to high resolution
     */
    private function convert_to_high_res_image($image_url) {
        if (empty($image_url)) {
            return '';
        }
        
        // Amazon image URL patterns that need to be converted
        // Example: https://m.media-amazon.com/images/I/51abc123._AC_SL1500_.jpg
        // Should become: https://m.media-amazon.com/images/I/51abc123.jpg
        
        // Remove common Amazon size and quality restrictions
        $patterns_to_remove = array(
            '/\._[A-Z]{2}[0-9]+_/',           // ._AC1500_, ._SL1500_, etc.
            '/\._[A-Z]{2}_[A-Z]{2}[0-9]+_/', // ._AC_SL1500_, ._SY300_, etc.
            '/\._[A-Z]{3}[0-9]+_/',          // ._UL1500_, ._SY300_, etc.
            '/\._[A-Z]{2}[0-9]+,[0-9]+_/',   // ._AC300,300_, etc.
            '/\._[A-Z]+[0-9,]+_/',           // More complex patterns
            '/\._[A-Z]{2}_/',                // ._AC_, ._SY_, etc.
        );
        
        foreach ($patterns_to_remove as $pattern) {
            $image_url = preg_replace($pattern, '.', $image_url);
        }
        
        // Ensure we have a proper file extension
        if (!preg_match('/\.(jpg|jpeg|png|webp)$/i', $image_url)) {
            $image_url .= '.jpg'; // Default to jpg if no extension
        }
        
        // For very specific Amazon patterns, try to get the largest available size
        if (preg_match('/images\/I\/([^.]+)\./', $image_url, $matches)) {
            $image_id = $matches[1];
            // Try to construct the highest quality URL
            $base_url = 'https://m.media-amazon.com/images/I/' . $image_id . '.jpg';
            return $base_url;
        }
        
        return $image_url;
    }
    
    /**
     * Validate image URL
     */
    private function is_valid_image_url($url) {
        if (empty($url)) {
            return false;
        }
        
        // Check if it's a valid URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Check if it's an image file
        $image_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        
        if (!in_array($extension, $image_extensions)) {
            // Amazon images might not have extensions, check domain
            if (strpos($url, 'media-amazon.com') !== false || 
                strpos($url, 'ssl-images-amazon.com') !== false ||
                strpos($url, 'images-amazon.com') !== false) {
                return true;
            }
            return false;
        }
        
        // Check minimum size (avoid 1x1 tracking pixels)
        if (strpos($url, '1x1') !== false || strpos($url, '_1,1_') !== false) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Extract images from data-a-dynamic-image attributes
     */
    private function extract_dynamic_images($xpath) {
        $images = array();
        
        // Look for data-a-dynamic-image attributes which contain JSON with image URLs
        $nodes = $xpath->query('//img[@data-a-dynamic-image]');
        
        foreach ($nodes as $node) {
            $dynamic_data = $node->getAttribute('data-a-dynamic-image');
            if (!empty($dynamic_data)) {
                // Parse the JSON data
                $image_data = json_decode($dynamic_data, true);
                if (is_array($image_data)) {
                    // Get the largest image (keys are URLs, values are dimensions)
                    $largest_size = 0;
                    $largest_url = '';
                    
                    foreach ($image_data as $url => $dimensions) {
                        if (is_array($dimensions) && count($dimensions) >= 2) {
                            $size = $dimensions[0] * $dimensions[1]; // width * height
                            if ($size > $largest_size) {
                                $largest_size = $size;
                                $largest_url = $url;
                            }
                        }
                    }
                    
                    if (!empty($largest_url) && $this->is_valid_image_url($largest_url)) {
                        $images[] = $largest_url;
                    }
                    
                    // Also add other high-quality images
                    foreach ($image_data as $url => $dimensions) {
                        if (is_array($dimensions) && count($dimensions) >= 2) {
                            $width = $dimensions[0];
                            $height = $dimensions[1];
                            
                            // Only include images that are reasonably large
                            if ($width >= 300 && $height >= 300 && $this->is_valid_image_url($url)) {
                                if (!in_array($url, $images)) {
                                    $images[] = $url;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return $images;
    }
    
    /**
     * Extract images from JSON data in page
     */
    private function extract_images_from_json($xpath) {
        $images = array();
        
        // Look for JSON data containing images - enhanced selectors
        $script_selectors = array(
            '//script[contains(text(), "ImageBlockATF")]',
            '//script[contains(text(), "colorImages")]',
            '//script[contains(text(), "hiRes")]',
            '//script[contains(text(), "large")]',
            '//script[contains(text(), "main")]',
            '//script[contains(text(), "landingAsinColor")]'
        );
        
        foreach ($script_selectors as $selector) {
            $script_nodes = $xpath->query($selector);
            
            foreach ($script_nodes as $script) {
                $content = $script->textContent;
                
                // Extract high-resolution images first (priority)
                if (preg_match_all('/"hiRes":"([^"]+)"/', $content, $matches)) {
                    foreach ($matches[1] as $url) {
                        $url = str_replace('\\/', '/', $url);
                        if ($this->is_valid_image_url($url) && !in_array($url, $images)) {
                            $images[] = $url;
                        }
                    }
                }
                
                // Extract large images
                if (preg_match_all('/"large":"([^"]+)"/', $content, $matches)) {
                    foreach ($matches[1] as $url) {
                        $url = str_replace('\\/', '/', $url);
                        if ($this->is_valid_image_url($url) && !in_array($url, $images)) {
                            $images[] = $url;
                        }
                    }
                }
                
                // Extract main images  
                if (preg_match_all('/"main":{"([^"]+)":(\[[0-9,]+\])/', $content, $matches)) {
                    foreach ($matches[1] as $url) {
                        $url = str_replace('\\/', '/', $url);
                        if ($this->is_valid_image_url($url) && !in_array($url, $images)) {
                            $images[] = $url;
                        }
                    }
                }
                
                // Extract from colorImages object
                if (preg_match_all('/"colorImages":\{[^}]*"initial":\[([^\]]+)\]/', $content, $matches)) {
                    foreach ($matches[1] as $initial_data) {
                        // Parse the initial image data
                        if (preg_match_all('/"hiRes":"([^"]+)"/', $initial_data, $sub_matches)) {
                            foreach ($sub_matches[1] as $url) {
                                $url = str_replace('\\/', '/', $url);
                                if ($this->is_valid_image_url($url) && !in_array($url, $images)) {
                                    $images[] = $url;
                                }
                            }
                        }
                        if (preg_match_all('/"large":"([^"]+)"/', $initial_data, $sub_matches)) {
                            foreach ($sub_matches[1] as $url) {
                                $url = str_replace('\\/', '/', $url);
                                if ($this->is_valid_image_url($url) && !in_array($url, $images)) {
                                    $images[] = $url;
                                }
                            }
                        }
                    }
                }
                
                // Generic high-quality image pattern
                if (preg_match_all('/"([^"]*media-amazon\.com[^"]*\.jpg)"/', $content, $matches)) {
                    foreach ($matches[1] as $url) {
                        $url = str_replace('\\/', '/', $url);
                        // Only include if it looks like a high-quality image
                        if (strpos($url, '_SL1500_') !== false || 
                            strpos($url, '_AC_') !== false ||
                            preg_match('/\d{4,}/', $url)) { // Contains 4+ digit numbers (likely dimensions)
                            
                            // Convert to highest res
                            $url = $this->convert_to_high_res_image($url);
                            if ($this->is_valid_image_url($url) && !in_array($url, $images)) {
                                $images[] = $url;
                            }
                        }
                    }
                }
            }
        }
        
        return $images;
    }
    
    /**
     * Extract product videos from Amazon page
     */
    private function extract_videos($xpath, $url) {
        $videos = array();
        
        // Extract videos from various sources on Amazon product pages
        
        // 1. Look for video thumbnails and video player elements
        $video_selectors = array(
            // Main video player
            '//div[@id="vse-related-videos"]//video/@src',
            '//div[@id="vse-related-videos"]//source/@src',
            
            // Video blocks in content
            '//div[contains(@class, "video")]//video/@src',
            '//div[contains(@class, "video")]//source/@src',
            
            // Video thumbnails that link to videos
            '//div[@data-video-url]/@data-video-url',
            '//span[@data-video-url]/@data-video-url',
            
            // Alternative video containers
            '//div[@id="altImages"]//span[contains(@class, "video")]/@data-video-url',
            '//div[contains(@class, "imageThumb")]//span[contains(@class, "video")]/@data-video-url'
        );
        
        foreach ($video_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                foreach ($nodes as $node) {
                    $video_url = trim($node->textContent);
                    if (!empty($video_url) && $this->is_valid_video_url($video_url)) {
                        $video_data = array(
                            'url' => $video_url,
                            'type' => $this->get_video_type($video_url),
                            'thumbnail' => $this->extract_video_thumbnail($node, $xpath)
                        );
                        
                        if (!in_array($video_data, $videos)) {
                            $videos[] = $video_data;
                        }
                    }
                }
            }
        }
        
        // 2. Extract from JSON data containing video information
        $json_videos = $this->extract_videos_from_json($xpath, $url);
        if (!empty($json_videos)) {
            $videos = array_merge($videos, $json_videos);
        }
        
        // 3. Look for embedded video iframes
        $iframe_videos = $this->extract_iframe_videos($xpath);
        if (!empty($iframe_videos)) {
            $videos = array_merge($videos, $iframe_videos);
        }
        
        // Remove duplicates and return
        return array_unique($videos, SORT_REGULAR);
    }
    
    /**
     * Extract videos from JSON data in Amazon page
     */
    private function extract_videos_from_json($xpath, $base_url) {
        $videos = array();
        
        // Look for JavaScript that contains video data
        $script_selectors = array(
            '//script[contains(text(), "videoUrl")]',
            '//script[contains(text(), "mp4")]',
            '//script[contains(text(), "video")]',
            '//script[contains(text(), "VideoBlockATF")]',
            '//script[contains(text(), "videoBlock")]'
        );
        
        foreach ($script_selectors as $selector) {
            $script_nodes = $xpath->query($selector);
            
            foreach ($script_nodes as $script) {
                $content = $script->textContent;
                
                // Extract video URLs from various JSON patterns
                $video_patterns = array(
                    '/"videoUrl":"([^"]+)"/',
                    '/"mp4":"([^"]+)"/',
                    '/"video":"([^"]+)"/',
                    '/"src":"([^"]*\.mp4[^"]*)"/',
                    '/"url":"([^"]*\.mp4[^"]*)"/'
                );
                
                foreach ($video_patterns as $pattern) {
                    if (preg_match_all($pattern, $content, $matches)) {
                        foreach ($matches[1] as $video_url) {
                            $video_url = str_replace('\\/', '/', $video_url);
                            
                            if ($this->is_valid_video_url($video_url)) {
                                $video_data = array(
                                    'url' => $video_url,
                                    'type' => $this->get_video_type($video_url),
                                    'source' => 'json_data',
                                    'thumbnail' => $this->generate_video_thumbnail_url($video_url)
                                );
                                
                                $videos[] = $video_data;
                            }
                        }
                    }
                }
                
                // Look for video thumbnail and URL pairs in JSON
                if (preg_match_all('/"videoThumbnail":"([^"]+)"[^}]*"videoUrl":"([^"]+)"/', $content, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $thumbnail = str_replace('\\/', '/', $match[1]);
                        $video_url = str_replace('\\/', '/', $match[2]);
                        
                        if ($this->is_valid_video_url($video_url)) {
                            $video_data = array(
                                'url' => $video_url,
                                'type' => $this->get_video_type($video_url),
                                'thumbnail' => $thumbnail,
                                'source' => 'json_thumbnail_pair'
                            );
                            
                            $videos[] = $video_data;
                        }
                    }
                }
            }
        }
        
        return $videos;
    }
    
    /**
     * Extract iframe embedded videos
     */
    private function extract_iframe_videos($xpath) {
        $videos = array();
        
        $iframe_selectors = array(
            '//iframe[contains(@src, "video")]/@src',
            '//iframe[contains(@src, "youtube")]/@src',
            '//iframe[contains(@src, "vimeo")]/@src',
            '//iframe[contains(@src, "mp4")]/@src'
        );
        
        foreach ($iframe_selectors as $selector) {
            $nodes = $xpath->query($selector);
            foreach ($nodes as $node) {
                $iframe_src = trim($node->textContent);
                
                if ($this->is_valid_video_url($iframe_src)) {
                    $video_data = array(
                        'url' => $iframe_src,
                        'type' => 'iframe',
                        'source' => 'embedded_iframe'
                    );
                    
                    $videos[] = $video_data;
                }
            }
        }
        
        return $videos;
    }
    
    /**
     * Validate video URL
     */
    private function is_valid_video_url($url) {
        if (empty($url)) {
            return false;
        }
        
        // Check if it's a valid URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Check for video file extensions or video platforms
        $video_indicators = array(
            '.mp4', '.webm', '.ogg', '.avi', '.mov',
            'youtube.com', 'vimeo.com', 'amazon.com',
            'media-amazon.com', 'ssl-images-amazon.com'
        );
        
        foreach ($video_indicators as $indicator) {
            if (strpos(strtolower($url), $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get video type from URL
     */
    private function get_video_type($url) {
        if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
            return 'youtube';
        } elseif (strpos($url, 'vimeo.com') !== false) {
            return 'vimeo';
        } elseif (strpos($url, '.mp4') !== false) {
            return 'mp4';
        } elseif (strpos($url, '.webm') !== false) {
            return 'webm';
        } elseif (strpos($url, 'amazon.com') !== false || strpos($url, 'media-amazon.com') !== false) {
            return 'amazon_video';
        } else {
            return 'unknown';
        }
    }
    
    /**
     * Extract video thumbnail from context
     */
    private function extract_video_thumbnail($node, $xpath) {
        // Look for thumbnail images near the video element
        $thumbnail_selectors = array(
            '..//img/@src',
            '../..//img/@src',
            './/img/@src'
        );
        
        foreach ($thumbnail_selectors as $selector) {
            $thumbnail_nodes = $xpath->query($selector, $node);
            if ($thumbnail_nodes->length > 0) {
                $thumbnail_url = trim($thumbnail_nodes->item(0)->textContent);
                if ($this->is_valid_image_url($thumbnail_url)) {
                    return $this->convert_to_high_res_image($thumbnail_url);
                }
            }
        }
        
        return '';
    }
    
    /**
     * Generate video thumbnail URL for Amazon videos
     */
    private function generate_video_thumbnail_url($video_url) {
        // For Amazon videos, try to generate a thumbnail URL
        if (strpos($video_url, 'amazon.com') !== false || strpos($video_url, 'media-amazon.com') !== false) {
            // Replace video extension with jpg for potential thumbnail
            $thumbnail_url = preg_replace('/\.(mp4|webm|ogg)/', '.jpg', $video_url);
            if ($thumbnail_url !== $video_url) {
                return $thumbnail_url;
            }
        }
        
        return '';
    }
    
    /**
     * Extract rating and review count data
     */
    private function extract_rating_data($xpath) {
        $rating_data = array(
            'rating' => null,
            'review_count' => null
        );
        
        // Try different selectors for rating
        $rating_selectors = array(
            '//span[@class="a-icon-alt" and contains(text(), "out of 5 stars")]',
            '//i[contains(@class, "a-icon-star")]//span[@class="a-icon-alt"]',
            '//span[@id="acrPopover"]/@title',
            '//a[@id="acrCustomerReviewLink"]//span[@class="a-icon-alt"]',
            '//div[@id="averageCustomerReviews"]//span[@class="a-icon-alt"]',
            '//span[contains(@class, "a-icon-alt") and contains(text(), "stars")]'
        );
        
        foreach ($rating_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $text = $nodes->item(0)->textContent ?? $nodes->item(0)->nodeValue;
                if (preg_match('/(\d+\.?\d*)\s*out of\s*5/i', $text, $matches)) {
                    $rating_data['rating'] = floatval($matches[1]);
                    break;
                }
            }
        }
        
        // Try different selectors for review count
        $review_count_selectors = array(
            '//a[@id="acrCustomerReviewLink"]//span[@id="acrCustomerReviewText"]',
            '//span[@id="acrCustomerReviewText"]',
            '//a[contains(@href, "#customerReviews")]//span',
            '//div[@id="averageCustomerReviews"]//a//span[contains(text(), "rating")]',
            '//span[contains(text(), "rating") and contains(text(), "customer")]'
        );
        
        foreach ($review_count_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $text = $nodes->item(0)->textContent;
                // Extract number from text like "1,234 ratings" or "567 customer reviews"
                if (preg_match('/(\d{1,3}(?:,\d{3})*)\s*(?:rating|review|customer)/i', $text, $matches)) {
                    $rating_data['review_count'] = intval(str_replace(',', '', $matches[1]));
                    break;
                }
            }
        }
        
        return $rating_data;
    }
    
    /**
     * Extract ASIN from URL
     */
    private function extract_asin_from_url($url) {
        $patterns = array(
            '/\/dp\/([A-Z0-9]{10})/',
            '/\/gp\/product\/([A-Z0-9]{10})/',
            '/\/product\/([A-Z0-9]{10})/',
            '/asin=([A-Z0-9]{10})/'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        
        return '';
    }
    
    /**
     * Check if content is duplicate
     */
    private function is_duplicate_content($text, $existing_parts) {
        $text_lower = strtolower($text);
        foreach ($existing_parts as $existing) {
            if (strtolower($existing) === $text_lower) {
                return true;
            }
            // Check for significant overlap (80% similarity)
            $similarity = 0;
            similar_text($text_lower, strtolower($existing), $similarity);
            if ($similarity > 80) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Format description paragraphs with proper line breaks
     */
    private function format_description_paragraphs($desc_parts) {
        $formatted_parts = array();
        
        foreach ($desc_parts as $part) {
            // Split long paragraphs at sentence boundaries
            $sentences = preg_split('/(?<=[.!?])\s+/', $part);
            $current_paragraph = '';
            
            foreach ($sentences as $sentence) {
                $sentence = trim($sentence);
                if (empty($sentence)) continue;
                
                // If adding this sentence would make paragraph too long, start new paragraph
                if (strlen($current_paragraph . ' ' . $sentence) > 300 && !empty($current_paragraph)) {
                    $formatted_parts[] = trim($current_paragraph);
                    $current_paragraph = $sentence;
                } else {
                    $current_paragraph .= empty($current_paragraph) ? $sentence : ' ' . $sentence;
                }
            }
            
            // Add the remaining paragraph
            if (!empty($current_paragraph)) {
                $formatted_parts[] = trim($current_paragraph);
            }
        }
        
        return implode("\n\n", $formatted_parts);
    }
    
    /**
     * Extract variations from Amazon product page
     */
    public function extract_variations($url) {
        $html = $this->get_page_content($url);
        
        if (is_wp_error($html)) {
            error_log('Amazon Importer: Failed to get page content for variations: ' . $html->get_error_message());
            return array();
        }
        
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        
        $variations = array();
        
        // Try different variation selectors
        $variation_selectors = array(
            // Color variations
            '//div[@id="variation_color_name"]//img',
            '//div[@id="variation_color_name"]//span',
            '//li[@data-defaultasin]',
            '//span[@data-action="main-image-click"][@data-asin]',
            
            // Size variations  
            '//div[@id="variation_size_name"]//span',
            '//select[@id="native_dropdown_selected_size_name"]//option[@value!=""]',
            
            // Style variations
            '//div[@id="variation_style_name"]//span',
            
            // General variation elements
            '//div[contains(@class, "a-section")]//span[contains(@class, "selection")][@data-asin]',
            '//div[@data-asin]',
            '//ul[contains(@class, "a-unordered-list")]//li[contains(@class, "swatchElement")][@data-asin]',
            '//div[contains(@class, "twister")]//span[@data-asin]',
            
            // Image-based variations
            '//div[contains(@class, "imageSwatches")]//span[@data-asin]',
            '//div[contains(@class, "variationImages")]//img[@data-asin]'
        );
        
        foreach ($variation_selectors as $selector) {
            $nodes = $xpath->query($selector);
            
            foreach ($nodes as $node) {
                $variation = $this->extract_single_variation($node, $xpath, $url);
                if (!empty($variation) && !empty($variation['asin'])) {
                    $variations[] = $variation;
                }
            }
            
            // If we found variations with one selector, stop trying others
            if (!empty($variations)) {
                break;
            }
        }
        
        // Remove duplicates based on ASIN
        $variations = $this->deduplicate_variations($variations);
        
        error_log('Amazon Importer: Found ' . count($variations) . ' variations for URL: ' . $url);
        
        return $variations;
    }
    
    /**
     * Extract single variation data
     */
    private function extract_single_variation($node, $xpath, $base_url) {
        $variation = array();
        
        // Try to get ASIN
        $asin = $node->getAttribute('data-asin') ?: 
                $node->getAttribute('data-defaultasin') ?: 
                $node->getAttribute('value');
        
        if (empty($asin)) {
            // Look for ASIN in parent elements
            $parent = $node->parentNode;
            while ($parent && empty($asin)) {
                if ($parent->hasAttribute('data-asin')) {
                    $asin = $parent->getAttribute('data-asin');
                } elseif ($parent->hasAttribute('data-defaultasin')) {
                    $asin = $parent->getAttribute('data-defaultasin');
                }
                $parent = $parent->parentNode;
            }
        }
        
        if (empty($asin)) {
            return array(); // No ASIN found, skip this variation
        }
        
        $variation['asin'] = $asin;
        
        // Try to get variation title/name
        $title = '';
        if ($node->hasAttribute('title')) {
            $title = trim($node->getAttribute('title'));
        } elseif ($node->hasAttribute('alt')) {
            $title = trim($node->getAttribute('alt'));
        } elseif ($node->nodeValue) {
            $title = trim($node->nodeValue);
        }
        
        if (!empty($title)) {
            $variation['title_suffix'] = $title;
        }
        
        // Try to get variation image
        if ($node->tagName === 'img') {
            $image_url = $node->getAttribute('src') ?: $node->getAttribute('data-src');
            if ($image_url) {
                $variation['image'] = $this->convert_to_high_res_image($image_url);
            }
        } else {
            // Look for img in children
            $img_nodes = $xpath->query('.//img', $node);
            if ($img_nodes->length > 0) {
                $img_node = $img_nodes->item(0);
                $image_url = $img_node->getAttribute('src') ?: $img_node->getAttribute('data-src');
                if ($image_url) {
                    $variation['image'] = $this->convert_to_high_res_image($image_url);
                }
            }
        }
        
        // Try to get price (look for price in nearby elements)
        $price = $this->extract_variation_price($node, $xpath);
        if ($price) {
            $variation['price'] = $price;
        }
        
        // Build variation URL
        $parsed_url = parse_url($base_url);
        $host = $parsed_url['host'];
        $scheme = $parsed_url['scheme'] ?? 'https';
        
        // Extract affiliate tag from base URL
        $tag = '';
        if (!empty($parsed_url['query'])) {
            parse_str($parsed_url['query'], $query_params);
            if (!empty($query_params['tag'])) {
                $tag = $query_params['tag'];
            }
        }
        
        $variation_url = $scheme . '://' . $host . '/dp/' . $asin;
        if ($tag) {
            $variation_url .= '?tag=' . $tag;
        }
        
        $variation['url'] = $variation_url;
        
        // Extract attributes (size, color, etc.)
        $attributes = $this->extract_variation_attributes($node, $xpath);
        if (!empty($attributes)) {
            $variation['attributes'] = $attributes;
        }
        
        return $variation;
    }
    
    /**
     * Extract variation price
     */
    private function extract_variation_price($node, $xpath) {
        // Look for price in various locations relative to the variation node
        $price_selectors = array(
            './/span[contains(@class, "a-price-whole")]',
            './/span[contains(@class, "a-price")]',
            '..//span[contains(@class, "a-price")]',
            '../..//span[contains(@class, "a-price")]',
            './/span[contains(@class, "price")]'
        );
        
        foreach ($price_selectors as $selector) {
            $price_nodes = $xpath->query($selector, $node);
            if ($price_nodes->length > 0) {
                $price_text = trim($price_nodes->item(0)->nodeValue);
                $price = $this->extract_price_from_text($price_text);
                if ($price) {
                    return $price;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Extract price from text
     */
    private function extract_price_from_text($text) {
        // Remove currency symbols and extract numeric value
        $text = preg_replace('/[^\d.,]/', '', $text);
        $text = str_replace(',', '', $text);
        
        if (is_numeric($text)) {
            return floatval($text);
        }
        
        return null;
    }
    
    /**
     * Extract variation attributes (color, size, etc.)
     */
    private function extract_variation_attributes($node, $xpath) {
        $attributes = array();
        
        // Check for common attribute patterns in data attributes
        if ($node->hasAttributes()) {
            foreach ($node->attributes as $attr) {
                $attr_name = strtolower($attr->name);
                $attr_value = trim($attr->value);
                
                if (empty($attr_value)) continue;
                
                // Map common attribute patterns
                if (strpos($attr_name, 'color') !== false) {
                    $attributes['Color'] = $attr_value;
                } elseif (strpos($attr_name, 'size') !== false) {
                    $attributes['Size'] = $attr_value;
                } elseif (strpos($attr_name, 'style') !== false) {
                    $attributes['Style'] = $attr_value;
                } elseif (strpos($attr_name, 'pattern') !== false) {
                    $attributes['Pattern'] = $attr_value;
                } elseif (strpos($attr_name, 'material') !== false) {
                    $attributes['Material'] = $attr_value;
                }
            }
        }
        
        // Look for text content that might indicate attributes
        if ($node->nodeValue) {
            $text = trim($node->nodeValue);
            
            // Common patterns for size/color in text
            if (preg_match('/Size:\s*([^,\n]+)/i', $text, $matches)) {
                $attributes['Size'] = trim($matches[1]);
            }
            if (preg_match('/Color:\s*([^,\n]+)/i', $text, $matches)) {
                $attributes['Color'] = trim($matches[1]);
            }
            if (preg_match('/Style:\s*([^,\n]+)/i', $text, $matches)) {
                $attributes['Style'] = trim($matches[1]);
            }
            
            // If no specific attribute found but text is short, assume it's a variation value
            if (empty($attributes) && strlen($text) < 50 && !empty($text)) {
                // Try to guess attribute type based on parent element ID
                $parent_id = '';
                $parent = $node->parentNode;
                while ($parent && empty($parent_id)) {
                    if ($parent->hasAttribute('id')) {
                        $parent_id = $parent->getAttribute('id');
                        break;
                    }
                    $parent = $parent->parentNode;
                }
                
                if (strpos($parent_id, 'color') !== false) {
                    $attributes['Color'] = $text;
                } elseif (strpos($parent_id, 'size') !== false) {
                    $attributes['Size'] = $text;
                } elseif (strpos($parent_id, 'style') !== false) {
                    $attributes['Style'] = $text;
                } else {
                    // Default to 'Option' if we can't determine the type
                    $attributes['Option'] = $text;
                }
            }
        }
        
        return $attributes;
    }
    
    /**
     * Remove duplicate variations
     */
    private function deduplicate_variations($variations) {
        $unique_variations = array();
        $seen_asins = array();
        
        foreach ($variations as $variation) {
            if (!empty($variation['asin']) && !in_array($variation['asin'], $seen_asins)) {
                $seen_asins[] = $variation['asin'];
                $unique_variations[] = $variation;
            }
        }
        
        return $unique_variations;
    }
}
