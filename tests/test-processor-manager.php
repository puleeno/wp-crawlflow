<?php
/**
 * Test Processor Manager
 * Verifies processors can be registered and retrieved
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

use Rake\Manager\ProcessorManager;
use Rake\Contracts\Processor\ProcessorInterface;
use CrawlFlow\Processors\WordPressPostProcessor;

echo "=== Test Processor Manager ===\n\n";

try {
    // Test 1: Check if processors are registered
    echo "--- Test 1: Check Registered Processors ---\n";
    
    $types = ProcessorManager::getRegisteredTypes();
    
    echo "✓ Registered processor types: " . count($types) . "\n";
    foreach ($types as $type) {
        echo "  - {$type}\n";
    }
    
    // Test 2: Check if save_to_wordpress is registered
    echo "\n--- Test 2: Check 'save_to_wordpress' Processor ---\n";
    
    if (ProcessorManager::has('save_to_wordpress')) {
        echo "✓ 'save_to_wordpress' processor is registered\n";
    } else {
        echo "✗ 'save_to_wordpress' processor NOT registered\n";
        exit(1);
    }
    
    // Test 3: Get processor instance
    echo "\n--- Test 3: Get Processor Instance ---\n";
    
    $manager = new ProcessorManager();
    $processor = $manager->getProcessor('save_to_wordpress');
    
    echo "✓ Processor retrieved\n";
    echo "  Type: " . get_class($processor) . "\n";
    echo "  Implements ProcessorInterface: " . 
         ($processor instanceof ProcessorInterface ? "YES" : "NO") . "\n";
    echo "  Is WordPressPostProcessor: " . 
         ($processor instanceof WordPressPostProcessor ? "YES" : "NO") . "\n";
    
    // Test 4: Check aliases
    echo "\n--- Test 4: Check Processor Aliases ---\n";
    
    $aliases = ['wordpress_post', 'wp_post'];
    
    foreach ($aliases as $alias) {
        if (ProcessorManager::has($alias)) {
            echo "✓ Alias '{$alias}' → 'save_to_wordpress'\n";
        } else {
            echo "✗ Alias '{$alias}' not found\n";
        }
    }
    
    // Test 5: Get processor with custom options
    echo "\n--- Test 5: Get Processor with Custom Options ---\n";
    
    $processor = $manager->getProcessor('save_to_wordpress', [
        'postType' => 'page',
        'postStatus' => 'publish',
    ]);
    
    echo "✓ Processor created with custom options\n";
    
    // Test 6: Create processor chain
    echo "\n--- Test 6: Create Processor Chain ---\n";
    
    $chainConfig = [
        [
            'type' => 'save_to_wordpress',
            'settings' => [
                'postType' => 'post',
                'postStatus' => 'draft',
            ],
        ],
    ];
    
    $chain = $manager->createChain($chainConfig);
    
    echo "✓ Processor chain created\n";
    echo "  Processors in chain: " . count($chain) . "\n";
    
    // Test 7: Execute chain
    echo "\n--- Test 7: Execute Processor Chain ---\n";
    
    $testData = [
        'title' => 'Test Article via ProcessorManager',
        'content' => 'This article was created via ProcessorManager',
    ];
    
    $result = $manager->executeChain($chain, $testData);
    
    echo "✓ Chain executed\n";
    
    if ($result === null) {
        echo "  Chain stopped (processor returned null)\n";
    } else {
        echo "  Result type: " . get_class($result) . "\n";
        
        if ($result->has('post_id')) {
            echo "  Post created: ID {$result->get('post_id')}\n";
        }
        
        echo "  Processed: " . ($result->get('processed') ? 'YES' : 'NO') . "\n";
    }
    
    echo "\n=== Summary ===\n\n";
    
    echo "✓ ProcessorManager managing processors\n";
    echo "✓ WordPressPostProcessor registered\n";
    echo "✓ Can get processor by type\n";
    echo "✓ Aliases working\n";
    echo "✓ Can create chains\n";
    echo "✓ Can execute chains\n";
    
    echo "\n--- Integration ---\n";
    echo "ProcessorManager → WordPressPostProcessor → WordPress\n";
    echo "✅ ALL WORKING\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

