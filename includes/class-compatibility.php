<?php
/**
 * WooCommerce Compatibility for Amazon Affiliate Product Importer
 */

if (!defined('ABSPATH')) {
    exit;
}

class AmazonAffiliateImporter_Compatibility {
    
    public function __construct() {
        add_action('before_woocommerce_init', array($this, 'declare_woocommerce_compatibility'));
        add_action('woocommerce_loaded', array($this, 'init_woocommerce_integration'));
        add_filter('woocommerce_integrations', array($this, 'add_integration'));
    }
    
    /**
     * Declare compatibility with WooCommerce features
     */
    public function declare_woocommerce_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            // Declare HPOS compatibility
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', AMAZON_AFFILIATE_IMPORTER_PLUGIN_DIR . 'amazon-affiliate-importer.php', true);
            
            // Declare cart/checkout blocks compatibility
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', AMAZON_AFFILIATE_IMPORTER_PLUGIN_DIR . 'amazon-affiliate-importer.php', true);
        }
    }
    
    /**
     * Initialize WooCommerce-specific integrations
     */
    public function init_woocommerce_integration() {
        // Add compatibility with WooCommerce product search
        add_filter('woocommerce_product_data_store_cpt_get_products_query', array($this, 'handle_custom_query_vars'), 10, 2);
        
        // Add Amazon-specific product data to REST API
        add_action('woocommerce_rest_prepare_product_object', array($this, 'add_amazon_data_to_api'), 10, 3);
        
        // Add Amazon product admin columns
        add_filter('manage_edit-product_columns', array($this, 'add_product_columns'));
        add_action('manage_product_posts_custom_column', array($this, 'render_product_columns'), 10, 2);
        
        // Add Amazon-specific product filters
        add_action('restrict_manage_posts', array($this, 'add_product_filters'));
        add_filter('parse_query', array($this, 'parse_product_filters'));
        
        // Enhance product search to include ASIN
        add_filter('woocommerce_shop_order_search_fields', array($this, 'add_asin_to_search_fields'));
    }
    
    /**
     * Add integration to WooCommerce integrations list
     */
    public function add_integration($integrations) {
        $integrations[] = 'AmazonAffiliateImporter_Integration';
        return $integrations;
    }
    
    /**
     * Handle custom query variables for WooCommerce product queries
     */
    public function handle_custom_query_vars($query, $query_vars) {
        if (!empty($query_vars['amazon_imported'])) {
            $query['meta_query'][] = array(
                'key' => '_imported_from_amazon',
                'value' => true,
                'compare' => '='
            );
        }
        
        if (!empty($query_vars['amazon_asin'])) {
            $query['meta_query'][] = array(
                'key' => '_amazon_asin',
                'value' => sanitize_text_field($query_vars['amazon_asin']),
                'compare' => '='
            );
        }
        
        return $query;
    }
    
    /**
     * Add Amazon data to WooCommerce REST API responses
     */
    public function add_amazon_data_to_api($response, $object, $request) {
        if ($object instanceof WC_Product) {
            $product_id = $object->get_id();
            $amazon_data = array();
            
            $asin = get_post_meta($product_id, '_amazon_asin', true);
            if ($asin) {
                $amazon_data['asin'] = $asin;
            }
            
            $amazon_url = get_post_meta($product_id, '_amazon_url', true);
            if ($amazon_url) {
                $amazon_data['amazon_url'] = $amazon_url;
            }
            
            $imported = get_post_meta($product_id, '_imported_from_amazon', true);
            if ($imported) {
                $amazon_data['imported_from_amazon'] = true;
                $amazon_data['import_date'] = get_post_meta($product_id, '_amazon_import_date', true);
            }
            
            $rating = get_post_meta($product_id, '_amazon_rating', true);
            if ($rating) {
                $amazon_data['amazon_rating'] = $rating;
                $amazon_data['amazon_review_count'] = get_post_meta($product_id, '_amazon_review_count', true);
                $amazon_data['amazon_reviews_url'] = get_post_meta($product_id, '_amazon_reviews_url', true);
            }
            
            if (!empty($amazon_data)) {
                $response->data['amazon_data'] = $amazon_data;
            }
        }
        
        return $response;
    }
    
    /**
     * Add Amazon-specific columns to product admin list
     */
    public function add_product_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // Add ASIN column after the name column
            if ($key === 'name') {
                $new_columns['amazon_asin'] = __('Amazon ASIN', 'amazon-affiliate-importer');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Render Amazon-specific columns content
     */
    public function render_product_columns($column, $post_id) {
        if ($column === 'amazon_asin') {
            $asin = get_post_meta($post_id, '_amazon_asin', true);
            $imported = get_post_meta($post_id, '_imported_from_amazon', true);
            
            if ($imported && $asin) {
                echo '<code>' . esc_html($asin) . '</code>';
                echo '<br><small>' . __('Amazon Import', 'amazon-affiliate-importer') . '</small>';
            } else {
                echo '—';
            }
        }
    }
    
    /**
     * Add Amazon-specific product filters to admin
     */
    public function add_product_filters() {
        global $typenow;
        
        if ($typenow === 'product') {
            $selected = isset($_GET['amazon_filter']) ? $_GET['amazon_filter'] : '';
            ?>
            <select name="amazon_filter" id="amazon_filter">
                <option value=""><?php _e('All Products', 'amazon-affiliate-importer'); ?></option>
                <option value="amazon_imported" <?php selected($selected, 'amazon_imported'); ?>>
                    <?php _e('Amazon Imported', 'amazon-affiliate-importer'); ?>
                </option>
                <option value="non_amazon" <?php selected($selected, 'non_amazon'); ?>>
                    <?php _e('Non-Amazon Products', 'amazon-affiliate-importer'); ?>
                </option>
            </select>
            <?php
        }
    }
    
    /**
     * Parse Amazon-specific product filters
     */
    public function parse_product_filters($query) {
        global $pagenow, $typenow;
        
        if ($pagenow === 'edit.php' && $typenow === 'product' && isset($_GET['amazon_filter'])) {
            $filter = $_GET['amazon_filter'];
            
            if ($filter === 'amazon_imported') {
                $query->query_vars['meta_query'] = array(
                    array(
                        'key' => '_imported_from_amazon',
                        'value' => true,
                        'compare' => '='
                    )
                );
            } elseif ($filter === 'non_amazon') {
                $query->query_vars['meta_query'] = array(
                    array(
                        'key' => '_imported_from_amazon',
                        'compare' => 'NOT EXISTS'
                    )
                );
            }
        }
    }
    
    /**
     * Add ASIN to WooCommerce search fields
     */
    public function add_asin_to_search_fields($search_fields) {
        $search_fields[] = '_amazon_asin';
        return $search_fields;
    }
    
    /**
     * Check if current WooCommerce version is compatible
     */
    public static function is_woocommerce_version_compatible() {
        if (!defined('WC_VERSION')) {
            return false;
        }
        
        return version_compare(WC_VERSION, '6.0', '>=');
    }
    
    /**
     * Get WooCommerce compatibility status
     */
    public static function get_compatibility_status() {
        $status = array(
            'woocommerce_active' => class_exists('WooCommerce'),
            'version_compatible' => self::is_woocommerce_version_compatible(),
            'hpos_compatible' => true,
            'blocks_compatible' => true
        );
        
        if (defined('WC_VERSION')) {
            $status['woocommerce_version'] = WC_VERSION;
        }
        
        return $status;
    }
}

// Initialize compatibility
new AmazonAffiliateImporter_Compatibility();
