<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['submit']) && check_admin_referer('crawlflow_settings', 'crawlflow_nonce')) {
    $options = [
        'crawlflow_enabled' => isset($_POST['crawlflow_enabled']),
        'crawlflow_debug_mode' => isset($_POST['crawlflow_debug_mode']),
        'crawlflow_max_concurrent' => intval($_POST['crawlflow_max_concurrent']),
        'crawlflow_request_delay' => floatval($_POST['crawlflow_request_delay']),
        'crawlflow_user_agent' => sanitize_text_field($_POST['crawlflow_user_agent']),
        'crawlflow_timeout' => intval($_POST['crawlflow_timeout']),
        'crawlflow_retry_attempts' => intval($_POST['crawlflow_retry_attempts']),
    ];

    foreach ($options as $key => $value) {
        update_option($key, $value);
    }

    echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'crawlflow') . '</p></div>';
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('crawlflow_settings', 'crawlflow_nonce'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="crawlflow_enabled"><?php _e('Enable CrawlFlow', 'crawlflow'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="crawlflow_enabled" name="crawlflow_enabled"
                           value="1" <?php checked(get_option('crawlflow_enabled', true)); ?>>
                    <p class="description"><?php _e('Enable or disable the crawling functionality', 'crawlflow'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="crawlflow_debug_mode"><?php _e('Debug Mode', 'crawlflow'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="crawlflow_debug_mode" name="crawlflow_debug_mode"
                           value="1" <?php checked(get_option('crawlflow_debug_mode', false)); ?>>
                    <p class="description"><?php _e('Enable debug logging for troubleshooting', 'crawlflow'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="crawlflow_max_concurrent"><?php _e('Max Concurrent Requests', 'crawlflow'); ?></label>
                </th>
                <td>
                    <input type="number" id="crawlflow_max_concurrent" name="crawlflow_max_concurrent"
                           value="<?php echo esc_attr(get_option('crawlflow_max_concurrent', 5)); ?>"
                           min="1" max="20" class="regular-text">
                    <p class="description"><?php _e('Maximum number of concurrent requests (1-20)', 'crawlflow'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="crawlflow_request_delay"><?php _e('Request Delay (seconds)', 'crawlflow'); ?></label>
                </th>
                <td>
                    <input type="number" id="crawlflow_request_delay" name="crawlflow_request_delay"
                           value="<?php echo esc_attr(get_option('crawlflow_request_delay', 1)); ?>"
                           min="0" max="10" step="0.1" class="regular-text">
                    <p class="description"><?php _e('Delay between requests to avoid overwhelming servers', 'crawlflow'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="crawlflow_user_agent"><?php _e('User Agent', 'crawlflow'); ?></label>
                </th>
                <td>
                    <input type="text" id="crawlflow_user_agent" name="crawlflow_user_agent"
                           value="<?php echo esc_attr(get_option('crawlflow_user_agent', 'CrawlFlow/2.0.0')); ?>"
                           class="regular-text">
                    <p class="description"><?php _e('User agent string for HTTP requests', 'crawlflow'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="crawlflow_timeout"><?php _e('Request Timeout (seconds)', 'crawlflow'); ?></label>
                </th>
                <td>
                    <input type="number" id="crawlflow_timeout" name="crawlflow_timeout"
                           value="<?php echo esc_attr(get_option('crawlflow_timeout', 30)); ?>"
                           min="5" max="300" class="regular-text">
                    <p class="description"><?php _e('Timeout for HTTP requests (5-300 seconds)', 'crawlflow'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="crawlflow_retry_attempts"><?php _e('Retry Attempts', 'crawlflow'); ?></label>
                </th>
                <td>
                    <input type="number" id="crawlflow_retry_attempts" name="crawlflow_retry_attempts"
                           value="<?php echo esc_attr(get_option('crawlflow_retry_attempts', 3)); ?>"
                           min="0" max="10" class="regular-text">
                    <p class="description"><?php _e('Number of retry attempts for failed requests (0-10)', 'crawlflow'); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php _e('Database Information', 'crawlflow'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Origin Data Table', 'crawlflow'); ?></th>
                <td>
                    <code><?php echo esc_html($GLOBALS['wpdb']->prefix . 'crawlflow_origin_data'); ?></code>
                    <p class="description"><?php _e('Table storing crawled data', 'crawlflow'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Feed Items Table', 'crawlflow'); ?></th>
                <td>
                    <code><?php echo esc_html($GLOBALS['wpdb']->prefix . 'crawlflow_feed_items'); ?></code>
                    <p class="description"><?php _e('Table storing processed feed items', 'crawlflow'); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php _e('Actions', 'crawlflow'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Clear Data', 'crawlflow'); ?></th>
                <td>
                    <button type="button" id="clear-data" class="button button-secondary">
                        <?php _e('Clear All Crawl Data', 'crawlflow'); ?>
                    </button>
                    <p class="description"><?php _e('Warning: This will delete all crawled data and feed items', 'crawlflow'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Export Configuration', 'crawlflow'); ?></th>
                <td>
                    <button type="button" id="export-config" class="button button-secondary">
                        <?php _e('Export Config', 'crawlflow'); ?>
                    </button>
                    <p class="description"><?php _e('Export current configuration as JSON', 'crawlflow'); ?></p>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Clear data confirmation
    $('#clear-data').on('click', function() {
        if (confirm('<?php _e('Are you sure you want to clear all crawl data? This action cannot be undone.', 'crawlflow'); ?>')) {
            // AJAX call to clear data
            $.post(ajaxurl, {
                action: 'crawlflow_clear_data',
                nonce: '<?php echo wp_create_nonce('crawlflow_clear_data'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php _e('Data cleared successfully!', 'crawlflow'); ?>');
                } else {
                    alert('<?php _e('Error clearing data!', 'crawlflow'); ?>');
                }
            });
        }
    });

    // Export configuration
    $('#export-config').on('click', function() {
        $.post(ajaxurl, {
            action: 'crawlflow_export_config',
            nonce: '<?php echo wp_create_nonce('crawlflow_export_config'); ?>'
        }, function(response) {
            if (response.success) {
                // Create download link
                var dataStr = JSON.stringify(response.data, null, 2);
                var dataBlob = new Blob([dataStr], {type: 'application/json'});
                var url = window.URL.createObjectURL(dataBlob);
                var link = document.createElement('a');
                link.href = url;
                link.download = 'crawlflow-config.json';
                link.click();
                window.URL.revokeObjectURL(url);
            } else {
                alert('<?php _e('Error exporting configuration!', 'crawlflow'); ?>');
            }
        });
    });
});
</script>