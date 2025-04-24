/**
 * Admin JavaScript for Mailgun Groundhogg integration
 */
(function($) {
    'use strict';

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Initialize tabs if they exist
        if ($('.mailgun-gh-tabs').length) {
            initTabs();
        }
        
        // Initialize date filters if they exist
        if ($('.mailgun-gh-date-filter').length) {
            initDateFilter();
        }
    });

    /**
     * Initialize tabs
     */
    function initTabs() {
        var $tabs = $('.mailgun-gh-tabs');
        var $tabLinks = $tabs.find('.mailgun-gh-tab-link');
        var $tabContents = $('.mailgun-gh-tab-content');
        
        // Hide all tab contents
        $tabContents.hide();
        
        // Show the first tab
        $tabLinks.first().addClass('active');
        $($tabLinks.first().attr('href')).show();
        
        // Handle tab clicks
        $tabLinks.on('click', function(e) {
            e.preventDefault();
            
            var target = $(this).attr('href');
            
            // Remove active class from all tabs
            $tabLinks.removeClass('active');
            
            // Add active class to current tab
            $(this).addClass('active');
            
            // Hide all tab contents
            $tabContents.hide();
            
            // Show target tab content
            $(target).show();
        });
    }

    /**
     * Initialize date filter
     */
    function initDateFilter() {
        var $dateFilter = $('.mailgun-gh-date-filter');
        var $dateRange = $dateFilter.find('select[name="date_range"]');
        var $customRange = $dateFilter.find('.mailgun-gh-custom-date-range');
        
        // Hide custom range by default
        $customRange.hide();
        
        // Show/hide custom range based on selection
        $dateRange.on('change', function() {
            if ($(this).val() === 'custom') {
                $customRange.show();
            } else {
                $customRange.hide();
            }
        });
    }

    /**
     * Copy text to clipboard
     */
    window.copyToClipboard = function(elementId) {
        var $element = $('#' + elementId);
        
        if ($element.length) {
            $element.select();
            document.execCommand('copy');
            
            // Show success message
            var $successMessage = $('<span class="mailgun-gh-copy-success">' + mailgun_gh_admin.copy_success + '</span>');
            $element.after($successMessage);
            
            // Remove success message after 2 seconds
            setTimeout(function() {
                $successMessage.fadeOut(function() {
                    $successMessage.remove();
                });
            }, 2000);
        }
    };

})(jQuery);