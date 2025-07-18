<?php
/**
 * Plugin Name: CrawlFlow - Data Migration & Crawling Framework
 * Plugin URI: https://github.com/puleeno/wp-crawlflow
 * Description: A powerful WordPress plugin for data migration and web crawling using Rake 2.0 framework
 * Version: 2.0.0
 * Author: Puleeno Nguyen
 * Author URI: https://github.com/puleeno
 * License: GPL v3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: crawlflow
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 8.1
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CRAWLFLOW_VERSION', '2.0.0');
define('CRAWLFLOW_PLUGIN_FILE', __FILE__);
define('CRAWLFLOW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CRAWLFLOW_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CRAWLFLOW_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main CrawlFlow Plugin Class
 */
class WP_CrawlFlow {

    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Rake App instance
     */
    private $rakeApp;

    /**
     * WordPress Adapter instance
     */
    private $wordpressAdapter;

    /**
     * Get plugin instance (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize plugin
     */
    private function init() {
        // Load composer autoloader
        $this->loadComposerAutoloader();

        // Initialize hooks
        $this->initHooks();

        // Initialize admin
        if (is_admin()) {
            $this->initAdmin();
        }
    }

    /**
     * Load Composer autoloader
     */
    private function loadComposerAutoloader() {
        $autoloader = CRAWLFLOW_PLUGIN_DIR . 'vendor/autoload.php';
        if (file_exists($autoloader)) {
            require_once $autoloader;
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function initHooks() {
        // Plugin activation/deactivation
        register_activation_hook(CRAWLFLOW_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(CRAWLFLOW_PLUGIN_FILE, [$this, 'deactivate']);

        // Init hook
        add_action('init', [$this, 'initCrawlFlow']);

        // Admin scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);

        // AJAX handlers
        add_action('wp_ajax_crawlflow_start_crawl', [$this, 'ajaxStartCrawl']);
        add_action('wp_ajax_crawlflow_stop_crawl', [$this, 'ajaxStopCrawl']);
        add_action('wp_ajax_crawlflow_get_status', [$this, 'ajaxGetStatus']);

        // Load text domain
        add_action('plugins_loaded', [$this, 'loadTextDomain']);
    }

    /**
     * Initialize admin functionality
     */
    private function initAdmin() {
        // Admin specific initialization
        if (class_exists('CrawlFlow\Admin\AdminController')) {
            new \CrawlFlow\Admin\AdminController();
        }
        // Khởi tạo AjaxController để đăng ký REST API
        if (class_exists('CrawlFlow\Admin\AjaxController')) {
            new \CrawlFlow\Admin\AjaxController();
        }
    }

    /**
     * Initialize CrawlFlow framework
     */
    public function initCrawlFlow() {
        try {
            // Initialize Rake App
            if (class_exists('Rake\\App')) {
                $this->rakeApp = new \Rake\App();

                // Initialize WordPress Adapter
                if (class_exists('Puleeno\\Rake\\Adapter\\WordPress\\WordPressAdapter')) {
                    $this->wordpressAdapter = new \Puleeno\Rake\Adapter\WordPress\WordPressAdapter();
                    $this->rakeApp->setAdapter($this->wordpressAdapter);
                }

                // Load configuration
                $this->loadConfiguration();

            }
        } catch (Exception $e) {
            error_log('CrawlFlow initialization error: ' . $e->getMessage());
        }
    }

    /**
     * Load configuration
     */
    private function loadConfiguration() {
        $configFile = WP_CONTENT_DIR . '/crawlflow.config.php';
        if (file_exists($configFile)) {
            $config = include $configFile;
            if ($this->rakeApp) {
                $this->rakeApp->setConfig($config);
            }
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create necessary database tables
        $this->createTables();

        // Set default options
        $this->setDefaultOptions();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up if needed
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private function createTables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Origin data table
        $table_origin_data = $wpdb->prefix . 'crawlflow_origin_data';
        $sql_origin_data = "CREATE TABLE $table_origin_data (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            url varchar(2048) NOT NULL,
            content longtext,
            type varchar(50) DEFAULT 'html',
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY url (url(255)),
            KEY status (status),
            KEY type (type)
        ) $charset_collate;";

        // Feed items table
        $table_feed_items = $wpdb->prefix . 'crawlflow_feed_items';
        $sql_feed_items = "CREATE TABLE $table_feed_items (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            origin_id bigint(20) NOT NULL,
            title varchar(500),
            content longtext,
            excerpt text,
            meta_data longtext,
            post_type varchar(50) DEFAULT 'post',
            status varchar(20) DEFAULT 'draft',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY origin_id (origin_id),
            KEY post_type (post_type),
            KEY status (status),
            FOREIGN KEY (origin_id) REFERENCES $table_origin_data(id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_origin_data);
        dbDelta($sql_feed_items);
    }

    /**
     * Set default options
     */
    private function setDefaultOptions() {
        $default_options = [
            'crawlflow_version' => CRAWLFLOW_VERSION,
            'crawlflow_enabled' => true,
            'crawlflow_debug_mode' => false,
            'crawlflow_max_concurrent' => 5,
            'crawlflow_request_delay' => 1,
            'crawlflow_user_agent' => 'CrawlFlow/2.0.0',
        ];

        foreach ($default_options as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets($hook) {
        if (strpos($hook, 'crawlflow') !== false) {
            wp_enqueue_style(
                'crawlflow-admin',
                CRAWLFLOW_PLUGIN_URL . 'assets/css/admin.css',
                [],
                CRAWLFLOW_VERSION
            );
        }
    }

    /**
     * Load text domain
     */
    public function loadTextDomain() {
        load_plugin_textdomain(
            'crawlflow',
            false,
            dirname(CRAWLFLOW_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * AJAX: Start crawl
     */
    public function ajaxStartCrawl() {
        check_ajax_referer('crawlflow_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'crawlflow'));
        }

        try {
            if ($this->rakeApp) {
                $result = $this->rakeApp->start();
                wp_send_json_success($result);
            } else {
                wp_send_json_error(__('Rake App not initialized', 'crawlflow'));
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Stop crawl
     */
    public function ajaxStopCrawl() {
        check_ajax_referer('crawlflow_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'crawlflow'));
        }

        try {
            if ($this->rakeApp) {
                $result = $this->rakeApp->stop();
                wp_send_json_success($result);
            } else {
                wp_send_json_error(__('Rake App not initialized', 'crawlflow'));
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Get status
     */
    public function ajaxGetStatus() {
        check_ajax_referer('crawlflow_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'crawlflow'));
        }

        try {
            if ($this->rakeApp) {
                $status = $this->rakeApp->getStatus();
                wp_send_json_success($status);
            } else {
                wp_send_json_error(__('Rake App not initialized', 'crawlflow'));
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Get Rake App instance
     */
    public function getRakeApp() {
        return $this->rakeApp;
    }

    /**
     * Get WordPress Adapter instance
     */
    public function getWordPressAdapter() {
        return $this->wordpressAdapter;
    }
}

// Initialize plugin
$crawlFlow = WP_CrawlFlow::getInstance();