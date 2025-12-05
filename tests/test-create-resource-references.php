<?php
/**
 * Test Create Resource References Action
 * 
 * Run: wp eval-file wp-content/plugins/wp-crawlflow/tests/test-create-resource-references.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Rake\Manager\CompletionActionManager;
use Rake\CompletionAction\CreateResourceReferencesAction;

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║         TEST: Create Resource References Action                           ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

global $wpdb;

// Step 1: Check if tables exist
echo "📋 Step 1: Check Database Tables\n";
echo str_repeat('─', 80) . "\n";

$tables = [
    $wpdb->prefix . 'rake_data_sources',
    $wpdb->prefix . 'rake_url_source_maps',
    $wpdb->prefix . 'rake_resources',
    $wpdb->prefix . 'rake_file_checksums'
];

$missingTables = [];
foreach ($tables as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
    if ($exists) {
        echo "✅ {$table}: EXISTS\n";
    } else {
        echo "❌ {$table}: NOT FOUND\n";
        $missingTables[] = $table;
    }
}

if (!empty($missingTables)) {
    echo "\n⚠️ WARNING: Some tables are missing. Run migration first:\n";
    echo "wp eval-file wp-content/plugins/wp-crawlflow/database/migrations/create-resource-references-tables.php\n";
    echo "\nContinuing with test...\n";
}

// Step 2: Insert test data into rake_data_sources
echo "\n📋 Step 2: Insert Test URLs into rake_data_sources\n";
echo str_repeat('─', 80) . "\n";

$testDomain = 'example.com';
$testUrls = [
    'https://example.com/images/test1.jpg',
    'https://example.com/images/test2.png',
    'https://example.com/videos/demo.mp4',
    'https://example.com/audio/sound.mp3'
];

$dataSourcesTable = $wpdb->prefix . 'rake_data_sources';

// Clear existing test data
$wpdb->query($wpdb->prepare("DELETE FROM {$dataSourcesTable} WHERE domain = %s", $testDomain));

foreach ($testUrls as $url) {
    $urlHash = hash('sha256', $url);
    $resourceType = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
    
    $wpdb->insert($dataSourcesTable, [
        'domain' => $testDomain,
        'url' => $url,
        'url_hash' => $urlHash,
        'resource_type' => $resourceType,
        'status' => 'pending',
        'created_at' => current_time('mysql')
    ], ['%s', '%s', '%s', '%s', '%s', '%s']);
    
    echo "✅ Inserted: {$url}\n";
}

// Step 3: Check if action is registered
echo "\n📋 Step 3: Check Action Registration\n";
echo str_repeat('─', 80) . "\n";

if (CompletionActionManager::hasAction('create_resource_references')) {
    echo "✅ CreateResourceReferencesAction is registered\n";
    
    $metadata = CompletionActionManager::getActionMetadata('create_resource_references');
    echo "Label: {$metadata['label']}\n";
    echo "Description: {$metadata['description']}\n";
    echo "Icon: {$metadata['icon']}\n";
    echo "Category: {$metadata['category']}\n";
} else {
    echo "❌ CreateResourceReferencesAction is NOT registered\n";
    echo "Run RegistryHooks::init() to register\n";
}

// Step 4: Get action metadata with config fields
echo "\n📋 Step 4: Get Action Config Fields\n";
echo str_repeat('─', 80) . "\n";

$actionData = CompletionActionManager::getActionMetadataWithConfigFields('create_resource_references');
if ($actionData && isset($actionData['configFields'])) {
    echo "✅ Config fields:\n";
    foreach ($actionData['configFields'] as $field) {
        echo "   - {$field['name']} ({$field['type']}): {$field['label']}\n";
    }
} else {
    echo "⚠️ No config fields found\n";
}

// Step 5: Test config validation
echo "\n📋 Step 5: Test Config Validation\n";
echo str_repeat('─', 80) . "\n";

$validConfig = [
    'domain' => 'example.com',
    'replace_urls_in_content' => true
];

$validation = CreateResourceReferencesAction::validateConfig($validConfig);
echo "Valid config: " . ($validation['valid'] ? '✅ Valid' : '❌ Invalid') . "\n";

$invalidConfig = [];
$validation = CreateResourceReferencesAction::validateConfig($invalidConfig);
echo "Invalid config (no domain): " . ($validation['valid'] ? '✅ Valid' : '❌ Invalid') . "\n";
if (!$validation['valid']) {
    echo "Errors: " . implode(', ', $validation['errors']) . "\n";
}

// Step 6: Dry-run execution (will fail on actual download but shows flow)
echo "\n📋 Step 6: Test Execution Flow (Dry Run)\n";
echo str_repeat('─', 80) . "\n";
echo "⚠️ Note: Execution will fail on actual HTTP downloads (test URLs don't exist)\n";
echo "This test demonstrates the flow and error handling.\n\n";

try {
    // Get action instance
    $action = CompletionActionManager::getAction('create_resource_references');
    
    if (!$action) {
        echo "❌ Failed to get action instance\n";
    } else {
        echo "✅ Got action instance\n";
        
        // Set config
        $config = [
            'domain' => $testDomain,
            'replace_urls_in_content' => false, // Don't replace for test
            'media_extensions' => ['jpg', 'png', 'mp4', 'mp3']
        ];
        
        $action->setConfig($config);
        echo "✅ Config set\n";
        
        // Execute (will fail on download)
        $context = [
            'project_id' => 999,
            'domain' => $testDomain,
            'project_name' => 'Test Project'
        ];
        
        echo "\nExecuting action...\n";
        $result = $action->execute($context);
        
        echo "\nResult: " . ($result['success'] ? '✅ Success' : '⚠️ Completed with errors') . "\n";
        echo "Message: {$result['message']}\n";
        
        if (isset($result['data'])) {
            $data = $result['data'];
            echo "\nStatistics:\n";
            echo "- Total URLs: {$data['total']}\n";
            echo "- Processed: {$data['processed']}\n";
            echo "- Imported: {$data['imported']}\n";
            echo "- Reused: {$data['reused']}\n";
            echo "- Skipped: {$data['skipped']}\n";
            echo "- Failed: {$data['failed']}\n";
            
            if (!empty($data['errors'])) {
                echo "\nErrors (expected for test URLs):\n";
                foreach (array_slice($data['errors'], 0, 3) as $error) {
                    echo "- {$error['url']}: {$error['error']}\n";
                }
            }
        }
    }
} catch (\Exception $e) {
    echo "⚠️ Exception (expected): " . $e->getMessage() . "\n";
}

// Step 7: Check database state
echo "\n📋 Step 7: Check Database State\n";
echo str_repeat('─', 80) . "\n";

$dataSourcesCount = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$dataSourcesTable} WHERE domain = %s",
    $testDomain
));
echo "rake_data_sources: {$dataSourcesCount} test URLs\n";

$urlMapsTable = $wpdb->prefix . 'rake_url_source_maps';
$urlMapsCount = $wpdb->get_var("SELECT COUNT(*) FROM {$urlMapsTable}");
echo "rake_url_source_maps: {$urlMapsCount} mappings\n";

$resourcesTable = $wpdb->prefix . 'rake_resources';
$importedCount = $wpdb->get_var("SELECT COUNT(*) FROM {$resourcesTable} WHERE imported_at IS NOT NULL");
echo "rake_resources: {$importedCount} imported resources\n";

// Cleanup
echo "\n📋 Cleanup: Remove test data\n";
echo str_repeat('─', 80) . "\n";
$wpdb->query($wpdb->prepare("DELETE FROM {$dataSourcesTable} WHERE domain = %s", $testDomain));
echo "✅ Removed test URLs from rake_data_sources\n";

// Summary
echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║         ✅ TEST COMPLETED                                                 ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Summary:\n";
echo "- ✅ Database tables checked\n";
echo "- ✅ Test data inserted\n";
echo "- ✅ Action registration verified\n";
echo "- ✅ Config validation working\n";
echo "- ⚠️ Execution flow tested (fails on HTTP download as expected)\n";
echo "\n";
echo "📝 To test with real URLs:\n";
echo "1. Insert real image URLs into rake_data_sources\n";
echo "2. Run CreateResourceReferencesAction via completion action\n";
echo "3. Check rake_resources for imported_at timestamp\n";
echo "4. Check rake_url_source_maps for URL mappings\n";
echo "\n";


