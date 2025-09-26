<?php
/**
 * Admin settings class for Unified Site Health Dashboard
 *
 * @package Unified_Site_Health_Dashboard
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin settings class
 */
class USH_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('ush_settings', 'ush_google_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
        
        register_setting('ush_settings', 'ush_scan_pages', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_scan_pages'),
            'default' => array()
        ));
        
        register_setting('ush_settings', 'ush_auto_scan', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false
        ));
        
        register_setting('ush_settings', 'ush_scan_interval', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'weekly'
        ));
    }
    
    /**
     * Sanitize scan pages setting
     *
     * @param array $value Scan pages value
     * @return array Sanitized value
     */
    public function sanitize_scan_pages($value) {
        if (!is_array($value)) {
            return array();
        }
        
        return array_map('intval', $value);
    }
    
    /**
     * Display settings page
     */
    public function display_settings() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        $api_key = get_option('ush_google_api_key', '');
        $scan_pages = get_option('ush_scan_pages', array());
        $auto_scan = get_option('ush_auto_scan', false);
        $scan_interval = get_option('ush_scan_interval', 'weekly');
        
        ?>
        <div class="wrap">
            <h1><?php _e('Site Health Settings', 'unified-site-health-dashboard'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('ush_settings_nonce', 'ush_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ush_google_api_key"><?php _e('Google PageSpeed Insights API Key', 'unified-site-health-dashboard'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="ush_google_api_key" 
                                   name="ush_google_api_key" 
                                   value="<?php echo esc_attr($api_key); ?>" 
                                   class="regular-text" 
                                   required />
                            <p class="description">
                                <?php _e('Get your API key from', 'unified-site-health-dashboard'); ?> 
                                <a href="https://developers.google.com/speed/docs/insights/v5/get-started" target="_blank">
                                    <?php _e('Google PageSpeed Insights API', 'unified-site-health-dashboard'); ?>
                                </a>
                                <br>
                                <strong><?php _e('Note:', 'unified-site-health-dashboard'); ?></strong> 
                                <?php _e('The API may take up to 60 seconds to respond. Timeout errors are normal and will be retried automatically.', 'unified-site-health-dashboard'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ush_auto_scan"><?php _e('Automatic Scanning', 'unified-site-health-dashboard'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="ush_auto_scan" 
                                       name="ush_auto_scan" 
                                       value="1" 
                                       <?php checked($auto_scan, 1); ?> />
                                <?php _e('Enable automatic scanning', 'unified-site-health-dashboard'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ush_scan_interval"><?php _e('Scan Interval', 'unified-site-health-dashboard'); ?></label>
                        </th>
                        <td>
                            <select id="ush_scan_interval" name="ush_scan_interval">
                                <option value="daily" <?php selected($scan_interval, 'daily'); ?>>
                                    <?php _e('Daily', 'unified-site-health-dashboard'); ?>
                                </option>
                                <option value="weekly" <?php selected($scan_interval, 'weekly'); ?>>
                                    <?php _e('Weekly', 'unified-site-health-dashboard'); ?>
                                </option>
                                <option value="monthly" <?php selected($scan_interval, 'monthly'); ?>>
                                    <?php _e('Monthly', 'unified-site-health-dashboard'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Pages to Scan', 'unified-site-health-dashboard'); ?></h2>
                <p><?php _e('Select which pages to include in the scan:', 'unified-site-health-dashboard'); ?></p>
                
                <div class="ush-pages-selection">
                    <?php
                    $pages = get_posts(array(
                        'post_type' => array('page', 'post'),
                        'post_status' => 'publish',
                        'numberposts' => -1,
                        'orderby' => 'title',
                        'order' => 'ASC'
                    ));
                    
                    foreach ($pages as $page) {
                        $checked = in_array($page->ID, $scan_pages) ? 'checked' : '';
                        ?>
                        <label class="ush-page-checkbox">
                            <input type="checkbox" 
                                   name="ush_scan_pages[]" 
                                   value="<?php echo $page->ID; ?>" 
                                   <?php echo $checked; ?> />
                            <?php echo esc_html($page->post_title); ?>
                            <span class="ush-page-type">(<?php echo $page->post_type; ?>)</span>
                        </label>
                        <?php
                    }
                    ?>
                </div>
                
                <?php submit_button(); ?>
            </form>
            
            <div class="ush-settings-info">
                <h3><?php _e('About This Plugin', 'unified-site-health-dashboard'); ?></h3>
                <p><?php _e('This plugin scans your website using Google PageSpeed Insights API to provide comprehensive site health analysis including performance, SEO, accessibility, and security metrics.', 'unified-site-health-dashboard'); ?></p>
                
                <h4><?php _e('Features:', 'unified-site-health-dashboard'); ?></h4>
                <ul>
                    <li><?php _e('Real-time PageSpeed Insights integration', 'unified-site-health-dashboard'); ?></li>
                    <li><?php _e('Mobile and desktop performance analysis', 'unified-site-health-dashboard'); ?></li>
                    <li><?php _e('Detailed audit results with actionable insights', 'unified-site-health-dashboard'); ?></li>
                    <li><?php _e('Historical data tracking and trends', 'unified-site-health-dashboard'); ?></li>
                    <li><?php _e('Automatic scanning capabilities', 'unified-site-health-dashboard'); ?></li>
                </ul>
            </div>
        </div>
        
        <style>
        .ush-pages-selection {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            background: #f9f9f9;
        }
        
        .ush-page-checkbox {
            display: block;
            margin-bottom: 8px;
            padding: 5px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 3px;
        }
        
        .ush-page-checkbox:hover {
            background: #f0f0f0;
        }
        
        .ush-page-type {
            color: #666;
            font-size: 12px;
        }
        
        .ush-settings-info {
            margin-top: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-left: 4px solid #0073aa;
        }
        
        .ush-settings-info h3 {
            margin-top: 0;
        }
        
        .ush-settings-info ul {
            margin-left: 20px;
        }
        </style>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        if (!wp_verify_nonce($_POST['ush_settings_nonce'], 'ush_settings_nonce')) {
            wp_die(__('Security check failed', 'unified-site-health-dashboard'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'unified-site-health-dashboard'));
        }
        
        // Save API key
        if (isset($_POST['ush_google_api_key'])) {
            update_option('ush_google_api_key', sanitize_text_field($_POST['ush_google_api_key']));
        }
        
        // Save auto scan setting
        $auto_scan = isset($_POST['ush_auto_scan']) ? 1 : 0;
        update_option('ush_auto_scan', $auto_scan);
        
        // Save scan interval
        if (isset($_POST['ush_scan_interval'])) {
            $interval = sanitize_text_field($_POST['ush_scan_interval']);
            if (in_array($interval, array('daily', 'weekly', 'monthly'))) {
                update_option('ush_scan_interval', $interval);
            }
        }
        
        // Save scan pages
        if (isset($_POST['ush_scan_pages']) && is_array($_POST['ush_scan_pages'])) {
            $pages = array_map('intval', $_POST['ush_scan_pages']);
            update_option('ush_scan_pages', $pages);
        } else {
            update_option('ush_scan_pages', array());
        }
        
        // Schedule or unschedule automatic scans
        $this->schedule_automatic_scans($auto_scan, get_option('ush_scan_interval', 'weekly'));
        
        add_settings_error('ush_settings', 'settings_updated', __('Settings saved successfully', 'unified-site-health-dashboard'), 'updated');
    }
    
    /**
     * Schedule automatic scans
     *
     * @param bool $enabled Whether auto scan is enabled
     * @param string $interval Scan interval
     */
    private function schedule_automatic_scans($enabled, $interval) {
        // Clear existing scheduled events
        wp_clear_scheduled_hook('ush_automatic_scan');
        
        if ($enabled) {
            $intervals = array(
                'daily' => DAY_IN_SECONDS,
                'weekly' => WEEK_IN_SECONDS,
                'monthly' => MONTH_IN_SECONDS
            );
            
            if (isset($intervals[$interval])) {
                wp_schedule_event(time(), $interval, 'ush_automatic_scan');
            }
        }
    }
}
