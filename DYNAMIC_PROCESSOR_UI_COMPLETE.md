# ✅ DYNAMIC PROCESSOR UI COMPLETE

**Date**: December 4, 2025  
**Status**: ✅ **FULLY IMPLEMENTED**

---

## 🎯 ACHIEVEMENT

### ✅ PHP-Driven UI Configuration

**All processor settings now defined in PHP** → UI renders dynamically!

- ✅ Config fields defined in `RegistryService.php`
- ✅ UI renders fields based on PHP metadata
- ✅ No hardcoded UI components for each processor
- ✅ External plugins can add custom fields
- ✅ Field mapping support included

---

## 📊 PROCESSOR CONFIGURATION

### 1. 💾 Save to WordPress (2 fields)

```php
[
    ['name' => 'postType', 'type' => 'select', 'label' => 'Post Type', 
     'options' => ['post' => 'Post', 'page' => 'Page'], 'default' => 'post'],
    ['name' => 'postStatus', 'type' => 'select', 'label' => 'Post Status', 
     'options' => ['draft' => 'Draft', 'publish' => 'Publish'], 'default' => 'draft'],
]
```

### 2. 🗄️ Save to Database (8 fields)

```php
[
    ['name' => 'connectionType', 'type' => 'select', 'label' => 'Database Type', 
     'options' => ['mysql' => 'MySQL', 'postgresql' => 'PostgreSQL'], 'default' => 'mysql'],
    ['name' => 'host', 'type' => 'text', 'label' => 'Host', 'default' => 'localhost'],
    ['name' => 'port', 'type' => 'text', 'label' => 'Port', 'default' => '3306'],
    ['name' => 'user', 'type' => 'text', 'label' => 'Username', 'default' => 'root'],
    ['name' => 'password', 'type' => 'password', 'label' => 'Password', 'default' => ''],
    ['name' => 'database', 'type' => 'text', 'label' => 'Database Name', 
     'default' => 'scraped_data', 'required' => true],
    ['name' => 'tableName', 'type' => 'text', 'label' => 'Table Name', 
     'default' => 'results', 'required' => true],
    ['name' => 'conflictStrategy', 'type' => 'select', 'label' => 'Duplicate Handling', 
     'options' => ['insert' => 'Insert', 'upsert' => 'Upsert', 'skip' => 'Skip'], 
     'default' => 'upsert'],
]
```

### 3. 🌐 Send to API (3 fields)

```php
[
    ['name' => 'endpointUrl', 'type' => 'url', 'label' => 'API Endpoint URL', 
     'required' => true, 'placeholder' => 'https://api.example.com/data'],
    ['name' => 'method', 'type' => 'select', 'label' => 'HTTP Method', 
     'options' => ['POST' => 'POST', 'PUT' => 'PUT', 'PATCH' => 'PATCH'], 'default' => 'POST'],
    ['name' => 'authType', 'type' => 'select', 'label' => 'Authentication Type', 
     'options' => ['none' => 'None', 'api-key' => 'API Key', 'bearer' => 'Bearer', 'basic' => 'Basic'], 
     'default' => 'none'],
]
```

### 4. 📊 Generate CSV File (3 fields)

```php
[
    ['name' => 'fileName', 'type' => 'text', 'label' => 'File Name Pattern', 
     'default' => 'crawl_results_{{date}}.csv', 
     'description' => 'Use {{date}}, {{datetime}}, {{timestamp}} placeholders'],
    ['name' => 'delimiter', 'type' => 'select', 'label' => 'Delimiter', 
     'options' => [',' => 'Comma', ';' => 'Semicolon', '\t' => 'Tab'], 'default' => ','],
    ['name' => 'includeHeader', 'type' => 'checkbox', 'label' => 'Include Header Row', 
     'default' => true],
]
```

### 5. 📧 Send Email Notification (3 fields)

```php
[
    ['name' => 'recipients', 'type' => 'text', 'label' => 'Recipients', 
     'required' => true, 'description' => 'Multiple emails separated by commas'],
    ['name' => 'subject', 'type' => 'text', 'label' => 'Email Subject', 
     'default' => 'Crawl Finished', 'description' => 'Use {{field}} placeholders'],
    ['name' => 'body', 'type' => 'textarea', 'label' => 'Email Body', 
     'default' => 'Data extracted', 'description' => 'Use {{field}} placeholders'],
]
```

---

## 🎨 UI RENDERING (Dynamic)

### React Component (SettingsPanel.tsx)

```typescript
// No hardcoded fields - all from PHP!
processors.find(p => p.type === data.processorType)?.configFields?.map(field => {
    const value = settings[field.name] ?? field.default ?? '';
    
    return (
        <div key={field.name}>
            <label>{field.label} {field.required && '*'}</label>
            
            {field.type === 'select' && (
                <select value={value} onChange={...}>
                    {Object.entries(field.options).map(([val, label]) => (
                        <option key={val} value={val}>{label}</option>
                    ))}
                </select>
            )}
            
            {field.type === 'textarea' && (
                <textarea value={value} onChange={...} />
            )}
            
            {field.type === 'checkbox' && (
                <input type="checkbox" checked={value} onChange={...} />
            )}
            
            {['text', 'number', 'url', 'password'].includes(field.type) && (
                <input type={field.type} value={value} onChange={...} />
            )}
            
            {field.description && <p>{field.description}</p>}
        </div>
    );
})
```

---

## 🔧 FIELD MAPPING FEATURE

### Auto Map Mode

```typescript
// Checkbox in UI
<input 
    type="checkbox" 
    checked={settings.autoMapFields} 
    onChange={e => handleSettingsChange('autoMapFields', e.target.checked)}
/>
```

**When enabled**: Fields passed as-is (no renaming)

### Custom Mapping Mode

```typescript
// Table UI
<div className="space-y-2">
    <div className="flex">
        <span>EXTRACTED FIELD</span> → <span>TARGET FIELD</span>
    </div>
    {availableFields.map(field => (
        <div className="flex">
            <div>{field}</div> → 
            <input 
                value={settings.fieldMappings?.[field] || ''} 
                onChange={e => updateMapping(field, e.target.value)}
            />
        </div>
    ))}
</div>
```

**Backend Processing**:
```php
private function applyFieldMappings(array $data): array
{
    if ($this->getConfig('autoMapFields', false)) {
        return $data; // No mapping
    }
    
    $mappings = $this->getConfig('fieldMappings', []);
    $mappedData = [];
    
    foreach ($mappings as $extractedField => $targetField) {
        if (isset($data[$extractedField])) {
            $mappedData[$targetField] = $data[$extractedField];
        }
    }
    
    return $mappedData;
}
```

---

## 🚀 EXTENSIBILITY

### Add Custom Processor with Custom Fields

```php
// 1. Register processor
ProcessorManager::register('my_processor', MyProcessor::class, [
    'customOption' => 'default_value',
]);

// 2. Add UI fields via filter
add_filter('crawlflow_processor_ui_data', function($data, $type) {
    if ($type === 'my_processor') {
        $data['configFields'] = [
            [
                'name' => 'customOption',
                'type' => 'text',
                'label' => 'My Custom Option',
                'default' => 'default_value',
                'required' => true,
                'placeholder' => 'Enter custom value',
                'description' => 'This is a custom field',
            ],
            [
                'name' => 'enableFeature',
                'type' => 'checkbox',
                'label' => 'Enable Special Feature',
                'default' => false,
            ],
        ];
    }
    return $data;
}, 10, 2);
```

**UI will automatically render**:
- Text input for `customOption`
- Checkbox for `enableFeature`
- With labels, placeholders, descriptions
- With default values

---

## 📋 FIELD TYPES SUPPORTED

| Type | UI Element | Example |
|------|-----------|---------|
| `text` | Text input | Name, URL, etc. |
| `number` | Number input | Port, timeout |
| `password` | Password input | DB password |
| `url` | URL input | API endpoint |
| `select` | Dropdown | Options list |
| `checkbox` | Checkbox | Enable/disable |
| `textarea` | Text area | Email body, template |

---

## ✅ BENEFITS

1. **No UI Hardcoding** ✅
   - All fields from PHP
   - UI auto-adapts
   - Add fields without touching React

2. **Extensibility** ✅
   - Plugins can add fields
   - Custom processors supported
   - Filters available

3. **Consistency** ✅
   - Single source of truth (PHP)
   - Backend validates fields
   - UI always in sync

4. **Field Mapping** ✅
   - Auto-map mode
   - Custom mapping table
   - Upstream field detection
   - Applied in processors

---

## 🎉 BEFORE vs AFTER

### Before (Hardcoded)

```typescript
// SettingsPanel.tsx - hardcoded for each processor
{data.processorType === 'save-to-database' && (
    <>
        <select>...</select>
        <input type="text" placeholder="Host" />
        <input type="text" placeholder="Database" />
        // ... 5 more hardcoded inputs
    </>
)}
```

### After (Dynamic)

```typescript
// SettingsPanel.tsx - renders any processor
{processors.find(p => p.type === data.processorType)?.configFields?.map(field => 
    renderField(field) // Dynamic rendering
)}
```

**Add new field in PHP** → **UI auto-renders**!

---

## 📖 TEST RESULTS

```
✅ ALL 5 PROCESSORS REGISTERED

Config Fields:
  💾 Save to WordPress: 2 fields
  🗄️ Save to Database: 8 fields
  🌐 Send to API: 3 fields
  📊 Generate CSV File: 3 fields
  📧 Send Email: 3 fields

Field Mapping:
  ✓ Auto map mode
  ✓ Custom mappings
  ✓ Applied in processors
  ✓ UI renders mapping table
```

---

## 🚀 IMPLEMENTATION COMPLETE

- [x] 5 built-in processors created
- [x] All processors registered in ProcessorManager
- [x] Config fields defined in PHP
- [x] UI renders fields dynamically
- [x] Field types supported (select, text, textarea, checkbox, etc.)
- [x] Field mapping feature implemented
- [x] Auto-map and custom mapping modes
- [x] Backend applies field mappings
- [x] Tests verified
- [x] Build successful

---

**Status**: ✅ **FULLY DYNAMIC**  
**Extensibility**: ✅ **100% PHP-DRIVEN**  
**UI**: ✅ **AUTO-ADAPTS TO PHP CONFIG**

