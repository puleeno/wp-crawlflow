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
     * Application bootstrapper
     */
    private $bootstrapper = null;

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

        // Initialize Rake container
        $this->initRake();

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
     * Initialize Rake container and register service providers
     * 
     * @throws \Exception If Rake classes are not available or initialization fails
     */
    private function initRake(): void {
        // Initialize LoggerService FIRST (CRITICAL: Do not delete or modify)
        // This ensures Rake Logger system is available before any other components
        \CrawlFlow\LoggerService::init();
        
        // Use ApplicationBootstrapper for clean service provider registration
        // The constructor automatically calls parent::__construct() which handles bootstrapping
        $bootstrapper = new \CrawlFlow\Bootstrapper\ApplicationBootstrapper();

        // Do not delete this line - Required for Rake framework initialization
        $bootstrapper->bootstrap();

        // Store bootstrapper instance for later use
        $this->bootstrapper = $bootstrapper;
    }

    /**
     * Initialize WordPress hooks
     */
    private function initHooks() {
        // Initialize registry hooks (for processors, data sources, etc.)
        \CrawlFlow\Hooks\RegistryHooks::init();

        // Plugin activation/deactivation
        register_activation_hook(CRAWLFLOW_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(CRAWLFLOW_PLUGIN_FILE, [$this, 'deactivate']);

        // Admin scripts and styles
        // Use priority 20 to ensure WordPress core scripts (like wp-hooks) are loaded first
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets'], 20);
        
        // Register React Flow CSS early to ensure it's available
        add_action('admin_init', [$this, 'registerReactFlowCSS'], 1);
        
        // Load React Flow CSS early in head to prevent FOUC
        add_action('admin_head', [$this, 'preloadReactFlowCSS'], 1);

        // Load text domain
        add_action('plugins_loaded', [$this, 'loadTextDomain']);
        
        // Fix for wp.hooks and wp.svgPainter undefined errors
        // Ensure WordPress core objects exist before scripts try to use them
        add_action('admin_head', [$this, 'fixWpObjectsUndefined'], 1);
    }

    /**
     * Initialize admin functionality
     * Controller is automatically booted by AdminServiceProvider
     * 
     * @throws \Exception If Rake is not initialized
     */
    private function initAdmin(): void {
        // Load ProjectStatusService for completion tracking
        require_once CRAWLFLOW_PLUGIN_DIR . 'src/Admin/ProjectStatusService.php';
        require_once CRAWLFLOW_PLUGIN_DIR . 'src/Helper/DatabaseStatusChecker.php';
        
        // Initialize project status tracking
        $projectStatusService = new \CrawlFlow\Admin\ProjectStatusService();
        $dbChecker = new \CrawlFlow\Helper\DatabaseStatusChecker();
        
        // Schedule automatic status updates
        if (!wp_next_scheduled('crawlflow_update_project_statuses')) {
            wp_schedule_event(time(), 'hourly', 'crawlflow_update_project_statuses');
        }
        
        // Hook for automatic status updates
        add_action('crawlflow_update_project_statuses', function() use ($projectStatusService) {
            $updated = $projectStatusService->autoUpdateProjectStatuses();
            if (!empty($updated)) {
                \Rake\Facade\Logger::info('CrawlFlow: Auto-updated project statuses: ' . json_encode($updated));
            }
        });
        
        // Register admin notices for migration status
        $dbChecker->registerHooks();
        
        // Controller is already initialized by AdminServiceProvider
        // This method is kept for future admin-specific initialization
        // that doesn't belong in service providers
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $this->setDefaultOptions();

        // Initialize database status checker
        $this->initializeDatabaseStatusChecker();

        // Run Rake migrations
        $this->runRakeMigrations();

        // Run CrawlFlow custom migrations
        $this->runCrawlFlowMigrations();
        
        // Initialize log import cron
        \CrawlFlow\Cron\LogImportCron::init();

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
     * Register React Flow CSS early
     */
    public function registerReactFlowCSS() {
        // Check if we're on the React Flow editor page
        $subScreen = sanitize_text_field($_GET['sub'] ?? '');
        $editor = sanitize_text_field($_GET['editor'] ?? '');
        
        if ($subScreen === 'compose' && $editor === 'flow') {
            // Register CSS early so it's available when enqueued
            wp_register_style(
                'reactflow-style',
                'https://cdn.jsdelivr.net/npm/reactflow@11.11.4/dist/style.css',
                [],
                '11.11.4',
                'all'
            );
        }
    }
    
    /**
     * Load React Flow CSS early in head to prevent FOUC
     */
    public function preloadReactFlowCSS() {
        // Check if we're on the React Flow editor page
        $subScreen = sanitize_text_field($_GET['sub'] ?? '');
        $editor = sanitize_text_field($_GET['editor'] ?? '');
        
        if ($subScreen === 'compose' && $editor === 'flow') {
            // Load React Flow CSS directly in head for immediate rendering
            // This prevents Flash of Unstyled Content (FOUC)
            echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/reactflow@11.11.4/dist/style.css?ver=11.11.4" media="all">' . "\n";
            
            // Add inline style to hide content until CSS loads
            echo '<style id="crawlflow-react-flow-critical">
                #crawlflow-react-flow-root {
                    visibility: hidden;
                    opacity: 0;
                    transition: opacity 0.2s ease, visibility 0.2s ease;
                }
                #crawlflow-react-flow-root.react-flow-loaded {
                    visibility: visible !important;
                    opacity: 1 !important;
                }
            </style>' . "\n";
            
            // Script to mark CSS as loaded - wait for DOM ready
            echo '<script>
                (function() {
                    function showReactFlow() {
                        var root = document.getElementById("crawlflow-react-flow-root");
                        if (root) {
                            root.classList.add("react-flow-loaded");
                        }
                    }
                    
                    // Check if CSS is already loaded
                    function checkCSSLoaded() {
                        var link = document.querySelector("link[href*=\'reactflow@11.11.4\']");
                        if (link) {
                            // Check if stylesheet is loaded
                            try {
                                if (link.sheet && link.sheet.cssRules && link.sheet.cssRules.length > 0) {
                                    showReactFlow();
                                    return true;
                                }
                            } catch (e) {
                                // Cross-origin stylesheet, check differently
                                if (link.sheet || link.styleSheet) {
                                    showReactFlow();
                                    return true;
                                }
                            }
                            
                            // Wait for onload
                            link.onload = function() {
                                showReactFlow();
                            };
                            
                            // Fallback timeout
                            setTimeout(function() {
                                showReactFlow();
                            }, 500);
                        } else {
                            // No link found, show anyway after delay
                            setTimeout(function() {
                                showReactFlow();
                            }, 200);
                        }
                        return false;
                    }
                    
                    // Wait for DOM ready
                    if (document.readyState === "loading") {
                        document.addEventListener("DOMContentLoaded", function() {
                            setTimeout(checkCSSLoaded, 100);
                        });
                    } else {
                        setTimeout(checkCSSLoaded, 100);
                    }
                })();
            </script>' . "\n";
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets($hook) {
        // Load on all CrawlFlow pages including analytics
        if (strpos($hook, 'crawlflow') !== false) {
            wp_enqueue_style(
                'crawlflow-admin',
                CRAWLFLOW_PLUGIN_URL . 'assets/css/admin.css',
                [],
                CRAWLFLOW_VERSION
            );
            
            // Also enqueue admin JS for functionality
            wp_enqueue_script(
                'crawlflow-admin-js',
                CRAWLFLOW_PLUGIN_URL . 'assets/js/admin.js',
                ['jquery'],
                CRAWLFLOW_VERSION,
                true
            );
            
            // Localize script for AJAX calls
            wp_localize_script('crawlflow-admin-js', 'crawlflowAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'adminUrl' => admin_url(),
                'nonce' => wp_create_nonce('crawlflow_admin_nonce'),
                'strings' => [
                    'confirmDelete' => __('Are you sure you want to delete this item?', 'crawlflow'),
                    'saving' => __('Saving...', 'crawlflow'),
                    'saved' => __('Saved successfully!', 'crawlflow'),
                    'error' => __('An error occurred.', 'crawlflow'),
                ],
            ]);
            
            // Check if we're on the React Flow editor page
            $subScreen = sanitize_text_field($_GET['sub'] ?? '');
            $editor = sanitize_text_field($_GET['editor'] ?? '');
            
            if ($subScreen === 'compose' && $editor === 'flow') {
                $this->enqueueReactFlowAssets();
            }
        }
    }
    
    /**
     * Enqueue React Flow assets
     */
    private function enqueueReactFlowAssets() {
        $buildDir = CRAWLFLOW_PLUGIN_DIR . 'assets/js/crawflow-ui/dist';
        $buildUrl = CRAWLFLOW_PLUGIN_URL . 'assets/js/crawflow-ui/dist';
        
        // Check if manifest exists
        $manifestPath = $buildDir . '/.vite/manifest.json';
        
        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            
            // Enqueue main entry
            if (isset($manifest['index.html'])) {
                $entry = $manifest['index.html'];
                
                // Enqueue CSS
                if (isset($entry['css'])) {
                    foreach ($entry['css'] as $css) {
                        wp_enqueue_style(
                            'crawlflow-react-flow',
                            $buildUrl . '/' . $css,
                            [],
                            CRAWLFLOW_VERSION
                        );
                    }
                }
                
                // Enqueue JS
                if (isset($entry['file'])) {
                    // Use file hash as version for cache busting
                    $fileHash = pathinfo($entry['file'], PATHINFO_FILENAME);
                    $fileHash = str_replace('crawflow-ui.', '', $fileHash);
                    wp_enqueue_script(
                        'crawlflow-react-flow',
                        $buildUrl . '/' . $entry['file'],
                        [], // No dependencies - React Flow is self-contained
                        $fileHash ?: CRAWLFLOW_VERSION,
                        true
                    );
                    
                    // Localize script with WordPress AJAX URL and nonce
                    wp_localize_script(
                        'crawlflow-react-flow',
                        'crawlflowAdmin',
                        [
                            'ajaxUrl' => admin_url('admin-ajax.php'),
                            'nonce' => wp_create_nonce('crawlflow_admin_nonce'),
                            'adminUrl' => admin_url(),
                            'projectId' => isset($_GET['project_id']) ? (int) $_GET['project_id'] : 0,
                        ]
                    );
                }
            }
        } else {
            // Fallback: Try to find JS file directly
            $jsFiles = glob($buildDir . '/crawflow-ui.*.js');
            if (!empty($jsFiles)) {
                $jsFile = basename($jsFiles[0]);
                wp_enqueue_script(
                    'crawlflow-react-flow',
                    $buildUrl . '/' . $jsFile,
                    [],
                    CRAWLFLOW_VERSION,
                    true
                );
            } else {
                // Fallback: Load from CDN for development
                wp_add_inline_script('jquery', 'console.warn("CrawlFlow: Built assets not found. Please run npm run build in assets/js/crawflow-ui");');
            }
        }
        
        // Enqueue React Flow CSS (already registered in admin_init)
        // This is a fallback in case preload in admin_head didn't work
        if (!wp_style_is('reactflow-style', 'enqueued')) {
            wp_enqueue_style('reactflow-style');
        }
        
        // Force CSS to load in head, not footer
        wp_style_add_data('reactflow-style', 'group', 0);
        
        // Add inline style to ensure React Flow styles are not overridden
        wp_add_inline_style(
            'reactflow-style',
            '
            /* Ensure React Flow styles work within WordPress admin */
            #crawlflow-react-flow-root .react-flow {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif !important;
            }
            #crawlflow-react-flow-root .react-flow__node {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif !important;
            }
            '
        );
        
        // Always load Tailwind CSS via CDN for React Flow UI
        // This ensures all Tailwind classes work properly
        wp_add_inline_script(
            'crawlflow-react-flow',
            'document.addEventListener("DOMContentLoaded", function() {
                if (typeof tailwindcss === "undefined") {
                    var script = document.createElement("script");
                    script.src = "https://cdn.tailwindcss.com";
                    script.onload = function() {
                        // Configure Tailwind to only apply to React Flow container
                        if (typeof tailwind !== "undefined" && tailwind.config) {
                            tailwind.config = {
                                content: ["#crawlflow-react-flow-root"],
                                important: "#crawlflow-react-flow-root"
                            };
                        }
                    };
                    document.head.appendChild(script);
                }
            });',
            'after'
        );
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
     * Fix wp.hooks and wp.svgPainter undefined errors
     * This ensures WordPress core objects exist before scripts try to use them
     */
    public function fixWpObjectsUndefined() {
        // Only run in admin area
        if (!is_admin()) {
            return;
        }
        
        // Add safety checks to ensure WordPress core objects exist before scripts run
        // This fixes errors like "wp.hooks is undefined" and "wp.svgPainter is undefined"
        ?>
        <script>
        (function() {
            // Ensure wp object exists
            if (typeof window.wp === 'undefined') {
                window.wp = {};
            }
            
            // Fix for wp.hooks - used by heartbeat.js
            // If wp.hooks doesn't exist yet, create a minimal stub
            // This will be replaced by the actual wp-hooks script when it loads
            if (typeof window.wp.hooks === 'undefined') {
                window.wp.hooks = {
                    doAction: function() {
                        // Stub function - will be replaced by actual wp-hooks
                        if (console && console.warn) {
                            console.warn('wp.hooks.doAction called before wp-hooks script loaded');
                        }
                    },
                    addAction: function() {},
                    removeAction: function() {},
                    addFilter: function() {},
                    removeFilter: function() {},
                    applyFilters: function() {
                        return arguments[1];
                    }
                };
            }
            
            // Fix for wp.svgPainter - used by svg-painter.js
            // If wp.svgPainter doesn't exist yet, create a minimal stub
            // This will be replaced by the actual svg-painter script when it loads
            if (typeof window.wp.svgPainter === 'undefined') {
                window.wp.svgPainter = {
                    init: function() {
                        // Stub function - will be replaced by actual svg-painter
                        // This prevents errors when svg-painter.js tries to call wp.svgPainter.init()
                    },
                    setColors: function() {},
                    findElements: function() {},
                    paint: function() {},
                    paintElement: function() {}
                };
            }
        })();
        </script>
        <?php
    }

    /**
     * Run Rake migrations
     * 
     * @throws \Exception If migration kernel initialization or execution fails
     */
    private function runRakeMigrations(): void {
        global $wpdb;
        
        // Suppress duplicate key/constraint warnings during Rake migrations
        // These are harmless warnings when indexes/constraints already exist
        $wpdb->hide_errors();
        
        try {
            // Khởi tạo kernel migration
            $kernel = new \CrawlFlow\Kernel\CrawlFlowMigrationKernel();
            $kernel->initializeMigration();

            // Thực thi migration qua kernel
            $result = $kernel->runMigrations();

            if (!($result['success'] ?? false)) {
                $errorMessage = $result['error'] ?? 'Unknown migration error';
                throw new \RuntimeException('CrawlFlow: Rake migrations failed - ' . $errorMessage);
            }

            \Rake\Facade\Logger::info('CrawlFlow: Rake migrations completed successfully');
        } finally {
            // Always re-enable error reporting
            $wpdb->show_errors();
        }
    }

    /**
     * Run CrawlFlow custom migrations
     * 
     * Runs custom migrations specific to CrawlFlow plugin
     * Note: Most migrations have been moved to Rake framework schema definitions
     */
    private function runCrawlFlowMigrations(): void {
        // Load and run project status tracking migration
        require_once CRAWLFLOW_PLUGIN_DIR . 'database/migrations/add-project-status-tracking.php';
        
        if (function_exists('crawlflow_run_project_status_migration')) {
            crawlflow_run_project_status_migration();
            \Rake\Facade\Logger::info('CrawlFlow: Project status tracking migration completed');
        }
        
        \Rake\Facade\Logger::info('CrawlFlow: Custom migrations handled by Rake framework schema definitions');
    }

    /**
     * Initialize database status checker
     */
    private function initializeDatabaseStatusChecker()
    {
        try {
            $dbChecker = new \CrawlFlow\Helper\DatabaseStatusChecker();
            $dbChecker->registerHooks();
            
            // Log initial status
            $status = $dbChecker->checkDatabaseStatus();
            if (!$status['healthy']) {
                \Rake\Facade\Logger::warning('CrawlFlow: Database issues detected - ' . $status['migration_count'] . ' migrations needed');
            }
        } catch (\Exception $e) {
            \Rake\Facade\Logger::error('CrawlFlow: Failed to initialize database status checker - ' . $e->getMessage());
        }
    }
}

// Initialize plugin
$crawlFlow = WP_CrawlFlow::getInstance();
