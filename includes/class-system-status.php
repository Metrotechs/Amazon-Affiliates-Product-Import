<?php
/**
 * WooCommerce System Status Integration for Amazon Affiliate Product Importer
 */

if (!defined('ABSPATH')) {
    exit;
}

class AmazonAffiliateImporter_System_Status {
    
    public function __construct() {
        add_filter('woocommerce_system_status_report', array($this, 'add_system_status_report'));
        add_action('woocommerce_system_status_report', array($this, 'render_system_status_report'));
    }
    
    /**
     * Add our plugin to WooCommerce system status report
     */
    public function add_system_status_report($reports) {
        $reports['amazon_affiliate_importer'] = array(
            'title'   => __('Amazon Affiliate Importer', 'amazon-affiliate-importer'),
            'callback' => array($this, 'render_system_status_report')
        );
        
        return $reports;
    }
    
    /**
     * Render system status report section
     */
    public function render_system_status_report() {
        $status_data = $this->get_system_status_data();
        
        ?>
        <table class="wc_status_table widefat" cellspacing="0">
            <thead>
                <tr>
                    <th colspan="3" data-export-label="Amazon Affiliate Importer">
                        <h2><?php _e('Amazon Affiliate Importer', 'amazon-affiliate-importer'); ?></h2>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td data-export-label="Version"><?php _e('Version', 'amazon-affiliate-importer'); ?>:</td>
                    <td class="help">&nbsp;</td>
                    <td><?php echo esc_html($status_data['version']); ?></td>
                </tr>
                <tr>
                    <td data-export-label="WooCommerce Compatibility"><?php _e('WooCommerce Compatibility', 'amazon-affiliate-importer'); ?>:</td>
                    <td class="help">&nbsp;</td>
                    <td>
                        <?php if ($status_data['wc_compatible']): ?>
                            <mark class="yes">
                                <span class="dashicons dashicons-yes"></span>
                                <?php _e('Compatible', 'amazon-affiliate-importer'); ?>
                            </mark>
                        <?php else: ?>
                            <mark class="error">
                                <span class="dashicons dashicons-warning"></span>
                                <?php _e('Incompatible', 'amazon-affiliate-importer'); ?>
                            </mark>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td data-export-label="HPOS Compatibility"><?php _e('HPOS Compatibility', 'amazon-affiliate-importer'); ?>:</td>
                    <td class="help">&nbsp;</td>
                    <td>
                        <mark class="yes">
                            <span class="dashicons dashicons-yes"></span>
                            <?php _e('Compatible', 'amazon-affiliate-importer'); ?>
                        </mark>
                    </td>
                </tr>
                <tr>
                    <td data-export-label="Total Imported Products"><?php _e('Total Imported Products', 'amazon-affiliate-importer'); ?>:</td>
                    <td class="help">&nbsp;</td>
                    <td><?php echo esc_html($status_data['imported_products_count']); ?></td>
                </tr>
                <tr>
                    <td data-export-label="Amazon Products with Videos"><?php _e('Products with Videos', 'amazon-affiliate-importer'); ?>:</td>
                    <td class="help">&nbsp;</td>
                    <td><?php echo esc_html($status_data['products_with_videos']); ?></td>
                </tr>
                <tr>
                    <td data-export-label="Amazon Products with Ratings"><?php _e('Products with Ratings', 'amazon-affiliate-importer'); ?>:</td>
                    <td class="help">&nbsp;</td>
                    <td><?php echo esc_html($status_data['products_with_ratings']); ?></td>
                </tr>
                <tr>
                    <td data-export-label="Database Tables"><?php _e('Database Tables', 'amazon-affiliate-importer'); ?>:</td>
                    <td class="help">&nbsp;</td>
                    <td>
                        <?php if ($status_data['db_tables_exist']): ?>
                            <mark class="yes">
                                <span class="dashicons dashicons-yes"></span>
                                <?php _e('Installed', 'amazon-affiliate-importer'); ?>
                            </mark>
                        <?php else: ?>
                            <mark class="error">
                                <span class="dashicons dashicons-warning"></span>
                                <?php _e('Missing', 'amazon-affiliate-importer'); ?>
                            </mark>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td data-export-label="PHP cURL Extension"><?php _e('PHP cURL Extension', 'amazon-affiliate-importer'); ?>:</td>
                    <td class="help">&nbsp;</td>
                    <td>
                        <?php if ($status_data['curl_available']): ?>
                            <mark class="yes">
                                <span class="dashicons dashicons-yes"></span>
                                <?php _e('Available', 'amazon-affiliate-importer'); ?>
                            </mark>
                        <?php else: ?>
                            <mark class="error">
                                <span class="dashicons dashicons-warning"></span>
                                <?php _e('Not Available', 'amazon-affiliate-importer'); ?>
                            </mark>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td data-export-label="DOMDocument Extension"><?php _e('DOMDocument Extension', 'amazon-affiliate-importer'); ?>:</td>
                    <td class="help">&nbsp;</td>
                    <td>
                        <?php if ($status_data['dom_available']): ?>
                            <mark class="yes">
                                <span class="dashicons dashicons-yes"></span>
                                <?php _e('Available', 'amazon-affiliate-importer'); ?>
                            </mark>
                        <?php else: ?>
                            <mark class="error">
                                <span class="dashicons dashicons-warning"></span>
                                <?php _e('Not Available', 'amazon-affiliate-importer'); ?>
                            </mark>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Get system status data
     */
    private function get_system_status_data() {
        global $wpdb;
        
        // Check if database tables exist
        $table_name = $wpdb->prefix . 'amazon_affiliate_products';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        // Count imported products
        $imported_count = get_posts(array(
            'post_type' => 'product',
            'meta_query' => array(
                array(
                    'key' => '_imported_from_amazon',
                    'value' => true,
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        // Count products with videos
        $videos_count = get_posts(array(
            'post_type' => 'product',
            'meta_query' => array(
                array(
                    'key' => '_amazon_has_videos',
                    'value' => true,
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        // Count products with ratings
        $ratings_count = get_posts(array(
            'post_type' => 'product',
            'meta_query' => array(
                array(
                    'key' => '_amazon_rating',
                    'compare' => 'EXISTS'
                )
            ),
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        return array(
            'version' => AMAZON_AFFILIATE_IMPORTER_VERSION,
            'wc_compatible' => AmazonAffiliateImporter_Compatibility::is_woocommerce_version_compatible(),
            'imported_products_count' => is_array($imported_count) ? count($imported_count) : 0,
            'products_with_videos' => is_array($videos_count) ? count($videos_count) : 0,
            'products_with_ratings' => is_array($ratings_count) ? count($ratings_count) : 0,
            'db_tables_exist' => $table_exists,
            'curl_available' => function_exists('curl_init'),
            'dom_available' => class_exists('DOMDocument')
        );
    }
}

// Initialize system status integration
new AmazonAffiliateImporter_System_Status();
