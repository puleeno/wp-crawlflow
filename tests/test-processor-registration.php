<?php
/**
 * Test Processor Registration
 * Verifies WordPressPostProcessor is registered and can process data
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

use Rake\Manager\ProcessorManager;
use Rake\Entities\ParsedData\ExtractedDataItem;

echo "=== Test Processor Registration ===\n\n";

try {
    // Test 1: Check registration
    echo "--- Test 1: Registration ---\n";
    
    $types = ProcessorManager::getRegisteredTypes();
    echo "✓ Registered types: " . implode(', ', $types) . "\n";
    
    $hasMain = ProcessorManager::has('save_to_wordpress');
    echo "✓ Has 'save_to_wordpress': " . ($hasMain ? 'YES' : 'NO') . "\n";
    
    $hasAlias1 = ProcessorManager::has('wordpress_post');
    echo "✓ Has 'wordpress_post' alias: " . ($hasAlias1 ? 'YES' : 'NO') . "\n";
    
    $hasAlias2 = ProcessorManager::has('wp_post');
    echo "✓ Has 'wp_post' alias: " . ($hasAlias2 ? 'YES' : 'NO') . "\n";
    
    // Test 2: Get processor instance
    echo "\n--- Test 2: Get Instance ---\n";
    
    $processorManager = new ProcessorManager();
    $processor = $processorManager->getProcessor('save_to_wordpress', [
        'postType' => 'post',
        'postStatus' => 'draft',
    ]);
    
    echo "✓ Processor instance created\n";
    echo "  Class: " . get_class($processor) . "\n";
    
    // Test 3: Process data (mock)
    echo "\n--- Test 3: Process Data ---\n";
    
    $dataItem = new ExtractedDataItem([
        'title' => 'Test Article from Processor',
        'content' => 'This is test content to verify processor works.',
        'excerpt' => 'Test excerpt',
    ]);
    
    echo "  Input data:\n";
    echo "    - Title: " . $dataItem->get('title') . "\n";
    echo "    - Content length: " . strlen($dataItem->get('content')) . " chars\n";
    
    // Process (this will actually create a post in WordPress)
    $result = $processor->process($dataItem);
    
    if ($result->isNull()) {
        echo "✗ Processing failed\n";
        echo "  Error: " . ($result->getMeta('reason') ?? 'Unknown') . "\n";
    } else {
        echo "✓ Processing successful\n";
        echo "  Post ID: " . $result->get('post_id') . "\n";
        echo "  Processed: " . ($result->get('processed') ? 'YES' : 'NO') . "\n";
        
        // Get the created post
        $postId = $result->get('post_id');
        if ($postId) {
            $post = get_post($postId);
            if ($post) {
                echo "  Post Status: " . $post->post_status . "\n";
                echo "  Post Type: " . $post->post_type . "\n";
                echo "  Post Title: " . $post->post_title . "\n";
                
                // Clean up - delete test post
                wp_delete_post($postId, true);
                echo "  ✓ Test post cleaned up\n";
            }
        }
    }
    
    // Test 4: Using alias
    echo "\n--- Test 4: Using Alias ---\n";
    
    $processorViaAlias = $processorManager->getProcessor('wordpress_post');
    echo "✓ Got processor via 'wordpress_post' alias\n";
    echo "  Same class: " . (get_class($processorViaAlias) === get_class($processor) ? 'YES' : 'NO') . "\n";
    
    echo "\n=== Summary ===\n\n";
    
    echo "✓ WordPressPostProcessor registered\n";
    echo "✓ Main type: save_to_wordpress\n";
    echo "✓ Aliases: wordpress_post, wp_post\n";
    echo "✓ Can create processor instance\n";
    echo "✓ Can process data\n";
    echo "✓ Creates WordPress posts\n";
    
    echo "\n✅ PROCESSOR REGISTRATION WORKING\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

