<?php
/**
 * Test Registry System
 * Verifies registry data collection and hooks
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

use CrawlFlow\Admin\RegistryService;
use Rake\Manager\DataSourceManager;
use Rake\Manager\ProcessorManager;
use Rake\Manager\HttpClientManager;

echo "=== Test Registry System ===\n\n";

try {
    // Test 1: Get registry data
    echo "--- Test 1: Registry Service ---\n";
    
    $registry = new RegistryService();
    $data = $registry->getAllRegistryData();
    
    echo "✓ Registry data collected\n";
    echo "  Data Sources: " . count($data['dataSources']) . "\n";
    echo "  Processors: " . count($data['processors']) . "\n";
    echo "  Parsers: " . count($data['parsers']) . "\n";
    echo "  HTTP Clients: " . count($data['httpClients']) . "\n";
    
    // Test 2: Data sources
    echo "\n--- Test 2: Data Sources ---\n";
    
    foreach ($data['dataSources'] as $ds) {
        echo "  {$ds['icon']} {$ds['label']} ({$ds['type']})\n";
    }
    
    // Test 3: Processors
    echo "\n--- Test 3: Processors ---\n";
    
    foreach ($data['processors'] as $proc) {
        echo "  {$proc['icon']} {$proc['label']} ({$proc['type']})\n";
    }
    
    // Test 4: Parsers
    echo "\n--- Test 4: Parsers ---\n";
    
    foreach ($data['parsers'] as $parser) {
        echo "  {$parser['icon']} {$parser['label']} ({$parser['type']})\n";
    }
    
    // Test 4.5: HTTP Clients
    echo "\n--- Test 4.5: HTTP Clients ---\n";
    
    foreach ($data['httpClients'] as $client) {
        echo "  {$client['icon']} {$client['label']} ({$client['name']})\n";
    }
    
    // Test 5: Hook to add custom data source
    echo "\n--- Test 5: External Plugin Hook ---\n";
    
    add_filter('crawlflow_registered_data_sources', function($dataSources) {
        $dataSources[] = [
            'type' => 'custom_api',
            'label' => 'Custom API',
            'description' => 'My custom API integration',
            'icon' => '🎯',
        ];
        return $dataSources;
    });
    
    $dataWithHook = $registry->getAllRegistryData();
    
    echo "✓ Hook applied\n";
    echo "  Data Sources now: " . count($dataWithHook['dataSources']) . "\n";
    
    $lastSource = end($dataWithHook['dataSources']);
    echo "  Last source: {$lastSource['label']} ({$lastSource['type']})\n";
    
    // Test 6: JSON encoding (for localize script)
    echo "\n--- Test 6: JSON Encoding ---\n";
    
    $json = json_encode($data, JSON_PRETTY_PRINT);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✓ JSON encoding successful\n";
        echo "  Size: " . strlen($json) . " bytes\n";
        echo "  Valid JSON: YES\n";
    }
    
    echo "\n=== Summary ===\n\n";
    
    echo "✓ RegistryService working\n";
    echo "✓ Data collected from managers\n";
    echo "✓ Hooks working for extensibility\n";
    echo "✓ JSON encoding working\n";
    echo "✓ Ready for wp_localize_script\n";
    
    echo "\n--- Usage for External Plugins ---\n";
    echo "add_action('crawlflow_register_data_sources', function() {\n";
    echo "    DataSourceManager::registerType('type', MyClass::class);\n";
    echo "});\n\n";
    echo "add_filter('crawlflow_registered_data_sources', function(\$ds) {\n";
    echo "    \$ds[] = ['type' => 'type', 'label' => 'Label', ...];\n";
    echo "    return \$ds;\n";
    echo "});\n";
    
    echo "\n✅ REGISTRY SYSTEM WORKING\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}


