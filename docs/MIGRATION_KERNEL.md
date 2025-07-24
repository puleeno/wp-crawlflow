# CrawlFlow Migration Kernel

## Tổng quan

MigrationKernel là một kernel chuyên biệt cho việc xử lý database migration trong CrawlFlow, đảm bảo mọi xử lý đều thống nhất qua Rake class. Migration được quản lý thông qua table `rake_configs` thay vì table riêng biệt.

## Kiến trúc

### 1. MigrationKernel
- **Vị trí**: `wp-crawlflow/src/Kernel/MigrationKernel.php`
- **Kế thừa**: `AbstractKernel`
- **Chức năng**: Quản lý migration process thông qua Rake framework

### 2. MigrationBootstrapper
- **Vị trí**: `wp-crawlflow/src/Bootstrapper/MigrationBootstrapper.php`
- **Chức năng**: Bootstrap các services cần thiết cho migration

### 3. Migration Services
```
wp-crawlflow/src/Services/
├── DatabaseBackupService.php      ✅ Backup database
└── MigrationValidatorService.php  ✅ Validate migration
```

## Workflow

### 1. Migration Process
```
1. MigrationKernel::initializeMigration()
   ↓
2. MigrationBootstrapper::bootstrap()
   ↓
3. Register services (MigrationService, DatabaseBackupService, etc.)
   ↓
4. MigrationKernel::runMigrations()
   ↓
5. Execute migrations through Rake container
```

### 2. Service Registration
```php
// MigrationBootstrapper registers:
- MigrationService
- DatabaseBackupService
- MigrationValidatorService
- Migration bindings (manager, backup, validator, config)
```

## Migration Management với rake_configs

### 1. Version Tracking
Migration versions được lưu trong table `rake_configs` với key pattern:
- `table_version_{table_name}`: Lưu version hiện tại của table
- `migration_history_{table_name}_{timestamp}`: Lưu lịch sử migration

### 2. Migration Status Check
```php
// Kiểm tra version từ rake_configs
$configTable = $this->getPrefixedTableName('rake_configs');
$result = $driver->query("SELECT config_value FROM $configTable WHERE config_key = 'table_version_$table' LIMIT 1");
$currentVersion = $result[0]['config_value'] ?? '0.0.0';
```

### 3. Migration History
```php
// Lấy migration history từ rake_configs
$pattern = "migration_history_{$table}_%";
$history = $this->adapter->select($configTable, ['config_value'], ['config_key' => $pattern], $limit, ['updated_at' => 'DESC']);
```

## Usage

### 1. Basic Migration
```php
// Initialize migration kernel
$migrationKernel = new MigrationKernel();
$migrationKernel->initializeMigration();

// Run migrations
$result = $migrationKernel->runMigrations([
    'auto_backup' => true,
    'rollback_on_error' => true
]);
```

### 2. Migration Service
```php
// Use through MigrationService
$migrationService = new MigrationService();
$migrationService->initialize();

$result = $migrationService->runMigrations([
    'batch_size' => 10,
    'timeout' => 300
]);
```

### 3. Environment Validation
```php
// Validate before migration
$validation = $migrationService->validateEnvironment();

if ($validation['success']) {
    $result = $migrationService->runMigrations();
} else {
    // Handle validation errors
    Logger::error('Migration validation failed', $validation);
}
```

## Features

### 1. Database Backup
```php
// Create backup before migration
$backupResult = $migrationService->createBackup('pre_migration_backup');

// List available backups
$backups = $migrationService->listBackups();

// Restore from backup
$restoreResult = $migrationService->restoreBackup($backupPath);
```

### 2. Migration Validation
```php
// Validate environment
$envValidation = $migrationService->validateEnvironment();

// Validate migration files
$fileValidation = $migrationService->validateMigrationFiles($files);
```

### 3. Rollback Support
```php
// Rollback last migration
$result = $migrationService->rollbackMigrations(1);

// Rollback multiple migrations
$result = $migrationService->rollbackMigrations(5);
```

## Configuration

### 1. Migration Options
```php
$options = [
    'auto_backup' => true,           // Backup before migration
    'rollback_on_error' => true,     // Rollback on error
    'batch_size' => 10,              // Migrations per batch
    'timeout' => 300,                // Timeout in seconds
    'backup_before' => true,         // Create backup
];
```

### 2. Database Configuration
```php
$config = [
    'database' => [
        'driver' => 'wordpress',
        'prefix' => $wpdb->prefix,
        'charset' => DB_CHARSET,
        'collate' => DB_COLLATE,
    ],
    'logging' => [
        'level' => 'info',
        'file' => 'crawlflow-migration.log',
    ],
];
```

## Error Handling

### 1. Migration Errors
```php
$result = $migrationService->runMigrations();

if (!$result['success']) {
    Logger::error('Migration failed', [
        'error' => $result['error'],
        'errors' => $result['errors'] ?? []
    ]);
}
```

### 2. Validation Errors
```php
$validation = $migrationService->validateEnvironment();

if (!$validation['success']) {
    foreach ($validation as $check => $result) {
        if (!$result['success']) {
            Logger::error("Validation failed: {$check}", $result);
        }
    }
}
```

### 3. Backup Errors
```php
$backupResult = $migrationService->createBackup();

if (!$backupResult['success']) {
    Logger::error('Backup failed', [
        'error' => $backupResult['error']
    ]);
}
```

## Status Monitoring

### 1. Migration Status
```php
$status = $migrationService->getMigrationStatus();

// Result:
[
    'success' => true,
    'migrations' => [
        'total' => 10,
        'run' => 8,
        'pending' => 2
    ],
    'last_migration' => '2024-01-15 10:30:00'
]
```

### 2. Kernel Status
```php
$kernelStatus = $migrationService->getFullMigrationStatus();

// Result:
[
    'migration_status' => [...],
    'kernel_status' => [
        'booted' => true,
        'bootstrappers' => [...],
        'config' => [...]
    ],
    'results' => [...],
    'errors' => [...]
]
```

## Integration với Rake

### 1. Service Container
```php
// All services registered in Rake container
$app = $migrationKernel->getApp();

$migrationService = $app->make('CrawlFlow\Admin\MigrationService');
$backupService = $app->make('CrawlFlow\Services\DatabaseBackupService');
$validatorService = $app->make('CrawlFlow\Services\MigrationValidatorService');
```

### 2. Logger Integration
```php
// Use Rake Logger
$logger = $app->make(\Rake\Manager\LoggerManager::class);
$logger->info('Migration started');

// Or use Logger Facade
\Rake\Facade\Logger::info('Migration completed');
```

### 3. Database Integration
```php
// Use Rake Database Manager
$dbManager = $app->make(\Rake\Manager\DatabaseDriverManager::class);
$connection = $dbManager->getConnection();
```

## Benefits

1. **🔄 Unified Processing**: Tất cả xử lý đều qua Rake class
2. **🛡️ Safe Migration**: Backup và rollback tự động
3. **✅ Validation**: Kiểm tra environment và migration files
4. **📊 Monitoring**: Status tracking chi tiết
5. **🔧 Configurable**: Options linh hoạt
6. **📝 Logging**: Logging đầy đủ qua Rake Logger
7. **🔄 Rollback**: Hỗ trợ rollback migrations
8. **🧪 Testable**: Dễ test và debug
9. **🗄️ Config-based**: Sử dụng rake_configs table thay vì table riêng biệt

## Best Practices

### 1. Always Validate
```php
// Validate before migration
$validation = $migrationService->validateEnvironment();
if (!$validation['success']) {
    // Handle validation errors
    return;
}
```

### 2. Use Backup
```php
// Always backup before migration
$backupResult = $migrationService->createBackup();
if (!$backupResult['success']) {
    // Handle backup failure
    return;
}
```

### 3. Monitor Progress
```php
// Monitor migration progress
$status = $migrationService->getMigrationStatus();
Logger::info('Migration progress', $status);
```

### 4. Handle Errors
```php
// Proper error handling
try {
    $result = $migrationService->runMigrations();
    if (!$result['success']) {
        // Handle migration errors
        $migrationService->rollbackMigrations();
    }
} catch (\Exception $e) {
    Logger::error('Migration exception', ['error' => $e->getMessage()]);
}
```