# Migration Config Management

## Tổng quan

CrawlFlow sử dụng table `rake_configs` để quản lý migration thay vì tạo table riêng biệt. Điều này giúp đơn giản hóa cấu trúc database và tận dụng hệ thống config có sẵn.

## Cấu trúc rake_configs

### 1. Table Schema
```sql
CREATE TABLE wp_rake_configs (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    `key` varchar(255) NOT NULL,
    value longtext,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Migration Keys Pattern

#### Version Tracking
- **Pattern**: `table_version_{table_name}`
- **Ví dụ**: `table_version_rake_urls`, `table_version_rake_configs`
- **Value**: Version string (e.g., "1.0.0", "2.1.3")

#### Migration History
- **Pattern**: `migration_history_{table_name}_{timestamp}`
- **Ví dụ**: `migration_history_rake_urls_1703123456`
- **Value**: JSON string chứa thông tin migration

## Migration Process

### 1. Check Current Version
```php
// Kiểm tra version hiện tại của table
$configTable = $this->getPrefixedTableName('rake_configs');
$result = $driver->query("SELECT config_value FROM $configTable WHERE config_key = 'table_version_$table' LIMIT 1");
$currentVersion = $result[0]['config_value'] ?? '0.0.0';
```

### 2. Compare Versions
```php
// So sánh version hiện tại với version yêu cầu
$requiredVersion = $definition['version'] ?? '1.0.0';
$needsMigration = $this->compareVersions($currentVersion, $requiredVersion) !== 0;
```

### 3. Execute Migration
```php
// Thực hiện migration
if ($needsMigration) {
    // Run migration logic
    $this->executeMigration($table, $definition);

    // Update version in config
    $this->updateTableVersion($table, $requiredVersion);

    // Log migration history
    $this->logMigrationHistory($table, $currentVersion, $requiredVersion);
}
```

### 4. Update Version
```php
private function updateTableVersion(string $table, string $version): void
{
    $configTable = $this->getPrefixedTableName('rake_configs');
    $key = "table_version_{$table}";

    $this->adapter->insert($configTable, [
        'key' => $key,
        'value' => $version,
        'updated_at' => date('Y-m-d H:i:s')
    ], true); // true = ON DUPLICATE KEY UPDATE
}
```

### 5. Log Migration History
```php
private function logMigrationHistory(string $table, string $fromVersion, string $toVersion): void
{
    $configTable = $this->getPrefixedTableName('rake_configs');
    $timestamp = time();
    $key = "migration_history_{$table}_{$timestamp}";

    $historyData = [
        'table' => $table,
        'from_version' => $fromVersion,
        'to_version' => $toVersion,
        'executed_at' => date('Y-m-d H:i:s'),
        'status' => 'completed'
    ];

    $this->adapter->insert($configTable, [
        'key' => $key,
        'value' => json_encode($historyData),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
}
```

## Migration Status Check

### 1. Get All Table Versions
```php
public function getMigrationStatus(): array
{
    $configTable = $this->getPrefixedTableName('rake_configs');
    $sql = "SELECT config_key, config_value FROM $configTable WHERE config_key LIKE 'table_version_%'";
    $results = $this->adapter->getResults($sql);

    $status = [];
    foreach ($results as $result) {
        $table = str_replace('table_version_', '', $result['config_key']);
        $status[$table] = [
            'current_version' => $result['config_value'],
            'required_version' => $this->getRequiredVersion($table),
            'needs_migration' => $this->needsMigration($table, $result['config_value'])
        ];
    }

    return $status;
}
```

### 2. Get Migration History
```php
public function getMigrationHistory(string $table = null, int $limit = 10): array
{
    $configTable = $this->getPrefixedTableName('rake_configs');

    if ($table) {
        $pattern = "migration_history_{$table}_%";
        $sql = "SELECT * FROM $configTable WHERE config_key LIKE ? ORDER BY updated_at DESC LIMIT ?";
        $results = $this->adapter->getResults($sql, [$pattern, $limit]);
    } else {
        $sql = "SELECT * FROM $configTable WHERE config_key LIKE 'migration_history_%' ORDER BY updated_at DESC LIMIT ?";
        $results = $this->adapter->getResults($sql, [$limit]);
    }

    $history = [];
    foreach ($results as $result) {
        $historyData = json_decode($result['value'], true);
        if ($historyData) {
            $history[] = array_merge($historyData, [
                'config_key' => $result['key'],
                'updated_at' => $result['updated_at']
            ]);
        }
    }

    return $history;
}
```

## Rollback Support

### 1. Rollback Migration
```php
public function rollbackMigration(string $table, string $targetVersion): bool
{
    try {
        // Get current version
        $currentVersion = $this->getCurrentVersion($table);

        // Execute rollback logic
        $this->executeRollback($table, $currentVersion, $targetVersion);

        // Update version
        $this->updateTableVersion($table, $targetVersion);

        // Log rollback
        $this->logMigrationHistory($table, $currentVersion, $targetVersion, 'rollback');

        return true;
    } catch (Exception $e) {
        Logger::error("Rollback failed for table {$table}: " . $e->getMessage());
        return false;
    }
}
```

### 2. Rollback History
```php
private function logMigrationHistory(string $table, string $fromVersion, string $toVersion, string $type = 'migration'): void
{
    $configTable = $this->getPrefixedTableName('rake_configs');
    $timestamp = time();
    $key = "migration_history_{$table}_{$timestamp}";

    $historyData = [
        'table' => $table,
        'from_version' => $fromVersion,
        'to_version' => $toVersion,
        'type' => $type,
        'executed_at' => date('Y-m-d H:i:s'),
        'status' => 'completed'
    ];

    $this->adapter->insert($configTable, [
        'key' => $key,
        'value' => json_encode($historyData),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
}
```

## Benefits

### 1. Simplified Database Structure
- Không cần tạo table riêng cho migration
- Tận dụng hệ thống config có sẵn
- Giảm số lượng tables trong database

### 2. Flexible Version Management
- Dễ dàng thêm/sửa/xóa version tracking
- Hỗ trợ custom migration metadata
- JSON-based history storage

### 3. WordPress Integration
- Sử dụng WordPress prefix tự động
- Tương thích với WordPress database settings
- Không conflict với existing tables

### 4. Performance
- Ít tables = ít joins
- Config table thường được cache
- Efficient key-based lookups

## Migration Examples

### 1. Basic Migration
```php
// MigrationService.php
public function runMigrations(): array
{
    $schemaDefinitions = $this->getSchemaDefinitions();
    $results = [];

    foreach ($schemaDefinitions as $table => $definition) {
        $currentVersion = $this->getCurrentVersion($table);
        $requiredVersion = $definition['version'] ?? '1.0.0';

        if ($this->compareVersions($currentVersion, $requiredVersion) !== 0) {
            $result = $this->executeMigration($table, $definition);
            $results[$table] = $result;
        }
    }

    return $results;
}
```

### 2. Version Comparison
```php
private function compareVersions(string $version1, string $version2): int
{
    $v1 = explode('.', $version1);
    $v2 = explode('.', $version2);

    for ($i = 0; $i < max(count($v1), count($v2)); $i++) {
        $num1 = (int)($v1[$i] ?? 0);
        $num2 = (int)($v2[$i] ?? 0);

        if ($num1 < $num2) return -1;
        if ($num1 > $num2) return 1;
    }

    return 0;
}
```

### 3. Migration Status
```php
public function getMigrationStatus(): array
{
    $status = [];
    $schemaDefinitions = $this->getSchemaDefinitions();

    foreach ($schemaDefinitions as $table => $definition) {
        $currentVersion = $this->getCurrentVersion($table);
        $requiredVersion = $definition['version'] ?? '1.0.0';

        $status[$table] = [
            'current_version' => $currentVersion,
            'required_version' => $requiredVersion,
            'needs_migration' => $this->compareVersions($currentVersion, $requiredVersion) !== 0,
            'status' => $this->compareVersions($currentVersion, $requiredVersion) === 0 ? 'up_to_date' : 'needs_migration'
        ];
    }

    return $status;
}
```

## Best Practices

### 1. Always Use Semantic Versioning
```php
// Good
'version' => '1.0.0'
'version' => '2.1.3'

// Avoid
'version' => '1'
'version' => 'latest'
```

### 2. Log Migration Details
```php
$historyData = [
    'table' => $table,
    'from_version' => $fromVersion,
    'to_version' => $toVersion,
    'executed_at' => date('Y-m-d H:i:s'),
    'status' => 'completed',
    'execution_time' => $executionTime,
    'affected_rows' => $affectedRows
];
```

### 3. Handle Errors Gracefully
```php
try {
    $this->executeMigration($table, $definition);
    $this->updateTableVersion($table, $requiredVersion);
} catch (Exception $e) {
    Logger::error("Migration failed for table {$table}: " . $e->getMessage());
    // Rollback if needed
    $this->rollbackMigration($table, $currentVersion);
    throw $e;
}
```

### 4. Validate Before Migration
```php
public function validateMigration(string $table, array $definition): bool
{
    // Check if table exists
    if (!$this->tableExists($table)) {
        Logger::error("Table {$table} does not exist");
        return false;
    }

    // Check version format
    if (!isset($definition['version']) || !$this->isValidVersion($definition['version'])) {
        Logger::error("Invalid version format for table {$table}");
        return false;
    }

    return true;
}
```