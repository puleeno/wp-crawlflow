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
        $status = [];
        foreach ($schemas as $table => $schema) {
            $fullTable = $wpdb->prefix . $table;
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $fullTable)) === $fullTable;
            if (!$exists) {
                $status[$table] = [
                    'current_version' => '',
                    'required_version' => $schema['version'] ?? '',
                    'needs_migration' => true,
                    'table_missing' => true,
                    'missing_columns' => array_keys($schema['fields'] ?? []),
                    'missing_indexes' => array_map(function ($idx) { return implode(',', $idx['fields']); }, $schema['indexes'] ?? []),
                ];
                continue;
            }
            $issues = $this->getStructureIssues($fullTable, $schema);
            $status[$table] = [
                'current_version' => '',
                'required_version' => $schema['version'] ?? '',
                'needs_migration' => !empty($issues['missing_columns']) || !empty($issues['mismatched_columns']) || !empty($issues['missing_indexes']),
                'table_missing' => false,
                'missing_columns' => $issues['missing_columns'],
                'mismatched_columns' => $issues['mismatched_columns'],
                'missing_indexes' => $issues['missing_indexes'],
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
            if (!empty($schema['constraints'])) {
                crawlflow_add_table_constraints($fullTable, $schema);
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

    private function getStructureIssues(string $fullTable, array $schema): array
    {
        global $wpdb;
        $rows = $wpdb->get_results("DESCRIBE {$fullTable}", ARRAY_A) ?: [];
        $byName = [];
        foreach ($rows as $r) {
            $byName[$r['Field']] = $r;
        }
        $missing = [];
        $mismatched = [];
        foreach (($schema['fields'] ?? []) as $name => $def) {
            if (!isset($byName[$name])) {
                $missing[] = $name;
                continue;
            }
            $actual = $byName[$name];
            $expType = $this->expectedTypePrefix($def['type'] ?? '');
            $actType = strtolower($actual['Type'] ?? '');
            $typeOk = $expType ? str_starts_with($actType, $expType) : true;
            $nullable = (bool)($def['nullable'] ?? false);
            $nullOk = $nullable ? ($actual['Null'] === 'YES') : ($actual['Null'] === 'NO');
            $defaultOk = true;
            if (array_key_exists('default', $def)) {
                $expDef = strtolower((string)$def['default']);
                $actDef = strtolower((string)$actual['Default']);
                if ($expDef === 'current_timestamp') {
                    $defaultOk = strpos($actDef, 'current') !== false;
                } else {
                    $defaultOk = $expDef === $actDef;
                }
            }
            if (!$typeOk || !$nullOk || !$defaultOk) {
                $mismatched[] = $name;
            }
        }
        $missingIdx = $this->findMissingIndexes($fullTable, $schema['indexes'] ?? []);
        return [
            'missing_columns' => $missing,
            'mismatched_columns' => $mismatched,
            'missing_indexes' => $missingIdx,
            'missing_constraints' => $this->findMissingConstraints($fullTable, $schema['constraints'] ?? []),
        ];
    }

    private function expectedTypePrefix(string $type): string
    {
        switch ($type) {
            case 'string': return 'varchar';
            case 'text': return 'text';
            case 'longtext': return 'longtext';
            case 'int': return 'int';
            case 'bigint': return 'bigint';
            case 'decimal': return 'decimal';
            case 'datetime': return 'datetime';
            default: return '';
        }
    }

    private function findMissingIndexes(string $fullTable, array $indexes): array
    {
        global $wpdb;
        $rows = $wpdb->get_results("SHOW INDEX FROM `{$fullTable}`", ARRAY_A) ?: [];
        $indexCols = [];
        foreach ($rows as $r) {
            $k = $r['Key_name'];
            $indexCols[$k][] = $r['Column_name'];
        }
        $missing = [];
        foreach ($indexes as $idx) {
            $fields = $idx['fields'] ?? [];
            $need = array_map('strval', $fields);
            $found = false;
            foreach ($indexCols as $cols) {
                $colsSorted = $cols;
                sort($colsSorted);
                $needSorted = $need;
                sort($needSorted);
                if ($colsSorted === $needSorted) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missing[] = implode(',', $need);
            }
        }
        return $missing;
    }

    private function findMissingConstraints(string $fullTable, array $constraints): array
    {
        global $wpdb;
        if (empty($constraints)) {
            return [];
        }
        $missing = [];
        $dbName = defined('DB_NAME') ? DB_NAME : '';
        foreach ($constraints as $c) {
            $name = $c['name'] ?? '';
            if (!$name) {
                continue;
            }
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = %s AND CONSTRAINT_NAME = %s",
                $dbName,
                $name
            ));
            if ((int)$exists === 0) {
                $missing[] = $name;
            }
        }
        return $missing;
    }
}
