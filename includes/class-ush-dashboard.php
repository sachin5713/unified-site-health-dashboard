<?php
/**
 * Dashboard display class for Unified Site Health Dashboard
 *
 * @package Unified_Site_Health_Dashboard
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dashboard display class
 */
class USH_Dashboard {
    
    /**
     * Database handler
     */
    private $database;
    
    /**
     * Scanner handler
     */
    private $scanner;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database = new USH_Database();
        $this->scanner = new USH_Scanner();
        
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Enqueue scripts and styles
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_ush-dashboard') {
            return;
        }
        
        wp_enqueue_style(
            'ush-dashboard',
            USH_PLUGIN_URL . 'assets/css/dashboard.css',
            array(),
            USH_PLUGIN_VERSION
        );
        
        wp_enqueue_script(
            'ush-dashboard',
            USH_PLUGIN_URL . 'assets/js/dashboard.js',
            array('jquery'),
            USH_PLUGIN_VERSION,
            true
        );
        
        wp_localize_script('ush-dashboard', 'ush_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ush_ajax_nonce'),
            'auto_scan' => (bool) get_option('ush_auto_scan', false),
            'date_format' => get_option('date_format', 'F j, Y'),
            'strings' => array(
                'scan_started' => __('Scan started successfully', 'unified-site-health-dashboard'),
                'scan_error' => __('Error starting scan', 'unified-site-health-dashboard'),
                'loading' => __('Loading...', 'unified-site-health-dashboard'),
                'no_data' => __('No data available', 'unified-site-health-dashboard'),
                'scanning' => __('Scanning...', 'unified-site-health-dashboard')
            )
        ));
    }
    
    /**
     * Display dashboard
     */
    public function display_dashboard() {
        $scan_progress = $this->scanner->get_scan_progress();
        $category_scores = $this->database->get_category_scores();
        $scan_stats = $this->database->get_scan_statistics();
        
        ?>
        <div class="wrap ush-dashboard">
            <h1><?php _e('Site Health Dashboard', 'unified-site-health-dashboard'); ?></h1>
            
            <?php $this->display_scan_controls($scan_progress); ?>
            <?php $this->display_scan_progress($scan_progress); ?>
            <?php $this->display_overview_stats($scan_stats); ?>
            <?php $this->display_category_tiles($category_scores); ?>
            <?php $this->display_modals(); ?>
        </div>
        <?php
    }
    
    /**
     * Display scan controls
     *
     * @param array $scan_progress Current scan progress
     */
    private function display_scan_controls($scan_progress) {
        $api_key = get_option('ush_google_api_key');
        $is_scanning = $scan_progress['status'] === 'running';
        
        ?>
        <div class="ush-scan-controls">
            <div class="ush-scan-header">
                <h2><?php _e('Site Health Scan', 'unified-site-health-dashboard'); ?></h2>
                <?php if (empty($api_key)): ?>
                    <div class="notice notice-warning">
                        <p><?php _e('Please configure your Google PageSpeed Insights API key in', 'unified-site-health-dashboard'); ?> 
                           <a href="<?php echo admin_url('admin.php?page=ush-settings'); ?>"><?php _e('Settings', 'unified-site-health-dashboard'); ?></a></p>
                    </div>
                <?php else: ?>
                    <button type="button" id="ush-start-scan" class="button button-primary" <?php disabled($is_scanning); ?>>
                        <?php echo $is_scanning ? __('Scanning...', 'unified-site-health-dashboard') : __('Start New Scan', 'unified-site-health-dashboard'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display scan progress
     *
     * @param array $scan_progress Current scan progress
     */
    private function display_scan_progress($scan_progress) {
        // Always render the container; JS will show/hide and update it as needed.
        $percentage = 0;
        if (!empty($scan_progress['total_pages']) && $scan_progress['total_pages'] > 0) {
            $percentage = round(($scan_progress['scanned_pages'] / $scan_progress['total_pages']) * 100);
        }
        ?>
        <div class="ush-scan-progress" id="ush-scan-progress" style="display:<?php echo ($scan_progress['status'] === 'running') ? 'block' : 'none'; ?>;">
            <div class="ush-progress-header">
                <h3 id="ush-progress-title">
                    <?php echo $scan_progress['status'] === 'completed' 
                        ? esc_html__('Scan Completed', 'unified-site-health-dashboard') 
                        : esc_html__('Scan Progress', 'unified-site-health-dashboard'); ?>
                </h3>
                <span class="ush-progress-percentage"><?php echo $percentage; ?>%</span>
            </div>
            <div class="ush-progress-bar">
                <div class="ush-progress-fill" style="width: <?php echo $percentage; ?>%"></div>
            </div>
            <div class="ush-progress-details">
                <p class="ush-current-page" id="ush-current-page">
                    <?php printf(__('Scanning: %s', 'unified-site-health-dashboard'), 
                        esc_html($scan_progress['current_page_title'])); ?>
                </p>
                <?php if (!empty($scan_progress['current_url'])): ?>
                    <p class="ush-current-url">
                        <small id="ush-current-url-text"><?php echo esc_html($scan_progress['current_url']); ?></small>
                    </p>
                <?php endif; ?>
            </div>
            <ul class="ush-category-state-list" style="margin-top:10px;list-style: none;padding:0;">
                <li data-cat="Performance"><?php _e('Performance', 'unified-site-health-dashboard'); ?></li>
                <li data-cat="SEO"><?php _e('SEO', 'unified-site-health-dashboard'); ?></li>
                <li data-cat="Accessibility"><?php _e('Accessibility', 'unified-site-health-dashboard'); ?></li>
                <li data-cat="Security"><?php _e('Security', 'unified-site-health-dashboard'); ?></li>
                <li data-cat="Host Health"><?php _e('Host Health', 'unified-site-health-dashboard'); ?></li>
            </ul>
            <div class="ush-scan-errors" id="ush-scan-errors" style="display:none;">
                <h4><?php _e('Errors:', 'unified-site-health-dashboard'); ?></h4>
                <ul id="ush-scan-errors-list"></ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display overview statistics
     *
     * @param array $scan_stats Scan statistics
     */
    private function display_overview_stats($scan_stats) {
        ?>
        <div class="ush-overview-stats">
            <h2><?php _e('Overview', 'unified-site-health-dashboard'); ?></h2>
            <div class="ush-stats-grid">
                <div class="ush-stat-item">
                    <div class="ush-stat-number"><?php echo intval($scan_stats['total_pages_scanned']); ?></div>
                    <div class="ush-stat-label"><?php _e('Pages Scanned', 'unified-site-health-dashboard'); ?></div>
                </div>
                <div class="ush-stat-item">
                    <div class="ush-stat-number"><?php echo intval($scan_stats['total_audits']); ?></div>
                    <div class="ush-stat-label"><?php _e('Total Audits', 'unified-site-health-dashboard'); ?></div>
                </div>
                <div class="ush-stat-item">
                    <div class="ush-stat-number">
                        <?php echo $scan_stats['last_scan_date'] ? 
                            date('M j, Y', strtotime($scan_stats['last_scan_date'])) : 
                            __('Never', 'unified-site-health-dashboard'); ?>
                    </div>
                    <div class="ush-stat-label"><?php _e('Last Scan', 'unified-site-health-dashboard'); ?></div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display category tiles
     *
     * @param array $category_scores Category scores
     */
    private function display_category_tiles($category_scores) {
        $categories = array(
            'Performance' => array('icon' => 'dashicons-performance', 'color' => '#ff6b6b'),
            'SEO' => array('icon' => 'dashicons-search', 'color' => '#4ecdc4'),
            'Accessibility' => array('icon' => 'dashicons-universal-access', 'color' => '#45b7d1'),
            'Security' => array('icon' => 'dashicons-shield', 'color' => '#f9ca24'),
            'Content' => array('icon' => 'dashicons-edit', 'color' => '#6c5ce7'),
            'Host Health' => array('icon' => 'dashicons-admin-site', 'color' => '#a29bfe')
        );
        
        ?>
        <div class="ush-category-tiles">
            <h2><?php _e('Site Health Categories', 'unified-site-health-dashboard'); ?></h2>
            <div class="ush-tiles-grid">
                <?php foreach ($categories as $category => $config): ?>
                    <?php
                    // Host Health is a server-level category and does not have mobile/desktop variants
                    if ($category === 'Host Health') {
                        $host_score = $this->get_category_score($category_scores, $category, 'desktop');
                        ?>
                        <div class="ush-tile ush-tile-modal-trigger" 
                             data-category="<?php echo esc_attr($category); ?>"
                             style="border-left-color: <?php echo $config['color']; ?>">
                            <div class="ush-tile-header">
                                <span class="dashicons <?php echo $config['icon']; ?>"></span>
                                <h3><?php echo $category; ?></h3>
                            </div>
                            <div class="ush-tile-score">
                                <div class="ush-score-number"><?php echo $host_score; ?>%</div>
                                <div class="ush-score-label"><?php _e('Score', 'unified-site-health-dashboard'); ?></div>
                            </div>
                            <div class="ush-tile-details">
                                <div class="ush-detail-item">
                                    <span><?php _e('Details:', 'unified-site-health-dashboard'); ?></span>
                                    <span><?php echo $host_score; ?>%</span>
                                </div>
                            </div>
                        </div>
                        <?php
                    } else {
                        $mobile_score = $this->get_category_score($category_scores, $category, 'mobile');
                        $desktop_score = $this->get_category_score($category_scores, $category, 'desktop');
                        $overall_score = $this->calculate_overall_score($mobile_score, $desktop_score);
                        ?>
                        <div class="ush-tile ush-tile-modal-trigger" 
                             data-category="<?php echo esc_attr($category); ?>"
                             style="border-left-color: <?php echo $config['color']; ?>">
                            <div class="ush-tile-header">
                                <span class="dashicons <?php echo $config['icon']; ?>"></span>
                                <h3><?php echo $category; ?></h3>
                            </div>
                            <div class="ush-tile-score">
                                <div class="ush-score-number"><?php echo $overall_score; ?>%</div>
                                <div class="ush-score-label"><?php _e('Overall Score', 'unified-site-health-dashboard'); ?></div>
                            </div>
                            <div class="ush-tile-details">
                                <div class="ush-detail-item">
                                    <span><?php _e('Mobile:', 'unified-site-health-dashboard'); ?></span>
                                    <span><?php echo $mobile_score; ?>%</span>
                                </div>
                                <div class="ush-detail-item">
                                    <span><?php _e('Desktop:', 'unified-site-health-dashboard'); ?></span>
                                    <span><?php echo $desktop_score; ?>%</span>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get category score from scores array
     *
     * @param array $scores Category scores
     * @param string $category Category name
     * @param string $scan_type Scan type
     * @return int Score percentage
     */
    private function get_category_score($scores, $category, $scan_type) {
        foreach ($scores as $score) {
            if ($score['audit_category'] === $category && $score['scan_type'] === $scan_type) {
                return round($score['avg_score'] * 100);
            }
        }
        // Return null if score not present so caller can distinguish missing vs zero
        return null;
    }
    
    /**
     * Calculate overall score from mobile and desktop
     *
     * @param int $mobile_score Mobile score
     * @param int $desktop_score Desktop score
     * @return int Overall score
     */
    private function calculate_overall_score($mobile_score, $desktop_score) {
        $values = array();
        if (is_numeric($mobile_score)) {
            $values[] = $mobile_score;
        }
        if (is_numeric($desktop_score)) {
            $values[] = $desktop_score;
        }

        if (empty($values)) {
            return 0;
        }

        return round(array_sum($values) / count($values));
    }
    
    /**
     * Display modals
     */
    private function display_modals() {
        ?>
        <div id="ush-modal" class="ush-modal" style="display: none;">
            <div class="ush-modal-content">
                <div class="ush-modal-header">
                    <h2 id="ush-modal-title"><?php _e('Audit Details', 'unified-site-health-dashboard'); ?></h2>
                    <div class="ush-modal-scores">
                        <span class="ush-score-badge" id="ush-modal-mobile-score">—</span>
                        <span class="ush-score-badge" id="ush-modal-desktop-score">—</span>
                    </div>
                    <button type="button" class="ush-modal-close">&times;</button>
                </div>
                <div class="ush-modal-body">
                    <div class="ush-modal-tabs">
                        <button type="button" class="ush-tab-button active" data-tab="mobile">
                            <?php _e('Mobile', 'unified-site-health-dashboard'); ?>
                        </button>
                        <button type="button" class="ush-tab-button" data-tab="desktop">
                            <?php _e('Desktop', 'unified-site-health-dashboard'); ?>
                        </button>
                    </div>
                    <div class="ush-modal-tab-content">
                        <div id="ush-tab-mobile" class="ush-tab-pane active">
                            <div class="ush-audit-table-container">
                                <table class="ush-audit-table">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Audit', 'unified-site-health-dashboard'); ?></th>
                                            <th><?php _e('Description', 'unified-site-health-dashboard'); ?></th>
                                            <th><?php _e('Element', 'unified-site-health-dashboard'); ?></th>
                                            <th><?php _e('Severity', 'unified-site-health-dashboard'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="ush-mobile-audits">
                                        <tr>
                                            <td colspan="4" class="ush-loading">
                                                <?php _e('Loading...', 'unified-site-health-dashboard'); ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div id="ush-tab-desktop" class="ush-tab-pane">
                            <div class="ush-audit-table-container">
                                <table class="ush-audit-table">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Audit', 'unified-site-health-dashboard'); ?></th>
                                            <th><?php _e('Description', 'unified-site-health-dashboard'); ?></th>
                                            <th><?php _e('Element', 'unified-site-health-dashboard'); ?></th>
                                            <th><?php _e('Severity', 'unified-site-health-dashboard'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="ush-desktop-audits">
                                        <tr>
                                            <td colspan="4" class="ush-loading">
                                                <?php _e('Loading...', 'unified-site-health-dashboard'); ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
