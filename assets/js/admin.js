/**
 * Unified Site Health Dashboard - Admin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize dashboard
    initDashboard();
    
    /**
     * Initialize dashboard functionality
     */
    function initDashboard() {
        bindEvents();
        updateLastScanTime();
    }
    
    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Rescan button
        $('#ush-rescan-btn').on('click', function(e) {
            e.preventDefault();
            runNewScan();
        });
        
        // Export button
        $('#ush-export-btn').on('click', function(e) {
            e.preventDefault();
            exportReport();
        });
        
        // Auto-fix button
        $('#ush-auto-fix-btn').on('click', function(e) {
            e.preventDefault();
            runAutoFix();
        });
        
        // Auto-refresh every 5 minutes
        setInterval(function() {
            // updateLastScanTime();
        }, 300000); // 5 minutes

        // Page-wise: post type select submits filter
        $(document).on('change', '#ush-post-type-select', function(){
            $(this).closest('form.ush-filter-bar')[0].submit();
        });
        // Page-wise: page dropdown triggers AJAX load
        function loadPageReport(pageUrl){
            if (!pageUrl) return;
            var $container = $('#ush-page-report-ajax');
            $container.html('<div class="ush-loader"><span class="spinner is-active" style="float:none"></span> ' + ush_ajax.strings.loading + '</div>');
            $.post(ush_ajax.ajax_url, { action: 'ush_get_page_report', nonce: ush_ajax.nonce, page_url: pageUrl }, function(res){
                if (res && res.success) {
                    $container.html(res.data.html);
                } else {
                    $container.html('<div class="notice notice-error"><p>' + (res && res.data && res.data.message ? res.data.message : ush_ajax.strings.error) + '</p></div>');
                }
            }).fail(function(){
                $container.html('<div class="notice notice-error"><p>' + ush_ajax.strings.error + '</p></div>');
            });
        }
        $(document).on('change', '#ush-page-dropdown', function(){
            loadPageReport($(this).val());
        });
        $(document).on('click', '.ush-load-page-report', function(e){
            e.preventDefault();
            var url = $(this).data('url');
            $('#ush-page-dropdown').val(url);
            loadPageReport(url);
            $('.ush-page-row').removeClass('is-active');
            $(this).closest('tr').addClass('is-active');
        });

        // Tile modal open/close
        $(document).on('click keypress', '.ush-tile-modal-trigger', function(e){
            if (e.type === 'click' || (e.type === 'keypress' && (e.which === 13 || e.which === 32))) {
                e.preventDefault();
                var section = $(this).data('section');
                var $modal = ensureModal();
                $modal.addClass('open');
                $modal.find('.ush-modal-content').html('<div class="ush-loader"><span class="spinner is-active" style="float:none"></span> ' + ush_ajax.strings.loading + '</div>');
                $.post(ush_ajax.ajax_url, { action: 'ush_get_section_issues', nonce: ush_ajax.nonce, section: section }, function(res){
                    if (res && res.success) {
                        $modal.find('.ush-modal-content').html(res.data.html);
                    } else {
                        $modal.find('.ush-modal-content').html('<div class="notice notice-error"><p>' + (res && res.data && res.data.message ? res.data.message : ush_ajax.strings.error) + '</p></div>');
                    }
                }).fail(function(){
                    $modal.find('.ush-modal-content').html('<div class="notice notice-error"><p>' + ush_ajax.strings.error + '</p></div>');
                });
            }
        });
        $(document).on('click', '.ush-modal-close, .ush-modal-overlay', function(){
            $('#ush-issues-modal').removeClass('open');
        });

        // Rescan page button
        $(document).on('click', '#ush-page-rescan', function(e){
            e.preventDefault();
            var pageUrl = $(this).data('page-url');
            if (!pageUrl) return;
            var $btn = $(this);
            var $container = $('#ush-page-report-ajax');
            $btn.prop('disabled', true);
            $container.html('<div class="ush-loader"><span class="spinner is-active" style="float:none"></span> ' + ush_ajax.strings.scanning + '</div>');
            $.post(ush_ajax.ajax_url, { action: 'ush_rescan_page', nonce: ush_ajax.nonce, page_url: pageUrl }, function(res){
                if (res && res.success) {
                    $container.html(res.data.html);
                } else {
                    $container.html('<div class="notice notice-error"><p>' + (res && res.data && res.data.message ? res.data.message : ush_ajax.strings.error) + '</p></div>');
                }
            }).fail(function(){
                $container.html('<div class="notice notice-error"><p>' + ush_ajax.strings.error + '</p></div>');
            }).always(function(){
                $btn.prop('disabled', false);
            });
        });
    }
    
    /**
     * Run new scan
     */
    function runNewScan() {
        var $button = $('#ush-rescan-btn');
        var originalText = $button.text();
        
        // Show loading state
        $button.prop('disabled', true).text(ush_ajax.strings.scanning);
        $('.ush-dashboard').addClass('ush-loading');
        
        // Make AJAX request
        $.ajax({
            url: ush_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ush_run_scan',
                nonce: ush_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Reload page to show new results
                    location.reload();
                } else {
                    showError(response.data || ush_ajax.strings.error);
                }
            },
            error: function() {
                showError(ush_ajax.strings.error);
            },
            complete: function() {
                // Reset button state
                $button.prop('disabled', false).text(originalText);
                $('.ush-dashboard').removeClass('ush-loading');
            }
        });
    }
    
    /**
     * Export report
     */
    function exportReport() {
        // Make AJAX request to generate export
        $.ajax({
            url: ush_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ush_export_report',
                nonce: ush_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.url) {
                    // Download the file
                    window.open(response.data.url, '_blank');
                } else {
                    showError('Export failed');
                }
            },
            error: function() {
                showError('Export failed');
            }
        });
    }
    
    /**
     * Run auto-fix
     */
    function runAutoFix() {
        if (!confirm('Are you sure you want to apply auto-fixes? This action cannot be undone.')) {
            return;
        }
        
        var $button = $('#ush-auto-fix-btn');
        var originalText = $button.text();
        
        // Show loading state
        $button.prop('disabled', true).text('Applying fixes...');
        $('.ush-detailed-report').addClass('ush-loading');
        
        // Make AJAX request
        $.ajax({
            url: ush_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ush_auto_fix',
                nonce: ush_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data.message);
                    
                    // Show applied fixes
                    if (response.data.fixes && response.data.fixes.length > 0) {
                        var fixesList = '<ul>';
                        response.data.fixes.forEach(function(fix) {
                            fixesList += '<li>' + fix + '</li>';
                        });
                        fixesList += '</ul>';
                        
                        showSuccess('Applied fixes: ' + fixesList);
                    }
                    
                    // Reload page after a delay to show updated results
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showError(response.data.message || 'Auto-fix failed');
                }
            },
            error: function() {
                showError('Auto-fix failed');
            },
            complete: function() {
                // Reset button state
                $button.prop('disabled', false).text(originalText);
                $('.ush-detailed-report').removeClass('ush-loading');
            }
        });
    }
    
    /**
     * Update last scan time
     */
    function updateLastScanTime() {
        // This would typically make an AJAX call to get the last scan time
        // For now, we'll just update the display if needed
        var $lastScan = $('.ush-last-scan');
        if ($lastScan.length) {
            // Update timestamp display
            var now = new Date();
            $lastScan.text('Last updated: ' + now.toLocaleTimeString());
        }
    }
    
    /**
     * Show error message
     */
    function showError(message) {
        // Remove existing error messages
        $('.ush-error-message').remove();
        
        // Create error message
        var $error = $('<div class="ush-error-message notice notice-error"><p>' + message + '</p></div>');
        
        // Insert at top of dashboard
        $('.ush-dashboard').prepend($error);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            $error.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    /**
     * Show success message
     */
    function showSuccess(message) {
        // Remove existing success messages
        $('.ush-success-message').remove();
        
        // Create success message
        var $success = $('<div class="ush-success-message notice notice-success"><p>' + message + '</p></div>');
        
        // Insert at top of dashboard
        $('.ush-dashboard').prepend($success);
        
        // Auto-remove after 3 seconds
        setTimeout(function() {
            $success.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    /**
     * Animate score circles
     */
    function animateScoreCircles() {
        $('.ush-score-circle').each(function() {
            var $circle = $(this);
            var score = parseInt($circle.find('.ush-score-value').text());
            
            // Animate the circle based on score
            $circle.css({
                'transform': 'scale(0)',
                'transition': 'transform 0.5s ease-out'
            });
            
            setTimeout(function() {
                $circle.css('transform', 'scale(1)');
            }, 100);
        });
    }
    
    /**
     * Initialize score circle animations
     */
    setTimeout(function() {
        animateScoreCircles();
    }, 500);
    
    /**
     * Add hover effects to sections
     */
    $('.ush-section').hover(
        function() {
            // $(this).css('transform', 'translateY(-2px)');
        },
        function() {
            // $(this).css('transform', 'translateY(0)');
        }
    );
    
    /**
     * Add click handlers for detailed views
     */
    $('.ush-section').on('click', function() {
        var sectionType = $(this).attr('class').match(/ush-(\w+)/)[1];
        showSectionDetails(sectionType);
    });
    
    /**
     * Show section details in modal or expanded view
     */
    function showSectionDetails(sectionType) {
        // Open modal via AJAX
        $('.ush-open-issues-modal[data-section="' + sectionType + '"]').trigger('click');
    }
    
    /**
     * Initialize tooltips for metrics
     */
    $('.ush-metric').each(function() {
        var $metric = $(this);
        var label = $metric.find('.ush-metric-label').text();
        var value = $metric.find('.ush-metric-value').text();
        
        // Add title attribute for tooltip
        $metric.attr('title', label + ': ' + value);
    });
    
    /**
     * Add keyboard navigation support
     */
    $(document).on('keydown', function(e) {
        // Ctrl+R to refresh scan
        if (e.ctrlKey && e.keyCode === 82) {
            e.preventDefault();
            runNewScan();
        }
        
        // Ctrl+E to export
        if (e.ctrlKey && e.keyCode === 69) {
            e.preventDefault();
            exportReport();
        }
    });
    
    /**
     * Add real-time updates (if WebSocket or Server-Sent Events are available)
     */
    function initRealTimeUpdates() {
        // This would connect to a real-time update system
        // For now, we'll just poll for updates every 30 seconds
        setInterval(function() {
            checkForUpdates();
        }, 30000);
    }

    function ensureModal(){
        var $modal = $('#ush-issues-modal');
        if ($modal.length === 0) {
            $('body').append('<div id="ush-issues-modal" class="ush-modal"><div class="ush-modal-overlay"></div><div class="ush-modal-dialog"><button class="ush-modal-close" aria-label="Close">×</button><div class="ush-modal-content"></div></div></div>');
            $modal = $('#ush-issues-modal');
        }
        return $modal;
    }
    
    /**
     * Check for updates
     */
    function checkForUpdates() {
        $.ajax({
            url: ush_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ush_check_updates',
                nonce: ush_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.updated) {
                    showSuccess('New scan results available!');
                    // Optionally auto-refresh
                    // location.reload();
                }
            }
        });
    }
    
    // Initialize real-time updates
    initRealTimeUpdates();
});
