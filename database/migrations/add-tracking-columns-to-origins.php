<?php
/**
 * Database Migration: Add Tracking Columns to Data Origins Table
 * 
 * Thêm các cột tracking và analytics:
 * - created_at: Timestamp khi origin được tạo
 * - updated_at: Timestamp khi origin được cập nhật
 * - crawled: tinyint(1) - Flag để tracking xem URL đã được crawl chưa
 * 
 * Run: wp eval-file wp-content/plugins/wp-crawlflow/database/migrations/add-tracking-columns-to-origins.php
 */

global $wpdb;

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║    DATABASE MIGRATION: Add Tracking Columns to Data Origins Table     ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

$table_name = $wpdb->prefix . 'rake_data_origins';
$charset_collate = $wpdb->get_charset_collate();

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

// Check current structure
$columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}", ARRAY_A);
$column_names = array_column($columns, 'Field');

echo "📋 Current columns: " . implode(', ', $column_names) . "\n\n";

// Add created_at if not exists
if (!in_array('created_at', $column_names)) {
    echo "➕ Adding created_at column...\n";
    $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER fetched_at");
    
    // Update existing rows with fetched_at value
    $wpdb->query("UPDATE {$table_name} SET created_at = fetched_at WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00'");
    echo "✅ created_at column added\n\n";
} else {
    echo "✅ created_at column already exists\n\n";
}

// Add updated_at if not exists
if (!in_array('updated_at', $column_names)) {
    echo "➕ Adding updated_at column...\n";
    $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
    echo "✅ updated_at column added\n\n";
} else {
    echo "✅ updated_at column already exists\n\n";
}

// Add crawled flag if not exists
if (!in_array('crawled', $column_names)) {
    echo "➕ Adding crawled column...\n";
    $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN crawled tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Flag indicating if URL has been crawled (has raw_data)' AFTER updated_at");
    
    // Set crawled = 1 for rows that have raw_data
    $wpdb->query("UPDATE {$table_name} SET crawled = 1 WHERE raw_data IS NOT NULL AND raw_data != ''");
    echo "✅ crawled column added\n\n";
} else {
    echo "✅ crawled column already exists\n\n";
}

// Add metadata column if not exists
if (!in_array('metadata', $column_names)) {
    echo "➕ Adding metadata column...\n";
    $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN metadata text DEFAULT NULL COMMENT 'JSON metadata for extensibility' AFTER crawled");
    echo "✅ metadata column added\n\n";
} else {
    echo "✅ metadata column already exists\n\n";
}

// Add indexes for better performance
echo "📊 Adding indexes...\n";
$indexes = $wpdb->get_results("SHOW INDEXES FROM {$table_name}", ARRAY_A);
$index_names = array_column($indexes, 'Key_name');

if (!in_array('idx_created_at', $index_names)) {
    $wpdb->query("ALTER TABLE {$table_name} ADD INDEX idx_created_at (created_at)");
    echo "✅ Index idx_created_at added\n";
}

if (!in_array('idx_updated_at', $index_names)) {
    $wpdb->query("ALTER TABLE {$table_name} ADD INDEX idx_updated_at (updated_at)");
    echo "✅ Index idx_updated_at added\n";
}

if (!in_array('idx_crawled', $index_names)) {
    $wpdb->query("ALTER TABLE {$table_name} ADD INDEX idx_crawled (crawled)");
    echo "✅ Index idx_crawled added\n";
}

// Note: metadata is TEXT, no index needed (can add fulltext index later if needed)

echo "\n";

// Show final structure
echo "📋 Final table structure:\n";
$columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}", ARRAY_A);
foreach ($columns as $col) {
    $null = $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
    $default = $col['Default'] !== null ? " DEFAULT '{$col['Default']}'" : '';
    echo "  - {$col['Field']} ({$col['Type']}) {$null}{$default}\n";
}

$count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
$crawled_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE crawled = 1");
echo "\n✅ {$table_name}: {$count} rows ({$crawled_count} crawled)\n\n";

echo "✅ MIGRATION COMPLETED\n";

