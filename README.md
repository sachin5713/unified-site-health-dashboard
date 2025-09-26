# Unified Site Health + Forecast Dashboard

A comprehensive WordPress plugin that scans, monitors, and forecasts site performance, SEO, accessibility, and security issues using Google PageSpeed Insights API.

## Features

### Site Health Overview Module

- **Real-time PageSpeed Insights Integration**: Scans your website using Google's PageSpeed Insights API for both mobile and desktop
- **Comprehensive Audit Analysis**: Extracts and stores all audit results from lighthouseResult.audits
- **Custom Database Storage**: Stores detailed audit data in a custom database table
- **Interactive Dashboard**: Beautiful tiles showing category scores with modal popups for detailed analysis
- **Progress Tracking**: Real-time progress bar during scans with page-by-page status updates
- **Category-based Organization**: Organizes audits into Performance, SEO, Accessibility, Security, Content, and Host Health categories

## Installation

1. Upload the plugin files to `/wp-content/plugins/unified-site-health-dashboard/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to 'Site Health' > 'Settings' to configure your Google PageSpeed Insights API key
4. Start your first scan from the dashboard

## Configuration

### Google PageSpeed Insights API Key

1. Visit [Google PageSpeed Insights API](https://developers.google.com/speed/docs/insights/v5/get-started)
2. Get your API key
3. Enter it in the plugin settings

### Settings

- **API Key**: Your Google PageSpeed Insights API key
- **Automatic Scanning**: Enable/disable automatic scans
- **Scan Interval**: Choose between daily, weekly, or monthly scans
- **Pages to Scan**: Select which pages to include in scans

## Database Schema

The plugin creates a custom table `wp_ush_scan_results` with the following structure:

- `id`: Auto-increment primary key
- `page_id`: WordPress post/page ID
- `page_url`: Full URL of the scanned page
- `scan_type`: 'mobile' or 'desktop'
- `audit_category`: Category (Performance, SEO, Accessibility, etc.)
- `audit_name`: Name of the audit (e.g., LCP, CLS, FID)
- `audit_score`: Score from the API
- `audit_description`: Detailed description
- `audit_element`: Affected resource (image URL, script, etc.)
- `severity`: 'critical', 'warning', 'info', or 'good'
- `scan_date`: Timestamp of the scan

## Usage

### Starting a Scan

1. Go to 'Site Health' in your WordPress admin
2. Click 'Start New Scan' (ensure API key is configured)
3. Monitor progress with the real-time progress bar
4. View results in the category tiles

### Viewing Results

- **Dashboard Tiles**: Click any category tile to see detailed audit results
- **Modal Popups**: Detailed tables showing all audit issues with severity levels
- **Mobile vs Desktop**: Switch between mobile and desktop results in modals
- **Severity Indicators**: Color-coded badges for critical, warning, info, and good issues

### Understanding Scores

- **90-100%**: Good (Green)
- **50-89%**: Warning (Yellow)
- **0-49%**: Critical (Red)

## Technical Details

### Architecture

- **Modular Design**: Separate classes for database, scanner, dashboard, and admin
- **WordPress Standards**: Follows WordPress coding standards and best practices
- **AJAX Integration**: Real-time updates without page refreshes
- **Security**: Proper nonce verification and capability checks
- **Performance**: Batch processing to avoid timeouts

### File Structure

```
unified-site-health-dashboard/
├── unified-site-health-dashboard.php (Main plugin file)
├── includes/
│   ├── class-ush-database.php (Database operations)
│   ├── class-ush-scanner.php (PageSpeed Insights API)
│   ├── class-ush-dashboard.php (Dashboard display)
│   └── class-ush-admin.php (Settings page)
├── assets/
│   ├── css/
│   │   └── dashboard.css (Dashboard styles)
│   └── js/
│       └── dashboard.js (Dashboard JavaScript)
└── README.md
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Google PageSpeed Insights API key
- Internet connection for API calls

## Support

For support and feature requests, please contact the plugin author.

## License

GPL v2 or later

## Developer / Testing Notes

- This plugin uses a test URL by default in development: https://golhh.stagingwp.website. Update `get_test_pages()` in `includes/class-ush-scanner.php` to change the test pages used for development.
- The dashboard will only poll the server for scan progress when a scan is started manually or when "Automatic Scanning" is enabled in the plugin settings. This prevents background progress checks from running continuously.
- If an API or network error occurs during a category scan, errors are shown in the progress panel and the scanner continues with the next category/page.
