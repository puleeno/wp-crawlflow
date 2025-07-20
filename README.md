# CrawlFlow WordPress Plugin

A powerful WordPress plugin for data migration and web crawling using Rake 2.0 framework.

## Features

- **Database Migration System** - Automatic schema migration with version tracking
- **Lazy Loading Logger** - Efficient logging system with Monolog integration
- **WordPress Integration** - Seamless integration with WordPress database and hooks
- **Rake Framework** - Built on top of Rake 2.0 for data processing

## Architecture

### Logger System

The plugin uses a lazy-loading logger system that only initializes when needed:

```php
// Logger is only initialized when actually needed
Logger::info('Starting migration');
Logger::error('Migration failed');
```

**Features:**
- One log file per day: `wp-content/crawlflow/crawlflow-YYYY-MM-DD.log`
- CLI mode includes stdout output
- Fallback to error_log if Rake container unavailable
- Memory efficient - single logger instance

### Database Migration

Automatic database migration system with schema versioning:

```php
// Migration service handles schema updates
$migrationService = new \CrawlFlow\Admin\MigrationService();
$result = $migrationService->runMigrations();
```

**Features:**
- Schema definitions in `vendor/ramphor/rake/schema_definitions/`
- Version tracking in `rake_configs` table
- WordPress prefix integration
- Automatic dependency resolution

### File Structure

```
wp-crawlflow/
├── src/
│   ├── Admin/
│   │   ├── MigrationService.php    # Database migration
│   │   ├── AdminController.php     # Admin UI
│   │   └── AjaxController.php      # REST API
│   └── LoggerService.php           # Lazy loading logger
├── assets/
│   ├── css/
│   └── js/
├── vendor/                         # Composer dependencies
├── wp-crawlflow.php               # Main plugin file
└── README.md
```

## Installation

1. Upload plugin to `/wp-content/plugins/wp-crawlflow/`
2. Activate plugin in WordPress admin
3. Plugin will automatically run migrations on activation

## Configuration

### Logger Configuration

Logger can be configured via WordPress filters:

```php
// Customize log file path
add_filter('crawlflow/logger', function($path) {
    return '/custom/path/to/logs/crawlflow.log';
});
```

### Database Configuration

Plugin automatically uses WordPress database settings:
- Host: `DB_HOST`
- Database: `DB_NAME`
- Username: `DB_USER`
- Password: `DB_PASSWORD`
- Prefix: `$wpdb->prefix`

## Development

### Adding New Features

1. **Logger Usage:**
```php
use Rake\Facade\Logger;

Logger::info('Feature started');
Logger::error('Feature failed', ['context' => 'data']);
```

2. **Database Operations:**
```php
use Puleeno\Rake\WordPress\Adapter\WordPressDatabaseAdapter;

$adapter = new WordPressDatabaseAdapter();
$result = $adapter->query('SELECT * FROM table');
```

3. **Migration Schema:**
Create schema definition in `vendor/ramphor/rake/schema_definitions/`:
```php
return [
    'version' => '1.0.0',
    'columns' => [
        'id' => ['type' => 'int', 'auto_increment' => true],
        'name' => ['type' => 'varchar', 'length' => 255],
    ],
    'indexes' => [
        'idx_name' => ['columns' => ['name'], 'unique' => true],
    ],
];
```

## Logging Levels

- `Logger::debug()` - Debug information
- `Logger::info()` - General information
- `Logger::warning()` - Warning messages
- `Logger::error()` - Error messages
- `Logger::critical()` - Critical errors

## Database Tables

Plugin creates and manages these tables:
- `{prefix}rake_configs` - Configuration and version tracking
- `{prefix}rake_tooths` - Data processing components
- `{prefix}rake_urls` - URL management
- `{prefix}logs` - Application logs
- `{prefix}resources` - Resource management
- `{prefix}queues` - Queue management
- `{prefix}failed_jobs` - Failed job tracking

## Requirements

- PHP 8.1+
- WordPress 5.0+
- MySQL 5.7+
- Composer dependencies

## License

GPL v3 - See LICENSE file for details.

## Support

For issues and feature requests, please visit the GitHub repository.