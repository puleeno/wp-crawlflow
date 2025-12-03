<?php
/**
 * Setup Test Project
 * Creates a test project in database for testing cronjob execution
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

// Load plugin
require_once __DIR__ . '/../wp-crawlflow.php';

echo "=== WP-CrawlFlow Test Project Setup ===\n\n";

try {
    // Get Rake instance
    $rake = \Rake\Rake::getInstance();
    
    echo "✓ Rake instance loaded\n";
    
    // Get ProjectService from container
    $projectService = $rake->make('CrawlFlow\Admin\ProjectService');
    
    echo "✓ ProjectService resolved from container\n";
    
    // Create test project with flow configuration
    $projectData = [
        'name' => 'Test Crawl Project - ' . date('Y-m-d H:i:s'),
        'description' => 'Automated test project for cronjob execution',
        'status' => 'active',
        'project_data' => [
            'projectSettings' => [
                'name' => 'Test Crawl Project',
                'description' => 'Test project',
                'enabled' => true,
                'crawlDelay' => 1000,
                'userAgent' => 'CrawlFlow-Test/2.0',
                'concurrency' => 2,
            ],
            'nodes' => [
                // Start node
                [
                    'id' => '1',
                    'type' => 'start',
                    'position' => ['x' => 0, 'y' => 0],
                    'data' => [
                        'sourceType' => 'url',
                        'sourceValue' => 'https://httpbin.org/html',
                        'urlSettings' => [
                            'scope' => 'single-page',
                            'excludeExtensions' => [],
                            'excludePatterns' => [],
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
                // Worker node
                [
                    'id' => '2',
                    'type' => 'worker',
                    'position' => ['x' => 0, 'y' => 400],
                    'data' => [
                        'detectionRules' => [
                            [
                                'id' => '1',
                                'type' => 'dom-value',
                                'selector' => 'h1',
                                'condition' => 'exists',
                            ],
                        ],
                        'detectionLogic' => 'and',
                        'priority' => 1,
                    ],
                ],
                // Completion node
                [
                    'id' => 'completion-node',
                    'type' => 'completion',
                    'position' => ['x' => 0, 'y' => 600],
                    'data' => [],
                ],
            ],
            'edges' => [
                [
                    'id' => 'e-1-repository',
                    'source' => '1',
                    'target' => 'repository-node',
                ],
                [
                    'id' => 'e-repository-2',
                    'source' => 'repository-node',
                    'target' => '2',
                ],
                [
                    'id' => 'e-2-completion',
                    'source' => '2',
                    'target' => 'completion-node',
                ],
            ],
        ],
    ];
    
    $projectId = $projectService->createProject($projectData);
    
    echo "✓ Project created successfully\n";
    echo "  Project ID: {$projectId}\n";
    
    // Verify project can be retrieved
    $project = $projectService->getProject($projectId);
    
    if ($project) {
        echo "✓ Project retrieved successfully\n";
        echo "  Name: {$project['name']}\n";
        echo "  Status: {$project['status']}\n";
        
        // Verify flow config
        $flowConfig = $projectService->getFlowConfig($projectId);
        
        if ($flowConfig) {
            echo "✓ Flow config retrieved successfully\n";
            echo "  Nodes: " . count($flowConfig['nodes']) . "\n";
            echo "  Edges: " . count($flowConfig['edges']) . "\n";
        } else {
            echo "✗ Failed to retrieve flow config\n";
            exit(1);
        }
    } else {
        echo "✗ Failed to retrieve project\n";
        exit(1);
    }
    
    echo "\n=== Setup Complete ===\n";
    echo "Project ID {$projectId} is ready for cronjob testing\n";
    echo "\nNext steps:\n";
    echo "1. Run: php tests/test-cronjob-execution.php {$projectId}\n";
    echo "2. Or use WordPress cron: wp cron event run crawlflow_execute_project --project_id={$projectId}\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

