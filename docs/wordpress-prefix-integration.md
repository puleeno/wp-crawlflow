# WordPress Prefix Integration

CrawlFlow tích hợp với WordPress database prefix để đảm bảo tương thích với các cài đặt WordPress khác nhau.

## Cách hoạt động

### 1. WordPress Prefix Detection

CrawlFlow tự động phát hiện WordPress prefix từ `$wpdb->prefix`:

```php
global $wpdb;
$wordpressPrefix = $wpdb->prefix; // Ví dụ: 'wp_'
```

### 2. Rake Tables Prefix

Tất cả tables của Rake sẽ sử dụng prefix: `{wordpress_prefix}rake_`


**Ví dụ:**
- WordPress prefix: `wp_`
- Rake tables prefix: `wp_rake_`
- Tables: `wp_rake_configs`, `wp_rake_urls`, `wp_resources`, etc.

### 3. Database Configuration

CrawlFlow tự động tạo DatabaseConfig với WordPress settings:

```php
$dbConfig = [
    'driver' => 'mysql',
    'host' => DB_HOST,
    'port' => 3306,
    'dbname' => DB_NAME,
    'user' => DB_USER,
    'password' => DB_PASSWORD,
    'charset' => $wpdb->charset,
    'collation' => $wpdb->collate,
    'prefix' => $wpdb->prefix, // WordPress prefix trực tiếp
];
```

## Migration Flow

### 1. Plugin Activation

Khi activate plugin CrawlFlow:

1. **Load WordPress settings**: Lấy `$wpdb->prefix` và database constants
2. **Create DatabaseConfig**: Tạo config với WordPress prefix
3. **Run migrations**: Chạy migration với prefixed table names
4. **Create tables**: Tạo tables với prefix `{wp_prefix}`

### 2. Migration Process

```php
// MigrationService.php
private function createWordPressDatabaseConfig()
{
    global $wpdb;

    $dbConfig = [
        'driver' => 'mysql',
        'host' => DB_HOST,
        'dbname' => DB_NAME,
        'user' => DB_USER,
        'password' => DB_PASSWORD,
        'charset' => $wpdb->charset,
        'collation' => $wpdb->collate,
        'prefix' => $wpdb->prefix,
    ];

    return new \Rake\Config\DatabaseConfig($dbConfig);
}
```

### 3. Table Creation

MigrationManager sẽ tạo tables với prefix:

```sql
-- Thay vì: CREATE TABLE rake_configs
-- Sẽ tạo: CREATE TABLE wp_rake_configs

CREATE TABLE wp_rake_configs (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    `key` varchar(255) NOT NULL,
    value longtext,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Admin Interface

### 1. Database Information

Admin page hiển thị thông tin database:

- **WordPress Prefix**: `wp_`
- **Rake Tables Prefix**: `wp_rake_`
- **Database Name**: `wordpress_db`

### 2. Migration Status

Hiển thị migration status với prefixed table names:

| Table | Database Table | Current Version | Required Version | Status |
|-------|----------------|-----------------|------------------|--------|
| configs | `wp_rake_configs` | 1.0.0 | 1.0.0 | Up to Date |
| urls | `wp_rake_urls` | 0.0.0 | 1.0.0 | Needs Migration |

## Testing

### 1. Test WordPress Prefix

```bash
cd wp-content/plugins/wp-crawlflow
php test-wordpress-prefix.php
```

### 2. Expected Output

```
=== CrawlFlow WordPress Prefix Test ===

1. Testing WordPress database settings...
   - WordPress prefix: wp_
   - Database name: wordpress_db
   - Database host: localhost
   - Database charset: utf8mb4
   - Database collation: utf8mb4_unicode_ci

2. Testing MigrationService with WordPress prefix...
   ✓ MigrationService created successfully

3. Testing prefixed table names...
   - configs -> wp_rake_configs
   - urls -> wp_rake_urls
   - resources -> wp_rake_resources
```

## Benefits

### 1. Multi-site Compatibility

- Hỗ trợ WordPress multi-site với different prefixes
- Tương thích với custom table prefixes
- Không conflict với existing tables

### 2. WordPress Standards

- Sử dụng WordPress database constants
- Tương thích với WordPress charset/collation
- Follow WordPress coding standards

### 3. Easy Migration

- Automatic prefix detection
- No manual configuration needed
- Seamless integration với WordPress

## Configuration

### 1. Custom Prefix (Optional)

Nếu muốn custom prefix khác WordPress:

```php
// Trong wp-config.php hoặc plugin settings
define('CRAWLFLOW_RAKE_PREFIX', 'custom_');
```

### 2. Disable Prefix (Not Recommended)

```php
// Trong MigrationService.php
'prefix' => '', // Không sử dụng prefix
```

## Troubleshooting

### 1. Table Not Found

**Error**: `Table 'wp_rake_configs' doesn't exist`

**Solution**:
- Check WordPress prefix configuration
- Run migration: `php test-wordpress-prefix.php`
- Verify database permissions

### 2. Permission Denied

**Error**: `Access denied for user`

**Solution**:
- Check WordPress database credentials
- Verify database user permissions
- Test database connection

### 3. Migration Failed

**Error**: `Migration failed`

**Solution**:
- Check error logs
- Verify schema definitions
- Test with dry run mode