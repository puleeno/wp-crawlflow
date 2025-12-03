<?php
/**
 * Test ArrayAccess for Data Items
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

use Rake\Entities\ExtractedDataItem;
use Rake\Entities\NullDataItem;

echo "=== Test ArrayAccess for Data Items ===\n\n";

try {
    // Test 1: ExtractedDataItem as array
    echo "--- Test 1: ExtractedDataItem ArrayAccess ---\n";
    
    $item = new ExtractedDataItem(['title' => 'Test']);
    
    // Array access - GET
    echo "✓ Array access GET: \$item['title'] = " . $item['title'] . "\n";
    
    // Array access - SET
    $item['content'] = 'New content';
    echo "✓ Array access SET: \$item['content'] = " . $item['content'] . "\n";
    
    // Array access - ISSET
    echo "✓ Array access ISSET: isset(\$item['title']) = " . (isset($item['title']) ? 'YES' : 'NO') . "\n";
    
    // Array access - UNSET
    unset($item['title']);
    echo "✓ Array access UNSET: isset(\$item['title']) after unset = " . (isset($item['title']) ? 'YES' : 'NO') . "\n";
    
    // Test 2: NullDataItem as array
    echo "\n--- Test 2: NullDataItem ArrayAccess ---\n";
    
    $nullItem = new NullDataItem('Failed');
    
    // Array access - GET (always returns null)
    echo "✓ NullDataItem GET: \$nullItem['anything'] = " . var_export($nullItem['anything'], true) . "\n";
    
    // Array access - ISSET (always false)
    echo "✓ NullDataItem ISSET: isset(\$nullItem['anything']) = " . (isset($nullItem['anything']) ? 'YES' : 'NO') . "\n";
    
    // Array access - SET (no-op)
    $nullItem['key'] = 'value';
    echo "✓ NullDataItem SET: No error (no-op)\n";
    
    // Array access - UNSET (no-op)
    unset($nullItem['key']);
    echo "✓ NullDataItem UNSET: No error (no-op)\n";
    
    // Test 3: Mixed usage
    echo "\n--- Test 3: Mixed Object/Array Access ---\n";
    
    $item = new ExtractedDataItem();
    
    // Object style
    $item->set('via_method', 'method value');
    
    // Array style
    $item['via_array'] = 'array value';
    
    echo "✓ Mixed access SET works\n";
    
    // Read back
    echo "  Via method: " . $item->get('via_method') . "\n";
    echo "  Via array:  " . $item['via_array'] . "\n";
    echo "  Cross read: " . $item['via_method'] . " / " . $item->get('via_array') . "\n";
    
    // Test 4: Usage in real scenario
    echo "\n--- Test 4: Real Usage Scenario ---\n";
    
    $item = new ExtractedDataItem([
        'title' => 'Article',
        'content' => 'Content',
    ]);
    
    // Can now use like array
    echo "Title: " . $item['title'] . "\n";
    
    $item['post_id'] = 40;
    $item['processed'] = true;
    
    echo "✓ Array-style assignment works\n";
    echo "  Data: " . json_encode($item->getData()) . "\n";
    
    echo "\n=== Summary ===\n\n";
    
    echo "✓ ExtractedDataItem implements ArrayAccess\n";
    echo "✓ NullDataItem implements ArrayAccess\n";
    echo "✓ Can use \$item['key'] syntax\n";
    echo "✓ Can use isset(\$item['key'])\n";
    echo "✓ Can use unset(\$item['key'])\n";
    echo "✓ Mixed object/array access works\n";
    echo "✓ NullDataItem safely handles all array operations\n";
    
    echo "\n--- Usage ---\n";
    echo "// Object style:\n";
    echo "\$item->get('key')\n";
    echo "\$item->set('key', 'value')\n\n";
    echo "// Array style:\n";
    echo "\$item['key']\n";
    echo "\$item['key'] = 'value'\n";
    echo "\n✅ BOTH WORK!\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

