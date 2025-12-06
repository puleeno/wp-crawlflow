<?php
/**
 * Test 3 Phases của CrawlFlow
 * 
 * Test Phase 1 (Crawl), Phase 2 (Process), Phase 3 (Resources)
 * 
 * Run: wp eval-file wp-content/plugins/wp-crawlflow/tests/test-3-phases.php
 */

require_once __DIR__ . '/../../../../wp-load.php';

use CrawlFlow\Cron\Phase1CrawlService;
use CrawlFlow\Cron\Phase2ProcessService;
use CrawlFlow\Cron\Phase3ResourcesService;
use CrawlFlow\Admin\ProjectService;
use Rake\Rake;

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║    TEST 3 PHASES: CrawlFlow Project Execution                          ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

// Get project ID from command line or use default
$projectId = isset($argv[1]) ? (int)$argv[1] : 1;

$rake = Rake::getInstance();
$projectService = $rake->make('CrawlFlow\Admin\ProjectService');

// Load project
$project = $projectService->getProject($projectId);
if (!$project) {
    echo "❌ Project {$projectId} not found\n";
    exit(1);
}

echo "📋 Project: {$project['name']} (ID: {$projectId})\n";
echo "   Status: {$project['status']}\n\n";

// Initialize services
$phase1Service = new Phase1CrawlService();
$phase2Service = new Phase2ProcessService();
$phase3Service = new Phase3ResourcesService();

// ============================================================
// PHASE 1: CRAWL
// ============================================================
echo "┌─ PHASE 1: CRAWL\n";
echo "│\n";

try {
    $phase1Result = $phase1Service->execute($projectId);
    
    echo "│ ✅ Phase 1 completed\n";
    echo "│   Sources processed: {$phase1Result['sources_processed']}\n";
    echo "│   Items saved: {$phase1Result['items_saved']}\n";
    echo "│   References saved: {$phase1Result['references_saved']}\n";
    
    if (!empty($phase1Result['errors'])) {
        echo "│   Errors: " . count($phase1Result['errors']) . "\n";
        foreach ($phase1Result['errors'] as $error) {
            echo "│     - {$error['error']}\n";
        }
    }
    
    echo "└───────────────────────────────────────────────────────────────────────\n\n";
    
} catch (\Exception $e) {
    echo "│ ❌ Phase 1 failed: {$e->getMessage()}\n";
    echo "└───────────────────────────────────────────────────────────────────────\n\n";
    exit(1);
}

// ============================================================
// PHASE 2: PROCESS
// ============================================================
echo "┌─ PHASE 2: PROCESS\n";
echo "│\n";

try {
    $phase2Result = $phase2Service->execute($projectId);
    
    echo "│ ✅ Phase 2 completed\n";
    echo "│   Items processed: {$phase2Result['items_processed']}\n";
    echo "│   Items success: {$phase2Result['items_success']}\n";
    echo "│   Items failed: {$phase2Result['items_failed']}\n";
    echo "│   Resources detected: {$phase2Result['resources_detected']}\n";
    
    if (!empty($phase2Result['errors'])) {
        echo "│   Errors: " . count($phase2Result['errors']) . "\n";
        foreach (array_slice($phase2Result['errors'], 0, 5) as $error) {
            echo "│     - {$error}\n";
        }
        if (count($phase2Result['errors']) > 5) {
            echo "│     ... and " . (count($phase2Result['errors']) - 5) . " more errors\n";
        }
    }
    
    echo "└───────────────────────────────────────────────────────────────────────\n\n";
    
} catch (\Exception $e) {
    echo "│ ❌ Phase 2 failed: {$e->getMessage()}\n";
    echo "│   Stack trace: " . $e->getTraceAsString() . "\n";
    echo "└───────────────────────────────────────────────────────────────────────\n\n";
    exit(1);
}

// ============================================================
// PHASE 3: RESOURCES
// ============================================================
echo "┌─ PHASE 3: RESOURCES\n";
echo "│\n";

try {
    $phase3Result = $phase3Service->execute($projectId);
    
    echo "│ ✅ Phase 3 completed\n";
    echo "│   Resources processed: {$phase3Result['resources_processed']}\n";
    echo "│   Resources downloaded: {$phase3Result['resources_downloaded']}\n";
    echo "│   Resources imported: {$phase3Result['resources_imported']}\n";
    echo "│   URLs replaced: {$phase3Result['urls_replaced']}\n";
    
    if (!empty($phase3Result['errors'])) {
        echo "│   Errors: " . count($phase3Result['errors']) . "\n";
        foreach (array_slice($phase3Result['errors'], 0, 5) as $error) {
            echo "│     - {$error}\n";
        }
        if (count($phase3Result['errors']) > 5) {
            echo "│     ... and " . (count($phase3Result['errors']) - 5) . " more errors\n";
        }
    }
    
    echo "└───────────────────────────────────────────────────────────────────────\n\n";
    
} catch (\Exception $e) {
    echo "│ ❌ Phase 3 failed: {$e->getMessage()}\n";
    echo "│   Stack trace: " . $e->getTraceAsString() . "\n";
    echo "└───────────────────────────────────────────────────────────────────────\n\n";
    exit(1);
}

// ============================================================
// SUMMARY
// ============================================================
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║    TEST SUMMARY                                                         ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

global $wpdb;

// Count data
$origins_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}rake_data_origins o
    INNER JOIN {$wpdb->prefix}rake_data_sources s ON o.source_id = s.id
    WHERE s.tooth_id = %d",
    $projectId
));

$references_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}rake_data_origins_references r
    INNER JOIN {$wpdb->prefix}rake_data_origins o ON r.parent_origin_id = o.id
    INNER JOIN {$wpdb->prefix}rake_data_sources s ON o.source_id = s.id
    WHERE s.tooth_id = %d",
    $projectId
));

$parsed_items_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}rake_data_parsed_items p
    INNER JOIN {$wpdb->prefix}rake_data_origins o ON p.origin_id = o.id
    INNER JOIN {$wpdb->prefix}rake_data_sources s ON o.source_id = s.id
    WHERE s.tooth_id = %d",
    $projectId
));

$parsed_items_with_changes = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}rake_data_parsed_items p
    INNER JOIN {$wpdb->prefix}rake_data_origins o ON p.origin_id = o.id
    INNER JOIN {$wpdb->prefix}rake_data_sources s ON o.source_id = s.id
    WHERE s.tooth_id = %d AND p.has_change = 1",
    $projectId
));

echo "📊 Database Statistics:\n";
echo "   - Data Origins: {$origins_count}\n";
echo "   - References: {$references_count}\n";
echo "   - Parsed Items: {$parsed_items_count}\n";
echo "   - Parsed Items with changes (has_change=1): {$parsed_items_with_changes}\n\n";

echo "✅ ALL 3 PHASES COMPLETED SUCCESSFULLY\n\n";

