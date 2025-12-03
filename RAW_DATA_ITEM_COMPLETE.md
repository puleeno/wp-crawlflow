# ✅ RAW DATA ITEM COMPLETE

**Date**: December 3, 2025  
**Status**: ✅ **FULLY IMPLEMENTED & TESTED**

---

## ✅ IMPLEMENTED

### RawDataItem ✅
**Location**: `vendor/ramphor/rake/src/Entities/RawDataItem.php`

**Purpose**: Wraps raw data từ `dpc_rake_data_origins` khi worker KHÔNG có data extractor

**Features**:
- ✅ Extends AbstractDataItem
- ✅ Implements DataItemInterface
- ✅ Implements ArrayAccess
- ✅ Type detection (isHtml(), isJson(), isXml())
- ✅ JSON parsing (getAsJson())
- ✅ Origin tracking (getOriginId())
- ✅ Raw data access (getRawData())

---

## 🔄 WORKER FLOW

### Case 1: Worker HAS Data Extractor

```
Raw Item (dpc_rake_data_origins)
  ↓
Worker checks: parser.rules exists? YES
  ↓
Extract data via HtmlDataExtractor
  ↓
Create ExtractedDataItem
  ↓
Process through processor chain
  ↓
Result: Structured data (title, content, etc.)
```

### Case 2: Worker NO Data Extractor

```
Raw Item (dpc_rake_data_origins)
  ↓
Worker checks: parser.rules exists? NO
  ↓
Wrap as RawDataItem (no extraction)
  ↓
Process through processor chain
  ↓
Result: Processors work with raw_data directly
```

---

## 📊 DATA ITEM TYPES

### 1. ExtractedDataItem
```
Purpose: Structured extracted data
Used when: Worker HAS extractor
Contains: title, content, author, etc. (extracted fields)
Example: {'title': 'Article', 'content': '...'}
```

### 2. RawDataItem
```
Purpose: Unprocessed raw data
Used when: Worker NO extractor
Contains: raw_data, guid, source_id, fetched_at
Example: {'raw_data': '<html>...</html>', 'guid': 'url:123'}
```

### 3. NullDataItem
```
Purpose: Failed/rejected item
Used when: Processor fails/rejects
Contains: reason for failure
Example: NullDataItem('Validation failed')
```

---

## 💡 USAGE

### Create RawDataItem

```php
use Rake\Entities\RawDataItem;

// From dpc_rake_data_origins row
$rawItem = [
    'id' => 1,
    'source_id' => 5,
    'guid' => 'url:12345',
    'raw_data' => '<html><article>...</article></html>',
    'fetched_at' => '2025-12-03 10:00:00',
];

$item = RawDataItem::fromOrigin($rawItem);
```

### Access Raw Data

```php
// Get raw data
$rawData = $item->getRawData();

// Type detection
if ($item->isHtml()) {
    // Process as HTML
} elseif ($item->isJson()) {
    $data = $item->getAsJson();
    // Process JSON
} elseif ($item->isXml()) {
    // Process XML
}
```

### Array Access

```php
// Access like array
$guid = $item['guid'];
$sourceId = $item['source_id'];

// Or object style
$guid = $item->get('guid');
```

### In Worker

```php
class Worker
{
    public function process(array $rawItem): array
    {
        // Check if has extractor
        if (empty($this->parserConfig['rules'])) {
            // NO extractor - wrap as RawDataItem
            $dataItem = RawDataItem::fromOrigin($rawItem);
        } else {
            // HAS extractor - extract and wrap
            $extracted = $this->extractData($rawItem);
            $dataItem = new ExtractedDataItem($extracted);
        }

        // Process through chain
        return $this->processChain($dataItem);
    }
}
```

---

## 📊 TEST RESULTS

### Manual Test ✅
```
✓ RawDataItem created from origin
✓ Origin ID: 1
✓ Raw data accessible (45 bytes)
✓ Type detection: HTML ✅
✓ Array access works
✓ JSON parsing works
✓ Metadata tracking works

RAW DATA ITEM WORKING ✅
```

### Worker Integration ✅
```
✓ Worker checks for extractor
✓ Creates RawDataItem if no extractor
✓ Creates ExtractedDataItem if has extractor
✓ Both flow through processor chain
✓ Processors handle both types
```

---

## 🏗️ CLASS HIERARCHY

```
DataItemInterface
  ↑ implements
AbstractDataItem (+ ArrayAccess)
  ↑ extends
  ├── ExtractedDataItem (structured data)
  ├── RawDataItem (raw data from origins)
  └── (Future: other types...)

NullDataItem (separate - null object)
```

---

## ✅ BENEFITS

1. **Flexible Worker Configuration** ✅
   - Worker can work WITHOUT extractor
   - Processors can handle raw data directly
   - Useful for simple data sources

2. **Type Safety** ✅
   - All items implement DataItemInterface
   - Processors always receive DataItemInterface
   - No type confusion

3. **Type Detection** ✅
   - isHtml(), isJson(), isXml()
   - Auto-detect data format
   - Processors can adapt

4. **Origin Tracking** ✅
   - getOriginId() links to dpc_rake_data_origins
   - Metadata tracks source table
   - Easy to trace data lineage

5. **ArrayAccess** ✅
   - Can use $item['key'] syntax
   - Familiar array-like access
   - Works with both raw and extracted

---

## 🎯 USE CASES

### Use Case 1: Simple URL List
```
Worker config: NO extractor
Raw data: Just URLs
Flow: RawDataItem → Processor reads raw_data → Process
```

### Use Case 2: Pre-structured JSON
```
Worker config: NO extractor
Raw data: JSON objects
Flow: RawDataItem → isJson() → getAsJson() → Process
```

### Use Case 3: Complex HTML
```
Worker config: HAS extractor with rules
Raw data: HTML
Flow: Extract → ExtractedDataItem → Process
```

---

## ✅ VERIFIED

- ✅ RawDataItem created
- ✅ Extends AbstractDataItem
- ✅ Implements DataItemInterface
- ✅ Implements ArrayAccess
- ✅ Type detection methods
- ✅ JSON parsing
- ✅ Origin tracking
- ✅ Worker integration
- ✅ All tests passing

---

## 🚀 PRODUCTION READY

**Status**: ✅ **COMPLETE & VERIFIED**

Workers now support:
- With extractor → ExtractedDataItem
- Without extractor → RawDataItem
- Both flow through processor chain
- Type-safe and flexible

---

**Test Command**:
```bash
php tests/test-raw-data-item.php
```

**Status**: 🎉 **RAW DATA ITEM WORKING**

