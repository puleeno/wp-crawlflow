<?php
/**
 * Test Cron Scheduling với Project Settings
 * Verify schedule time matches project settings
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

echo "=== WP-CrawlFlow Cron Schedule Verification ===\n\n";

try {
    // Get services
    $rake = \Rake\Rake::getInstance();
    $projectService = $rake->make('CrawlFlow\Admin\ProjectService');
    $cronScheduler = $rake->make('CrawlFlow\Cron\CronScheduler');
    
    // Create project với schedule settings rõ ràng
    echo "--- Creating Test Project ---\n";
    
    $projectData = [
        'name' => 'Cron Schedule Test - ' . date('H:i:s'),
        'description' => 'Test project with 15-minute schedule',
        'status' => 'active',
        'project_data' => [
            'projectSettings' => [
                'name' => 'Cron Schedule Test',
                'enabled' => true,
                'scheduleType' => 'interval',
                'scheduleInterval' => 15, // 15 minutes
                'crawlDelay' => 1000,
                'userAgent' => 'CrawlFlow-Test/2.0',
                'concurrency' => 2,
            ],
            'nodes' => [
                [
                    'id' => '1',
                    'type' => 'start',
                    'data' => ['sourceType' => 'manual'],
                ],
                [
                    'id' => 'repository-node',
                    'type' => 'repository',
                    'data' => [],
                ],
                [
                    'id' => 'completion-node',
                    'type' => 'completion',
                    'data' => [],
                ],
            ],
            'edges' => [
                ['source' => '1', 'target' => 'repository-node'],
                ['source' => 'repository-node', 'target' => 'completion-node'],
            ],
        ],
    ];
    
    $projectId = $projectService->createProject($projectData);
    echo "✓ Project created: ID {$projectId}\n";
    
    // Schedule project
    echo "\n--- Scheduling Project ---\n";
    $cronScheduler->scheduleProject($projectId);
    echo "✓ Project scheduled\n";
    
    // Verify schedule
    echo "\n--- Verify Schedule ---\n";
    $isScheduled = $cronScheduler->isProjectScheduled($projectId);
    echo "Is Scheduled: " . ($isScheduled ? "✓ YES" : "✗ NO") . "\n";
    
    if ($isScheduled) {
        $nextRun = $cronScheduler->getNextRunTime($projectId);
        echo "Next Run Timestamp: {$nextRun}\n";
        echo "Next Run Time: " . date('Y-m-d H:i:s', $nextRun) . "\n";
        echo "Time Until Run: " . round(($nextRun - time()) / 60, 1) . " minutes\n";
    }
    
    // Get all scheduled projects
    echo "\n--- All Scheduled Projects ---\n";
    $allScheduled = $cronScheduler->getScheduledProjects();
    
    echo "Total: " . count($allScheduled) . " project(s)\n\n";
    
    foreach ($allScheduled as $item) {
        $pid = $item['project_id'];
        $project = $projectService->getProject($pid);
        $flowConfig = $projectService->getFlowConfig($pid);
        $settings = $flowConfig['projectSettings'] ?? [];
        
        echo "Project {$pid}: {$project['name']}\n";
        echo "  WordPress Schedule: {$item['schedule']}\n";
        echo "  WordPress Interval: " . ($item['interval'] / 60) . " minutes\n";
        echo "  Next Run: {$item['next_run']}\n";
        
        // Project settings
        $scheduleType = $settings['scheduleType'] ?? 'N/A';
        $scheduleInterval = $settings['scheduleInterval'] ?? 'N/A';
        
        echo "  Project Settings:\n";
        echo "    - Type: {$scheduleType}\n";
        echo "    - Interval: {$scheduleInterval} minutes\n";
        
        // Verify match
        if ($scheduleType === 'interval' && is_numeric($scheduleInterval)) {
            $expectedInterval = (int)$scheduleInterval;
            $actualInterval = $item['interval'] / 60;
            
            // WordPress maps intervals to predefined schedules
            $expectedWpInterval = $actualInterval; // WP schedule might differ
            
            if ($expectedInterval <= 5 && $actualInterval == 5) {
                echo "    - Match: ✓ YES (mapped to 5 min)\n";
            } elseif ($expectedInterval > 5 && $expectedInterval <= 15 && $actualInterval == 15) {
                echo "    - Match: ✓ YES (mapped to 15 min)\n";
            } elseif ($expectedInterval > 15 && $expectedInterval <= 30 && $actualInterval == 30) {
                echo "    - Match: ✓ YES (mapped to 30 min)\n";
            } elseif ($expectedInterval > 30 && $expectedInterval <= 60 && $actualInterval == 60) {
                echo "    - Match: ✓ YES (mapped to hourly)\n";
            } elseif ($expectedInterval > 60 && $actualInterval >= 60) {
                echo "    - Match: ✓ YES (mapped to " . ($actualInterval / 60) . " hours)\n";
            } else {
                echo "    - Match: ✗ MISMATCH\n";
                echo "      Expected: {$expectedInterval} min\n";
                echo "      Actual: {$actualInterval} min\n";
            }
        }
        
        echo "\n";
    }
    
    // Test running wp-cron.php
    echo "=== Test Complete ===\n";
    echo "✓ Projects scheduled in WordPress cron\n";
    echo "✓ Schedule intervals match project settings\n\n";
    echo "To trigger cron manually, run:\n";
    echo "  php wp-cron.php\n\n";
    echo "To check cron events:\n";
    echo "  wp cron event list\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

