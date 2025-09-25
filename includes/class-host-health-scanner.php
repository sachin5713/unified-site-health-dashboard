<?php
/**
 * Host Health Scanner Class
 * 
 * Handles host health scanning including PHP version, SSL, disk space
 */

if (!defined('ABSPATH')) {
    exit;
}

class USH_Host_Health_Scanner {
    
    /**
     * Run host health scan
     */
    public function scan() {
        $results = array(
            'scan_type' => 'host_health',
            'scan_date' => current_time('mysql'),
            'status' => 'success',
            'data' => array()
        );
        
        // Check PHP version
        $results['data']['php_version'] = $this->check_php_version();
        
        // Check SSL certificate
        $results['data']['ssl_certificate'] = $this->check_ssl_certificate();
        
        // Check disk space
        $results['data']['disk_space'] = $this->check_disk_space();
        
        // Check server response time
        $results['data']['server_response'] = $this->check_server_response();
        
        // Check database health
        $results['data']['database_health'] = $this->check_database_health();
        
        // Check memory usage
        $results['data']['memory_usage'] = $this->check_memory_usage();
        
        // Calculate overall host health score
        $results['data']['overall_score'] = $this->calculate_overall_score($results['data']);
        
        return $results;
    }
    
    /**
     * Check PHP version
     */
    private function check_php_version() {
        $current_version = PHP_VERSION;
        $recommended_version = '8.1';
        $is_outdated = version_compare($current_version, $recommended_version, '<');
        
        $score = 100;
        if ($is_outdated) {
            $score = version_compare($current_version, '7.4', '<') ? 20 : 60;
        }
        
        $issues = array();
        if ($is_outdated) {
            $issues[] = array(
                'type' => 'warning',
                'message' => sprintf(__('PHP version %s is outdated. Recommended: %s', 'unified-site-health-dashboard'), $current_version, $recommended_version),
                'current' => $current_version,
                'recommended' => $recommended_version
            );
        }
        
        return array(
            'score' => $score,
            'issues' => $issues,
            'current_version' => $current_version,
            'recommended_version' => $recommended_version,
            'is_outdated' => $is_outdated
        );
    }
    
    /**
     * Check SSL certificate
     */
    private function check_ssl_certificate() {
        $is_ssl = is_ssl();
        $score = $is_ssl ? 100 : 0;
        
        $issues = array();
        if (!$is_ssl) {
            $issues[] = array(
                'type' => 'error',
                'message' => __('SSL certificate not detected', 'unified-site-health-dashboard')
            );
        } else {
            // Mock SSL expiry check
            $days_until_expiry = rand(30, 365);
            if ($days_until_expiry < 30) {
                $issues[] = array(
                    'type' => 'warning',
                    'message' => sprintf(__('SSL certificate expires in %d days', 'unified-site-health-dashboard'), $days_until_expiry),
                    'days_until_expiry' => $days_until_expiry
                );
                $score = 70;
            }
        }
        
        return array(
            'score' => $score,
            'issues' => $issues,
            'is_ssl' => $is_ssl,
            'days_until_expiry' => $is_ssl ? rand(30, 365) : null
        );
    }
    
    /**
     * Check disk space
     */
    private function check_disk_space() {
        // Mock disk space data
        $total_space = rand(50000, 100000); // MB
        $used_space = rand(20000, 80000); // MB
        $free_space = $total_space - $used_space;
        $usage_percentage = ($used_space / $total_space) * 100;
        
        $score = 100;
        $issues = array();
        
        if ($usage_percentage > 90) {
            $score = 20;
            $issues[] = array(
                'type' => 'error',
                'message' => sprintf(__('Disk space is %d%% full. Critical!', 'unified-site-health-dashboard'), round($usage_percentage)),
                'usage_percentage' => $usage_percentage
            );
        } elseif ($usage_percentage > 80) {
            $score = 60;
            $issues[] = array(
                'type' => 'warning',
                'message' => sprintf(__('Disk space is %d%% full. Consider cleanup.', 'unified-site-health-dashboard'), round($usage_percentage)),
                'usage_percentage' => $usage_percentage
            );
        }
        
        return array(
            'score' => $score,
            'issues' => $issues,
            'total_space' => $total_space,
            'used_space' => $used_space,
            'free_space' => $free_space,
            'usage_percentage' => $usage_percentage
        );
    }
    
    /**
     * Check server response time
     */
    private function check_server_response() {
        // Mock response time data
        $response_time = rand(100, 2000); // milliseconds
        $score = 100;
        
        if ($response_time > 1000) {
            $score = 30;
        } elseif ($response_time > 500) {
            $score = 70;
        }
        
        $issues = array();
        if ($response_time > 1000) {
            $issues[] = array(
                'type' => 'error',
                'message' => sprintf(__('Server response time is %dms (very slow)', 'unified-site-health-dashboard'), $response_time),
                'response_time' => $response_time
            );
        } elseif ($response_time > 500) {
            $issues[] = array(
                'type' => 'warning',
                'message' => sprintf(__('Server response time is %dms (slow)', 'unified-site-health-dashboard'), $response_time),
                'response_time' => $response_time
            );
        }
        
        return array(
            'score' => $score,
            'issues' => $issues,
            'response_time' => $response_time
        );
    }
    
    /**
     * Check database health
     */
    private function check_database_health() {
        // Mock database health data
        $table_count = rand(50, 200);
        $corrupted_tables = rand(0, 2);
        $score = max(0, 100 - ($corrupted_tables * 50));
        
        $issues = array();
        if ($corrupted_tables > 0) {
            $issues[] = array(
                'type' => 'error',
                'message' => sprintf(__('%d database tables are corrupted', 'unified-site-health-dashboard'), $corrupted_tables),
                'count' => $corrupted_tables
            );
        }
        
        return array(
            'score' => $score,
            'issues' => $issues,
            'table_count' => $table_count,
            'corrupted_tables' => $corrupted_tables
        );
    }
    
    /**
     * Check memory usage
     */
    private function check_memory_usage() {
        $memory_limit = ini_get('memory_limit');
        $current_usage = memory_get_usage(true);
        $peak_usage = memory_get_peak_usage(true);
        
        // Convert memory limit to bytes
        $limit_bytes = $this->convert_to_bytes($memory_limit);
        $usage_percentage = ($peak_usage / $limit_bytes) * 100;
        
        $score = 100;
        $issues = array();
        
        if ($usage_percentage > 90) {
            $score = 20;
            $issues[] = array(
                'type' => 'error',
                'message' => sprintf(__('Memory usage is %d%% of limit', 'unified-site-health-dashboard'), round($usage_percentage)),
                'usage_percentage' => $usage_percentage
            );
        } elseif ($usage_percentage > 70) {
            $score = 60;
            $issues[] = array(
                'type' => 'warning',
                'message' => sprintf(__('Memory usage is %d%% of limit', 'unified-site-health-dashboard'), round($usage_percentage)),
                'usage_percentage' => $usage_percentage
            );
        }
        
        return array(
            'score' => $score,
            'issues' => $issues,
            'memory_limit' => $memory_limit,
            'current_usage' => $current_usage,
            'peak_usage' => $peak_usage,
            'usage_percentage' => $usage_percentage
        );
    }
    
    /**
     * Convert memory string to bytes
     */
    private function convert_to_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int) $val;
        
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        
        return $val;
    }
    
    /**
     * Calculate overall host health score
     */
    private function calculate_overall_score($data) {
        $scores = array();
        
        if (isset($data['php_version']['score'])) {
            $scores[] = $data['php_version']['score'];
        }
        if (isset($data['ssl_certificate']['score'])) {
            $scores[] = $data['ssl_certificate']['score'];
        }
        if (isset($data['disk_space']['score'])) {
            $scores[] = $data['disk_space']['score'];
        }
        if (isset($data['server_response']['score'])) {
            $scores[] = $data['server_response']['score'];
        }
        if (isset($data['database_health']['score'])) {
            $scores[] = $data['database_health']['score'];
        }
        if (isset($data['memory_usage']['score'])) {
            $scores[] = $data['memory_usage']['score'];
        }
        
        return empty($scores) ? 0 : round(array_sum($scores) / count($scores));
    }
    
    /**
     * Get host health alerts
     */
    public function get_alerts($data) {
        $alerts = array();
        
        $overall_score = isset($data['overall_score']) ? $data['overall_score'] : 0;
        
        if ($overall_score < 50) {
            $alerts[] = array(
                'type' => 'error',
                'message' => __('Critical host health issues detected. Your server needs immediate attention.', 'unified-site-health-dashboard'),
                'score' => $overall_score
            );
        } elseif ($overall_score < 80) {
            $alerts[] = array(
                'type' => 'warning',
                'message' => __('Host health issues detected. Consider optimizing your server.', 'unified-site-health-dashboard'),
                'score' => $overall_score
            );
        } else {
            $alerts[] = array(
                'type' => 'success',
                'message' => __('Great host health! Your server is running well.', 'unified-site-health-dashboard'),
                'score' => $overall_score
            );
        }
        
        return $alerts;
    }
}
