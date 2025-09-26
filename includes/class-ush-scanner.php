<?php
/**
 * Scanner class for Unified Site Health Dashboard
 *
 * @package Unified_Site_Health_Dashboard
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Scanner class for PageSpeed Insights API
 */
class USH_Scanner {
    
    /**
     * Database handler
     */
    private $database;
    
    /**
     * Google PageSpeed Insights API URL
     */
    private $api_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
    
    /**
     * Scan progress option name
     */
    private $progress_option = 'ush_scan_progress';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database = new USH_Database();
    }
    
    /**
     * Start the scan process
     */
    public function start_scan() {
        // Check if API key is configured
        $api_key = get_option('ush_google_api_key');
        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('Google API key not configured', 'unified-site-health-dashboard')));
        }
        
        // Get pages to scan - use static URL for testing
        $pages = $this->get_test_pages();
        
        if (empty($pages)) {
            wp_send_json_error(array('message' => __('No pages found to scan', 'unified-site-health-dashboard')));
        }
        
        // Test API connection first
        $connection_test = $this->test_api_connection($api_key, $pages[0]['url']);
        if (!$connection_test['success']) {
            wp_send_json_error(array('message' => $connection_test['message']));
        }
        
        // Clear any existing progress and initialize new scan
        $this->clear_scan_progress();

        // Define categories order and initial states
        $categories = array('Performance', 'SEO', 'Accessibility', 'Security', 'Host Health');
        $category_state = array();
        foreach ($categories as $c) {
            $category_state[$c] = 'pending';
        }

        $this->set_scan_progress(array(
            'status' => 'running',
            'total_pages' => count($pages),
            'current_page' => 0,
            'current_page_title' => '',
            'scanned_pages' => 0,
            'errors' => array(),
            'current_url' => '',
            'categories' => $categories,
            'category_state' => $category_state,
            'total_categories' => count($categories)
        ));
        
        // Start background scan
        $this->schedule_scan_batch($pages, 0);
    }
    
    /**
     * Test API connection
     *
     * @param string $api_key API key
     * @param string $test_url Test URL
     * @return array Test result
     */
    private function test_api_connection($api_key, $test_url) {
        $url = $this->build_api_url($test_url, 'mobile', $api_key);
        
        $response = wp_remote_get($url, array(
            'timeout' => 60, // Increased timeout for connection test
            'redirection' => 10,
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
                'Accept' => 'application/json'
            ),
            'sslverify' => true,
            'httpversion' => '1.1'
        ));
        
        if (is_wp_error($response)) {
            $error_message = $this->get_user_friendly_error($response->get_error_message());
            return array(
                'success' => false,
                'message' => 'API connection test failed: ' . $error_message
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $error_message = $this->get_http_error_message($response_code);
            return array(
                'success' => false,
                'message' => 'API connection test failed: ' . $error_message
            );
        }
        
        return array(
            'success' => true,
            'message' => 'API connection successful'
        );
    }
    
    /**
     * Get test pages for development
     *
     * @return array Test pages
     */
    private function get_test_pages() {
        // For now, use static URL for testing (development environment)
        // Use the provided staging URL which is accessible to PageSpeed Insights
        $test_url = 'https://gol-hh.stagingwp.website/';
        
        return array(
            array(
                'id' => 1,
                'url' => $test_url,
                'title' => 'Homepage'
            )
        );
    }
    
    /**
     * Get dynamic site pages (for future use)
     *
     * @return array Site pages
     */
    private function get_site_pages() {
        return $this->database->get_pages_to_scan();
    }
    
    /**
     * Schedule scan batch
     *
     * @param array $pages Pages to scan
     * @param int $batch_start Batch start index
     */
    public function schedule_scan_batch($pages, $batch_start) {
        $batch_size = 2; // Process 2 pages at a time
        $batch_end = min($batch_start + $batch_size, count($pages));
        
        for ($i = $batch_start; $i < $batch_end; $i++) {
            $page = $pages[$i];
            
            // Update progress with current page
            $progress = get_option($this->progress_option, array());
            $progress['current_page'] = $i + 1;
            // mark in-progress scanned_pages (pages fully processed will be i+1)
            $progress['scanned_pages'] = $i;
            $progress['percentage'] = isset($progress['total_pages']) && $progress['total_pages'] > 0 ? round(($progress['scanned_pages'] / $progress['total_pages']) * 100) : 0;
            $this->set_scan_progress($progress);
            
            // Scan both mobile and desktop
            $this->scan_page($page, 'mobile');
            $this->scan_page($page, 'desktop');

            // Also write Security and Host Health audits based on WP/server checks
            $this->run_wp_checks($page);
            
            // After finishing both strategies for a page (and WP checks), increment scanned_pages
            $progress = get_option($this->progress_option, array());
            $progress['scanned_pages'] = $i + 1;
            $progress['percentage'] = isset($progress['total_pages']) && $progress['total_pages'] > 0 ? round(($progress['scanned_pages'] / $progress['total_pages']) * 100) : 0;
            $this->set_scan_progress($progress);
        }
        
        // Check if more pages to scan
        if ($batch_end < count($pages)) {
            // Schedule next batch
            wp_schedule_single_event(time() + 5, 'ush_scan_batch', array($pages, $batch_end));
        } else {
            // Scan completed
            $progress = get_option($this->progress_option, array());
            $progress['status'] = 'completed';
            $progress['current_page'] = count($pages);
            $progress['scanned_pages'] = count($pages);
            $progress['percentage'] = 100;
            $progress['current_page_title'] = '';
            $progress['current_url'] = '';
            $progress['completed_at'] = current_time('mysql');
            $this->set_scan_progress($progress);
        }
    }
    
    /**
     * Scan a single page
     *
     * @param array $page Page data
     * @param string $strategy Scan strategy (mobile/desktop)
     */
    private function scan_page($page, $strategy) {
        $api_key = get_option('ush_google_api_key');
        
        // Update progress with current page being scanned
        $this->update_scan_progress($page['title'], $page['url']);
        
        $url = $this->build_api_url($page['url'], $strategy, $api_key);
        
        // Try the request with retry logic
        $response = $this->make_api_request($url, $page['title']);
        
        if (is_wp_error($response)) {
            return; // Error already handled in make_api_request
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $error_message = $this->get_http_error_message($response_code);
            $this->add_scan_error($page['title'], $error_message, 'Performance');
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->add_scan_error($page['title'], 'Invalid JSON response from Google API', 'Performance');
            return;
        }
        
        if (isset($data['error'])) {
            $error_message = $this->get_api_error_message($data['error']);
            $this->add_scan_error($page['title'], $error_message, 'Performance');
            return;
        }
        
        // Process audit results
        $this->process_audit_results($page, $strategy, $data);
    }
    
    /**
     * Build Google PSI API URL exactly like Postman example
     *
     * @param string $page_url
     * @param string $strategy
     * @param string $api_key
     * @return string
     */
    private function build_api_url($page_url, $strategy, $api_key) {
        $encoded_url = rawurlencode($page_url);
        return $this->api_url . '?url=' . $encoded_url . '&strategy=' . $strategy . '&key=' . $api_key;
    }
    
    /**
     * Make API request with retry logic
     *
     * @param string $url API URL
     * @param string $page_title Page title for error reporting
     * @return array|WP_Error Response or error
     */
    private function make_api_request($url, $page_title) {
        $max_retries = 2;
        $retry_delay = 5; // seconds
        
        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
        $response = wp_remote_get($url, array(
            'timeout' => 60, // 60 seconds timeout
            'redirection' => 10,
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
                'Accept' => 'application/json'
            ),
            'sslverify' => true,
            'httpversion' => '1.1'
        ));
            $response = wp_remote_get($url, array(
                'timeout' => 60, // 60 seconds timeout
                'redirection' => 10,
                'headers' => array(
                    'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
                    'Accept' => 'application/json'
                ),
                'sslverify' => true,
                'httpversion' => '1.1'
            ));
            
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                
                // Check if it's a timeout error and we have retries left
                if (strpos($error_message, 'cURL error 28') !== false && $attempt < $max_retries) {
                    // Wait before retry
                    sleep($retry_delay);
                    continue;
                }
                
                // Handle the error
                $friendly_error = $this->get_user_friendly_error($error_message);
                $this->add_scan_error($page_title, $friendly_error, 'Performance');
                return $response;
            }
            
            // Success
            return $response;
        }
        
        // If we get here, all retries failed
        $this->add_scan_error($page_title, 'Request failed after multiple attempts. Please try again later.', 'Performance');
        return new WP_Error('api_request_failed', 'All retry attempts failed');
    }
    
    /**
     * Get user-friendly error message
     *
     * @param string $error_message Original error message
     * @return string User-friendly error message
     */
    private function get_user_friendly_error($error_message) {
        if (strpos($error_message, 'cURL error 6') !== false) {
            return 'The provided URL is not valid.';
        }
        
        if (strpos($error_message, 'cURL error 7') !== false) {
            return 'This site is not accessible from Google API (local environment).';
        }
        
        if (strpos($error_message, 'cURL error 28') !== false || strpos($error_message, 'timeout') !== false) {
            return 'Request timeout. The Google PageSpeed Insights API is taking longer than expected. This may be due to the site being slow or the API being busy. Please try again in a few minutes.';
        }
        
        if (strpos($error_message, 'cURL error 35') !== false) {
            return 'SSL connection error. Please check your server SSL configuration.';
        }
        
        if (strpos($error_message, 'cURL error 52') !== false) {
            return 'Empty reply from server. The Google API may be temporarily unavailable.';
        }
        
        return 'Unexpected error: ' . $error_message;
    }
    
    /**
     * Get HTTP error message
     *
     * @param int $response_code HTTP response code
     * @return string Error message
     */
    private function get_http_error_message($response_code) {
        switch ($response_code) {
            case 400:
                return 'The provided URL is not valid.';
            case 403:
                return 'API key is invalid or quota exceeded.';
            case 404:
                return 'Google PageSpeed Insights API not found.';
            case 429:
                return 'API quota exceeded. Please try again later.';
            case 500:
                return 'Google API server error. Please try again later.';
            default:
                return 'HTTP Error ' . $response_code . ': Request failed.';
        }
    }
    
    /**
     * Get API error message
     *
     * @param array $error API error object
     * @return string Error message
     */
    private function get_api_error_message($error) {
        if (isset($error['message'])) {
            $message = $error['message'];
            
            if (strpos($message, 'Invalid URL') !== false) {
                return 'The provided URL is not valid.';
            }
            
            if (strpos($message, 'not accessible') !== false || strpos($message, 'unreachable') !== false) {
                return 'This site is not accessible from Google API (local environment).';
            }
            
            if (strpos($message, 'quota') !== false) {
                return 'API quota exceeded. Please try again later.';
            }
            
            return 'Unexpected error: ' . $message;
        }
        
        return 'Unexpected error: Unknown API error';
    }
    
    /**
     * Process audit results from API response
     *
     * @param array $page Page data
     * @param string $strategy Scan strategy
     * @param array $data API response data
     */
    private function process_audit_results($page, $strategy, $data) {
        if (!isset($data['lighthouseResult']['audits'])) {
            return;
        }
        
        $audits = $data['lighthouseResult']['audits'];
        
        foreach ($audits as $audit_id => $audit_data) {
            // Skip if audit has no useful data
            // Some accessibility audits provide details but no numeric score (score === null).
            // Store audits if they have a title and at least one of: score, description, or details.items
            if (!isset($audit_data['title'])) {
                continue;
            }

            $has_score = array_key_exists('score', $audit_data) && $audit_data['score'] !== null;
            $has_description = !empty($audit_data['description']);
            $has_items = isset($audit_data['details']['items']) && !empty($audit_data['details']['items']);

            if (!$has_score && !$has_description && !$has_items) {
                // Nothing useful to store
                continue;
            }
            
            // Determine category
            $category = $this->get_audit_category($audit_id, $audit_data);
            
            // Determine severity
            $severity = $this->get_audit_severity($audit_data['score']);
            
            // Extract affected element
            $element = $this->extract_affected_element($audit_data);
            
            // Store audit result
            $result_data = array(
                'page_id' => $page['id'],
                'page_url' => $page['url'],
                'scan_type' => $strategy,
                'audit_category' => $category,
                'audit_name' => $audit_data['title'],
                'audit_score' => isset($audit_data['score']) ? $audit_data['score'] : '',
                'audit_description' => isset($audit_data['description']) ? $audit_data['description'] : '',
                'audit_element' => $element,
                'severity' => $severity
            );
            
            $this->database->store_scan_result($result_data);
        }

        // If lighthouse categories are available, store category-level scores so tiles
        // and the UI can rely on explicit category scores (Performance, SEO, Accessibility, Security)
        if (isset($data['lighthouseResult']['categories']) && is_array($data['lighthouseResult']['categories'])) {
            $category_map = array(
                'performance' => 'Performance',
                'seo' => 'SEO',
                'accessibility' => 'Accessibility',
                'best-practices' => 'Security'
            );

            foreach ($data['lighthouseResult']['categories'] as $key => $cat) {
                if (!isset($category_map[$key])) {
                    continue;
                }

                $cat_name = $category_map[$key];
                // mark category as running in progress
                $progress = get_option($this->progress_option, array());
                if (!isset($progress['category_state'])) {
                    $progress['category_state'] = array();
                }
                $progress['category_state'][$cat_name] = 'running';
                $this->set_scan_progress($progress);
                $score = isset($cat['score']) ? $cat['score'] : null; // 0-1 or null

                // Update progress to indicate which category is currently being processed
                $progress = get_option($this->progress_option, array());
                $progress['current_category'] = $cat_name;
                $this->set_scan_progress($progress);

                // Store a synthetic "Category Score" audit so the DB contains concrete values
                $result = array(
                    'page_id' => $page['id'],
                    'page_url' => $page['url'],
                    'scan_type' => $strategy,
                    'audit_category' => $cat_name,
                    'audit_name' => 'Category Score',
                    'audit_score' => $score,
                    'audit_description' => isset($cat['title']) ? $cat['title'] : $cat_name . ' score',
                    'audit_element' => '',
                    'severity' => $this->get_audit_severity($score)
                );

                $this->database->store_scan_result($result);

                // mark category as completed
                $progress = get_option($this->progress_option, array());
                if (!isset($progress['category_state'])) {
                    $progress['category_state'] = array();
                }
                $progress['category_state'][$cat_name] = 'completed';
                $this->set_scan_progress($progress);
            }

            // Clear current_category after processing
            $progress = get_option($this->progress_option, array());
            if (isset($progress['current_category'])) {
                unset($progress['current_category']);
                $this->set_scan_progress($progress);
            }
        }
    }
    
    /**
     * Get audit category based on audit ID and data
     *
     * @param string $audit_id Audit ID
     * @param array $audit_data Audit data
     * @return string Category name
     */
    private function get_audit_category($audit_id, $audit_data) {
        // Performance audits
        $performance_audits = array(
            'first-contentful-paint', 'largest-contentful-paint', 'speed-index',
            'cumulative-layout-shift', 'first-meaningful-paint', 'interactive',
            'total-blocking-time', 'max-potential-fid', 'render-blocking-resources',
            'unused-css-rules', 'unused-javascript', 'modern-image-formats',
            'uses-optimized-images', 'uses-text-compression', 'uses-responsive-images'
        );
        
        // SEO audits
        $seo_audits = array(
            'meta-description', 'document-title', 'link-text', 'is-crawlable',
            'robots-txt', 'hreflang', 'canonical', 'font-size', 'tap-targets',
            'plugins', 'viewport', 'crawlable-anchors', 'image-alt'
        );
        
        // Accessibility audits
        $accessibility_audits = array(
            'color-contrast', 'image-alt', 'label', 'button-name', 'form-field-multiple-labels',
            'heading-order', 'html-has-lang', 'html-lang-valid', 'input-image-alt',
            'link-name', 'object-alt', 'video-caption', 'video-description',
            'duplicate-id', 'aria-allowed-attr', 'aria-hidden-body', 'aria-allowed-attr',
            'document-title', 'meta-description', 'tabindex', 'frame-title', 'accesskeys',
            'aria-roles', 'aria-allowed-attr'
        );
        
        // Security audits
        $security_audits = array(
            'is-on-https', 'uses-http2', 'no-vulnerable-libraries', 'notification-on-start',
            'redirects-http', 'external-anchors-use-rel-noopener'
        );
        
        if (in_array($audit_id, $performance_audits)) {
            return 'Performance';
        } elseif (in_array($audit_id, $seo_audits)) {
            return 'SEO';
        } elseif (in_array($audit_id, $accessibility_audits)) {
            return 'Accessibility';
        } elseif (in_array($audit_id, $security_audits)) {
            return 'Security';
        } else {
            return 'Content';
        }
    }

    /**
     * Run WordPress/server-based checks and store as audits
     *
     * @param array $page
     * @return void
     */
    private function run_wp_checks($page) {
        // Mark Host Health category as running
        $progress = get_option($this->progress_option, array());
        if (!isset($progress['category_state'])) {
            $progress['category_state'] = array();
        }
        $progress['category_state']['Host Health'] = 'running';
        $this->set_scan_progress($progress);

        // Security checks
        $security_items = array();
        $security_items[] = array(
            'title' => 'Site served over HTTPS',
            'score' => is_ssl() ? 1 : 0,
            'description' => 'Checks whether the admin is being served over HTTPS.',
            'element' => home_url(),
            'severity' => is_ssl() ? 'good' : 'warning'
        );
        $security_items[] = array(
            'title' => 'Filesystem access method',
            'score' => defined('FS_METHOD') ? 0.6 : 1,
            'description' => 'Verifies WP filesystem method configuration.',
            'element' => defined('FS_METHOD') ? FS_METHOD : 'direct',
            'severity' => defined('FS_METHOD') && FS_METHOD !== 'direct' ? 'warning' : 'good'
        );
        foreach ($security_items as $item) {
            $this->database->store_scan_result(array(
                'page_id' => $page['id'],
                'page_url' => $page['url'],
                'scan_type' => 'desktop',
                'audit_category' => 'Security',
                'audit_name' => $item['title'],
                'audit_score' => $item['score'],
                'audit_description' => $item['description'],
                'audit_element' => $item['element'],
                'severity' => $item['severity']
            ));
        }

        // Host Health checks
        global $wpdb;
        $php_ok = PHP_VERSION_ID >= 70400;
        $host_items = array();
        $host_items[] = array(
            'title' => 'PHP Version',
            'score' => PHP_VERSION_ID >= 80100 ? 1 : (PHP_VERSION_ID >= 70400 ? 0.7 : 0.3),
            'description' => 'Server PHP version.',
            'element' => PHP_VERSION,
            'severity' => PHP_VERSION_ID >= 80100 ? 'good' : (PHP_VERSION_ID >= 70400 ? 'warning' : 'critical')
        );
        $host_items[] = array(
            'title' => 'Database Version',
            'score' => 1,
            'description' => 'Database server version.',
            'element' => $wpdb->db_version(),
            'severity' => 'good'
        );
        foreach ($host_items as $item) {
            $this->database->store_scan_result(array(
                'page_id' => $page['id'],
                'page_url' => $page['url'],
                'scan_type' => 'desktop',
                'audit_category' => 'Host Health',
                'audit_name' => $item['title'],
                'audit_score' => $item['score'],
                'audit_description' => $item['description'],
                'audit_element' => $item['element'],
                'severity' => $item['severity']
            ));
        }

        // Mark Host Health completed
        $progress = get_option($this->progress_option, array());
        if (!isset($progress['category_state'])) {
            $progress['category_state'] = array();
        }
        $progress['category_state']['Host Health'] = 'completed';
        $this->set_scan_progress($progress);
    }
    
    /**
     * Get audit severity based on score
     *
     * @param mixed $score Audit score
     * @return string Severity level
     */
    private function get_audit_severity($score) {
        if ($score === null) {
            return 'info';
        }
        
        if (is_numeric($score)) {
            if ($score >= 0.9) {
                return 'good';
            } elseif ($score >= 0.5) {
                return 'warning';
            } else {
                return 'critical';
            }
        }
        
        return 'info';
    }
    
    /**
     * Extract affected element from audit data
     *
     * @param array $audit_data Audit data
     * @return string Affected element
     */
    private function extract_affected_element($audit_data) {
        if (isset($audit_data['details']['items'])) {
            $items = $audit_data['details']['items'];
            if (is_array($items) && !empty($items)) {
                $first_item = $items[0];
                if (isset($first_item['url'])) {
                    return $first_item['url'];
                } elseif (isset($first_item['node'])) {
                    return $first_item['node']['selector'];
                }
            }
        }
        
        if (isset($audit_data['details']['overallSavingsMs'])) {
            return 'Overall savings: ' . $audit_data['details']['overallSavingsMs'] . 'ms';
        }
        
        return '';
    }
    
    /**
     * Set scan progress
     *
     * @param array $progress Progress data
     */
    private function set_scan_progress($progress) {
        update_option($this->progress_option, $progress);
    }
    
    /**
     * Update scan progress
     *
     * @param string $page_title Current page title
     * @param string $page_url Current page URL
     */
    private function update_scan_progress($page_title, $page_url) {
        $progress = get_option($this->progress_option, array());
        $progress['current_page_title'] = $page_title;
        $progress['current_url'] = $page_url;
        $this->set_scan_progress($progress);
    }
    
    /**
     * Add scan error and optionally mark a category as failed
     *
     * @param string $page_title Page title
     * @param string $error_message Error message
     * @param string|null $category Optional category to mark as failed
     */
    private function add_scan_error($page_title, $error_message, $category = null) {
        $progress = get_option($this->progress_option, array());
        if (!isset($progress['errors'])) {
            $progress['errors'] = array();
        }
        $progress['errors'][] = array(
            'page' => $page_title,
            'message' => $error_message
        );

        // Mark category as failed if provided
        if ($category) {
            if (!isset($progress['category_state'])) {
                $progress['category_state'] = array();
            }
            $progress['category_state'][$category] = 'failed';
        }

        $this->set_scan_progress($progress);
    }
    
    /**
     * Get scan progress
     *
     * @return array Scan progress data
     */
    public function get_scan_progress() {
        return get_option($this->progress_option, array(
            'status' => 'idle',
            'total_pages' => 0,
            'current_page' => 0,
            'current_page_title' => '',
            'scanned_pages' => 0,
            'errors' => array()
        ));
    }
    
    /**
     * Clear scan progress
     */
    public function clear_scan_progress() {
        delete_option($this->progress_option);
    }
    
    /**
     * Check if scan is running
     *
     * @return bool True if scan is running
     */
    public function is_scan_running() {
        $progress = $this->get_scan_progress();
        return $progress['status'] === 'running';
    }
}
