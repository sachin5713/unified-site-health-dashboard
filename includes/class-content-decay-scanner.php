<?php
/**
 * Content Decay Scanner Class
 * 
 * Handles content decay scanning including old posts, broken internal links
 */

if (!defined('ABSPATH')) {
    exit;
}

class USH_Content_Decay_Scanner {
    
    /**
     * Run content decay scan
     */
    public function scan() {
        $results = array(
            'scan_type' => 'content_decay',
            'scan_date' => current_time('mysql'),
            'status' => 'success',
            'data' => array()
        );
        
        // Check old content
        $results['data']['old_content'] = $this->check_old_content();
        
        // Check broken internal links
        $results['data']['broken_internal_links'] = $this->check_broken_internal_links();
        
        // Check outdated content
        $results['data']['outdated_content'] = $this->check_outdated_content();
        
        // Check duplicate content
        $results['data']['duplicate_content'] = $this->check_duplicate_content();
        
        // Check content freshness
        $results['data']['content_freshness'] = $this->check_content_freshness();
        
        // Calculate overall content decay score
        $results['data']['overall_score'] = $this->calculate_overall_score($results['data']);
        
        return $results;
    }
    
    /**
     * Check for old content (older than 1 year)
     */
    private function check_old_content() {
        // Mock data for MVP
        $old_posts_count = rand(10, 50);
        $score = max(0, 100 - ($old_posts_count * 1.5));
        
        $issues = array();
        if ($old_posts_count > 30) {
            $issues[] = array(
                'type' => 'warning',
                'message' => sprintf(__('%d posts are older than 1 year', 'unified-site-health-dashboard'), $old_posts_count),
                'count' => $old_posts_count
            );
        }
        
        return array(
            'score' => $score,
            'issues' => $issues,
            'old_posts_count' => $old_posts_count,
            'total_posts' => rand(100, 500)
        );
    }
    
    /**
     * Check for broken internal links
     */
    private function check_broken_internal_links() {
        // Mock data for MVP
        $broken_links = rand(0, 15);
        $score = max(0, 100 - ($broken_links * 5));
        
        $issues = array();
        if ($broken_links > 0) {
            $issues[] = array(
                'type' => 'error',
                'message' => sprintf(__('%d broken internal links found', 'unified-site-health-dashboard'), $broken_links),
                'count' => $broken_links
            );
        }
        
        return array(
            'score' => $score,
            'issues' => $issues,
            'broken_links' => $broken_links,
            'total_links_checked' => rand(200, 800)
        );
    }
    
    /**
     * Check for outdated content
     */
    private function check_outdated_content() {
        // Mock data for MVP
        $outdated_posts = rand(0, 20);
        $score = max(0, 100 - ($outdated_posts * 3));
        
        $issues = array();
        if ($outdated_posts > 10) {
            $issues[] = array(
                'type' => 'warning',
                'message' => sprintf(__('%d posts contain outdated information', 'unified-site-health-dashboard'), $outdated_posts),
                'count' => $outdated_posts
            );
        }
        
        return array(
            'score' => $score,
            'issues' => $issues,
            'outdated_posts' => $outdated_posts
        );
    }
    
    /**
     * Check for duplicate content
     */
    private function check_duplicate_content() {
        // Mock data for MVP
        $duplicate_posts = rand(0, 8);
        $score = max(0, 100 - ($duplicate_posts * 8));
        
        $issues = array();
        if ($duplicate_posts > 0) {
            $issues[] = array(
                'type' => 'warning',
                'message' => sprintf(__('%d posts have duplicate content', 'unified-site-health-dashboard'), $duplicate_posts),
                'count' => $duplicate_posts
            );
        }
        
        return array(
            'score' => $score,
            'issues' => $issues,
            'duplicate_posts' => $duplicate_posts
        );
    }
    
    /**
     * Check content freshness
     */
    private function check_content_freshness() {
        // Mock data for MVP
        $last_update_days = rand(1, 365);
        $score = max(0, 100 - ($last_update_days / 10));
        
        $issues = array();
        if ($last_update_days > 90) {
            $issues[] = array(
                'type' => 'warning',
                'message' => sprintf(__('Content was last updated %d days ago', 'unified-site-health-dashboard'), $last_update_days),
                'days' => $last_update_days
            );
        }
        
        return array(
            'score' => $score,
            'issues' => $issues,
            'last_update_days' => $last_update_days
        );
    }
    
    /**
     * Calculate overall content decay score
     */
    private function calculate_overall_score($data) {
        $scores = array();
        
        if (isset($data['old_content']['score'])) {
            $scores[] = $data['old_content']['score'];
        }
        if (isset($data['broken_internal_links']['score'])) {
            $scores[] = $data['broken_internal_links']['score'];
        }
        if (isset($data['outdated_content']['score'])) {
            $scores[] = $data['outdated_content']['score'];
        }
        if (isset($data['duplicate_content']['score'])) {
            $scores[] = $data['duplicate_content']['score'];
        }
        if (isset($data['content_freshness']['score'])) {
            $scores[] = $data['content_freshness']['score'];
        }
        
        return empty($scores) ? 0 : round(array_sum($scores) / count($scores));
    }
    
    /**
     * Get content decay alerts
     */
    public function get_alerts($data) {
        $alerts = array();
        
        $overall_score = isset($data['overall_score']) ? $data['overall_score'] : 0;
        
        if ($overall_score < 50) {
            $alerts[] = array(
                'type' => 'error',
                'message' => __('Critical content decay issues detected. Your site needs immediate attention.', 'unified-site-health-dashboard'),
                'score' => $overall_score
            );
        } elseif ($overall_score < 80) {
            $alerts[] = array(
                'type' => 'warning',
                'message' => __('Content decay issues detected. Consider updating your content.', 'unified-site-health-dashboard'),
                'score' => $overall_score
            );
        } else {
            $alerts[] = array(
                'type' => 'success',
                'message' => __('Great content health! Your site content is fresh.', 'unified-site-health-dashboard'),
                'score' => $overall_score
            );
        }
        
        return $alerts;
    }
}
