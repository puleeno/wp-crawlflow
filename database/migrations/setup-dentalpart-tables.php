<?php
/**
 * Setup Database Tables for DentalPart Crawler
 * 
 * Chạy tất cả migrations cần thiết:
 * 1. Create dpc_rake_data_origins_references table
 * 
 * Run: wp eval-file wp-content/plugins/wp-crawlflow/database/migrations/setup-dentalpart-tables.php
 */

global $wpdb;

$charset_collate = $wpdb->get_charset_collate();

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║         SETUP DATABASE TABLES FOR DENTALPART CRAWLER                    ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

// Table: dpc_rake_data_origins_references
echo "📋 Creating table: {$wpdb->prefix}rake_data_origins_references\n";
echo str_repeat('─', 80) . "\n";

$table_name = $wpdb->prefix . 'rake_data_origins_references';
$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_url text NOT NULL COMMENT 'Parent URL (e.g., category URL)',
  parent_url_hash varchar(64) NOT NULL COMMENT 'SHA256 hash of parent URL',
  child_url text NOT NULL COMMENT 'Child URL (e.g., product URL or resource URL)',
  child_url_hash varchar(64) NOT NULL COMMENT 'SHA256 hash of child URL',
  relationship_type varchar(50) DEFAULT 'parent_child' COMMENT 'parent_child, category_product, parent_resource, etc.',
  source_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Reference to dpc_rake_data_origins.id',
  metadata text DEFAULT NULL COMMENT 'JSON metadata',
  created_at datetime NOT NULL,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY unique_parent_child (parent_url_hash, child_url_hash),
  KEY parent_url_hash (parent_url_hash),
  KEY child_url_hash (child_url_hash),
  KEY relationship_type (relationship_type),
  KEY source_id (source_id)
) {$charset_collate};";

dbDelta($sql);

$exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
if ($exists) {
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    echo "✅ {$table_name}: EXISTS ({$count} rows)\n\n";
} else {
    echo "❌ {$table_name}: NOT FOUND\n\n";
}

// Verify rake_data_sources table exists (for resources)
$table_sources = $wpdb->prefix . 'rake_data_sources';
$exists_sources = $wpdb->get_var("SHOW TABLES LIKE '{$table_sources}'");
if ($exists_sources) {
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_sources}");
    echo "✅ {$table_sources}: EXISTS ({$count} rows)\n\n";
} else {
    echo "⚠️  {$table_sources}: NOT FOUND (will be created by CreateResourceReferencesAction)\n\n";
}

echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║         ✅ MIGRATION COMPLETED                                            ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n";
echo "\n";
