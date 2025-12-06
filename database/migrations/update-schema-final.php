<?php
/**
 * Database Migration: Update Schema Final
 * 
 * Đảm bảo schema đúng cho dpc_rake_data_origins và dpc_rake_data_origins_references
 * Xóa table tạm nếu có
 * 
 * Run: wp eval-file wp-content/plugins/wp-crawlflow/database/migrations/update-schema-final.php
 */

global $wpdb;

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║    DATABASE MIGRATION: Update Schema Final                            ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

$charset_collate = $wpdb->get_charset_collate();
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

// 1. Xóa table tạm nếu có
$temp_tables = [
    $wpdb->prefix . 'rake_data_origins_references_new',
    $wpdb->prefix . 'rake_data_origins_new',
];

foreach ($temp_tables as $temp_table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '{$temp_table}'");
    if ($exists) {
        $wpdb->query("DROP TABLE IF EXISTS {$temp_table}");
        echo "✅ Deleted temporary table: {$temp_table}\n";
    }
}

// 2. Đảm bảo dpc_rake_data_origins có schema đúng
$origins_table = $wpdb->prefix . 'rake_data_origins';
echo "\n📋 Checking {$origins_table}...\n";

$origins_sql = "CREATE TABLE IF NOT EXISTS {$origins_table} (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  source_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Reference to dpc_rake_data_sources.id (can be NULL for child URLs)',
  guid varchar(256) NOT NULL COMMENT 'Unique identifier (URL)',
  raw_data longtext NOT NULL COMMENT 'Raw data (HTML, JSON, etc.)',
  fetched_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY guid (guid),
  KEY source_id (source_id),
  KEY fetched_at (fetched_at)
) {$charset_collate};";

dbDelta($origins_sql);

// Kiểm tra source_id có thể NULL
$columns = $wpdb->get_results("SHOW COLUMNS FROM {$origins_table} WHERE Field = 'source_id'", ARRAY_A);
if (!empty($columns)) {
    $sourceIdColumn = $columns[0];
    if ($sourceIdColumn['Null'] !== 'YES') {
        echo "⚠️  source_id is NOT NULL, updating to allow NULL...\n";
        $wpdb->query("ALTER TABLE {$origins_table} MODIFY source_id bigint(20) UNSIGNED DEFAULT NULL");
        echo "✅ Updated source_id to allow NULL\n";
    } else {
        echo "✅ source_id already allows NULL\n";
    }
}

$origins_count = $wpdb->get_var("SELECT COUNT(*) FROM {$origins_table}");
echo "✅ {$origins_table}: {$origins_count} rows\n";

// 3. Đảm bảo dpc_rake_data_origins_references có schema đúng
$references_table = $wpdb->prefix . 'rake_data_origins_references';
echo "\n📋 Checking {$references_table}...\n";

$references_sql = "CREATE TABLE IF NOT EXISTS {$references_table} (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_origin_id bigint(20) NOT NULL COMMENT 'Reference to dpc_rake_data_origins.id (parent)',
  child_origin_id bigint(20) NOT NULL COMMENT 'Reference to dpc_rake_data_origins.id (child)',
  relationship_type varchar(50) DEFAULT 'parent_child' COMMENT 'parent_child, category_product, etc.',
  created_at datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY unique_parent_child_origin (parent_origin_id, child_origin_id),
  KEY parent_origin_id (parent_origin_id),
  KEY child_origin_id (child_origin_id),
  KEY relationship_type (relationship_type)
) {$charset_collate};";

dbDelta($references_sql);

// Kiểm tra xem có columns cũ không (parent_url, child_url, etc.)
$old_columns = ['parent_url', 'parent_url_hash', 'child_url', 'child_url_hash', 'source_id', 'metadata', 'updated_at'];
$columns = $wpdb->get_results("SHOW COLUMNS FROM {$references_table}", ARRAY_A);
$column_names = array_column($columns, 'Field');

$has_old_columns = false;
foreach ($old_columns as $old_col) {
    if (in_array($old_col, $column_names)) {
        $has_old_columns = true;
        break;
    }
}

if ($has_old_columns) {
    echo "⚠️  Table has old columns, running refactor migration...\n";
    // Chạy refactor migration
    $refactor_script = __DIR__ . '/refactor-references-table.php';
    if (file_exists($refactor_script)) {
        include $refactor_script;
    } else {
        echo "❌ Refactor migration script not found: {$refactor_script}\n";
    }
} else {
    echo "✅ Table schema is correct (no old columns)\n";
}

$references_count = $wpdb->get_var("SELECT COUNT(*) FROM {$references_table}");
echo "✅ {$references_table}: {$references_count} rows\n";

// 4. Hiển thị schema cuối cùng
echo "\n📋 Final Schema:\n";
echo "\n{$origins_table}:\n";
$columns = $wpdb->get_results("SHOW COLUMNS FROM {$origins_table}", ARRAY_A);
foreach ($columns as $col) {
    $null = $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
    echo "  - {$col['Field']} ({$col['Type']}) {$null}\n";
}

echo "\n{$references_table}:\n";
$columns = $wpdb->get_results("SHOW COLUMNS FROM {$references_table}", ARRAY_A);
foreach ($columns as $col) {
    $null = $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
    echo "  - {$col['Field']} ({$col['Type']}) {$null}\n";
}

echo "\n✅ MIGRATION COMPLETED\n";

