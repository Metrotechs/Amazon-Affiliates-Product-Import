jQuery(document).ready(function($) {
    'use strict';
    
    // Handle form submission
    $('#amazon-import-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitButton = $form.find('#submit');
        var $spinner = $form.find('.spinner');
        var $results = $('#import-results');
        var $successDiv = $('#import-success');
        var $errorDiv = $('#import-error');
        
        // Validate URL
        var amazonUrl = $('#amazon_url').val().trim();
        if (!amazonUrl) {
            alert(amazonImporter.strings.invalid_url);
            return;
        }
        
        // Check if it's a valid Amazon URL
        var amazonUrlPattern = /(amazon\.(com|co\.uk|de|fr|it|es|ca|com\.au|co\.jp|in)|amzn\.to|amzn\.com)/i;
        if (!amazonUrlPattern.test(amazonUrl)) {
            alert(amazonImporter.strings.invalid_url);
            return;
        }
        
        // Show loading state
        $submitButton.prop('disabled', true);
        $spinner.addClass('is-active');
        $results.hide();
        $successDiv.hide();
        $errorDiv.hide();
        
        // Prepare form data
        var formData = {
            action: 'import_amazon_product',
            nonce: amazonImporter.nonce,
            amazon_url: amazonUrl,
            product_category: $('#product_category').val(),
            product_status: $('#product_status').val(),
            import_images: $('#import_images').is(':checked') ? 1 : 0,
            category_handling: $('#category_handling').val(),
            use_extracted_categories: $('#use_extracted_categories').is(':checked') ? 1 : 0
        };
        
        // Add extracted categories if using them
        if ($('#use_extracted_categories').is(':checked')) {
            var extractedCategories = [];
            $('input[name="extracted_categories[]"]:checked').each(function() {
                extractedCategories.push($(this).val());
            });
            formData.extracted_categories = extractedCategories;
        }
        
        // Submit AJAX request
        $.ajax({
            url: amazonImporter.ajax_url,
            type: 'POST',
            data: formData,
            dataType: 'json',
            timeout: 60000, // 60 seconds timeout
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $successDiv.find('p').html(
                        amazonImporter.strings.success + ' ' +
                        '<a href="' + response.data.edit_url + '" target="_blank">Edit Product</a> | ' +
                        '<a href="' + response.data.view_url + '" target="_blank">View Product</a>'
                    );
                    $successDiv.show();
                    
                    // Clear form and category preview
                    $form[0].reset();
                    $('#category-preview-container').empty();
                } else {
                    // Show error message
                    var errorMessage = response.data && response.data.message ? 
                        response.data.message : amazonImporter.strings.error;
                    $errorDiv.find('#error-message').text(errorMessage);
                    $errorDiv.show();
                }
                
                $results.show();
            },
            error: function(xhr, status, error) {
                var errorMessage = amazonImporter.strings.error;
                
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. The import might still be processing in the background.';
                } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                }
                
                $errorDiv.find('#error-message').text(errorMessage);
                $errorDiv.show();
                $results.show();
            },
            complete: function() {
                // Hide loading state
                $submitButton.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
    
    // Category extraction preview functionality
    $('#extract-categories-preview').on('click', function() {
        var url = $('#amazon_url').val();
        
        if (!url) {
            alert('Please enter an Amazon URL first.');
            return;
        }
        
        var button = $(this);
        button.prop('disabled', true).text('Extracting...');            $.ajax({
                url: amazonImporter.ajax_url,
                type: 'POST',
                data: {
                    action: 'extract_categories_preview',
                    amazon_url: url,
                    nonce: amazonImporter.nonce
                },
            success: function(response) {
                if (response.success) {
                    displayCategoryPreview(response.data);
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                alert('Network error occurred. Please try again.');
            },
            complete: function() {
                button.prop('disabled', false).text('Preview Categories');
            }
        });
    });
    
    // Display category preview
    function displayCategoryPreview(data) {
        var html = '<div class="category-preview">';
        html += '<h4>Extracted Categories:</h4>';
        
        if (data.breadcrumbs && data.breadcrumbs.length > 0) {
            html += '<div class="breadcrumb-display">';
            html += '<strong>Breadcrumb Path:</strong> ';
            html += data.breadcrumbs.join(' > ');
            html += '</div>';
        }
        
        if (data.categories && data.categories.length > 0) {
            html += '<div class="categories-list">';
            html += '<strong>Available Categories:</strong>';
            html += '<ul>';
            data.categories.forEach(function(category) {
                html += '<li>';
                html += '<label>';
                html += '<input type="checkbox" name="extracted_categories[]" value="' + category.name + '" checked>';
                html += ' ' + category.name;
                if (category.exists) {
                    html += ' <span class="exists">(exists)</span>';
                } else {
                    html += ' <span class="new">(will be created)</span>';
                }
                html += '</label>';
                html += '</li>';
            });
            html += '</ul>';
            html += '</div>';
        }
        
        html += '<div class="category-actions">';
        html += '<label>';
        html += '<input type="checkbox" id="use_extracted_categories" name="use_extracted_categories" checked>';
        html += ' Use extracted categories for this product';
        html += '</label>';
        html += '</div>';
        
        html += '</div>';
        
        $('#category-preview-container').html(html);
    }
    
    // Toggle category extraction options
    $('#category_handling').on('change', function() {
        var value = $(this).val();
        
        if (value === 'extract') {
            $('#category-extraction-options').show();
            $('#manual-category-selection').hide();
        } else if (value === 'manual') {
            $('#category-extraction-options').hide();
            $('#manual-category-selection').show();
        } else {
            $('#category-extraction-options').hide();
            $('#manual-category-selection').hide();
        }
    });
    
    // Initialize form state
    $('#category_handling').trigger('change');
    
    // Category management page functionality
    if ($('#categories-management').length > 0) {
        initCategoryManagement();
    }
    
    function initCategoryManagement() {
        // Fix broken categories
        $('#fix-broken-categories').on('click', function() {
            var button = $(this);
            button.prop('disabled', true).text('Fixing...');                $.ajax({
                    url: amazonImporter.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'fix_broken_categories',
                        nonce: amazonImporter.nonce
                    },
                success: function(response) {
                    if (response.success) {
                        alert('Fixed ' + response.data.fixed + ' broken categories.');
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Network error occurred. Please try again.');
                },
                complete: function() {
                    button.prop('disabled', false).text('Fix Broken Categories');
                }
            });
        });
        
        // Merge duplicate categories
        $('#merge-duplicates').on('click', function() {
            var button = $(this);
            button.prop('disabled', true).text('Merging...');                $.ajax({
                    url: amazonImporter.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'merge_duplicate_categories',
                        nonce: amazonImporter.nonce
                    },
                success: function(response) {
                    if (response.success) {
                        alert('Merged ' + response.data.merged + ' duplicate categories.');
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Network error occurred. Please try again.');
                },
                complete: function() {
                    button.prop('disabled', false).text('Merge Duplicates');
                }
            });
        });
        
        // Toggle category tree display
        $('.category-toggle').on('click', function() {
            var categoryId = $(this).data('category-id');
            var childrenContainer = $('#children-' + categoryId);
            
            if (childrenContainer.is(':visible')) {
                childrenContainer.hide();
                $(this).text('+');
            } else {
                childrenContainer.show();
                $(this).text('-');
            }
        });
        
        // Delete category
        $('.delete-category').on('click', function() {
            var categoryId = $(this).data('category-id');
            var categoryName = $(this).data('category-name');
            
            if (confirm('Are you sure you want to delete the category "' + categoryName + '"? This action cannot be undone.')) {
                $.ajax({
                    url: amazonImporter.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'delete_category',
                        category_id: categoryId,
                        nonce: amazonImporter.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#category-' + categoryId).remove();
                            alert('Category deleted successfully.');
                        } else {
                            alert('Error: ' + (response.data || 'Unknown error'));
                        }
                    },
                    error: function() {
                        alert('Network error occurred. Please try again.');
                    }
                });
            }
        });
    }
    
    // Auto-detect affiliate tag in URL
    $('#amazon_url').on('blur', function() {
        var url = $(this).val();
        var tagMatch = url.match(/[?&]tag=([^&]+)/);
        
        if (tagMatch) {
            // Show detected affiliate tag
            var affiliateTag = tagMatch[1];
            if (!$('.affiliate-tag-notice').length) {
                $(this).after('<p class="affiliate-tag-notice description" style="color: green;">✓ Affiliate tag detected: <strong>' + affiliateTag + '</strong></p>');
            }
        } else {
            // Remove notice if no tag found
            $('.affiliate-tag-notice').remove();
        }
    });
    
    // URL format helper
    $('#amazon_url').on('focus', function() {
        if (!$(this).val()) {
            $(this).attr('placeholder', 'https://www.amazon.com/dp/B07XXXXX?tag=your-affiliate-tag');
        }
    });
    
    // Add copy button for imported product URLs
    $(document).on('click', '.copy-url-btn', function(e) {
        e.preventDefault();
        var url = $(this).data('url');
        
        // Create temporary input to copy URL
        var $temp = $('<input>');
        $('body').append($temp);
        $temp.val(url).select();
        document.execCommand('copy');
        $temp.remove();
        
        // Show feedback
        var $btn = $(this);
        var originalText = $btn.text();
        $btn.text('Copied!').addClass('copied');
        
        setTimeout(function() {
            $btn.text(originalText).removeClass('copied');
        }, 2000);
    });
    
    // Validate Amazon URL format on input
    $('#amazon_url').on('input', function() {
        var url = $(this).val().trim();
        var $input = $(this);
        
        if (url) {
            var amazonUrlPattern = /(amazon\.(com|co\.uk|de|fr|it|es|ca|com\.au|co\.jp|in)|amzn\.to|amzn\.com)/i;
            
            if (amazonUrlPattern.test(url)) {
                $input.removeClass('invalid-url').addClass('valid-url');
            } else {
                $input.removeClass('valid-url').addClass('invalid-url');
            }
        } else {
            $input.removeClass('valid-url invalid-url');
        }
    });
    
    // Auto-scroll to results when they appear
    $('#import-results').on('show', function() {
        $('html, body').animate({
            scrollTop: $(this).offset().top - 100
        }, 500);
    });
    
    // Show/hide results with animation
    var originalShow = $.fn.show;
    $('#import-results').show = function() {
        $(this).trigger('show');
        return originalShow.apply(this, arguments);
    };
    
    // Enhanced Category management functionality
    if ($('#scan-categories').length) {
        $('#scan-categories').on('click', function() {
            var $button = $(this);
            var $results = $('#category-tools-results');
            
            $button.prop('disabled', true).text('Scanning...');
            $results.html('<div class="notice notice-info"><p>Scanning for category issues...</p></div>');
            
            $.ajax({
                url: amazonImporter.ajax_url,
                type: 'POST',
                data: {
                    action: 'amazon_scan_categories',
                    nonce: amazonImporter.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var html = '<div class="notice notice-success"><p>Scan completed:</p><ul>';
                        response.data.issues.forEach(function(issue) {
                            html += '<li>' + issue.type + ': ' + issue.message + '</li>';
                        });
                        html += '</ul></div>';
                        $results.html(html);
                    } else {
                        $results.html('<div class="notice notice-error"><p>Error: ' + (response.data.message || 'Unknown error') + '</p></div>');
                    }
                },
                error: function() {
                    $results.html('<div class="notice notice-error"><p>AJAX request failed</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Scan for Issues');
                }
            });
        });
        
        $('#fix-categories').on('click', function() {
            var $button = $(this);
            var $results = $('#category-tools-results');
            
            if (!confirm('This will fix broken categories. Continue?')) {
                return;
            }
            
            $button.prop('disabled', true).text('Fixing...');
            $results.html('<div class="notice notice-info"><p>Fixing category issues...</p></div>');
            
            $.ajax({
                url: amazonImporter.ajax_url,
                type: 'POST',
                data: {
                    action: 'amazon_fix_categories',
                    nonce: amazonImporter.nonce,
                    dry_run: false
                },
                success: function(response) {
                    if (response.success) {
                        var html = '<div class="notice notice-success"><p>' + response.data.message + '</p></div>';
                        $results.html(html);
                        // Refresh the category tree
                        loadCategoryTree();
                    } else {
                        $results.html('<div class="notice notice-error"><p>Error: ' + (response.data.message || 'Unknown error') + '</p></div>');
                    }
                },
                error: function() {
                    $results.html('<div class="notice notice-error"><p>AJAX request failed</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Fix Broken Categories');
                }
            });
        });
        
        $('#refresh-tree').on('click', function() {
            loadCategoryTree();
        });
        
        $('#show-amazon-only').on('change', function() {
            loadCategoryTree();
        });
        
        // Load category tree on page load
        loadCategoryTree();
    }
    
    function loadCategoryTree() {
        var $container = $('#category-tree-container');
        var amazonOnly = $('#show-amazon-only').is(':checked');
        
        $container.html('<div class="loading">Loading categories...</div>');
        
        $.ajax({
            url: amazonImporter.ajax_url,
            type: 'POST',
            data: {
                action: 'amazon_get_category_tree',
                nonce: amazonImporter.nonce,
                amazon_only: amazonOnly
            },
            success: function(response) {
                if (response.success) {
                    var html = buildCategoryTreeHTML(response.data.tree);
                    $container.html(html);
                } else {
                    $container.html('<div class="notice notice-error"><p>Error loading categories: ' + (response.data.message || 'Unknown error') + '</p></div>');
                }
            },
            error: function() {
                $container.html('<div class="notice notice-error"><p>Failed to load categories</p></div>');
            }
        });
    }
    
    function buildCategoryTreeHTML(categories) {
        if (!categories || categories.length === 0) {
            return '<p>No categories found.</p>';
        }
        
        var html = '<ul class="category-tree">';
        categories.forEach(function(category) {
            html += '<li>';
            html += '<span class="category-name">' + category.name + '</span>';
            html += ' <span class="category-count">(' + category.count + ')</span>';
            if (category.amazon_imported) {
                html += ' <span class="amazon-badge">Amazon</span>';
            }
            if (category.children && category.children.length > 0) {
                html += buildCategoryTreeHTML(category.children);
            }
            html += '</li>';
        });
        html += '</ul>';
        return html;
    }
});
