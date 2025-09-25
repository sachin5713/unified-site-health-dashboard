<?php
/**
 * Security Scanner Class
 * 
 * Handles security scanning including outdated plugins, themes, vulnerabilities
 */

if (!defined('ABSPATH')) {
    exit;
}

class USH_Security_Scanner {
    
    /**
     * Run security scan
     */
    public function scan() {
        $results = array(
            'scan_type' => 'security',
            'scan_date' => current_time('mysql'),
            'status' => 'success',
            'data' => array()
        );
        
        // Check WordPress version
        $results['data']['wordpress_version'] = $this->check_wordpress_version();
        
        // Check plugins
        $results['data']['plugins'] = $this->check_plugins();
        
        // Check themes
        $results['data']['themes'] = $this->check_themes();
        
        // Check file permissions
        $results['data']['file_permissions'] = $this->check_file_permissions();
        
        // Check SSL
        $results['data']['ssl'] = $this->check_ssl();
        
        // Check admin users
        $results['data']['admin_users'] = $this->check_admin_users();
        
        // Calculate overall security score
        $results['data']['overall_score'] = $this->calculate_overall_score($results['data']);
        
        return $results;
    }
    
    /**
     * Check WordPress version
     */
    private function check_wordpress_version() {
        global $wp_version;
        
        $latest_version = $this->get_latest_wordpress_version();
        $is_outdated = version_compare($wp_version, $latest_version, '<');
        
        return array(
            'current_version' => $wp_version,
            'latest_version' => $latest_version,
            'is_outdated' => $is_outdated,
            'score' => $is_outdated ? 30 : 100
        );
    }
    
    /**
     * Get latest WordPress version (mock for MVP)
     */
    private function get_latest_wordpress_version() {
        // In a real implementation, this would fetch from WordPress API
        return '6.4.2';
    }
    
    /**
     * Check plugins for updates and vulnerabilities
     */
    private function check_plugins() {
        // Mock data for MVP
        $plugins = get_plugins();
        $outdated_plugins = rand(0, 3);
        $vulnerable_plugins = rand(0, 1);
        
        $score = 100;
        $issues = array();
        
        if ($outdated_plugins > 0) {
            $score -= $outdated_plugins * 20;
            $issues[] = array(
                'type' => 'warning',
                'message' => sprintf(__('%d plugins need updates', 'unified-site-health-dashboard'), $outdated_plugins),
                'count' => $outdated_plugins
            );
        }
        
        if ($vulnerable_plugins > 0) {
            $score -= 50;
            $issues[] = array(
                'type' => 'error',
                'message' => sprintf(__('%d plugins have known vulnerabilities', 'unified-site-health-dashboard'), $vulnerable_plugins),
                'count' => $vulnerable_plugins
            );
        }
        
        return array(
            'score' => max(0, $score),
            'issues' => $issues,
            'total_plugins' => count($plugins),
            'outdated_plugins' => $outdated_plugins,
            'vulnerable_plugins' => $vulnerable_plugins
        );
    }
    
    /**
     * Check themes for updates and vulnerabilities
     */
    private function check_themes() {
        // Mock data for MVP
        $themes = wp_get_themes();
        $outdated_themes = rand(0, 1);
        $vulnerable_themes = rand(0, 1);
        
        $score = 100;
        $issues = array();
        
        if ($outdated_themes > 0) {
            $score -= 30;
            $issues[] = array(
                'type' => 'warning',
                'message' => sprintf(__('%d themes need updates', 'unified-site-health-dashboard'), $outdated_themes),
                'count' => $outdated_themes
            );
        }
        
        if ($vulnerable_themes > 0) {
            $score -= 40;
            $issues[] = array(
                'type' => 'error',
                'message' => sprintf(__('%d themes have known vulnerabilities', 'unified-site-health-dashboard'), $vulnerable_themes),
                'count' => $vulnerable_themes
            );
        }
        
        return array(
            'score' => max(0, $score),
            'issues' => $issues,
            'total_themes' => count($themes),
            'outdated_themes' => $outdated_themes,
            'vulnerable_themes' => $vulnerable_themes
        );
    }
    
    /**
     * Check file permissions
     */
    private function check_file_permissions() {
        // Mock data for MVP
        $insecure_files = rand(0, 5);
        $score = max(0, 100 - ($insecure_files * 15));
        
        $issues = array();
        if ($insecure_files > 0) {
            $issues[] = array(
                'type' => 'warning',
                'message' => sprintf(__('%d files have insecure permissions', 'unified-site-health-dashboard'), $insecure_files),
                'count' => $insecure_files
            );
        }
        
        return array(
            'score' => $score,
            'issues' => $issues,
            'insecure_files' => $insecure_files
        );
    }
    
    /**
     * Check SSL certificate
     */
    private function check_ssl() {
        $is_ssl = is_ssl();
        $ssl_score = $is_ssl ? 100 : 0;
        
        $issues = array();
        if (!$is_ssl) {
            $issues[] = array(
                'type' => 'error',
                'message' => __('SSL certificate not detected', 'unified-site-health-dashboard')
            );
        }
        
        return array(
            'score' => $ssl_score,
            'issues' => $issues,
            'is_ssl' => $is_ssl,
            'certificate_valid' => $is_ssl ? rand(0, 1) : false
        );
    }
    
    /**
     * Check admin users
     */
    private function check_admin_users() {
        // Mock data for MVP
        $admin_users = get_users(array('role' => 'administrator'));
        $weak_passwords = rand(0, 2);
        $score = max(0, 100 - ($weak_passwords * 25));
        
        $issues = array();
        if ($weak_passwords > 0) {
            $issues[] = array(
                'type' => 'warning',
                'message' => sprintf(__('%d admin users have weak passwords', 'unified-site-health-dashboard'), $weak_passwords),
                'count' => $weak_passwords
            );
        }
        
        return array(
            'score' => $score,
            'issues' => $issues,
            'total_admin_users' => count($admin_users),
            'weak_passwords' => $weak_passwords
        );
    }
    
    /**
     * Calculate overall security score
     */
    private function calculate_overall_score($data) {
        $scores = array();
        
        if (isset($data['wordpress_version']['score'])) {
            $scores[] = $data['wordpress_version']['score'];
        }
        if (isset($data['plugins']['score'])) {
            $scores[] = $data['plugins']['score'];
        }
        if (isset($data['themes']['score'])) {
            $scores[] = $data['themes']['score'];
        }
        if (isset($data['file_permissions']['score'])) {
            $scores[] = $data['file_permissions']['score'];
        }
        if (isset($data['ssl']['score'])) {
            $scores[] = $data['ssl']['score'];
        }
        if (isset($data['admin_users']['score'])) {
            $scores[] = $data['admin_users']['score'];
        }
        
        return empty($scores) ? 0 : round(array_sum($scores) / count($scores));
    }
    
    /**
     * Get security alerts
     */
    public function get_alerts($data) {
        $alerts = array();
        
        $overall_score = isset($data['overall_score']) ? $data['overall_score'] : 0;
        
        if ($overall_score < 50) {
            $alerts[] = array(
                'type' => 'error',
                'message' => __('Critical security issues detected. Your site needs immediate attention.', 'unified-site-health-dashboard'),
                'score' => $overall_score
            );
        } elseif ($overall_score < 80) {
            $alerts[] = array(
                'type' => 'warning',
                'message' => __('Security issues detected. Consider updating your site.', 'unified-site-health-dashboard'),
                'score' => $overall_score
            );
        } else {
            $alerts[] = array(
                'type' => 'success',
                'message' => __('Great security! Your site is well protected.', 'unified-site-health-dashboard'),
                'score' => $overall_score
            );
        }
        
        return $alerts;
    }
}
