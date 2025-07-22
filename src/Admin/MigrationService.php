<?php

namespace CrawlFlow\Admin;

use Rake\Manager\Database\MigrationManager;
use Rake\Database\SchemaGenerator;
use Puleeno\Rake\WordPress\Adapter\WordPressDatabaseAdapter;

/**
 * Migration Service for CrawlFlow Plugin
 */
class MigrationService
{
    /**
     * @var MigrationManager
     */
    private $migrationManager;

    /**
     * @var SchemaGenerator
     */
    private $schemaGenerator;

    /**
     * @var WordPressDatabaseAdapter
     */
    private $wordpressAdapter;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->wordpressAdapter = new WordPressDatabaseAdapter();
        $this->schemaGenerator = new SchemaGenerator($this->wordpressAdapter);

        // Create database config with WordPress prefix
        $databaseConfig = $this->createWordPressDatabaseConfig();

        $this->migrationManager = new MigrationManager($this->wordpressAdapter, $databaseConfig);
    }

    /**
     * Create database config using WordPress settings
     *
     * @return \Rake\Config\DatabaseConfig|null
     */
    private function createWordPressDatabaseConfig()
    {
        global $wpdb;

        if (!class_exists('Rake\Config\DatabaseConfig')) {
            // Logger::error('DatabaseConfig class not found');
            return null;
        }

        try {
            // Get WordPress database settings
            $dbConfig = [
                'driver' => 'mysql',
                'host' => DB_HOST,
                'port' => 3306,
                'dbname' => DB_NAME,
                'user' => DB_USER,
                'password' => DB_PASSWORD,
                'charset' => $wpdb->charset,
                'collation' => $wpdb->collate,
                'prefix' => $wpdb->prefix . 'rake_', // WordPress prefix with rake_
            ];

            return new \Rake\Config\DatabaseConfig($dbConfig);
        } catch (\Exception $e) {
            // Logger::error('Failed to create database config: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Run migrations when plugin is activated
     */
    public function runMigrations()
    {
        try {
            // Initialize logger only when needed
            if (class_exists('CrawlFlow\LoggerService')) {
                \CrawlFlow\LoggerService::init();
            }

            // Logger::info('Starting CrawlFlow migrations');

            // Get schema definitions from Rake
            $schemaDefinitions = $this->getSchemaDefinitions();

            if (empty($schemaDefinitions)) {
                // Logger::warning('No schema definitions found');
                return false;
            }

            // Run migration using the public method
            $schemaDir = CRAWLFLOW_PLUGIN_DIR . '../rake/schema_definitions/';
            $result = $this->migrationManager->runMigration($this->schemaGenerator, $schemaDir);

            if ($result) {
                // Logger::info('Database migration completed successfully');
                return true;
            } else {
                // Logger::error('Database migration failed');
                return false;
            }
        } catch (\Exception $e) {
            // Logger::error('Migration error - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get schema definitions from Rake
     */
    private function getSchemaDefinitions()
    {
        $schemaPath = CRAWLFLOW_PLUGIN_DIR . '../rake/schema_definitions/';

        if (!is_dir($schemaPath)) {
            // Logger::error('Schema definitions directory not found: ' . $schemaPath);
            return [];
        }

        $definitions = [];
        $files = glob($schemaPath . '*.php');

        foreach ($files as $file) {
            $tableName = basename($file, '.php');
            $definition = include $file;

            if (is_array($definition)) {
                $definitions[$tableName] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * Check migration status
     */
    public function checkMigrationStatus()
    {
        try {
            $schemaDefinitions = $this->getSchemaDefinitions();
            $status = [];

            foreach ($schemaDefinitions as $table => $definition) {
                // Use adapter to get current version from database
                $driver = $this->wordpressAdapter->getDriver();
                $currentVersion = '0.0.0'; // Default version

                // Get prefixed table name
                $configTable = $this->getPrefixedTableName('rake_configs');

                // Try to get version from rake_configs table using config_value column
                $result = $driver->query("SELECT config_value FROM $configTable WHERE config_key = 'table_version_$table' LIMIT 1");
                if ($result && count($result) > 0) {
                    $currentVersion = $result[0]['config_value'] ?? '0.0.0';
                }

                $requiredVersion = $definition['version'] ?? '1.0.0';

                $status[$table] = [
                    'current_version' => $currentVersion,
                    'required_version' => $requiredVersion,
                    'needs_migration' => $this->compareVersions($currentVersion, $requiredVersion) !== 0
                ];
            }

            return $status;
        } catch (\Exception $e) {
            // Logger::error('Migration status check error - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get prefixed table name
     *
     * @param string $tableName
     * @return string
     */
    private function getPrefixedTableName(string $tableName): string
    {
        global $wpdb;
        return $wpdb->prefix . 'rake_' . $tableName;
    }

    /**
     * Compare two version strings
     */
    private function compareVersions(string $version1, string $version2): int
    {
        $v1Parts = array_map('intval', explode('.', $version1));
        $v2Parts = array_map('intval', explode('.', $version2));

        $maxLength = max(count($v1Parts), count($v2Parts));

        for ($i = 0; $i < $maxLength; $i++) {
            $v1 = $v1Parts[$i] ?? 0;
            $v2 = $v2Parts[$i] ?? 0;

            if ($v1 > $v2) {
                return 1;
            }
            if ($v1 < $v2) {
                return -1;
            }
        }

        return 0;
    }

    /**
     * Get migration history
     */
    public function getMigrationHistory()
    {
        try {
            return $this->migrationManager->getAllMigrationHistory();
        } catch (\Exception $e) {
            // Logger::error('Migration history error - ' . $e->getMessage());
            return [];
        }
    }
}
