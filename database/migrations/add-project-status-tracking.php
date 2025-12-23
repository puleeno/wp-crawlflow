<?php
/**
 * Migration: Add Project Status Tracking (Rake Schema Compliant)
 * 
 * This migration adds enhanced status tracking for projects following
 * Rake schema conventions for consistency and compatibility
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load Rake schema definitions
 */
function crawlflow_load_rake_schema($schemaName)
{
    $schemaFile = CRAWLFLOW_PLUGIN_DIR . "vendor/ramphor/rake/schema_definitions/{$schemaName}.php";
    if (file_exists($schemaFile)) {
        return include $schemaFile;
    }
    return null;
}

/**
 * Add project status tracking functionality using Rake schema
 */
function crawlflow_add_project_status_tracking()
{
    global $wpdb;
    
    // Load enhanced tooths schema
    $toothsSchema = crawlflow_load_rake_schema('tooths_enhanced');
    $executionsSchema = crawlflow_load_rake_schema('crawlflow_executions');
    
    $toothsTable = $wpdb->prefix . ($toothsSchema['table'] ?? 'rake_tooths');
    $executionsTable = $wpdb->prefix . ($executionsSchema['table'] ?? 'crawlflow_executions');
    
    // Create executions table using Rake schema if not exists
    if ($executionsSchema) {
        crawlflow_create_table_from_schema($executionsTable, $executionsSchema);
    }
    
    // Update tooths table with enhanced fields
    if ($toothsSchema) {
        crawlflow_update_table_from_schema($toothsTable, $toothsSchema);
    }
    
    // Add constraints for data integrity
    crawlflow_add_table_constraints($toothsTable, $toothsSchema);
    
    // Log migration
    error_log('CrawlFlow: Project status tracking migration completed (Rake schema compliant)');
}

/**
 * Create table from Rake schema definition
 */
function crawlflow_create_table_from_schema($tableName, $schema)
{
    global $wpdb;
    
    // Check if table exists
    $tableExists = $wpdb->get_var(
        $wpdb->prepare("SHOW TABLES LIKE %s", $tableName)
    ) === $tableName;
    
    if ($tableExists) {
        return; // Table already exists
    }
    
    $charset_collate = $wpdb->get_charset_collate();
    $fields = [];
    $indexes = [];
    $foreignKeys = [];
    
    // Build field definitions
    foreach ($schema['fields'] as $fieldName => $fieldDef) {
        $fieldSql = crawlflow_build_field_sql($fieldName, $fieldDef);
        if ($fieldSql) {
            $fields[] = $fieldSql;
        }
    }
    
    // Build index definitions
    if (isset($schema['indexes'])) {
        foreach ($schema['indexes'] as $index) {
            $indexFields = implode(', ', array_map(function($field) {
                return "`{$field}`";
            }, $index['fields']));
            $indexName = 'idx_' . implode('_', $index['fields']);
            $indexes[] = "INDEX `{$indexName}` ({$indexFields})";
        }
    }
    
    // Build foreign key definitions
    if (isset($schema['foreign_keys'])) {
        foreach ($schema['foreign_keys'] as $fk) {
            $fkColumns = implode(', ', array_map(function($col) {
                return "`{$col}`";
            }, $fk['columns']));
            $refColumns = implode(', ', array_map(function($col) {
                return "`{$col}`";
            }, $fk['references']['columns']));
            $refTable = $wpdb->prefix . $fk['references']['table'];
            
            $foreignKeys[] = "FOREIGN KEY ({$fkColumns}) REFERENCES {$refTable}({$refColumns}) 
                             ON DELETE {$fk['on_delete']} ON UPDATE {$fk['on_update']}";
        }
    }
    
    // Build complete CREATE TABLE SQL
    $sql = "CREATE TABLE `{$tableName}` (
        " . implode(",\n        ", $fields) .
        (empty($indexes) ? "" : ",\n        " . implode(",\n        ", $indexes)) .
        (empty($foreignKeys) ? "" : ",\n        " . implode(",\n        ", $foreignKeys)) .
        " ) ENGINE={$schema['engine']} DEFAULT CHARSET={$schema['collation']}";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    error_log("CrawlFlow: Created table {$tableName} from schema");
}

/**
 * Update existing table with new fields from schema
 */
function crawlflow_update_table_from_schema($tableName, $schema)
{
    global $wpdb;
    
    // Get existing columns
    $existingColumns = $wpdb->get_col(
        "SHOW COLUMNS FROM `{$tableName}`"
    );
    
    // Add missing fields
    foreach ($schema['fields'] as $fieldName => $fieldDef) {
        if (!in_array($fieldName, $existingColumns)) {
            $fieldSql = crawlflow_build_field_sql($fieldName, $fieldDef);
            if ($fieldSql) {
                $wpdb->query("ALTER TABLE `{$tableName}` ADD COLUMN {$fieldSql}");
                error_log("CrawlFlow: Added column {$fieldName} to {$tableName}");
            }
        }
    }
    
    // Add missing indexes
    if (isset($schema['indexes'])) {
        $existingIndexes = $wpdb->get_col(
            "SHOW INDEX FROM `{$tableName}` WHERE Key_name != 'PRIMARY'"
        );
        
        foreach ($schema['indexes'] as $index) {
            $indexName = 'idx_' . implode('_', $index['fields']);
            if (!in_array($indexName, $existingIndexes)) {
                $indexFields = implode(', ', array_map(function($field) {
                    return "`{$field}`";
                }, $index['fields']));
                $wpdb->query("ALTER TABLE `{$tableName}` ADD INDEX `{$indexName}` ({$indexFields})");
                error_log("CrawlFlow: Added index {$indexName} to {$tableName}");
            }
        }
    }
}

/**
 * Build SQL field definition from schema
 */
function crawlflow_build_field_sql($fieldName, $fieldDef)
{
    $sql = "`{$fieldName}`";
    
    // Data type
    switch ($fieldDef['type']) {
        case 'bigint':
            $sql .= ' BIGINT' . ($fieldDef['auto_increment'] ?? false ? ' UNSIGNED AUTO_INCREMENT' : '');
            break;
        case 'string':
            $length = $fieldDef['length'] ?? 255;
            $sql .= " VARCHAR({$length})";
            break;
        case 'text':
            $sql .= ' TEXT';
            break;
        case 'longtext':
            $sql .= ' LONGTEXT';
            break;
        case 'int':
            $sql .= ' INT';
            break;
        case 'decimal':
            $length = $fieldDef['length'] ?? '10,2';
            $sql .= " DECIMAL({$length})";
            break;
        case 'datetime':
            $sql .= ' DATETIME';
            break;
        default:
            return null;
    }
    
    // Nullable
    if (!($fieldDef['nullable'] ?? false) && !($fieldDef['auto_increment'] ?? false)) {
        $sql .= ' NOT NULL';
    } elseif ($fieldDef['nullable'] ?? false) {
        $sql .= ' NULL';
    }
    
    // Default value
    if (isset($fieldDef['default'])) {
        if ($fieldDef['default'] === 'CURRENT_TIMESTAMP') {
            $sql .= ' DEFAULT CURRENT_TIMESTAMP';
        } else {
            $sql .= ' DEFAULT ' . (is_numeric($fieldDef['default']) ? $fieldDef['default'] : "'{$fieldDef['default']}'");
        }
    }
    
    // Primary key
    if ($fieldDef['primary'] ?? false) {
        $sql .= ' PRIMARY KEY';
    }
    
    return $sql;
}

/**
 * Add table constraints for data integrity
 */
function crawlflow_add_table_constraints($tableName, $schema)
{
    global $wpdb;
    
    if (!isset($schema['constraints'])) {
        return;
    }
    
    foreach ($schema['constraints'] as $constraint) {
        if ($constraint['type'] === 'check') {
            $constraintName = $constraint['name'];
            $constraintSql = $constraint['constraint'];
            
            // Check if constraint already exists
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS 
                     WHERE CONSTRAINT_SCHEMA = %s AND CONSTRAINT_NAME = %s",
                    DB_NAME,
                    $constraintName
                )
            );
            
            if ($exists == 0) {
                $wpdb->query("ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$constraintName}` {$constraintSql}");
                error_log("CrawlFlow: Added constraint {$constraintName} to {$tableName}");
            }
        }
    }
}

/**
 * Add project status indexes for better performance
 */
function crawlflow_add_project_status_indexes()
{
    global $wpdb;
    
    $toothsTable = $wpdb->prefix . 'rake_tooths';
    
    // Add indexes for better query performance
    $indexes = [
        'idx_tooth_status' => "CREATE INDEX idx_tooth_status ON $toothsTable(status)",
        'idx_tooth_completion' => "CREATE INDEX idx_tooth_completion ON $toothsTable(completion_percentage)",
        'idx_tooth_last_update' => "CREATE INDEX idx_tooth_last_update ON $toothsTable(last_status_update)"
    ];
    
    foreach ($indexes as $indexName => $sql) {
        try {
            $wpdb->query($sql);
        } catch (Exception $e) {
            // Index might already exist, continue
            error_log("CrawlFlow: Index $indexName might already exist: " . $e->getMessage());
        }
    }
}

/**
 * Run the migration
 */
function crawlflow_run_project_status_migration()
{
    crawlflow_add_project_status_tracking();
    crawlflow_add_project_status_indexes();
}

// Register migration hook
add_action('crawlflow_run_migrations', 'crawlflow_run_project_status_migration');
