<?php
/**
 * Migration: Create crawlflow_executions table
 * 
 * Table để lưu execution logs của CrawlFlow projects
 * 
 * Run: wp eval-file wp-content/plugins/wp-crawlflow/database/migrations/create-crawlflow-executions-table.php
 */

global $wpdb;

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once __DIR__ . '/../../../../wp-load.php';
}

$table_name = $wpdb->prefix . 'crawlflow_executions';
$charset_collate = $wpdb->get_charset_collate();

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║         DATABASE MIGRATION: Create CrawlFlow Executions Table             ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

echo "📋 Creating table: {$table_name}\n";
echo str_repeat('─', 80) . "\n";

$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id bigint(20) UNSIGNED NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'completed' COMMENT 'completed, failed, running',
    repository_count int(11) UNSIGNED DEFAULT 0,
    extracted_count int(11) UNSIGNED DEFAULT 0,
    processed_count int(11) UNSIGNED DEFAULT 0,
    error_count int(11) UNSIGNED DEFAULT 0,
    execution_time decimal(10,3) DEFAULT 0.000 COMMENT 'Execution time in seconds',
    executed_at datetime NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY project_id (project_id),
    KEY status (status),
    KEY executed_at (executed_at)
) {$charset_collate};";

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta($sql);

// Check if table was created
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

if ($table_exists) {
    echo "✅ Table {$table_name} created/updated successfully\n\n";
    
    // Show table structure
    $columns = $wpdb->get_results("DESCRIBE {$table_name}");
    echo "📊 Table Structure:\n";
    echo str_repeat('─', 80) . "\n";
    foreach ($columns as $column) {
        echo "  - {$column->Field} ({$column->Type})\n";
    }
    echo "\n";
} else {
    echo "❌ Failed to create table {$table_name}\n\n";
}

echo "✅ Migration completed!\n\n";
