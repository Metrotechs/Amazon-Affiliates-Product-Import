<?php
/**
 * Admin functionality for Amazon Affiliate Product Importer
 */

if (!defined('ABSPATH')) {
    exit;
}

class AmazonAffiliateImporter_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_ajax_import_amazon_product', array($this, 'ajax_import_product'));
        add_action('wp_ajax_extract_categories_preview', array($this, 'ajax_extract_categories_preview'));
        add_action('wp_ajax_fix_broken_categories', array($this, 'ajax_fix_broken_categories'));
        add_action('wp_ajax_merge_duplicate_categories', array($this, 'ajax_merge_duplicate_categories'));
        add_action('wp_ajax_delete_category', array($this, 'ajax_delete_category'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    public function admin_menu() {
        add_menu_page(
            __('Amazon Affiliate Importer', 'amazon-affiliate-importer'),
            __('Amazon Importer', 'amazon-affiliate-importer'),
            'manage_woocommerce',
            'amazon-affiliate-importer',
            array($this, 'admin_page'),
            'dashicons-cart',
            56
        );
        
        add_submenu_page(
            'amazon-affiliate-importer',
            __('Import Product', 'amazon-affiliate-importer'),
            __('Import Product', 'amazon-affiliate-importer'),
            'manage_woocommerce',
            'amazon-affiliate-importer',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'amazon-affiliate-importer',
            __('Category Manager', 'amazon-affiliate-importer'),
            __('Categories', 'amazon-affiliate-importer'),
            'manage_woocommerce',
            'amazon-affiliate-importer-categories',
            array($this, 'categories_page')
        );
        
        add_submenu_page(
            'amazon-affiliate-importer',
            __('Settings', 'amazon-affiliate-importer'),
            __('Settings', 'amazon-affiliate-importer'),
            'manage_woocommerce',
            'amazon-affiliate-importer-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'amazon-affiliate-importer',
            __('Imported Products', 'amazon-affiliate-importer'),
            __('Imported Products', 'amazon-affiliate-importer'),
            'manage_woocommerce',
            'amazon-affiliate-importer-products',
            array($this, 'products_page')
        );
    }
    
    public function admin_init() {
        register_setting('amazon_affiliate_importer_settings', 'amazon_affiliate_importer_settings');
    }
    
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'amazon-affiliate-importer') === false) {
            return;
        }
        
        wp_enqueue_script(
            'amazon-affiliate-importer-admin',
            AMAZON_AFFILIATE_IMPORTER_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            AMAZON_AFFILIATE_IMPORTER_VERSION,
            true
        );
        
        wp_localize_script('amazon-affiliate-importer-admin', 'amazonImporter', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('amazon_importer_nonce'),
            'strings' => array(
                'importing' => __('Importing product...', 'amazon-affiliate-importer'),
                'success' => __('Product imported successfully!', 'amazon-affiliate-importer'),
                'error' => __('Error importing product. Please try again.', 'amazon-affiliate-importer'),
                'invalid_url' => __('Please enter a valid Amazon product URL.', 'amazon-affiliate-importer')
            )
        ));
        
        wp_enqueue_style(
            'amazon-affiliate-importer-admin',
            AMAZON_AFFILIATE_IMPORTER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AMAZON_AFFILIATE_IMPORTER_VERSION
        );
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="amazon-importer-container">
                <div class="amazon-importer-form">
                    <h2><?php _e('Import Amazon Product', 'amazon-affiliate-importer'); ?></h2>
                    <p><?php _e('Enter an Amazon product URL with your affiliate tag to import it to WooCommerce.', 'amazon-affiliate-importer'); ?></p>
                    
                    <form id="amazon-import-form">
                        <?php wp_nonce_field('amazon_importer_nonce', 'amazon_importer_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="amazon_url"><?php _e('Amazon Product URL', 'amazon-affiliate-importer'); ?></label>
                                </th>
                                <td>
                                    <input type="url" 
                                           id="amazon_url" 
                                           name="amazon_url" 
                                           class="regular-text" 
                                           placeholder="https://www.amazon.com/dp/B07XXXXX?tag=your-affiliate-tag"
                                           required />
                                    <p class="description">
                                        <?php _e('Make sure to include your Amazon affiliate tag in the URL (e.g., ?tag=your-affiliate-tag)', 'amazon-affiliate-importer'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="category_handling"><?php _e('Category Handling', 'amazon-affiliate-importer'); ?></label>
                                </th>
                                <td>
                                    <select id="category_handling" name="category_handling" class="regular-text">
                                        <option value="extract"><?php _e('Extract from Amazon', 'amazon-affiliate-importer'); ?></option>
                                        <option value="manual"><?php _e('Manual Selection', 'amazon-affiliate-importer'); ?></option>
                                        <option value="none"><?php _e('No Category', 'amazon-affiliate-importer'); ?></option>
                                    </select>
                                    <p class="description">
                                        <?php _e('Choose how to handle product categories', 'amazon-affiliate-importer'); ?>
                                    </p>
                                    
                                    <!-- Category Extraction Options -->
                                    <div id="category-extraction-options" class="category-options" style="display: none;">
                                        <h4><?php _e('Category Extraction', 'amazon-affiliate-importer'); ?></h4>
                                        <button type="button" id="extract-categories-preview" class="button">
                                            <?php _e('Preview Categories', 'amazon-affiliate-importer'); ?>
                                        </button>
                                        <div id="category-preview-container"></div>
                                    </div>
                                    
                                    <!-- Manual Category Selection -->
                                    <div id="manual-category-selection" class="category-options" style="display: none;">
                                        <h4><?php _e('Select Category', 'amazon-affiliate-importer'); ?></h4>
                                        <?php
                                        wp_dropdown_categories(array(
                                            'taxonomy' => 'product_cat',
                                            'name' => 'product_category',
                                            'id' => 'product_category',
                                            'class' => 'regular-text',
                                            'show_option_none' => __('Select a category', 'amazon-affiliate-importer'),
                                            'option_none_value' => '',
                                            'hide_empty' => false
                                        ));
                                        ?>
                                    </div>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="product_status"><?php _e('Product Status', 'amazon-affiliate-importer'); ?></label>
                                </th>
                                <td>
                                    <select id="product_status" name="product_status" class="regular-text">
                                        <option value="draft"><?php _e('Draft', 'amazon-affiliate-importer'); ?></option>
                                        <option value="publish"><?php _e('Published', 'amazon-affiliate-importer'); ?></option>
                                        <option value="private"><?php _e('Private', 'amazon-affiliate-importer'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="import_images"><?php _e('Import Images', 'amazon-affiliate-importer'); ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" 
                                           id="import_images" 
                                           name="import_images" 
                                           value="1" 
                                           checked />
                                    <label for="import_images"><?php _e('Import product images from Amazon', 'amazon-affiliate-importer'); ?></label>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" 
                                   name="submit" 
                                   id="submit" 
                                   class="button button-primary" 
                                   value="<?php _e('Import Product', 'amazon-affiliate-importer'); ?>" />
                            <span class="spinner"></span>
                        </p>
                    </form>
                    
                    <div id="import-results" style="display: none;">
                        <div id="import-success" class="notice notice-success" style="display: none;">
                            <p><?php _e('Product imported successfully!', 'amazon-affiliate-importer'); ?> 
                               <a href="#" id="view-product-link" target="_blank"><?php _e('View Product', 'amazon-affiliate-importer'); ?></a>
                            </p>
                        </div>
                        <div id="import-error" class="notice notice-error" style="display: none;">
                            <p id="error-message"></p>
                        </div>
                    </div>
                </div>
                
                <div class="amazon-importer-sidebar">
                    <div class="postbox">
                        <h3 class="hndle"><?php _e('How to Use', 'amazon-affiliate-importer'); ?></h3>
                        <div class="inside">
                            <ol>
                                <li><?php _e('Copy the Amazon product URL from your browser', 'amazon-affiliate-importer'); ?></li>
                                <li><?php _e('Add your affiliate tag to the URL (e.g., ?tag=your-tag)', 'amazon-affiliate-importer'); ?></li>
                                <li><?php _e('Paste the URL in the form above', 'amazon-affiliate-importer'); ?></li>
                                <li><?php _e('Select category and other options', 'amazon-affiliate-importer'); ?></li>
                                <li><?php _e('Click "Import Product"', 'amazon-affiliate-importer'); ?></li>
                            </ol>
                        </div>
                    </div>
                    
                    <div class="postbox">
                        <h3 class="hndle"><?php _e('Supported Formats', 'amazon-affiliate-importer'); ?></h3>
                        <div class="inside">
                            <ul>
                                <li><code>amazon.com/dp/ASIN</code></li>
                                <li><code>amazon.com/gp/product/ASIN</code></li>
                                <li><code>amzn.to/shortlink</code></li>
                                <li><code>amazon.com/product-name/dp/ASIN</code></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function settings_page() {
        if (isset($_POST['submit'])) {
            $settings = array(
                'default_category' => sanitize_text_field($_POST['default_category']),
                'auto_publish' => sanitize_text_field($_POST['auto_publish']),
                'image_import' => isset($_POST['image_import']) ? 1 : 0,
                'price_sync' => isset($_POST['price_sync']) ? 1 : 0
            );
            update_option('amazon_affiliate_importer_settings', $settings);
            echo '<div class="notice notice-success"><p>' . __('Settings saved!', 'amazon-affiliate-importer') . '</p></div>';
        }
        
        $settings = get_option('amazon_affiliate_importer_settings', array());
        ?>
        <div class="wrap">
            <h1><?php _e('Amazon Affiliate Importer Settings', 'amazon-affiliate-importer'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('amazon_importer_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Default Category', 'amazon-affiliate-importer'); ?></th>
                        <td>
                            <?php
                            wp_dropdown_categories(array(
                                'taxonomy' => 'product_cat',
                                'name' => 'default_category',
                                'selected' => isset($settings['default_category']) ? $settings['default_category'] : '',
                                'show_option_none' => __('No default category', 'amazon-affiliate-importer'),
                                'option_none_value' => '',
                                'hide_empty' => false
                            ));
                            ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Auto Publish', 'amazon-affiliate-importer'); ?></th>
                        <td>
                            <select name="auto_publish">
                                <option value="draft" <?php selected(isset($settings['auto_publish']) ? $settings['auto_publish'] : 'draft', 'draft'); ?>><?php _e('Save as Draft', 'amazon-affiliate-importer'); ?></option>
                                <option value="publish" <?php selected(isset($settings['auto_publish']) ? $settings['auto_publish'] : 'draft', 'publish'); ?>><?php _e('Publish Immediately', 'amazon-affiliate-importer'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Import Images', 'amazon-affiliate-importer'); ?></th>
                        <td>
                            <input type="checkbox" name="image_import" value="1" <?php checked(isset($settings['image_import']) ? $settings['image_import'] : 1, 1); ?> />
                            <label><?php _e('Automatically import product images', 'amazon-affiliate-importer'); ?></label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Price Sync', 'amazon-affiliate-importer'); ?></th>
                        <td>
                            <input type="checkbox" name="price_sync" value="1" <?php checked(isset($settings['price_sync']) ? $settings['price_sync'] : 0, 1); ?> />
                            <label><?php _e('Sync prices periodically (requires cron job)', 'amazon-affiliate-importer'); ?></label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    public function products_page() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'amazon_affiliate_products';
        $products = $wpdb->get_results("SELECT * FROM $table_name ORDER BY imported_date DESC");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Imported Amazon Products', 'amazon-affiliate-importer'); ?></h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Product', 'amazon-affiliate-importer'); ?></th>
                        <th><?php _e('ASIN', 'amazon-affiliate-importer'); ?></th>
                        <th><?php _e('Affiliate Tag', 'amazon-affiliate-importer'); ?></th>
                        <th><?php _e('Imported Date', 'amazon-affiliate-importer'); ?></th>
                        <th><?php _e('Actions', 'amazon-affiliate-importer'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="5"><?php _e('No products imported yet.', 'amazon-affiliate-importer'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <?php $wc_product = wc_get_product($product->product_id); ?>
                            <tr>
                                <td>
                                    <?php if ($wc_product): ?>
                                        <strong><a href="<?php echo get_edit_post_link($product->product_id); ?>"><?php echo esc_html($wc_product->get_name()); ?></a></strong>
                                    <?php else: ?>
                                        <em><?php _e('Product not found', 'amazon-affiliate-importer'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($product->asin); ?></td>
                                <td><?php echo esc_html($product->affiliate_tag); ?></td>
                                <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($product->imported_date))); ?></td>
                                <td>
                                    <?php if ($wc_product): ?>
                                        <a href="<?php echo get_edit_post_link($product->product_id); ?>" class="button button-small"><?php _e('Edit', 'amazon-affiliate-importer'); ?></a>
                                        <a href="<?php echo get_permalink($product->product_id); ?>" class="button button-small" target="_blank"><?php _e('View', 'amazon-affiliate-importer'); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function categories_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Amazon Category Manager', 'amazon-affiliate-importer'); ?></h1>
            
            <div class="amazon-category-container">
                <div class="amazon-category-tools">
                    <div class="postbox">
                        <h3 class="hndle"><?php _e('Category Tools', 'amazon-affiliate-importer'); ?></h3>
                        <div class="inside">
                            <p>
                                <button type="button" id="scan-categories" class="button button-primary">
                                    <?php _e('Scan for Issues', 'amazon-affiliate-importer'); ?>
                                </button>
                                <button type="button" id="fix-categories" class="button">
                                    <?php _e('Fix Broken Categories', 'amazon-affiliate-importer'); ?>
                                </button>
                                <button type="button" id="refresh-tree" class="button">
                                    <?php _e('Refresh Tree', 'amazon-affiliate-importer'); ?>
                                </button>
                            </p>
                            <div id="category-tools-results"></div>
                        </div>
                    </div>
                    
                    <div class="postbox">
                        <h3 class="hndle"><?php _e('Statistics', 'amazon-affiliate-importer'); ?></h3>
                        <div class="inside">
                            <?php
                            $stats = $this->get_category_stats();
                            ?>
                            <ul>
                                <li><?php printf(__('Total Categories: %d', 'amazon-affiliate-importer'), $stats['total']); ?></li>
                                <li><?php printf(__('Amazon Categories: %d', 'amazon-affiliate-importer'), $stats['amazon']); ?></li>
                                <li><?php printf(__('Empty Categories: %d', 'amazon-affiliate-importer'), $stats['empty']); ?></li>
                                <li><?php printf(__('Top Level Categories: %d', 'amazon-affiliate-importer'), $stats['top_level']); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="amazon-category-tree">
                    <h2><?php _e('Category Hierarchy', 'amazon-affiliate-importer'); ?></h2>
                    <div class="category-filter">
                        <label>
                            <input type="checkbox" id="show-amazon-only" />
                            <?php _e('Show only Amazon imported categories', 'amazon-affiliate-importer'); ?>
                        </label>
                    </div>
                    <div id="category-tree-container">
                        <div class="loading"><?php _e('Loading categories...', 'amazon-affiliate-importer'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get category statistics
     */
    private function get_category_stats() {
        $all_categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false
        ));
        
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
        
        $empty_categories = array_filter($all_categories, function($cat) {
            return $cat->count == 0;
        });
        
        $top_level_categories = array_filter($all_categories, function($cat) {
            return $cat->parent == 0;
        });
        
        return array(
            'total' => count($all_categories),
            'amazon' => count($amazon_categories),
            'empty' => count($empty_categories),
            'top_level' => count($top_level_categories)
        );
    }
    
    public function ajax_import_product() {
        check_ajax_referer('amazon_importer_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $amazon_url = sanitize_url($_POST['amazon_url']);
        $category_id = intval($_POST['product_category']);
        $status = sanitize_text_field($_POST['product_status']);
        $import_images = isset($_POST['import_images']) ? true : false;
        
        // Handle category extraction options
        $category_handling = sanitize_text_field($_POST['category_handling'] ?? 'manual');
        $use_extracted_categories = isset($_POST['use_extracted_categories']) ? true : false;
        $extracted_categories = array();
        
        if (isset($_POST['extracted_categories']) && is_array($_POST['extracted_categories'])) {
            $extracted_categories = array_map('sanitize_text_field', $_POST['extracted_categories']);
        }
        
        try {
            $importer = new AmazonAffiliateImporter_Product();
            
            $options = array(
                'category_id' => $category_id,
                'status' => $status,
                'import_images' => $import_images,
                'category_handling' => $category_handling,
                'use_extracted_categories' => $use_extracted_categories,
                'extracted_categories' => $extracted_categories
            );
            
            $result = $importer->import_product($amazon_url, $options);
            
            if ($result && !is_wp_error($result)) {
                wp_send_json_success(array(
                    'message' => __('Product imported successfully!', 'amazon-affiliate-importer'),
                    'product_id' => $result,
                    'edit_url' => get_edit_post_link($result),
                    'view_url' => get_permalink($result)
                ));
            } else {
                $error_message = is_wp_error($result) ? $result->get_error_message() : __('Unknown error occurred.', 'amazon-affiliate-importer');
                wp_send_json_error(array('message' => $error_message));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler for category preview
     */
    public function ajax_extract_categories_preview() {
        check_ajax_referer('amazon_importer_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $amazon_url = sanitize_url($_POST['amazon_url']);
        
        if (empty($amazon_url)) {
            wp_send_json_error('Amazon URL is required');
        }
        
        try {
            $categories_manager = new AmazonAffiliateImporter_Categories();
            $scraper = new AmazonAffiliateImporter_Scraper();
            
            // Get HTML content
            $html = $scraper->get_page_content($amazon_url);
            
            if (is_wp_error($html)) {
                wp_send_json_error($html->get_error_message());
            }
            
            // Extract categories
            $categories = $categories_manager->extract_categories_from_html($html);
            
            if (is_wp_error($categories)) {
                wp_send_json_error($categories->get_error_message());
            }
            
            wp_send_json_success($categories);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX handler for fixing broken categories
     */
    public function ajax_fix_broken_categories() {
        check_ajax_referer('amazon_importer_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $categories_manager = new AmazonAffiliateImporter_Categories();
            $result = $categories_manager->fix_broken_categories();
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            
            wp_send_json_success(array('fixed' => $result));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX handler for merging duplicate categories
     */
    public function ajax_merge_duplicate_categories() {
        check_ajax_referer('amazon_importer_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $categories_manager = new AmazonAffiliateImporter_Categories();
            $result = $categories_manager->merge_duplicate_categories();
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            
            wp_send_json_success(array('merged' => $result));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX handler for deleting categories
     */
    public function ajax_delete_category() {
        check_ajax_referer('amazon_importer_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $category_id = intval($_POST['category_id']);
        
        if (empty($category_id)) {
            wp_send_json_error('Category ID is required');
        }
        
        try {
            $result = wp_delete_term($category_id, 'product_cat');
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            
            wp_send_json_success(array('deleted' => true));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}
