<?php
/**
 * Test Crawl OceanWP Blog
 * Creates a project to crawl posts from https://simpleblog.oceanwp.org/blog/
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

echo "=== Crawl OceanWP Blog Test ===\n\n";

try {
    // Get services
    $rake = \Rake\Rake::getInstance();
    $projectService = $rake->make('CrawlFlow\Admin\ProjectService');
    $flowService = $rake->make('CrawlFlow\Flow\FlowService');
    
    echo "--- Creating Crawl Project ---\n";
    
    // Create project to crawl OceanWP blog
    $projectData = [
        'name' => 'OceanWP Blog Crawler - ' . date('H:i:s'),
        'description' => 'Crawl posts from https://simpleblog.oceanwp.org/blog/',
        'status' => 'active',
        'project_data' => [
            'projectSettings' => [
                'name' => 'OceanWP Blog Crawler',
                'enabled' => true,
                'scheduleType' => 'interval',
                'scheduleInterval' => 60, // Every hour
                'crawlDelay' => 2000,
                'userAgent' => 'CrawlFlow/2.0 (WordPress Plugin)',
                'concurrency' => 1,
            ],
            'nodes' => [
                // Start node - Fetch blog page
                [
                    'id' => '1',
                    'type' => 'start',
                    'position' => ['x' => 0, 'y' => 0],
                    'data' => [
                        'sourceType' => 'url',
                        'sourceValue' => 'https://simpleblog.oceanwp.org/blog/',
                        'urlSettings' => [
                            'scope' => 'single-page',
                            'excludeExtensions' => ['pdf', 'jpg', 'png', 'zip'],
                        ],
                    ],
                ],
                // Repository node
                [
                    'id' => 'repository-node',
                    'type' => 'repository',
                    'position' => ['x' => 0, 'y' => 200],
                    'data' => [],
                ],
                // HTML Data Extractor - Extract blog posts
                [
                    'id' => '2',
                    'type' => 'html-data-extractor',
                    'position' => ['x' => 0, 'y' => 400],
                    'data' => [
                        'presets' => ['blog-posts'], // Use blog post preset
                        'customRules' => [
                            [
                                'name' => 'title',
                                'selector' => 'article h2.entry-title, article h3.entry-title',
                                'extract' => 'text',
                            ],
                            [
                                'name' => 'url',
                                'selector' => 'article h2.entry-title a, article h3.entry-title a',
                                'extract' => 'href',
                            ],
                            [
                                'name' => 'excerpt',
                                'selector' => 'article .entry-excerpt, article .entry-content',
                                'extract' => 'text',
                            ],
                            [
                                'name' => 'image',
                                'selector' => 'article img',
                                'extract' => 'src',
                            ],
                            [
                                'name' => 'date',
                                'selector' => 'article time, article .posted-on',
                                'extract' => 'text',
                            ],
                        ],
                    ],
                ],
                // Processor - Save to WordPress
                [
                    'id' => '3',
                    'type' => 'processor',
                    'position' => ['x' => 0, 'y' => 600],
                    'data' => [
                        'processorType' => 'save-to-wordpress',
                        'settings' => [
                            'postType' => 'post',
                            'postStatus' => 'draft',
                            'authorId' => 1,
                            'updateIfExists' => true,
                            'categories' => [], // Add category IDs if needed
                        ],
                    ],
                ],
                // Completion node
                [
                    'id' => 'completion-node',
                    'type' => 'completion',
                    'position' => ['x' => 0, 'y' => 800],
                    'data' => [],
                ],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => '1', 'target' => 'repository-node'],
                ['id' => 'e2', 'source' => 'repository-node', 'target' => '2'],
                ['id' => 'e3', 'source' => '2', 'target' => '3'],
                ['id' => 'e4', 'source' => '3', 'target' => 'completion-node'],
            ],
        ],
    ];
    
    $projectId = $projectService->createProject($projectData);
    echo "✓ Project created: ID {$projectId}\n";
    
    // Verify project
    $project = $projectService->getProject($projectId);
    echo "✓ Project verified: {$project['name']}\n";
    
    // Get flow config
    $flowConfig = $projectService->getFlowConfig($projectId);
    echo "✓ Flow config loaded\n";
    echo "  Nodes: " . count($flowConfig['nodes']) . "\n";
    echo "  Edges: " . count($flowConfig['edges']) . "\n";
    
    // Execute flow immediately (test)
    echo "\n--- Executing Flow ---\n";
    echo "Fetching: https://simpleblog.oceanwp.org/blog/\n";
    
    $startTime = microtime(true);
    $context = $flowService->executeFlow($flowConfig);
    $endTime = microtime(true);
    
    $executionTime = round($endTime - $startTime, 2);
    
    echo "✓ Flow executed in {$executionTime}s\n";
    
    // Get results
    $result = $context->getResult();
    
    echo "\n--- Execution Results ---\n";
    echo "Completed: " . ($result['completed'] ? 'YES' : 'NO') . "\n";
    echo "Repository: {$result['repository_count']} items\n";
    echo "Extracted: {$result['extracted_count']} items\n";
    echo "Processed: {$result['processed_count']} items\n";
    echo "Errors: " . count($result['errors']) . "\n";
    
    // Show errors
    if (!empty($result['errors'])) {
        echo "\n⚠ Errors:\n";
        foreach ($result['errors'] as $error) {
            echo "  - {$error['message']}\n";
        }
    }
    
    // Show sample logs
    echo "\n📝 Execution Logs:\n";
    foreach (array_slice($result['logs'], 0, 10) as $log) {
        echo "  [{$log['level']}] {$log['message']}\n";
    }
    
    // Show extracted data sample
    $extractedData = $context->getExtractedData();
    if (!empty($extractedData)) {
        echo "\n📄 Sample Extracted Data (first item):\n";
        $sample = $extractedData[0];
        foreach ($sample as $key => $value) {
            if (is_string($value)) {
                $displayValue = strlen($value) > 100 ? substr($value, 0, 100) . '...' : $value;
                echo "  {$key}: {$displayValue}\n";
            }
        }
    }
    
    // Show processed results
    $processedResults = $context->getProcessedResults();
    if (!empty($processedResults)) {
        echo "\n✅ Processed Results:\n";
        foreach ($processedResults as $result) {
            if (isset($result['success']) && $result['success']) {
                echo "  ✓ Post ID: {$result['post_id']} - {$result['title']}\n";
            } else {
                echo "  ✗ Failed: {$result['title']} - {$result['error']}\n";
            }
        }
    }
    
    echo "\n=== Test Complete ===\n";
    echo "Project ID: {$projectId}\n";
    echo "Status: " . ($result['completed'] ? "✓ SUCCESS" : "⚠ WITH ERRORS") . "\n";
    
    if ($result['completed']) {
        echo "\n✓ Blog posts đã được crawl và import vào WordPress!\n";
        echo "Check WordPress admin → Posts để xem kết quả.\n";
    }
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

