<?php
/**
 * Debug Executor Registration
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

echo "=== Debug Executor Registration ===\n\n";

$rake = \Rake\Rake::getInstance();

// Get NodeRegistry
$nodeRegistry = $rake->make('CrawlFlow\Flow\NodeRegistry');

echo "NodeRegistry instance: " . get_class($nodeRegistry) . "\n";

// Get all executors
$executors = $nodeRegistry->getAllExecutors();

echo "Total executors registered: " . count($executors) . "\n\n";

foreach ($executors as $executor) {
    echo "Executor: " . get_class($executor) . "\n";
    
    // Test which types it supports
    $types = ['start', 'html-data-extractor', 'processor', 'worker'];
    $supported = [];
    
    foreach ($types as $type) {
        if ($executor->supports($type)) {
            $supported[] = $type;
        }
    }
    
    echo "  Supports: " . implode(', ', $supported) . "\n\n";
}

// Test specific lookups
echo "--- Test Executor Lookup ---\n";
$testTypes = ['start', 'html-data-extractor', 'processor'];

foreach ($testTypes as $type) {
    $executor = $nodeRegistry->getExecutor($type);
    if ($executor) {
        echo "✓ {$type}: " . get_class($executor) . "\n";
    } else {
        echo "✗ {$type}: NOT FOUND\n";
    }
}

