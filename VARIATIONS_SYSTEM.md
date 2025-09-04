# Amazon Affiliate Product Variations - Complete Implementation

## Overview
The Amazon Affiliate Importer plugin now supports full Amazon product variation import and management. This creates a sophisticated system that allows users to browse product variations (color, size, style, etc.) on your WooCommerce site while maintaining proper Amazon affiliate compliance.

## How It Works

### 1. Variation Detection
When importing an Amazon product, the system:
- Scans the Amazon page for variation elements using multiple CSS selectors
- Detects variations like colors, sizes, styles, patterns, materials
- Extracts individual ASINs for each variation
- Captures variation-specific data (images, prices, attributes)

### 2. Product Creation
The system creates **WooCommerce Variable Products** with:
- Parent product with main Amazon product data
- Individual variations for each Amazon option
- Product attributes (Color, Size, Style, etc.)
- Variation-specific images and URLs
- Amazon affiliate compliance throughout

### 3. User Experience
When customers browse your site:
- They see a normal WooCommerce variable product
- They can select options (color, size, etc.) using WooCommerce dropdowns
- When they click "Add to Cart", they're redirected to the specific Amazon variation
- Affiliate tags are preserved throughout the process

## Technical Implementation

### Core Files Modified

#### 1. `includes/class-product.php`
**New Methods Added:**
- `create_amazon_variable_product()` - Creates WooCommerce Variable Products
- `create_amazon_product_attributes()` - Builds product attributes from variation data
- `create_amazon_product_variations()` - Creates individual WooCommerce variations
- `add_amazon_variable_redirect_handlers()` - Sets up AJAX redirect system
- `add_amazon_variation_redirect_script()` - Adds JavaScript for variation handling
- `handle_amazon_variation_url_ajax()` - Processes AJAX requests for variation URLs

**Enhanced Methods:**
- `import_product()` - Now detects variations and creates Variable Products when found
- `create_external_product()` - Maintains compatibility for single products

#### 2. `includes/class-scraper.php`
**New Methods Added:**
- `extract_variations()` - Main variation detection method
- `extract_single_variation()` - Processes individual variation elements
- `extract_variation_price()` - Attempts to extract variation-specific pricing
- `extract_variation_attributes()` - Identifies attributes (color, size, etc.)
- `deduplicate_variations()` - Removes duplicate ASINs

**Enhanced Selectors:**
The scraper uses comprehensive CSS selectors to find variations:
```php
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
    // ... and more
);
```

### 3. JavaScript Integration
The system includes sophisticated JavaScript that:

```javascript
// Detects variation selection changes
jQuery('form.variations_form').on('change', 'select, input[type="radio"]', function() {
    // Check if all required attributes are selected
    // Enable/disable the redirect functionality
});

// Overrides Add to Cart button
jQuery(document).on('click', '.single_add_to_cart_button', function(e) {
    if (is_amazon_variable_product) {
        e.preventDefault();
        // Get selected variation
        // Make AJAX call to get Amazon URL
        // Redirect to Amazon
    }
});
```

## Amazon Compliance Features

### 1. Direct Amazon Redirects
- Users never actually add products to WooCommerce cart
- All purchases happen on Amazon
- Maintains Amazon's affiliate program requirements

### 2. Affiliate Tag Preservation
- All variation URLs include your affiliate tag
- Consistent tracking across all variations
- Proper attribution for commissions

### 3. Real Product Data
- Pulls actual Amazon variation data
- Maintains accurate product information
- Updates reflect real Amazon availability

## Usage Examples

### Basic Import
```php
$product_importer = new Amazon_Affiliate_Product();
$result = $product_importer->import_product('https://amazon.com/dp/B08N5WRWNW');

if (!is_wp_error($result)) {
    echo "Imported product ID: " . $result;
}
```

### Testing Variations
```php
$scraper = new Amazon_Affiliate_Scraper();
$variations = $scraper->extract_variations('https://amazon.com/dp/B08N5WRWNW');

foreach ($variations as $variation) {
    echo "ASIN: " . $variation['asin'] . "\n";
    echo "URL: " . $variation['url'] . "\n";
    if (!empty($variation['attributes'])) {
        print_r($variation['attributes']);
    }
}
```

## Configuration Options

### 1. Variation Detection Threshold
The system automatically decides between External and Variable products:
- **2+ variations found**: Creates Variable Product
- **1 or no variations**: Creates External Product

### 2. Attribute Mapping
Common Amazon attributes are automatically mapped:
- Color variations → "Color" attribute
- Size variations → "Size" attribute  
- Style variations → "Style" attribute
- Pattern variations → "Pattern" attribute
- Material variations → "Material" attribute
- Unknown variations → "Option" attribute

### 3. Image Handling
- Main product images are imported as normal
- Variation-specific images are detected and imported
- High-resolution images are preferred when available

## Benefits for Your Amazon Affiliate Site

### 1. Better User Experience
- Customers can see all options before going to Amazon
- Familiar WooCommerce interface for browsing
- Clear indication of available variations

### 2. SEO Advantages
- Multiple product pages become one comprehensive page
- Better internal linking structure
- More complete product data for search engines

### 3. Conversion Benefits
- Reduced decision paralysis with organized options
- Single product page covers multiple Amazon ASINs
- Maintains affiliate commissions across all variations

### 4. Management Efficiency
- One WooCommerce product manages multiple Amazon products
- Centralized inventory and pricing display
- Simplified categorization and organization

## Amazon ToS Compliance Notes

### 1. Purchase Redirection
- All purchases occur on Amazon, not your site
- No local checkout or payment processing
- Maintains Amazon's control over the transaction

### 2. Price Display
- Prices are displayed as extracted from Amazon
- No markup or modification of Amazon pricing
- Regular updates reflect Amazon's current pricing

### 3. Product Information
- All data sourced directly from Amazon
- No modification of product descriptions or specifications
- Maintains Amazon's product presentation standards

## Troubleshooting

### 1. No Variations Found
If the system doesn't detect variations:
- Check if the product actually has variations on Amazon
- Verify the Amazon URL is accessible
- Check error logs for scraping issues

### 2. Incomplete Variation Data
Some variations might be missing data:
- Amazon's variation structure varies by product type
- Some attributes may not be detectable
- Manual review and adjustment may be needed

### 3. JavaScript Issues
If redirects don't work:
- Check for JavaScript errors in browser console
- Verify WooCommerce variation JavaScript is loading
- Ensure no theme conflicts with the added scripts

## Future Enhancements

### Possible Improvements
1. **Automated Updates**: Periodic re-scanning for new variations
2. **Price Monitoring**: Track variation price changes
3. **Inventory Status**: Display Amazon stock status
4. **Review Integration**: Pull variation-specific reviews
5. **Advanced Filtering**: Allow filtering by variation attributes

## Testing

Use the included `test-variations.php` file to:
- Test variation detection on specific URLs
- Verify attribute extraction
- Debug scraping issues
- Validate product creation process

The complete variation system provides a professional, compliant way to showcase Amazon product variations while maintaining proper affiliate relationships and user experience standards.
