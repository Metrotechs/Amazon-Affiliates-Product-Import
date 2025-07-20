# Amazon Affiliate Product Importer - Usage Examples

This file contains examples of how to use the Amazon Affiliate Product Importer plugin.

## Example Amazon URLs

Here are examples of supported Amazon URL formats that you can use with the plugin:

### Standard Amazon Product URLs

```
https://www.amazon.com/dp/B08N5WRWNW?tag=youraffiliattag-20
https://www.amazon.com/gp/product/B08N5WRWNW?tag=youraffiliattag-20
https://www.amazon.com/Echo-Dot-3rd-Gen/dp/B08N5WRWNW?tag=youraffiliattag-20
```

### International Amazon URLs

```
https://www.amazon.co.uk/dp/B08N5WRWNW?tag=youraffiliattag-21
https://www.amazon.de/dp/B08N5WRWNW?tag=youraffiliattag-21
https://www.amazon.fr/dp/B08N5WRWNW?tag=youraffiliattag-21
https://www.amazon.ca/dp/B08N5WRWNW?tag=youraffiliattag-20
```

### Shortened Amazon URLs

```
https://amzn.to/3xyz123?tag=youraffiliattag-20
https://amzn.com/B08N5WRWNW?tag=youraffiliattag-20
```

## Step-by-Step Import Process

### 1. Find a Product on Amazon

1. Go to Amazon.com (or your local Amazon domain)
2. Search for a product you want to promote
3. Click on the product to go to its detail page
4. Copy the URL from your browser's address bar

### 2. Add Your Affiliate Tag

If your URL doesn't already have an affiliate tag, add it:

**Before:**
```
https://www.amazon.com/dp/B08N5WRWNW
```

**After:**
```
https://www.amazon.com/dp/B08N5WRWNW?tag=youraffiliattag-20
```

### 3. Import the Product

1. Go to WordPress Admin → Amazon Importer
2. Paste the URL in the "Amazon Product URL" field
3. Select a category (optional)
4. Choose product status (Draft recommended for review)
5. Check "Import Images" if desired
6. Click "Import Product"

### 4. Review and Publish

1. Go to WooCommerce → Products
2. Find your imported product
3. Review the title, description, and price
4. Edit as needed for your audience
5. Publish when ready

## Sample Product Data

Here's an example of what gets imported for an Amazon Echo Dot:

```
Title: Echo Dot (3rd Gen) - Smart speaker with Alexa - Charcoal
Price: $49.99
Description: Meet Echo Dot - Our most popular smart speaker with a fabric design...
Images: Multiple product images automatically downloaded
Category: Electronics (if selected)
External URL: https://www.amazon.com/dp/B07FZ8S74R?tag=youraffiliattag-20
```

## Best Practices

### Choosing Products
- Select products relevant to your audience
- Choose items with good reviews and ratings
- Consider seasonal and trending products
- Verify the product is still available

### URL Management
- Always include your affiliate tag
- Use the simplest URL format (amazon.com/dp/ASIN)
- Test links before publishing
- Keep a record of your affiliate tags

### Content Optimization
- Customize product titles for SEO
- Write unique descriptions when possible
- Add your own product photos if available
- Include comparison tables or buying guides

### Legal Compliance
- Add affiliate disclosure to your site
- Follow FTC guidelines
- Respect Amazon's terms of service
- Include required disclaimers

## Troubleshooting Common Issues

### "Invalid Amazon URL" Error
```
# Bad URL:
https://amazon.com/some-invalid-format

# Good URL:
https://www.amazon.com/dp/B08N5WRWNW?tag=youraffiliattag-20
```

### "Product Already Imported" Error
- Check the "Imported Products" page
- Delete the existing product if you want to re-import
- Or edit the existing product instead

### Missing Images
- Amazon may block image downloads sometimes
- Try importing without images first
- Add images manually if needed

### Timeout Errors
- Large product pages may take time to process
- Check if the product was created despite the error
- Try again if no product was created

## Advanced Usage

### Bulk Import (Future Feature)
The plugin is designed to handle single imports efficiently. For bulk importing, consider:
- Using a spreadsheet with Amazon URLs
- Implementing rate limiting between requests
- Processing in batches during off-peak hours

### Custom Fields
The plugin automatically adds these custom fields to products:
- `_amazon_asin`: The product's ASIN
- `_amazon_url`: Original Amazon URL
- `_imported_from_amazon`: Flag indicating import source

### Hooks and Filters (For Developers)
The plugin provides hooks for customization:
- `amazon_importer_before_import`: Run code before importing
- `amazon_importer_after_import`: Run code after importing
- `amazon_importer_product_data`: Filter product data before saving

## Support and Resources

### Getting Help
- Check the plugin documentation
- Review error messages carefully
- Test with simple product URLs first
- Check WordPress and WooCommerce versions

### Amazon Associates Resources
- Amazon Associates Central: https://affiliate-program.amazon.com/
- Operating Agreement: Review terms and conditions
- Link Checker: Verify your affiliate links work
- Reporting: Monitor your earnings and clicks
