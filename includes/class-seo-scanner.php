<?php
/**
 * SEO Scanner Class
 * 
 * Handles SEO scanning including meta tags, alt text, broken links, etc.
 */

if (!defined('ABSPATH')) {
    exit;
}

class USH_SEO_Scanner {
    
    /**
     * Run SEO scan
     */
    public function scan() {
        $results = array(
            'scan_type' => 'seo',
            'scan_date' => current_time('mysql'),
            'status' => 'success',
            'data' => array()
        );
        
        // Check meta tags
        $results['data']['meta_tags'] = $this->check_meta_tags();
        
        // Check alt text
        $results['data']['alt_text'] = $this->check_alt_text();
        
        // Check broken links
        $results['data']['broken_links'] = $this->check_broken_links();
        
        // Check heading structure
        $results['data']['heading_structure'] = $this->check_heading_structure();
        
        // Check sitemap
        $results['data']['sitemap'] = $this->check_sitemap();
        
        // Calculate overall SEO score
        $results['data']['overall_score'] = $this->calculate_overall_score($results['data']);
        
        return $results;
    }
    
    /**
     * Check meta tags
     */
    private function check_meta_tags() {
        // Mock data for MVP
        $issues = array();
        $score = 85;
        
        // Check for missing meta description
        if (rand(0, 1)) {
            $issues[] = array(
                'type' => 'warning',
                'message' => __('Some pages are missing meta descriptions', 'unified-site-health-dashboard'),
                'count' => rand(1, 5)
            );
            $score -= 10;
        }
        
        // Check for duplicate titles
        if (rand(0, 1)) {
            $issues[] = array(
                'type' => 'error',
                'message' => __('Duplicate page titles found', 'unified-site-health-dashboard'),
                'count' => rand(1, 3)
            );
            $score -= 15;
        }
        
        return array(
            'score' => max(0, $score),
            'issues' => $issues,
            'total_pages_checked' => rand(50, 200)
        );
    }
    
    /**
     * Check alt text for images
     */
    private function check_alt_text() {
        // Mock data for MVP
        $issues = array();
        $score = 90;
        
        $missing_alt_count = rand(0, 20);
        if ($missing_alt_count > 0) {
            $issues[] = array(
                'type' => 'warning',
                'message' => sprintf(__('%d images are missing alt text', 'unified-site-health-dashboard'), $missing_alt_count),
                'count' => $missing_alt_count
            );
            $score -= $missing_alt_count * 2;
        }
        
        return array(
            'score' => max(0, $score),
            'issues' => $issues,
            'total_images' => rand(100, 500),
            'missing_alt' => $missing_alt_count
        );
    }
    
    /**
     * Check for broken links
     */
    private function check_broken_links() {
        // Mock data for MVP
        $broken_links = rand(0, 10);
        $score = max(0, 100 - ($broken_links * 10));
        
        $issues = array();
        if ($broken_links > 0) {
            $issues[] = array(
                'type' => 'error',
                'message' => sprintf(__('%d broken links found', 'unified-site-health-dashboard'), $broken_links),
                'count' => $broken_links
            );
        }
        
        return array(
            'score' => $score,
            'issues' => $issues,
            'total_links_checked' => rand(200, 1000),
            'broken_links' => $broken_links
        );
    }
    
    /**
     * Check heading structure
     */
    private function check_heading_structure() {
        // Mock data for MVP
        $issues = array();
        $score = 80;
        
        if (rand(0, 1)) {
            $issues[] = array(
                'type' => 'warning',
                'message' => __('Some pages have missing H1 tags', 'unified-site-health-dashboard'),
                'count' => rand(1, 5)
            );
            $score -= 10;
        }
        
        if (rand(0, 1)) {
            $issues[] = array(
                'type' => 'warning',
                'message' => __('Some pages have improper heading hierarchy', 'unified-site-health-dashboard'),
                'count' => rand(1, 8)
            );
            $score -= 10;
        }
        
        return array(
            'score' => max(0, $score),
            'issues' => $issues,
            'total_pages_checked' => rand(30, 150)
        );
    }
    
    /**
     * Check sitemap
     */
    private function check_sitemap() {
        // Mock data for MVP
        $sitemap_exists = rand(0, 1);
        $score = $sitemap_exists ? 100 : 0;
        
        $issues = array();
        if (!$sitemap_exists) {
            $issues[] = array(
                'type' => 'error',
                'message' => __('XML sitemap not found', 'unified-site-health-dashboard')
            );
        }
        
        return array(
            'score' => $score,
            'issues' => $issues,
            'sitemap_url' => $sitemap_exists ? home_url('/sitemap.xml') : null
        );
    }
    
    /**
     * Calculate overall SEO score
     */
    private function calculate_overall_score($data) {
        $scores = array();
        
        if (isset($data['meta_tags']['score'])) {
            $scores[] = $data['meta_tags']['score'];
        }
        if (isset($data['alt_text']['score'])) {
            $scores[] = $data['alt_text']['score'];
        }
        if (isset($data['broken_links']['score'])) {
            $scores[] = $data['broken_links']['score'];
        }
        if (isset($data['heading_structure']['score'])) {
            $scores[] = $data['heading_structure']['score'];
        }
        if (isset($data['sitemap']['score'])) {
            $scores[] = $data['sitemap']['score'];
        }
        
        return empty($scores) ? 0 : round(array_sum($scores) / count($scores));
    }
    
    /**
     * Get SEO alerts
     */
    public function get_alerts($data) {
        $alerts = array();
        
        $overall_score = isset($data['overall_score']) ? $data['overall_score'] : 0;
        
        if ($overall_score < 50) {
            $alerts[] = array(
                'type' => 'error',
                'message' => __('Critical SEO issues detected. Your site needs immediate attention.', 'unified-site-health-dashboard'),
                'score' => $overall_score
            );
        } elseif ($overall_score < 80) {
            $alerts[] = array(
                'type' => 'warning',
                'message' => __('SEO issues detected. Consider optimizing your site.', 'unified-site-health-dashboard'),
                'score' => $overall_score
            );
        } else {
            $alerts[] = array(
                'type' => 'success',
                'message' => __('Great SEO! Your site is well optimized.', 'unified-site-health-dashboard'),
                'score' => $overall_score
            );
        }
        
        return $alerts;
    }
}
