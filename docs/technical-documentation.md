# TÀI LIỆU KỸ THUẬT CRAWFLOW & RAKE FRAMEWORK
**Phiên bản:** 2.0
**Ngày tạo:** 2024
**Tác giả:** Development Team

---

## MỤC LỤC

1. [Tổng quan hệ thống](#1-tổng-quan-hệ-thống)
2. [Kiến trúc Rake Framework](#2-kiến-trúc-rake-framework)
3. [CrawlFlow Plugin Architecture](#3-crawlflow-plugin-architecture)
4. [Database Migration System](#4-database-migration-system)
5. [Logging System](#5-logging-system)
6. [Dependency Injection Container](#6-dependency-injection-container)
7. [Kernel & Bootstrapper System](#7-kernel--bootstrapper-system)
8. [Facade Pattern Implementation](#8-facade-pattern-implementation)
9. [WordPress Integration](#9-wordpress-integration)
10. [Performance Optimization](#10-performance-optimization)
11. [Error Handling & Debugging](#11-error-handling--debugging)
12. [Development Guidelines](#12-development-guidelines)
13. [API Reference](#13-api-reference)
14. [Deployment Guide](#14-deployment-guide)
15. [Troubleshooting](#15-troubleshooting)

---

## 1. TỔNG QUAN HỆ THỐNG

### 1.1 Mục tiêu dự án
CrawlFlow là một WordPress plugin được xây dựng trên Rake Framework 2.0, cung cấp hệ thống crawling và xử lý dữ liệu mạnh mẽ với các tính năng:
- Database migration tự động
- Logging system tích hợp
- Dependency injection container
- Modular architecture
- WordPress integration
- Performance optimization

### 1.2 Kiến trúc tổng thể
```
CrawlFlow Plugin
├── Rake Framework Core
│   ├── Container (Dependency Injection)
│   ├── Kernel System
│   ├── Bootstrapper System
│   ├── Facade Pattern
│   └── Manager Classes
├── WordPress Integration
│   ├── Database Adapter
│   ├── WordPress Driver
│   └── Prefix Handling
├── Migration System
│   ├── Migration Kernel
│   ├── Schema Definitions
│   ├── Backup Service
│   └── Validator Service
└── Logging System
    ├── Logger Manager
    ├── Monolog Integration
    └── Logger Facade
```

### 1.3 Công nghệ sử dụng
- **PHP 8.1+**: Ngôn ngữ chính
- **WordPress**: Platform hosting
- **Monolog**: Logging library
- **Composer**: Dependency management
- **PSR-4**: Autoloading standard
- **PSR-3**: Logger interface

---

## 2. KIẾN TRÚC RAKE FRAMEWORK

### 2.1 Core Container (Rake.php)
```php
class Rake
{
    private array $services = [];
    private array $singletons = [];
    private array $resolved = [];

    public function bind(string $abstract, $concrete): void
    public function singleton(string $abstract, $concrete): void
    public function make(string $abstract)
    public function has(string $abstract): bool
    public function resolve(string $abstract)
}
```

**Chức năng:**
- Dependency injection container
- Service registration và resolution
- Singleton pattern support
- Lazy loading implementation

### 2.2 Manager Classes
#### 2.2.1 LoggerManager
```php
class LoggerManager
{
    private ?LoggerInterface $logger = null;
    private array $config = [];

    public function getLogger(): LoggerInterface
    public function setConfig(array $config): void
    public function log(string $level, string $message, array $context = []): void
}
```

#### 2.2.2 DatabaseDriverManager
```php
class DatabaseDriverManager
{
    private array $drivers = [];
    private ?DatabaseDriverInterface $defaultDriver = null;

    public function registerDriver(string $name, DatabaseDriverInterface $driver): void
    public function getDriver(string $name = null): DatabaseDriverInterface
    public function setDefaultDriver(string $name): void
}
```

### 2.3 Bootstrapper System
```php
abstract class Bootstrapper
{
    abstract public function register(Rake $container): void;
    abstract public function boot(Rake $container): void;
}
```

**CoreBootstrapper:**
- Đăng ký các service cơ bản
- Khởi tạo logger manager
- Cấu hình database driver manager

---

## 3. CRAWFLOW PLUGIN ARCHITECTURE

### 3.1 Plugin Main File (wp-crawlflow.php)
```php
class CrawlFlow
{
    private Rake $container;
    private CrawlFlowKernel $kernel;

    public function __construct()
    {
        $this->container = new Rake();
        $this->kernel = new CrawlFlowKernel($this->container);
    }

    public function activate(): void
    {
        $this->kernel->bootstrap();
        $this->runMigrations();
    }
}
```

### 3.2 CrawlFlow Kernel
```php
class CrawlFlowKernel extends AbstractKernel
{
    public function getBootstrappers(): array
    {
        return [
            CoreBootstrapper::class,
            CrawlFlowBootstrapper::class,
            MigrationBootstrapper::class,
        ];
    }

    protected function getConfig(): array
    {
        return [
            'plugin_path' => plugin_dir_path(__FILE__),
            'plugin_url' => plugin_dir_url(__FILE__),
            'version' => '2.0.0',
        ];
    }
}
```

### 3.3 Service Registration
#### 3.3.1 CrawlFlowBootstrapper
```php
class CrawlFlowBootstrapper extends Bootstrapper
{
    public function register(Rake $container): void
    {
        $container->singleton(LoggerService::class, function() {
            return new LoggerService();
        });

        $container->singleton(MigrationService::class, function($container) {
            return new MigrationService($container);
        });
    }
}
```

#### 3.3.2 MigrationBootstrapper
```php
class MigrationBootstrapper extends Bootstrapper
{
    public function register(Rake $container): void
    {
        $container->singleton(MigrationKernel::class, function($container) {
            return new MigrationKernel($container);
        });

        $container->singleton(DatabaseBackupService::class, function($container) {
            return new DatabaseBackupService($container);
        });

        $container->singleton(MigrationValidatorService::class, function($container) {
            return new MigrationValidatorService($container);
        });
    }
}
```

---

## 4. DATABASE MIGRATION SYSTEM

### 4.1 Migration Kernel
```php
class MigrationKernel
{
    private Rake $container;
    private MigrationService $migrationService;
    private DatabaseBackupService $backupService;
    private MigrationValidatorService $validatorService;

    public function __construct(Rake $container)
    {
        $this->container = $container;
        $this->migrationService = $container->make(MigrationService::class);
        $this->backupService = $container->make(DatabaseBackupService::class);
        $this->validatorService = $container->make(MigrationValidatorService::class);
    }

    public function runMigrations(): void
    {
        try {
            $this->backupService->createBackup();
            $this->migrationService->runMigrations();
            $this->validatorService->validateMigrations();
        } catch (Exception $e) {
            Logger::error('Migration failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
```

### 4.2 Migration Service
```php
class MigrationService
{
    private Rake $container;
    private LoggerInterface $logger;
    private DatabaseDriverInterface $driver;

    public function __construct(Rake $container)
    {
        $this->container = $container;
        $this->logger = $container->make(LoggerService::class)->getLogger();
        $this->driver = $container->make(DatabaseDriverManager::class)->getDriver();
    }

    public function runMigrations(): void
    {
        $migrations = $this->getMigrationFiles();
        $executedMigrations = $this->getExecutedMigrations();

        foreach ($migrations as $migration) {
            if (!in_array($migration['name'], $executedMigrations)) {
                $this->executeMigration($migration);
            }
        }
    }

    private function executeMigration(array $migration): void
    {
        $this->logger->info('Executing migration: ' . $migration['name']);

        try {
            $sql = $this->generateSQL($migration['schema']);
            $this->driver->execute($sql);
            $this->recordMigration($migration['name']);

            $this->logger->info('Migration completed: ' . $migration['name']);
        } catch (Exception $e) {
            $this->logger->error('Migration failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
```

### 4.3 Schema Definitions
```php
// rake/schema_definitions/rake_configs.php
return [
    'table' => 'rake_configs',
    'columns' => [
        'id' => [
            'type' => 'bigint',
            'unsigned' => true,
            'auto_increment' => true,
        ],
        'key' => [
            'type' => 'varchar',
            'length' => 255,
            'not_null' => true,
        ],
        'value' => [
            'type' => 'longtext',
            'nullable' => true,
        ],
        'created_at' => [
            'type' => 'datetime',
            'default' => 'CURRENT_TIMESTAMP',
        ],
        'updated_at' => [
            'type' => 'datetime',
            'default' => 'CURRENT_TIMESTAMP',
            'on_update' => 'CURRENT_TIMESTAMP',
        ],
    ],
    'primary_key' => ['id'],
    'indexes' => [
        'idx_key' => ['columns' => ['key'], 'unique' => true],
    ],
];
```

### 4.4 Backup Service
```php
class DatabaseBackupService
{
    private Rake $container;
    private LoggerInterface $logger;

    public function createBackup(): void
    {
        $backupDir = WP_CONTENT_DIR . '/crawlflow-backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $backupFile = $backupDir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';

        // Tạo backup của database
        $this->createDatabaseBackup($backupFile);

        $this->logger->info('Database backup created: ' . $backupFile);
    }

    private function createDatabaseBackup(string $file): void
    {
        global $wpdb;

        $tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}%'");

        $backup = "-- CrawlFlow Database Backup\n";
        $backup .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";

        foreach ($tables as $table) {
            $tableName = array_values((array) $table)[0];
            $backup .= $this->backupTable($tableName);
        }

        file_put_contents($file, $backup);
    }
}
```

### 4.5 Validator Service
```php
class MigrationValidatorService
{
    private Rake $container;
    private LoggerInterface $logger;

    public function validateMigrations(): void
    {
        $this->validateTableStructure();
        $this->validateForeignKeys();
        $this->validateIndexes();
    }

    private function validateTableStructure(): void
    {
        $expectedTables = [
            $this->getPrefix() . 'rake_configs',
            $this->getPrefix() . 'rake_migrations',
            // Thêm các bảng khác
        ];

        foreach ($expectedTables as $table) {
            if (!$this->tableExists($table)) {
                throw new Exception("Required table missing: {$table}");
            }
        }
    }
}
```

---

## 5. LOGGING SYSTEM

### 5.1 Logger Service
```php
class LoggerService
{
    private ?LoggerInterface $logger = null;
    private array $config = [];

    public function __construct()
    {
        $this->config = [
            'log_path' => WP_CONTENT_DIR . '/logs/crawlflow/',
            'log_level' => 'info',
            'max_files' => 30,
            'file_size' => 10 * 1024 * 1024, // 10MB
        ];
    }

    public function getLogger(): LoggerInterface
    {
        if ($this->logger === null) {
            $this->initializeLogger();
        }

        return $this->logger;
    }

    private function initializeLogger(): void
    {
        if (!is_dir($this->config['log_path'])) {
            mkdir($this->config['log_path'], 0755, true);
        }

        $logger = new Logger('crawlflow');

        // File handler
        $fileHandler = new RotatingFileHandler(
            $this->config['log_path'] . 'crawlflow.log',
            $this->config['max_files'],
            $this->getLogLevel()
        );

        // Console handler (for development)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $consoleHandler = new StreamHandler('php://stdout', $this->getLogLevel());
            $logger->pushHandler($consoleHandler);
        }

        $logger->pushHandler($fileHandler);

        $this->logger = $logger;
    }

    private function getLogLevel(): int
    {
        $levels = [
            'debug' => Logger::DEBUG,
            'info' => Logger::INFO,
            'warning' => Logger::WARNING,
            'error' => Logger::ERROR,
            'critical' => Logger::CRITICAL,
        ];

        return $levels[$this->config['log_level']] ?? Logger::INFO;
    }
}
```

### 5.2 Logger Facade
```php
class Logger
{
    private static ?LoggerManager $manager = null;

    public static function setManager(LoggerManager $manager): void
    {
        self::$manager = $manager;
    }

    public static function emergency(string $message, array $context = []): void
    {
        self::log('emergency', $message, $context);
    }

    public static function alert(string $message, array $context = []): void
    {
        self::log('alert', $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::log('critical', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function notice(string $message, array $context = []): void
    {
        self::log('notice', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }

    private static function log(string $level, string $message, array $context = []): void
    {
        if (self::$manager === null) {
            // Fallback logging
            error_log("[CrawlFlow] [{$level}] {$message}");
            return;
        }

        try {
            self::$manager->log($level, $message, $context);
        } catch (Exception $e) {
            error_log("[CrawlFlow] Logger error: " . $e->getMessage());
        }
    }
}
```

---

## 6. DEPENDENCY INJECTION CONTAINER

### 6.1 Container Implementation
```php
class Rake
{
    private array $services = [];
    private array $singletons = [];
    private array $resolved = [];

    public function bind(string $abstract, $concrete): void
    {
        $this->services[$abstract] = $concrete;
    }

    public function singleton(string $abstract, $concrete): void
    {
        $this->singletons[$abstract] = $concrete;
    }

    public function make(string $abstract)
    {
        if ($this->has($abstract)) {
            return $this->resolve($abstract);
        }

        throw new Exception("Service not found: {$abstract}");
    }

    public function has(string $abstract): bool
    {
        return isset($this->services[$abstract]) || isset($this->singletons[$abstract]);
    }

    private function resolve(string $abstract)
    {
        // Check if already resolved
        if (isset($this->resolved[$abstract])) {
            return $this->resolved[$abstract];
        }

        $concrete = $this->getConcrete($abstract);
        $instance = $this->build($concrete);

        // Store singleton instances
        if (isset($this->singletons[$abstract])) {
            $this->resolved[$abstract] = $instance;
        }

        return $instance;
    }

    private function getConcrete(string $abstract)
    {
        if (isset($this->services[$abstract])) {
            return $this->services[$abstract];
        }

        if (isset($this->singletons[$abstract])) {
            return $this->singletons[$abstract];
        }

        throw new Exception("Service not found: {$abstract}");
    }

    private function build($concrete)
    {
        if (is_callable($concrete)) {
            return $concrete($this);
        }

        if (is_string($concrete)) {
            return new $concrete();
        }

        return $concrete;
    }
}
```

### 6.2 Service Registration Patterns
```php
// Singleton registration
$container->singleton(LoggerService::class, function($container) {
    return new LoggerService();
});

// Interface binding
$container->bind(LoggerInterface::class, LoggerService::class);

// Factory pattern
$container->bind(DatabaseDriverInterface::class, function($container) {
    $config = $container->make('config');
    return new WordPressDatabaseDriver($config);
});
```

---

## 7. KERNEL & BOOTSTRAPPER SYSTEM

### 7.1 Abstract Kernel
```php
abstract class AbstractKernel
{
    protected Rake $container;
    protected array $config = [];
    protected array $bootstrappers = [];

    public function __construct(Rake $container)
    {
        $this->container = $container;
        $this->config = $this->getConfig();
        $this->bootstrappers = $this->getBootstrappers();
    }

    public function bootstrap(): void
    {
        $this->registerServices();
        $this->bootServices();
    }

    private function registerServices(): void
    {
        foreach ($this->bootstrappers as $bootstrapperClass) {
            $bootstrapper = new $bootstrapperClass();
            $bootstrapper->register($this->container);
        }
    }

    private function bootServices(): void
    {
        foreach ($this->bootstrappers as $bootstrapperClass) {
            $bootstrapper = new $bootstrapperClass();
            $bootstrapper->boot($this->container);
        }
    }

    abstract public function getBootstrappers(): array;
    abstract protected function getConfig(): array;
}
```

### 7.2 Bootstrapper Interface
```php
abstract class Bootstrapper
{
    abstract public function register(Rake $container): void;
    abstract public function boot(Rake $container): void;
}
```

### 7.3 Core Bootstrapper
```php
class CoreBootstrapper extends Bootstrapper
{
    public function register(Rake $container): void
    {
        // Register core services
        $container->singleton(LoggerManager::class, function() {
            return new LoggerManager();
        });

        $container->singleton(DatabaseDriverManager::class, function() {
            return new DatabaseDriverManager();
        });

        // Register configuration
        $container->singleton('config', function() {
            return [
                'database' => [
                    'prefix' => $GLOBALS['wpdb']->prefix,
                    'charset' => $GLOBALS['wpdb']->charset,
                    'collate' => $GLOBALS['wpdb']->collate,
                ],
                'logging' => [
                    'path' => WP_CONTENT_DIR . '/logs/crawlflow/',
                    'level' => 'info',
                ],
            ];
        });
    }

    public function boot(Rake $container): void
    {
        // Initialize logger manager
        $loggerManager = $container->make(LoggerManager::class);
        Logger::setManager($loggerManager);

        // Register WordPress database driver
        $driverManager = $container->make(DatabaseDriverManager::class);
        $driverManager->registerDriver('wordpress', new WordPressDatabaseDriver());
        $driverManager->setDefaultDriver('wordpress');
    }
}
```

---

## 8. FACADE PATTERN IMPLEMENTATION

### 8.1 Facade Base Class
```php
abstract class Facade
{
    protected static Rake $container;

    public static function setContainer(Rake $container): void
    {
        self::$container = $container;
    }

    protected static function getFacadeAccessor(): string
    {
        throw new Exception('Facade must implement getFacadeAccessor method.');
    }

    protected static function resolveFacadeInstance(string $name)
    {
        if (!isset(self::$container)) {
            throw new Exception('Container not set for facade.');
        }

        return self::$container->make($name);
    }

    public static function __callStatic(string $method, array $arguments)
    {
        $instance = static::resolveFacadeInstance(static::getFacadeAccessor());

        return $instance->$method(...$arguments);
    }
}
```

### 8.2 Logger Facade Implementation
```php
class Logger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LoggerManager::class;
    }

    // Convenience methods
    public static function info(string $message, array $context = []): void
    {
        static::log('info', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        static::log('error', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        static::log('debug', $message, $context);
    }
}
```

---

## 9. WORDPRESS INTEGRATION

### 9.1 WordPress Database Adapter
```php
class WordPressDatabaseAdapter implements DatabaseAdapterInterface
{
    private \wpdb $wpdb;
    private string $prefix;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix;
    }

    public function query(string $sql): bool
    {
        return $this->wpdb->query($sql) !== false;
    }

    public function getResults(string $sql): array
    {
        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    public function getRow(string $sql): ?array
    {
        $result = $this->wpdb->get_row($sql, ARRAY_A);
        return $result ?: null;
    }

    public function getVar(string $sql): mixed
    {
        return $this->wpdb->get_var($sql);
    }

    public function insert(string $table, array $data): int
    {
        $table = $this->addPrefix($table);
        $result = $this->wpdb->insert($table, $data);

        if ($result === false) {
            throw new Exception('Insert failed: ' . $this->wpdb->last_error);
        }

        return $this->wpdb->insert_id;
    }

    public function update(string $table, array $data, array $where): int
    {
        $table = $this->addPrefix($table);
        $result = $this->wpdb->update($table, $data, $where);

        if ($result === false) {
            throw new Exception('Update failed: ' . $this->wpdb->last_error);
        }

        return $result;
    }

    public function delete(string $table, array $where): int
    {
        $table = $this->addPrefix($table);
        $result = $this->wpdb->delete($table, $where);

        if ($result === false) {
            throw new Exception('Delete failed: ' . $this->wpdb->last_error);
        }

        return $result;
    }

    private function addPrefix(string $table): string
    {
        if (strpos($table, $this->prefix) === 0) {
            return $table;
        }

        return $this->prefix . $table;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function escape(string $value): string
    {
        return $this->wpdb->_real_escape($value);
    }
}
```

### 9.2 WordPress Database Driver
```php
class WordPressDatabaseDriver implements DatabaseDriverInterface
{
    private WordPressDatabaseAdapter $adapter;

    public function __construct()
    {
        $this->adapter = new WordPressDatabaseAdapter();
    }

    public function execute(string $sql): bool
    {
        return $this->adapter->query($sql);
    }

    public function query(string $sql): array
    {
        return $this->adapter->getResults($sql);
    }

    public function getRow(string $sql): ?array
    {
        return $this->adapter->getRow($sql);
    }

    public function getValue(string $sql): mixed
    {
        return $this->adapter->getVar($sql);
    }

    public function insert(string $table, array $data): int
    {
        return $this->adapter->insert($table, $data);
    }

    public function update(string $table, array $data, array $where): int
    {
        return $this->adapter->update($table, $data, $where);
    }

    public function delete(string $table, array $where): int
    {
        return $this->adapter->delete($table, $where);
    }

    public function tableExists(string $table): bool
    {
        $sql = "SHOW TABLES LIKE '{$this->adapter->getPrefix()}{$table}'";
        $result = $this->adapter->getRow($sql);
        return $result !== null;
    }

    public function getTables(): array
    {
        $sql = "SHOW TABLES LIKE '{$this->adapter->getPrefix()}%'";
        $results = $this->adapter->getResults($sql);

        $tables = [];
        foreach ($results as $result) {
            $tables[] = array_values($result)[0];
        }

        return $tables;
    }
}
```

---

## 10. PERFORMANCE OPTIMIZATION

### 10.1 Lazy Loading Implementation
```php
class LazyServiceLoader
{
    private Rake $container;
    private array $loadedServices = [];

    public function __construct(Rake $container)
    {
        $this->container = $container;
    }

    public function load(string $serviceName): void
    {
        if (!isset($this->loadedServices[$serviceName])) {
            $this->container->make($serviceName);
            $this->loadedServices[$serviceName] = true;
        }
    }

    public function isLoaded(string $serviceName): bool
    {
        return isset($this->loadedServices[$serviceName]);
    }
}
```

### 10.2 Memory Management
```php
class MemoryManager
{
    private static array $memoryUsage = [];

    public static function startTracking(string $operation): void
    {
        self::$memoryUsage[$operation] = [
            'start' => memory_get_usage(true),
            'peak_start' => memory_get_peak_usage(true),
        ];
    }

    public static function endTracking(string $operation): array
    {
        if (!isset(self::$memoryUsage[$operation])) {
            return [];
        }

        $current = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);

        $usage = [
            'current' => $current,
            'peak' => $peak,
            'difference' => $current - self::$memoryUsage[$operation]['start'],
            'peak_difference' => $peak - self::$memoryUsage[$operation]['peak_start'],
        ];

        unset(self::$memoryUsage[$operation]);

        return $usage;
    }

    public static function optimize(): void
    {
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
}
```

### 10.3 Caching Strategy
```php
class CacheManager
{
    private array $cache = [];
    private array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'default_ttl' => 3600,
            'max_items' => 1000,
        ], $config);
    }

    public function get(string $key)
    {
        if (!isset($this->cache[$key])) {
            return null;
        }

        $item = $this->cache[$key];

        if (time() > $item['expires']) {
            unset($this->cache[$key]);
            return null;
        }

        return $item['value'];
    }

    public function set(string $key, $value, int $ttl = null): void
    {
        $ttl = $ttl ?? $this->config['default_ttl'];

        $this->cache[$key] = [
            'value' => $value,
            'expires' => time() + $ttl,
        ];

        // Cleanup if too many items
        if (count($this->cache) > $this->config['max_items']) {
            $this->cleanup();
        }
    }

    private function cleanup(): void
    {
        $now = time();
        $this->cache = array_filter($this->cache, function($item) use ($now) {
            return $item['expires'] > $now;
        });
    }
}
```

---

## 11. ERROR HANDLING & DEBUGGING

### 11.1 Exception Classes
```php
class CrawlFlowException extends Exception
{
    private array $context = [];

    public function __construct(string $message, array $context = [], int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}

class MigrationException extends CrawlFlowException
{
    public function __construct(string $message, array $context = [], int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct("Migration error: {$message}", $context, $code, $previous);
    }
}

class DatabaseException extends CrawlFlowException
{
    public function __construct(string $message, array $context = [], int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct("Database error: {$message}", $context, $code, $previous);
    }
}
```

### 11.2 Error Handler
```php
class ErrorHandler
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        $this->logger->error("PHP Error: {$errstr}", [
            'file' => $errfile,
            'line' => $errline,
            'type' => $errno,
        ]);

        return true;
    }

    public function handleException(Throwable $exception): void
    {
        $this->logger->critical("Uncaught Exception: " . $exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    public function handleFatalError(): void
    {
        $error = error_get_last();

        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->logger->critical("Fatal Error: {$error['message']}", [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type'],
            ]);
        }
    }
}
```

### 11.3 Debug Utilities
```php
class Debug
{
    public static function dump($var, string $label = ''): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo "<pre>";
            if ($label) {
                echo "<strong>{$label}:</strong>\n";
            }
            var_dump($var);
            echo "</pre>";
        }
    }

    public static function log($var, string $label = ''): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $output = $label ? "{$label}: " : '';
            $output .= print_r($var, true);
            error_log("[CrawlFlow Debug] {$output}");
        }
    }

    public static function trace(): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            self::log($trace, 'Stack Trace');
        }
    }
}
```

---

## 12. DEVELOPMENT GUIDELINES

### 12.1 Coding Standards
```php
/**
 * PSR-12 Coding Standards
 * - 4 spaces indentation
 * - UTF-8 encoding
 * - Unix line endings
 * - Trailing whitespace removal
 * - Single blank line at end of file
 */

// Class naming: PascalCase
class MigrationService
{
    // Method naming: camelCase
    public function runMigrations(): void
    {
        // Variable naming: camelCase
        $migrationFiles = $this->getMigrationFiles();

        // Constant naming: UPPER_SNAKE_CASE
        const DEFAULT_TIMEOUT = 30;

        // Private properties: camelCase with underscore prefix
        private string $_privateProperty;
    }
}
```

### 12.2 Documentation Standards
```php
/**
 * Migration Service for handling database migrations
 *
 * This service is responsible for:
 * - Executing migration files
 * - Tracking migration history
 * - Validating migration results
 * - Rolling back failed migrations
 *
 * @package CrawlFlow
 * @author Development Team
 * @version 2.0.0
 */
class MigrationService
{
    /**
     * Run all pending migrations
     *
     * @throws MigrationException When migration fails
     * @throws DatabaseException When database operation fails
     * @return void
     */
    public function runMigrations(): void
    {
        // Implementation
    }

    /**
     * Get list of migration files
     *
     * @return array Array of migration file information
     */
    private function getMigrationFiles(): array
    {
        // Implementation
    }
}
```

### 12.3 Testing Guidelines
```php
/**
 * Migration Service Test
 *
 * @package CrawlFlow\Tests
 */
class MigrationServiceTest extends TestCase
{
    private MigrationService $service;
    private Rake $container;

    protected function setUp(): void
    {
        $this->container = new Rake();
        $this->service = new MigrationService($this->container);
    }

    public function testRunMigrations(): void
    {
        // Arrange
        $migrationFiles = ['test_migration.php'];

        // Act
        $this->service->runMigrations();

        // Assert
        $this->assertTrue($this->migrationWasExecuted('test_migration'));
    }

    public function testMigrationFailure(): void
    {
        // Arrange
        $this->expectException(MigrationException::class);

        // Act
        $this->service->runMigrations();
    }
}
```

---

## 13. API REFERENCE

### 13.1 Container API
```php
// Service registration
$container->bind('service.name', ServiceClass::class);
$container->singleton('service.name', ServiceClass::class);

// Service resolution
$service = $container->make('service.name');
$hasService = $container->has('service.name');
```

### 13.2 Logger API
```php
// Direct usage
$logger = $container->make(LoggerService::class)->getLogger();
$logger->info('Message', ['context' => 'data']);

// Facade usage
Logger::info('Message', ['context' => 'data']);
Logger::error('Error message');
Logger::debug('Debug info');
```

### 13.3 Migration API
```php
// Run migrations
$migrationKernel = $container->make(MigrationKernel::class);
$migrationKernel->runMigrations();

// Check migration status
$migrationService = $container->make(MigrationService::class);
$pendingMigrations = $migrationService->getPendingMigrations();
$executedMigrations = $migrationService->getExecutedMigrations();
```

### 13.4 Database API
```php
// Database operations
$driver = $container->make(DatabaseDriverManager::class)->getDriver();

// Query execution
$results = $driver->query('SELECT * FROM table');
$row = $driver->getRow('SELECT * FROM table LIMIT 1');
$value = $driver->getValue('SELECT COUNT(*) FROM table');

// Data manipulation
$id = $driver->insert('table', ['column' => 'value']);
$affected = $driver->update('table', ['column' => 'value'], ['id' => 1]);
$deleted = $driver->delete('table', ['id' => 1]);
```

---

## 14. DEPLOYMENT GUIDE

### 14.1 Installation Steps
```bash
# 1. Clone repository
git clone https://github.com/your-repo/crawlflow.git

# 2. Install dependencies
composer install

# 3. Copy to WordPress plugins directory
cp -r wp-crawlflow /path/to/wordpress/wp-content/plugins/

# 4. Activate plugin in WordPress admin
```

### 14.2 Configuration
```php
// wp-config.php additions
define('CRAWFLOW_DEBUG', true);
define('CRAWFLOW_LOG_LEVEL', 'info');
define('CRAWFLOW_LOG_PATH', WP_CONTENT_DIR . '/logs/crawlflow/');
```

### 14.3 Environment Setup
```bash
# Development environment
export WP_DEBUG=true
export CRAWFLOW_DEBUG=true

# Production environment
export WP_DEBUG=false
export CRAWFLOW_DEBUG=false
```

### 14.4 Database Setup
```sql
-- Verify tables exist
SHOW TABLES LIKE 'wp_rake_%';

-- Check migration history
SELECT * FROM wp_rake_migrations ORDER BY executed_at DESC;

-- Verify configuration
SELECT * FROM wp_rake_configs;
```

---

## 15. TROUBLESHOOTING

### 15.1 Common Issues

#### 15.1.1 Memory Exhaustion
**Symptoms:** Fatal error: Allowed memory size exhausted
**Solutions:**
```php
// Increase memory limit
ini_set('memory_limit', '256M');

// Optimize logging
Logger::setConfig(['max_files' => 10]);

// Use lazy loading
$container->singleton(HeavyService::class, function() {
    return new HeavyService();
});
```

#### 15.1.2 Database Connection Issues
**Symptoms:** Database connection failed
**Solutions:**
```php
// Check WordPress database configuration
global $wpdb;
if (!$wpdb->db_connect()) {
    throw new DatabaseException('Database connection failed');
}

// Verify table prefix
$prefix = $wpdb->prefix;
Logger::info("Using database prefix: {$prefix}");
```

#### 15.1.3 Migration Failures
**Symptoms:** Migration execution fails
**Solutions:**
```php
// Check migration files
$migrationFiles = glob(RAKE_PATH . '/schema_definitions/*.php');
Logger::info("Found migration files: " . count($migrationFiles));

// Verify database permissions
$driver = $container->make(DatabaseDriverManager::class)->getDriver();
if (!$driver->tableExists('rake_migrations')) {
    throw new MigrationException('Migration table not found');
}
```

### 15.2 Debug Commands
```php
// Enable debug mode
define('WP_DEBUG', true);
define('CRAWFLOW_DEBUG', true);

// Check service registration
$container = new Rake();
$services = $container->getRegisteredServices();
Debug::dump($services, 'Registered Services');

// Test database connection
$driver = $container->make(DatabaseDriverManager::class)->getDriver();
$tables = $driver->getTables();
Debug::dump($tables, 'Database Tables');
```

### 15.3 Performance Monitoring
```php
// Memory usage tracking
MemoryManager::startTracking('migration');
$migrationKernel->runMigrations();
$usage = MemoryManager::endTracking('migration');
Logger::info('Migration memory usage', $usage);

// Execution time tracking
$start = microtime(true);
$migrationKernel->runMigrations();
$duration = microtime(true) - $start;
Logger::info("Migration completed in {$duration}s");
```

---

## KẾT LUẬN

Tài liệu kỹ thuật này cung cấp hướng dẫn toàn diện cho việc phát triển và bảo trì hệ thống CrawlFlow và Rake Framework. Các điểm chính:

1. **Kiến trúc modular** với dependency injection container
2. **Hệ thống logging** tích hợp Monolog với facade pattern
3. **Database migration** tự động với backup và validation
4. **WordPress integration** hoàn chỉnh với prefix handling
5. **Performance optimization** với lazy loading và memory management
6. **Error handling** toàn diện với custom exceptions
7. **Development guidelines** theo PSR-12 standards

Để tiếp tục phát triển, hãy tuân thủ các nguyên tắc và patterns đã được thiết lập trong tài liệu này.

---

**Tài liệu này sẽ được cập nhật thường xuyên khi có thay đổi trong hệ thống.**