<?php
/**
 * Database handler for Unified Site Health Dashboard
 *
 * @package Unified_Site_Health_Dashboard
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database handler class
 */
class USH_Database {
    
    /**
     * Database table name
     */
    private $table_name;
    
    /**
     * WordPress database instance
     */
    private $wpdb;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'ush_scan_results';
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            page_id bigint(20) NOT NULL,
            page_url text NOT NULL,
            scan_type enum('mobile','desktop') NOT NULL,
            audit_category varchar(50) NOT NULL,
            audit_name varchar(100) NOT NULL,
            audit_score varchar(20) DEFAULT NULL,
            audit_description text DEFAULT NULL,
            audit_element text DEFAULT NULL,
            severity enum('critical','warning','info','good') NOT NULL DEFAULT 'info',
            scan_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY page_id (page_id),
            KEY scan_type (scan_type),
            KEY audit_category (audit_category),
            KEY severity (severity),
            KEY scan_date (scan_date)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Store database version
        update_option('ush_db_version', '1.0.0');
    }
    
    /**
     * Store scan result
     *
     * @param array $data Scan result data
     * @return int|false Insert ID or false on failure
     */
    public function store_scan_result($data) {
        $result = $this->wpdb->insert(
            $this->table_name,
            array(
                'page_id' => $data['page_id'],
                'page_url' => $data['page_url'],
                'scan_type' => $data['scan_type'],
                'audit_category' => $data['audit_category'],
                'audit_name' => $data['audit_name'],
                'audit_score' => $data['audit_score'],
                'audit_description' => $data['audit_description'],
                'audit_element' => $data['audit_element'],
                'severity' => $data['severity'],
                'scan_date' => isset($data['scan_date']) && $data['scan_date'] ? $data['scan_date'] : current_time('mysql')
            ),
            array(
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s'
            )
        );
        
        if ($result === false) {
            return false;
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Get audit data by category
     *
     * @param string $category Audit category
     * @param string $scan_type Scan type (mobile/desktop)
     * @return array Audit data
     */
    public function get_audit_data_by_category($category, $scan_type = '') {
        // If scan_type provided, get the latest scan_date for that category+scan_type
        if (!empty($scan_type)) {
            $max_date = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT MAX(scan_date) FROM {$this->table_name} WHERE audit_category = %s AND scan_type = %s",
                $category,
                $scan_type
            ));

            if ($max_date) {
                $sql = $this->wpdb->prepare(
                    "SELECT * FROM {$this->table_name} WHERE audit_category = %s AND scan_type = %s AND scan_date = %s ORDER BY severity DESC",
                    $category,
                    $scan_type,
                    $max_date
                );
                return $this->wpdb->get_results($sql, ARRAY_A);
            }
            // fallback to any matching rows if no max_date
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE audit_category = %s AND scan_type = %s ORDER BY scan_date DESC, severity DESC",
                $category,
                $scan_type
            );
            return $this->wpdb->get_results($sql, ARRAY_A);
        }

        // If no scan_type provided, get the latest scan_date for the category across types
        $max_date = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MAX(scan_date) FROM {$this->table_name} WHERE audit_category = %s",
            $category
        ));

        if ($max_date) {
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE audit_category = %s AND scan_date = %s ORDER BY severity DESC",
                $category,
                $max_date
            );
            return $this->wpdb->get_results($sql, ARRAY_A);
        }

        // fallback
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE audit_category = %s ORDER BY scan_date DESC, severity DESC",
            $category
        );
        return $this->wpdb->get_results($sql, ARRAY_A);
    }
    
    /**
     * Get overall scores by category
     *
     * @return array Category scores
     */
    public function get_category_scores() {
        // Use the latest scan_date per audit_category + scan_type by joining with a subquery
        $sql = "SELECT t.audit_category, t.scan_type,
                    AVG(CASE WHEN t.audit_score REGEXP '^[0-9]+\.?[0-9]*$' THEN CAST(t.audit_score AS DECIMAL(5,2)) ELSE NULL END) as avg_score,
                    COUNT(*) as total_audits,
                    SUM(CASE WHEN t.severity = 'critical' THEN 1 ELSE 0 END) as critical_count,
                    SUM(CASE WHEN t.severity = 'warning' THEN 1 ELSE 0 END) as warning_count,
                    SUM(CASE WHEN t.severity = 'info' THEN 1 ELSE 0 END) as info_count,
                    SUM(CASE WHEN t.severity = 'good' THEN 1 ELSE 0 END) as good_count
                FROM {$this->table_name} t
                INNER JOIN (
                    SELECT audit_category, scan_type, MAX(scan_date) as max_date
                    FROM {$this->table_name}
                    GROUP BY audit_category, scan_type
                ) m ON t.audit_category = m.audit_category AND t.scan_type = m.scan_type AND t.scan_date = m.max_date
                GROUP BY t.audit_category, t.scan_type
                ORDER BY t.audit_category, t.scan_type";

        return $this->wpdb->get_results($sql, ARRAY_A);
    }
    
    /**
     * Get average score for a specific category and scan type
     *
     * @param string $category
     * @param string $scan_type mobile|desktop
     * @return float|null
     */
    public function get_category_avg_score($category, $scan_type) {
        // Get latest scan_date for this category+scan_type
        $max_date = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MAX(scan_date) FROM {$this->table_name} WHERE audit_category = %s AND scan_type = %s",
            $category,
            $scan_type
        ));

        if ($max_date) {
            $sql = $this->wpdb->prepare(
                "SELECT AVG(CASE WHEN audit_score REGEXP '^[0-9]+\\.?[0-9]*$' THEN CAST(audit_score AS DECIMAL(5,2)) ELSE NULL END) as avg_score
                 FROM {$this->table_name}
                 WHERE audit_category = %s AND scan_type = %s AND scan_date = %s",
                $category,
                $scan_type,
                $max_date
            );
        } else {
            $sql = $this->wpdb->prepare(
                "SELECT AVG(CASE WHEN audit_score REGEXP '^[0-9]+\\.?[0-9]*$' THEN CAST(audit_score AS DECIMAL(5,2)) ELSE NULL END) as avg_score
                 FROM {$this->table_name}
                 WHERE audit_category = %s AND scan_type = %s",
                $category,
                $scan_type
            );
        }

        $row = $this->wpdb->get_row($sql, ARRAY_A);
        if (!$row || $row['avg_score'] === null) {
            return null;
        }
        return (float) $row['avg_score'];
    }
    
    /**
     * Get latest scan results
     *
     * @param int $limit Number of results to return
     * @return array Latest scan results
     */
    public function get_latest_scan_results($limit = 10) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} ORDER BY scan_date DESC LIMIT %d",
            $limit
        );
        
        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get latest scan_date for a category and optional scan_type
     *
     * @param string $category
     * @param string $scan_type Optional 'mobile'|'desktop'
     * @return string|null Latest scan_date in mysql format or null
     */
    public function get_latest_scan_date($category, $scan_type = '') {
        if (!empty($scan_type)) {
            $sql = $this->wpdb->prepare(
                "SELECT MAX(scan_date) FROM {$this->table_name} WHERE audit_category = %s AND scan_type = %s",
                $category,
                $scan_type
            );
        } else {
            $sql = $this->wpdb->prepare(
                "SELECT MAX(scan_date) FROM {$this->table_name} WHERE audit_category = %s",
                $category
            );
        }

        $date = $this->wpdb->get_var($sql);
        return $date ? $date : null;
    }
    
    /**
     * Get scan statistics
     *
     * @return array Scan statistics
     */
    public function get_scan_statistics() {
        $sql = "SELECT 
                    COUNT(DISTINCT page_id) as total_pages_scanned,
                    COUNT(*) as total_audits,
                    MAX(scan_date) as last_scan_date,
                    MIN(scan_date) as first_scan_date
                FROM {$this->table_name}";
        
        return $this->wpdb->get_row($sql, ARRAY_A);
    }
    
    /**
     * Clear old scan results
     *
     * @param int $days Number of days to keep
     * @return int Number of deleted records
     */
    public function clear_old_results($days = 30) {
        $sql = $this->wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE scan_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        );
        
        return $this->wpdb->query($sql);
    }
    
    /**
     * Get pages that need scanning
     *
     * @return array Pages to scan
     */
    public function get_pages_to_scan() {
        // Get all published pages and posts
        $pages = get_posts(array(
            'post_type' => array('page', 'post'),
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids'
        ));
        
        $pages_to_scan = array();
        
        foreach ($pages as $page_id) {
            $page_url = get_permalink($page_id);
            if ($page_url) {
                $pages_to_scan[] = array(
                    'id' => $page_id,
                    'url' => $page_url,
                    'title' => get_the_title($page_id)
                );
            }
        }
        
        return $pages_to_scan;
    }
    
    /**
     * Check if table exists
     *
     * @return bool True if table exists
     */
    public function table_exists() {
        $sql = $this->wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $this->table_name
        );
        
        return $this->wpdb->get_var($sql) === $this->table_name;
    }
    
    /**
     * Get table name
     *
     * @return string Table name
     */
    public function get_table_name() {
        return $this->table_name;
    }
}
