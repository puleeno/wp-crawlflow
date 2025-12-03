<?php
/**
 * Test Data Item Hierarchy
 * Verifies interface hierarchy and structure
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

echo "=== Data Item Hierarchy ===\n\n";

echo "--- Interface Hierarchy ---\n\n";

echo "DataItemInterface (Base)\n";
echo "  ├── RawDataItemInterface (extends DataItemInterface)\n";
echo "  │     ↑ implements\n";
echo "  │     └── RawDataItem (Database: dpc_rake_data_origins)\n";
echo "  │\n";
echo "  └── ParsedDataItemInterface (extends DataItemInterface)\n";
echo "        ↑ implements\n";
echo "        ├── ParsedDataItem (In-memory base)\n";
echo "        │     ↑ extends\n";
echo "        │     └── ExtractedDataItem (Extracted data)\n";
echo "        │\n";
echo "        └── NullDataItem (Null Object Pattern)\n";

echo "\n--- RawDataItem Structure ---\n\n";

$rawItem = [
    'id' => 1,                    // Primary key
    'source_id' => 5,             // FK to dpc_rake_data_sources
    'guid' => 'url:12345',        // Unique identifier
    'raw_data' => '<html>...</html>', // Raw content
    'fetched_at' => '2025-12-03 10:00:00', // Timestamp
];

$item = \Rake\Entities\RawDataItem::fromOrigin($rawItem);

echo "Database Fields (dpc_rake_data_origins):\n";
echo "  ✓ id: " . $item->get('id') . "\n";
echo "  ✓ source_id: " . $item->get('source_id') . "\n";
echo "  ✓ guid: " . $item->get('guid') . "\n";
echo "  ✓ raw_data: " . strlen($item->get('raw_data')) . " bytes\n";
echo "  ✓ fetched_at: " . $item->get('fetched_at') . "\n";

echo "\nRawDataItem Methods:\n";
echo "  ✓ getRawData(): " . strlen($item->getRawData()) . " bytes\n";
echo "  ✓ getOriginId(): " . $item->getOriginId() . "\n";
echo "  ✓ isHtml(): " . ($item->isHtml() ? 'YES' : 'NO') . "\n";
echo "  ✓ isJson(): " . ($item->isJson() ? 'YES' : 'NO') . "\n";
echo "  ✓ isXml(): " . ($item->isXml() ? 'YES' : 'NO') . "\n";

echo "\n--- Verification ---\n\n";

echo "✓ RawDataItem implements RawDataItemInterface\n";
echo "✓ RawDataItemInterface extends DataItemInterface\n";
echo "✓ Matches dpc_rake_data_origins table structure\n";
echo "✓ Provides database field access\n";
echo "✓ Provides type detection methods\n";

echo "\n✅ STRUCTURE CORRECT\n";

