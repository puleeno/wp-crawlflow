<?php
/**
 * Migration: Create rake_file_checksums table
 * 
 * Run: wp eval-file wp-content/plugins/wp-crawlflow/database/migrations/create-file-checksums-table.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

global $wpdb;

$tableName = $wpdb->prefix . 'rake_file_checksums';

$charsetCollate = $wpdb->get_charset_collate();

$sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    resource_id bigint(20) UNSIGNED NOT NULL,
    checksum varchar(32) NOT NULL,
    app_new_type varchar(50) DEFAULT NULL,
    app_new_guid varchar(255) DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY resource_checksum (resource_id, checksum),
    KEY checksum (checksum),
    KEY resource_id (resource_id),
    KEY app_new_guid (app_new_guid)
) {$charsetCollate};";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
dbDelta($sql);

echo "✅ Table {$tableName} created/updated\n";
