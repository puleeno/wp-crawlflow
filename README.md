# WP-CRAWFLOW PLUGIN
**Phiên bản:** 1.0
**Ngày tạo:** 2025
**Tác giả:** Development Team

---

## 📋 MỤC LỤC

1. [Tổng quan WP-CrawlFlow](#tổng-quan-wp-crawlflow)
2. [Mục đích và ý nghĩa](#mục-đích-và-ý-nghĩa)
3. [Tại sao cần dùng WP-CrawlFlow](#tại-sao-cần-dùng-wp-crawlflow)
4. [Mối quan hệ với Rake Ecosystem](#mối-quan-hệ-với-rake-ecosystem)
5. [Kiến trúc Plugin](#kiến-trúc-plugin)
6. [Cách sử dụng](#cách-sử-dụng)
7. [Tài liệu kỹ thuật](#tài-liệu-kỹ-thuật)
8. [Development Guidelines](#development-guidelines)

---

## 🎯 TỔNG QUAN WP-CRAWFLOW

### Mục tiêu
WP-CrawlFlow là WordPress plugin mạnh mẽ cho **data migration** và **web crawling** sử dụng Rake 2.0 framework, cung cấp:

- **Flow-based Architecture**: Kiến trúc dựa trên flow để xử lý dữ liệu
- **Database Migration System**: Hệ thống migration tự động với version tracking
- **Web Crawling Engine**: Engine crawl dữ liệu từ web
- **WordPress Integration**: Tích hợp hoàn hảo với WordPress admin và database
- **Visual Flow Composer**: Giao diện visual để tạo flow xử lý dữ liệu

### Vai trò trong hệ thống
```
┌─────────────────────────────────────────────────────────────┐
│                WP-CRAWFLOW PLUGIN                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐ │
│  │   DASHBOARD     │  │   MIGRATION     │  │    CRAWL    │ │
│  │     KERNEL      │  │     SYSTEM      │  │   ENGINE    │ │
│  │                 │  │                 │  │             │ │
│  │ • Screen Detect │  │ • Schema Update │  │ • URL Fetch │ │
│  │ • Data Loading  │  │ • Version Track │  │ • Data Parse│ │
│  │ • View Render   │  │ • Auto Migrate  │  │ • Store Data│ │
│  │ • Admin UI      │  │ • Rollback      │  │ • Queue Mgmt│ │
│  └─────────────────┘  └─────────────────┘  └─────────────┘ │
│                                                             │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐ │
│  │   FLOW COMPOSER │  │     LOGGER      │  │   PROJECT   │ │
│  │   (REACT)       │  │     SYSTEM      │  │  MANAGEMENT │ │
│  │                 │  │                 │  │             │ │
│  │ • Visual Editor │  │ • Lazy Loading  │  │ • CRUD Ops  │ │
│  │ • Flow Builder  │  │ • Daily Logs    │  │ • Settings  │ │
│  │ • Schema Design │  │ • Error Track   │  │ • Analytics │ │
│  │ • Data Preview  │  │ • CLI Support   │  │ • Export    │ │
│  └─────────────────┘  └─────────────────┘  └─────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

---

## 🎯 MỤC ĐÍCH VÀ Ý NGHĨA

### Mục đích chính
WP-CrawlFlow được thiết kế để giải quyết các vấn đề phức tạp trong **data processing** và **web crawling**:

1. **Data Migration Automation**
   - Tự động migrate database schema
   - Version tracking và rollback
   - WordPress prefix integration

2. **Web Crawling Engine**
   - Crawl dữ liệu từ websites
   - Parse và transform data
   - Store vào WordPress database

3. **Visual Flow Design**
   - Giao diện visual để thiết kế flow
   - Drag & drop interface
   - Real-time preview

4. **WordPress Integration**
   - Tích hợp hoàn hảo với WordPress admin
   - Sử dụng WordPress hooks và database
   - Security và permission handling

### Ý nghĩa trong hệ sinh thái
```
┌─────────────────────────────────────────────────────────────┐
│                    CRAWLFLOW ECOSYSTEM                     │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────┐    ┌─────────────────┐    ┌─────────┐ │
│  │   WP-CRAWFLOW   │    │  CRAWLFLOW CLI  │    │ CRAWLFLOW│ │
│  │    PLUGIN       │    │    TOOL         │    │  CORE   │ │
│  │                 │    │                 │    │         │ │
│  │ • WordPress UI  │    │ • Command Line  │    │ • Engine│ │
│  │ • Visual Editor │    │ • Batch Process │    │ • API   │ │
│  │ • Admin Panel   │    │ • Scripts       │    │ • Core  │ │
│  └─────────────────┘    └─────────────────┘    └─────────┘ │
│                                                             │
│  ┌─────────────────┐    ┌─────────────────┐    ┌─────────┐ │
│  │   CRAWLFLOW     │    │   CRAWLFLOW     │    │ CRAWLFLOW│ │
│  │   DASHBOARD     │    │   ANALYTICS     │    │  QUEUE  │ │
│  │                 │    │                 │    │         │ │
│  │ • Real-time     │    │ • Data Insights │    │ • Jobs  │ │
│  │ • Monitoring    │    │ • Reports       │    │ • Tasks │ │
│  │ • Alerts        │    │ • Charts        │    │ • Queue │ │
│  └─────────────────┘    └─────────────────┘    └─────────┘ │
└─────────────────────────────────────────────────────────────┘
```

---

## 🤔 TẠI SAO CẦN DÙNG WP-CRAWFLOW

### Vấn đề hiện tại
1. **Manual Data Processing**
   - Phải viết code thủ công cho mỗi website
   - Không có template hay pattern chung
   - Khó maintain và scale

2. **WordPress Limitations**
   - WordPress không có built-in crawling
   - Không có visual flow designer
   - Database migration phức tạp

3. **Development Overhead**
   - Phải build từ đầu cho mỗi project
   - Không có framework chung
   - Khó debug và monitor

### Giải pháp của WP-CrawlFlow

#### 1. **Flow-based Architecture**
```php
// Thay vì viết code thủ công
$data = file_get_contents($url);
$parsed = parseData($data);
saveToDatabase($parsed);

// Sử dụng visual flow composer
// Drag & drop các components
// Auto generate code
```

#### 2. **WordPress Integration**
```php
// Tích hợp hoàn hảo với WordPress
add_action('wp_ajax_crawlflow_save_project', [$this, 'handleSaveProject']);
add_action('admin_menu', [$this, 'registerMenu']);
add_action('wp_loaded', [$this, 'initialize']);
```

#### 3. **Visual Development**
```javascript
// React-based visual composer
const ProjectComposer = () => {
    const [nodes, setNodes] = useState([]);
    const [edges, setEdges] = useState([]);

    return (
        <ReactFlow
            nodes={nodes}
            edges={edges}
            onNodesChange={onNodesChange}
            onEdgesChange={onEdgesChange}
        />
    );
};
```

#### 4. **Automated Migration**
```php
// Tự động migrate database
$migrationService = new MigrationService();
$result = $migrationService->runMigrations();

// Version tracking
$version = $migrationService->getCurrentVersion();
```

---

## 🔗 MỐI QUAN HỆ VỚI RAKE ECOSYSTEM

### Dependency Chain
```
┌─────────────────┐    depends on    ┌─────────────────┐    depends on    ┌─────────────────┐
│   WP-CRAWFLOW   │ ────────────────▶ │ RAKE WORDPRESS  │ ────────────────▶ │   RAKE CORE     │
│     PLUGIN      │                  │    ADAPTER      │                  │   FRAMEWORK     │
└─────────────────┘                  └─────────────────┘                  └─────────────────┘
         │                                    │                                    │
         │ uses                               │ uses                               │ uses
         ▼                                    ▼                                    ▼
┌─────────────────┐                  ┌─────────────────┐                  ┌─────────────────┐
│   WORDPRESS     │                  │   WORDPRESS     │                  │   PHP 8.1+      │
│   ENVIRONMENT   │                  │   DATABASE      │                  │   COMPOSER      │
└─────────────────┘                  └─────────────────┘                  └─────────────────┘
```

### Package Dependencies
```json
{
    "name": "crawlflow/wp-crawlflow",
    "require": {
        "php": ">=8.1",
        "crawlflow/rake-core": "^1.0",
        "crawlflow/rake-wordpress-adapter": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "CrawlFlow\\": "src/"
        }
    }
}
```

### Service Integration
```php
// WP-CrawlFlow sử dụng Rake Core
use Rake\Rake;
use Rake\Facade\Logger;
use Rake\Manager\Database\MigrationManager;

// WP-CrawlFlow sử dụng Rake WordPress Adapter
use Rake\WordPress\Database\WordPressDatabaseAdapter;
use Rake\WordPress\Hooks\WordPressHooksAdapter;
use Rake\WordPress\Admin\WordPressAdminAdapter;

// Service registration
$app = new Rake();
$app->singleton(DatabaseAdapterInterface::class, WordPressDatabaseAdapter::class);
$app->singleton(WordPressHooksInterface::class, WordPressHooksAdapter::class);
$app->singleton(WordPressAdminInterface::class, WordPressAdminAdapter::class);
```

---

## 🏗️ KIẾN TRÚC PLUGIN

### Package Structure
```
wp-crawlflow/
├── src/
│   ├── Kernel/                     # Rake Kernel implementations
│   │   ├── CrawlFlowDashboardKernel.php
│   │   ├── CrawlFlowMigrationKernel.php
│   │   └── CrawlFlowConsoleKernel.php
│   ├── Admin/                      # WordPress Admin integration
│   │   ├── CrawlFlowController.php
│   │   ├── DashboardService.php
│   │   ├── ProjectService.php
│   │   ├── MigrationService.php
│   │   ├── LogService.php
│   │   └── DashboardRenderer.php
│   ├── Bootstrapper/               # Rake Bootstrapper implementations
│   │   ├── CrawlFlowDashboardBootstrapper.php
│   │   ├── CrawlFlowMigrationBootstrapper.php
│   │   └── CrawlFlowCoreBootstrapper.php
│   ├── ServiceProvider/            # Rake Service Provider implementations
│   │   ├── CrawlFlowDashboardServiceProvider.php
│   │   ├── CrawlFlowMigrationServiceProvider.php
│   │   └── CrawlFlowCoreServiceProvider.php
│   └── Logger/                     # Logging system
│       └── CrawlFlowLogger.php
├── assets/
│   ├── css/
│   │   ├── admin.css              # Admin styles
│   │   └── composer.css           # Flow composer styles
│   └── js/
│       ├── admin.js               # Admin JavaScript
│       └── composer-simple.js     # React flow composer
├── vendor/                         # Composer dependencies
├── wp-crawlflow.php               # Main plugin file
├── composer.json
└── README.md
```

### Architecture Flow
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   WORDPRESS     │    │   WP-CRAWFLOW   │    │   RAKE CORE     │
│   ADMIN         │    │     PLUGIN      │    │   FRAMEWORK     │
│                 │    │                 │    │                 │
│ • Menu Pages    │───▶│ • Dashboard     │───▶│ • Container     │
│ • AJAX Actions  │    │ • Migration     │    │ • Kernel        │
│ • Admin Scripts │    │ • Flow Composer │    │ • Services      │
│ • Admin Styles  │    │ • Logger        │    │ • Facades       │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   WORDPRESS     │    │   RAKE WORDPRESS│    │   PHP/COMPOSER  │
│   DATABASE      │    │    ADAPTER      │    │   ENVIRONMENT   │
│                 │    │                 │    │                 │
│ • wp_posts      │    │ • Database      │    │ • Autoloader    │
│ • wp_options    │    │ • Hooks         │    │ • Dependencies  │
│ • Custom Tables │    │ • Admin         │    │ • Extensions    │
│ • Migrations    │    │ • Security      │    │ • Configuration │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

---

## 🚀 CÁCH SỬ DỤNG

### 1. Cài đặt

#### WordPress Plugin Installation
```bash
# Upload to WordPress plugins directory
wp-content/plugins/wp-crawlflow/

# Activate plugin trong WordPress admin
# Plugin sẽ tự động run migrations
```

#### Composer Installation
```bash
composer require crawlflow/wp-crawlflow
```

### 2. Khởi tạo Plugin

```php
// Trong wp-crawlflow.php
class WP_CrawlFlow
{
    private Rake $app;
    private CrawlFlowController $controller;

    public function __construct()
    {
        // Initialize Rake container
        $this->app = new Rake();

        // Register service providers
        $this->app->register(new CrawlFlowCoreServiceProvider());
        $this->app->register(new CrawlFlowDashboardServiceProvider());
        $this->app->register(new CrawlFlowMigrationServiceProvider());

        // Initialize controller
        $this->controller = new CrawlFlowController($this->app);
        $this->controller->registerHooks();
    }
}
```

### 3. Sử dụng Dashboard

#### Access Dashboard
```
WordPress Admin → CrawlFlow → Projects
```

#### Create New Project
```php
// Sử dụng ProjectService
$projectService = new ProjectService();

$project = $projectService->createProject([
    'name' => 'My Crawl Project',
    'description' => 'Crawl data from website',
    'settings' => [
        'url' => 'https://example.com',
        'selectors' => ['h1', 'h2', '.content'],
        'output_format' => 'json'
    ]
]);
```

#### Visual Flow Composer
```javascript
// React-based flow composer
const ProjectComposer = () => {
    const [nodes, setNodes] = useState([
        {
            id: '1',
            type: 'input',
            data: { label: 'Start' },
            position: { x: 0, y: 0 }
        },
        {
            id: '2',
            type: 'crawl',
            data: { label: 'Crawl URL' },
            position: { x: 200, y: 0 }
        }
    ]);

    return (
        <ReactFlow
            nodes={nodes}
            edges={edges}
            onNodesChange={onNodesChange}
            onEdgesChange={onEdgesChange}
        />
    );
};
```

### 4. Database Migration

#### Automatic Migration
```php
// Plugin tự động run migrations khi activate
$migrationService = new MigrationService($app);
$result = $migrationService->runMigrations();

if ($result['success']) {
    Logger::info('Migrations completed successfully');
} else {
    Logger::error('Migration failed: ' . $result['error']);
}
```

#### Manual Migration
```php
// Run migrations manually
$kernel = new CrawlFlowMigrationKernel($app);
$kernel->runMigrations();

// Check migration status
$status = $kernel->checkMigrationStatus();
echo "Current version: " . $status['current_version'];
```

### 5. Logging System

#### Lazy Loading Logger
```php
use Rake\Facade\Logger;

// Logger chỉ được initialize khi cần
Logger::info('Starting crawl process');
Logger::error('Crawl failed', ['url' => $url, 'error' => $error]);
Logger::debug('Processing data', ['count' => count($data)]);
```

#### Log Files
```
wp-content/crawlflow/
├── crawlflow-2025-01-15.log
├── crawlflow-2025-01-16.log
└── crawlflow-2025-01-17.log
```

### 6. AJAX Operations

#### Save Project
```javascript
// JavaScript
jQuery.post(ajaxurl, {
    action: 'crawlflow_save_project',
    nonce: crawlflowAdmin.nonce,
    project: projectData
}, function(response) {
    if (response.success) {
        alert('Project saved successfully');
    }
});
```

```php
// PHP Handler
public function handleSaveProject()
{
    if (!wp_verify_nonce($_POST['nonce'], 'crawlflow_save_project')) {
        wp_die('Security check failed');
    }

    $projectService = new ProjectService();
    $result = $projectService->createProject($_POST['project']);

    if ($result) {
        wp_send_json_success(['id' => $result]);
    } else {
        wp_send_json_error('Failed to save project');
    }
}
```

### 7. Admin Menu Integration

```php
// Register admin menu
public function registerMenu()
{
    add_menu_page(
        'CrawlFlow',
        'CrawlFlow',
        'manage_options',
        'crawlflow',
        [$this, 'renderProjectsPage'],
        'dashicons-networking',
        30
    );

    add_submenu_page(
        'crawlflow',
        'Projects',
        'Projects',
        'manage_options',
        'crawlflow',
        [$this, 'renderProjectsPage']
    );

    add_submenu_page(
        'crawlflow',
        'Logs',
        'Logs',
        'manage_options',
        'crawlflow-logs',
        [$this, 'renderLogsPage']
    );
}
```

---

## 📚 TÀI LIỆU KỸ THUẬT

### Tài liệu chi tiết
📖 [`docs/technical-documentation.md`](docs/technical-documentation.md)

**Nội dung:**
- Flow-based Architecture
- Dashboard Kernel System
- Migration System
- Visual Flow Composer
- WordPress Integration
- Development Guidelines

### Code Examples

#### Dashboard Kernel
```php
class CrawlFlowDashboardKernel extends AbstractKernel
{
    private DashboardService $dashboardService;
    private CrawlFlowController $controller;

    public function __construct(Rake $app)
    {
        parent::__construct($app);

        $this->dashboardService = new DashboardService();
        $this->controller = new CrawlFlowController($app);

        $this->detectCurrentScreen();
        $this->loadScreenData();
    }

    public function render(): void
    {
        $this->controller->renderPage();
    }
}
```

#### Migration Service
```php
class MigrationService
{
    private Rake $app;
    private WordPressDatabaseAdapter $adapter;

    public function __construct(Rake $app)
    {
        $this->app = $app;
        $this->adapter = new WordPressDatabaseAdapter();
    }

    public function runMigrations(): array
    {
        try {
            $schemaPath = $this->app->get('migration_schema_path');
            $definitions = $this->getSchemaDefinitions($schemaPath);

            foreach ($definitions as $table => $schema) {
                $this->createTable($table, $schema);
            }

            return ['success' => true];
        } catch (Exception $e) {
            Logger::error('Migration failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
```

#### Project Service
```php
class ProjectService
{
    private WordPressDatabaseAdapter $adapter;

    public function createProject(array $data): int
    {
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');

        return $this->adapter->insert('crawlflow_projects', $data);
    }

    public function getProjects(): array
    {
        return $this->adapter->getResults("
            SELECT * FROM {$this->adapter->getPrefix()}crawlflow_projects
            ORDER BY created_at DESC
        ");
    }
}
```

---

## 🛠️ DEVELOPMENT GUIDELINES

### Coding Standards

#### WordPress Integration Best Practices
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

#### Rake Framework Integration
```php
// Use Rake Facades
use Rake\Facade\Logger;

Logger::info('Operation started');
Logger::error('Operation failed', ['context' => $data]);

// Use Rake Container
$app = new Rake();
$service = $app->make(ServiceInterface::class);

// Use Rake Database Adapter
$adapter = new WordPressDatabaseAdapter();
$result = $adapter->query('SELECT * FROM table');
```

### Testing Guidelines

#### Unit Testing
```php
class CrawlFlowControllerTest extends TestCase
{
    private CrawlFlowController $controller;

    protected function setUp(): void
    {
        $app = new Rake();
        $this->controller = new CrawlFlowController($app);
    }

    public function testSaveProject(): void
    {
        // Arrange
        $projectData = [
            'name' => 'Test Project',
            'description' => 'Test Description'
        ];

        // Act
        $result = $this->controller->handleSaveProject($projectData);

        // Assert
        $this->assertTrue($result['success']);
    }
}
```

#### Integration Testing
```php
class CrawlFlowIntegrationTest extends TestCase
{
    public function testDashboardRendering(): void
    {
        // Arrange
        $app = new Rake();
        $kernel = new CrawlFlowDashboardKernel($app);

        // Act
        ob_start();
        $kernel->render();
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('CrawlFlow', $output);
    }
}
```

### Error Handling
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
    $migrationService = new MigrationService($app);
    $result = $migrationService->runMigrations();
} catch (CrawlFlowException $e) {
    Logger::error('CrawlFlow operation failed: ' . $e->getMessage());
}
```

---

## 🔧 CONFIGURATION

### WordPress Settings
Plugin tự động sử dụng WordPress database settings:

```php
// Tự động detect từ WordPress
$adapter = new WordPressDatabaseAdapter();
echo $adapter->getPrefix();        // wp_
echo $adapter->getCharset();       // utf8mb4
echo $adapter->getCollation();     // utf8mb4_unicode_ci
```

### Plugin Configuration
```php
// Logger configuration
add_filter('crawlflow/logger', function($path) {
    return '/custom/path/to/logs/crawlflow.log';
});

// Migration configuration
add_filter('crawlflow/migration_schema_path', function($path) {
    return '/custom/path/to/schemas/';
});
```

---

## 🚨 TROUBLESHOOTING

### Common Issues

#### Error: `Class 'CrawlFlow\Admin\CrawlFlowController' not found`
**Solution:**
```bash
composer dump-autoload
```

#### Error: `WordPress not loaded`
**Solution:**
```php
// Ensure WordPress is loaded
require_once 'wp-load.php';
```

#### Error: `Database migration failed`
**Solution:**
```php
// Check database permissions
// Verify WordPress database configuration
// Check migration schema files
```

### Debug Mode
```php
// Enable debug mode
define('CRAWFLOW_DEBUG', true);

// Check logs
Logger::debug('Debug information');
Logger::error('Error information');
```

---

## 📊 PERFORMANCE

### Optimizations
- **Lazy loading**: Logger chỉ initialize khi cần
- **Database optimization**: Sử dụng WordPress database adapter
- **Memory management**: Efficient memory usage
- **Caching**: WordPress cache integration

### Best Practices
```php
// Use transactions for multiple operations
$adapter->beginTransaction();
try {
    foreach ($projects as $project) {
        $adapter->insert('crawlflow_projects', $project);
    }
    $adapter->commit();
} catch (Exception $e) {
    $adapter->rollback();
    throw $e;
}

// Use batch operations
$adapter->getResults("SELECT * FROM crawlflow_projects LIMIT 1000");

// Use specific columns
$adapter->getResults("SELECT id, name FROM crawlflow_projects WHERE status = 'active'");
```

---

## 🎯 KẾT LUẬN

WP-CrawlFlow cung cấp giải pháp hoàn chỉnh cho **data migration** và **web crawling** trong WordPress với:

### Điểm nổi bật:
1. **Flow-based Architecture**: Kiến trúc dựa trên flow để xử lý dữ liệu
2. **Visual Flow Composer**: Giao diện visual để thiết kế flow
3. **WordPress Integration**: Tích hợp hoàn hảo với WordPress
4. **Rake Framework**: Built trên Rake 2.0 framework
5. **Automated Migration**: Hệ thống migration tự động

### Sử dụng:
```php
// Initialize plugin
$plugin = new WP_CrawlFlow();

// Use dashboard
// WordPress Admin → CrawlFlow → Projects

// Use visual composer
// Projects → Add New → Visual Flow Composer

// Use migration
$migrationService = new MigrationService($app);
$result = $migrationService->runMigrations();
```

### Lợi ích:
- **Giảm development time**: Visual composer thay vì code thủ công
- **Tăng productivity**: Flow-based architecture
- **Dễ maintain**: WordPress integration
- **Scalable**: Rake framework foundation
- **User-friendly**: Visual interface

---

**Tài liệu này sẽ được cập nhật thường xuyên khi có thay đổi trong plugin.**