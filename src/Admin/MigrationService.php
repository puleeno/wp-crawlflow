<?php

namespace CrawlFlow\Admin;

class MigrationService
{
    private $app;

    public function __construct($app = null)
    {
        $this->app = $app;
    }

    public function checkMigrationStatus(): array
    {
        global $wpdb;
        $schemas = $this->getSchemaDefinitions();
        $configTable = $wpdb->prefix . 'rake_configs';
        $rows = $wpdb->get_results("SELECT config_key, config_value FROM {$configTable} WHERE config_key LIKE 'table_version_%'", ARRAY_A);
        $current = [];
        foreach ($rows as $row) {
            $table = str_replace('table_version_', '', $row['config_key']);
            $current[$table] = $row['config_value'];
        }
        $status = [];
        foreach ($schemas as $table => $schema) {
            $required = $schema['version'] ?? '1.0.0';
            $cur = $current[$table] ?? '0.0.0';
            $needs = version_compare($cur, $required, '!=');
            $status[$table] = [
                'current_version' => $cur,
                'required_version' => $required,
                'needs_migration' => $needs,
            ];
        }
        return $status;
    }

    public function runMigrations(): bool
    {
        global $wpdb;
        require_once CRAWLFLOW_PLUGIN_DIR . 'database/migrations/add-project-status-tracking.php';
        $schemas = $this->getSchemaDefinitions();
        foreach ($schemas as $table => $schema) {
            $fullTable = $wpdb->prefix . $table;
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $fullTable)) === $fullTable;
            if ($exists) {
                crawlflow_update_table_from_schema($fullTable, $schema);
            } else {
                crawlflow_create_table_from_schema($fullTable, $schema);
            }
            $this->updateTableVersion($table, $schema['version'] ?? '1.0.0');
        }
        return true;
    }

    private function updateTableVersion(string $table, string $version): void
    {
        global $wpdb;
        $configTable = $wpdb->prefix . 'rake_configs';
        $wpdb->replace(
            $configTable,
            [
                'config_key' => 'table_version_' . $table,
                'config_value' => $version,
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s']
        );
    }

    private function getSchemaDefinitions(): array
    {
        $dir = CRAWLFLOW_PLUGIN_DIR . 'vendor/ramphor/rake/schema_definitions';
        $files = glob($dir . '/*.php') ?: [];
        $schemas = [];
        foreach ($files as $file) {
            $def = include $file;
            if (is_array($def) && isset($def['table'])) {
                $schemas[$def['table']] = $def;
            }
        }
        return $schemas;
    }
}
