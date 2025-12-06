<?php
/**
 * Database Migration: Refactor References Table
 * 
 * Refactor dpc_rake_data_origins_references để chỉ quản lý quan hệ:
 * - Remove: parent_url, parent_url_hash, child_url, child_url_hash, source_id, metadata
 * - Add: parent_origin_id, child_origin_id (link đến dpc_rake_data_origins.id)
 * 
 * Run: wp eval-file wp-content/plugins/wp-crawlflow/database/migrations/refactor-references-table.php
 */

global $wpdb;

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║    DATABASE MIGRATION: Refactor References Table                       ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

$table_name = $wpdb->prefix . 'rake_data_origins_references';
$origins_table = $wpdb->prefix . 'rake_data_origins';

// Check if table exists
$exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
if (!$exists) {
    echo "❌ Table {$table_name} does not exist. Creating new table...\n\n";
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE {$table_name} (
      id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      parent_origin_id bigint(20) NOT NULL COMMENT 'Reference to dpc_rake_data_origins.id (parent)',
      child_origin_id bigint(20) NOT NULL COMMENT 'Reference to dpc_rake_data_origins.id (child)',
      relationship_type varchar(50) DEFAULT 'parent_child' COMMENT 'parent_child, category_product, etc.',
      created_at datetime NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY unique_parent_child (parent_origin_id, child_origin_id),
      KEY parent_origin_id (parent_origin_id),
      KEY child_origin_id (child_origin_id),
      KEY relationship_type (relationship_type)
    ) {$charset_collate};";
    
    dbDelta($sql);
    echo "✅ Table {$table_name} created\n\n";
} else {
    echo "📋 Refactoring existing table {$table_name}...\n\n";
    
    // Check if already refactored
    $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}", ARRAY_A);
    $has_parent_origin_id = false;
    $has_parent_url = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'parent_origin_id') {
            $has_parent_origin_id = true;
        }
        if ($column['Field'] === 'parent_url') {
            $has_parent_url = true;
        }
    }
    
    if ($has_parent_origin_id && !$has_parent_url) {
        echo "✅ Table already refactored. No changes needed.\n\n";
    } else {
        echo "⚠️  Migrating data from old schema to new schema...\n";
        
        // Step 1: Create new table with new schema
        $temp_table = $table_name . '_new';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$temp_table} (
          id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
          parent_origin_id bigint(20) NOT NULL,
          child_origin_id bigint(20) NOT NULL,
          relationship_type varchar(50) DEFAULT 'parent_child',
          created_at datetime NOT NULL,
          PRIMARY KEY (id),
          UNIQUE KEY unique_parent_child (parent_origin_id, child_origin_id),
          KEY parent_origin_id (parent_origin_id),
          KEY child_origin_id (child_origin_id),
          KEY relationship_type (relationship_type)
        ) {$charset_collate};";
        
        $wpdb->query($sql);
        echo "✅ Created temporary table {$temp_table}\n";
        
        // Step 2: Migrate data
        if ($has_parent_url) {
            echo "📦 Migrating data from old schema...\n";
            
            // Get all references and find corresponding origin IDs
            $references = $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);
            $migrated = 0;
            $skipped = 0;
            
            foreach ($references as $ref) {
                $parentUrl = $ref['parent_url'] ?? '';
                $childUrl = $ref['child_url'] ?? '';
                $relationshipType = $ref['relationship_type'] ?? 'parent_child';
                $createdAt = $ref['created_at'] ?? current_time('mysql');
                
                if (empty($parentUrl) || empty($childUrl)) {
                    $skipped++;
                    continue;
                }
                
                // Find parent origin
                $parentOrigin = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$origins_table} WHERE guid = %s LIMIT 1",
                    $parentUrl
                ), ARRAY_A);
                
                // Find or create child origin
                $childOrigin = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$origins_table} WHERE guid = %s LIMIT 1",
                    $childUrl
                ), ARRAY_A);
                
                if (!$childOrigin) {
                    // Create child origin if not exists (just URL, no data yet)
                    $wpdb->insert($origins_table, [
                        'source_id' => $ref['source_id'] ?? 0,
                        'guid' => $childUrl,
                        'raw_data' => '',
                        'fetched_at' => current_time('mysql'),
                    ]);
                    $childOriginId = $wpdb->insert_id;
                } else {
                    $childOriginId = $childOrigin['id'];
                }
                
                if ($parentOrigin && $childOriginId) {
                    $wpdb->insert($temp_table, [
                        'parent_origin_id' => $parentOrigin['id'],
                        'child_origin_id' => $childOriginId,
                        'relationship_type' => $relationshipType,
                        'created_at' => $createdAt,
                    ]);
                    $migrated++;
                } else {
                    $skipped++;
                }
            }
            
            echo "✅ Migrated {$migrated} records, skipped {$skipped} records\n";
        }
        
        // Step 3: Drop old table and rename new table
        echo "🔄 Replacing old table with new schema...\n";
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        $wpdb->query("RENAME TABLE {$temp_table} TO {$table_name}");
        echo "✅ Table refactored successfully\n\n";
    }
}

// Verify final structure
$columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}", ARRAY_A);
echo "📋 Final table structure:\n";
foreach ($columns as $column) {
    echo "  - {$column['Field']} ({$column['Type']})\n";
}

$count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
echo "\n✅ {$table_name}: {$count} rows\n";
echo "\n✅ MIGRATION COMPLETED\n";

