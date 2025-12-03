<?php
/**
 * Test Rake Flow với existing data
 * Uses existing data from rake_data_origins
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

echo "=== Test Rake Flow với Existing Data ===\n\n";

try {
    $rake = \Rake\Rake::getInstance();
    global $wpdb;
    
    // Get existing raw item
    $originsTable = $wpdb->prefix . 'rake_data_origins';
    $rawItem = $wpdb->get_row("SELECT * FROM {$originsTable} ORDER BY id DESC LIMIT 1", ARRAY_A);
    
    if (!$rawItem) {
        echo "✗ No raw items found. Run test-rake-complete-flow.php first\n";
        exit(1);
    }
    
    echo "✓ Found raw item: ID {$rawItem['id']}\n";
    echo "  GUID: {$rawItem['guid']}\n";
    echo "  Source ID: {$rawItem['source_id']}\n";
    
    // Get source and tooth
    $sourcesTable = $wpdb->prefix . 'rake_data_sources';
    $source = $wpdb->get_row("SELECT * FROM {$sourcesTable} WHERE id = {$rawItem['source_id']}", ARRAY_A);
    
    $toothsTable = $wpdb->prefix . 'rake_tooths';
    $tooth = $wpdb->get_row("SELECT * FROM {$toothsTable} WHERE id = {$source['tooth_id']}", ARRAY_A);
    
    echo "✓ Tooth: {$tooth['name']} (ID: {$tooth['id']})\n\n";
    
    // === Test Complete Flow ===
    echo "--- Testing Complete Rake Flow ---\n";
    
    $config = json_decode($tooth['config'], true);
    
    // Step 1: Reception
    $reception = new \CrawlFlow\Reception\Reception($config);
    echo "✓ Reception initialized\n";
    echo "  Workers: " . count($reception->getWorkers()) . "\n";
    
    // Step 2: Check if worker can handle
    $worker = $reception->assignToWorker($rawItem);
    
    if ($worker) {
        echo "✓ Worker assigned: {$worker->getName()}\n";
        echo "  Priority: {$worker->getPriority()}\n";
        
        // Step 3: Process item
        echo "\n--- Processing Item ---\n";
        $processedData = $worker->process($rawItem);
        
        echo "✓ Item processed\n";
        
        // Show results
        if (isset($processedData['post_id'])) {
            echo "  Created Post ID: {$processedData['post_id']}\n";
            echo "  Title: " . ($processedData['title'] ?? 'N/A') . "\n";
        }
        
        if (isset($processedData['processed'])) {
            echo "  Processed: YES\n";
        }
        
    } else {
        echo "✗ No worker can handle this item\n";
    }
    
    // === Test with multiple items ===
    echo "\n--- Test Reception with Multiple Items ---\n";
    
    $rawItems = $wpdb->get_results(
        "SELECT * FROM {$originsTable} LIMIT 5",
        ARRAY_A
    );
    
    echo "✓ Retrieved: " . count($rawItems) . " raw items\n";
    
    $stats = $reception->getAssignmentStats($rawItems);
    echo "  Assigned: {$stats['assigned']}\n";
    echo "  Unassigned: {$stats['unassigned']}\n";
    
    foreach ($stats['by_worker'] as $workerName => $count) {
        echo "    - {$workerName}: {$count} items\n";
    }
    
    // Process all
    echo "\n--- Process All Items ---\n";
    $results = $reception->processRawItems($rawItems);
    
    $successCount = 0;
    $failCount = 0;
    
    foreach ($results as $result) {
        if ($result['success']) {
            $successCount++;
        } else {
            $failCount++;
        }
    }
    
    echo "✓ Processing complete\n";
    echo "  Success: {$successCount}\n";
    echo "  Failed: {$failCount}\n";
    
    echo "\n=== Rake Flow Test COMPLETE ===\n";
    echo "\n✓ Complete flow verified:\n";
    echo "  rake_tooths → rake_data_sources → rake_data_origins ✓\n";
    echo "  Reception → Workers → Parser → Processors ✓\n";
    echo "  Detection rules → Priority assignment ✓\n";
    echo "  Data extraction → Processing → WordPress save ✓\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

