<?php
/**
 * Test SimpleBlog OceanWP Crawl
 * Test crawling posts from https://simpleblog.oceanwp.org/blog/
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

echo "=== SimpleBlog OceanWP Crawl Test ===\n\n";

try {
    // Load flow config
    $configFile = __DIR__ . '/../examples/simpleblog-oceanwp-config.json';
    
    if (!file_exists($configFile)) {
        throw new \RuntimeException("Config file not found: {$configFile}");
    }
    
    $flowConfig = json_decode(file_get_contents($configFile), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \RuntimeException("Invalid JSON in config file");
    }
    
    echo "✓ Loaded flow configuration\n";
    echo "  Nodes: " . count($flowConfig['nodes']) . "\n";
    echo "  Edges: " . count($flowConfig['edges']) . "\n\n";
    
    // Get services
    $rake = \Rake\Rake::getInstance();
    $flowService = $rake->make('CrawlFlow\Flow\FlowService');
    
    echo "--- Executing Flow ---\n";
    echo "Target: https://simpleblog.oceanwp.org/blog/\n";
    echo "Starting crawl...\n\n";
    
    $startTime = microtime(true);
    
    // Execute flow
    $context = $flowService->executeFlow($flowConfig);
    
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);
    
    // Get results
    $result = $context->getResult();
    
    echo "--- Execution Complete ---\n";
    echo "Time: {$executionTime}s\n";
    echo "Status: " . ($result['completed'] ? "✓ COMPLETED" : "✗ INCOMPLETE") . "\n\n";
    
    // Display statistics
    echo "--- Statistics ---\n";
    echo "Repository (URLs found): {$result['repository_count']}\n";
    echo "Extracted (Data items): {$result['extracted_count']}\n";
    echo "Processed (Saved): {$result['processed_count']}\n";
    echo "Errors: " . count($result['errors']) . "\n";
    echo "Logs: " . count($result['logs']) . "\n\n";
    
    // Show extracted data sample
    if ($result['extracted_count'] > 0) {
        echo "--- Extracted Data Sample ---\n";
        $extractedData = $context->getExtractedData();
        $sample = array_slice($extractedData, 0, 3);
        
        foreach ($sample as $i => $item) {
            echo "\nPost " . ($i + 1) . ":\n";
            echo "  Title: " . ($item['title'] ?? 'N/A') . "\n";
            echo "  Excerpt: " . substr($item['excerpt'] ?? 'N/A', 0, 80) . "...\n";
            echo "  Author: " . ($item['author'] ?? 'N/A') . "\n";
            echo "  Date: " . ($item['date'] ?? 'N/A') . "\n";
            echo "  URL: " . ($item['source_url'] ?? 'N/A') . "\n";
        }
        
        if ($result['extracted_count'] > 3) {
            echo "\n... and " . ($result['extracted_count'] - 3) . " more items\n";
        }
    }
    
    // Show processed results
    if ($result['processed_count'] > 0) {
        echo "\n--- Processed Results ---\n";
        $processedResults = $context->getProcessedResults();
        
        foreach (array_slice($processedResults, 0, 5) as $item) {
            $postId = $item['post_id'] ?? $item['id'] ?? 'N/A';
            $title = $item['title'] ?? 'N/A';
            $action = $item['action'] ?? 'saved';
            echo "  [{$action}] Post ID: {$postId} - {$title}\n";
        }
    }
    
    // Show errors if any
    if (!empty($result['errors'])) {
        echo "\n--- Errors ---\n";
        foreach (array_slice($result['errors'], 0, 5) as $error) {
            echo "  ✗ " . $error['message'] . "\n";
            if (isset($error['node_id'])) {
                echo "    Node: {$error['node_id']}\n";
            }
        }
    }
    
    // Show recent logs
    echo "\n--- Recent Logs ---\n";
    foreach (array_slice($result['logs'], -10) as $log) {
        $level = strtoupper($log['level']);
        echo "  [{$level}] {$log['message']}\n";
    }
    
    echo "\n=== Test Complete ===\n";
    
    if ($result['completed'] && $result['processed_count'] > 0) {
        echo "✓ Successfully crawled and saved posts from SimpleBlog OceanWP\n";
        exit(0);
    } elseif ($result['completed']) {
        echo "✓ Flow completed but no posts were saved\n";
        echo "  This might be expected if no new posts found\n";
        exit(0);
    } else {
        echo "✗ Flow did not complete successfully\n";
        exit(1);
    }
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

