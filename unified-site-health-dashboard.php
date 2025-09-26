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

// Include required files
require_once USH_PLUGIN_DIR . 'includes/class-ush-database.php';
require_once USH_PLUGIN_DIR . 'includes/class-ush-scanner.php';
require_once USH_PLUGIN_DIR . 'includes/class-ush-dashboard.php';
require_once USH_PLUGIN_DIR . 'includes/class-ush-admin.php';

/**
 * Main plugin class
 */
class Unified_Site_Health_Dashboard {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Database handler
     */
    public $database;
    
    /**
     * Scanner handler
     */
    public $scanner;
    
    /**
     * Dashboard handler
     */
    public $dashboard;
    
    /**
     * Admin handler
     */
    public $admin;
    
    /**
     * Get plugin instance
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
        $this->init_hooks();
        $this->init_components();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(USH_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(USH_PLUGIN_FILE, array($this, 'deactivate'));
        
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('wp_ajax_ush_start_scan', array($this, 'ajax_start_scan'));
        add_action('wp_ajax_ush_get_scan_progress', array($this, 'ajax_get_scan_progress'));
        add_action('wp_ajax_ush_get_audit_data', array($this, 'ajax_get_audit_data'));
        add_action('wp_ajax_ush_get_category_scores', array($this, 'ajax_get_category_scores'));
        add_action('ush_scan_batch', array($this, 'handle_scan_batch'), 10, 2);
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        $this->database = new USH_Database();
        $this->scanner = new USH_Scanner();
        $this->dashboard = new USH_Dashboard();
        $this->admin = new USH_Admin();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        $this->database->create_tables();
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('unified-site-health-dashboard', false, dirname(plugin_basename(USH_PLUGIN_FILE)) . '/languages');
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        // Register settings
        register_setting('ush_settings', 'ush_google_api_key');
        register_setting('ush_settings', 'ush_scan_pages');
    }
    
    /**
     * Add admin menu
     */
    public function admin_menu() {
        add_menu_page(
            __('Site Health Dashboard', 'unified-site-health-dashboard'),
            __('Site Health', 'unified-site-health-dashboard'),
            'manage_options',
            'ush-dashboard',
            array($this->dashboard, 'display_dashboard'),
            'dashicons-chart-line',
            30
        );
        
        add_submenu_page(
            'ush-dashboard',
            __('Settings', 'unified-site-health-dashboard'),
            __('Settings', 'unified-site-health-dashboard'),
            'manage_options',
            'ush-settings',
            array($this->admin, 'display_settings')
        );
    }
    
    /**
     * AJAX handler for starting scan
     */
    public function ajax_start_scan() {
        check_ajax_referer('ush_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'unified-site-health-dashboard'));
        }
        
        $this->scanner->start_scan();
        wp_send_json_success(array('message' => __('Scan started successfully', 'unified-site-health-dashboard')));
    }
    
    /**
     * AJAX handler for getting scan progress
     */
    public function ajax_get_scan_progress() {
        check_ajax_referer('ush_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'unified-site-health-dashboard'));
        }
        
        $progress = $this->scanner->get_scan_progress();
        wp_send_json_success($progress);
    }
    
    /**
     * AJAX handler for getting audit data
     */
    public function ajax_get_audit_data() {
        check_ajax_referer('ush_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'unified-site-health-dashboard'));
        }
        
        $category = sanitize_text_field($_POST['category']);
        $scan_type = sanitize_text_field($_POST['scan_type']);
        
        $audits = $this->database->get_audit_data_by_category($category, $scan_type);
        $avg = $this->database->get_category_avg_score($category, $scan_type);
        $scan_date = $this->database->get_latest_scan_date($category, $scan_type);

        wp_send_json_success(array(
            'avg_score' => $avg,
            'audits' => $audits,
            'scan_date' => $scan_date
        ));
    }

    /**
     * AJAX handler for category scores
     */
    public function ajax_get_category_scores() {
        check_ajax_referer('ush_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'unified-site-health-dashboard'));
        }
        
        $scores = $this->database->get_category_scores();
        wp_send_json_success($scores);
    }
    
    /**
     * Handle scan batch
     *
     * @param array $pages Pages to scan
     * @param int $batch_start Batch start index
     */
    public function handle_scan_batch($pages, $batch_start) {
        $this->scanner->schedule_scan_batch($pages, $batch_start);
    }
}

// Initialize the plugin
function ush_init() {
    return Unified_Site_Health_Dashboard::get_instance();
}

// Start the plugin
ush_init();