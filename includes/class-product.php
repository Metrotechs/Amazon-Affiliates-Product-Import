<?php
/**
 * Product management for Amazon Affiliate Product Importer
 */

if (!defined('ABSPATH')) {
    exit;
}

class AmazonAffiliateImporter_Product {
    
    public function __construct() {
        // Hook to add external product URL field
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_external_url_field'));
        add_action('woocommerce_process_product_meta', array($this, 'save_external_url_field'));
        
        // Ensure Amazon affiliate buttons open in new tab
        add_filter('woocommerce_loop_add_to_cart_link', array($this, 'add_target_blank_to_amazon_buttons'), 10, 2);
        add_action('woocommerce_single_product_summary', array($this, 'add_amazon_button_script'), 25);
        
        // Additional hooks for single product pages
        add_action('woocommerce_external_add_to_cart', array($this, 'add_amazon_button_script'), 5);
        add_action('wp_footer', array($this, 'add_product_page_amazon_script'));
        
        // Direct filter for external product button HTML
        add_filter('woocommerce_external_add_to_cart_text', array($this, 'modify_external_button_text'), 10);
        add_filter('woocommerce_product_add_to_cart_text', array($this, 'modify_add_to_cart_text'), 10, 2);
        
        // Global Amazon link target="_blank" handler
        add_action('wp_footer', array($this, 'add_global_amazon_link_script'));
        
        // Video display hooks
        add_action('woocommerce_single_product_summary', array($this, 'display_amazon_videos'), 25);
        add_action('woocommerce_product_tabs', array($this, 'add_videos_tab'), 98);
    }
    
    /**
     * Import a product from Amazon URL
     */
    public function import_product($amazon_url, $options = array()) {
        // Validate Amazon URL
        if (!$this->is_valid_amazon_url($amazon_url)) {
            return new WP_Error('invalid_url', __('Please provide a valid Amazon product URL.', 'amazon-affiliate-importer'));
        }
        
        // Extract ASIN from URL
        $asin = $this->extract_asin($amazon_url);
        if (!$asin) {
            return new WP_Error('no_asin', __('Could not extract product ASIN from URL.', 'amazon-affiliate-importer'));
        }
        
        // Check if product already imported
        $existing_product = $this->get_product_by_asin($asin);
        if ($existing_product) {
            return new WP_Error('already_imported', __('This product has already been imported.', 'amazon-affiliate-importer'));
        }
        
        // Extract affiliate tag
        $affiliate_tag = $this->extract_affiliate_tag($amazon_url);
        
        // Scrape product data
        $scraper = new AmazonAffiliateImporter_Scraper();
        $product_data = $scraper->scrape_product($amazon_url);
        
        if (is_wp_error($product_data)) {
            return $product_data;
        }
        
        // Check for variations
        $variations_data = $scraper->extract_variations($amazon_url);
        
        // Handle category extraction and assignment
        $category_ids = $this->handle_categories($amazon_url, $options);
        
        // Create WooCommerce product (variable if variations exist, external if not)
        if (!empty($variations_data) && count($variations_data) > 1) {
            $product_id = $this->create_amazon_variable_product($product_data, $variations_data, $amazon_url, $options, $category_ids);
        } else {
            $product_id = $this->create_woocommerce_product($product_data, $amazon_url, $options, $category_ids);
        }
        
        if (is_wp_error($product_id)) {
            return $product_id;
        }
        
        // Save to tracking table
        $this->save_imported_product($product_id, $amazon_url, $asin, $affiliate_tag);
        
        return $product_id;
    }
    
    /**
     * Handle category extraction and assignment
     */
    private function handle_categories($amazon_url, $options) {
        $category_ids = array();
        
        // Get category handling preference
        $category_handling = isset($options['category_handling']) ? $options['category_handling'] : 'manual';
        
        // Handle category extraction from Amazon
        if ($category_handling === 'extract' && isset($options['use_extracted_categories']) && $options['use_extracted_categories']) {
            // Use pre-extracted categories if provided
            if (isset($options['extracted_categories']) && !empty($options['extracted_categories'])) {
                try {
                    $category_manager = new AmazonAffiliateImporter_Categories();
                    $category_ids = $category_manager->create_category_hierarchy($options['extracted_categories']);
                } catch (Exception $e) {
                    error_log('Amazon Importer: Category creation failed - ' . $e->getMessage());
                }
            } else {
                // Extract categories from Amazon page directly
                try {
                    $scraper = new AmazonAffiliateImporter_Scraper();
                    $html = $scraper->get_page_content($amazon_url);
                    
                    if (!is_wp_error($html)) {
                        $category_manager = new AmazonAffiliateImporter_Categories();
                        $extracted_categories = $category_manager->extract_categories_from_html($html);
                        
                        if (!empty($extracted_categories)) {
                            $category_ids = $category_manager->create_category_hierarchy($extracted_categories);
                        }
                    }
                } catch (Exception $e) {
                    error_log('Amazon Importer: Category extraction failed - ' . $e->getMessage());
                }
            }
        }
        
        // Fallback to manual category selection
        if (empty($category_ids) && isset($options['category_id']) && $options['category_id']) {
            $category_ids[] = $options['category_id'];
        }
        
        return array_unique(array_filter($category_ids));
    }
    
    /**
     * Validate Amazon URL
     */
    private function is_valid_amazon_url($url) {
        $allowed_domains = array(
            'amazon.com', 'amazon.co.uk', 'amazon.de', 'amazon.fr', 'amazon.it',
            'amazon.es', 'amazon.ca', 'amazon.com.au', 'amazon.co.jp', 'amazon.in',
            'amzn.to', 'amzn.com'
        );
        
        $parsed_url = parse_url($url);
        if (!$parsed_url || !isset($parsed_url['host'])) {
            return false;
        }
        
        $host = strtolower($parsed_url['host']);
        $host = preg_replace('/^www\./', '', $host);
        
        return in_array($host, $allowed_domains);
    }
    
    /**
     * Extract ASIN from Amazon URL
     */
    private function extract_asin($url) {
        // Common ASIN patterns
        $patterns = array(
            '/\/dp\/([A-Z0-9]{10})/',
            '/\/gp\/product\/([A-Z0-9]{10})/',
            '/\/product\/([A-Z0-9]{10})/',
            '/asin=([A-Z0-9]{10})/',
            '/\/([A-Z0-9]{10})(?:\/|$|\?|#)/'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        
        return false;
    }
    
    /**
     * Extract affiliate tag from URL
     */
    private function extract_affiliate_tag($url) {
        $parsed_url = parse_url($url);
        if (isset($parsed_url['query'])) {
            parse_str($parsed_url['query'], $query_params);
            
            // Common affiliate tag parameters
            $tag_params = array('tag', 'linkCode', 'ascsubtag');
            
            foreach ($tag_params as $param) {
                if (isset($query_params[$param])) {
                    return $query_params[$param];
                }
            }
        }
        
        return '';
    }
    
    /**
     * Check if product already imported by ASIN
     */
    private function get_product_by_asin($asin) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'amazon_affiliate_products';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE asin = %s",
            $asin
        ));
    }
    
    /**
     * Create WooCommerce product
     */
    private function create_woocommerce_product($product_data, $amazon_url, $options, $category_ids = array()) {
        // Create external product
        $product = new WC_Product_External();
        
        // Set basic product data
        $product->set_name($product_data['title']);
        $product->set_description($this->format_product_description($product_data['description'], $product_data['features']));
        $product->set_short_description($this->format_short_description($product_data['short_description']));
        $product->set_regular_price($product_data['price']);
        $product->set_button_text(__('Buy on Amazon', 'amazon-affiliate-importer'));
        $product->set_product_url($amazon_url);
        
        // Set status
        $status = isset($options['status']) ? $options['status'] : 'draft';
        $product->set_status($status);
        
        // Set categories (use provided category_ids or fallback to manual selection)
        $categories_to_set = !empty($category_ids) ? $category_ids : array();
        if (empty($categories_to_set) && isset($options['category_id']) && $options['category_id']) {
            $categories_to_set = array($options['category_id']);
        }
        
        if (!empty($categories_to_set)) {
            $product->set_category_ids($categories_to_set);
        }
        
        // Save product
        $product_id = $product->save();
        
        if (!$product_id) {
            return new WP_Error('create_failed', __('Failed to create product.', 'amazon-affiliate-importer'));
        }
        
        // Import images if requested
        if (isset($options['import_images']) && $options['import_images'] && !empty($product_data['images'])) {
            $this->import_product_images($product_id, $product_data['images']);
        }
        
        // Add custom meta
        update_post_meta($product_id, '_amazon_asin', $product_data['asin']);
        update_post_meta($product_id, '_amazon_url', $amazon_url);
        update_post_meta($product_id, '_imported_from_amazon', true);
        
        // Store category extraction info
        if (!empty($category_ids)) {
            update_post_meta($product_id, '_amazon_extracted_categories', $category_ids);
        }
        
        // Save rating data if available
        if (isset($product_data['rating']) || isset($product_data['review_count'])) {
            $rating = isset($product_data['rating']) ? $product_data['rating'] : null;
            $count = isset($product_data['review_count']) ? $product_data['review_count'] : null;
            
            // Build reviews URL for this product
            $reviews_url = $this->build_amazon_reviews_url($amazon_url, $product_data['asin']);
            
            update_post_meta($product_id, '_amazon_rating', $rating);
            update_post_meta($product_id, '_amazon_review_count', $count);
            update_post_meta($product_id, '_amazon_reviews_url', $reviews_url);
        }
        
        // Save video data if available
        if (isset($product_data['videos']) && !empty($product_data['videos'])) {
            update_post_meta($product_id, '_amazon_videos', $product_data['videos']);
            update_post_meta($product_id, '_amazon_has_videos', true);
        }
        
        return $product_id;
    }
    
    /**
     * Create variable product with Amazon redirect functionality
     */
    private function create_amazon_variable_product($product_data, $variations_data, $amazon_url, $options, $category_ids = array()) {
        // Create variable product
        $product = new WC_Product_Variable();
        
        // Set basic product data
        $product->set_name($product_data['title']);
        $product->set_description($this->format_product_description($product_data['description'], $product_data['features']));
        $product->set_short_description($this->format_short_description($product_data['short_description']));
        
        // Set status
        $status = isset($options['status']) ? $options['status'] : 'draft';
        $product->set_status($status);
        
        // Set categories
        $categories_to_set = !empty($category_ids) ? $category_ids : array();
        if (empty($categories_to_set) && isset($options['category_id']) && $options['category_id']) {
            $categories_to_set = array($options['category_id']);
        }
        
        if (!empty($categories_to_set)) {
            $product->set_category_ids($categories_to_set);
        }
        
        // Save parent product first
        $product_id = $product->save();
        
        if (!$product_id) {
            return new WP_Error('create_failed', __('Failed to create variable product.', 'amazon-affiliate-importer'));
        }
        
        // Create attributes and variations
        $this->create_amazon_product_attributes($product_id, $variations_data);
        $this->create_amazon_product_variations($product_id, $variations_data, $amazon_url, $options);
        
        // Import images if requested
        if (isset($options['import_images']) && $options['import_images'] && !empty($product_data['images'])) {
            $this->import_product_images($product_id, $product_data['images']);
        }
        
        // Add custom meta
        update_post_meta($product_id, '_amazon_asin', $product_data['asin']);
        update_post_meta($product_id, '_amazon_url', $amazon_url);
        update_post_meta($product_id, '_imported_from_amazon', true);
        update_post_meta($product_id, '_amazon_has_variations', true);
        update_post_meta($product_id, '_amazon_is_variable', true);
        
        // Store category extraction info
        if (!empty($category_ids)) {
            update_post_meta($product_id, '_amazon_extracted_categories', $category_ids);
        }
        
        // Save rating data if available
        if (isset($product_data['rating']) || isset($product_data['review_count'])) {
            $rating = isset($product_data['rating']) ? $product_data['rating'] : null;
            $count = isset($product_data['review_count']) ? $product_data['review_count'] : null;
            $reviews_url = $this->build_amazon_reviews_url($amazon_url, $product_data['asin']);
            
            AmazonAffiliateImporter_Ratings::save_amazon_review_meta($product_id, $rating, $count, $reviews_url);
        }
        
        // Save video data if available
        if (isset($product_data['videos']) && !empty($product_data['videos'])) {
            update_post_meta($product_id, '_amazon_videos', $product_data['videos']);
            update_post_meta($product_id, '_amazon_has_videos', true);
        }
        
        // Add Amazon redirect functionality
        $this->add_amazon_variable_redirect_handlers();
        
        return $product_id;
    }
    
    /**
     * Build Amazon reviews URL from product URL and ASIN
     */
    private function build_amazon_reviews_url($amazon_url, $asin) {
        $parts = parse_url($amazon_url);
        if (!$parts || empty($parts['host']) || empty($asin)) {
            return '';
        }
        
        $host = $parts['host'];
        $scheme = isset($parts['scheme']) ? $parts['scheme'] : 'https';
        
        // Extract affiliate tag from original URL
        $tag = '';
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query_params);
            if (!empty($query_params['tag'])) {
                $tag = $query_params['tag'];
            }
        }
        
        // Build reviews URL
        $reviews_url = $scheme . '://' . $host . '/product-reviews/' . rawurlencode($asin) . '/?th=1&psc=1';
        if (!empty($tag)) {
            $reviews_url .= '&tag=' . rawurlencode($tag);
        }
        
        return $reviews_url;
    }
    
    /**
     * Import product images
     */
    private function import_product_images($product_id, $image_urls) {
        if (empty($image_urls)) {
            return;
        }
        
        $attachment_ids = array();
        
        foreach ($image_urls as $index => $image_url) {
            $attachment_id = $this->import_image($image_url, $product_id);
            
            if ($attachment_id && !is_wp_error($attachment_id)) {
                $attachment_ids[] = $attachment_id;
                
                // Set first image as featured image
                if ($index === 0) {
                    set_post_thumbnail($product_id, $attachment_id);
                }
            }
        }
        
        // Set gallery images
        if (!empty($attachment_ids)) {
            update_post_meta($product_id, '_product_image_gallery', implode(',', $attachment_ids));
        }
    }
    
    /**
     * Import single image
     */
    private function import_image($image_url, $product_id) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Download image
        $tmp = download_url($image_url);
        
        if (is_wp_error($tmp)) {
            return $tmp;
        }
        
        // Prepare file array
        $file_array = array(
            'name' => basename($image_url),
            'tmp_name' => $tmp
        );
        
        // Import image
        $attachment_id = media_handle_sideload($file_array, $product_id);
        
        // Clean up
        @unlink($tmp);
        
        return $attachment_id;
    }
    
    /**
     * Save imported product to tracking table
     */
    private function save_imported_product($product_id, $amazon_url, $asin, $affiliate_tag) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'amazon_affiliate_products';
        
        $wpdb->insert(
            $table_name,
            array(
                'amazon_url' => $amazon_url,
                'product_id' => $product_id,
                'asin' => $asin,
                'affiliate_tag' => $affiliate_tag,
                'imported_date' => current_time('mysql')
            ),
            array('%s', '%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * Add external URL field to product data
     */
    public function add_external_url_field() {
        global $post;
        
        $amazon_url = get_post_meta($post->ID, '_amazon_url', true);
        $is_amazon_import = get_post_meta($post->ID, '_imported_from_amazon', true);
        
        if ($is_amazon_import) {
            echo '<div class="options_group">';
            echo '<p class="form-field">';
            echo '<label>' . __('Amazon URL', 'amazon-affiliate-importer') . '</label>';
            echo '<span class="description">' . esc_html($amazon_url) . '</span>';
            echo '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Save external URL field (placeholder for future use)
     */
    public function save_external_url_field($post_id) {
        // Currently read-only, but can be extended for editing
    }
    
    /**
     * Add target="_blank" to Amazon affiliate product buttons in product loops
     */
    public function add_target_blank_to_amazon_buttons($link, $product) {
        if ($product->get_type() === 'external') {
            $external_url = $product->get_product_url();
            if ($this->is_amazon_url($external_url)) {
                // Add target="_blank" and proper rel attributes
                $link = str_replace('<a ', '<a target="_blank" rel="nofollow noopener sponsored" ', $link);
                
                // In case target was already present, make sure it's set to _blank
                if (strpos($link, 'target=') !== false && strpos($link, 'target="_blank"') === false) {
                    $link = preg_replace('/target="[^"]*"/', 'target="_blank"', $link);
                }
            }
        }
        return $link;
    }
    
    /**
     * Add JavaScript to ensure single product page Amazon button opens in new tab
     */
    public function add_amazon_button_script() {
        global $product;
        
        if ($product && $product->get_type() === 'external') {
            $external_url = $product->get_product_url();
            if ($this->is_amazon_url($external_url)) {
                echo '<script type="text/javascript">
                    jQuery(document).ready(function($) {
                        // Target multiple button selectors for better compatibility
                        var buttonSelectors = [
                            ".single_add_to_cart_button",
                            ".product_type_external",
                            "a[href*=\"amazon.\"]",
                            "a[href*=\"amzn.\"]",
                            ".cart .button",
                            ".summary .button[href*=\"amazon\"]"
                        ];
                        
                        var addTargetBlank = function() {
                            buttonSelectors.forEach(function(selector) {
                                $(selector).each(function() {
                                    var $button = $(this);
                                    var href = $button.attr("href");
                                    
                                    // Check if this is an Amazon link
                                    if (href && (href.includes("amazon.") || href.includes("amzn."))) {
                                        $button.attr("target", "_blank");
                                        $button.attr("rel", "nofollow noopener sponsored");
                                        console.log("Amazon button target=_blank added:", href);
                                    }
                                });
                            });
                        };
                        
                        // Apply immediately
                        addTargetBlank();
                        
                        // Apply after a delay for dynamic content
                        setTimeout(addTargetBlank, 500);
                        setTimeout(addTargetBlank, 1000);
                        
                        // Reapply when page content changes
                        $(document).on("DOMNodeInserted", function() {
                            setTimeout(addTargetBlank, 100);
                        });
                    });
                </script>';
            }
        }
    }
    
    /**
     * Check if a URL is an Amazon URL
     */
    private function is_amazon_url($url) {
        if (empty($url)) {
            return false;
        }
        return (strpos($url, 'amazon.') !== false || strpos($url, 'amzn.') !== false);
    }
    
    /**
     * Add Amazon link script specifically for product pages (more aggressive)
     */
    public function add_product_page_amazon_script() {
        // Only run on single product pages
        if (!is_product()) {
            return;
        }
        
        global $product;
        if (!$product || $product->get_type() !== 'external') {
            return;
        }
        
        $external_url = $product->get_product_url();
        if (!$this->is_amazon_url($external_url)) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            console.log('Amazon Product Page: Initializing target="_blank" for Amazon links');
            
            var productPageAmazonLinks = function() {
                // More comprehensive selectors for product pages
                var selectors = [
                    '.single_add_to_cart_button',
                    '.product_type_external', 
                    '.cart .button',
                    '.summary .button',
                    '.product .button',
                    'form.cart .button',
                    '.woocommerce-product-details__short-description a[href*="amazon"]',
                    '.product-summary a[href*="amazon"]',
                    'a[href*="amazon."]',
                    'a[href*="amzn."]'
                ];
                
                var linksUpdated = 0;
                
                selectors.forEach(function(selector) {
                    $(selector).each(function() {
                        var $link = $(this);
                        var href = $link.attr('href');
                        
                        if (href && (href.includes('amazon.') || href.includes('amzn.'))) {
                            // Force target="_blank" even if already set
                            $link.attr('target', '_blank');
                            $link.attr('rel', 'nofollow noopener sponsored');
                            
                            // Also handle click events as backup
                            $link.off('click.amazon-target').on('click.amazon-target', function(e) {
                                e.preventDefault();
                                window.open(href, '_blank', 'noopener,noreferrer');
                                return false;
                            });
                            
                            linksUpdated++;
                            console.log('Product page Amazon link updated:', href);
                        }
                    });
                });
                
                console.log('Amazon Product Page: Updated ' + linksUpdated + ' links');
                return linksUpdated;
            };
            
            // Apply immediately
            productPageAmazonLinks();
            
            // Apply with multiple delays
            setTimeout(productPageAmazonLinks, 100);
            setTimeout(productPageAmazonLinks, 500);
            setTimeout(productPageAmazonLinks, 1000);
            setTimeout(productPageAmazonLinks, 2000);
            
            // Apply when DOM changes
            var observer = new MutationObserver(function(mutations) {
                var shouldCheck = false;
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                        shouldCheck = true;
                    }
                });
                if (shouldCheck) {
                    setTimeout(productPageAmazonLinks, 100);
                }
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
            
            // Force check on any form interactions
            $('form.cart, .cart').on('change click', function() {
                setTimeout(productPageAmazonLinks, 100);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Modify external button text for Amazon products (not really changing text, but hooking in)
     */
    public function modify_external_button_text($text) {
        global $product;
        if ($product && $product->get_type() === 'external') {
            $external_url = $product->get_product_url();
            if ($this->is_amazon_url($external_url)) {
                // Use this opportunity to ensure our scripts run
                add_action('wp_footer', function() {
                    echo '<script>
                        jQuery(document).ready(function($) {
                            console.log("External button filter triggered - checking Amazon links");
                            $(".single_add_to_cart_button, .product_type_external").each(function() {
                                if ($(this).attr("href") && ($(this).attr("href").includes("amazon.") || $(this).attr("href").includes("amzn."))) {
                                    $(this).attr("target", "_blank").attr("rel", "nofollow noopener sponsored");
                                    console.log("Amazon link updated via external button filter");
                                }
                            });
                        });
                    </script>';
                }, 999);
            }
        }
        return $text;
    }
    
    /**
     * Modify add to cart text and ensure target="_blank"
     */
    public function modify_add_to_cart_text($text, $product) {
        if ($product && $product->get_type() === 'external') {
            $external_url = $product->get_product_url();
            if ($this->is_amazon_url($external_url)) {
                // Use this hook to inject our target="_blank" logic
                static $script_added = false;
                if (!$script_added) {
                    add_action('wp_footer', function() {
                        echo '<script>
                            jQuery(document).ready(function($) {
                                console.log("Add to cart text filter - ensuring Amazon target=_blank");
                                var ensureTarget = function() {
                                    $("a").each(function() {
                                        var href = $(this).attr("href");
                                        if (href && (href.includes("amazon.") || href.includes("amzn."))) {
                                            $(this).attr("target", "_blank").attr("rel", "nofollow noopener sponsored");
                                        }
                                    });
                                };
                                ensureTarget();
                                setTimeout(ensureTarget, 500);
                            });
                        </script>';
                    }, 998);
                    $script_added = true;
                }
            }
        }
        return $text;
    }
    
    /**
     * Format product description with proper HTML structure
     */
    private function format_product_description($description, $features) {
        if (empty($description) && empty($features)) {
            return '';
        }
        
        $formatted_description = '';
        
        // Format main description if available
        if (!empty($description)) {
            // Split description into paragraphs
            $paragraphs = explode("\n", $description);
            $paragraphs = array_filter($paragraphs, function($p) {
                return !empty(trim($p));
            });
            
            foreach ($paragraphs as $paragraph) {
                $formatted_description .= '<p>' . esc_html(trim($paragraph)) . '</p>' . "\n";
            }
        }
        
        // Add features as a formatted list if available
        if (!empty($features) && is_array($features)) {
            if (!empty($formatted_description)) {
                $formatted_description .= "\n";
            }
            
            $formatted_description .= '<h3>Key Features:</h3>' . "\n";
            $formatted_description .= '<ul>' . "\n";
            
            foreach ($features as $feature) {
                $feature = trim($feature);
                if (!empty($feature)) {
                    $formatted_description .= '<li>' . esc_html($feature) . '</li>' . "\n";
                }
            }
            
            $formatted_description .= '</ul>' . "\n";
        }
        
        return $formatted_description;
    }
    
    /**
     * Format short description with proper HTML structure
     */
    private function format_short_description($short_description) {
        if (empty($short_description)) {
            return '';
        }
        
        // If it contains bullet points, format as list
        if (strpos($short_description, '•') !== false) {
            $items = explode('•', $short_description);
            $items = array_filter(array_map('trim', $items));
            
            if (count($items) > 1) {
                $formatted = '<ul>';
                foreach ($items as $item) {
                    if (!empty($item)) {
                        $formatted .= '<li>' . esc_html($item) . '</li>';
                    }
                }
                $formatted .= '</ul>';
                return $formatted;
            }
        }
        
        // Split into lines and format as paragraphs
        $lines = explode("\n", $short_description);
        $lines = array_filter($lines, function($line) {
            return !empty(trim($line));
        });
        
        $formatted = '';
        foreach ($lines as $line) {
            $formatted .= '<p>' . esc_html(trim($line)) . '</p>' . "\n";
        }
        
        return $formatted;
    }
    
    /**
     * Add global script to ensure all Amazon links open in new tabs
     */
    public function add_global_amazon_link_script() {
        // Only add on frontend pages
        if (is_admin()) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Global function to add target="_blank" to all Amazon links
            var makeAmazonLinksOpenInNewTab = function() {
                $('a[href*="amazon."], a[href*="amzn."]').each(function() {
                    var $link = $(this);
                    var href = $link.attr('href');
                    
                    // Skip if already has target="_blank"
                    if ($link.attr('target') === '_blank') {
                        return;
                    }
                    
                    // Add target="_blank" and proper rel attributes for Amazon links
                    if (href && (href.includes('amazon.') || href.includes('amzn.'))) {
                        $link.attr('target', '_blank');
                        $link.attr('rel', 'nofollow noopener sponsored');
                        console.log('Global Amazon link updated:', href);
                    }
                });
            };
            
            // Apply immediately
            makeAmazonLinksOpenInNewTab();
            
            // Apply when new content is loaded (AJAX, etc.)
            $(document).on('DOMNodeInserted', function() {
                setTimeout(makeAmazonLinksOpenInNewTab, 100);
            });
            
            // Apply on WooCommerce events
            $('body').on('updated_wc_div', makeAmazonLinksOpenInNewTab);
            $('body').on('updated_cart_totals', makeAmazonLinksOpenInNewTab);
            $('body').on('updated_checkout', makeAmazonLinksOpenInNewTab);
        });
        </script>
        <?php
    }
    
    /**
     * Create product attributes for variations
     */
    private function create_amazon_product_attributes($product_id, $variations_data) {
        if (empty($variations_data)) {
            return;
        }
        
        $attributes = array();
        $attribute_names = array();
        
        // Extract all unique attribute names
        foreach ($variations_data as $variation) {
            if (isset($variation['attributes'])) {
                foreach ($variation['attributes'] as $attr_name => $attr_value) {
                    $attribute_names[] = $attr_name;
                }
            }
        }
        
        $attribute_names = array_unique($attribute_names);
        
        // Create attributes
        foreach ($attribute_names as $attr_name) {
            $attribute_values = array();
            
            // Collect all values for this attribute
            foreach ($variations_data as $variation) {
                if (isset($variation['attributes'][$attr_name])) {
                    $attribute_values[] = $variation['attributes'][$attr_name];
                }
            }
            
            $attribute_values = array_unique($attribute_values);
            
            // Create WooCommerce attribute
            $attribute = new WC_Product_Attribute();
            $attribute->set_name($attr_name);
            $attribute->set_options($attribute_values);
            $attribute->set_position(count($attributes));
            $attribute->set_visible(true);
            $attribute->set_variation(true);
            
            $attributes[] = $attribute;
        }
        
        // Save attributes to product
        $product = wc_get_product($product_id);
        $product->set_attributes($attributes);
        $product->save();
    }
    
    /**
     * Create product variations
     */
    private function create_amazon_product_variations($product_id, $variations_data, $base_amazon_url, $options) {
        if (empty($variations_data)) {
            return;
        }
        
        foreach ($variations_data as $variation_data) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product_id);
            
            // Set variation attributes
            if (isset($variation_data['attributes'])) {
                $variation->set_attributes($variation_data['attributes']);
            }
            
            // Set price
            if (isset($variation_data['price'])) {
                $variation->set_regular_price($variation_data['price']);
            }
            
            // Set Amazon URL for this specific variation
            $variation_url = isset($variation_data['url']) ? $variation_data['url'] : $base_amazon_url;
            $variation->update_meta_data('_amazon_variation_url', $variation_url);
            
            // Set variation description if available
            if (isset($variation_data['description'])) {
                $variation->set_description($variation_data['description']);
            }
            
            // Save variation
            $variation_id = $variation->save();
            
            // Import variation-specific image if available
            if (isset($options['import_images']) && $options['import_images'] && isset($variation_data['image'])) {
                $attachment_id = $this->import_image($variation_data['image'], $variation_id);
                if ($attachment_id && !is_wp_error($attachment_id)) {
                    set_post_thumbnail($variation_id, $attachment_id);
                }
            }
            
            // Store ASIN for this variation
            if (isset($variation_data['asin'])) {
                update_post_meta($variation_id, '_amazon_variation_asin', $variation_data['asin']);
            }
        }
        
        // Sync variable product
        if (class_exists('WC_Product_Variable')) {
            WC_Product_Variable::sync($product_id);
        }
    }
    
    /**
     * Add Amazon redirect functionality for variable products
     */
    private function add_amazon_variable_redirect_handlers() {
        // Add JavaScript for frontend variation handling
        add_action('wp_footer', array($this, 'add_amazon_variation_redirect_script'));
        
        // Add AJAX handler for getting variation URLs
        add_action('wp_ajax_get_amazon_variation_url', array($this, 'handle_amazon_variation_url_ajax'));
        add_action('wp_ajax_nopriv_get_amazon_variation_url', array($this, 'handle_amazon_variation_url_ajax'));
    }
    
    /**
     * Add JavaScript for Amazon variation redirects
     */
    public function add_amazon_variation_redirect_script() {
        if (!is_product()) {
            return;
        }
        
        global $product;
        if (!$product || $product->get_type() !== 'variable' || !get_post_meta($product->get_id(), '_amazon_is_variable', true)) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            console.log('Amazon Variable Product: Initializing variation redirect');
            
            // Override the add to cart form submission
            $('form.variations_form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var variation_id = $form.find('input[name="variation_id"]').val();
                
                if (!variation_id) {
                    alert('Please select all product options before adding to cart.');
                    return false;
                }
                
                // Get the Amazon URL for this variation via AJAX
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'get_amazon_variation_url',
                        variation_id: variation_id,
                        nonce: '<?php echo wp_create_nonce('amazon_variation_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success && response.data.url) {
                            console.log('Redirecting to Amazon variation:', response.data.url);
                            window.open(response.data.url, '_blank');
                        } else {
                            console.error('Failed to get Amazon URL:', response);
                            alert('Unable to redirect to Amazon. Please try again.');
                        }
                    },
                    error: function() {
                        console.error('AJAX error getting Amazon variation URL');
                        alert('Unable to redirect to Amazon. Please try again.');
                    }
                });
                
                return false;
            });
            
            // Also handle direct button clicks
            $('.single_add_to_cart_button').on('click', function(e) {
                e.preventDefault();
                $(this).closest('form').submit();
                return false;
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle AJAX request for Amazon variation URL
     */
    public function handle_amazon_variation_url_ajax() {
        check_ajax_referer('amazon_variation_nonce', 'nonce');
        
        $variation_id = intval($_POST['variation_id']);
        if (!$variation_id) {
            wp_send_json_error('Invalid variation ID');
        }
        
        $amazon_url = get_post_meta($variation_id, '_amazon_variation_url', true);
        if (!$amazon_url) {
            wp_send_json_error('No Amazon URL found for this variation');
        }
        
        wp_send_json_success(array('url' => $amazon_url));
    }
    
    /**
     * Display Amazon videos on product page
     */
    public function display_amazon_videos() {
        global $product;
        
        if (!$product || !is_a($product, 'WC_Product')) {
            return;
        }
        
        $videos = get_post_meta($product->get_id(), '_amazon_videos', true);
        $has_videos = get_post_meta($product->get_id(), '_amazon_has_videos', true);
        
        if (!$has_videos || empty($videos)) {
            return;
        }
        
        echo '<div class="amazon-product-videos">';
        echo '<h3>' . __('Product Videos', 'amazon-affiliate-importer') . '</h3>';
        
        foreach ($videos as $index => $video) {
            $this->render_video_player($video, $index);
        }
        
        echo '</div>';
        
        // Add CSS for video styling
        $this->add_video_styles();
    }
    
    /**
     * Add Videos tab to product tabs
     */
    public function add_videos_tab($tabs) {
        global $product;
        
        if (!$product || !is_a($product, 'WC_Product')) {
            return $tabs;
        }
        
        $has_videos = get_post_meta($product->get_id(), '_amazon_has_videos', true);
        
        if (!$has_videos) {
            return $tabs;
        }
        
        $tabs['amazon_videos'] = array(
            'title'    => __('Videos', 'amazon-affiliate-importer'),
            'priority' => 25,
            'callback' => array($this, 'videos_tab_content')
        );
        
        return $tabs;
    }
    
    /**
     * Content for the Videos tab
     */
    public function videos_tab_content() {
        global $product;
        
        $videos = get_post_meta($product->get_id(), '_amazon_videos', true);
        
        if (empty($videos)) {
            echo '<p>' . __('No videos available for this product.', 'amazon-affiliate-importer') . '</p>';
            return;
        }
        
        echo '<div class="amazon-videos-tab-content">';
        
        foreach ($videos as $index => $video) {
            echo '<div class="amazon-video-item">';
            $this->render_video_player($video, $index, true);
            echo '</div>';
        }
        
        echo '</div>';
        
        $this->add_video_styles();
    }
    
    /**
     * Render individual video player
     */
    private function render_video_player($video, $index, $is_tab = false) {
        $video_url = isset($video['url']) ? $video['url'] : '';
        $video_type = isset($video['type']) ? $video['type'] : 'unknown';
        $thumbnail = isset($video['thumbnail']) ? $video['thumbnail'] : '';
        
        if (empty($video_url)) {
            return;
        }
        
        $container_class = $is_tab ? 'amazon-video-tab-player' : 'amazon-video-summary-player';
        
        echo '<div class="amazon-video-container ' . esc_attr($container_class) . '" data-video-index="' . esc_attr($index) . '">';
        
        switch ($video_type) {
            case 'youtube':
                $this->render_youtube_video($video_url, $thumbnail, $index);
                break;
                
            case 'vimeo':
                $this->render_vimeo_video($video_url, $thumbnail, $index);
                break;
                
            case 'mp4':
            case 'webm':
            case 'amazon_video':
                $this->render_html5_video($video_url, $thumbnail, $index);
                break;
                
            default:
                $this->render_generic_video($video_url, $thumbnail, $index);
                break;
        }
        
        echo '</div>';
    }
    
    /**
     * Render YouTube video
     */
    private function render_youtube_video($url, $thumbnail, $index) {
        // Extract YouTube video ID
        preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\s]+)/', $url, $matches);
        $video_id = isset($matches[1]) ? $matches[1] : '';
        
        if ($video_id) {
            $embed_url = 'https://www.youtube.com/embed/' . $video_id;
            echo '<iframe width="560" height="315" src="' . esc_url($embed_url) . '" ';
            echo 'frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" ';
            echo 'allowfullscreen></iframe>';
        } else {
            $this->render_video_link($url, $thumbnail, $index);
        }
    }
    
    /**
     * Render Vimeo video
     */
    private function render_vimeo_video($url, $thumbnail, $index) {
        // Extract Vimeo video ID
        preg_match('/vimeo\.com\/(\d+)/', $url, $matches);
        $video_id = isset($matches[1]) ? $matches[1] : '';
        
        if ($video_id) {
            $embed_url = 'https://player.vimeo.com/video/' . $video_id;
            echo '<iframe src="' . esc_url($embed_url) . '" width="560" height="315" ';
            echo 'frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
        } else {
            $this->render_video_link($url, $thumbnail, $index);
        }
    }
    
    /**
     * Render HTML5 video
     */
    private function render_html5_video($url, $thumbnail, $index) {
        echo '<video width="560" height="315" controls';
        if ($thumbnail) {
            echo ' poster="' . esc_url($thumbnail) . '"';
        }
        echo '>';
        echo '<source src="' . esc_url($url) . '" type="video/mp4">';
        echo '<p>' . __('Your browser does not support the video tag.', 'amazon-affiliate-importer') . '</p>';
        echo '</video>';
    }
    
    /**
     * Render generic video (as link)
     */
    private function render_generic_video($url, $thumbnail, $index) {
        $this->render_video_link($url, $thumbnail, $index);
    }
    
    /**
     * Render video as clickable link
     */
    private function render_video_link($url, $thumbnail, $index) {
        echo '<div class="amazon-video-link">';
        
        if ($thumbnail) {
            echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">';
            echo '<img src="' . esc_url($thumbnail) . '" alt="' . __('Product Video', 'amazon-affiliate-importer') . '" style="max-width: 100%; height: auto;">';
            echo '<div class="video-play-overlay">▶</div>';
            echo '</a>';
        } else {
            echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener" class="amazon-video-text-link">';
            echo '🎥 ' . __('Watch Product Video', 'amazon-affiliate-importer') . ' ' . ($index + 1);
            echo '</a>';
        }
        
        echo '</div>';
    }
    
    /**
     * Add CSS styles for video display
     */
    private function add_video_styles() {
        static $styles_added = false;
        
        if ($styles_added) {
            return;
        }
        
        $styles_added = true;
        
        echo '<style>
        .amazon-product-videos {
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: #f9f9f9;
        }
        
        .amazon-product-videos h3 {
            margin-top: 0;
            color: #333;
            font-size: 1.2em;
        }
        
        .amazon-video-container {
            margin: 15px 0;
            text-align: center;
        }
        
        .amazon-video-container iframe,
        .amazon-video-container video {
            max-width: 100%;
            height: auto;
        }
        
        .amazon-video-link {
            position: relative;
            display: inline-block;
            margin: 10px 0;
        }
        
        .amazon-video-link img {
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .video-play-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 15px 20px;
            border-radius: 50%;
            font-size: 24px;
            pointer-events: none;
        }
        
        .amazon-video-text-link {
            display: inline-block;
            padding: 10px 20px;
            background: #ff9800;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        
        .amazon-video-text-link:hover {
            background: #f57c00;
            color: white;
        }
        
        .amazon-videos-tab-content .amazon-video-item {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .amazon-videos-tab-content .amazon-video-item:last-child {
            border-bottom: none;
        }
        
        @media (max-width: 768px) {
            .amazon-video-container iframe,
            .amazon-video-container video {
                width: 100%;
                height: 200px;
            }
        }
        </style>';
    }
}
