<?php
/**
 * Test WordPress Cron Schedule
 * Verify projects are scheduled in WordPress cron
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

echo "=== WP-CrawlFlow Cron Schedule Test ===\n\n";

try {
    // Get Rake instance
    $rake = \Rake\Rake::getInstance();
    
    // Get CronScheduler
    $cronScheduler = $rake->make('CrawlFlow\Cron\CronScheduler');
    
    echo "✓ CronScheduler loaded from container\n\n";
    
    // Get scheduled projects
    echo "--- Scheduled Projects ---\n";
    $scheduled = $cronScheduler->getScheduledProjects();
    
    if (empty($scheduled)) {
        echo "No projects scheduled yet.\n";
        echo "Creating and scheduling a test project...\n\n";
        
        // Create test project
        $projectService = $rake->make('CrawlFlow\Admin\ProjectService');
        
        $projectData = [
            'name' => 'Cron Test Project - ' . date('H:i:s'),
            'description' => 'Test project for cron scheduling',
            'status' => 'active',
            'project_data' => [
                'projectSettings' => [
                    'name' => 'Cron Test',
                    'enabled' => true,
                    'scheduleType' => 'interval',
                    'scheduleInterval' => 30, // 30 minutes
                    'crawlDelay' => 1000,
                ],
                'nodes' => [
                    ['id' => '1', 'type' => 'start', 'data' => ['sourceType' => 'manual']],
                    ['id' => 'repo', 'type' => 'repository', 'data' => []],
                    ['id' => 'complete', 'type' => 'completion', 'data' => []],
                ],
                'edges' => [
                    ['source' => '1', 'target' => 'repo'],
                    ['source' => 'repo', 'target' => 'complete'],
                ],
            ],
        ];
        
        $projectId = $projectService->createProject($projectData);
        echo "✓ Created project ID: {$projectId}\n";
        
        // Schedule it
        $cronScheduler->scheduleProject($projectId);
        echo "✓ Scheduled project {$projectId}\n\n";
        
        // Get scheduled again
        $scheduled = $cronScheduler->getScheduledProjects();
    }
    
    // Display scheduled projects
    echo "Total scheduled: " . count($scheduled) . "\n\n";
    
    foreach ($scheduled as $item) {
        $projectId = $item['project_id'];
        
        // Get project details
        $projectService = $rake->make('CrawlFlow\Admin\ProjectService');
        $project = $projectService->getProject($projectId);
        $flowConfig = $projectService->getFlowConfig($projectId);
        
        $projectSettings = $flowConfig['projectSettings'] ?? [];
        $scheduleInterval = $projectSettings['scheduleInterval'] ?? 'N/A';
        $scheduleType = $projectSettings['scheduleType'] ?? 'N/A';
        
        echo "Project ID: {$projectId}\n";
        echo "  Name: " . ($project['name'] ?? 'N/A') . "\n";
        echo "  Status: " . ($project['status'] ?? 'N/A') . "\n";
        echo "  Schedule: {$item['schedule']}\n";
        echo "  Interval: " . ($item['interval'] / 60) . " minutes\n";
        echo "  Next Run: {$item['next_run']}\n";
        echo "  Project Settings:\n";
        echo "    - Schedule Type: {$scheduleType}\n";
        echo "    - Schedule Interval: {$scheduleInterval} minutes\n";
        
        // Verify schedule matches project settings
        $expectedInterval = (int)$scheduleInterval * 60; // Convert to seconds
        $actualInterval = $item['interval'];
        
        if ($scheduleType === 'interval') {
            $matches = false;
            
            // Check if intervals roughly match (allow some variance due to WP schedule mapping)
            if ($scheduleInterval <= 5 && $actualInterval == 5 * 60) $matches = true;
            if ($scheduleInterval > 5 && $scheduleInterval <= 15 && $actualInterval == 15 * 60) $matches = true;
            if ($scheduleInterval > 15 && $scheduleInterval <= 30 && $actualInterval == 30 * 60) $matches = true;
            if ($scheduleInterval > 30 && $scheduleInterval <= 60 && $actualInterval == HOUR_IN_SECONDS) $matches = true;
            
            echo "    - Match: " . ($matches ? "✓ YES" : "✗ NO") . "\n";
            
            if (!$matches) {
                echo "    - Expected: ~{$scheduleInterval} min, Got: " . ($actualInterval / 60) . " min\n";
            }
        }
        
        echo "\n";
    }
    
    echo "=== Test Complete ===\n";
    echo "Projects are scheduled in WordPress cron ✓\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

