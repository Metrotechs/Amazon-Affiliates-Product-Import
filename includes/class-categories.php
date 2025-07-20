<?php
/**
 * Category management for Amazon Affiliate Product Importer
 */

if (!defined('ABSPATH')) {
    exit;
}

class AmazonAffiliateImporter_Categories {
    
    private $category_cache = array();
    
    public function __construct() {
        add_action('wp_ajax_amazon_import_categories', array($this, 'ajax_import_categories'));
        add_action('wp_ajax_amazon_fix_categories', array($this, 'ajax_fix_categories'));
        add_action('wp_ajax_amazon_get_category_tree', array($this, 'ajax_get_category_tree'));
        add_action('wp_ajax_amazon_scan_categories', array($this, 'ajax_scan_categories'));
        add_action('wp_ajax_amazon_merge_categories', array($this, 'ajax_merge_categories'));
    }
    
    /**
     * Extract categories from Amazon page
     */
    public function extract_categories_from_html($html) {
        $categories = array();
        
        // Create DOMDocument to parse HTML
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new DOMXPath($dom);
        
        // Try different category breadcrumb selectors
        $category_selectors = array(
            '//div[@id="wayfinding-breadcrumbs_feature_div"]//a',
            '//div[@id="wayfinding-breadcrumbs_container"]//a',
            '//nav[@aria-label="Breadcrumb"]//a',
            '//div[contains(@class, "a-breadcrumb")]//a',
            '//div[@id="SalesRank"]//a[contains(@href, "/gp/bestsellers/")]',
            '//span[contains(@class, "a-list-item")]//a[contains(@href, "/s?")]'
        );
        
        foreach ($category_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                foreach ($nodes as $node) {
                    $category_name = $this->clean_category_name($node->textContent);
                    if (!empty($category_name) && 
                        !$this->is_invalid_category($category_name) &&
                        !in_array($category_name, $categories)) {
                        $categories[] = $category_name;
                    }
                }
                // If we found categories from breadcrumb, use those
                if (!empty($categories)) {
                    break;
                }
            }
        }
        
        // Fallback: extract from department links
        if (empty($categories)) {
            $dept_selectors = array(
                '//a[contains(@href, "/gp/browse.html")]',
                '//a[contains(@href, "/b/ref=")]'
            );
            
            foreach ($dept_selectors as $selector) {
                $nodes = $xpath->query($selector);
                foreach ($nodes as $node) {
                    $category_name = $this->clean_category_name($node->textContent);
                    if (!empty($category_name) && 
                        !$this->is_invalid_category($category_name) &&
                        !in_array($category_name, $categories) &&
                        count($categories) < 5) {
                        $categories[] = $category_name;
                    }
                }
            }
        }
        
        return array_unique($categories);
    }
    
    /**
     * Clean category name
     */
    private function clean_category_name($name) {
        $name = trim($name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = preg_replace('/[^\w\s\-&]/', '', $name);
        return $name;
    }
    
    /**
     * Check if category name is invalid
     */
    private function is_invalid_category($name) {
        $invalid_patterns = array(
            '/^(home|amazon|shop|buy|cart|account|sign|help|customer|service)$/i',
            '/^\d+$/',
            '/^.{1,2}$/',
            '/^.{50,}$/',
            '/\.(com|org|net)$/i'
        );
        
        foreach ($invalid_patterns as $pattern) {
            if (preg_match($pattern, $name)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Create category hierarchy in WooCommerce
     */
    public function create_category_hierarchy($categories, $options = array()) {
        if (empty($categories)) {
            return array();
        }
        
        $created_categories = array();
        $parent_id = 0;
        
        foreach ($categories as $index => $category_name) {
            // Check if category already exists
            $existing_category = $this->find_existing_category($category_name, $parent_id);
            
            if ($existing_category) {
                $category_id = $existing_category->term_id;
                $created_categories[] = $category_id;
                $parent_id = $category_id;
                continue;
            }
            
            // Create new category
            $category_data = array(
                'name' => $category_name,
                'slug' => sanitize_title($category_name),
                'parent' => $parent_id,
                'description' => sprintf(__('Imported from Amazon: %s', 'amazon-affiliate-importer'), $category_name)
            );
            
            $result = wp_insert_term($category_name, 'product_cat', array(
                'slug' => $category_data['slug'],
                'parent' => $parent_id,
                'description' => $category_data['description']
            ));
            
            if (!is_wp_error($result)) {
                $category_id = $result['term_id'];
                $created_categories[] = $category_id;
                $parent_id = $category_id;
                
                // Add meta to track Amazon import
                update_term_meta($category_id, '_amazon_imported', true);
                update_term_meta($category_id, '_amazon_import_date', current_time('mysql'));
                
                // Cache the category
                $this->category_cache[$category_name] = $category_id;
            }
        }
        
        return $created_categories;
    }
    
    /**
     * Find existing category by name and parent
     */
    private function find_existing_category($name, $parent_id = 0) {
        // Check cache first
        $cache_key = $name . '_' . $parent_id;
        if (isset($this->category_cache[$cache_key])) {
            return get_term($this->category_cache[$cache_key], 'product_cat');
        }
        
        $existing = get_terms(array(
            'taxonomy' => 'product_cat',
            'name' => $name,
            'parent' => $parent_id,
            'hide_empty' => false,
            'number' => 1
        ));
        
        if (!empty($existing)) {
            $this->category_cache[$cache_key] = $existing[0]->term_id;
            return $existing[0];
        }
        
        // Try fuzzy matching for similar names
        $similar = get_terms(array(
            'taxonomy' => 'product_cat',
            'parent' => $parent_id,
            'hide_empty' => false,
            'search' => $name
        ));
        
        foreach ($similar as $term) {
            $similarity = similar_text(strtolower($name), strtolower($term->name), $percent);
            if ($percent > 80) {
                $this->category_cache[$cache_key] = $term->term_id;
                return $term;
            }
        }
        
        return false;
    }
    
    /**
     * Get category hierarchy as tree
     */
    public function get_category_tree($include_amazon_only = false) {
        $args = array(
            'taxonomy' => 'product_cat',
            'orderby' => 'name',
            'order' => 'ASC',
            'hide_empty' => false,
            'hierarchical' => true
        );
        
        if ($include_amazon_only) {
            $args['meta_query'] = array(
                array(
                    'key' => '_amazon_imported',
                    'value' => true,
                    'compare' => '='
                )
            );
        }
        
        $categories = get_terms($args);
        
        return $this->build_category_tree($categories);
    }
    
    /**
     * Build hierarchical category tree
     */
    private function build_category_tree($categories, $parent_id = 0) {
        $tree = array();
        
        foreach ($categories as $category) {
            if ($category->parent == $parent_id) {
                $children = $this->build_category_tree($categories, $category->term_id);
                
                $tree[] = array(
                    'id' => $category->term_id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'parent' => $category->parent,
                    'count' => $category->count,
                    'amazon_imported' => get_term_meta($category->term_id, '_amazon_imported', true),
                    'children' => $children
                );
            }
        }
        
        return $tree;
    }
    
    /**
     * Fix broken categories (merge duplicates, fix hierarchy)
     */
    public function fix_broken_categories($dry_run = false) {
        $issues = array();
        $fixes = array();
        
        // Find duplicate categories
        $duplicates = $this->find_duplicate_categories();
        foreach ($duplicates as $name => $terms) {
            if (count($terms) > 1) {
                $issues[] = array(
                    'type' => 'duplicate',
                    'message' => sprintf(__('Found %d duplicate categories named "%s"', 'amazon-affiliate-importer'), count($terms), $name),
                    'terms' => $terms
                );
                
                if (!$dry_run) {
                    $fix_result = $this->merge_duplicate_categories($terms);
                    $fixes[] = $fix_result;
                }
            }
        }
        
        // Find orphaned categories
        $orphaned = $this->find_orphaned_categories();
        foreach ($orphaned as $term) {
            $issues[] = array(
                'type' => 'orphaned',
                'message' => sprintf(__('Category "%s" has invalid parent ID %d', 'amazon-affiliate-importer'), $term->name, $term->parent),
                'term' => $term
            );
            
            if (!$dry_run) {
                wp_update_term($term->term_id, 'product_cat', array('parent' => 0));
                $fixes[] = array(
                    'type' => 'orphaned_fixed',
                    'message' => sprintf(__('Fixed orphaned category "%s"', 'amazon-affiliate-importer'), $term->name)
                );
            }
        }
        
        // Find empty categories with no products
        $empty = $this->find_empty_amazon_categories();
        foreach ($empty as $term) {
            $issues[] = array(
                'type' => 'empty',
                'message' => sprintf(__('Empty Amazon category "%s" has no products', 'amazon-affiliate-importer'), $term->name),
                'term' => $term
            );
        }
        
        return array(
            'issues' => $issues,
            'fixes' => $fixes
        );
    }
    
    /**
     * Find duplicate categories
     */
    private function find_duplicate_categories() {
        $all_categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false
        ));
        
        $duplicates = array();
        foreach ($all_categories as $term) {
            $name = strtolower(trim($term->name));
            if (!isset($duplicates[$name])) {
                $duplicates[$name] = array();
            }
            $duplicates[$name][] = $term;
        }
        
        // Filter to only actual duplicates
        return array_filter($duplicates, function($terms) {
            return count($terms) > 1;
        });
    }
    
    /**
     * Merge duplicate categories
     */
    private function merge_duplicate_categories($terms) {
        if (count($terms) < 2) {
            return false;
        }
        
        // Keep the one with most products, or the oldest
        usort($terms, function($a, $b) {
            if ($a->count != $b->count) {
                return $b->count - $a->count;
            }
            return $a->term_id - $b->term_id;
        });
        
        $primary_term = array_shift($terms);
        $merged_count = 0;
        
        foreach ($terms as $term_to_merge) {
            // Move all products to primary term
            $products = get_objects_in_term($term_to_merge->term_id, 'product_cat');
            foreach ($products as $product_id) {
                wp_set_post_terms($product_id, array($primary_term->term_id), 'product_cat', true);
                $merged_count++;
            }
            
            // Delete the duplicate term
            wp_delete_term($term_to_merge->term_id, 'product_cat');
        }
        
        return array(
            'type' => 'duplicate_merged',
            'message' => sprintf(__('Merged %d duplicate categories into "%s", moved %d products', 'amazon-affiliate-importer'), 
                count($terms), $primary_term->name, $merged_count),
            'primary_term' => $primary_term,
            'merged_count' => $merged_count
        );
    }
    
    /**
     * Find orphaned categories
     */
    private function find_orphaned_categories() {
        global $wpdb;
        
        $orphaned = $wpdb->get_results("
            SELECT t.*, tt.*
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy = 'product_cat'
            AND tt.parent > 0
            AND tt.parent NOT IN (
                SELECT DISTINCT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'product_cat'
            )
        ");
        
        return $orphaned;
    }
    
    /**
     * Find empty Amazon categories
     */
    private function find_empty_amazon_categories() {
        $amazon_categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'meta_query' => array(
                array(
                    'key' => '_amazon_imported',
                    'value' => true,
                    'compare' => '='
                )
            )
        ));
        
        return array_filter($amazon_categories, function($term) {
            return $term->count == 0;
        });
    }
    
    /**
     * AJAX handler for scanning categories
     */
    public function ajax_scan_categories() {
        check_ajax_referer('amazon_importer_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'amazon-affiliate-importer'));
        }
        
        $results = $this->fix_broken_categories(true); // Dry run
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX handler for importing categories
     */
    public function ajax_import_categories() {
        check_ajax_referer('amazon_importer_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'amazon-affiliate-importer'));
        }
        
        $amazon_url = sanitize_url($_POST['amazon_url']);
        
        try {
            // Fetch page content
            $scraper = new AmazonAffiliateImporter_Scraper();
            $html = $scraper->get_page_content($amazon_url);
            
            if (is_wp_error($html)) {
                wp_send_json_error(array('message' => $html->get_error_message()));
            }
            
            // Extract categories
            $categories = $this->extract_categories_from_html($html);
            
            wp_send_json_success(array(
                'categories' => $categories,
                'message' => sprintf(__('Found %d categories', 'amazon-affiliate-importer'), count($categories))
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler for fixing categories
     */
    public function ajax_fix_categories() {
        check_ajax_referer('amazon_importer_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'amazon-affiliate-importer'));
        }
        
        $dry_run = isset($_POST['dry_run']) ? (bool) $_POST['dry_run'] : false;
        
        try {
            $result = $this->fix_broken_categories($dry_run);
            
            wp_send_json_success(array(
                'issues' => $result['issues'],
                'fixes' => $result['fixes'],
                'message' => sprintf(__('Found %d issues, applied %d fixes', 'amazon-affiliate-importer'), 
                    count($result['issues']), count($result['fixes']))
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler for getting category tree
     */
    public function ajax_get_category_tree() {
        check_ajax_referer('amazon_importer_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'amazon-affiliate-importer'));
        }
        
        $amazon_only = isset($_POST['amazon_only']) ? (bool) $_POST['amazon_only'] : false;
        
        try {
            $tree = $this->get_category_tree($amazon_only);
            
            wp_send_json_success(array(
                'tree' => $tree,
                'message' => sprintf(__('Loaded %d categories', 'amazon-affiliate-importer'), count($tree))
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler for merging categories
     */
    public function ajax_merge_categories() {
        check_ajax_referer('amazon_importer_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'amazon-affiliate-importer'));
        }
        
        $term_ids = isset($_POST['term_ids']) ? array_map('intval', $_POST['term_ids']) : array();
        
        if (count($term_ids) < 2) {
            wp_send_json_error(array('message' => __('At least 2 categories are required for merging.', 'amazon-affiliate-importer')));
        }
        
        $terms = array();
        foreach ($term_ids as $term_id) {
            $term = get_term($term_id, 'product_cat');
            if ($term && !is_wp_error($term)) {
                $terms[] = $term;
            }
        }
        
        if (count($terms) < 2) {
            wp_send_json_error(array('message' => __('Invalid category IDs provided.', 'amazon-affiliate-importer')));
        }
        
        $result = $this->merge_duplicate_categories($terms);
        
        if ($result) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(array('message' => __('Failed to merge categories.', 'amazon-affiliate-importer')));
        }
    }
}
