<?php
/**
 * Test All Processors Registration
 * Comprehensive test for all 5 processors
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

use Rake\Manager\ProcessorManager;
use CrawlFlow\Admin\RegistryService;

echo "=== Test All Processors ===\n\n";

try {
    // Test 1: ProcessorManager
    echo "--- Test 1: ProcessorManager ---\n";
    
    $types = ProcessorManager::getRegisteredTypes();
    echo "Registered types: " . count($types) . "\n";
    
    foreach ($types as $type) {
        echo "  ✓ {$type}\n";
    }
    
    // Test 2: Aliases
    echo "\n--- Test 2: Aliases ---\n";
    
    $aliases = [
        'wordpress_post' => 'save_to_wordpress',
        'wp_post' => 'save_to_wordpress',
        'save_to_db' => 'save_to_database',
        'database' => 'save_to_database',
        'api' => 'send_to_api',
        'webhook' => 'send_to_api',
        'csv' => 'generate_csv_file',
        'export_csv' => 'generate_csv_file',
        'email' => 'send_email_notification',
        'notify' => 'send_email_notification',
    ];
    
    foreach ($aliases as $alias => $main) {
        $has = ProcessorManager::has($alias);
        echo "  " . ($has ? '✓' : '✗') . " {$alias} → {$main}\n";
    }
    
    // Test 3: Registry UI Data
    echo "\n--- Test 3: Registry UI Data ---\n";
    
    $registry = new RegistryService();
    $data = $registry->getAllRegistryData();
    
    echo "Processors in UI registry: " . count($data['processors']) . "\n";
    
    foreach ($data['processors'] as $proc) {
        echo "  {$proc['icon']} {$proc['label']}\n";
        echo "    Type: {$proc['type']}\n";
        echo "    Description: {$proc['description']}\n\n";
    }
    
    // Test 4: Can create instances
    echo "--- Test 4: Create Instances ---\n";
    
    $manager = new ProcessorManager();
    
    foreach ($types as $type) {
        try {
            $processor = $manager->getProcessor($type);
            $className = get_class($processor);
            echo "  ✓ {$type} → {$className}\n";
        } catch (\Exception $e) {
            echo "  ✗ {$type}: {$e->getMessage()}\n";
        }
    }
    
    // Test 5: JSON for UI
    echo "\n--- Test 5: JSON for UI ---\n";
    
    $json = json_encode($data['processors'], JSON_PRETTY_PRINT);
    echo "JSON size: " . strlen($json) . " bytes\n";
    echo "JSON valid: " . (json_last_error() === JSON_ERROR_NONE ? 'YES' : 'NO') . "\n";
    
    echo "\n=== Summary ===\n\n";
    
    $expected = 5;
    $actual = count($types);
    
    if ($actual === $expected) {
        echo "✅ ALL {$expected} PROCESSORS REGISTERED\n\n";
        
        echo "Processors:\n";
        echo "  1. 💾 Save to WordPress\n";
        echo "  2. 🗄️ Save to Database\n";
        echo "  3. 🌐 Send to API\n";
        echo "  4. 📊 Generate CSV File\n";
        echo "  5. 📧 Send Email Notification\n\n";
        
        echo "✅ READY FOR UI\n";
    } else {
        echo "✗ Expected {$expected} processors, got {$actual}\n";
        exit(1);
    }
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

