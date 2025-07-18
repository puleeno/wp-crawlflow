<?php
/**
 * Uninstall CrawlFlow Plugin
 *
 * This file is executed when the plugin is uninstalled.
 * It removes all plugin data from the database and filesystem.
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if user has permission to uninstall
if (!current_user_can('activate_plugins')) {
    return;
}

// Include WordPress database functions
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

/**
 * Remove plugin data
 */
function crawlflow_remove_data() {
    global $wpdb;

    // Remove database tables
    $tables = [
        $wpdb->prefix . 'crawlflow_origin_data',
        $wpdb->prefix . 'crawlflow_feed_items'
    ];

    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }

    // Remove plugin options
    $options = [
        'crawlflow_version',
        'crawlflow_enabled',
        'crawlflow_debug_mode',
        'crawlflow_max_concurrent',
        'crawlflow_request_delay',
        'crawlflow_user_agent',
        'crawlflow_timeout',
        'crawlflow_retry_attempts'
    ];

    foreach ($options as $option) {
        delete_option($option);
    }

    // Remove plugin transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_crawlflow_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_crawlflow_%'");

    // Remove scheduled events
    wp_clear_scheduled_hook('crawlflow_cron_crawl');
    wp_clear_scheduled_hook('crawlflow_cron_cleanup');

    // Remove cache files
    $cache_dir = WP_CONTENT_DIR . '/cache/crawlflow/';
    if (is_dir($cache_dir)) {
        crawlflow_remove_directory($cache_dir);
    }

    // Remove log files
    $log_dir = WP_CONTENT_DIR . '/logs/';
    $log_file = $log_dir . 'crawlflow.log';
    if (file_exists($log_file)) {
        unlink($log_file);
    }

    // Remove empty log directory if exists
    if (is_dir($log_dir) && count(scandir($log_dir)) <= 2) {
        rmdir($log_dir);
    }
}

/**
 * Remove directory recursively
 */
function crawlflow_remove_directory($dir) {
    if (!is_dir($dir)) {
        return;
    }

    $files = array_diff(scandir($dir), ['.', '..']);

    foreach ($files as $file) {
        $path = $dir . '/' . $file;

        if (is_dir($path)) {
            crawlflow_remove_directory($path);
        } else {
            unlink($path);
        }
    }

    rmdir($dir);
}

/**
 * Clean up any remaining data
 */
function crawlflow_cleanup_remaining() {
    global $wpdb;

    // Remove any orphaned post meta
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'crawlflow_%'");

    // Remove any orphaned user meta
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'crawlflow_%'");

    // Remove any orphaned options
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'crawlflow_%'");
}

// Execute cleanup
crawlflow_remove_data();
crawlflow_cleanup_remaining();

// Log uninstall for debugging
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('CrawlFlow plugin uninstalled successfully');
}
