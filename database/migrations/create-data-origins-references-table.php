<?php
/**
 * Database Migration: Create Data Origins References Table
 * 
 * Creates table for managing parent-child relationships from original data sources.
 * Stores URLs to track relationships between categories and products.
 * 
 * Run: wp eval-file wp-content/plugins/wp-crawlflow/database/migrations/create-data-origins-references-table.php
 */

global $wpdb;

$charset_collate = $wpdb->get_charset_collate();

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║    DATABASE MIGRATION: Create Data Origins References Table              ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

// Table: dpc_rake_data_origins_references
$table_name = $wpdb->prefix . 'rake_data_origins_references';
$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_url text NOT NULL COMMENT 'Parent URL (e.g., category URL)',
  parent_url_hash varchar(64) NOT NULL COMMENT 'SHA256 hash of parent URL',
  child_url text NOT NULL COMMENT 'Child URL (e.g., product URL)',
  child_url_hash varchar(64) NOT NULL COMMENT 'SHA256 hash of child URL',
  relationship_type varchar(50) DEFAULT 'parent_child' COMMENT 'parent_child, category_product, etc.',
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

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta($sql);
echo "✅ Table {$table_name} created/updated\n\n";

$exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
if ($exists) {
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    echo "✅ {$table_name}: EXISTS ({$count} rows)\n";
} else {
    echo "❌ {$table_name}: NOT FOUND\n";
}

echo "\n✅ MIGRATION COMPLETED\n";
