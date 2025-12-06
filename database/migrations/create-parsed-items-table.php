<?php
/**
 * Database Migration: Create Parsed Items Table
 * 
 * Tạo table để quản lý parsed item data với versioning:
 * - Lưu parsed data từ workers và processors
 * - Quản lý phiên bản (versioning) của parsed item data
 * - Lưu tác nhân đã tạo version (worker, processor 1, processor 2, etc.)
 * - Cho phép kiểm tra lại độ chính xác và thống kê
 * 
 * Run: wp eval-file wp-content/plugins/wp-crawlflow/database/migrations/create-parsed-items-table.php
 */

global $wpdb;

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║    DATABASE MIGRATION: Create Parsed Items Table                       ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

$table_name = $wpdb->prefix . 'rake_data_parsed_items';
$origins_table = $wpdb->prefix . 'rake_data_origins';

$charset_collate = $wpdb->get_charset_collate();

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  origin_id bigint(20) NOT NULL COMMENT 'Reference to dpc_rake_data_origins.id',
  version int(11) NOT NULL DEFAULT 1 COMMENT 'Version number of parsed data',
  processor_type varchar(50) NOT NULL COMMENT 'worker, processor, extractor',
  processor_id varchar(255) DEFAULT NULL COMMENT 'ID/name of processor/worker that created this version',
  processor_name varchar(255) DEFAULT NULL COMMENT 'Human-readable name of processor/worker',
  parsed_data longtext NOT NULL COMMENT 'JSON parsed data',
  status varchar(50) DEFAULT 'pending' COMMENT 'pending, processed, failed, skipped',
  has_change tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Flag indicating if data has changed and needs saving',
  metadata text DEFAULT NULL COMMENT 'JSON metadata (errors, warnings, etc.)',
  created_at datetime NOT NULL,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY unique_origin_version (origin_id, version),
  KEY origin_id (origin_id),
  KEY version (version),
  KEY processor_type (processor_type),
  KEY processor_id (processor_id),
  KEY status (status),
  KEY has_change (has_change),
  KEY created_at (created_at)
) {$charset_collate};";

dbDelta($sql);

$exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
if ($exists) {
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    echo "✅ Table {$table_name} created/updated ({$count} rows)\n\n";
    
    // Show structure
    $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}", ARRAY_A);
    echo "📋 Table structure:\n";
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']})\n";
    }
} else {
    echo "❌ Table {$table_name} NOT FOUND\n\n";
}

echo "\n✅ MIGRATION COMPLETED\n";

