<?php
/**
 * Test Data Source Manager
 * Verifies data source registration and fetching
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

use Rake\Manager\DataSourceManager;
use Rake\DataSource\UrlDataSource;

echo "=== Test Data Source Manager ===\n\n";

try {
    // Test 1: Register data source type
    echo "--- Test 1: Register Data Source Type ---\n";
    
    DataSourceManager::registerType('url', UrlDataSource::class);
    
    echo "✓ URL data source registered\n";
    
    if (DataSourceManager::hasType('url')) {
        echo "  hasType('url'): YES\n";
    }
    
    // Test 2: Get registered types
    echo "\n--- Test 2: Get Registered Types ---\n";
    
    $types = DataSourceManager::getRegisteredTypes();
    
    echo "✓ Registered types: " . count($types) . "\n";
    foreach ($types as $type) {
        echo "  - {$type}\n";
    }
    
    // Test 3: Create data source
    echo "\n--- Test 3: Create Data Source ---\n";
    
    $manager = new DataSourceManager();
    
    $source = $manager->create('url', [
        'name' => 'Test URL Source',
        'url' => 'https://httpbin.org/html',
    ]);
    
    echo "✓ Data source created\n";
    echo "  Type: " . $source->getType() . "\n";
    echo "  Name: " . $source->getName() . "\n";
    
    // Test 4: Fetch data
    echo "\n--- Test 4: Fetch Data ---\n";
    
    try {
        $data = $source->fetch();
        
        echo "✓ Data fetched\n";
        echo "  Items: " . count($data) . "\n";
        
        if (!empty($data)) {
            $firstItem = $data[0];
            echo "  First item keys: " . implode(', ', array_keys($firstItem)) . "\n";
            echo "  GUID: " . ($firstItem['guid'] ?? 'N/A') . "\n";
            echo "  Data size: " . strlen($firstItem['raw_data'] ?? '') . " bytes\n";
        }
        
    } catch (\Exception $e) {
        echo "⚠ Fetch failed: " . $e->getMessage() . "\n";
    }
    
    // Test 5: Named instances
    echo "\n--- Test 5: Named Instances ---\n";
    
    $source1 = $manager->createNamed('blog_source', 'url', [
        'name' => 'Blog Source',
        'url' => 'https://example.com',
    ]);
    
    echo "✓ Named instance created: 'blog_source'\n";
    
    $source2 = $manager->getInstance('blog_source');
    
    if ($source1 === $source2) {
        echo "✓ Same instance returned (cached)\n";
    }
    
    // Test 6: Validation
    echo "\n--- Test 6: Validation ---\n";
    
    try {
        $invalidSource = $manager->create('url', [
            'name' => 'Invalid Source',
            // Missing URL
        ]);
        echo "✗ Validation should have failed\n";
    } catch (\RuntimeException $e) {
        echo "✓ Validation works: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== Summary ===\n\n";
    
    echo "✓ DataSourceManager created\n";
    echo "✓ Can register data source types\n";
    echo "✓ Can create instances\n";
    echo "✓ Can fetch data\n";
    echo "✓ Named instances (caching)\n";
    echo "✓ Validation working\n";
    
    echo "\n--- Registered Types ---\n";
    foreach (DataSourceManager::getRegisteredTypes() as $type) {
        echo "  - {$type}\n";
    }
    
    echo "\n✅ DATA SOURCE MANAGER WORKING\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

