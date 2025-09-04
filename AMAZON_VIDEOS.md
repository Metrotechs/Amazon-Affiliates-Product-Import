# Amazon Product Videos - Complete Implementation Guide

## Overview
The Amazon Affiliate Importer now includes comprehensive video extraction and display functionality. This feature automatically detects, extracts, and displays Amazon product videos on your WooCommerce site, providing customers with rich media content while maintaining Amazon ToS compliance.

## Key Features

### 🎥 **Multi-Source Video Detection**
- **HTML Elements**: Direct video/source tags and data-video-url attributes
- **JSON Data**: JavaScript objects containing video URLs and metadata
- **Embedded Videos**: YouTube, Vimeo, and iframe-based players
- **Amazon Native**: Direct Amazon video content and thumbnails

### 📱 **Flexible Display Options**
- **Product Summary**: Videos displayed directly on product pages
- **Dedicated Tab**: Professional "Videos" tab in WooCommerce product tabs
- **Multiple Formats**: Support for MP4, WebM, YouTube, Vimeo, and generic videos
- **Responsive Design**: Mobile-friendly video playback

### 🎨 **Professional Presentation**
- **Custom Styling**: Comprehensive CSS for professional appearance
- **Play Overlays**: Visual indicators for clickable video thumbnails
- **Responsive Layout**: Adapts to different screen sizes
- **Theme Integration**: Seamless integration with WooCommerce themes

## Technical Implementation

### File Structure

#### **Enhanced Files:**
1. **`includes/class-scraper.php`** - Video extraction engine
2. **`includes/class-product.php`** - Video storage and display system
3. **`test-amazon-videos.php`** - Testing and validation script

### Core Methods Added

#### **Video Extraction (`class-scraper.php`)**

##### `extract_videos($xpath, $url)`
Main video extraction method that coordinates all video detection strategies:

```php
// Priority extraction system:
// 1. HTML video elements and data attributes
// 2. JSON data containing video URLs
// 3. Embedded iframe videos
// 4. Validation and deduplication
```

##### `extract_videos_from_json($xpath, $base_url)`
Comprehensive JSON parsing for video data:

```php
$video_patterns = array(
    '/"videoUrl":"([^"]+)"/',
    '/"mp4":"([^"]+)"/',
    '/"video":"([^"]+)"/',
    '/"src":"([^"]*\.mp4[^"]*)"/',
    '/"url":"([^"]*\.mp4[^"]*)"/'
);
```

##### `extract_iframe_videos($xpath)`
Detects embedded video players:
- YouTube iframe embeds
- Vimeo iframe embeds  
- Generic video iframes
- Amazon embedded content

##### Validation Methods
- `is_valid_video_url()` - URL format and content validation
- `get_video_type()` - Automatic video type detection
- `extract_video_thumbnail()` - Thumbnail image extraction

#### **Video Display (`class-product.php`)**

##### `display_amazon_videos()`
Renders videos directly on product summary:

```php
// Hooks into: woocommerce_single_product_summary (priority 25)
// Displays: Formatted video players with styling
// Features: Automatic video type detection and rendering
```

##### `add_videos_tab($tabs)`
Adds dedicated Videos tab to WooCommerce:

```php
$tabs['amazon_videos'] = array(
    'title'    => __('Videos', 'amazon-affiliate-importer'),
    'priority' => 25,
    'callback' => array($this, 'videos_tab_content')
);
```

##### Video Rendering Methods
- `render_video_player()` - Main video rendering coordinator
- `render_youtube_video()` - YouTube embed handling
- `render_vimeo_video()` - Vimeo embed handling
- `render_html5_video()` - Native HTML5 video players
- `render_video_link()` - Fallback clickable thumbnails

## Video Data Structure

### Storage Format
Videos are stored as WordPress post meta in the following structure:

```php
// Meta Key: _amazon_videos
array(
    array(
        'url' => 'https://example.com/video.mp4',
        'type' => 'mp4|youtube|vimeo|amazon_video|unknown',
        'thumbnail' => 'https://example.com/thumbnail.jpg',
        'source' => 'json_data|html_element|iframe'
    ),
    // Additional videos...
)

// Meta Key: _amazon_has_videos (boolean flag for quick checking)
```

### Video Types Supported

#### **1. YouTube Videos**
```php
// Detection: youtube.com or youtu.be URLs
// Rendering: Embedded iframe player
// Features: Full YouTube player controls
```

#### **2. Vimeo Videos**
```php
// Detection: vimeo.com URLs
// Rendering: Embedded Vimeo player
// Features: Vimeo player controls and branding
```

#### **3. MP4/WebM Videos**
```php
// Detection: File extension or MIME type
// Rendering: HTML5 video element
// Features: Native browser controls, poster images
```

#### **4. Amazon Videos**
```php
// Detection: Amazon domain video URLs
// Rendering: HTML5 or thumbnail link
// Features: Amazon-compliant display
```

## Video Extraction Strategies

### 1. **HTML Element Detection**
```php
$video_selectors = array(
    // Main video player
    '//div[@id="vse-related-videos"]//video/@src',
    '//div[@id="vse-related-videos"]//source/@src',
    
    // Video data attributes
    '//div[@data-video-url]/@data-video-url',
    '//span[@data-video-url]/@data-video-url',
    
    // Video thumbnails
    '//div[@id="altImages"]//span[contains(@class, "video")]/@data-video-url'
);
```

### 2. **JSON Data Extraction**
```php
$script_selectors = array(
    '//script[contains(text(), "videoUrl")]',
    '//script[contains(text(), "mp4")]',
    '//script[contains(text(), "VideoBlockATF")]',
    '//script[contains(text(), "videoBlock")]'
);
```

### 3. **Iframe Video Detection**
```php
$iframe_selectors = array(
    '//iframe[contains(@src, "video")]/@src',
    '//iframe[contains(@src, "youtube")]/@src',
    '//iframe[contains(@src, "vimeo")]/@src'
);
```

## Display Integration

### Product Summary Display
Videos appear automatically on product pages using the WooCommerce hook system:

```php
// Hook: woocommerce_single_product_summary
// Priority: 25 (after main product info, before add to cart)
// Rendering: Inline video players with custom styling
```

### Product Tabs Integration
A dedicated "Videos" tab provides organized video viewing:

```php
// Tab Title: "Videos"
// Priority: 25 (after Description and Additional Information)
// Content: All product videos with enhanced layout
```

### Responsive Design
```css
@media (max-width: 768px) {
    .amazon-video-container iframe,
    .amazon-video-container video {
        width: 100%;
        height: 200px;
    }
}
```

## Styling and Customization

### Default CSS Styling
The system includes comprehensive CSS for professional presentation:

```css
.amazon-product-videos {
    margin: 20px 0;
    padding: 20px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    background: #f9f9f9;
}

.video-play-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(0,0,0,0.7);
    color: white;
    padding: 15px 20px;
    border-radius: 50%;
    font-size: 24px;
}
```

### Customization Options
Override styles in your theme's CSS:

```css
/* Custom video container styling */
.amazon-product-videos {
    background: your-custom-color;
    border: your-custom-border;
}

/* Custom play button styling */
.video-play-overlay {
    background: your-brand-color;
}
```

## Amazon Compliance Features

### ✅ **Compliant Video Handling**
- **No Content Hosting**: Videos are linked, not downloaded or re-hosted
- **Original Sources**: All video URLs point to original Amazon content
- **External Links**: Video links open in new tabs when appropriate
- **Proper Attribution**: Amazon branding and attribution maintained

### ✅ **User Experience Compliance**
- **Amazon Redirect**: Video interactions can redirect to Amazon when appropriate
- **Affiliate Tags**: Video links maintain affiliate attribution
- **Original Context**: Videos are presented in their original Amazon context

## Benefits for Amazon Affiliate Sites

### 📈 **Conversion Improvements**
- **Product Demonstrations**: Videos show products in action
- **Informed Decisions**: Customers make better purchasing decisions
- **Reduced Returns**: Better product understanding reduces returns
- **Trust Building**: Video content builds customer confidence

### 🎯 **SEO Benefits**
- **Rich Media Content**: Search engines favor pages with diverse content
- **Engagement Metrics**: Videos increase time on page
- **Rich Snippets**: Video content may appear in search results
- **Content Differentiation**: Stand out from competitors with video content

### 💼 **Professional Presentation**
- **Modern Interface**: Video integration creates a modern, professional look
- **Brand Credibility**: Rich media content enhances site credibility
- **User Engagement**: Interactive content keeps visitors engaged
- **Mobile Experience**: Responsive video playback improves mobile UX

## Testing and Validation

### Using the Test Script
The `test-amazon-videos.php` script provides comprehensive testing:

```php
// Test video extraction on sample URLs
// Validate video data structure
// Demonstrate display rendering
// Check compliance features
```

### Manual Testing Checklist
1. **Video Detection**: Verify videos are found and extracted
2. **Display Integration**: Check both summary and tab display
3. **Player Functionality**: Test different video types play correctly
4. **Responsive Design**: Verify mobile-friendly display
5. **Amazon Compliance**: Ensure proper linking and attribution

## Troubleshooting

### Common Issues

#### **No Videos Found**
```php
// Check if product actually has videos on Amazon
// Verify scraping selectors are up to date
// Check for Amazon page structure changes
// Review error logs for extraction issues
```

#### **Videos Not Displaying**
```php
// Verify _amazon_has_videos meta is set
// Check WooCommerce hooks are working
// Ensure theme compatibility
// Review CSS conflicts
```

#### **Player Issues**
```php
// Verify video URLs are accessible
// Check for CORS or security restrictions
// Test with different video types
// Validate HTML5 video support
```

### Debug Information
The system includes error logging for troubleshooting:

```php
error_log('Amazon Importer: Found ' . count($videos) . ' videos for URL: ' . $url);
```

## Future Enhancements

### Potential Improvements
1. **Video Caching**: Local thumbnail caching for better performance
2. **Video Galleries**: Multiple video organization and navigation
3. **Video Analytics**: Track video engagement and conversions
4. **Advanced Players**: Custom video player with Amazon branding
5. **Video SEO**: Schema markup for video rich snippets

## Integration Summary

The Amazon video system provides:

- ✅ **Automatic Detection**: No manual video entry required
- ✅ **Professional Display**: Multiple viewing options with custom styling
- ✅ **Amazon Compliance**: Maintains all affiliate program requirements
- ✅ **Responsive Design**: Works on all devices and screen sizes
- ✅ **SEO Benefits**: Enhanced content for better search rankings
- ✅ **Conversion Focus**: Rich media content improves sales potential

This comprehensive video system transforms your Amazon affiliate site into a rich media destination that provides customers with detailed product information while maintaining full compliance with Amazon's affiliate program requirements.
