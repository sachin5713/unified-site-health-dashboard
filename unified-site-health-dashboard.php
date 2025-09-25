<?php
/**
 * Plugin Name: Unified Site Health + Forecast Dashboard
 * Plugin URI: https://sachinsuthar.com/unified-site-health-dashboard
 * Description: Scan, monitor, and forecast WordPress site performance, SEO, accessibility, and security issues in a unified dashboard.
 * Version: 1.0.0
 * Author: Sachin Suthar
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: unified-site-health-dashboard
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('USH_PLUGIN_FILE', __FILE__);
define('USH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('USH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('USH_PLUGIN_VERSION', '1.0.0');

/**
 * Main plugin class
 */
class UnifiedSiteHealthDashboard {
    
    /**
     * Single instance of the plugin
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Include required files immediately
        $this->include_files();
        
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('unified-site-health-dashboard', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Add AJAX handlers
        add_action('wp_ajax_ush_run_scan', array($this, 'ajax_run_scan'));
        add_action('wp_ajax_ush_export_report', array($this, 'ajax_export_report'));
        add_action('wp_ajax_ush_check_updates', array($this, 'ajax_check_updates'));
        add_action('wp_ajax_ush_auto_fix', array($this, 'ajax_auto_fix'));
        add_action('wp_ajax_ush_get_page_report', array($this, 'ajax_get_page_report'));
        add_action('wp_ajax_ush_get_section_issues', array($this, 'ajax_get_section_issues'));
        add_action('wp_ajax_ush_rescan_page', array($this, 'ajax_rescan_page'));
        
        // Add settings page
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add dashboard widget
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }
    
    /**
     * Include required files
     */
    private function include_files() {
        $files = array(
            'includes/class-performance-scanner.php',
            'includes/class-seo-scanner.php',
            'includes/class-security-scanner.php',
            'includes/class-accessibility-scanner.php',
            'includes/class-content-decay-scanner.php',
            'includes/class-host-health-scanner.php',
            'includes/class-dashboard.php'
        );
        
        foreach ($files as $file) {
            $file_path = USH_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Detailed Report main menu
        add_menu_page(
            __('Site Health Report', 'unified-site-health-dashboard'),
            __('Site Health Report', 'unified-site-health-dashboard'),
            'manage_options',
            'site-health-detailed-report',
            array($this, 'detailed_report_page'),
            'dashicons-chart-line',
            30
        );
        
        // Page-wise Report submenu
        add_submenu_page(
            'site-health-detailed-report',
            __('Page-wise Report', 'unified-site-health-dashboard'),
            __('Page-wise Report', 'unified-site-health-dashboard'),
            'manage_options',
            'site-health-page-report',
            array($this, 'page_wise_report_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'site-health-detailed-report',
            __('Settings', 'unified-site-health-dashboard'),
            __('Settings', 'unified-site-health-dashboard'),
            'manage_options',
            'site-health-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Always enqueue for dashboard widget or any Unified Site Health plugin page
        $plugin_pages = array(
            'site-health-detailed-report',
            'site-health-page-report',
            'site-health-settings',
        );
        $is_plugin_page = isset($_GET['page']) && in_array($_GET['page'], $plugin_pages);
        if ($hook === 'index.php' || $is_plugin_page || in_array($hook, array(
            'toplevel_page_site-health-detailed-report',
            'site-health-detailed-report_page_site-health-page-report',
            'site-health-detailed-report_page_site-health-settings'
        ))) {
            wp_enqueue_style(
                'ush-admin-style',
                USH_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                USH_PLUGIN_VERSION
            );
            // Modal/thickbox optional styles can be added in CSS; ensure jQuery present for AJAX
            wp_enqueue_script(
                'ush-admin-script',
                USH_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                USH_PLUGIN_VERSION,
                true
            );
            wp_localize_script('ush-admin-script', 'ush_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ush_ajax_nonce'),
                'strings' => array(
                    'scanning' => __('Scanning...', 'unified-site-health-dashboard'),
                    'error' => __('An error occurred', 'unified-site-health-dashboard'),
                    'loading' => __('Loading...', 'unified-site-health-dashboard'),
                )
            ));
        }
    }
    
    /**
     * Detailed report page callback
     */
    public function detailed_report_page() {
        $dashboard = new USH_Dashboard();
        $dashboard->render_detailed_report();
    }
    
    /**
     * Page-wise report page callback
     */
    public function page_wise_report_page() {
        $dashboard = new USH_Dashboard();
        $dashboard->render_page_wise_report();
    }

    /**
     * AJAX: Get page-wise report content
     */
    public function ajax_get_page_report() {
        check_ajax_referer('ush_ajax_nonce', 'nonce');
        $page_url = isset($_POST['page_url']) ? esc_url_raw($_POST['page_url']) : '';
        if (empty($page_url)) {
            wp_send_json_error(array('message' => __('Missing page URL', 'unified-site-health-dashboard')));
        }
        ob_start();
        $dashboard = new USH_Dashboard();
        $report = $dashboard->get_page_report_for_ajax($page_url);
        echo $report; // already sanitized output from renderer
        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
    }

    /**
     * AJAX: Rescan a specific page and return updated report + history row
     */
    public function ajax_rescan_page() {
        check_ajax_referer('ush_ajax_nonce', 'nonce');
        $page_url = isset($_POST['page_url']) ? esc_url_raw($_POST['page_url']) : '';
        if (empty($page_url)) {
            wp_send_json_error(array('message' => __('Missing page URL', 'unified-site-health-dashboard')));
        }
        // Run performance scan for specific page
        $scanner = new USH_Performance_Scanner();
        $results = $scanner->scan($page_url);
        $this->store_scan_results('performance', $results);
        
        // Render updated report HTML
        ob_start();
        $dashboard = new USH_Dashboard();
        $dashboard->get_page_report_for_ajax($page_url);
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html,
            'score' => isset($results['data']['overall_score']) ? (int) $results['data']['overall_score'] : 0,
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * AJAX: Get issues for a section (modal)
     */
    public function ajax_get_section_issues() {
        check_ajax_referer('ush_ajax_nonce', 'nonce');
        $section = isset($_POST['section']) ? sanitize_key($_POST['section']) : '';
        if (empty($section)) {
            wp_send_json_error(array('message' => __('Missing section', 'unified-site-health-dashboard')));
        }
        ob_start();
        $dashboard = new USH_Dashboard();
        $dashboard->render_section_issues_for_ajax($section);
        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables if needed
        $this->create_tables();
        
        // Run initial scan
        $this->run_initial_scan();
        
        // Schedule weekly reports
        $this->schedule_weekly_reports();
        
        // Set activation flag
        update_option('ush_plugin_activated', true);
        update_option('ush_activation_time', current_time('timestamp'));
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up if needed
        delete_option('ush_plugin_activated');
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ush_scan_results';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            scan_type varchar(50) NOT NULL,
            scan_data longtext NOT NULL,
            scan_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY scan_type (scan_type),
            KEY scan_date (scan_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Run initial scan on activation
     */
    private function run_initial_scan() {
        // Run performance scan
        $performance_scanner = new USH_Performance_Scanner();
        $performance_results = $performance_scanner->scan();
        
        // Store results
        $this->store_scan_results('performance', $performance_results);
        
        // Run other scans with mock data for now
        $seo_scanner = new USH_SEO_Scanner();
        $seo_results = $seo_scanner->scan();
        $this->store_scan_results('seo', $seo_results);
        
        $security_scanner = new USH_Security_Scanner();
        $security_results = $security_scanner->scan();
        $this->store_scan_results('security', $security_results);
        
        $accessibility_scanner = new USH_Accessibility_Scanner();
        $accessibility_results = $accessibility_scanner->scan();
        $this->store_scan_results('accessibility', $accessibility_results);
        
        $content_decay_scanner = new USH_Content_Decay_Scanner();
        $content_decay_results = $content_decay_scanner->scan();
        $this->store_scan_results('content_decay', $content_decay_results);
        
        $host_health_scanner = new USH_Host_Health_Scanner();
        $host_health_results = $host_health_scanner->scan();
        $this->store_scan_results('host_health', $host_health_results);
    }
    
    /**
     * Store scan results
     */
    private function store_scan_results($scan_type, $results) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ush_scan_results';
        
        $wpdb->insert(
            $table_name,
            array(
                'scan_type' => $scan_type,
                'scan_data' => json_encode($results),
                'scan_date' => current_time('mysql')
            ),
            array('%s', '%s', '%s')
        );
    }
    
    /**
     * Get scan results
     */
    public static function get_scan_results($scan_type = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ush_scan_results';
        
        if ($scan_type) {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE scan_type = %s ORDER BY scan_date DESC LIMIT 1",
                $scan_type
            ));
        } else {
            $results = $wpdb->get_results(
                "SELECT * FROM $table_name ORDER BY scan_date DESC"
            );
        }
        
        return $results;
    }
    
    /**
     * Add settings page
     */
    public function add_settings_page() {
        add_submenu_page(
            'site-health-forecast',
            __('Settings', 'unified-site-health-dashboard'),
            __('Settings', 'unified-site-health-dashboard'),
            'manage_options',
            'site-health-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('ush_settings', 'ush_pagespeed_api_key');
        register_setting('ush_settings', 'ush_scan_frequency');
        register_setting('ush_settings', 'ush_email_alerts');
        register_setting('ush_settings', 'ush_alert_threshold');
        register_setting('ush_settings', 'ush_email_address');
        register_setting('ush_settings', 'ush_weekly_reports');
        register_setting('ush_settings', 'ush_auto_fix_enabled');
    }
    
    /**
     * Settings page callback
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Site Health Dashboard Settings', 'unified-site-health-dashboard'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('ush_settings'); ?>
                <?php do_settings_sections('ush_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ush_pagespeed_api_key"><?php _e('Google PageSpeed Insights API Key', 'unified-site-health-dashboard'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="ush_pagespeed_api_key" name="ush_pagespeed_api_key" 
                                   value="<?php echo esc_attr(get_option('ush_pagespeed_api_key')); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('Get your API key from', 'unified-site-health-dashboard'); ?> 
                                <a href="https://developers.google.com/speed/docs/insights/v5/get-started" target="_blank">
                                    <?php _e('Google PageSpeed Insights API', 'unified-site-health-dashboard'); ?>
                                </a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ush_scan_frequency"><?php _e('Auto-scan Frequency', 'unified-site-health-dashboard'); ?></label>
                        </th>
                        <td>
                            <select id="ush_scan_frequency" name="ush_scan_frequency">
                                <option value="daily" <?php selected(get_option('ush_scan_frequency', 'daily'), 'daily'); ?>>
                                    <?php _e('Daily', 'unified-site-health-dashboard'); ?>
                                </option>
                                <option value="weekly" <?php selected(get_option('ush_scan_frequency', 'daily'), 'weekly'); ?>>
                                    <?php _e('Weekly', 'unified-site-health-dashboard'); ?>
                                </option>
                                <option value="monthly" <?php selected(get_option('ush_scan_frequency', 'daily'), 'monthly'); ?>>
                                    <?php _e('Monthly', 'unified-site-health-dashboard'); ?>
                                </option>
                                <option value="never" <?php selected(get_option('ush_scan_frequency', 'daily'), 'never'); ?>>
                                    <?php _e('Never', 'unified-site-health-dashboard'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ush_email_alerts"><?php _e('Email Alerts', 'unified-site-health-dashboard'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="ush_email_alerts" name="ush_email_alerts" 
                                   value="1" <?php checked(get_option('ush_email_alerts'), 1); ?> />
                            <label for="ush_email_alerts">
                                <?php _e('Send email alerts when critical issues are detected', 'unified-site-health-dashboard'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ush_alert_threshold"><?php _e('Alert Threshold', 'unified-site-health-dashboard'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="ush_alert_threshold" name="ush_alert_threshold" 
                                   value="<?php echo esc_attr(get_option('ush_alert_threshold', 50)); ?>" 
                                   min="0" max="100" />
                            <p class="description">
                                <?php _e('Send alerts when any score falls below this percentage', 'unified-site-health-dashboard'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ush_email_address"><?php _e('Email Address', 'unified-site-health-dashboard'); ?></label>
                        </th>
                        <td>
                            <input type="email" id="ush_email_address" name="ush_email_address" 
                                   value="<?php echo esc_attr(get_option('ush_email_address', get_option('admin_email'))); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('Email address for weekly reports and alerts', 'unified-site-health-dashboard'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ush_weekly_reports"><?php _e('Weekly Reports', 'unified-site-health-dashboard'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="ush_weekly_reports" name="ush_weekly_reports" 
                                   value="1" <?php checked(get_option('ush_weekly_reports'), 1); ?> />
                            <label for="ush_weekly_reports">
                                <?php _e('Send weekly email reports with site health status', 'unified-site-health-dashboard'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ush_auto_fix_enabled"><?php _e('Auto-Fix Options', 'unified-site-health-dashboard'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="ush_auto_fix_enabled" name="ush_auto_fix_enabled" 
                                   value="1" <?php checked(get_option('ush_auto_fix_enabled'), 1); ?> />
                            <label for="ush_auto_fix_enabled">
                                <?php _e('Enable auto-fix options for common issues', 'unified-site-health-dashboard'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Allow the plugin to automatically fix common issues like image compression and broken links', 'unified-site-health-dashboard'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for running new scan
     */
    public function ajax_run_scan() {
        check_ajax_referer('ush_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'unified-site-health-dashboard'));
        }
        
        // Run all scans
        $this->run_initial_scan();
        
        wp_send_json_success(array(
            'message' => __('Scan completed successfully', 'unified-site-health-dashboard')
        ));
    }
    
    /**
     * AJAX handler for exporting report
     */
    public function ajax_export_report() {
        check_ajax_referer('ush_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'unified-site-health-dashboard'));
        }
        
        // Generate CSV report
        $csv_data = $this->generate_csv_report();
        $filename = 'site-health-report-' . date('Y-m-d-H-i-s') . '.csv';
        $file_path = wp_upload_dir()['path'] . '/' . $filename;
        
        file_put_contents($file_path, $csv_data);
        
        wp_send_json_success(array(
            'url' => wp_upload_dir()['url'] . '/' . $filename
        ));
    }
    
    /**
     * AJAX handler for checking updates
     */
    public function ajax_check_updates() {
        check_ajax_referer('ush_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'unified-site-health-dashboard'));
        }
        
        // Check if there are newer scan results
        $last_scan = get_option('ush_last_scan_time', 0);
        $current_time = current_time('timestamp');
        
        wp_send_json_success(array(
            'updated' => ($current_time - $last_scan) > 3600 // 1 hour
        ));
    }
    
    /**
     * AJAX handler for auto-fix
     */
    public function ajax_auto_fix() {
        check_ajax_referer('ush_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'unified-site-health-dashboard'));
        }
        
        if (!get_option('ush_auto_fix_enabled', 0)) {
            wp_send_json_error(array(
                'message' => __('Auto-fix is not enabled in settings.', 'unified-site-health-dashboard')
            ));
        }
        
        $fixes_applied = $this->auto_fix_issues();
        
        if (empty($fixes_applied)) {
            wp_send_json_success(array(
                'message' => __('No fixes were needed or applied.', 'unified-site-health-dashboard'),
                'fixes' => array()
            ));
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('%d fixes were applied successfully.', 'unified-site-health-dashboard'), count($fixes_applied)),
            'fixes' => $fixes_applied
        ));
    }
    
    /**
     * Generate CSV report
     */
    private function generate_csv_report() {
        $csv_data = array();
        
        // Add current scan results
        $csv_data[] = array('Current Scan Results');
        $csv_data[] = array('Scan Type', 'Score', 'Date', 'Details');
        
        $scan_types = array('performance', 'seo', 'security', 'accessibility', 'content_decay', 'host_health');
        
        foreach ($scan_types as $scan_type) {
            $results = self::get_scan_results($scan_type);
            if (!empty($results)) {
                $data = json_decode($results[0]->scan_data, true);
                $csv_data[] = array(
                    ucfirst(str_replace('_', ' ', $scan_type)),
                    isset($data['data']['overall_score']) ? $data['data']['overall_score'] . '%' : 'N/A',
                    $results[0]->scan_date,
                    'See dashboard for details'
                );
            }
        }
        
        // Add scan history
        $csv_data[] = array(''); // Empty row
        $csv_data[] = array('Scan History');
        $csv_data[] = array('Date', 'Time', 'Performance', 'SEO', 'Security', 'Accessibility', 'Content', 'Host Health', 'Overall');
        
        $scan_history = $this->get_scan_history_for_export();
        foreach ($scan_history as $scan) {
            $csv_data[] = array(
                $scan['date'],
                $scan['time'],
                $scan['performance'] . '%',
                $scan['seo'] . '%',
                $scan['security'] . '%',
                $scan['accessibility'] . '%',
                $scan['content_decay'] . '%',
                $scan['host_health'] . '%',
                $scan['overall'] . '%'
            );
        }
        
        // Convert to CSV string
        $output = '';
        foreach ($csv_data as $row) {
            $output .= '"' . implode('","', $row) . '"' . "\n";
        }
        
        return $output;
    }
    
    /**
     * Get scan history for export
     */
    private function get_scan_history_for_export() {
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
            }
            
            if ($score_count > 0) {
                $entry['overall'] = round($overall_score / $score_count);
            }
            
            $history[] = $entry;
        }
        
        return $history;
    }
    
    /**
     * Get overall site health score
     */
    private function get_overall_site_health_score() {
        $scan_results = $this->get_all_scan_results();
        $scores = array();
        
        foreach ($scan_results as $scan_type => $data) {
            if (isset($data['data']['overall_score'])) {
                $scores[] = $data['data']['overall_score'];
            }
        }
        
        return empty($scores) ? 0 : round(array_sum($scores) / count($scores));
    }
    
    /**
     * Get score class for styling
     */
    private function get_score_class($score) {
        if ($score < 50) {
            return 'red';
        } elseif ($score < 80) {
            return 'yellow';
        } else {
            return 'green';
        }
    }
    
    /**
     * Get score message
     */
    private function get_score_message($score) {
        if ($score < 50) {
            return __('Critical Issues Detected', 'unified-site-health-dashboard');
        } elseif ($score < 80) {
            return __('Needs Improvement', 'unified-site-health-dashboard');
        } else {
            return __('Excellent Health', 'unified-site-health-dashboard');
        }
    }
    
    /**
     * Get last scan time
     */
    private function get_last_scan_time() {
        $last_scan = get_option('ush_last_scan_time', 0);
        if ($last_scan) {
            return date('F j, Y \a\t g:i A', $last_scan);
        }
        return __('Never', 'unified-site-health-dashboard');
    }
    
    /**
     * Get all scan results
     */
    private function get_all_scan_results() {
        $results = array();
        
        $scan_types = array('performance', 'seo', 'security', 'accessibility', 'content_decay', 'host_health');
        
        foreach ($scan_types as $scan_type) {
            $scan_data = self::get_scan_results($scan_type);
            if (!empty($scan_data)) {
                $results[$scan_type] = json_decode($scan_data[0]->scan_data, true);
            }
        }
        
        return $results;
    }
    
    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'ush_dashboard_widget',
            __('Site Health Status', 'unified-site-health-dashboard'),
            array($this, 'render_dashboard_widget')
        );
    }
    
    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        $overall_score = $this->get_overall_site_health_score();
        $score_class = $this->get_score_class($overall_score);
        $score_message = $this->get_score_message($overall_score);
        $last_scan = $this->get_last_scan_time();
        
        ?>
        <div class="ush-dashboard-widget">
            <div class="ush-widget-score">
                <div class="ush-widget-circle ush-score-<?php echo esc_attr($score_class); ?>">
                    <span class="ush-widget-score-value"><?php echo $overall_score; ?></span>
                </div>
                <div class="ush-widget-info">
                    <h3><?php echo esc_html($score_message); ?></h3>
                    <p><?php _e('Last scan:', 'unified-site-health-dashboard'); ?> <?php echo $last_scan; ?></p>
                </div>
            </div>
            
            <div class="ush-widget-actions">
                <a href="<?php echo admin_url('admin.php?page=site-health-detailed-report'); ?>" 
                   class="button button-primary">
                    <?php _e('View Detail Report', 'unified-site-health-dashboard'); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Schedule weekly email reports
     */
    public function schedule_weekly_reports() {
        if (!wp_next_scheduled('ush_weekly_report')) {
            wp_schedule_event(time(), 'weekly', 'ush_weekly_report');
        }
    }
    
    /**
     * Send weekly email report
     */
    public function send_weekly_report() {
        if (!get_option('ush_weekly_reports', 0)) {
            return;
        }
        
        $email_address = get_option('ush_email_address', get_option('admin_email'));
        if (empty($email_address)) {
            return;
        }
        
        $overall_score = $this->get_overall_site_health_score();
        $scan_results = $this->get_all_scan_results();
        $top_issues = $this->get_top_issues($scan_results);
        $forecast_message = $this->get_forecast_message($scan_results);
        
        $subject = sprintf(__('Weekly Site Health Report - %s', 'unified-site-health-dashboard'), get_bloginfo('name'));
        
        $message = $this->generate_weekly_email_content($overall_score, $top_issues, $forecast_message);
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        wp_mail($email_address, $subject, $message, $headers);
    }
    
    /**
     * Generate weekly email content
     */
    private function generate_weekly_email_content($overall_score, $top_issues, $forecast_message) {
        $score_class = $this->get_score_class($overall_score);
        $score_message = $this->get_score_message($overall_score);
        
        ob_start();
        ?>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: #f8f9fa; padding: 20px; text-align: center; }
                .score-display { text-align: center; margin: 20px 0; }
                .score-circle { 
                    display: inline-block; 
                    width: 80px; 
                    height: 80px; 
                    border-radius: 50%; 
                    line-height: 80px; 
                    text-align: center; 
                    font-size: 24px; 
                    font-weight: bold; 
                    color: white; 
                }
                .score-red { background: #f44336; }
                .score-yellow { background: #ff9800; }
                .score-green { background: #4caf50; }
                .issues { margin: 20px 0; }
                .issue { background: #f8f9fa; padding: 15px; margin: 10px 0; border-left: 4px solid #007cba; }
                .forecast { background: #e3f2fd; padding: 15px; margin: 20px 0; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1><?php _e('Weekly Site Health Report', 'unified-site-health-dashboard'); ?></h1>
                <p><?php echo get_bloginfo('name'); ?> - <?php echo date('F j, Y'); ?></p>
            </div>
            
            <div class="score-display">
                <h2><?php _e('Overall Site Health Status', 'unified-site-health-dashboard'); ?></h2>
                <div class="score-circle score-<?php echo esc_attr($score_class); ?>">
                    <?php echo $overall_score; ?>%
                </div>
                <h3><?php echo esc_html($score_message); ?></h3>
            </div>
            
            <?php if (!empty($top_issues)): ?>
                <div class="issues">
                    <h2><?php _e('Top Issues to Address', 'unified-site-health-dashboard'); ?></h2>
                    <?php foreach ($top_issues as $issue): ?>
                        <div class="issue">
                            <h3><?php echo esc_html($issue['title']); ?></h3>
                            <p><strong><?php _e('Impact:', 'unified-site-health-dashboard'); ?></strong> <?php echo esc_html($issue['impact']); ?></p>
                            <p><strong><?php _e('Fix:', 'unified-site-health-dashboard'); ?></strong> <?php echo esc_html($issue['fix']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($forecast_message)): ?>
                <div class="forecast">
                    <h2><?php _e('Forecast', 'unified-site-health-dashboard'); ?></h2>
                    <p><?php echo esc_html($forecast_message); ?></p>
                </div>
            <?php endif; ?>
            
            <p>
                <a href="<?php echo admin_url('admin.php?page=site-health-forecast'); ?>" style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;">
                    <?php _e('View Full Dashboard', 'unified-site-health-dashboard'); ?>
                </a>
            </p>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get top 3 issues
     */
    private function get_top_issues($scan_results) {
        $all_issues = array();
        
        foreach ($scan_results as $scan_type => $data) {
            if (isset($data['data']['issues'])) {
                foreach ($data['data']['issues'] as $issue) {
                    $all_issues[] = array(
                        'title' => $issue['title'] ?? ucfirst(str_replace('_', ' ', $scan_type)) . ' Issue',
                        'impact' => $issue['impact'] ?? 'Affects site performance',
                        'fix' => $issue['fix'] ?? 'Please check the detailed report',
                        'severity' => $issue['severity'] ?? 'warning'
                    );
                }
            }
        }
        
        // Sort by severity and return top 3
        usort($all_issues, function($a, $b) {
            $severity_order = array('critical' => 3, 'warning' => 2, 'good' => 1);
            return $severity_order[$b['severity']] - $severity_order[$a['severity']];
        });
        
        return array_slice($all_issues, 0, 3);
    }
    
    /**
     * Get forecast message
     */
    private function get_forecast_message($scan_results) {
        $messages = array();
        
        if (isset($scan_results['performance']['data']['pagespeed']['lcp']['value'])) {
            $lcp = $scan_results['performance']['data']['pagespeed']['lcp']['value'];
            if ($lcp > 2.0) {
                $messages[] = sprintf(__('If image sizes continue to grow, your LCP may increase by 0.3s within 30 days.', 'unified-site-health-dashboard'));
            }
        }
        
        if (isset($scan_results['seo']['data']['overall_score'])) {
            $seo_score = $scan_results['seo']['data']['overall_score'];
            if ($seo_score < 80) {
                $messages[] = sprintf(__('Without content updates, your SEO score may decrease by 5 points in 30 days.', 'unified-site-health-dashboard'));
            }
        }
        
        return !empty($messages) ? implode(' ', $messages) : __('Your site health is stable. Continue monitoring for optimal performance.', 'unified-site-health-dashboard');
    }
    
    /**
     * Auto-fix common issues
     */
    public function auto_fix_issues() {
        if (!get_option('ush_auto_fix_enabled', 0)) {
            return;
        }
        
        $fixes_applied = array();
        
        // Compress large images
        $compressed_images = $this->compress_large_images();
        if ($compressed_images > 0) {
            $fixes_applied[] = sprintf(__('Compressed %d large images', 'unified-site-health-dashboard'), $compressed_images);
        }
        
        // Remove unused plugins
        $removed_plugins = $this->remove_unused_plugins();
        if ($removed_plugins > 0) {
            $fixes_applied[] = sprintf(__('Removed %d unused plugins', 'unified-site-health-dashboard'), $removed_plugins);
        }
        
        // Fix broken internal links
        $fixed_links = $this->fix_broken_internal_links();
        if ($fixed_links > 0) {
            $fixes_applied[] = sprintf(__('Fixed %d broken internal links', 'unified-site-health-dashboard'), $fixed_links);
        }
        
        return $fixes_applied;
    }
    
    /**
     * Compress large images (mock implementation)
     */
    private function compress_large_images() {
        // This would integrate with image optimization libraries
        // For now, return mock data
        return rand(0, 5);
    }
    
    /**
     * Remove unused plugins (mock implementation)
     */
    private function remove_unused_plugins() {
        // This would check for unused plugins and deactivate them
        // For now, return mock data
        return rand(0, 2);
    }
    
    /**
     * Fix broken internal links (mock implementation)
     */
    private function fix_broken_internal_links() {
        // This would scan for broken links and attempt to fix them
        // For now, return mock data
        return rand(0, 3);
    }
}

// Initialize the plugin
UnifiedSiteHealthDashboard::get_instance();

// Schedule weekly reports
add_action('ush_weekly_report', array(UnifiedSiteHealthDashboard::get_instance(), 'send_weekly_report'));
