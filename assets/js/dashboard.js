/**
 * Dashboard JavaScript for Unified Site Health Dashboard
 *
 * @package Unified_Site_Health_Dashboard
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Dashboard object
    var USHDashboard = {
        
    // Track last known scan status to avoid infinite reloads
    lastStatus: null,
        
        // Initialize
        init: function() {
            this.bindEvents();
            var self = this;
            // Check current scan status; start polling only if a scan is running or auto_scan is enabled.
            $.ajax({
                url: ush_ajax.ajax_url,
                type: 'POST',
                data: { action: 'ush_get_scan_progress', nonce: ush_ajax.nonce },
                success: function(response) {
                    if (response.success) {
                        var status = response.data.status;
                        // If a scan is currently running, start polling.
                        if (status === 'running') {
                            self.showProgressBar();
                            self.startProgressPolling();
                            return;
                        }
                        // If auto_scan setting is enabled, start polling in background to show periodic updates.
                        if (typeof ush_ajax.auto_scan !== 'undefined' && ush_ajax.auto_scan) {
                            self.showProgressBar();
                            self.startProgressPolling();
                        }
                    }
                }
            });
        },
        
        // Bind events
        bindEvents: function() {
            // Start scan button
            $(document).on('click', '#ush-start-scan', this.startScan);
            
            // Modal triggers
            $(document).on('click', '.ush-tile-modal-trigger', this.openModal);
            
            // Modal close
            $(document).on('click', '.ush-modal-close', this.closeModal);
            $(document).on('click', '.ush-modal', function(e) {
                if (e.target === this) {
                    USHDashboard.closeModal();
                }
            });
            
            // Tab switching
            $(document).on('click', '.ush-tab-button', this.switchTab);
            
            // Escape key to close modal
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27) { // Escape key
                    USHDashboard.closeModal();
                }
            });
        },
        
        // Start scan
        startScan: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            // Disable button and show loading
            $button.prop('disabled', true).text(ush_ajax.strings.scan_started);
            
            // Show progress bar immediately with connection test message
            USHDashboard.showProgressBar();
            USHDashboard.showConnectionTest();
            // Start polling to reflect progress updates. Force start because the backend may not have set the
            // progress option yet when the start_scan AJAX call returns.
            USHDashboard.startProgressPolling(true);
            
            // Make AJAX request
            $.ajax({
                url: ush_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ush_start_scan',
                    nonce: ush_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Progress bar is already shown, just continue polling
                        console.log('Scan started successfully');
                    } else {
                        USHDashboard.showError(response.data.message || ush_ajax.strings.scan_error);
                        $button.prop('disabled', false).text(originalText);
                        USHDashboard.hideProgressBar();
                    }
                },
                error: function() {
                    USHDashboard.showError(ush_ajax.strings.scan_error);
                    $button.prop('disabled', false).text(originalText);
                    USHDashboard.hideProgressBar();
                }
            });
        },
        
        // Show progress bar
        showProgressBar: function() {
            var $progress = $('#ush-scan-progress');
            if ($progress.length === 0) {
                // Create progress bar if it doesn't exist
                var progressHtml = '<div class="ush-scan-progress" id="ush-scan-progress">' +
                    '<div class="ush-progress-header">' +
                        '<h3>' + ush_ajax.strings.scanning + '</h3>' +
                        '<span class="ush-progress-percentage">0%</span>' +
                    '</div>' +
                    '<div class="ush-progress-bar">' +
                        '<div class="ush-progress-fill" style="width: 0%"></div>' +
                    '</div>' +
                    '<div class="ush-progress-details">' +
                        '<p class="ush-current-page">' + ush_ajax.strings.scanning + '</p>' +
                    '</div>' +
                    // placeholder for per-category states
                    '<div class="ush-category-states" style="margin-top:10px;">' +
                        '<ul class="ush-category-state-list" style="list-style:none;padding-left:0;margin:0"></ul>' +
                    '</div>' +
                '</div>';
                
                $('.ush-scan-controls').after(progressHtml);
            }
            $progress.show();
        },
        
        // Hide progress bar
        hideProgressBar: function() {
            $('#ush-scan-progress').hide();
        },
        
        // Show connection test message
        showConnectionTest: function() {
            var $progress = $('#ush-scan-progress');
            if ($progress.length > 0) {
                $progress.find('.ush-current-page').text('Testing API connection...');
                $progress.find('.ush-progress-percentage').text('0%');
                $progress.find('.ush-progress-fill').css('width', '0%');
            }
        },
        
        // Start progress polling
        // Start progress polling (guarded): verifies a scan is running before polling
        startProgressPolling: function(force) {
            var self = this;
            force = !!force;
            if (this.progressInterval) {
                clearInterval(this.progressInterval);
            }

            var start = function() {
                self.progressInterval = setInterval(function() {
                    USHDashboard.checkProgress();
                    USHDashboard.fetchCategoryScores();
                }, 2000); // Check every 2 seconds
            };

            if (force) {
                start();
                return;
            }

            // Perform a quick check to ensure a scan is running before polling in background
            $.ajax({
                url: ush_ajax.ajax_url,
                type: 'POST',
                data: { action: 'ush_get_scan_progress', nonce: ush_ajax.nonce },
                success: function(response) {
                    if (response.success && response.data && response.data.status === 'running') {
                        start();
                    }
                }
            });
        },
        
        // Check scan progress
        checkProgress: function() {
            $.ajax({
                url: ush_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ush_get_scan_progress',
                    nonce: ush_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Remember previous status
                        var prevStatus = USHDashboard.lastStatus;
                        var status = response.data.status;
                        
                        USHDashboard.updateProgress(response.data);
                        USHDashboard.lastStatus = status;
                        
                        // Stop polling if scan is complete
                        if (status === 'completed' || status === 'idle') {
                            clearInterval(USHDashboard.progressInterval);
                            USHDashboard.progressInterval = null;

                            // Ensure UI shows 100% and completed state
                            $progress = $('#ush-scan-progress');
                            if ($progress.length) {
                                $progress.find('.ush-progress-percentage').text('100%');
                                $progress.find('.ush-progress-fill').css('width', '100%');
                                $progress.find('.ush-progress-header h3').text('Scan Completed');
                            }

                            // Show errors if any
                            if (response.data.errors && response.data.errors.length) {
                                USHDashboard.showErrors(response.data.errors);
                            }

                            // Reload only on transition from running -> completed
                            if (prevStatus === 'running' && status === 'completed') {
                                setTimeout(function() { location.reload(); }, 1500);
                            }
                        }
                    }
                },
                error: function() {
                    console.error('Failed to get scan progress');
                }
            });
        },
        
        // Fetch and update category scores during scan
        fetchCategoryScores: function() {
            $.ajax({
                url: ush_ajax.ajax_url,
                type: 'POST',
                data: { action: 'ush_get_category_scores', nonce: ush_ajax.nonce },
                success: function(response) {
                    if (!response.success) return;
                    var scores = response.data || [];
                    // Update tile mobile/desktop numbers if present on page
                    scores.forEach(function(row){
                        var category = row.audit_category;
                        var scanType = row.scan_type;
                        var avg = (typeof row.avg_score === 'number' && row.avg_score !== null) ? Math.round(row.avg_score * 100) : null;
                        var tile = $(".ush-tile[data-category='" + category + "']");
                        if (tile.length) {
                            var detailItems = tile.find('.ush-detail-item');
                                if (scanType === 'mobile' && avg !== null) {
                                    detailItems.eq(0).find('span').last().text(avg + '%');
                                } else if (scanType === 'desktop' && avg !== null) {
                                    detailItems.eq(1).find('span').last().text(avg + '%');
                                }
                                // Recompute overall from available numbers
                                var mText = detailItems.eq(0).find('span').last().text();
                                var dText = detailItems.eq(1).find('span').last().text();
                                var mv = (mText && mText.indexOf('%') > -1) ? parseInt(mText) : null;
                                var dv = (dText && dText.indexOf('%') > -1) ? parseInt(dText) : null;
                                var vals = [];
                                if (mv !== null) vals.push(mv);
                                if (dv !== null) vals.push(dv);
                                var overall = vals.length ? Math.round(vals.reduce(function(a,b){return a+b;},0)/vals.length) : 0;
                                tile.find('.ush-score-number').text(overall + '%');
                        }

                            // Also update category_state list entry with percentage if shown in progress panel
                            var $progress = $('#ush-scan-progress');
                            if ($progress.length) {
                                var $list = $progress.find('.ush-category-state-list');
                                if ($list.length) {
                                    var $item = $list.find('li:contains("' + category + '")');
                                    if ($item.length && avg !== null) {
                                        // append score if not already present
                                        if ($item.find('.ush-cat-score').length === 0) {
                                            $item.append(' <span class="ush-cat-score" style="margin-left:8px;color:#333;">(' + avg + '%)</span>');
                                        } else {
                                            $item.find('.ush-cat-score').text('(' + avg + '%)');
                                        }
                                    }
                                }
                            }
                    });
                }
            });
        },
        
        // Update progress display
        updateProgress: function(progress) {
            var $progress = $('#ush-scan-progress');
            if ($progress.length === 0) return;
            
            var percentage = 0;
            // Prefer category-based progress if available
            if (progress.category_state) {
                var totalCats = 0, doneCats = 0;
                for (var c in progress.category_state) {
                    if (!progress.category_state.hasOwnProperty(c)) continue;
                    totalCats++;
                    if (progress.category_state[c] === 'completed') doneCats++;
                }
                if (totalCats > 0) {
                    percentage = Math.round((doneCats / totalCats) * 100);
                }
            } else if (progress.total_pages > 0) {
                percentage = Math.round((progress.scanned_pages / progress.total_pages) * 100);
            }
            
            // Update percentage
            $progress.find('.ush-progress-percentage').text(percentage + '%');
            $progress.find('.ush-progress-fill').css('width', percentage + '%');
            
            // Update current page
            // If a specific category is being processed, show that status
            var currentPageText = '';
            if (progress.current_category) {
                currentPageText = 'Scanning ' + progress.current_category + '...';
            } else if (progress.current_page_title) {
                currentPageText = 'Scanning: ' + progress.current_page_title;
            } else {
                currentPageText = ush_ajax.strings.scanning;
            }

            $progress.find('.ush-current-page').text(currentPageText);
            
            // Update current URL if available
            if (progress.current_url) {
                var $urlElement = $progress.find('.ush-current-url');
                if ($urlElement.length === 0) {
                    $progress.find('.ush-progress-details').append('<p class="ush-current-url"><small></small></p>');
                    $urlElement = $progress.find('.ush-current-url small');
                }
                $urlElement.text(progress.current_url);
            }
            
            // Show errors if any
            if (progress.errors && progress.errors.length > 0) {
                USHDashboard.showErrorsInProgress(progress.errors);
            }

            // If category-level progress is present, optionally show per-category percentage
            if (progress.current_category && typeof progress.total_pages === 'number') {
                // If we saved category-specific scores early, fetch them to update tiles
                USHDashboard.fetchCategoryScores();
            }

            // Update category state list if available
            if (progress.category_state) {
                var $list = $progress.find('.ush-category-state-list');
                if ($list.length === 0) {
                    $progress.find('.ush-category-states').append('<ul class="ush-category-state-list" style="list-style:none;padding-left:0;margin:0"></ul>');
                    $list = $progress.find('.ush-category-state-list');
                }
                $list.empty();
                for (var cat in progress.category_state) {
                    if (!progress.category_state.hasOwnProperty(cat)) continue;
                    var state = progress.category_state[cat];
                    var stateText = state.charAt(0).toUpperCase() + state.slice(1);
                    var icon = '●';
                    var color = '#999';
                    if (state === 'running') { color = '#ffb86b'; }
                    else if (state === 'completed') { color = '#2ecc71'; }
                    else if (state === 'failed') { color = '#e74c3c'; }
                    $list.append('<li style="margin:3px 0;"><span style="color:' + color + ';margin-right:8px">' + icon + '</span><strong>' + cat + '</strong>: ' + stateText + '</li>');
                }
            }
        },
        
        // Open modal
        openModal: function(e) {
            e.preventDefault();
            
            var $tile = $(this);
            var category = $tile.data('category');
            
            if (!category) return;
            
            // Show modal
            $('#ush-modal').show();
            $('#ush-modal-title').text(category + ' - Audit Details');
            // reset scores
            $('#ush-modal-mobile-score').text('Mobile: —');
            $('#ush-modal-desktop-score').text('Desktop: —');
            // clear scan date
            $('#ush-modal .ush-scan-date').remove();
            
            // Load data for both tabs
            // Host Health does not use mobile/desktop tabs - hide tabs and show a single general score
            if (category === 'Host Health') {
                $('.ush-modal-tabs').hide();
                $('#ush-tab-mobile').addClass('active');
                $('#ush-tab-desktop').removeClass('active');
                // Load general host health audits into mobile tab container for simplicity
                USHDashboard.loadAuditData(category, 'desktop', '#ush-mobile-audits', function(score, scan_date){
                    if (typeof score === 'number') {
                        $('#ush-modal-mobile-score').text('Score: ' + Math.round(score * 100));
                        $('#ush-modal-desktop-score').hide();
                    }
                    if (scan_date) {
                        // Remove any existing scan date
                        $('#ush-modal .ush-scan-date').remove();
                        try {
                            var d = new Date(scan_date);
                            var dateStr = isNaN(d.getTime()) ? scan_date : d.toLocaleDateString(undefined);
                        } catch(e) { var dateStr = scan_date; }
                        $('#ush-modal .ush-modal-header').append('<div class="ush-scan-date">' + dateStr + '</div>');
                    }
                });
            } else {
                $('.ush-modal-tabs').show();
                $('#ush-modal-desktop-score').show();
                USHDashboard.loadAuditData(category, 'mobile', '#ush-mobile-audits', function(score, scan_date){
                    if (typeof score === 'number') {
                        $('#ush-modal-mobile-score').text('Mobile: ' + Math.round(score * 100));
                    }
                    if (scan_date) {
                        // Replace any existing scan date
                        $('#ush-modal .ush-scan-date').remove();
                        try {
                            var d = new Date(scan_date);
                            var dateStr = isNaN(d.getTime()) ? scan_date : d.toLocaleDateString(undefined);
                        } catch(e) { var dateStr = scan_date; }
                        $('#ush-modal .ush-modal-header').append('<div class="ush-scan-date">' + dateStr + '</div>');
                    }
                });
                USHDashboard.loadAuditData(category, 'desktop', '#ush-desktop-audits', function(score, scan_date){
                    if (typeof score === 'number') {
                        $('#ush-modal-desktop-score').text('Desktop: ' + Math.round(score * 100));
                    }
                    if (scan_date) {
                        // Replace any existing scan date (desktop may load after mobile)
                        $('#ush-modal .ush-scan-date').remove();
                        try {
                            var d = new Date(scan_date);
                            var dateStr = isNaN(d.getTime()) ? scan_date : d.toLocaleDateString(undefined);
                        } catch(e) { var dateStr = scan_date; }
                        $('#ush-modal .ush-modal-header').append('<div class="ush-scan-date">' + dateStr + '</div>');
                    }
                });
            }
        },
        
        // Load audit data
        loadAuditData: function(category, scanType, container, onScore) {
            var $container = $(container);
            $container.html('<tr><td colspan="4" class="ush-loading">' + ush_ajax.strings.loading + '</td></tr>');
            
            $.ajax({
                url: ush_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ush_get_audit_data',
                    category: category,
                    scan_type: scanType,
                    nonce: ush_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var audits = response.data && response.data.audits ? response.data.audits : response.data;
                        var score = response.data && typeof response.data.avg_score !== 'undefined' ? response.data.avg_score : null;
                        var scan_date = response.data && response.data.scan_date ? response.data.scan_date : null;
                        if (typeof onScore === 'function') { onScore(score, scan_date); }
                        if (audits && audits.length > 0) {
                            USHDashboard.renderAuditTable($container, audits);
                        } else {
                            $container.html('<tr><td colspan="4" class="ush-loading">' + ush_ajax.strings.no_data + '</td></tr>');
                        }
                    } else {
                        $container.html('<tr><td colspan="4" class="ush-loading">' + ush_ajax.strings.no_data + '</td></tr>');
                    }
                },
                error: function() {
                    $container.html('<tr><td colspan="4" class="ush-loading">Error loading data</td></tr>');
                }
            });
        },
        
        // Render audit table
        renderAuditTable: function($container, data) {
            var html = '';
            
            $.each(data, function(index, audit) {
                var severityClass = 'ush-severity-' + audit.severity;
                var severityText = audit.severity.charAt(0).toUpperCase() + audit.severity.slice(1);
                
                html += '<tr>' +
                    '<td><strong>' + audit.audit_name + '</strong></td>' +
                    '<td>' + (audit.audit_description || '') + '</td>' +
                    '<td>' + (audit.audit_element || '') + '</td>' +
                    '<td><span class="ush-severity-badge ' + severityClass + '">' + severityText + '</span></td>' +
                '</tr>';
            });
            
            $container.html(html);
        },
        
        // Close modal
        closeModal: function() {
            $('#ush-modal').hide();
        },
        
        // Switch tab
        switchTab: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var tab = $button.data('tab');
            
            // Update button states
            $('.ush-tab-button').removeClass('active');
            $button.addClass('active');
            
            // Update tab content
            $('.ush-tab-pane').removeClass('active');
            $('#ush-tab-' + tab).addClass('active');
        },
        
        // Show error message
        showError: function(message) {
            var $notice = $('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>');
            $('.ush-dashboard h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut();
            }, 5000);
        },
        
        // Show errors array
        showErrors: function(errors) {
            if (!errors || errors.length === 0) return;
            
            var message = 'Scan completed with errors:<ul>';
            $.each(errors, function(index, error) {
                message += '<li>' + error.page + ': ' + error.message + '</li>';
            });
            message += '</ul>';
            
            this.showError(message);
        },
        
        // Show errors in progress bar
        showErrorsInProgress: function(errors) {
            if (!errors || errors.length === 0) return;
            var $progress = $('#ush-scan-progress');
            var $errorsWrap = $progress.find('#ush-scan-errors');
            var $errorsList = $progress.find('#ush-scan-errors-list');

            if ($errorsWrap.length === 0) {
                // Create the error area (header already contains title)
                $progress.append('<div class="ush-scan-errors" id="ush-scan-errors"><h4>' + (ush_ajax.strings.errors_title || 'Errors:') + '</h4><ul id="ush-scan-errors-list"></ul></div>');
                $errorsWrap = $progress.find('#ush-scan-errors');
                $errorsList = $progress.find('#ush-scan-errors-list');
            }

            $errorsList.empty();
            $.each(errors, function(index, error) {
                var errorHtml = '<li class="ush-error-item" style="margin:4px 0;">' +
                    '<strong>' + error.page + ':</strong> ' + error.message +
                '</li>';
                $errorsList.append(errorHtml);
            });

            $errorsWrap.show();
            // Mark category items in list as failed if error refers to a category name
            var $list = $progress.find('.ush-category-state-list');
            if ($list.length) {
                $list.find('li').each(function(){
                    var $li = $(this);
                    var cat = $li.data('cat');
                    $.each(errors, function(i, err){
                        // rudimentary mapping: if error.message contains category name
                        if (err.message && cat && err.message.indexOf(cat) !== -1) {
                            $li.css('opacity', '0.6');
                            $li.append(' <span style="color:#e74c3c;margin-left:6px">(failed)</span>');
                        }
                    });
                });
            }
        },
        
        // Show success message
        showSuccess: function(message) {
            var $notice = $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>');
            $('.ush-dashboard h1').after($notice);
            
            // Auto-dismiss after 3 seconds
            setTimeout(function() {
                $notice.fadeOut();
            }, 3000);
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        USHDashboard.init();
    });
    
    // Make USHDashboard available globally
    window.USHDashboard = USHDashboard;
    
})(jQuery);
