<?php
/**
 * Test Cronjob Execution
 * Tests if a project can be loaded and executed via cronjob
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

// Load plugin
require_once __DIR__ . '/../wp-crawlflow.php';

// Get project ID from command line
$projectId = $argv[1] ?? null;

if (!$projectId) {
    echo "Usage: php test-cronjob-execution.php <project_id>\n";
    echo "Example: php test-cronjob-execution.php 1\n";
    exit(1);
}

echo "=== WP-CrawlFlow Cronjob Execution Test ===\n\n";
echo "Testing project ID: {$projectId}\n\n";

try {
    // Get Rake instance
    $rake = \Rake\Rake::getInstance();
    
    echo "✓ Rake instance loaded\n";
    
    // Get services from container
    $projectService = $rake->make('CrawlFlow\Admin\ProjectService');
    $flowService = $rake->make('CrawlFlow\Flow\FlowService');
    
    echo "✓ Services resolved from container\n";
    
    // 1. Load project
    echo "\n--- Step 1: Load Project ---\n";
    $project = $projectService->getProject((int)$projectId);
    
    if (!$project) {
        throw new \RuntimeException("Project {$projectId} not found");
    }
    
    echo "✓ Project loaded: {$project['name']}\n";
    echo "  Status: {$project['status']}\n";
    
    // 2. Get flow configuration
    echo "\n--- Step 2: Get Flow Configuration ---\n";
    $flowConfig = $projectService->getFlowConfig((int)$projectId);
    
    if (!$flowConfig) {
        throw new \RuntimeException("Flow config not found for project {$projectId}");
    }
    
    echo "✓ Flow config loaded\n";
    echo "  Nodes: " . count($flowConfig['nodes'] ?? []) . "\n";
    echo "  Edges: " . count($flowConfig['edges'] ?? []) . "\n";
    
    // Verify flow has start node
    $hasStartNode = false;
    $nodes = isset($flowConfig['nodes']) ? $flowConfig['nodes'] : [];
    foreach ($nodes as $node) {
        $nodeType = isset($node['type']) ? $node['type'] : '';
        if ($nodeType === 'start') {
            $hasStartNode = true;
            echo "  Start Node ID: {$node['id']}\n";
            $sourceValue = isset($node['data']['sourceValue']) ? $node['data']['sourceValue'] : 'N/A';
            echo "  Source: {$sourceValue}\n";
            break;
        }
    }
    
    if (!$hasStartNode) {
        throw new \RuntimeException("Flow must have a start node");
    }
    
    // 3. Execute flow
    echo "\n--- Step 3: Execute Flow ---\n";
    echo "Starting flow execution...\n";
    
    $startTime = microtime(true);
    
    $context = $flowService->executeFlow($flowConfig);
    
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 3);
    
    echo "✓ Flow execution completed\n";
    echo "  Execution time: {$executionTime}s\n";
    
    // 4. Check results
    echo "\n--- Step 4: Check Results ---\n";
    
    $result = $context->getResult();
    
    echo "Execution Summary:\n";
    echo "  Completed: " . ($result['completed'] ? 'Yes' : 'No') . "\n";
    echo "  Repository count: {$result['repository_count']}\n";
    echo "  Extracted count: {$result['extracted_count']}\n";
    echo "  Processed count: {$result['processed_count']}\n";
    echo "  Errors: " . count($result['errors']) . "\n";
    echo "  Logs: " . count($result['logs']) . "\n";
    
    // Show errors if any
    if (!empty($result['errors'])) {
        echo "\n⚠ Errors:\n";
        foreach ($result['errors'] as $error) {
            echo "  - {$error['message']}" . 
                 ($error['node_id'] ? " (Node: {$error['node_id']})" : "") . "\n";
        }
    }
    
    // Show sample logs
    echo "\n📝 Sample Logs (last 5):\n";
    $logs = array_slice($result['logs'], -5);
    foreach ($logs as $log) {
        echo "  [{$log['level']}] {$log['message']}\n";
    }
    
    // 5. Verify execution success
    echo "\n--- Step 5: Verify Execution ---\n";
    
    if ($context->hasErrors()) {
        echo "⚠ Flow executed with errors\n";
        $status = 'completed_with_errors';
    } elseif ($result['completed']) {
        echo "✓ Flow executed successfully\n";
        $status = 'completed_successfully';
    } else {
        echo "✗ Flow execution incomplete\n";
        $status = 'incomplete';
    }
    
    echo "\n=== Cronjob Test Complete ===\n";
    echo "Status: {$status}\n";
    echo "Project can " . ($result['completed'] ? "✓" : "✗") . " be executed via cronjob\n";
    
    // Return appropriate exit code
    exit($result['completed'] ? 0 : 1);
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

