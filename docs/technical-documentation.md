# TÀI LIỆU THIẾT KẾ KỸ THUẬT CRAWFLOW PLUGIN
**Phiên bản:** 1.0
**Ngày tạo:** 2025
**Tác giả:** Development Team

---

## MỤC LỤC

1. [Tổng quan CrawlFlow Plugin](#1-tổng-quan-crawlflow-plugin)
2. [Kiến trúc Plugin](#2-kiến-trúc-plugin)
3. [Dashboard System](#3-dashboard-system)
4. [Project Management](#4-project-management)
5. [Migration Integration](#5-migration-integration)
6. [Logging System](#6-logging-system)
7. [Frontend Assets](#7-frontend-assets)
8. [Development Guidelines](#8-development-guidelines)

---

## 1. TỔNG QUAN CRAWFLOW PLUGIN

### 1.1 Mục tiêu
CrawlFlow Plugin là WordPress plugin sử dụng Rake Framework, cung cấp:
- Dashboard quản lý projects
- Visual flow composer cho database schemas
- Migration system tích hợp
- Logging và analytics
- WordPress admin integration

### 1.2 Kiến trúc tổng thể
```
┌─────────────────────────────────────────────────────────────┐
│                    CRAWFLOW PLUGIN                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐ │
│  │   DASHBOARD     │  │    PROJECTS     │  │  MIGRATION  │ │
│  │     SYSTEM      │  │   MANAGEMENT    │  │ INTEGRATION │ │
│  │                 │  │                 │  │             │ │
│  │ • Overview      │  │ • Project CRUD  │  │ • Schema    │ │
│  │ • Analytics     │  │ • Flow Composer │  │   Migration │ │
│  │ • Settings      │  │ • Visual Editor │  │ • Status    │ │
│  │ • System Info   │  │ • Data Preview  │  │ • History   │ │
│  └─────────────────┘  └─────────────────┘  └─────────────┘ │
│                                                             │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐ │
│  │    LOGGING      │  │   FRONTEND      │  │   ASSETS    │ │
│  │     SYSTEM      │  │    ASSETS       │  │ MANAGEMENT  │ │
│  │                 │  │                 │  │             │ │
│  │ • Log Viewer    │  │ • React Composer│  │ • CSS/JS    │ │
│  │ • Log Filter    │  │ • XYFlow React  │  │ • CDN       │ │
│  │ • Log Export    │  │ • Admin Styles  │  │ • Build     │ │
│  │ • Analytics     │  │ • Responsive    │  │ • Minify    │ │
│  └─────────────────┘  └─────────────────┘  └─────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. KIẾN TRÚC PLUGIN

### 2.1 Package Structure
```
wp-crawlflow/
├── src/
│   ├── Admin/                 # Admin Controllers & Services
│   │   ├── CrawlFlowController.php
│   │   ├── DashboardService.php
│   │   ├── ProjectService.php
│   │   ├── LogService.php
│   │   ├── MigrationService.php
│   │   └── DashboardRenderer.php
│   ├── Kernel/                # Plugin Kernels
│   │   ├── CrawlFlowDashboardKernel.php
│   │   ├── CrawlFlowMigrationKernel.php
│   │   └── CrawlFlowConsoleKernel.php
│   ├── Bootstrapper/          # Plugin Bootstrappers
│   │   ├── CrawlFlowDashboardBootstrapper.php
│   │   ├── CrawlFlowMigrationBootstrapper.php
│   │   └── CrawlFlowCoreBootstrapper.php
│   ├── ServiceProvider/       # Service Providers
│   │   ├── CrawlFlowDashboardServiceProvider.php
│   │   ├── CrawlFlowMigrationServiceProvider.php
│   │   └── CrawlFlowCoreServiceProvider.php
│   └── Assets/                # Asset Management
│       ├── AssetManager.php
│       └── ScriptManager.php
├── assets/
│   ├── css/                   # Stylesheets
│   │   ├── admin.css
│   │   └── composer.css
│   └── js/                    # JavaScript
│       ├── admin.js
│       ├── composer-simple.js
│       └── composer-test.js
├── wp-crawlflow.php           # Main plugin file
├── composer.json
└── README.md
```

### 2.2 Package Dependencies
```json
{
    "name": "crawlflow/wp-crawlflow",
    "require": {
        "php": ">=8.1",
        "crawlflow/rake-wordpress-adapter": "^1.0",
        "monolog/monolog": "^3.0"
    },
    "autoload": {
        "psr-4": {
            "CrawlFlow\\": "src/"
        }
    }
}
```

---

## 3. DASHBOARD SYSTEM

### 3.1 Main Plugin File
```php
class WP_CrawlFlow
{
    private static ?self $instance = null;
    private Rake $app;
    private CrawlFlowDashboardKernel $dashboardKernel;
    private CrawlFlowMigrationKernel $migrationKernel;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->app = new Rake();
        $this->initializeKernels();
        $this->registerHooks();
    }

    private function initializeKernels(): void
    {
        $this->dashboardKernel = new CrawlFlowDashboardKernel($this->app);
        $this->migrationKernel = new CrawlFlowMigrationKernel($this->app);
    }

    private function registerHooks(): void
    {
        \add_action('plugins_loaded', [$this, 'initializePlugin']);
        \add_action('init', [$this, 'initialize']);
        \register_activation_hook(__FILE__, [$this, 'activate']);
        \register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }

    public function initializePlugin(): void
    {
        $this->dashboardKernel->bootstrap();
        $this->migrationKernel->bootstrap();
    }

    public function activate(): void
    {
        $this->migrationKernel->runMigrations();
        \flush_rewrite_rules();
    }

    public function deactivate(): void
    {
        \flush_rewrite_rules();
    }
}

// Initialize plugin
WP_CrawlFlow::getInstance();
```

### 3.2 Dashboard Kernel
```php
class CrawlFlowDashboardKernel extends AbstractKernel
{
    private DashboardService $dashboardService;
    private CrawlFlowController $controller;
    private ?string $currentScreen = null;
    private array $screenData = [];

    public function __construct(Rake $app)
    {
        parent::__construct($app);
        $this->dashboardService = new DashboardService();
        $this->controller = new CrawlFlowController($app);
        $this->detectCurrentScreen();
        $this->loadScreenData();
    }

    public function getBootstrappers(): array
    {
        return [
            CrawlFlowDashboardBootstrapper::class,
            CrawlFlowMigrationBootstrapper::class,
        ];
    }

    protected function getConfig(): array
    {
        return [
            'plugin_path' => \plugin_dir_path(__FILE__),
            'plugin_url' => \plugin_dir_url(__FILE__),
            'version' => '1.0.0',
        ];
    }

    private function detectCurrentScreen(): void
    {
        $screen = \get_current_screen();
        $this->currentScreen = $screen ? $screen->id : null;
    }

    private function loadScreenData(): void
    {
        if ($this->currentScreen) {
            $this->screenData = $this->dashboardService->getScreenData($this->currentScreen);
        }
    }

    public function render(): void
    {
        $this->controller->renderPage();
    }
}
```

### 3.3 Dashboard Service
```php
class DashboardService
{
    private MigrationService $migrationService;
    private ProjectService $projectService;
    private LogService $logService;

    public function __construct()
    {
        $this->migrationService = new MigrationService();
        $this->projectService = new ProjectService();
        $this->logService = new LogService();
    }

    public function getScreenData(string $screenId): array
    {
        switch ($screenId) {
            case 'toplevel_page_crawlflow':
                return $this->getDashboardData();
            case 'crawlflow_page_crawlflow-projects':
                return $this->getProjectsData();
            case 'crawlflow_page_crawlflow-logs':
                return $this->getLogsData();
            default:
                return [];
        }
    }

    private function getDashboardData(): array
    {
        return [
            'projects' => $this->projectService->getRecentProjects(),
            'migration_status' => $this->migrationService->getMigrationStatus(),
            'system_info' => $this->getSystemInfo(),
            'settings' => $this->getSettings(),
            'migration_history' => $this->migrationService->getMigrationHistory(),
        ];
    }

    private function getProjectsData(): array
    {
        return [
            'projects' => $this->projectService->getProjects(),
            'available_tooths' => $this->projectService->getAvailableTooths(),
        ];
    }

    private function getLogsData(): array
    {
        return [
            'logs' => $this->logService->getLogs(),
            'log_stats' => $this->logService->getLogStats(),
        ];
    }

    private function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'wordpress_version' => \get_bloginfo('version'),
            'plugin_version' => '1.0.0',
            'memory_limit' => \ini_get('memory_limit'),
            'max_execution_time' => \ini_get('max_execution_time'),
        ];
    }

    private function getSettings(): array
    {
        return [
            'debug_mode' => \defined('WP_DEBUG') && WP_DEBUG,
            'log_level' => 'info',
            'auto_migration' => true,
        ];
    }
}
```

---

## 4. PROJECT MANAGEMENT

### 4.1 Project Service
```php
class ProjectService
{
    private DatabaseAdapterInterface $db;

    public function __construct()
    {
        $this->db = new WordPressDatabaseAdapter();
    }

    public function getProjects(): array
    {
        $sql = "SELECT * FROM {$this->db->getPrefix()}crawlflow_projects ORDER BY created_at DESC";
        return $this->db->getResults($sql);
    }

    public function getRecentProjects(int $limit = 5): array
    {
        $sql = "SELECT * FROM {$this->db->getPrefix()}crawlflow_projects ORDER BY created_at DESC LIMIT {$limit}";
        return $this->db->getResults($sql);
    }

    public function getProject(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->db->getPrefix()}crawlflow_projects WHERE id = {$id}";
        return $this->db->getRow($sql);
    }

    public function createProject(array $data): int
    {
        $data['created_at'] = \current_time('mysql');
        $data['updated_at'] = \current_time('mysql');

        return $this->db->insert('crawlflow_projects', $data);
    }

    public function updateProject(int $id, array $data): bool
    {
        $data['updated_at'] = \current_time('mysql');

        $affected = $this->db->update('crawlflow_projects', $data, ['id' => $id]);
        return $affected > 0;
    }

    public function deleteProject(int $id): bool
    {
        $affected = $this->db->delete('crawlflow_projects', ['id' => $id]);
        return $affected > 0;
    }

    public function getAvailableTooths(): array
    {
        return [
            ['id' => 'mysql', 'name' => 'MySQL Database'],
            ['id' => 'postgresql', 'name' => 'PostgreSQL Database'],
            ['id' => 'mongodb', 'name' => 'MongoDB Database'],
            ['id' => 'redis', 'name' => 'Redis Cache'],
            ['id' => 'elasticsearch', 'name' => 'Elasticsearch'],
            ['id' => 'api', 'name' => 'REST API'],
            ['id' => 'file', 'name' => 'File System'],
        ];
    }
}
```

### 4.2 Project Controller
```php
class CrawlFlowController
{
    private Rake $app;
    private DashboardService $dashboardService;
    private ProjectService $projectService;
    private LogService $logService;
    private MigrationService $migrationService;
    private DashboardRenderer $renderer;

    public function __construct(Rake $app)
    {
        $this->app = $app;
        $this->dashboardService = new DashboardService();
        $this->projectService = new ProjectService();
        $this->logService = new LogService();
        $this->migrationService = new MigrationService($app);
        $this->renderer = new DashboardRenderer();
    }

    public function registerHooks(): void
    {
        \add_action('admin_menu', [$this, 'registerMenu']);
        \add_action('wp_ajax_crawlflow_save_project', [$this, 'handleSaveProject']);
        \add_action('wp_ajax_crawlflow_delete_project', [$this, 'handleDeleteProject']);
        \add_action('wp_ajax_crawlflow_auto_save_project', [$this, 'handleAutoSaveProject']);
        \add_action('admin_post_crawlflow_run_migration', [$this, 'handleRunMigration']);
        \add_action('admin_post_crawlflow_clear_logs', [$this, 'handleClearLogsAction']);
        \add_action('admin_post_crawlflow_export_data', [$this, 'handleExportDataAction']);
        \add_action('admin_post_crawlflow_save_settings', [$this, 'handleSaveSettings']);
    }

    public function registerMenu(): void
    {
        \add_menu_page(
            'CrawlFlow',
            'CrawlFlow',
            'manage_options',
            'crawlflow',
            [$this, 'renderPage'],
            'dashicons-networking',
            30
        );

        \add_submenu_page(
            'crawlflow',
            'Projects',
            'Projects',
            'manage_options',
            'crawlflow-projects',
            [$this, 'renderProjectsPage']
        );

        \add_submenu_page(
            'crawlflow',
            'Logs',
            'Logs',
            'manage_options',
            'crawlflow-logs',
            [$this, 'renderLogsPage']
        );
    }

    public function renderPage(): void
    {
        $screen = \get_current_screen();
        $this->renderer->renderDashboardOverview($this->dashboardService->getScreenData($screen->id));
    }

    public function renderProjectsPage(): void
    {
        $sub = $_GET['sub'] ?? 'list';

        if ($sub === 'compose') {
            $this->renderer->renderProjectCompose($this->dashboardService->getScreenData('crawlflow_page_crawlflow-projects'));
        } else {
            $this->renderer->renderProjectsList($this->dashboardService->getScreenData('crawlflow_page_crawlflow-projects'));
        }
    }

    public function handleSaveProject(): void
    {
        \check_ajax_referer('crawlflow_nonce', 'nonce');

        if (!\current_user_can('manage_options')) {
            \wp_send_json_error('Insufficient permissions');
        }

        $projectData = [
            'name' => \sanitize_text_field($_POST['name'] ?? ''),
            'description' => \sanitize_textarea_field($_POST['description'] ?? ''),
            'config' => \sanitize_textarea_field($_POST['config'] ?? ''),
            'status' => \sanitize_text_field($_POST['status'] ?? 'active'),
        ];

        try {
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                $id = (int) $_POST['id'];
                $success = $this->projectService->updateProject($id, $projectData);
                $message = 'Project updated successfully';
            } else {
                $id = $this->projectService->createProject($projectData);
                $success = $id > 0;
                $message = 'Project created successfully';
            }

            if ($success) {
                \wp_send_json_success(['message' => $message, 'id' => $id]);
            } else {
                \wp_send_json_error('Failed to save project');
            }
        } catch (Exception $e) {
            \wp_send_json_error('Error: ' . $e->getMessage());
        }
    }

    public function handleDeleteProject(): void
    {
        \check_ajax_referer('crawlflow_nonce', 'nonce');

        if (!\current_user_can('manage_options')) {
            \wp_send_json_error('Insufficient permissions');
        }

        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            \wp_send_json_error('Invalid project ID');
        }

        try {
            $success = $this->projectService->deleteProject($id);

            if ($success) {
                \wp_send_json_success('Project deleted successfully');
            } else {
                \wp_send_json_error('Failed to delete project');
            }
        } catch (Exception $e) {
            \wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
}
```

---

## 5. MIGRATION INTEGRATION

### 5.1 Migration Service
```php
class MigrationService
{
    private Rake $app;
    private DatabaseAdapterInterface $adapter;
    private LoggerInterface $logger;

    public function __construct(Rake $app)
    {
        $this->app = $app;
        $this->adapter = new WordPressDatabaseAdapter();
        $this->logger = \Rake\Facade\Logger::getLogger();
    }

    public function getMigrationStatus(): array
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM {$this->adapter->getPrefix()}rake_migrations";
            $result = $this->adapter->getVar($sql);

            return [
                'total_migrations' => (int) $result,
                'last_migration' => $this->getLastMigration(),
                'status' => 'ready',
            ];
        } catch (Exception $e) {
            return [
                'total_migrations' => 0,
                'last_migration' => null,
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getMigrationHistory(): array
    {
        try {
            $sql = "SELECT * FROM {$this->adapter->getPrefix()}rake_migrations ORDER BY executed_at DESC LIMIT 10";
            return $this->adapter->getResults($sql);
        } catch (Exception $e) {
            return [];
        }
    }

    public function runMigrations(): array
    {
        try {
            $this->logger->info('Starting migrations');

            $migrationKernel = new CrawlFlowMigrationKernel($this->app);
            $migrationKernel->runMigrations();

            $this->logger->info('Migrations completed successfully');

            return [
                'success' => true,
                'message' => 'Migrations completed successfully',
            ];
        } catch (Exception $e) {
            $this->logger->error('Migration failed: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Migration failed: ' . $e->getMessage(),
            ];
        }
    }

    private function getLastMigration(): ?array
    {
        try {
            $sql = "SELECT * FROM {$this->adapter->getPrefix()}rake_migrations ORDER BY executed_at DESC LIMIT 1";
            return $this->adapter->getRow($sql);
        } catch (Exception $e) {
            return null;
        }
    }
}
```

---

## 6. LOGGING SYSTEM

### 6.1 Log Service
```php
class LogService
{
    private DatabaseAdapterInterface $db;

    public function __construct()
    {
        $this->db = new WordPressDatabaseAdapter();
    }

    public function getLogs(int $limit = 100, int $offset = 0, array $filters = []): array
    {
        $sql = "SELECT * FROM {$this->db->getPrefix()}crawlflow_logs";

        $whereConditions = [];
        if (!empty($filters['level'])) {
            $level = $this->db->escape($filters['level']);
            $whereConditions[] = "level = '{$level}'";
        }

        if (!empty($filters['date_from'])) {
            $dateFrom = $this->db->escape($filters['date_from']);
            $whereConditions[] = "created_at >= '{$dateFrom}'";
        }

        if (!empty($filters['date_to'])) {
            $dateTo = $this->db->escape($filters['date_to']);
            $whereConditions[] = "created_at <= '{$dateTo}'";
        }

        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }

        $sql .= " ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";

        return $this->db->getResults($sql);
    }

    public function getLogStats(): array
    {
        $sql = "SELECT
                    level,
                    COUNT(*) as count,
                    MIN(created_at) as first_log,
                    MAX(created_at) as last_log
                FROM {$this->db->getPrefix()}crawlflow_logs
                GROUP BY level";

        $results = $this->db->getResults($sql);

        $stats = [];
        foreach ($results as $result) {
            $stats[$result['level']] = [
                'count' => (int) $result['count'],
                'first_log' => $result['first_log'],
                'last_log' => $result['last_log'],
            ];
        }

        return $stats;
    }

    public function clearLogs(): bool
    {
        $sql = "DELETE FROM {$this->db->getPrefix()}crawlflow_logs";
        return $this->db->query($sql);
    }

    public function exportLogs(string $format = 'json'): string
    {
        $logs = $this->getLogs(1000, 0);

        switch ($format) {
            case 'json':
                return json_encode($logs, JSON_PRETTY_PRINT);
            case 'csv':
                return $this->exportToCsv($logs);
            default:
                return json_encode($logs);
        }
    }

    private function exportToCsv(array $logs): string
    {
        if (empty($logs)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Write headers
        fputcsv($output, array_keys($logs[0]));

        // Write data
        foreach ($logs as $log) {
            fputcsv($output, $log);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
```

---

## 7. FRONTEND ASSETS

### 7.1 Asset Manager
```php
class AssetManager
{
    private string $pluginUrl;
    private string $pluginPath;

    public function __construct()
    {
        $this->pluginUrl = \plugin_dir_url(__FILE__);
        $this->pluginPath = \plugin_dir_path(__FILE__);
    }

    public function enqueueAdminAssets(): void
    {
        \add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueueAdminStyles']);
    }

    public function enqueueAdminScripts(): void
    {
        $screen = \get_current_screen();

        if (!$screen || !strpos($screen->id, 'crawlflow')) {
            return;
        }

        // Enqueue jQuery for AJAX
        \wp_enqueue_script('jquery');

        // Enqueue admin script
        \wp_enqueue_script(
            'crawlflow-admin',
            $this->pluginUrl . 'assets/js/admin.js',
            ['jquery'],
            '1.0.0',
            true
        );

        // Localize script
        \wp_localize_script('crawlflow-admin', 'crawlflowAdmin', [
            'ajaxUrl' => \admin_url('admin-ajax.php'),
            'adminUrl' => \admin_url(),
            'nonce' => \wp_create_nonce('crawlflow_nonce'),
            'strings' => [
                'confirmDelete' => 'Are you sure you want to delete this project?',
                'saving' => 'Saving...',
                'saved' => 'Saved successfully',
                'error' => 'An error occurred',
            ],
        ]);

        // Enqueue React and XYFlow for composer
        if ($screen->id === 'crawlflow_page_crawlflow-projects' && isset($_GET['sub']) && $_GET['sub'] === 'compose') {
            $this->enqueueComposerAssets();
        }
    }

    public function enqueueAdminStyles(): void
    {
        $screen = \get_current_screen();

        if (!$screen || !strpos($screen->id, 'crawlflow')) {
            return;
        }

        \wp_enqueue_style(
            'crawlflow-admin',
            $this->pluginUrl . 'assets/css/admin.css',
            [],
            '1.0.0'
        );

        // Enqueue composer styles
        if ($screen->id === 'crawlflow_page_crawlflow-projects' && isset($_GET['sub']) && $_GET['sub'] === 'compose') {
            \wp_enqueue_style(
                'crawlflow-composer',
                $this->pluginUrl . 'assets/css/composer.css',
                [],
                '1.0.0'
            );
        }
    }

    private function enqueueComposerAssets(): void
    {
        // React CDN
        \wp_enqueue_script(
            'react',
            'https://unpkg.com/react@18/umd/react.production.min.js',
            [],
            '18.0.0',
            true
        );

        \wp_enqueue_script(
            'react-dom',
            'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js',
            ['react'],
            '18.0.0',
            true
        );

        // XYFlow CDN
        \wp_enqueue_script(
            'xyflow',
            'https://cdn.jsdelivr.net/npm/@xyflow/react@12.8.2/dist/index.umd.js',
            ['react', 'react-dom'],
            '12.8.2',
            true
        );

        // Composer script
        \wp_enqueue_script(
            'crawlflow-composer',
            $this->pluginUrl . 'assets/js/composer-simple.js',
            ['react', 'react-dom', 'xyflow'],
            '1.0.0',
            true
        );
    }
}
```

### 7.2 Composer JavaScript
```javascript
// assets/js/composer-simple.js
(function() {
    'use strict';

    const { useState, useCallback, createElement } = React;
    const { ReactFlow, Background, Controls, MiniMap } = window.ReactFlow;

    const SCHEMA_DEFINITIONS = {
        mysql: {
            name: 'MySQL Database',
            tables: ['users', 'posts', 'comments', 'categories'],
            fields: ['id', 'name', 'email', 'created_at', 'updated_at']
        },
        postgresql: {
            name: 'PostgreSQL Database',
            tables: ['users', 'posts', 'comments', 'categories'],
            fields: ['id', 'name', 'email', 'created_at', 'updated_at']
        },
        mongodb: {
            name: 'MongoDB Database',
            collections: ['users', 'posts', 'comments'],
            fields: ['_id', 'name', 'email', 'createdAt', 'updatedAt']
        },
        redis: {
            name: 'Redis Cache',
            keys: ['session', 'cache', 'queue'],
            dataTypes: ['string', 'hash', 'list', 'set', 'zset']
        },
        elasticsearch: {
            name: 'Elasticsearch',
            indices: ['users', 'posts', 'comments'],
            fields: ['id', 'title', 'content', 'created_at']
        },
        api: {
            name: 'REST API',
            endpoints: ['GET', 'POST', 'PUT', 'DELETE'],
            methods: ['users', 'posts', 'comments']
        },
        file: {
            name: 'File System',
            directories: ['uploads', 'logs', 'cache'],
            fileTypes: ['txt', 'json', 'xml', 'csv']
        }
    };

    function ProjectComposer() {
        const [projectName, setProjectName] = useState('');
        const [projectDescription, setProjectDescription] = useState('');
        const [selectedTooth, setSelectedTooth] = useState('');
        const [nodes, setNodes] = useState([]);
        const [edges, setEdges] = useState([]);
        const [isSaving, setIsSaving] = useState(false);
        const [saveStatus, setSaveStatus] = useState('');

        const generateSampleData = useCallback((toothType) => {
            const definition = SCHEMA_DEFINITIONS[toothType];
            if (!definition) return [];

            const sampleNodes = [];
            const sampleEdges = [];

            if (definition.tables) {
                definition.tables.forEach((table, index) => {
                    sampleNodes.push({
                        id: `table-${index}`,
                        type: 'default',
                        position: { x: 100 + (index * 200), y: 100 },
                        data: {
                            label: table,
                            type: 'table',
                            fields: definition.fields
                        }
                    });
                });
            }

            if (definition.collections) {
                definition.collections.forEach((collection, index) => {
                    sampleNodes.push({
                        id: `collection-${index}`,
                        type: 'default',
                        position: { x: 100 + (index * 200), y: 100 },
                        data: {
                            label: collection,
                            type: 'collection',
                            fields: definition.fields
                        }
                    });
                });
            }

            setNodes(sampleNodes);
            setEdges(sampleEdges);
        }, []);

        const handleToothChange = useCallback((toothType) => {
            setSelectedTooth(toothType);
            generateSampleData(toothType);
        }, [generateSampleData]);

        const handleSaveProject = useCallback(async () => {
            if (!projectName.trim()) {
                setSaveStatus('Project name is required');
                return;
            }

            setIsSaving(true);
            setSaveStatus('Saving...');

            try {
                const response = await fetch(crawlflowAdmin.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'crawlflow_save_project',
                        nonce: crawlflowAdmin.nonce,
                        name: projectName,
                        description: projectDescription,
                        config: JSON.stringify({
                            tooth: selectedTooth,
                            nodes: nodes,
                            edges: edges
                        }),
                        status: 'active'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    setSaveStatus('Project saved successfully!');
                    setTimeout(() => {
                        window.location.href = crawlflowAdmin.adminUrl + 'admin.php?page=crawlflow-projects';
                    }, 2000);
                } else {
                    setSaveStatus('Error: ' + (result.data || 'Unknown error'));
                }
            } catch (error) {
                setSaveStatus('Error: ' + error.message);
            } finally {
                setIsSaving(false);
            }
        }, [projectName, projectDescription, selectedTooth, nodes, edges]);

        return createElement('div', { className: 'crawlflow-project-composer' },
            createElement('div', { className: 'crawlflow-project-compose-header' },
                createElement('h1', null, 'Create New Project'),
                createElement('p', null, 'Design your data flow using the visual composer')
            ),

            createElement('div', { className: 'crawlflow-project-compose-form' },
                createElement('div', { className: 'form-group' },
                    createElement('label', { htmlFor: 'project-name' }, 'Project Name'),
                    createElement('input', {
                        type: 'text',
                        id: 'project-name',
                        value: projectName,
                        onChange: (e) => setProjectName(e.target.value),
                        placeholder: 'Enter project name'
                    })
                ),

                createElement('div', { className: 'form-group' },
                    createElement('label', { htmlFor: 'project-description' }, 'Description'),
                    createElement('textarea', {
                        id: 'project-description',
                        value: projectDescription,
                        onChange: (e) => setProjectDescription(e.target.value),
                        placeholder: 'Enter project description',
                        rows: 3
                    })
                ),

                createElement('div', { className: 'form-group' },
                    createElement('label', { htmlFor: 'tooth-type' }, 'Data Source Type'),
                    createElement('select', {
                        id: 'tooth-type',
                        value: selectedTooth,
                        onChange: (e) => handleToothChange(e.target.value)
                    },
                        createElement('option', { value: '' }, 'Select data source type'),
                        Object.entries(SCHEMA_DEFINITIONS).map(([key, def]) =>
                            createElement('option', { key, value: key }, def.name)
                        )
                    )
                ),

                createElement('div', { className: 'form-group' },
                    createElement('button', {
                        onClick: handleSaveProject,
                        disabled: isSaving || !projectName.trim(),
                        className: 'button button-primary'
                    }, isSaving ? 'Saving...' : 'Save Project')
                ),

                saveStatus && createElement('div', {
                    className: 'save-status ' + (saveStatus.includes('Error') ? 'error' : 'success')
                }, saveStatus)
            ),

            selectedTooth && createElement('div', { className: 'crawlflow-project-compose-flow' },
                createElement(ReactFlow, {
                    nodes: nodes,
                    edges: edges,
                    onNodesChange: (changes) => {
                        setNodes((nds) => applyNodeChanges(changes, nds));
                    },
                    onEdgesChange: (changes) => {
                        setEdges((eds) => applyEdgeChanges(changes, eds));
                    },
                    fitView: true
                },
                    createElement(Background),
                    createElement(Controls),
                    createElement(MiniMap)
                )
            )
        );
    }

    function initComposer() {
        const container = document.getElementById('crawlflow-project-composer');
        if (container) {
            const root = ReactDOM.createRoot(container);
            root.render(createElement(ProjectComposer));
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initComposer);
    } else {
        initComposer();
    }
})();
```

---

## 8. DEVELOPMENT GUIDELINES

### 8.1 Coding Standards
- **WordPress Coding Standards**: Follow WordPress coding standards
- **PSR-12 Compliance**: Adhere to PSR-12 for non-WordPress specific code
- **Type Declarations**: Use strict types and type hints
- **Documentation**: PHPDoc required for all public methods

### 8.2 Plugin Development Best Practices
```php
// Always use WordPress functions with backslash prefix
$result = \wp_verify_nonce($nonce, $action);

// Use WordPress security functions
$sanitized = \sanitize_text_field($input);

// Check capabilities before actions
if (\current_user_can('manage_options')) {
    // Perform admin action
}

// Use WordPress hooks properly
\add_action('init', [$this, 'initialize']);
```

### 8.3 Testing Guidelines
```php
class CrawlFlowPluginTest extends TestCase
{
    public function testProjectService(): void
    {
        // Arrange
        $service = new ProjectService();

        // Act
        $projects = $service->getProjects();

        // Assert
        $this->assertIsArray($projects);
    }

    public function testMigrationService(): void
    {
        // Arrange
        $app = new Rake();
        $service = new MigrationService($app);

        // Act
        $status = $service->getMigrationStatus();

        // Assert
        $this->assertIsArray($status);
        $this->assertArrayHasKey('total_migrations', $status);
    }
}
```

### 8.4 Error Handling
```php
class CrawlFlowException extends Exception
{
    public function __construct(string $message, array $context = [], int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct("CrawlFlow error: {$message}", $code, $previous);
    }
}

// Usage
try {
    $projectService = new ProjectService();
    $result = $projectService->createProject($data);
} catch (CrawlFlowException $e) {
    Logger::error('Project creation failed: ' . $e->getMessage());
}
```

---

## KẾT LUẬN

CrawlFlow Plugin cung cấp hệ thống quản lý projects với visual flow composer:

### Điểm nổi bật:
1. **Dashboard System**: Overview, analytics, settings
2. **Project Management**: CRUD operations với visual composer
3. **Migration Integration**: Tích hợp với Rake migration system
4. **Logging System**: Log viewer và analytics
5. **Frontend Assets**: React-based visual composer với XYFlow

### Sử dụng:
```php
// Initialize plugin
WP_CrawlFlow::getInstance();

// Use services
$projectService = new ProjectService();
$projects = $projectService->getProjects();

$migrationService = new MigrationService($app);
$status = $migrationService->getMigrationStatus();
```

---

**Tài liệu này sẽ được cập nhật thường xuyên khi có thay đổi trong plugin.**