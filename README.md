# Amazon Affiliate Product Importer

A WordPress plugin that allows you to easily import Amazon products to WooCommerce with your affiliate links for the Amazon Associates program.

## Features

- **Easy Product Import**: Simply paste an Amazon product URL with your affiliate tag
- **Automatic Data Extraction**: Scrapes product title, description, price, and images
- **WooCommerce Integration**: Creates external/affiliate products in WooCommerce
- **Affiliate Link Preservation**: Maintains your Amazon affiliate tags in product URLs
- **Batch Processing**: Import multiple products efficiently
- **Image Import**: Automatically downloads and imports product images
- **Category Management**: Assign products to WooCommerce categories
- **Tracking**: Keep track of all imported products with ASIN and affiliate tag information

## Requirements

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- Valid Amazon Associates account

## Installation

1. **Download the Plugin**
   - Download all plugin files to your computer
   - Create a zip file containing all the plugin files

2. **Upload to WordPress**
   - Go to your WordPress admin dashboard
   - Navigate to Plugins → Add New → Upload Plugin
   - Choose the zip file and click "Install Now"
   - Activate the plugin

3. **Alternative Installation**
   - Upload the plugin folder to `/wp-content/plugins/`
   - Activate the plugin through the WordPress admin

## Setup

1. **Verify WooCommerce**
   - Ensure WooCommerce is installed and activated
   - The plugin will show an error if WooCommerce is not active

2. **Configure Settings**
   - Go to Amazon Importer → Settings
   - Set your default category for imported products
   - Choose whether to auto-publish or save as drafts
   - Configure image import preferences

## Usage

### Importing a Single Product

1. **Get Amazon Product URL**
   - Go to the Amazon product page you want to import
   - Copy the full URL from your browser
   - Make sure your affiliate tag is included in the URL

2. **Add Affiliate Tag**
   - If not already present, add your affiliate tag to the URL
   - Format: `?tag=your-affiliate-tag-20`
   - Example: `https://www.amazon.com/dp/B07XXXXX?tag=yourtag-20`

3. **Import the Product**
   - Go to Amazon Importer in your WordPress admin
   - Paste the Amazon URL in the text field
   - Select a product category (optional)
   - Choose product status (Draft/Published)
   - Check "Import Images" if you want to import product photos
   - Click "Import Product"

4. **Review and Edit**
   - The plugin will create a WooCommerce external product
   - Review the imported data in WooCommerce → Products
   - Edit product details as needed

### Supported URL Formats

The plugin supports various Amazon URL formats:

- `https://www.amazon.com/dp/ASIN`
- `https://www.amazon.com/gp/product/ASIN`
- `https://www.amazon.com/product-name/dp/ASIN`
- `https://amzn.to/shortlink`
- International Amazon domains (.co.uk, .de, .fr, etc.)

### Managing Imported Products

1. **View Imported Products**
   - Go to Amazon Importer → Imported Products
   - See all products imported with their ASIN and affiliate tags
   - Quick links to edit or view products

2. **Track Performance**
   - Monitor which products were imported when
   - Verify affiliate tags are correctly preserved
   - Manage product categories and status

## Configuration Options

### Plugin Settings

- **Default Category**: Set a default WooCommerce category for imported products
- **Auto Publish**: Choose to publish immediately or save as drafts
- **Image Import**: Enable/disable automatic image importing
- **Price Sync**: (Future feature) Periodic price synchronization

### Product Options

Each import allows you to configure:
- **Product Category**: Assign to specific WooCommerce categories
- **Product Status**: Draft, Published, or Private
- **Image Import**: Include product images or text only
- **Custom Fields**: Automatic ASIN and source URL tracking

## Best Practices

### Affiliate Compliance

1. **Disclosure Requirements**
   - Add proper affiliate disclosures to your site
   - Follow FTC guidelines for affiliate marketing
   - Include Amazon Associates disclaimer

2. **Link Management**
   - Always include your affiliate tag in imported URLs
   - Verify links work correctly before publishing
   - Monitor for broken or expired links

### Content Quality

1. **Product Selection**
   - Choose relevant products for your audience
   - Verify product information accuracy
   - Update descriptions to match your site's tone

2. **SEO Optimization**
   - Customize product titles for better SEO
   - Write unique product descriptions
   - Optimize product categories and tags

### Performance

1. **Image Optimization**
   - Consider image sizes for page load speed
   - Use image optimization plugins
   - Monitor storage space usage

2. **Database Management**
   - Regularly review and clean up old imports
   - Monitor plugin performance impact
   - Keep WordPress and WooCommerce updated

## Troubleshooting

### Common Issues

1. **"WooCommerce Required" Error**
   - Install and activate WooCommerce plugin
   - Ensure WooCommerce is properly configured

2. **"Invalid Amazon URL" Error**
   - Verify the URL is from a supported Amazon domain
   - Check that the URL contains a valid product ASIN
   - Ensure the URL is properly formatted

3. **"Product Already Imported" Error**
   - The ASIN has already been imported
   - Check Imported Products page to find existing product
   - Delete existing product if you want to re-import

4. **Import Timeout**
   - Large product pages may take time to process
   - Check if the product was created despite the timeout
   - Try importing again if no product was created

5. **Missing Images**
   - Amazon may block automated image downloads
   - Try importing without images and add manually
   - Check image URLs are accessible

### Debug Information

Enable WordPress debug logging to troubleshoot issues:

```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check `/wp-content/debug.log` for plugin error messages.

## Support

### Getting Help

1. **Check Documentation**
   - Review this README file
   - Check plugin settings and options

2. **WordPress Support**
   - Post in WordPress support forums
   - Include specific error messages
   - Provide plugin and WordPress version info

3. **Amazon Associates**
   - Verify your Amazon Associates account status
   - Check affiliate tag format and validity
   - Review Amazon's terms of service

## Legal Considerations

### Amazon Associates Compliance

- Maintain compliance with Amazon Associates Operating Agreement
- Include required disclosures on your website
- Follow Amazon's guidelines for affiliate marketing
- Respect Amazon's robots.txt and scraping policies

### Copyright and Content

- Respect Amazon's intellectual property rights
- Use scraped content in compliance with fair use guidelines
- Consider creating original product descriptions
- Ensure proper attribution where required

## Changelog

### Version 0.1
- Initial release
- Basic Amazon product importing
- WooCommerce integration
- Admin interface
- Image import functionality
- Product tracking system

## License

This plugin is released under the GPL v2 or later license.

## Disclaimer

This plugin is provided "as is" without warranty. Users are responsible for compliance with Amazon Associates terms, WordPress guidelines, and applicable laws. The plugin author is not responsible for any issues arising from plugin use.
