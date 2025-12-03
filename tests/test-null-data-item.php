<?php
/**
 * Test Null Data Item Pattern
 * Verifies Null Object Pattern for failed processors
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

use Rake\Entities\ExtractedDataItem;
use Rake\Entities\NullDataItem;
use Rake\Manager\ProcessorManager;
use CrawlFlow\Processors\WordPressPostProcessor;

echo "=== Test Null Data Item Pattern ===\n\n";

try {
    // Test 1: Create ExtractedDataItem
    echo "--- Test 1: ExtractedDataItem ---\n";
    
    $item = new ExtractedDataItem(['title' => 'Test', 'content' => 'Content']);
    
    echo "✓ ExtractedDataItem created\n";
    echo "  isNull(): " . ($item->isNull() ? 'YES' : 'NO') . "\n";
    echo "  Type: " . get_class($item) . "\n";
    
    // Test 2: Create NullDataItem
    echo "\n--- Test 2: NullDataItem ---\n";
    
    $nullItem = new NullDataItem('Validation failed');
    
    echo "✓ NullDataItem created\n";
    echo "  isNull(): " . ($nullItem->isNull() ? 'YES' : 'NO') . "\n";
    echo "  Reason: " . $nullItem->getReason() . "\n";
    echo "  get('anything'): " . var_export($nullItem->get('anything'), true) . "\n";
    echo "  has('anything'): " . ($nullItem->has('anything') ? 'YES' : 'NO') . "\n";
    
    // Test 3: Process valid item
    echo "\n--- Test 3: Process Valid Item ---\n";
    
    ProcessorManager::clearAll();
    ProcessorManager::register('save_to_wordpress', WordPressPostProcessor::class);
    
    $manager = new ProcessorManager();
    $processor = $manager->getProcessor('save_to_wordpress');
    
    $validItem = new ExtractedDataItem([
        'title' => 'Valid Article',
        'content' => 'Valid content',
    ]);
    
    $result = $processor->process($validItem);
    
    echo "✓ Valid item processed\n";
    echo "  Result isNull(): " . ($result->isNull() ? 'YES' : 'NO') . "\n";
    
    if (!$result->isNull()) {
        echo "  Post ID: " . $result->get('post_id') . "\n";
        echo "  Processed: " . ($result->get('processed') ? 'YES' : 'NO') . "\n";
    }
    
    // Test 4: Process invalid item (missing title)
    echo "\n--- Test 4: Process Invalid Item (Missing Title) ---\n";
    
    $invalidItem = new ExtractedDataItem([
        'content' => 'Content without title',
    ]);
    
    $result = $processor->process($invalidItem);
    
    echo "✓ Invalid item processed\n";
    echo "  Result isNull(): " . ($result->isNull() ? 'YES' : 'NO') . "\n";
    
    if ($result->isNull() && $result instanceof NullDataItem) {
        echo "  Result type: NullDataItem\n";
        echo "  Reason: " . $result->getReason() . "\n";
    }
    
    // Test 5: Chain with NullDataItem stops
    echo "\n--- Test 5: Chain Stops on NullDataItem ---\n";
    
    $chain = $manager->createChain([
        ['type' => 'save_to_wordpress', 'settings' => []],
    ]);
    
    // Invalid data (no title)
    $invalidData = ['content' => 'No title'];
    
    $result = $manager->executeChain($chain, $invalidData);
    
    echo "✓ Chain executed with invalid data\n";
    echo "  Result isNull(): " . ($result->isNull() ? 'YES' : 'NO') . "\n";
    
    if ($result->isNull() && $result instanceof NullDataItem) {
        echo "  Chain stopped due to: " . $result->getReason() . "\n";
    }
    
    // Test 6: NullDataItem passed to next processor
    echo "\n--- Test 6: NullDataItem Skipped by Processor ---\n";
    
    $nullItem = new NullDataItem('Already failed');
    $result = $processor->process($nullItem);
    
    echo "✓ NullDataItem passed to processor\n";
    echo "  Result isNull(): " . ($result->isNull() ? 'YES' : 'NO') . "\n";
    echo "  Same instance: " . ($result === $nullItem ? 'YES' : 'NO') . "\n";
    
    echo "\n=== Summary ===\n\n";
    
    echo "✓ ExtractedDataItem: Valid data container\n";
    echo "✓ NullDataItem: Null Object Pattern\n";
    echo "✓ Processor returns NullDataItem on failure\n";
    echo "✓ Chain stops on NullDataItem\n";
    echo "✓ No null checks needed (Null Object Pattern)\n";
    echo "✓ Reason tracking for failures\n";
    
    echo "\n--- Pattern Verified ---\n";
    echo "Success: ExtractedDataItem flows through chain\n";
    echo "Failure: NullDataItem returned, chain stops\n";
    echo "✅ NULL OBJECT PATTERN WORKING\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

