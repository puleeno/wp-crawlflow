<?php
/**
 * Test Data Source UI Integration
 * 
 * Verifies that data sources are properly loaded from PHP and ready for UI consumption
 */

require_once __DIR__ . '/../../../../wp-load.php';

echo "=== DATA SOURCE UI INTEGRATION TEST ===" . PHP_EOL . PHP_EOL;

// Initialize registry
\CrawlFlow\Hooks\RegistryHooks::init();

// Get registry service
$registryService = new \CrawlFlow\Admin\RegistryService();
$allData = $registryService->getAllRegistryData();

// Test 1: Data sources are loaded
echo "✓ Test 1: Data sources loaded" . PHP_EOL;
assert(isset($allData['dataSources']), 'Data sources key exists');
assert(is_array($allData['dataSources']), 'Data sources is array');
assert(count($allData['dataSources']) === 5, 'Expected 5 data sources, got ' . count($allData['dataSources']));
echo "  → " . count($allData['dataSources']) . " data sources registered" . PHP_EOL . PHP_EOL;

// Test 2: Each data source has required fields
echo "✓ Test 2: Data source structure validation" . PHP_EOL;
$requiredKeys = ['type', 'label', 'description', 'icon', 'configFields'];
foreach ($allData['dataSources'] as $ds) {
    foreach ($requiredKeys as $key) {
        assert(isset($ds[$key]), "Data source missing key: {$key}");
    }
    echo "  → {$ds['type']}: {$ds['label']}" . PHP_EOL;
}
echo PHP_EOL;

// Test 3: Config fields structure
echo "✓ Test 3: Config fields validation" . PHP_EOL;
$totalFields = 0;
foreach ($allData['dataSources'] as $ds) {
    assert(is_array($ds['configFields']), "{$ds['type']} configFields must be array");
    $fieldCount = count($ds['configFields']);
    $totalFields += $fieldCount;
    
    echo "  → {$ds['type']}: {$fieldCount} fields" . PHP_EOL;
    
    // Validate each field structure
    foreach ($ds['configFields'] as $field) {
        assert(isset($field['name']), 'Field must have name');
        assert(isset($field['type']), 'Field must have type');
        assert(isset($field['label']), 'Field must have label');
        
        // Validate field types
        $validTypes = ['text', 'number', 'select', 'textarea', 'checkbox', 'url', 'password'];
        assert(in_array($field['type'], $validTypes), "Invalid field type: {$field['type']}");
    }
}
echo "  → Total: {$totalFields} config fields" . PHP_EOL . PHP_EOL;

// Test 4: JSON serialization
echo "✓ Test 4: JSON serialization" . PHP_EOL;
$json = json_encode($allData['dataSources']);
assert($json !== false, 'JSON encoding failed');
assert(json_last_error() === JSON_ERROR_NONE, 'JSON error: ' . json_last_error_msg());
echo "  → JSON size: " . number_format(strlen($json)) . " bytes" . PHP_EOL;
echo "  → Valid JSON: Yes" . PHP_EOL . PHP_EOL;

// Test 5: Specific data source checks
echo "✓ Test 5: Specific data source validation" . PHP_EOL;

// URL data source
$urlDs = array_values(array_filter($allData['dataSources'], fn($ds) => $ds['type'] === 'url'))[0];
assert($urlDs !== null, 'URL data source not found');
assert(count($urlDs['configFields']) === 4, 'URL should have 4 fields');
echo "  → URL: " . count($urlDs['configFields']) . " fields ✓" . PHP_EOL;

// API data source
$apiDs = array_values(array_filter($allData['dataSources'], fn($ds) => $ds['type'] === 'api'))[0];
assert($apiDs !== null, 'API data source not found');
assert(count($apiDs['configFields']) === 3, 'API should have 3 fields');
echo "  → API: " . count($apiDs['configFields']) . " fields ✓" . PHP_EOL;

// MySQL data source
$mysqlDs = array_values(array_filter($allData['dataSources'], fn($ds) => $ds['type'] === 'mysql'))[0];
assert($mysqlDs !== null, 'MySQL data source not found');
assert(count($mysqlDs['configFields']) === 6, 'MySQL should have 6 fields');
echo "  → MySQL: " . count($mysqlDs['configFields']) . " fields ✓" . PHP_EOL . PHP_EOL;

// Test 6: JSON data source removed
echo "✓ Test 6: JSON data source removed" . PHP_EOL;
$jsonDs = array_values(array_filter($allData['dataSources'], fn($ds) => $ds['type'] === 'json'));
assert(empty($jsonDs), 'JSON data source should be removed');
echo "  → JSON data source not found (as expected) ✓" . PHP_EOL . PHP_EOL;

// Test 7: UI compatibility
echo "✓ Test 7: UI compatibility check" . PHP_EOL;
$uiData = [
    'dataSources' => $allData['dataSources'],
    'processors' => $allData['processors'],
    'parsers' => $allData['parsers'],
    'httpClients' => $allData['httpClients'],
    'nonce' => 'test_nonce',
    'ajaxUrl' => '/wp-admin/admin-ajax.php',
];
$uiJson = json_encode($uiData);
assert($uiJson !== false, 'UI data JSON encoding failed');
echo "  → UI data structure: Valid" . PHP_EOL;
echo "  → Total size: " . number_format(strlen($uiJson)) . " bytes" . PHP_EOL . PHP_EOL;

echo "🎉 ALL TESTS PASSED!" . PHP_EOL . PHP_EOL;

echo "=== SUMMARY ===" . PHP_EOL;
echo "✅ Data Sources: " . count($allData['dataSources']) . PHP_EOL;
echo "✅ Total Config Fields: {$totalFields}" . PHP_EOL;
echo "✅ JSON Valid: Yes" . PHP_EOL;
echo "✅ UI Ready: Yes" . PHP_EOL . PHP_EOL;

echo "Data sources are properly loaded and ready for UI consumption!" . PHP_EOL;

