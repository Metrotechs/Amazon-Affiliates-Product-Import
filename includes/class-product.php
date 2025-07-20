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
        
        // Handle category extraction and assignment
        $category_ids = $this->handle_categories($amazon_url, $options);
        
        // Create WooCommerce product
        $product_id = $this->create_woocommerce_product($product_data, $amazon_url, $options, $category_ids);
        
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
        $product->set_description($product_data['description']);
        $product->set_short_description($product_data['short_description']);
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
        
        return $product_id;
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
}
