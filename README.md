# Unified Site Health + Forecast Dashboard

A comprehensive WordPress plugin that scans your website for performance, SEO, security, accessibility, and content issues, then displays results in a unified dashboard with forecasting capabilities.

## Features

### 🔍 Comprehensive Scanning
- **Performance**: Core Web Vitals (LCP, FID, CLS), TTFB, and server metrics
- **SEO**: Meta tags, alt text, broken links, heading structure, sitemap
- **Security**: WordPress version, plugins, themes, file permissions, SSL
- **Accessibility**: Alt text, heading structure, color contrast, keyboard navigation
- **Content Decay**: Old posts, broken internal links, outdated content
- **Host Health**: PHP version, SSL expiry, disk space, server response

### 📊 Unified Dashboard
- Color-coded score indicators (red/yellow/green)
- Real-time alerts and warnings
- Detailed metrics for each category
- Export functionality for reports

### 🔮 Forecasting
- Predictive analytics for performance trends
- 30-day projections based on current data
- Actionable recommendations

### ⚙️ Configuration
- Google PageSpeed Insights API integration
- Customizable scan frequency
- Email alerts for critical issues
- Configurable alert thresholds

## Installation

1. **Upload the plugin files** to your WordPress plugins directory:
   ```
   wp-content/plugins/unified-site-health-dashboard/
   ```

2. **Activate the plugin** through the 'Plugins' menu in WordPress

3. **Configure the plugin**:
   - Go to "Site Health Forecast" → "Settings"
   - Add your Google PageSpeed Insights API key (optional but recommended)
   - Configure scan frequency and alert settings

## Getting Your Google PageSpeed Insights API Key

1. Visit the [Google PageSpeed Insights API documentation](https://developers.google.com/speed/docs/insights/v5/get-started)
2. Follow the instructions to create a Google Cloud project
3. Enable the PageSpeed Insights API
4. Create an API key
5. Add the API key in the plugin settings

## Usage

### Dashboard Overview
After activation, you'll find a new "Site Health Forecast" menu item in your WordPress admin sidebar. The dashboard displays:

- **Overall Health Score**: Aggregated score across all categories
- **Section Scores**: Individual scores for Performance, SEO, Security, etc.
- **Alerts**: Critical issues that need immediate attention
- **Predictions**: Forecasted performance trends

### Running Scans
- **Automatic**: Scans run automatically based on your configured frequency
- **Manual**: Click "Run New Scan" button to scan immediately
- **On Activation**: Initial scan runs automatically when plugin is activated

### Understanding Scores
- **🟢 Green (80-100%)**: Excellent - no action needed
- **🟡 Yellow (50-79%)**: Good - minor optimizations recommended
- **🔴 Red (0-49%)**: Critical - immediate attention required

### Exporting Reports
Click the "Export Report" button to download a CSV file with all scan results for external analysis or record-keeping.

## File Structure

```
unified-site-health-dashboard/
├── unified-site-health-dashboard.php    # Main plugin file
├── includes/                            # Scanner classes
│   ├── class-performance-scanner.php
│   ├── class-seo-scanner.php
│   ├── class-security-scanner.php
│   ├── class-accessibility-scanner.php
│   ├── class-content-decay-scanner.php
│   ├── class-host-health-scanner.php
│   └── class-dashboard.php
├── assets/                             # Frontend assets
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
└── README.md
```

## Technical Details

### Database Tables
The plugin creates a custom table `wp_ush_scan_results` to store scan data:
- `id`: Primary key
- `scan_type`: Type of scan (performance, seo, etc.)
- `scan_data`: JSON data with scan results
- `scan_date`: Timestamp of the scan

### API Integration
- **Google PageSpeed Insights API**: For real performance data
- **WordPress Hooks**: For plugin/theme information
- **Server Metrics**: PHP, disk space, memory usage

### Security
- All AJAX requests are protected with nonces
- User capability checks for admin functions
- Sanitized input and escaped output
- No direct file access

## Customization

### Adding New Scanners
1. Create a new scanner class in `includes/`
2. Follow the naming convention: `class-{name}-scanner.php`
3. Implement the `scan()` method
4. Add the scanner to the main plugin file

### Modifying Dashboard
The dashboard is rendered by the `USH_Dashboard` class. You can:
- Add new sections in `render_*_section()` methods
- Modify styling in `assets/css/admin.css`
- Add JavaScript functionality in `assets/js/admin.js`

## Troubleshooting

### Common Issues

**"No scan data available"**
- Ensure the plugin is properly activated
- Check that database tables were created
- Run a manual scan from the dashboard

**PageSpeed API errors**
- Verify your API key is correct
- Check API quota limits
- Ensure your site is publicly accessible

**Performance issues during scans**
- Increase PHP memory limit
- Adjust scan frequency in settings
- Consider running scans during off-peak hours

### Debug Mode
Enable WordPress debug mode to see detailed error messages:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **Memory**: 128MB minimum (256MB recommended)

## Changelog

### Version 1.0.0
- Initial release
- Core Web Vitals scanning
- SEO, Security, Accessibility, Content Decay, and Host Health scanning
- Unified dashboard with forecasting
- Export functionality
- Settings page with API key configuration

## Support

For support, feature requests, or bug reports, please contact the plugin developer or create an issue in the plugin repository.

## License

This plugin is licensed under the GPL v2 or later.

---

**Note**: This is an MVP (Minimum Viable Product) version. Some features use mock data for demonstration purposes. Future versions will include real scanning capabilities for all modules.
