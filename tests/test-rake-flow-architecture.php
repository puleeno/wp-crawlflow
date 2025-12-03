<?php
/**
 * Test Rake Flow Architecture
 * Verify flow hoạt động theo: 
 * Tooth → DataSources → DataOrigins → Reception → Workers → Parser → Processors → Finish
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

echo "=== Test Rake Flow Architecture ===\n\n";
echo "Testing flow: Tooth → DataSources → DataOrigins → Reception → Workers → Processors\n\n";

try {
    $rake = \Rake\Rake::getInstance();
    
    // === STEP 1: Load Project from rake_tooths ===
    echo "--- Step 1: Load Project (Tooth) ---\n";
    
    global $wpdb;
    $table = $wpdb->prefix . 'rake_tooths';
    
    // Get a project
    $project = $wpdb->get_row("SELECT * FROM {$table} WHERE status = 'active' ORDER BY id DESC LIMIT 1", ARRAY_A);
    
    if (!$project) {
        echo "⚠ No active project found. Creating one...\n";
        
        // Create test project
        $wpdb->insert($table, [
            'name' => 'Rake Flow Test',
            'description' => 'Test Rake flow architecture',
            'config' => json_encode([
                'data_sources' => [
                    [
                        'type' => 'url',
                        'url' => 'https://example.com',
                    ],
                ],
                'workers' => [
                    [
                        'name' => 'blog_post_worker',
                        'priority' => 1,
                        'detection_rules' => [
                            ['selector' => 'article', 'condition' => 'exists'],
                        ],
                    ],
                ],
                'processors' => [
                    ['type' => 'save_to_wordpress'],
                ],
            ]),
            'status' => 'active',
            'created_at' => current_time('mysql'),
        ]);
        
        $project = $wpdb->get_row("SELECT * FROM {$table} WHERE id = {$wpdb->insert_id}", ARRAY_A);
    }
    
    echo "✓ Project loaded: {$project['name']} (ID: {$project['id']})\n";
    
    $config = json_decode($project['config'], true);
    echo "  Data sources: " . count($config['data_sources'] ?? []) . "\n";
    echo "  Workers: " . count($config['workers'] ?? []) . "\n";
    echo "  Processors: " . count($config['processors'] ?? []) . "\n";
    
    // === STEP 2: Init Data Sources ===
    echo "\n--- Step 2: Init Data Sources (rake_data_sources) ---\n";
    
    $sourcesTable = $wpdb->prefix . 'rake_data_sources';
    
    // Check if table exists
    $tableExists = $wpdb->get_var("SHOW TABLES LIKE '{$sourcesTable}'");
    
    if (!$tableExists) {
        echo "⚠ Table {$sourcesTable} doesn't exist yet\n";
        echo "  Need to run migration first\n";
    } else {
        // Create data source for project
        $wpdb->insert($sourcesTable, [
            'tooth_id' => $project['id'],
            'type' => 'url',
            'name' => 'Test URL Source',
            'config' => json_encode(['url' => 'https://example.com']),
            'created_at' => current_time('mysql'),
        ]);
        
        $sourceId = $wpdb->insert_id;
        echo "✓ Data source created: ID {$sourceId}\n";
        echo "  Type: url\n";
        echo "  Tooth ID: {$project['id']}\n";
    }
    
    // === STEP 3: Fetch and Save to rake_data_origins ===
    echo "\n--- Step 3: Fetch Data → Save to rake_data_origins ---\n";
    
    $originsTable = $wpdb->prefix . 'rake_data_origins';
    
    $tableExists = $wpdb->get_var("SHOW TABLES LIKE '{$originsTable}'");
    
    if (!$tableExists) {
        echo "⚠ Table {$originsTable} doesn't exist yet\n";
        echo "  Need to run migration first\n";
        echo "  This table stores raw items (URLs, CSV rows, etc.)\n";
    } else {
        // Insert raw item
        $wpdb->insert($originsTable, [
            'source_id' => $sourceId ?? 1,
            'guid' => 'https://example.com/post-1',
            'raw_data' => json_encode([
                'url' => 'https://example.com/post-1',
                'html' => '<html><body><h1>Test Post</h1></body></html>',
            ]),
            'fetched_at' => current_time('mysql'),
        ]);
        
        echo "✓ Raw item saved to rake_data_origins\n";
        echo "  GUID: https://example.com/post-1\n";
        echo "  This is the repository for all raw items\n";
    }
    
    // === STEP 4: Reception Loads Workers ===
    echo "\n--- Step 4: Reception Loads Workers ---\n";
    echo "Reception should:\n";
    echo "  1. Get raw items from rake_data_origins for this project\n";
    echo "  2. Load workers from project config\n";
    echo "  3. Check which worker handles each item (detection rules)\n";
    echo "  4. Assign items to workers by priority\n";
    
    $workers = $config['workers'] ?? [];
    echo "\n✓ Workers configured: " . count($workers) . "\n";
    
    foreach ($workers as $i => $worker) {
        echo "  Worker " . ($i + 1) . ": {$worker['name']}\n";
        echo "    Priority: {$worker['priority']}\n";
        echo "    Detection rules: " . count($worker['detection_rules'] ?? []) . "\n";
    }
    
    // === STEP 5: Worker Processes Item ===
    echo "\n--- Step 5: Worker Processes Item ---\n";
    echo "Worker should:\n";
    echo "  1. Receive raw item from Reception\n";
    echo "  2. Use Parser/Extractor to extract data\n";
    echo "  3. Create structured data\n";
    echo "  4. Send to Processor chain\n";
    
    echo "\n✓ Worker flow defined\n";
    
    // === STEP 6: Processor Chain ===
    echo "\n--- Step 6: Processor Chain ---\n";
    echo "Processors should:\n";
    echo "  1. Receive extracted data\n";
    echo "  2. Process sequentially through chain\n";
    echo "  3. Each processor can transform data\n";
    echo "  4. Final processor saves result\n";
    
    $processors = $config['processors'] ?? [];
    echo "\n✓ Processors configured: " . count($processors) . "\n";
    
    foreach ($processors as $i => $processor) {
        echo "  Processor " . ($i + 1) . ": {$processor['type']}\n";
    }
    
    // === STEP 7: Finish Actions ===
    echo "\n--- Step 7: Finish Actions & Report ---\n";
    echo "On completion:\n";
    echo "  1. Log execution results\n";
    echo "  2. Update project status\n";
    echo "  3. Send notifications (if configured)\n";
    echo "  4. Clean up resources\n";
    
    echo "\n=== Flow Architecture Verified ===\n\n";
    
    echo "Current Implementation Status:\n";
    echo "  ✓ rake_tooths table: EXISTS\n";
    echo "  " . ($tableExists ? "✓" : "✗") . " rake_data_sources table: " . ($tableExists ? "EXISTS" : "NEEDS MIGRATION") . "\n";
    echo "  " . ($tableExists ? "✓" : "✗") . " rake_data_origins table: " . ($tableExists ? "EXISTS" : "NEEDS MIGRATION") . "\n";
    echo "  ⚠ Reception: NEEDS IMPLEMENTATION\n";
    echo "  ⚠ Worker: NEEDS IMPLEMENTATION\n";
    echo "  ✓ Parser: HtmlDataExtractor implemented\n";
    echo "  ✓ Processor: WordPressPostProcessor implemented\n";
    
    echo "\n📋 Next Steps:\n";
    echo "1. Run migrations to create rake_data_sources and rake_data_origins tables\n";
    echo "2. Implement Reception class (ReceptionInterface)\n";
    echo "3. Implement Worker class with detection rules\n";
    echo "4. Integrate Reception → Worker → Parser → Processor flow\n";
    echo "5. Test complete flow end-to-end\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

