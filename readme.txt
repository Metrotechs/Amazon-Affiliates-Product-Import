=== Amazon Affiliate Product Importer ===
Contributors: yourname
Tags: amazon, affiliate, woocommerce, product, import, scraper
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import Amazon products to WooCommerce with your affiliate links for the Amazon Associates program.

== Description ==

The Amazon Affiliate Product Importer plugin allows you to easily import Amazon products to your WooCommerce store while preserving your Amazon Associates affiliate links. Perfect for affiliate marketers who want to showcase Amazon products on their WordPress sites.

= Features =

* **Easy Product Import**: Simply paste an Amazon product URL with your affiliate tag
* **Automatic Data Extraction**: Scrapes product title, description, price, and images
* **WooCommerce Integration**: Creates external/affiliate products in WooCommerce
* **Affiliate Link Preservation**: Maintains your Amazon affiliate tags in product URLs
* **Image Import**: Automatically downloads and imports product images
* **Category Management**: Assign products to WooCommerce categories
* **Product Tracking**: Keep track of all imported products with ASIN and affiliate tag information
* **Multiple Amazon Domains**: Supports amazon.com, amazon.co.uk, amazon.de, and more

= How It Works =

1. Copy an Amazon product URL from your browser
2. Add your Amazon Associates affiliate tag to the URL
3. Paste the URL into the plugin's import form
4. Select category and import options
5. Click "Import Product" and the plugin does the rest!

= Supported URL Formats =

* `amazon.com/dp/ASIN`
* `amazon.com/gp/product/ASIN`
* `amzn.to/shortlink`
* International Amazon domains
* URLs with existing affiliate tags

= Requirements =

* WordPress 5.0 or higher
* WooCommerce 5.0 or higher
* PHP 7.4 or higher
* Valid Amazon Associates account

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/amazon-affiliate-importer/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Ensure WooCommerce is installed and activated
4. Go to Amazon Importer in your WordPress admin to start importing products

== Frequently Asked Questions ==

= Do I need an Amazon Associates account? =

Yes, you need a valid Amazon Associates account to use affiliate links. The plugin helps you import products with your affiliate tags, but you must sign up for the Amazon Associates program separately.

= Can I import products from international Amazon sites? =

Yes, the plugin supports multiple Amazon domains including amazon.com, amazon.co.uk, amazon.de, amazon.fr, amazon.it, amazon.es, amazon.ca, amazon.com.au, amazon.co.jp, and amazon.in.

= Will this plugin automatically add my affiliate tag? =

No, you need to include your affiliate tag in the Amazon URL before importing. For example: `https://amazon.com/dp/B12345?tag=yourtag-20`

= Can I import product images? =

Yes, the plugin can automatically download and import product images from Amazon. This feature can be enabled or disabled for each import.

= What happens if I import the same product twice? =

The plugin checks for duplicate ASINs and will prevent importing the same product multiple times.

= Is this plugin free? =

Yes, this plugin is completely free and open source under the GPL license.

== Screenshots ==

1. Main import interface with URL input field
2. Product import form with category and options selection
3. Imported products management page
4. Plugin settings page
5. Successfully imported WooCommerce product

== Changelog ==

= 1.0.0 =
* Initial release
* Basic Amazon product importing functionality
* WooCommerce integration
* Admin interface for product import
* Image import capability
* Product tracking system
* Support for multiple Amazon domains
* Affiliate link preservation

== Upgrade Notice ==

= 1.0.0 =
Initial release of the Amazon Affiliate Product Importer plugin.

== Additional Notes ==

= Legal Compliance =

This plugin is designed to help Amazon Associates import product information. Users are responsible for:
* Complying with Amazon Associates Operating Agreement
* Adding proper affiliate disclosures to their websites
* Following FTC guidelines for affiliate marketing
* Respecting Amazon's robots.txt and scraping policies

= Support =

For support and documentation, please refer to the plugin's README file and examples. This plugin is provided as-is under the GPL license.

= Contributing =

This plugin is open source and contributions are welcome. Please follow WordPress coding standards and test thoroughly before submitting changes.
