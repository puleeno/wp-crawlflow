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

        // Admin scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);

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
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $this->setDefaultOptions();

        // Run Rake migrations
        $this->runRakeMigrations();

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
     * Run Rake migrations
     */
    private function runRakeMigrations() {
        try {
            // Khởi tạo kernel migration
            $kernel = new \CrawlFlow\Kernel\CrawlFlowMigrationKernel();
            $kernel->initializeMigration();

            // Thực thi migration qua kernel
            $result = $kernel->runMigrations();

            if ($result['success'] ?? false) {
                    \Rake\Facade\Logger::info('CrawlFlow: Rake migrations completed successfully');
                } else {
                    \Rake\Facade\Logger::error('CrawlFlow: Rake migrations failed');
            }
        } catch (\Exception $e) {
            \Rake\Facade\Logger::error('CrawlFlow: Error running Rake migrations - ' . $e->getMessage());
        }
    }
}

// Initialize plugin
$crawlFlow = WP_CrawlFlow::getInstance();