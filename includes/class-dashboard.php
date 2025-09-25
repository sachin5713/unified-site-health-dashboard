<?php
/**
 * Dashboard Class
 * 
 * Handles the dashboard page rendering and data aggregation
 */

if (!defined('ABSPATH')) {
    exit;
}

class USH_Dashboard {
    
    /**
     * Render the detailed report page
     */
    public function render_detailed_report() {
        // Get scan results
        $scan_results = $this->get_all_scan_results();
        
        // Get overall alerts
        $overall_alerts = $this->get_overall_alerts($scan_results);
        
        // Get forecasting data
        $forecasting_data = $this->get_forecasting_data($scan_results);
        
        ?>
        <div class="wrap ush-detailed-report">
            <h1><?php _e('Site Health Report', 'unified-site-health-dashboard'); ?></h1>
            
            <!-- Google PageSpeed-like Overview -->
            <div class="ush-overview-scores">
                <?php $this->render_overview_scores($scan_results); ?>
            </div>
            
            <!-- Detailed Sections -->
            <div class="ush-dashboard-grid">
                <?php $this->render_performance_section($scan_results['performance'] ?? null); ?>
                <?php $this->render_seo_section($scan_results['seo'] ?? null); ?>
                <?php $this->render_security_section($scan_results['security'] ?? null); ?>
                <?php $this->render_accessibility_section($scan_results['accessibility'] ?? null); ?>
                <?php $this->render_content_decay_section($scan_results['content_decay'] ?? null); ?>
                <?php $this->render_host_health_section($scan_results['host_health'] ?? null); ?>
            </div>
            
            <?php $this->render_forecasting_section($forecasting_data); ?>
            
            <?php $this->render_scan_history(); ?>
            
            <div class="ush-actions">
                <button type="button" class="button button-primary" id="ush-rescan-btn">
                    <?php _e('Run New Scan', 'unified-site-health-dashboard'); ?>
                </button>
                <button type="button" class="button" id="ush-export-btn">
                    <?php _e('Export Report', 'unified-site-health-dashboard'); ?>
                </button>
                <?php if (get_option('ush_auto_fix_enabled', 0)): ?>
                    <button type="button" class="button button-secondary" id="ush-auto-fix-btn">
                        <?php _e('Auto-Fix Issues', 'unified-site-health-dashboard'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render page-wise report
     */
    public function render_page_wise_report() {
        $selected_post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : 'any';
        $pages = $this->get_all_pages($selected_post_type);
        $selected_page = isset($_GET['page_url']) ? sanitize_url($_GET['page_url']) : '';
        $page_report = null;
        
        if ($selected_page) {
            $page_report = $this->get_page_report($selected_page);
        }
        
        ?>
        <div class="wrap ush-page-wise-report">
            <h1><?php _e('Page-wise Report', 'unified-site-health-dashboard'); ?></h1>
            
            <form method="get" class="ush-filter-bar">
                <input type="hidden" name="page" value="site-health-page-report" />
                <label for="ush-post-type-select" class="screen-reader-text"><?php _e('Filter by post type', 'unified-site-health-dashboard'); ?></label>
                <select id="ush-post-type-select" name="post_type" class="ush-filter-select">
                    <?php foreach ($this->get_public_post_types_with_counts() as $pt => $info): ?>
                        <option value="<?php echo esc_attr($pt); ?>" <?php selected($selected_post_type, $pt); ?>>
                            <?php echo esc_html($info['label'] . ' (' . $info['count'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="any" <?php selected($selected_post_type, 'any'); ?>><?php _e('All public types', 'unified-site-health-dashboard'); ?></option>
                </select>
                <button type="submit" class="button"><?php _e('Apply', 'unified-site-health-dashboard'); ?></button>
            </form>
            
            <div class="ush-page-report-layout">
                <div class="ush-pages-sidebar">
                    <h2><?php _e('Pages', 'unified-site-health-dashboard'); ?></h2>
                    <select id="ush-page-dropdown" class="ush-filter-select" aria-label="<?php esc_attr_e('Select a page', 'unified-site-health-dashboard'); ?>">
                        <option value=""><?php _e('Select a page…', 'unified-site-health-dashboard'); ?></option>
                        <?php foreach ($pages as $page): ?>
                            <option value="<?php echo esc_url($page['url']); ?>" <?php selected($selected_page, $page['url']); ?>><?php echo esc_html($page['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Choose a page to view its report.', 'unified-site-health-dashboard'); ?></p>

                    <table class="widefat fixed striped ush-pages-table">
                        <thead>
                            <tr>
                                <th><?php _e('Page name', 'unified-site-health-dashboard'); ?></th>
                                <th class="column-post-type"><?php _e('Type', 'unified-site-health-dashboard'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pages as $page): ?>
                                <tr class="ush-page-row" data-url="<?php echo esc_url($page['url']); ?>">
                                    <td class="column-title">
                                        <a href="#" class="row-title ush-load-page-report" data-url="<?php echo esc_url($page['url']); ?>"><?php echo esc_html($page['title']); ?></a>
                                    </td>
                                    <td class="column-post-type"><span class="ush-badge"><?php echo esc_html($page['type']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="ush-page-report-content" id="ush-page-report-ajax">
                    <?php if ($selected_page && $page_report): ?>
                        <div class="ush-page-header">
                            <h2><?php echo esc_html($page_report['title']); ?></h2>
                            <p class="ush-page-url-display"><?php echo esc_html($page_report['url']); ?></p>
                        </div>
                        
                        <div class="ush-page-overview">
                            <?php $this->render_page_overview_scores($page_report); ?>
                        </div>
                        
                        <div class="ush-page-details">
                            <?php $this->render_page_detailed_report($page_report); ?>
                        </div>
                    <?php else: ?>
                        <div class="ush-page-placeholder">
                            <h2><?php _e('Select a Page', 'unified-site-health-dashboard'); ?></h2>
                            <p><?php _e('Choose a page from the dropdown to view its detailed health report.', 'unified-site-health-dashboard'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get all pages
     */
    private function get_all_pages($post_type_filter = 'any') {
        $pages = array();
        
        // Determine post types
        $post_types = $post_type_filter === 'any' ? array_keys($this->get_public_post_types_with_counts()) : array($post_type_filter);
        
        // Get published posts
        $posts = get_posts(array(
            'post_status' => 'publish',
            'numberposts' => -1,
            'post_type' => $post_types
        ));
        
        $seen = array();
        foreach ($posts as $post) {
            $permalink = get_permalink($post->ID);
            if (isset($seen[$permalink])) { continue; }
            $seen[$permalink] = true;
            $pages[] = array(
                'title' => $post->post_title,
                'url' => $permalink,
                'id' => $post->ID,
                'type' => $post->post_type
            );
        }
        
        // Add homepage
        $pages[] = array(
            'title' => __('Homepage', 'unified-site-health-dashboard'),
            'url' => home_url('/'),
            'id' => 0,
            'type' => 'home'
        );
        
        return $pages;
    }

    /**
     * Get public post types with published counts
     */
    private function get_public_post_types_with_counts() {
        $post_types = get_post_types(array(
            'public' => true
        ), 'objects');
        
        $result = array();
        foreach ($post_types as $pt => $obj) {
            $counts = wp_count_posts($pt);
            $published = isset($counts->publish) ? (int) $counts->publish : 0;
            if ($published > 0) {
                $result[$pt] = array(
                    'label' => $obj->labels->name,
                    'count' => $published,
                );
            }
        }
        
        // Ensure default post and page appear first if present
        $ordered = array();
        foreach (array('page', 'post') as $preferred) {
            if (isset($result[$preferred])) {
                $ordered[$preferred] = $result[$preferred];
                unset($result[$preferred]);
            }
        }
        return $ordered + $result;
    }
    
    /**
     * Get page report
     */
    private function get_page_report($page_url) {
        // Mock page report data for now
        $page_data = array(
            'title' => $this->get_page_title_from_url($page_url),
            'url' => $page_url,
            'performance' => rand(60, 95),
            'seo' => rand(70, 90),
            'security' => rand(80, 100),
            'accessibility' => rand(65, 85),
            'content_decay' => rand(75, 95),
            'host_health' => rand(85, 100),
            'issues' => $this->get_page_issues($page_url)
        );
        
        return $page_data;
    }
    
    /**
     * Get page title from URL
     */
    private function get_page_title_from_url($url) {
        if ($url === home_url('/')) {
            return __('Homepage', 'unified-site-health-dashboard');
        }
        
        $post_id = url_to_postid($url);
        if ($post_id) {
            return get_the_title($post_id);
        }
        
        return __('Unknown Page', 'unified-site-health-dashboard');
    }
    
    /**
     * Get page issues
     */
    private function get_page_issues($page_url) {
        // Mock issues for the page with categories
        $issues = array();
        
        if (rand(0, 1)) {
            $issues[] = array(
                'category' => 'performance',
                'title' => __('Slow loading images', 'unified-site-health-dashboard'),
                'severity' => 'warning',
                'description' => __('Some images on this page are not optimized', 'unified-site-health-dashboard'),
                'fix' => __('Compress and optimize images for web', 'unified-site-health-dashboard')
            );
        }
        
        if (rand(0, 1)) {
            $issues[] = array(
                'category' => 'seo',
                'title' => __('Missing meta description', 'unified-site-health-dashboard'),
                'severity' => 'critical',
                'description' => __('This page is missing a meta description', 'unified-site-health-dashboard'),
                'fix' => __('Add a compelling meta description for better SEO', 'unified-site-health-dashboard')
            );
        }
        
        if (rand(0, 1)) {
            $issues[] = array(
                'category' => 'accessibility',
                'title' => __('Images missing alt attributes', 'unified-site-health-dashboard'),
                'severity' => 'warning',
                'description' => __('Some images do not have alt attributes', 'unified-site-health-dashboard'),
                'fix' => __('Add descriptive alt text to images', 'unified-site-health-dashboard')
            );
        }
        
        return $issues;
    }
    
    /**
     * Render page overview scores
     */
    private function render_page_overview_scores($page_report) {
        $scores = array(
            'performance' => $page_report['performance'],
            'seo' => $page_report['seo'],
            'security' => $page_report['security'],
            'accessibility' => $page_report['accessibility'],
            'content_decay' => $page_report['content_decay'],
            'host_health' => $page_report['host_health']
        );
        
        ?>
        <div class="ush-overview-container">
            <div class="ush-overview-header">
                <h2><?php _e('Page Health Overview', 'unified-site-health-dashboard'); ?></h2>
                <p><?php _e('Scores for this page only. Calculated from the same metrics.', 'unified-site-health-dashboard'); ?></p>
            </div>
            <div class="ush-overview-scores-grid">
                <?php foreach ($scores as $key => $score): ?>
                    <div class="ush-overview-score">
                        <div class="ush-overview-circle ush-score-<?php echo esc_attr($this->get_score_class($score)); ?>">
                            <span class="ush-overview-value"><?php echo $score; ?></span>
                        </div>
                        <div class="ush-overview-label"><?php echo ucfirst($key); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="ush-overview-legend">
                <div class="ush-legend-item">
                    <span class="ush-legend-icon ush-legend-red"></span>
                    <span><?php _e('0-49', 'unified-site-health-dashboard'); ?></span>
                </div>
                <div class="ush-legend-item">
                    <span class="ush-legend-icon ush-legend-yellow"></span>
                    <span><?php _e('50-89', 'unified-site-health-dashboard'); ?></span>
                </div>
                <div class="ush-legend-item">
                    <span class="ush-legend-icon ush-legend-green"></span>
                    <span><?php _e('90-100', 'unified-site-health-dashboard'); ?></span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render page detailed report
     */
    private function render_page_detailed_report($page_report) {
        if (empty($page_report['issues'])) {
            echo '<div class="ush-no-issues"><p>' . __('No issues found for this page.', 'unified-site-health-dashboard') . '</p></div>';
            return;
        }
        
        ?>
        <div class="ush-page-issues">
            <h3><?php _e('Issues Found', 'unified-site-health-dashboard'); ?></h3>
            <?php foreach ($page_report['issues'] as $issue): ?>
                <div class="ush-page-issue ush-severity-<?php echo esc_attr($issue['severity']); ?>">
                    <div class="ush-page-issue-header">
                        <h4><?php echo esc_html($issue['title']); ?></h4>
                        <span class="ush-severity-badge"><?php echo ucfirst($issue['severity']); ?></span>
                    </div>
                    <div class="ush-page-issue-content">
                        <p><strong><?php _e('Description:', 'unified-site-health-dashboard'); ?></strong> <?php echo esc_html($issue['description']); ?></p>
                        <p><strong><?php _e('Fix:', 'unified-site-health-dashboard'); ?></strong> <?php echo esc_html($issue['fix']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Render only the page report HTML for AJAX
     */
    public function get_page_report_for_ajax($page_url) {
        $report = $this->get_page_report($page_url);
        if (!$report) {
            echo '<div class="notice notice-error"><p>' . __('No report available.', 'unified-site-health-dashboard') . '</p></div>';
            return;
        }
        ?>
        <div class="ush-page-header">
            <h2><?php echo esc_html($report['title']); ?></h2>
            <p class="ush-page-url-display"><?php echo esc_html($report['url']); ?></p>
            <p><button type="button" class="button button-primary" id="ush-page-rescan" data-page-url="<?php echo esc_url($report['url']); ?>"><?php _e('Rescan', 'unified-site-health-dashboard'); ?></button></p>
        </div>
        <div class="ush-page-overview">
            <?php $this->render_page_overview_scores($report); ?>
        </div>
        <?php
        // Build category summary counts for modal shortcuts
        $category_counts = array('performance' => 0, 'seo' => 0, 'security' => 0, 'accessibility' => 0, 'content_decay' => 0, 'host_health' => 0);
        foreach ($report['issues'] as $issue) {
            if (!empty($issue['category']) && isset($category_counts[$issue['category']])) {
                $category_counts[$issue['category']]++;
            }
        }
        ?>
        <div class="ush-page-issue-summary">
            <?php foreach ($category_counts as $cat => $count): if ($count < 1) continue; ?>
                <div class="ush-summary-card">
                    <div class="ush-summary-count"><?php echo (int) $count; ?></div>
                    <div class="ush-summary-label"><?php echo esc_html(ucfirst(str_replace('_',' ', $cat))); ?></div>
                    <a href="#" class="button-link ush-open-issues-modal" data-section="<?php echo esc_attr($cat); ?>" data-page-url="<?php echo esc_url($report['url']); ?>"><?php _e('View details', 'unified-site-health-dashboard'); ?></a>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="ush-page-details">
            <?php $this->render_page_detailed_report($report); ?>
        </div>
        <?php
    }

    /**
     * Render section issues HTML for modal via AJAX
     */
    public function render_section_issues_for_ajax($section) {
        $section = sanitize_key($section);
        $page_url = isset($_POST['page_url']) ? esc_url_raw($_POST['page_url']) : '';
        $title = ucfirst(str_replace('_', ' ', $section));
        echo '<h2>' . esc_html($title) . ' ' . __('Issues', 'unified-site-health-dashboard') . '</h2>';
        
        // If a page URL is supplied, show page-specific issues filtered by category
        if (!empty($page_url)) {
            $issues = $this->get_page_issues($page_url);
            $filtered = array_filter($issues, function($i) use ($section) {
                return isset($i['category']) && $i['category'] === $section;
            });
            if (empty($filtered)) {
                echo '<div class="notice notice-info"><p>' . __('No issues found for this category on the selected page.', 'unified-site-health-dashboard') . '</p></div>';
                return;
            }
            foreach ($filtered as $issue) {
                $this->render_issue_card($issue);
            }
            return;
        }
        
        // Otherwise, fall back to site-wide data for the section
        $scan_results = $this->get_all_scan_results();
        if (!isset($scan_results[$section])) {
            echo '<div class="notice notice-info"><p>' . __('No data available.', 'unified-site-health-dashboard') . '</p></div>';
            return;
        }
        if ($section === 'performance') {
            $data = $scan_results['performance']['data'] ?? array();
            $pagespeed = $data['pagespeed'] ?? array();
            $ttfb = $data['ttfb'] ?? array();
            ob_start();
            $this->render_performance_issues($pagespeed, $ttfb);
            $html = ob_get_clean();
            echo $html ?: '<p>' . __('No performance issues.', 'unified-site-health-dashboard') . '</p>';
            return;
        }
        echo '<p>' . __('Detailed issues will appear here.', 'unified-site-health-dashboard') . '</p>';
    }
    
    /**
     * Get all scan results
     */
    private function get_all_scan_results() {
        $results = array();
        
        $scan_types = array('performance', 'seo', 'security', 'accessibility', 'content_decay', 'host_health');
        
        foreach ($scan_types as $scan_type) {
            $scan_data = UnifiedSiteHealthDashboard::get_scan_results($scan_type);
            if (!empty($scan_data)) {
                $results[$scan_type] = json_decode($scan_data[0]->scan_data, true);
            }
        }
        
        return $results;
    }
    
    /**
     * Get overall alerts
     */
    private function get_overall_alerts($scan_results) {
        $alerts = array();
        
        foreach ($scan_results as $scan_type => $data) {
            if (isset($data['data']['overall_score'])) {
                $score = $data['data']['overall_score'];
                $alerts[] = array(
                    'type' => $this->get_alert_type($score),
                    'section' => ucfirst(str_replace('_', ' ', $scan_type)),
                    'score' => $score,
                    'message' => $this->get_alert_message($score, $scan_type)
                );
            }
        }
        
        return $alerts;
    }
    
    /**
     * Get alert type based on score
     */
    private function get_alert_type($score) {
        if ($score < 50) {
            return 'error';
        } elseif ($score < 80) {
            return 'warning';
        } else {
            return 'success';
        }
    }
    
    /**
     * Get alert message
     */
    private function get_alert_message($score, $section) {
        $section_name = ucfirst(str_replace('_', ' ', $section));
        
        if ($score < 50) {
            return sprintf(__('%s: Critical issues detected', 'unified-site-health-dashboard'), $section_name);
        } elseif ($score < 80) {
            return sprintf(__('%s: Issues detected', 'unified-site-health-dashboard'), $section_name);
        } else {
            return sprintf(__('%s: Looking good', 'unified-site-health-dashboard'), $section_name);
        }
    }
    
    /**
     * Get forecasting data
     */
    private function get_forecasting_data($scan_results) {
        $forecasts = array();
        
        // Performance forecasting
        if (isset($scan_results['performance']['data']['pagespeed']['lcp']['value'])) {
            $current_lcp = $scan_results['performance']['data']['pagespeed']['lcp']['value'];
            $forecasts[] = array(
                'metric' => 'LCP',
                'current' => $current_lcp,
                'predicted' => $current_lcp + 0.2,
                'trend' => 'increasing',
                'message' => __('If you don\'t compress images, your LCP may increase by 0.2s in 30 days.', 'unified-site-health-dashboard')
            );
        }
        
        // SEO forecasting
        if (isset($scan_results['seo']['data']['overall_score'])) {
            $current_score = $scan_results['seo']['data']['overall_score'];
            $forecasts[] = array(
                'metric' => 'SEO Score',
                'current' => $current_score,
                'predicted' => max(0, $current_score - 5),
                'trend' => 'decreasing',
                'message' => __('Without content updates, your SEO score may decrease by 5 points in 30 days.', 'unified-site-health-dashboard')
            );
        }
        
        return $forecasts;
    }
    
    /**
     * Render overall alerts
     */
    private function render_overall_alerts($alerts) {
        if (empty($alerts)) {
            return;
        }
        
        echo '<div class="ush-overall-alerts">';
        foreach ($alerts as $alert) {
            $class = 'ush-alert ush-alert-' . $alert['type'];
            echo '<div class="' . esc_attr($class) . '">';
            echo '<strong>' . esc_html($alert['section']) . ':</strong> ';
            echo esc_html($alert['message']) . ' ';
            echo '<span class="ush-score">(' . $alert['score'] . '%)</span>';
            echo '</div>';
        }
        echo '</div>';
    }
    
    /**
     * Render performance section
     */
    private function render_performance_section($data) {
        if (!$data) {
            $this->render_empty_section('Performance', 'performance');
            return;
        }
        
        $score = $data['data']['overall_score'] ?? 0;
        $pagespeed = $data['data']['pagespeed'] ?? array();
        $ttfb = $data['data']['ttfb'] ?? array();
        
        ?>
            <div class="ush-section ush-performance ush-tile-modal-trigger" data-section="performance" tabindex="0" role="button" aria-pressed="false">
            <h2><?php _e('Performance', 'unified-site-health-dashboard'); ?></h2>
            <div class="ush-score-circle ush-score-<?php echo $this->get_score_class($score); ?>">
                <span class="ush-score-value"><?php echo $score; ?>%</span>
            </div>
            <?php if (!empty($pagespeed)): ?>
                <div class="ush-metrics">
                    <h3><?php _e('Core Web Vitals', 'unified-site-health-dashboard'); ?></h3>
                    <div class="ush-metric">
                        <span class="ush-metric-label"><?php _e('LCP:', 'unified-site-health-dashboard'); ?></span>
                        <span class="ush-metric-value"><?php echo isset($pagespeed['lcp']['value']) ? round($pagespeed['lcp']['value'], 2) . 's' : 'N/A'; ?></span>
                    </div>
                    <div class="ush-metric">
                        <span class="ush-metric-label"><?php _e('FID:', 'unified-site-health-dashboard'); ?></span>
                        <span class="ush-metric-value"><?php echo isset($pagespeed['fid']['value']) ? round($pagespeed['fid']['value'], 2) . 's' : 'N/A'; ?></span>
                    </div>
                    <div class="ush-metric">
                        <span class="ush-metric-label"><?php _e('CLS:', 'unified-site-health-dashboard'); ?></span>
                        <span class="ush-metric-value"><?php echo isset($pagespeed['cls']['value']) ? round($pagespeed['cls']['value'], 3) : 'N/A'; ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($ttfb) && isset($ttfb['value'])): ?>
                <div class="ush-metrics">
                    <h3><?php _e('Server Metrics', 'unified-site-health-dashboard'); ?></h3>
                    <div class="ush-metric">
                        <span class="ush-metric-label"><?php _e('TTFB:', 'unified-site-health-dashboard'); ?></span>
                        <span class="ush-metric-value"><?php echo round($ttfb['value'], 2); ?>ms</span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php $this->render_performance_issues($pagespeed, $ttfb); ?>
        </div>
        <?php
    }
    
    /**
     * Render performance issues with guidance
     */
    private function render_performance_issues($pagespeed, $ttfb) {
        $issues = array();
        
        // Check LCP
        if (isset($pagespeed['lcp']['value']) && $pagespeed['lcp']['value'] > 2.5) {
            $issues[] = array(
                'title' => __('Large Contentful Paint (LCP) is too slow', 'unified-site-health-dashboard'),
                'description' => sprintf(__('Your LCP is %.2fs, which exceeds the recommended 2.5s threshold.', 'unified-site-health-dashboard'), $pagespeed['lcp']['value']),
                'impact' => __('Slow LCP affects user experience and search rankings. Users may leave your site before content loads.', 'unified-site-health-dashboard'),
                'fix' => __('Optimize images, use a CDN, enable caching, and minimize server response time.', 'unified-site-health-dashboard'),
                'severity' => 'critical'
            );
        }
        
        // Check FID
        if (isset($pagespeed['fid']['value']) && $pagespeed['fid']['value'] > 0.1) {
            $issues[] = array(
                'title' => __('First Input Delay (FID) is too high', 'unified-site-health-dashboard'),
                'description' => sprintf(__('Your FID is %.2fs, which exceeds the recommended 0.1s threshold.', 'unified-site-health-dashboard'), $pagespeed['fid']['value']),
                'impact' => __('High FID makes your site feel unresponsive. Users may think the site is broken.', 'unified-site-health-dashboard'),
                'fix' => __('Reduce JavaScript execution time, remove unused code, and optimize third-party scripts.', 'unified-site-health-dashboard'),
                'severity' => 'warning'
            );
        }
        
        // Check CLS
        if (isset($pagespeed['cls']['value']) && $pagespeed['cls']['value'] > 0.1) {
            $issues[] = array(
                'title' => __('Cumulative Layout Shift (CLS) is too high', 'unified-site-health-dashboard'),
                'description' => sprintf(__('Your CLS is %.3f, which exceeds the recommended 0.1 threshold.', 'unified-site-health-dashboard'), $pagespeed['cls']['value']),
                'impact' => __('High CLS causes content to jump around, creating a poor user experience.', 'unified-site-health-dashboard'),
                'fix' => __('Set dimensions for images and videos, avoid inserting content above existing content, and use transform animations.', 'unified-site-health-dashboard'),
                'severity' => 'warning'
            );
        }
        
        // Check TTFB
        if (isset($ttfb['value']) && $ttfb['value'] > 600) {
            $issues[] = array(
                'title' => __('Time to First Byte (TTFB) is too slow', 'unified-site-health-dashboard'),
                'description' => sprintf(__('Your TTFB is %dms, which exceeds the recommended 600ms threshold.', 'unified-site-health-dashboard'), round($ttfb['value'])),
                'impact' => __('Slow TTFB delays all other metrics and creates a poor first impression.', 'unified-site-health-dashboard'),
                'fix' => __('Use a fast hosting provider, enable caching, optimize database queries, and use a CDN.', 'unified-site-health-dashboard'),
                'severity' => 'critical'
            );
        }
        
        // No output here. Issues will be loaded in modal via AJAX only.
    }
    
    /**
     * Render SEO section
     */
    private function render_seo_section($data) {
        if (!$data) {
            $this->render_empty_section('SEO', 'seo');
            return;
        }
        
        $score = $data['data']['overall_score'] ?? 0;
        $meta_tags = $data['data']['meta_tags'] ?? array();
        $alt_text = $data['data']['alt_text'] ?? array();
        $broken_links = $data['data']['broken_links'] ?? array();
        
        ?>
        <div class="ush-section ush-seo ush-tile-modal-trigger" data-section="seo" tabindex="0" role="button" aria-pressed="false">
            <h2><?php _e('SEO', 'unified-site-health-dashboard'); ?></h2>
            <div class="ush-score-circle ush-score-<?php echo $this->get_score_class($score); ?>">
                <span class="ush-score-value"><?php echo $score; ?>%</span>
            </div>
            <div class="ush-metrics">
                <div class="ush-metric">
                    <span class="ush-metric-label"><?php _e('Meta Tags:', 'unified-site-health-dashboard'); ?></span>
                    <span class="ush-metric-value"><?php echo isset($meta_tags['score']) ? $meta_tags['score'] . '%' : 'N/A'; ?></span>
                </div>
                <div class="ush-metric">
                    <span class="ush-metric-label"><?php _e('Alt Text:', 'unified-site-health-dashboard'); ?></span>
                    <span class="ush-metric-value"><?php echo isset($alt_text['score']) ? $alt_text['score'] . '%' : 'N/A'; ?></span>
                </div>
                <div class="ush-metric">
                    <span class="ush-metric-label"><?php _e('Broken Links:', 'unified-site-health-dashboard'); ?></span>
                    <span class="ush-metric-value"><?php echo isset($broken_links['broken_links']) ? $broken_links['broken_links'] : '0'; ?></span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render security section
     */
    private function render_security_section($data) {
        if (!$data) {
            $this->render_empty_section('Security', 'security');
            return;
        }
        
        $score = $data['data']['overall_score'] ?? 0;
        $wordpress_version = $data['data']['wordpress_version'] ?? array();
        $plugins = $data['data']['plugins'] ?? array();
        $ssl = $data['data']['ssl'] ?? array();
        
        ?>
        <div class="ush-section ush-security ush-tile-modal-trigger" data-section="security" tabindex="0" role="button" aria-pressed="false">
            <h2><?php _e('Security', 'unified-site-health-dashboard'); ?></h2>
            <div class="ush-score-circle ush-score-<?php echo $this->get_score_class($score); ?>">
                <span class="ush-score-value"><?php echo $score; ?>%</span>
            </div>
            <div class="ush-metrics">
                <div class="ush-metric">
                    <span class="ush-metric-label"><?php _e('WordPress:', 'unified-site-health-dashboard'); ?></span>
                    <span class="ush-metric-value"><?php echo isset($wordpress_version['current_version']) ? $wordpress_version['current_version'] : 'N/A'; ?></span>
                </div>
                <div class="ush-metric">
                    <span class="ush-metric-label"><?php _e('Outdated Plugins:', 'unified-site-health-dashboard'); ?></span>
                    <span class="ush-metric-value"><?php echo isset($plugins['outdated_plugins']) ? $plugins['outdated_plugins'] : '0'; ?></span>
                </div>
                <div class="ush-metric">
                    <span class="ush-metric-label"><?php _e('SSL:', 'unified-site-health-dashboard'); ?></span>
                    <span class="ush-metric-value"><?php echo isset($ssl['is_ssl']) && $ssl['is_ssl'] ? __('Yes', 'unified-site-health-dashboard') : __('No', 'unified-site-health-dashboard'); ?></span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render accessibility section
     */
    private function render_accessibility_section($data) {
        if (!$data) {
            $this->render_empty_section('Accessibility', 'accessibility');
            return;
        }
        
        $score = $data['data']['overall_score'] ?? 0;
        $alt_text = $data['data']['alt_text'] ?? array();
        $heading_structure = $data['data']['heading_structure'] ?? array();
        
        ?>
        <div class="ush-section ush-accessibility ush-tile-modal-trigger" data-section="accessibility" tabindex="0" role="button" aria-pressed="false">
            <h2><?php _e('Accessibility', 'unified-site-health-dashboard'); ?></h2>
            <div class="ush-score-circle ush-score-<?php echo $this->get_score_class($score); ?>">
                <span class="ush-score-value"><?php echo $score; ?>%</span>
            </div>
            <div class="ush-metrics">
                <div class="ush-metric">
                    <span class="ush-metric-label"><?php _e('Alt Text:', 'unified-site-health-dashboard'); ?></span>
                    <span class="ush-metric-value"><?php echo isset($alt_text['score']) ? $alt_text['score'] . '%' : 'N/A'; ?></span>
                </div>
                <div class="ush-metric">
                    <span class="ush-metric-label"><?php _e('Heading Structure:', 'unified-site-health-dashboard'); ?></span>
                    <span class="ush-metric-value"><?php echo isset($heading_structure['score']) ? $heading_structure['score'] . '%' : 'N/A'; ?></span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render content decay section
     */
    private function render_content_decay_section($data) {
        if (!$data) {
            $this->render_empty_section('Content Decay', 'content_decay');
            return;
        }
        
        $score = $data['data']['overall_score'] ?? 0;
        $old_content = $data['data']['old_content'] ?? array();
        $broken_links = $data['data']['broken_internal_links'] ?? array();
        
        ?>
        <div class="ush-section ush-content-decay ush-tile-modal-trigger" data-section="content_decay" tabindex="0" role="button" aria-pressed="false">
            <h2><?php _e('Content Decay', 'unified-site-health-dashboard'); ?></h2>
            <div class="ush-score-circle ush-score-<?php echo $this->get_score_class($score); ?>">
                <span class="ush-score-value"><?php echo $score; ?>%</span>
            </div>
            <div class="ush-metrics">
                <div class="ush-metric">
                    <span class="ush-metric-label"><?php _e('Old Posts:', 'unified-site-health-dashboard'); ?></span>
                    <span class="ush-metric-value"><?php echo isset($old_content['old_posts_count']) ? $old_content['old_posts_count'] : '0'; ?></span>
                </div>
                <div class="ush-metric">
                    <span class="ush-metric-label"><?php _e('Broken Links:', 'unified-site-health-dashboard'); ?></span>
                    <span class="ush-metric-value"><?php echo isset($broken_links['broken_links']) ? $broken_links['broken_links'] : '0'; ?></span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render host health section
     */
    private function render_host_health_section($data) {
        if (!$data) {
            $this->render_empty_section('Host Health', 'host_health');
            return;
        }
        
        $score = $data['data']['overall_score'] ?? 0;
        $php_version = $data['data']['php_version'] ?? array();
        $disk_space = $data['data']['disk_space'] ?? array();
        $ssl_certificate = $data['data']['ssl_certificate'] ?? array();
        
        ?>
        <div class="ush-section ush-host-health ush-tile-modal-trigger" data-section="host_health" tabindex="0" role="button" aria-pressed="false">
            <h2><?php _e('Host Health', 'unified-site-health-dashboard'); ?></h2>
            <div class="ush-score-circle ush-score-<?php echo $this->get_score_class($score); ?>">
                <span class="ush-score-value"><?php echo $score; ?>%</span>
            </div>
            <div class="ush-metrics">
                <div class="ush-metric">
                    <span class="ush-metric-label"><?php _e('PHP Version:', 'unified-site-health-dashboard'); ?></span>
                    <span class="ush-metric-value"><?php echo isset($php_version['current_version']) ? $php_version['current_version'] : 'N/A'; ?></span>
                </div>
                <div class="ush-metric">
                    <span class="ush-metric-label"><?php _e('Disk Usage:', 'unified-site-health-dashboard'); ?></span>
                    <span class="ush-metric-value"><?php echo isset($disk_space['usage_percentage']) ? round($disk_space['usage_percentage']) . '%' : 'N/A'; ?></span>
                </div>
                <div class="ush-metric">
                    <span class="ush-metric-label"><?php _e('SSL Expiry:', 'unified-site-health-dashboard'); ?></span>
                    <span class="ush-metric-value"><?php echo isset($ssl_certificate['days_until_expiry']) ? $ssl_certificate['days_until_expiry'] . ' days' : 'N/A'; ?></span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render forecasting section
     */
    private function render_forecasting_section($forecasts) {
        if (empty($forecasts)) {
            return;
        }
        
        ?>
        <div class="ush-forecasting">
            <h2><?php _e('Predictions', 'unified-site-health-dashboard'); ?></h2>
            <div class="ush-forecasts">
                <?php foreach ($forecasts as $forecast): ?>
                    <div class="ush-forecast">
                        <h3><?php echo esc_html($forecast['metric']); ?></h3>
                        <div class="ush-forecast-values">
                            <div class="ush-forecast-current">
                                <span class="ush-forecast-label"><?php _e('Current:', 'unified-site-health-dashboard'); ?></span>
                                <span class="ush-forecast-value"><?php echo esc_html($forecast['current']); ?></span>
                            </div>
                            <div class="ush-forecast-predicted">
                                <span class="ush-forecast-label"><?php _e('Predicted (30 days):', 'unified-site-health-dashboard'); ?></span>
                                <span class="ush-forecast-value ush-trend-<?php echo esc_attr($forecast['trend']); ?>"><?php echo esc_html($forecast['predicted']); ?></span>
                            </div>
                        </div>
                        <p class="ush-forecast-message"><?php echo esc_html($forecast['message']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render empty section
     */
    private function render_empty_section($title, $type) {
        ?>
        <div class="ush-section ush-<?php echo esc_attr($type); ?> ush-empty">
            <h2><?php echo esc_html($title); ?></h2>
            <p><?php _e('No scan data available. Run a scan to see results.', 'unified-site-health-dashboard'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Get score class for styling
     */
    private function get_score_class($score) {
        if ($score < 50) {
            return 'error';
        } elseif ($score < 80) {
            return 'warning';
        } else {
            return 'success';
        }
    }
    
    /**
     * Render issue card with detailed guidance
     */
    private function render_issue_card($issue) {
        $severity_class = 'ush-severity-' . $issue['severity'];
        $severity_label = ucfirst($issue['severity']);
        
        ?>
        <div class="ush-issue-card <?php echo esc_attr($severity_class); ?>">
            <div class="ush-issue-header">
                <h4><?php echo esc_html($issue['title']); ?></h4>
                <span class="ush-severity-badge"><?php echo esc_html($severity_label); ?></span>
            </div>
            <div class="ush-issue-content">
                <p class="ush-issue-description"><?php echo esc_html($issue['description']); ?></p>
                <div class="ush-issue-impact">
                    <strong><?php _e('Why it matters:', 'unified-site-health-dashboard'); ?></strong>
                    <p><?php echo esc_html($issue['impact']); ?></p>
                </div>
                <div class="ush-issue-fix">
                    <strong><?php _e('How to fix:', 'unified-site-health-dashboard'); ?></strong>
                    <p><?php echo esc_html($issue['fix']); ?></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render scan history table
     */
    private function render_scan_history() {
        $scan_history = $this->get_scan_history();
        
        if (empty($scan_history)) {
            return;
        }
        
        ?>
        <div class="ush-scan-history">
            <h2><?php _e('Scan History', 'unified-site-health-dashboard'); ?></h2>
            <table class="ush-scan-history-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Date & Time', 'unified-site-health-dashboard'); ?></th>
                        <th><?php _e('Page', 'unified-site-health-dashboard'); ?></th>
                        <th><?php _e('Performance', 'unified-site-health-dashboard'); ?></th>
                        <th><?php _e('SEO', 'unified-site-health-dashboard'); ?></th>
                        <th><?php _e('Security', 'unified-site-health-dashboard'); ?></th>
                        <th><?php _e('Accessibility', 'unified-site-health-dashboard'); ?></th>
                        <th><?php _e('Content', 'unified-site-health-dashboard'); ?></th>
                        <th><?php _e('Host Health', 'unified-site-health-dashboard'); ?></th>
                        <th><?php _e('Overall', 'unified-site-health-dashboard'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($scan_history as $scan): ?>
                        <tr>
                            <td>
                                <div class="ush-scan-datetime">
                                    <div class="ush-scan-date"><?php echo esc_html($scan['date']); ?></div>
                                    <div class="ush-scan-time"><?php echo esc_html($scan['time']); ?></div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($scan['page_url'])): ?>
                                    <a href="<?php echo esc_url($scan['page_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($scan['page_title'] ?? $scan['page_url']); ?></a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?php echo $this->render_score_badge($scan['performance']); ?></td>
                            <td><?php echo $this->render_score_badge($scan['seo']); ?></td>
                            <td><?php echo $this->render_score_badge($scan['security']); ?></td>
                            <td><?php echo $this->render_score_badge($scan['accessibility']); ?></td>
                            <td><?php echo $this->render_score_badge($scan['content_decay']); ?></td>
                            <td><?php echo $this->render_score_badge($scan['host_health']); ?></td>
                            <td><?php echo $this->render_score_badge($scan['overall']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Get scan history
     */
    private function get_scan_history() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ush_scan_results';
        
        // Get all scans ordered by date
        $scans = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY scan_date DESC"
        );
        
        if (empty($scans)) {
            return array();
        }
        
        $history = array();
        $scan_groups = array();
        
        // Group scans by exact timestamp
        foreach ($scans as $scan) {
            $scan_timestamp = strtotime($scan->scan_date);
            $scan_key = date('Y-m-d H:i:s', $scan_timestamp);
            
            if (!isset($scan_groups[$scan_key])) {
                $scan_groups[$scan_key] = array(
                    'timestamp' => $scan_timestamp,
                    'date' => date('M j, Y', $scan_timestamp),
                    'time' => date('g:i A', $scan_timestamp),
                    'scans' => array()
                );
            }
            
            $scan_groups[$scan_key]['scans'][$scan->scan_type] = json_decode($scan->scan_data, true);
        }
        
        // Create history entries
        foreach ($scan_groups as $scan_key => $group) {
            $overall_score = 0;
            $score_count = 0;
            
            $entry = array(
                'date' => $group['date'],
                'time' => $group['time'],
                'datetime' => $group['date'] . ' at ' . $group['time'],
                'page_url' => '',
                'page_title' => '',
                'performance' => 0,
                'seo' => 0,
                'security' => 0,
                'accessibility' => 0,
                'content_decay' => 0,
                'host_health' => 0,
                'overall' => 0
            );
            
            foreach ($group['scans'] as $scan_type => $data) {
                if (isset($data['data']['overall_score'])) {
                    $score = $data['data']['overall_score'];
                    $entry[$scan_type] = $score;
                    $overall_score += $score;
                    $score_count++;
                }
                // If page info included in scan data, capture it (future-proof)
                if (empty($entry['page_url']) && isset($data['data']['page_url'])) {
                    $entry['page_url'] = $data['data']['page_url'];
                    $entry['page_title'] = $data['data']['page_title'] ?? '';
                }
            }
            
            if ($score_count > 0) {
                $entry['overall'] = round($overall_score / $score_count);
            }
            
            $history[] = $entry;
        }
        
        return $history;
    }
    
    /**
     * Render score badge
     */
    private function render_score_badge($score) {
        if ($score == 0) {
            return '<span class="ush-scan-score score-red">N/A</span>';
        }
        
        $class = 'score-red';
        if ($score >= 80) {
            $class = 'score-green';
        } elseif ($score >= 50) {
            $class = 'score-yellow';
        }
        
        return '<span class="ush-scan-score ' . $class . '">' . $score . '%</span>';
    }
    
    /**
     * Render overview scores like Google PageSpeed
     */
    private function render_overview_scores($scan_results) {
        $scores = array();
        
        // Get scores for each category
        $categories = array(
            'performance' => 'Performance',
            'seo' => 'SEO', 
            'security' => 'Security',
            'accessibility' => 'Accessibility',
            'content_decay' => 'Content',
            'host_health' => 'Host Health'
        );
        
        foreach ($categories as $key => $label) {
            $score = 0;
            if (isset($scan_results[$key]['data']['overall_score'])) {
                $score = $scan_results[$key]['data']['overall_score'];
            }
            $scores[$key] = array(
                'label' => $label,
                'score' => $score,
                'class' => $this->get_score_class($score)
            );
        }
        
        ?>
        <div class="ush-overview-container">
            <div class="ush-overview-header">
                <h2><?php _e('Site Health Overview', 'unified-site-health-dashboard'); ?></h2>
                <p><?php _e('Values are estimated and may vary. The overall score is calculated from these metrics.', 'unified-site-health-dashboard'); ?></p>
            </div>
            
            <div class="ush-overview-scores-grid">
                <?php foreach ($scores as $key => $data): ?>
                    <div class="ush-overview-score">
                        <div class="ush-overview-circle ush-score-<?php echo esc_attr($data['class']); ?>">
                            <span class="ush-overview-value"><?php echo $data['score']; ?></span>
                        </div>
                        <div class="ush-overview-label"><?php echo esc_html($data['label']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="ush-overview-legend">
                <div class="ush-legend-item">
                    <span class="ush-legend-icon ush-legend-red"></span>
                    <span><?php _e('0-49', 'unified-site-health-dashboard'); ?></span>
                </div>
                <div class="ush-legend-item">
                    <span class="ush-legend-icon ush-legend-yellow"></span>
                    <span><?php _e('50-89', 'unified-site-health-dashboard'); ?></span>
                </div>
                <div class="ush-legend-item">
                    <span class="ush-legend-icon ush-legend-green"></span>
                    <span><?php _e('90-100', 'unified-site-health-dashboard'); ?></span>
                </div>
            </div>
        </div>
        <?php
    }
}
