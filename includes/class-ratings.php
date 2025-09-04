<?php
if (!defined('ABSPATH')) { exit; }

class AmazonAffiliateImporter_Ratings {
    public function __construct() {
        // Use Amazon rating/count if present.
        add_filter('woocommerce_product_get_average_rating', [$this, 'use_amazon_average_rating'], 10, 2);
        add_filter('woocommerce_product_get_review_count',   [$this, 'use_amazon_review_count'], 10, 2);

        // Add Amazon reviews button to the existing Reviews tab content
        add_action('woocommerce_output_product_data_tabs', [$this, 'add_amazon_reviews_to_reviews_tab']);
        add_filter('woocommerce_product_review_list_args', [$this, 'add_amazon_button_to_reviews']);
        
        // Additional hooks to ensure the button appears
        add_action('woocommerce_single_product_summary', [$this, 'add_amazon_reviews_to_reviews_tab'], 25);
        add_action('wp_footer', [$this, 'ensure_amazon_reviews_button']);
    }

    public function use_amazon_average_rating($avg, $product) {
        $rating = get_post_meta($product->get_id(), '_amazon_rating', true);
        if ($rating !== '' && is_numeric($rating)) {
            return (string) round(floatval($rating), 1);
        }
        return $avg;
    }

    public function use_amazon_review_count($count, $product) {
        $review_count = get_post_meta($product->get_id(), '_amazon_review_count', true);
        if ($review_count !== '' && is_numeric($review_count)) {
            return intval($review_count);
        }
        return $count;
    }

    /**
     * Add Amazon reviews button to the WooCommerce Reviews tab
     */
    public function add_amazon_reviews_to_reviews_tab() {
        global $product;
        if (!$product) { return; }

        $url = get_post_meta($product->get_id(), '_amazon_reviews_url', true);
        // Fallback: build a reviews URL from ASIN and original product URL if missing
        if (empty($url)) {
            $built = $this->build_reviews_url($product->get_id());
            if (!empty($built)) {
                $url = $built;
                update_post_meta($product->get_id(), '_amazon_reviews_url', esc_url_raw($url));
            }
        }

        if ($url) {
            // Add JavaScript to inject button into reviews tab
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                console.log('Amazon Reviews: Initializing button injection');
                
                // Wait for reviews tab to be available
                var checkReviewsTab = function() {
                    console.log('Amazon Reviews: Checking for reviews tab');
                    
                    // Try multiple selectors to find the reviews content area
                    var reviewsContent = $('#tab-reviews, .woocommerce-tabs #reviews, .wc-tabs #reviews, #reviews, .reviews_tab, .woocommerce-Reviews-title').closest('.panel, .tab-content, #reviews');
                    
                    console.log('Amazon Reviews: Found ' + reviewsContent.length + ' potential review containers');
                    
                    if (reviewsContent.length && !reviewsContent.find('.amazon-reviews-button').length) {
                        console.log('Amazon Reviews: Adding button to reviews tab');
                        
                        var amazonButton = '<div class="amazon-reviews-section" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">' +
                            '<h4 style="margin: 0 0 10px 0; color: #333;"><?php echo esc_js(__('Amazon Customer Reviews', 'amazon-affiliate-importer')); ?></h4>' +
                            '<p style="margin: 0 0 15px 0; color: #666;"><?php echo esc_js(__('Read what customers are saying about this product on Amazon.', 'amazon-affiliate-importer')); ?></p>' +
                            '<a class="button amazon-reviews-button" href="<?php echo esc_js($url); ?>" target="_blank" rel="nofollow noopener sponsored" style="background: #ff9500 !important; color: white !important; border-color: #ff9500 !important; text-decoration: none; padding: 10px 20px; border-radius: 3px; display: inline-block;">' +
                            '<?php echo esc_js(__('Read Reviews on Amazon', 'amazon-affiliate-importer')); ?></a>' +
                            '</div>';
                        
                        // Try different insertion points
                        var inserted = false;
                        
                        // Try to insert before review form
                        if (!inserted && reviewsContent.find('#review_form_wrapper, .comment-form-wrapper, #respond').length) {
                            reviewsContent.find('#review_form_wrapper, .comment-form-wrapper, #respond').first().before(amazonButton);
                            inserted = true;
                            console.log('Amazon Reviews: Inserted before review form');
                        }
                        
                        // Try to insert after reviews title
                        if (!inserted && reviewsContent.find('.woocommerce-Reviews-title, h2').length) {
                            reviewsContent.find('.woocommerce-Reviews-title, h2').first().after(amazonButton);
                            inserted = true;
                            console.log('Amazon Reviews: Inserted after reviews title');
                        }
                        
                        // Fallback: prepend to reviews content
                        if (!inserted) {
                            reviewsContent.prepend(amazonButton);
                            console.log('Amazon Reviews: Prepended to reviews content');
                        }
                        
                        // Also try to add to any visible reviews container
                        $('.woocommerce-Reviews, .reviews, [id*="review"]').each(function() {
                            if ($(this).is(':visible') && !$(this).find('.amazon-reviews-button').length) {
                                $(this).prepend(amazonButton);
                                console.log('Amazon Reviews: Added to visible reviews container');
                            }
                        });
                    } else if (reviewsContent.find('.amazon-reviews-button').length) {
                        console.log('Amazon Reviews: Button already exists');
                    } else {
                        console.log('Amazon Reviews: No reviews content found');
                    }
                };
                
                // Check immediately
                checkReviewsTab();
                
                // Check after a short delay for slow-loading themes
                setTimeout(checkReviewsTab, 500);
                setTimeout(checkReviewsTab, 1000);
                
                // Check after tab clicks
                $(document).on('click', '.wc-tabs a, .woocommerce-tabs a, a[href*="reviews"], a[href*="#reviews"]', function() {
                    console.log('Amazon Reviews: Tab clicked, checking again');
                    setTimeout(checkReviewsTab, 100);
                    setTimeout(checkReviewsTab, 500);
                });
                
                // Check when reviews tab becomes visible
                $(document).on('woocommerce_tabs_loaded', checkReviewsTab);
            });
            </script>
            <?php
        }
    }

    /**
     * Alternative method using filter (backup)
     */
    public function add_amazon_button_to_reviews($args) {
        global $product;
        if (!$product) { return $args; }

        $url = get_post_meta($product->get_id(), '_amazon_reviews_url', true);
        if (empty($url)) {
            $built = $this->build_reviews_url($product->get_id());
            if (!empty($built)) {
                $url = $built;
            }
        }

        if ($url) {
            add_action('woocommerce_review_before_comment_meta', function() use ($url) {
                static $button_added = false;
                if (!$button_added) {
                    echo '<div class="amazon-reviews-section" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 4px;">';
                    echo '<h4 style="margin: 0 0 10px 0;">' . esc_html__('Amazon Customer Reviews', 'amazon-affiliate-importer') . '</h4>';
                    echo '<p style="margin: 0 0 10px 0;">' . esc_html__('Read what customers are saying about this product on Amazon.', 'amazon-affiliate-importer') . '</p>';
                    echo '<a class="button amazon-reviews-button" href="' . esc_url($url) . '" target="_blank" rel="nofollow noopener sponsored" style="background: #ff9500; color: white; border-color: #ff9500;">';
                    echo esc_html__('Read Reviews on Amazon', 'amazon-affiliate-importer');
                    echo '</a></div>';
                    $button_added = true;
                }
            });
        }

        return $args;
    }
    
    /**
     * Ensure Amazon reviews button appears (fallback method)
     */
    public function ensure_amazon_reviews_button() {
        // Only run on single product pages
        if (!is_product()) {
            return;
        }
        
        global $product;
        if (!$product) { return; }

        $url = get_post_meta($product->get_id(), '_amazon_reviews_url', true);
        if (empty($url)) {
            $built = $this->build_reviews_url($product->get_id());
            if (!empty($built)) {
                $url = $built;
            }
        }

        if ($url) {
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                // More aggressive button injection as fallback
                var injectButton = function() {
                    var url = '<?php echo esc_js($url); ?>';
                    var buttonHtml = '<div class="amazon-reviews-section" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">' +
                        '<h4 style="margin: 0 0 10px 0; color: #333;">Amazon Customer Reviews</h4>' +
                        '<p style="margin: 0 0 15px 0; color: #666;">Read what customers are saying about this product on Amazon.</p>' +
                        '<a class="button amazon-reviews-button" href="' + url + '" target="_blank" rel="nofollow noopener sponsored" style="background: #ff9500 !important; color: white !important; border-color: #ff9500 !important; text-decoration: none; padding: 10px 20px; border-radius: 3px; display: inline-block;">Read Reviews on Amazon</a>' +
                        '</div>';
                    
                    // Find any reviews-related containers and add button if not already present
                    var containers = $('[id*="review"], [class*="review"], .woocommerce-tabs, .wc-tabs').filter(':visible');
                    
                    containers.each(function() {
                        if (!$(this).find('.amazon-reviews-button').length) {
                            // Try to find a good insertion point
                            var insertPoint = $(this).find('h2, .reviews_tab, #reviews').first();
                            if (insertPoint.length) {
                                insertPoint.after(buttonHtml);
                                console.log('Amazon Reviews: Button inserted via fallback method');
                            }
                        }
                    });
                };
                
                // Try multiple times to ensure button appears
                setTimeout(injectButton, 1000);
                setTimeout(injectButton, 2000);
                setTimeout(injectButton, 3000);
                
                // Also try when tabs are clicked
                $(document).on('click', 'a[href*="#reviews"], a[href*="reviews"]', function() {
                    setTimeout(injectButton, 500);
                });
            });
            </script>
            <?php
        }
    }

    // Helper for importer code to save the metadata.
    public static function save_amazon_review_meta($product_id, $rating, $count, $reviews_url) {
        if ($rating !== null) {
            update_post_meta($product_id, '_amazon_rating', floatval($rating));
        }
        if ($count !== null) {
            update_post_meta($product_id, '_amazon_review_count', intval($count));
        }
        if (!empty($reviews_url)) {
            update_post_meta($product_id, '_amazon_reviews_url', esc_url_raw($reviews_url));
        }
        // Also sync WooCommerce's cached rating metas so features like sorting by rating work
        $stored_rating = get_post_meta($product_id, '_amazon_rating', true);
        $stored_count  = get_post_meta($product_id, '_amazon_review_count', true);
        if ($stored_rating !== '' && is_numeric($stored_rating)) {
            update_post_meta($product_id, '_wc_average_rating', (string) round(floatval($stored_rating), 1));
        }
        if ($stored_count !== '' && is_numeric($stored_count)) {
            update_post_meta($product_id, '_wc_review_count', intval($stored_count));
        }
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($product_id);
        }
    }

    /**
     * Build an Amazon reviews URL from stored ASIN and original Amazon product URL (to preserve locale and tag).
     */
    private function build_reviews_url($product_id) {
        $asin = get_post_meta($product_id, '_amazon_asin', true);
        $product_url = get_post_meta($product_id, '_amazon_url', true);
        if (empty($asin) || empty($product_url)) {
            return '';
        }
        $parts = wp_parse_url($product_url);
        if (!$parts || empty($parts['host'])) {
            return '';
        }
        $host = $parts['host'];
        $scheme = isset($parts['scheme']) ? $parts['scheme'] : 'https';
        $tag = '';
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $q);
            if (!empty($q['tag'])) { $tag = $q['tag']; }
        }
        $base = $scheme . '://' . $host;
        $url  = $base . '/product-reviews/' . rawurlencode($asin) . '/?th=1&psc=1';
        if (!empty($tag)) {
            $url .= '&tag=' . rawurlencode($tag);
        }
        return $url;
    }
}
// Initialize the ratings functionality
new AmazonAffiliateImporter_Ratings();