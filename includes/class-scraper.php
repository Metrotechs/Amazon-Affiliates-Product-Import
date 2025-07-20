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
            $short_description = implode("\n", array_slice($features, 0, 3));
        }
        
        // Extract detailed description (avoid CSS/JS content)
        $description_selectors = array(
            '//div[@id="productDescription"]//p',
            '//div[@id="aplus"]//p',
            '//div[contains(@class, "a-section a-spacing-small")]//p[not(contains(@class, "aplus-v2"))]'
        );
        
        foreach ($description_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $desc_parts = array();
                foreach ($nodes as $node) {
                    $text = $this->clean_text($node->textContent);
                    if (!empty($text) && 
                        !$this->is_unwanted_content($text) && 
                        strlen($text) > 20) {
                        $desc_parts[] = $text;
                    }
                }
                if (!empty($desc_parts)) {
                    $description = implode("\n\n", $desc_parts);
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
        
        // Remove common unwanted characters and sequences
        $text = preg_replace('/[^\x20-\x7E\x0A\x0D]/', '', $text); // Keep only printable ASCII + newlines
        $text = str_replace(array("\r\n", "\r"), "\n", $text);
        $text = preg_replace('/\n+/', "\n", $text);
        
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
        
        // Try different image selectors
        $image_selectors = array(
            '//div[@id="imageBlock"]//img/@src',
            '//div[@id="imgTagWrapperId"]//img/@src',
            '//div[contains(@class, "imgTagWrapper")]//img/@src',
            '//img[@id="landingImage"]/@src'
        );
        
        foreach ($image_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                foreach ($nodes as $node) {
                    $image_url = $node->textContent;
                    // Convert to high resolution image
                    $image_url = $this->convert_to_high_res_image($image_url);
                    if (!in_array($image_url, $images)) {
                        $images[] = $image_url;
                    }
                }
            }
        }
        
        // Also try to extract from JSON data
        $json_images = $this->extract_images_from_json($xpath);
        $images = array_merge($images, $json_images);
        
        return array_unique($images);
    }
    
    /**
     * Convert Amazon image URL to high resolution
     */
    private function convert_to_high_res_image($image_url) {
        // Remove size restrictions from Amazon image URLs
        $image_url = preg_replace('/\._[A-Z0-9,_]+_\./', '.', $image_url);
        return $image_url;
    }
    
    /**
     * Extract images from JSON data in page
     */
    private function extract_images_from_json($xpath) {
        $images = array();
        
        // Look for JSON data containing images
        $script_nodes = $xpath->query('//script[contains(text(), "ImageBlockATF") or contains(text(), "colorImages")]');
        
        foreach ($script_nodes as $script) {
            $content = $script->textContent;
            
            // Extract image URLs from JSON
            if (preg_match_all('/"large":"([^"]+)"/', $content, $matches)) {
                foreach ($matches[1] as $url) {
                    $url = str_replace('\/', '/', $url);
                    if (!in_array($url, $images)) {
                        $images[] = $url;
                    }
                }
            }
            
            if (preg_match_all('/"hiRes":"([^"]+)"/', $content, $matches)) {
                foreach ($matches[1] as $url) {
                    $url = str_replace('\/', '/', $url);
                    if (!in_array($url, $images)) {
                        $images[] = $url;
                    }
                }
            }
        }
        
        return $images;
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
}
