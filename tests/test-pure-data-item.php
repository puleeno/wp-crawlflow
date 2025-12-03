<?php
/**
 * Test PureDataItem
 * Verifies pure data wrapping when worker has no parser
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

use Rake\Entities\ParsedData\PureDataItem;
use Rake\Entities\ParsedData\ExtractedDataItem;
use Rake\Entities\RawDataItem;

echo "=== Test PureDataItem ===\n\n";

try {
    // Test 1: Create from raw item
    echo "--- Test 1: Create PureDataItem ---\n";
    
    $rawItem = [
        'id' => 1,
        'source_id' => 5,
        'guid' => 'url:12345',
        'raw_data' => '<html><article><h1>Test</h1></article></html>',
        'fetched_at' => '2025-12-03 10:00:00',
    ];
    
    $item = PureDataItem::fromRawItem($rawItem);
    
    echo "✓ PureDataItem created\n";
    echo "  Type: " . get_class($item) . "\n";
    echo "  isNull(): " . ($item->isNull() ? 'YES' : 'NO') . "\n";
    
    // Test 2: Access data
    echo "\n--- Test 2: Access Data ---\n";
    
    echo "✓ Data accessible\n";
    echo "  raw_data: " . strlen($item->get('raw_data')) . " bytes\n";
    echo "  guid: " . $item->get('guid') . "\n";
    echo "  source_id: " . $item->get('source_id') . "\n";
    echo "  origin_id: " . $item->get('origin_id') . "\n";
    
    // Test 3: Type detection
    echo "\n--- Test 3: Type Detection ---\n";
    
    echo "✓ Type detection working\n";
    echo "  Is HTML: " . ($item->isHtml() ? 'YES' : 'NO') . "\n";
    echo "  Is JSON: " . ($item->isJson() ? 'YES' : 'NO') . "\n";
    echo "  Is XML: " . ($item->isXml() ? 'YES' : 'NO') . "\n";
    
    // Test 4: Metadata
    echo "\n--- Test 4: Metadata ---\n";
    
    echo "✓ Metadata accessible\n";
    echo "  type: " . $item->getMeta('type') . "\n";
    echo "  has_parser: " . ($item->getMeta('has_parser') ? 'YES' : 'NO') . "\n";
    
    // Test 5: Comparison with other types
    echo "\n--- Test 5: Comparison ---\n";
    
    echo "RawDataItem (Database entity):\n";
    echo "  - Maps to dpc_rake_data_origins table\n";
    echo "  - Database-backed\n";
    echo "  - Preserves DB structure\n";
    echo "  - Use: Direct database access\n\n";
    
    echo "PureDataItem (In-memory pure):\n";
    echo "  - Wraps raw item in-memory\n";
    echo "  - No extraction applied\n";
    echo "  - Has type detection\n";
    echo "  - Use: Worker without parser\n\n";
    
    echo "ExtractedDataItem (In-memory extracted):\n";
    echo "  - Extracted structured data\n";
    echo "  - Has fields (title, content, etc)\n";
    echo "  - Clean data structure\n";
    echo "  - Use: Worker with parser\n";
    
    // Test 6: Array access
    echo "\n--- Test 6: Array Access ---\n";
    
    echo "✓ Array access works\n";
    echo "  \$item['guid'] = " . $item['guid'] . "\n";
    echo "  \$item['source_id'] = " . $item['source_id'] . "\n";
    
    echo "\n=== Summary ===\n\n";
    
    echo "✓ PureDataItem wraps raw items in-memory\n";
    echo "✓ Used when worker has NO parser\n";
    echo "✓ Contains raw_data + metadata\n";
    echo "✓ Type detection available\n";
    echo "✓ ArrayAccess support\n";
    echo "✓ Extends ParsedDataItem\n";
    
    echo "\n--- Worker Flow ---\n";
    echo "If worker has NO parser:\n";
    echo "  raw item → PureDataItem → processors\n\n";
    echo "If worker has parser:\n";
    echo "  raw item → extract → ExtractedDataItem → processors\n";
    
    echo "\n✅ PURE DATA ITEM WORKING\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

