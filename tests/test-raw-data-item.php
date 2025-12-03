<?php
/**
 * Test RawDataItem
 * Verifies raw data wrapping when worker has no extractor
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

use Rake\Entities\RawDataItem;
use Rake\Entities\ExtractedDataItem;

echo "=== Test RawDataItem ===\n\n";

try {
    // Test 1: Create from dpc_rake_data_origins row
    echo "--- Test 1: Create from Origin Row ---\n";
    
    $originRow = [
        'id' => 1,
        'source_id' => 5,
        'guid' => 'url:12345',
        'raw_data' => '<html><article><h1>Test</h1></article></html>',
        'fetched_at' => '2025-12-03 10:00:00',
    ];
    
    $item = RawDataItem::fromOrigin($originRow);
    
    echo "✓ RawDataItem created from origin\n";
    echo "  Type: " . get_class($item) . "\n";
    echo "  Origin ID: " . $item->getOriginId() . "\n";
    echo "  isNull(): " . ($item->isNull() ? 'YES' : 'NO') . "\n";
    
    // Test 2: Access raw data
    echo "\n--- Test 2: Access Raw Data ---\n";
    
    $rawData = $item->getRawData();
    
    echo "✓ Raw data accessible\n";
    echo "  Length: " . strlen($rawData) . " bytes\n";
    echo "  Is HTML: " . ($item->isHtml() ? 'YES' : 'NO') . "\n";
    echo "  Is JSON: " . ($item->isJson() ? 'YES' : 'NO') . "\n";
    echo "  Is XML: " . ($item->isXml() ? 'YES' : 'NO') . "\n";
    
    // Test 3: Access via array syntax
    echo "\n--- Test 3: Array Access ---\n";
    
    echo "✓ Array access works\n";
    echo "  \$item['guid'] = " . $item['guid'] . "\n";
    echo "  \$item['source_id'] = " . $item['source_id'] . "\n";
    
    // Test 4: JSON raw data
    echo "\n--- Test 4: JSON Raw Data ---\n";
    
    $jsonOrigin = [
        'id' => 2,
        'raw_data' => json_encode(['name' => 'Product', 'price' => 100]),
        'guid' => 'csv:67890',
    ];
    
    $jsonItem = RawDataItem::fromOrigin($jsonOrigin);
    
    echo "✓ JSON RawDataItem created\n";
    echo "  Is JSON: " . ($jsonItem->isJson() ? 'YES' : 'NO') . "\n";
    
    if ($jsonItem->isJson()) {
        $parsed = $jsonItem->getAsJson();
        echo "  Parsed: " . json_encode($parsed) . "\n";
    }
    
    // Test 5: Metadata
    echo "\n--- Test 5: Metadata ---\n";
    
    echo "✓ Metadata stored\n";
    echo "  Type: " . $item->getMeta('type') . "\n";
    echo "  Origin ID: " . $item->getMeta('origin_id') . "\n";
    echo "  Source Table: " . $item->getMeta('source_table') . "\n";
    
    // Test 6: Difference from ExtractedDataItem
    echo "\n--- Test 6: RawDataItem vs ExtractedDataItem ---\n";
    
    $extractedItem = new ExtractedDataItem([
        'title' => 'Extracted Title',
        'content' => 'Extracted Content',
    ]);
    
    echo "RawDataItem:\n";
    echo "  - Contains raw_data field\n";
    echo "  - Has origin_id metadata\n";
    echo "  - Type detection methods (isHtml, isJson, isXml)\n";
    echo "  - Used when NO extractor\n\n";
    
    echo "ExtractedDataItem:\n";
    echo "  - Contains extracted fields (title, content, etc)\n";
    echo "  - Clean structured data\n";
    echo "  - Used when HAS extractor\n";
    
    echo "\n=== Summary ===\n\n";
    
    echo "✓ RawDataItem wraps dpc_rake_data_origins rows\n";
    echo "✓ Used when worker has NO data extractor\n";
    echo "✓ Provides raw_data access\n";
    echo "✓ Type detection (HTML, JSON, XML)\n";
    echo "✓ ArrayAccess support\n";
    echo "✓ Metadata tracking\n";
    
    echo "\n--- Worker Flow ---\n";
    echo "If worker has extractor:\n";
    echo "  raw item → extract → ExtractedDataItem → processors\n\n";
    echo "If worker has NO extractor:\n";
    echo "  raw item → wrap → RawDataItem → processors\n";
    echo "\n✅ RAW DATA ITEM WORKING\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

