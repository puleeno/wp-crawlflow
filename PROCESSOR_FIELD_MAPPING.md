# 📋 Processor Field Mapping Feature

**Date**: December 4, 2025  
**Status**: 🔨 **IN PROGRESS**

---

## 🎯 REQUIREMENT

User needs ability to map extracted data fields to processor-specific fields:

### Example: Save to Database
```
Extracted Field → Database Column
---------------------------------
title          → product_title
price          → product_price
description    → product_desc
image_url      → main_image
```

---

## 📊 CURRENT STATUS

### ✅ Config Fields Added

All processors now have config fields in registry:

**Save to Database** (8 fields):
- Database Type, Host, Port, Username, Password
- Database Name, Table Name, Duplicate Handling

**Send to API** (3 fields):
- API Endpoint URL, HTTP Method, Authentication Type

**Generate CSV File** (3 fields):
- File Name Pattern, Delimiter, Include Header Row

**Send Email Notification** (3 fields):
- Recipients, Email Subject, Email Body

### ❌ Missing: Field Mapping UI

Need to add field mapping section similar to the image shown:
- Auto Map Fields section
- Table with "EXTRACTED FIELD" → "DB COLUMN"
- Add/Remove mapping rows
- Dynamic based on extracted data

---

## 🔧 IMPLEMENTATION PLAN

### 1. Update Registry Data Structure

Add `supportsFieldMapping` flag to processors:

```php
// RegistryService.php
$processorData = [
    'type' => $type,
    'label' => $this->getProcessorLabel($type),
    'description' => $this->getProcessorDescription($type),
    'icon' => $this->getProcessorIcon($type),
    'configFields' => $this->getProcessorConfigFields($type),
    'supportsFieldMapping' => $this->supportsFieldMapping($type), // NEW
];
```

### 2. Define Which Processors Support Mapping

```php
private function supportsFieldMapping(string $type): bool
{
    return in_array($type, [
        'save_to_database',
        'send_to_api',
        'generate_csv_file',
        // NOT: send_email_notification (uses template placeholders)
        // NOT: save_to_wordpress (has predefined fields)
    ]);
}
```

### 3. Update React UI Component

**ProcessorNodeSettings.tsx** needs:

```typescript
// Show field mapping section if processor supports it
{processor?.supportsFieldMapping && (
  <CollapsibleSection title="Auto Map Fields" defaultOpen>
    <FieldMappingTable
      mappings={data.fieldMappings || []}
      onChange={(mappings) => handleSettingsChange('fieldMappings', mappings)}
    />
  </CollapsibleSection>
)}
```

### 4. Create FieldMappingTable Component

```typescript
interface FieldMapping {
  id: string;
  extractedField: string;
  targetField: string;
}

const FieldMappingTable: React.FC<{
  mappings: FieldMapping[];
  onChange: (mappings: FieldMapping[]) => void;
}> = ({ mappings, onChange }) => {
  // Render table with add/remove rows
  // Two columns: EXTRACTED FIELD → TARGET FIELD
};
```

### 5. Update Processor Classes

Processors should use field mappings:

```php
// SaveToDatabaseProcessor.php
public function process(ParsedDataItemInterface $item): ParsedDataItemInterface
{
    $data = $item->getData();
    $fieldMappings = $this->getConfig('fieldMappings', []);
    
    // Apply field mappings
    $mappedData = [];
    foreach ($fieldMappings as $mapping) {
        $extractedField = $mapping['extractedField'];
        $targetField = $mapping['targetField'];
        
        if (isset($data[$extractedField])) {
            $mappedData[$targetField] = $data[$extractedField];
        }
    }
    
    // Use $mappedData for database insert
    // ...
}
```

---

## 📝 DATA STRUCTURE

### Registry Data (Backend)

```javascript
{
  type: "save_to_database",
  label: "Save to Database",
  icon: "🗄️",
  configFields: [...],
  supportsFieldMapping: true  // NEW
}
```

### Processor Node Data (Frontend)

```typescript
interface ProcessorNodeData {
  processorType: string;
  settings: {
    // Config fields
    connectionType?: string;
    host?: string;
    // ...
    
    // Field mappings (NEW)
    fieldMappings?: Array<{
      id: string;
      extractedField: string;
      targetField: string;
    }>;
  };
}
```

---

## 🎨 UI MOCKUP

```
┌─────────────────────────────────────┐
│ Configuration                    ▲  │
├─────────────────────────────────────┤
│ Database Type: [MySQL        ▼]    │
│ Host: [localhost              ]    │
│ ...                                 │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│ Auto Map Fields                  ▲  │
├─────────────────────────────────────┤
│ EXTRACTED FIELD  →  DB COLUMN       │
│ [title         ] → [title        ]  │
│ [price         ] → [price        ]  │
│ [description   ] → [description  ]  │
│                                     │
│ [+ Add Mapping]                     │
└─────────────────────────────────────┘
```

---

## ✅ NEXT STEPS

1. [ ] Add `supportsFieldMapping()` method to RegistryService
2. [ ] Update registry data to include flag
3. [ ] Create `FieldMappingTable.tsx` component
4. [ ] Update `ProcessorNodeSettings.tsx` to show mapping section
5. [ ] Update processor classes to use mappings
6. [ ] Test with Save to Database processor
7. [ ] Document field mapping usage

---

**Status**: ✅ **CONFIG FIELDS COMPLETE**  
**Next**: 🔨 **FIELD MAPPING UI**

