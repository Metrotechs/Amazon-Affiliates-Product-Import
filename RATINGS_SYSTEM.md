# Amazon Affiliate Importer - Ratings System

## Overview
The rating system automatically extracts Amazon product ratings and review counts during import, displays them as WooCommerce star ratings, and provides a link to the full Amazon reviews page.

## How It Works

### 1. Rating Extraction (Automatic)
When a product is imported, the scraper automatically:
- Extracts the average rating (e.g., 4.3 out of 5 stars)
- Extracts the review count (e.g., 1,287 customer reviews)
- Builds an Amazon reviews URL with the affiliate tag preserved

### 2. Rating Display
The imported ratings are displayed in WooCommerce:
- **Star rating**: Shows on product pages using WooCommerce's native star rating display
- **Review count**: Shows the number of Amazon reviews (e.g., "Based on 1,287 reviews")
- **Amazon Reviews button**: Added to WooCommerce's Reviews tab with a prominent button to read full reviews on Amazon

### 3. Compliant Implementation
- **No review text copying**: Only rating summary and review count are stored
- **Link to Amazon**: Full reviews are accessed via Amazon with affiliate tags preserved
- **Terms compliant**: Follows Amazon Associates program guidelines

## Technical Details

### Database Storage
The following meta fields are stored for each imported product:
- `_amazon_rating` - Float value (e.g., 4.3)
- `_amazon_review_count` - Integer value (e.g., 1287)
- `_amazon_reviews_url` - URL to Amazon reviews page with affiliate tag
- `_wc_average_rating` - Synced with WooCommerce for rating-based sorting/filtering
- `_wc_review_count` - Synced with WooCommerce for review count display

### Extraction Selectors
The scraper looks for ratings using multiple CSS selectors:
- `span.a-icon-alt` containing "out of 5 stars"
- `i.a-icon-star span.a-icon-alt`
- `#acrPopover @title`
- `#acrCustomerReviewLink span.a-icon-alt`

For review counts, it looks for:
- `#acrCustomerReviewText`
- Links containing "customerReviews"
- Text patterns like "1,234 ratings" or "567 customer reviews"

### Fallback URL Building
If no explicit reviews URL is provided during import, the system automatically builds one using:
- The original Amazon product URL (to preserve locale: .com, .co.uk, etc.)
- The extracted ASIN
- The affiliate tag from the original URL

Example: `https://www.amazon.com/product-reviews/B07XXXXX/?th=1&psc=1&tag=yourtag-20`

## Usage

### For Users
1. **Import products normally** - Ratings are extracted automatically
2. **View ratings** - Star ratings appear on product pages
3. **Read full reviews** - Click the "Read Reviews on Amazon" button in the Reviews tab to visit Amazon

### For Developers
```php
// Manually save rating data for a product
AmazonAffiliateImporter_Ratings::save_amazon_review_meta(
    $product_id,
    4.3,  // average rating
    1287, // review count
    'https://amazon.com/product-reviews/ASIN/?tag=yourtag-20' // reviews URL (optional)
);
```

## WooCommerce Integration

### Star Ratings
- Replaces WooCommerce's native rating system for Amazon products
- Uses WooCommerce's `wc_get_rating_html()` function for consistent styling
- Supports themes that customize rating display

### Product Reviews Integration
- Adds a "Read Reviews on Amazon" button to WooCommerce's existing Reviews tab
- Button is styled to match Amazon's branding (orange color)
- Uses proper `target="_blank"` and `rel="nofollow noopener sponsored"` attributes
- JavaScript ensures button appears regardless of theme's review tab implementation

### Sorting and Filtering
- Syncs ratings with WooCommerce's internal rating meta
- Enables rating-based product sorting in shop pages
- Supports rating filter widgets

## Troubleshooting

### Ratings Not Showing
1. **Check if ratings were extracted**: Look for `_amazon_rating` meta in the product
2. **Verify WooCommerce reviews are enabled**: WooCommerce > Settings > Products > Enable reviews
3. **Theme compatibility**: Some themes may override rating display

### No Amazon Reviews Button
1. **Check for reviews URL**: Look for `_amazon_reviews_url` meta
2. **ASIN required**: The product must have a valid `_amazon_asin` meta
3. **URL building**: System will try to build URL from ASIN + original Amazon URL
4. **Reviews tab**: Make sure WooCommerce reviews are enabled and the Reviews tab is visible

### Missing Review Counts
- Amazon sometimes doesn't display review counts on all product pages
- System may extract ratings without counts - this is normal
- Links to Amazon reviews will still work

## Security and Compliance

### Amazon ToS Compliance
- No review content is scraped or stored
- Only rating summary and count are extracted
- Users are directed to Amazon for full reviews
- Affiliate tags are preserved in review links

### Data Sanitization
- All URLs are sanitized with `esc_url_raw()`
- Rating values are validated as numeric
- Review counts are validated as integers

### Performance
- Rating data is cached in post meta
- No real-time API calls on page load
- WooCommerce transients are cleared when ratings update

## Future Enhancements
- Amazon Product Advertising API integration for official rating data
- Bulk rating refresh for existing products
- Rating update scheduling
- Custom rating display templates
