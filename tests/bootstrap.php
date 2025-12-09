<?php
/**
 * PHPUnit Bootstrap File for WP-CrawlFlow
 */

// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define test constants
define('CRAWLFLOW_TESTS', true);
define('CRAWLFLOW_PLUGIN_DIR', dirname(__DIR__) . '/');
define('CRAWLFLOW_PLUGIN_URL', 'http://localhost/wp-content/plugins/wp-crawlflow/');
define('CRAWLFLOW_VERSION', '2.0.0-test');

// Load WordPress test environment if available
$wpTestsDir = getenv('WP_TESTS_DIR');
if ($wpTestsDir && file_exists($wpTestsDir . '/includes/functions.php')) {
    require_once $wpTestsDir . '/includes/functions.php';
    
    // Manually load the plugin
    tests_add_filter('muplugins_loaded', function() {
        require CRAWLFLOW_PLUGIN_DIR . 'wp-crawlflow.php';
        // Load dentalpart plugin processors
        $dentalpartPlugin = dirname(CRAWLFLOW_PLUGIN_DIR) . '/wp-crawlflow-dentalpart/wp-crawlflow-dentalpart.php';
        if (file_exists($dentalpartPlugin)) {
            require_once $dentalpartPlugin;
        }
    });
    
    require $wpTestsDir . '/includes/bootstrap.php';
} else {
    // Mock WordPress functions for unit tests
    require_once __DIR__ . '/mocks/wordpress-functions.php';
    // Load dentalpart processor for unit tests
    $dentalpartProcessor = dirname(__DIR__) . '/../wp-crawlflow-dentalpart/src/Processor/LookingForParentCategoryProcessor.php';
    if (file_exists($dentalpartProcessor)) {
        require_once $dentalpartProcessor;
    }
}

// Test helper functions
function crawlflow_clean_test_data() {
    // Clean up test data after tests
    global $wpdb;
    if (isset($wpdb)) {
        // Clean test tables if needed
    }
}

echo "CrawlFlow Tests Bootstrap Loaded\n";

