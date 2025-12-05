<?php
/**
 * Database Migration: Create Resource References Tables
 * 
 * Creates tables for resource management:
 * - rake_data_sources: Store all URLs from crawled domains
 * - rake_url_source_maps: Map source URLs to WordPress objects
 * - rake_resources: Store resource metadata and import status
 * 
 * Run: wp eval-file wp-content/plugins/wp-crawlflow/database/migrations/create-resource-references-tables.php
 */

global $wpdb;

$charset_collate = $wpdb->get_charset_collate();

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║         DATABASE MIGRATION: Create Resource References Tables             ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

// Table 1: rake_data_sources
// Stores all URLs from same domain (links, images, videos, etc.)
echo "📋 Creating table: {$wpdb->prefix}rake_data_sources\n";
echo str_repeat('─', 80) . "\n";

$table_data_sources = $wpdb->prefix . 'rake_data_sources';
$sql_data_sources = "CREATE TABLE IF NOT EXISTS {$table_data_sources} (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  domain varchar(255) NOT NULL,
  url text NOT NULL,
  url_hash varchar(64) NOT NULL COMMENT 'SHA256 hash of URL for indexing',
  resource_type varchar(50) DEFAULT NULL COMMENT 'image, video, audio, link, etc.',
  mime_type varchar(100) DEFAULT NULL,
  file_size bigint(20) DEFAULT NULL,
  status varchar(20) DEFAULT 'pending' COMMENT 'pending, processing, completed, failed',
  project_id bigint(20) DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY url_hash (url_hash),
  KEY domain (domain),
  KEY resource_type (resource_type),
  KEY status (status),
  KEY project_id (project_id)
) {$charset_collate};";

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta($sql_data_sources);
echo "✅ Table {$table_data_sources} created/updated\n\n";

// Table 2: rake_url_source_maps
// Maps source URLs to WordPress objects (posts, attachments, etc.)
echo "📋 Creating table: {$wpdb->prefix}rake_url_source_maps\n";
echo str_repeat('─', 80) . "\n";

$table_url_maps = $wpdb->prefix . 'rake_url_source_maps';
$sql_url_maps = "CREATE TABLE IF NOT EXISTS {$table_url_maps} (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  source_url text NOT NULL,
  source_url_hash varchar(64) NOT NULL COMMENT 'SHA256 hash of source URL',
  object_type varchar(50) NOT NULL COMMENT 'wordpress_post, wordpress_attachment, custom, etc.',
  object_id bigint(20) UNSIGNED NOT NULL,
  metadata text DEFAULT NULL COMMENT 'JSON metadata',
  created_at datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY source_url_hash (source_url_hash),
  KEY object_type_id (object_type, object_id)
) {$charset_collate};";

dbDelta($sql_url_maps);
echo "✅ Table {$table_url_maps} created/updated\n\n";

// Table 3: rake_resources
// Stores resource metadata and import status
echo "📋 Creating table: {$wpdb->prefix}rake_resources\n";
echo str_repeat('─', 80) . "\n";

$table_resources = $wpdb->prefix . 'rake_resources';
$sql_resources = "CREATE TABLE IF NOT EXISTS {$table_resources} (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  url text NOT NULL,
  url_hash varchar(64) NOT NULL COMMENT 'SHA256 hash of URL',
  resource_type varchar(50) DEFAULT NULL COMMENT 'image, video, audio, document, etc.',
  mime_type varchar(100) DEFAULT NULL,
  file_size bigint(20) DEFAULT NULL,
  checksum varchar(64) DEFAULT NULL COMMENT 'File checksum (XXH128 or SHA256)',
  wordpress_id bigint(20) DEFAULT NULL COMMENT 'WordPress attachment ID',
  project_id bigint(20) DEFAULT NULL,
  imported_at datetime DEFAULT NULL COMMENT 'Timestamp when successfully imported',
  created_at datetime NOT NULL,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY url_hash (url_hash),
  KEY checksum (checksum),
  KEY wordpress_id (wordpress_id),
  KEY project_id (project_id),
  KEY imported_at (imported_at)
) {$charset_collate};";

dbDelta($sql_resources);
echo "✅ Table {$table_resources} created/updated\n\n";

// Verify tables
echo "📋 Verifying tables...\n";
echo str_repeat('─', 80) . "\n";

$tables = [
    $table_data_sources,
    $table_url_maps,
    $table_resources
];

foreach ($tables as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        echo "✅ {$table}: EXISTS ({$count} rows)\n";
    } else {
        echo "❌ {$table}: NOT FOUND\n";
    }
}

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║         ✅ MIGRATION COMPLETED                                            ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Tables created:\n";
echo "- {$wpdb->prefix}rake_data_sources: Store all URLs from crawled domains\n";
echo "- {$wpdb->prefix}rake_url_source_maps: Map source URLs to WordPress objects\n";
echo "- {$wpdb->prefix}rake_resources: Store resource metadata and import status\n";
echo "\n";
echo "Usage:\n";
echo "1. Crawl website → URLs saved to rake_data_sources\n";
echo "2. Run CreateResourceReferencesAction → Import media files\n";
echo "3. Check rake_resources.imported_at for import status\n";
echo "4. URL mappings in rake_url_source_maps for old → new URL references\n";
echo "\n";


