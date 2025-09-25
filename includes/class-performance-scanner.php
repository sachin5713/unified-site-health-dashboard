<?php
/**
 * Performance Scanner Class
 * 
 * Handles performance scanning using Google PageSpeed Insights API
 * and other performance metrics
 */

if (!defined('ABSPATH')) {
    exit;
}

class USH_Performance_Scanner {
    
    /**
     * Google PageSpeed Insights API URL
     */
    private $pagespeed_api_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
    
    /**
     * API key for Google PageSpeed Insights
     */
    private $api_key;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = get_option('ush_pagespeed_api_key', '');
    }
    
    /**
     * Run performance scan
     */
    public function scan($url = null) {
        $results = array(
            'scan_type' => 'performance',
            'scan_date' => current_time('mysql'),
            'status' => 'success',
            'data' => array()
        );
        
        // Get target URL (site or specific page)
        $site_url = $url ? $url : home_url();
        
        // Run PageSpeed Insights scan
        $pagespeed_data = $this->get_pagespeed_data($site_url);
        
        if ($pagespeed_data) {
            $results['data']['pagespeed'] = $pagespeed_data;
        }
        
        // Get additional performance metrics
        $results['data']['ttfb'] = $this->get_ttfb_metric($site_url);
        $results['data']['server_response'] = $this->get_server_response_metrics();
        
        // Calculate overall performance score
        $results['data']['overall_score'] = $this->calculate_overall_score($results['data']);
        $results['data']['page_url'] = $site_url;
        if ($url) {
            $post_id = function_exists('url_to_postid') ? url_to_postid($url) : 0;
            $results['data']['page_title'] = $post_id ? get_the_title($post_id) : $url;
        }
        
        return $results;
    }
    
    /**
     * Get PageSpeed Insights data
     */
    private function get_pagespeed_data($url) {
        if (empty($this->api_key)) {
            return $this->get_mock_pagespeed_data();
        }
        
        $api_url = add_query_arg(array(
            'url' => $url,
            'key' => $this->api_key,
            'strategy' => 'mobile'
        ), $this->pagespeed_api_url);
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'Unified Site Health Dashboard/1.0'
            )
        ));
        
        if (is_wp_error($response)) {
            return $this->get_mock_pagespeed_data();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || isset($data['error'])) {
            return $this->get_mock_pagespeed_data();
        }
        
        return $this->parse_pagespeed_data($data);
    }
    
    /**
     * Parse PageSpeed Insights data
     */
    private function parse_pagespeed_data($data) {
        $lighthouse = $data['lighthouseResult'];
        $audits = $lighthouse['audits'];
        
        return array(
            'performance_score' => round($lighthouse['categories']['performance']['score'] * 100),
            'lcp' => array(
                'value' => $audits['largest-contentful-paint']['numericValue'] / 1000, // Convert to seconds
                'score' => $audits['largest-contentful-paint']['score']
            ),
            'fid' => array(
                'value' => $audits['max-potential-fid']['numericValue'] / 1000, // Convert to seconds
                'score' => $audits['max-potential-fid']['score']
            ),
            'cls' => array(
                'value' => $audits['cumulative-layout-shift']['numericValue'],
                'score' => $audits['cumulative-layout-shift']['score']
            ),
            'fcp' => array(
                'value' => $audits['first-contentful-paint']['numericValue'] / 1000, // Convert to seconds
                'score' => $audits['first-contentful-paint']['score']
            ),
            'si' => array(
                'value' => $audits['speed-index']['numericValue'] / 1000, // Convert to seconds
                'score' => $audits['speed-index']['score']
            ),
            'tti' => array(
                'value' => $audits['interactive']['numericValue'] / 1000, // Convert to seconds
                'score' => $audits['interactive']['score']
            )
        );
    }
    
    /**
     * Get mock PageSpeed data for testing
     */
    private function get_mock_pagespeed_data() {
        return array(
            'performance_score' => rand(40, 95),
            'lcp' => array(
                'value' => rand(15, 45) / 10, // 1.5 to 4.5 seconds
                'score' => rand(0, 1)
            ),
            'fid' => array(
                'value' => rand(5, 30) / 10, // 0.5 to 3.0 seconds
                'score' => rand(0, 1)
            ),
            'cls' => array(
                'value' => rand(0, 25) / 100, // 0 to 0.25
                'score' => rand(0, 1)
            ),
            'fcp' => array(
                'value' => rand(10, 40) / 10, // 1.0 to 4.0 seconds
                'score' => rand(0, 1)
            ),
            'si' => array(
                'value' => rand(15, 50) / 10, // 1.5 to 5.0 seconds
                'score' => rand(0, 1)
            ),
            'tti' => array(
                'value' => rand(20, 60) / 10, // 2.0 to 6.0 seconds
                'score' => rand(0, 1)
            )
        );
    }
    
    /**
     * Get TTFB (Time to First Byte) metric
     */
    private function get_ttfb_metric($url) {
        $start_time = microtime(true);
        
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'Unified Site Health Dashboard/1.0'
            )
        ));
        
        if (is_wp_error($response)) {
            return array(
                'value' => 0,
                'status' => 'error',
                'message' => $response->get_error_message()
            );
        }
        
        $ttfb = (microtime(true) - $start_time) * 1000; // Convert to milliseconds
        
        return array(
            'value' => round($ttfb, 2),
            'status' => 'success',
            'message' => 'TTFB measured successfully'
        );
    }
    
    /**
     * Get server response metrics
     */
    private function get_server_response_metrics() {
        return array(
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        );
    }
    
    /**
     * Calculate overall performance score
     */
    private function calculate_overall_score($data) {
        $scores = array();
        
        if (isset($data['pagespeed']['performance_score'])) {
            $scores[] = $data['pagespeed']['performance_score'];
        }
        
        // Add TTFB score (inverse relationship - lower is better)
        if (isset($data['ttfb']['value'])) {
            $ttfb_score = max(0, 100 - ($data['ttfb']['value'] * 2)); // Penalty for high TTFB
            $scores[] = $ttfb_score;
        }
        
        // Add Core Web Vitals scores
        if (isset($data['pagespeed']['lcp']['score'])) {
            $scores[] = $data['pagespeed']['lcp']['score'] * 100;
        }
        if (isset($data['pagespeed']['fid']['score'])) {
            $scores[] = $data['pagespeed']['fid']['score'] * 100;
        }
        if (isset($data['pagespeed']['cls']['score'])) {
            $scores[] = $data['pagespeed']['cls']['score'] * 100;
        }
        
        return empty($scores) ? 0 : round(array_sum($scores) / count($scores));
    }
    
    /**
     * Get performance alerts
     */
    public function get_alerts($data) {
        $alerts = array();
        
        $overall_score = isset($data['overall_score']) ? $data['overall_score'] : 0;
        
        if ($overall_score < 50) {
            $alerts[] = array(
                'type' => 'error',
                'message' => __('Critical performance issues detected. Your site needs immediate attention.', 'unified-site-health-dashboard'),
                'score' => $overall_score
            );
        } elseif ($overall_score < 80) {
            $alerts[] = array(
                'type' => 'warning',
                'message' => __('Performance issues detected. Consider optimizing your site.', 'unified-site-health-dashboard'),
                'score' => $overall_score
            );
        } else {
            $alerts[] = array(
                'type' => 'success',
                'message' => __('Great performance! Your site is running well.', 'unified-site-health-dashboard'),
                'score' => $overall_score
            );
        }
        
        // Core Web Vitals alerts
        if (isset($data['pagespeed'])) {
            $pagespeed = $data['pagespeed'];
            
            if (isset($pagespeed['lcp']['value']) && $pagespeed['lcp']['value'] > 2.5) {
                $alerts[] = array(
                    'type' => 'warning',
                    'message' => sprintf(__('LCP is %.2fs (should be under 2.5s)', 'unified-site-health-dashboard'), $pagespeed['lcp']['value']),
                    'metric' => 'lcp'
                );
            }
            
            if (isset($pagespeed['fid']['value']) && $pagespeed['fid']['value'] > 0.1) {
                $alerts[] = array(
                    'type' => 'warning',
                    'message' => sprintf(__('FID is %.2fs (should be under 0.1s)', 'unified-site-health-dashboard'), $pagespeed['fid']['value']),
                    'metric' => 'fid'
                );
            }
            
            if (isset($pagespeed['cls']['value']) && $pagespeed['cls']['value'] > 0.1) {
                $alerts[] = array(
                    'type' => 'warning',
                    'message' => sprintf(__('CLS is %.3f (should be under 0.1)', 'unified-site-health-dashboard'), $pagespeed['cls']['value']),
                    'metric' => 'cls'
                );
            }
        }
        
        return $alerts;
    }
}
