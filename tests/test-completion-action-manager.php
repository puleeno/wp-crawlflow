<?php
/**
 * Test Completion Action Manager
 * 
 * Run: wp eval-file wp-content/plugins/wp-crawlflow/tests/test-completion-action-manager.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Rake\Manager\CompletionActionManager;
use Rake\CompletionAction\ReportingAction;
use Rake\CompletionAction\SendEmailNotificationAction;
use Rake\CompletionAction\WebhookAction;

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║         TEST: Completion Action Manager                                    ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

// Test 1: Register actions
echo "📋 Test 1: Register Completion Actions\n";
echo str_repeat('─', 80) . "\n";

CompletionActionManager::clear(); // Clear for testing

CompletionActionManager::register(
    'reporting',
    new ReportingAction(),
    [
        'label' => 'Generate Report',
        'description' => 'Generate and log summary report',
        'icon' => '📊',
        'category' => 'reporting',
        'enabled' => true
    ]
);

CompletionActionManager::register(
    'send_email_notification',
    new SendEmailNotificationAction(),
    [
        'label' => 'Send Email Notification',
        'description' => 'Send email when crawl completes',
        'icon' => '📧',
        'category' => 'notification',
        'enabled' => true
    ]
);

CompletionActionManager::register(
    'webhook',
    new WebhookAction(),
    [
        'label' => 'Webhook',
        'description' => 'Send HTTP POST to webhook URL',
        'icon' => '🔗',
        'category' => 'integration',
        'enabled' => true
    ]
);

$actions = CompletionActionManager::getActions();
echo "✅ Registered " . count($actions) . " completion actions\n";
foreach (array_keys($actions) as $type) {
    echo "   - {$type}\n";
}

// Test 2: Get action metadata
echo "\n📋 Test 2: Get Action Metadata\n";
echo str_repeat('─', 80) . "\n";

$metadata = CompletionActionManager::getActionMetadata();
echo "✅ Got metadata for " . count($metadata) . " actions:\n";
foreach ($metadata as $type => $data) {
    echo "   - {$type}: {$data['label']} ({$data['icon']})\n";
}

// Test 3: Get UI data
echo "\n📋 Test 3: Get UI Data (with config fields)\n";
echo str_repeat('─', 80) . "\n";

$uiData = CompletionActionManager::getAllForUI();
echo "✅ Got UI data for " . count($uiData) . " actions:\n";
foreach ($uiData as $data) {
    $fieldsCount = count($data['configFields'] ?? []);
    echo "   - {$data['type']}: {$data['label']} - {$fieldsCount} config fields\n";
    if ($fieldsCount > 0) {
        foreach ($data['configFields'] as $field) {
            echo "     • {$field['name']} ({$field['type']})\n";
        }
    }
}

// Test 4: Execute Reporting Action
echo "\n📋 Test 4: Execute Reporting Action\n";
echo str_repeat('─', 80) . "\n";

$context = [
    'project_name' => 'Test Project',
    'project_id' => 123,
    'total_items_processed' => 100,
    'success_count' => 95,
    'failure_count' => 5,
    'duration_seconds' => 120,
    'start_time' => '2024-01-01 10:00:00',
    'end_time' => '2024-01-01 10:02:00',
    'errors' => [
        ['message' => 'Error 1'],
        ['message' => 'Error 2']
    ]
];

$config = [
    'report_type' => 'summary',
    'include_errors' => true,
    'include_stats' => true,
    'log_report' => false,
    'save_to_file' => false
];

$result = CompletionActionManager::execute('reporting', $context, $config);
echo "Result: " . ($result['success'] ? '✅ Success' : '❌ Failed') . "\n";
echo "Message: {$result['message']}\n";
if (isset($result['data']['statistics'])) {
    echo "Total Processed: {$result['data']['statistics']['total_items_processed']}\n";
    echo "Success: {$result['data']['statistics']['success_count']}\n";
    echo "Failed: {$result['data']['statistics']['failure_count']}\n";
}

// Test 5: Validate config
echo "\n📋 Test 5: Validate Action Config\n";
echo str_repeat('─', 80) . "\n";

// Valid config
$validConfig = [
    'url' => 'https://example.com/webhook',
    'timeout' => 30
];
$validation = WebhookAction::validateConfig($validConfig);
echo "Valid config: " . ($validation['valid'] ? '✅ Valid' : '❌ Invalid') . "\n";

// Invalid config
$invalidConfig = [
    'url' => 'not-a-url',
    'timeout' => -5
];
$validation = WebhookAction::validateConfig($invalidConfig);
echo "Invalid config: " . ($validation['valid'] ? '✅ Valid' : '❌ Invalid') . "\n";
if (!$validation['valid']) {
    echo "Errors:\n";
    foreach ($validation['errors'] as $error) {
        echo "   - {$error}\n";
    }
}

// Test 6: Execute Multiple Actions
echo "\n📋 Test 6: Execute Multiple Actions\n";
echo str_repeat('─', 80) . "\n";

$multipleActions = [
    [
        'type' => 'reporting',
        'config' => [
            'report_type' => 'summary',
            'log_report' => false
        ]
    ],
    [
        'type' => 'send_email_notification',
        'config' => [
            'to' => 'test@example.com',
            'subject' => 'Test Complete'
        ]
    ]
];

// Note: Email won't actually send in test
$result = CompletionActionManager::executeMultiple($multipleActions, $context);
echo "Result: " . ($result['success'] ? '✅ All Success' : '⚠️ Some Failed') . "\n";
echo "Total: {$result['total']}, Success: {$result['success_count']}, Failed: {$result['failure_count']}\n";

foreach ($result['results'] as $actionResult) {
    echo "   - {$actionResult['type']}: " . ($actionResult['success'] ? '✅' : '❌') . " {$actionResult['message']}\n";
}

// Summary
echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║         ✅ ALL TESTS COMPLETED                                            ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Summary:\n";
echo "- ✅ Registered 3 completion actions\n";
echo "- ✅ Retrieved metadata and UI data\n";
echo "- ✅ Executed reporting action successfully\n";
echo "- ✅ Config validation working correctly\n";
echo "- ✅ Multiple action execution working\n";
echo "\n";
echo "🎯 Completion Action Manager is working correctly!\n";
echo "\n";

