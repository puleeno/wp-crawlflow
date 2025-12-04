# 🔌 CRAWLFLOW EXTENSIBILITY GUIDE

**CrawlFlow Plugin Extension Documentation**  
**Version**: 2.0  
**Last Updated**: December 4, 2025

---

## 📖 TABLE OF CONTENTS

1. [Register Data Sources](#register-data-sources)
2. [Register Processors](#register-processors)
3. [Register HTTP Clients](#register-http-clients)
4. [Add Custom Fields](#add-custom-fields)
5. [Field Types Reference](#field-types-reference)
6. [Complete Example](#complete-example)

---

## 🔌 REGISTER DATA SOURCES

### Basic Registration

```php
add_action('crawlflow_register_data_sources', function() {
    \Rake\Manager\DataSourceManager::registerType(
        'shopify',
        MyShopifyDataSource::class
    );
});
```

### With UI Metadata

```php
add_filter('crawlflow_registered_data_sources', function($dataSources) {
    $dataSources[] = [
        'type' => 'shopify',
        'label' => 'Shopify Store',
        'description' => 'Fetch products from Shopify API',
        'icon' => '🛍️',
        'configFields' => [
            [
                'name' => 'shopUrl',
                'type' => 'text',
                'label' => 'Shop URL',
                'required' => true,
                'placeholder' => 'mystore.myshopify.com',
            ],
            [
                'name' => 'apiKey',
                'type' => 'password',
                'label' => 'API Key',
                'required' => true,
            ],
        ],
    ];
    return $dataSources;
});
```

---

## ⚙️ REGISTER PROCESSORS

### Step 1: Create Processor Class

```php
<?php

namespace MyPlugin\Processors;

use Rake\Processor\AbstractProcessor;
use Rake\Contracts\Entities\ParsedDataItemInterface;

class CustomProcessor extends AbstractProcessor
{
    public function process(ParsedDataItemInterface $item): ParsedDataItemInterface
    {
        // Skip if null
        if ($item->isNull()) {
            return $item;
        }

        // Get config
        $option1 = $this->getConfig('option1', 'default');
        $enableFeature = $this->getConfig('enableFeature', false);

        // Apply field mappings if configured
        $data = $item->getData();
        $data = $this->applyFieldMappings($data);

        // Process data
        try {
            // Your logic here
            $result = $this->processData($data, $option1, $enableFeature);
            
            return $item->set('custom_processed', true);
        } catch (\Exception $e) {
            return $this->createNullItem('Processing failed: ' . $e->getMessage());
        }
    }

    private function applyFieldMappings(array $data): array
    {
        $autoMap = $this->getConfig('autoMapFields', false);
        $mappings = $this->getConfig('fieldMappings', []);

        if ($autoMap || empty($mappings)) {
            return $data;
        }

        $mapped = [];
        foreach ($mappings as $from => $to) {
            if (isset($data[$from])) {
                $mapped[$to] = $data[$from];
            }
        }
        return $mapped;
    }
}
```

### Step 2: Register Processor

```php
add_action('crawlflow_register_processors', function() {
    \Rake\Manager\ProcessorManager::register(
        'my_custom_processor',
        \MyPlugin\Processors\CustomProcessor::class,
        [
            'option1' => 'default_value',
            'enableFeature' => false,
        ]
    );
    
    // Optional: Add aliases
    \Rake\Manager\ProcessorManager::alias('custom', 'my_custom_processor');
});
```

### Step 3: Define UI Fields

```php
add_filter('crawlflow_processor_ui_data', function($data, $type) {
    if ($type === 'my_custom_processor') {
        $data['label'] = 'My Custom Processor';
        $data['icon'] = '🚀';
        $data['description'] = 'Custom data processing logic';
        $data['configFields'] = [
            [
                'name' => 'option1',
                'type' => 'text',
                'label' => 'Custom Option',
                'default' => 'default_value',
                'required' => true,
                'placeholder' => 'Enter custom value',
                'description' => 'This option controls feature X',
            ],
            [
                'name' => 'enableFeature',
                'type' => 'checkbox',
                'label' => 'Enable Special Feature',
                'default' => false,
                'placeholder' => 'Activate advanced mode',
            ],
            [
                'name' => 'mode',
                'type' => 'select',
                'label' => 'Processing Mode',
                'options' => [
                    'fast' => 'Fast Mode',
                    'accurate' => 'Accurate Mode',
                    'balanced' => 'Balanced Mode',
                ],
                'default' => 'balanced',
            ],
        ];
    }
    return $data;
}, 10, 2);
```

**Result**: UI dropdown shows "🚀 My Custom Processor" with 3 config fields auto-rendered!

---

## 🌐 REGISTER HTTP CLIENTS

### Step 1: Create HTTP Client Class

```php
use Rake\Contracts\Http\HttpClientInterface;
use Rake\Contracts\Http\HttpResponseInterface;

class MyHttpClient implements HttpClientInterface
{
    public function get(string $url, array $options = []): HttpResponseInterface {
        // Implementation
    }

    public function post(string $url, array $options = []): HttpResponseInterface {
        // Implementation
    }

    public function request(string $method, string $url, array $options = []): HttpResponseInterface {
        // Implementation
    }
}
```

### Step 2: Register Client

```php
add_action('init', function() {
    \Rake\Manager\HttpClientManager::register('my_client', new MyHttpClient());
});
```

### Step 3: Add to UI

```php
add_filter('crawlflow_registered_http_clients', function($clients) {
    $clients[] = [
        'name' => 'my_client',
        'label' => 'My HTTP Client',
        'description' => 'Custom HTTP client with special features',
        'icon' => '⚡',
    ];
    return $clients;
});
```

---

## 🎨 FIELD TYPES REFERENCE

### Text Input

```php
[
    'name' => 'username',
    'type' => 'text',
    'label' => 'Username',
    'default' => '',
    'required' => true,
    'placeholder' => 'Enter username',
    'description' => 'Your account username',
]
```

**Renders**: `<input type="text" placeholder="Enter username" />`

### Number Input

```php
[
    'name' => 'timeout',
    'type' => 'number',
    'label' => 'Timeout (seconds)',
    'default' => 30,
    'placeholder' => '30',
]
```

### Password Input

```php
[
    'name' => 'apiKey',
    'type' => 'password',
    'label' => 'API Key',
    'required' => true,
]
```

### URL Input

```php
[
    'name' => 'webhookUrl',
    'type' => 'url',
    'label' => 'Webhook URL',
    'placeholder' => 'https://example.com/webhook',
]
```

### Select Dropdown (Array Options)

```php
[
    'name' => 'format',
    'type' => 'select',
    'label' => 'Output Format',
    'options' => ['json' => 'JSON', 'xml' => 'XML', 'csv' => 'CSV'],
    'default' => 'json',
]
```

**Renders**:
```html
<select>
    <option value="json">JSON</option>
    <option value="xml">XML</option>
    <option value="csv">CSV</option>
</select>
```

### Checkbox

```php
[
    'name' => 'enableCache',
    'type' => 'checkbox',
    'label' => 'Enable Caching',
    'default' => true,
    'placeholder' => 'Cache results for faster processing',
]
```

### Textarea

```php
[
    'name' => 'template',
    'type' => 'textarea',
    'label' => 'Email Template',
    'default' => 'Hello {{name}}',
    'placeholder' => 'Use {{field}} placeholders',
    'description' => 'HTML template for email body',
]
```

---

## 📋 COMPLETE EXAMPLE

### Plugin: WooCommerce Sync

```php
<?php
/**
 * Plugin Name: CrawlFlow WooCommerce Sync
 * Description: Sync extracted data to WooCommerce products
 */

// 1. Create processor
class WooCommerceSyncProcessor extends \Rake\Processor\AbstractProcessor
{
    public function process(\Rake\Contracts\Entities\ParsedDataItemInterface $item): \Rake\Contracts\Entities\ParsedDataItemInterface
    {
        if ($item->isNull()) {
            return $item;
        }

        $data = $item->getData();
        $data = $this->applyFieldMappings($data);

        $productType = $this->getConfig('productType', 'simple');
        $publishImmediately = $this->getConfig('publishImmediately', false);

        // Create WooCommerce product
        $product = new \WC_Product_Simple();
        $product->set_name($data['name'] ?? 'Untitled');
        $product->set_regular_price($data['price'] ?? 0);
        $product->set_description($data['description'] ?? '');
        $product->set_status($publishImmediately ? 'publish' : 'draft');
        
        $productId = $product->save();

        return $item->set('wc_product_id', $productId);
    }

    private function applyFieldMappings(array $data): array
    {
        $mappings = $this->getConfig('fieldMappings', []);
        if (empty($mappings)) return $data;

        $mapped = [];
        foreach ($mappings as $from => $to) {
            if (isset($data[$from])) {
                $mapped[$to] = $data[$from];
            }
        }
        return $mapped;
    }
}

// 2. Register on init
add_action('crawlflow_register_processors', function() {
    \Rake\Manager\ProcessorManager::register(
        'woocommerce_sync',
        WooCommerceSyncProcessor::class,
        [
            'productType' => 'simple',
            'publishImmediately' => false,
        ]
    );
});

// 3. Define UI metadata
add_filter('crawlflow_processor_ui_data', function($data, $type) {
    if ($type === 'woocommerce_sync') {
        $data['label'] = 'Sync to WooCommerce';
        $data['icon'] = '🛒';
        $data['description'] = 'Create or update WooCommerce products';
        $data['configFields'] = [
            [
                'name' => 'productType',
                'type' => 'select',
                'label' => 'Product Type',
                'options' => [
                    'simple' => 'Simple Product',
                    'variable' => 'Variable Product',
                    'grouped' => 'Grouped Product',
                ],
                'default' => 'simple',
            ],
            [
                'name' => 'publishImmediately',
                'type' => 'checkbox',
                'label' => 'Publish Immediately',
                'default' => false,
                'placeholder' => 'Publish products without review',
            ],
            [
                'name' => 'categoryId',
                'type' => 'number',
                'label' => 'Default Category ID',
                'placeholder' => 'Enter category ID',
                'description' => 'Products will be added to this category',
            ],
        ];
    }
    return $data;
}, 10, 2);
```

**Result in UI**:
- Dropdown shows: "🛒 Sync to WooCommerce"
- 3 config fields auto-render
- Field mapping table available
- User can map: `title` → `name`, `price` → `regular_price`, etc.

---

## 🎓 BEST PRACTICES

### 1. Use Descriptive Labels

```php
// ✓ Good
'label' => 'API Endpoint URL'

// ✗ Bad
'label' => 'URL'
```

### 2. Provide Placeholders

```php
'placeholder' => 'https://api.example.com/data'
```

### 3. Add Descriptions

```php
'description' => 'The REST API endpoint to send extracted data. Supports HTTPS only.'
```

### 4. Set Sensible Defaults

```php
'default' => 'default_value'
```

### 5. Mark Required Fields

```php
'required' => true  // Shows * in UI
```

### 6. Use Key-Value for Select Options

```php
// ✓ Good - clear labels
'options' => [
    'fast' => 'Fast Mode (Less Accurate)',
    'accurate' => 'Accurate Mode (Slower)',
]

// ✗ Bad - unclear
'options' => ['fast', 'accurate']
```

---

## 🔍 TESTING YOUR EXTENSION

### Test Registration

```bash
php -r "require 'wp-load.php'; 
    use Rake\Manager\ProcessorManager;
    echo ProcessorManager::has('your_processor_type') ? 'Registered ✓' : 'Not found ✗';"
```

### Test UI Data

```bash
php -r "require 'wp-load.php'; 
    \CrawlFlow\Hooks\RegistryHooks::init();
    \$r = new \CrawlFlow\Admin\RegistryService();
    \$d = \$r->getAllRegistryData();
    print_r(\$d['processors']);"
```

### Test in Browser

1. Go to CrawlFlow admin page
2. Open DevTools Console (F12)
3. Type: `window.crawlflowRegistry.processors`
4. Look for your processor

---

## 📚 HOOKS REFERENCE

### Actions

- `crawlflow_register_data_sources` - Register data source types
- `crawlflow_register_processors` - Register processors
- `crawlflow_register_parsers` - Register parsers
- `crawlflow_after_register_defaults` - After built-in registration

### Filters

- `crawlflow_registered_data_sources` - Modify data sources array
- `crawlflow_data_source_ui_data` - Modify single data source UI data
- `crawlflow_registered_processors` - Modify processors array
- `crawlflow_processor_ui_data` - Modify single processor UI data
- `crawlflow_registered_parsers` - Modify parsers array
- `crawlflow_registered_http_clients` - Modify HTTP clients array
- `crawlflow_http_client_ui_data` - Modify single HTTP client UI data

---

## 💡 EXAMPLES

### Example 1: Google Sheets Processor

```php
class GoogleSheetsProcessor extends AbstractProcessor {
    public function process(ParsedDataItemInterface $item): ParsedDataItemInterface {
        $spreadsheetId = $this->getConfig('spreadsheetId');
        $sheetName = $this->getConfig('sheetName');
        
        // Apply field mappings
        $data = $this->applyFieldMappings($item->getData());
        
        // Append to Google Sheet
        // ... Google API logic
        
        return $item->set('sheet_updated', true);
    }
    
    private function applyFieldMappings(array $data): array {
        $mappings = $this->getConfig('fieldMappings', []);
        if (empty($mappings)) return $data;
        
        $mapped = [];
        foreach ($mappings as $from => $to) {
            if (isset($data[$from])) $mapped[$to] = $data[$from];
        }
        return $mapped;
    }
}

// Register
add_action('crawlflow_register_processors', function() {
    ProcessorManager::register('google_sheets', GoogleSheetsProcessor::class);
});

// UI
add_filter('crawlflow_processor_ui_data', function($data, $type) {
    if ($type === 'google_sheets') {
        $data['label'] = 'Export to Google Sheets';
        $data['icon'] = '📊';
        $data['configFields'] = [
            ['name' => 'spreadsheetId', 'type' => 'text', 'label' => 'Spreadsheet ID', 'required' => true],
            ['name' => 'sheetName', 'type' => 'text', 'label' => 'Sheet Name', 'default' => 'Sheet1'],
        ];
    }
    return $data;
}, 10, 2);
```

### Example 2: Slack Notification

```php
class SlackNotificationProcessor extends AbstractProcessor {
    public function process(ParsedDataItemInterface $item): ParsedDataItemInterface {
        $webhookUrl = $this->getConfig('webhookUrl');
        $message = $this->getConfig('message', 'New data: {{title}}');
        
        $data = $item->getData();
        
        // Replace placeholders
        foreach ($data as $key => $value) {
            $message = str_replace('{{' . $key . '}}', $value, $message);
        }
        
        // Send to Slack
        // ... webhook logic
        
        return $item->set('slack_sent', true);
    }
}

// Register
add_action('crawlflow_register_processors', function() {
    ProcessorManager::register('slack_notify', SlackNotificationProcessor::class);
});

// UI
add_filter('crawlflow_processor_ui_data', function($data, $type) {
    if ($type === 'slack_notify') {
        $data['label'] = 'Slack Notification';
        $data['icon'] = '💬';
        $data['configFields'] = [
            ['name' => 'webhookUrl', 'type' => 'url', 'label' => 'Webhook URL', 'required' => true],
            ['name' => 'message', 'type' => 'textarea', 'label' => 'Message Template', 
             'default' => 'New data found: {{title}}', 
             'description' => 'Use {{field}} for data placeholders'],
        ];
    }
    return $data;
}, 10, 2);
```

---

## 📝 FIELD DEFINITION SCHEMA

```php
[
    'name' => string,              // REQUIRED - Field identifier
    'type' => string,              // REQUIRED - Field type (see types below)
    'label' => string,             // REQUIRED - Display label
    'default' => mixed,            // Optional - Default value
    'required' => bool,            // Optional - Show * if true
    'placeholder' => string,       // Optional - Placeholder text
    'description' => string,       // Optional - Help text below field
    'options' => array,            // For 'select' type - ['val' => 'Label']
]
```

---

## 🎯 SUPPORT

**Issues**: https://github.com/your-repo/issues  
**Forum**: https://wordpress.org/support/plugin/wp-crawlflow  
**Docs**: https://crawlflow.com/docs

---

**Happy extending!** 🚀

