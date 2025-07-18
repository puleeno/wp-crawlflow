<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="crawlflow-dashboard">
        <!-- Status Overview -->
        <div class="crawlflow-status-overview">
            <h2><?php _e('Crawl Status', 'crawlflow'); ?></h2>
            <div class="status-cards">
                <div class="status-card">
                    <h3><?php _e('Total URLs', 'crawlflow'); ?></h3>
                    <div class="status-number" id="total-urls">0</div>
                </div>
                <div class="status-card">
                    <h3><?php _e('Processed', 'crawlflow'); ?></h3>
                    <div class="status-number" id="processed-urls">0</div>
                </div>
                <div class="status-card">
                    <h3><?php _e('Feed Items', 'crawlflow'); ?></h3>
                    <div class="status-number" id="feed-items">0</div>
                </div>
                <div class="status-card">
                    <h3><?php _e('Status', 'crawlflow'); ?></h3>
                    <div class="status-indicator" id="crawl-status"><?php _e('Stopped', 'crawlflow'); ?></div>
                </div>
            </div>
        </div>

        <!-- Control Panel -->
        <div class="crawlflow-control-panel">
            <h2><?php _e('Control Panel', 'crawlflow'); ?></h2>
            <div class="control-buttons">
                <button type="button" id="start-crawl" class="button button-primary">
                    <?php _e('Start Crawl', 'crawlflow'); ?>
                </button>
                <button type="button" id="stop-crawl" class="button button-secondary" disabled>
                    <?php _e('Stop Crawl', 'crawlflow'); ?>
                </button>
                <button type="button" id="refresh-status" class="button">
                    <?php _e('Refresh Status', 'crawlflow'); ?>
                </button>
            </div>
        </div>

        <!-- Configuration -->
        <div class="crawlflow-configuration">
            <h2><?php _e('Configuration', 'crawlflow'); ?></h2>
            <div class="config-form">
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
                            <label for="crawlflow_max_concurrent"><?php _e('Max Concurrent Requests', 'crawlflow'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="crawlflow_max_concurrent" name="crawlflow_max_concurrent"
                                   value="<?php echo esc_attr(get_option('crawlflow_max_concurrent', 5)); ?>"
                                   min="1" max="20">
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
                                   min="0" max="10" step="0.1">
                            <p class="description"><?php _e('Delay between requests to avoid overwhelming servers', 'crawlflow'); ?></p>
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
                </table>
                <p class="submit">
                    <button type="button" id="save-config" class="button button-primary">
                        <?php _e('Save Configuration', 'crawlflow'); ?>
                    </button>
                </p>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="crawlflow-recent-activity">
            <h2><?php _e('Recent Activity', 'crawlflow'); ?></h2>
            <div class="activity-log" id="activity-log">
                <p><?php _e('No recent activity', 'crawlflow'); ?></p>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialize dashboard
    CrawlFlowDashboard.init();
});
</script>