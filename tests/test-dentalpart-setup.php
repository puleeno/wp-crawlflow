<?php
/**
 * Test DentalPart Setup
 * 
 * Test tất cả components đã được setup đúng:
 * 1. Database tables
 * 2. Processors registration
 * 3. Plugin activation
 * 4. Basic functionality
 * 
 * Run: wp eval-file wp-content/plugins/wp-crawlflow/tests/test-dentalpart-setup.php
 */

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║         TEST DENTALPART SETUP                                            ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

global $wpdb;

$errors = [];
$warnings = [];
$success = [];

// Test 1: Check database tables
echo "📋 Testing Database Tables...\n";
echo str_repeat('─', 80) . "\n";

$tables = [
    $wpdb->prefix . 'rake_data_origins_references' => 'References table',
    $wpdb->prefix . 'rake_data_sources' => 'Resources table',
    $wpdb->prefix . 'rake_data_origins' => 'Data origins table',
];

foreach ($tables as $table => $description) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        echo "✅ {$description} ({$table}): EXISTS ({$count} rows)\n";
        $success[] = $table;
    } else {
        echo "❌ {$description} ({$table}): NOT FOUND\n";
        $errors[] = "Table {$table} not found";
    }
}

// Test 2: Check processors registration
echo "\n📋 Testing Processors Registration...\n";
echo str_repeat('─', 80) . "\n";

$processors = [
    'get_dentalpart_product_prices' => 'Get Product Prices Processor',
    'import_woocommerce_product_category' => 'Import WooCommerce Product Category',
    'import_woocommerce_product' => 'Import WooCommerce Product',
    'scan_category_pages' => 'Scan Category Pages',
    'collect_resources' => 'Collect Resources',
];

foreach ($processors as $key => $name) {
    if (class_exists('\Rake\Manager\ProcessorManager')) {
        $has = \Rake\Manager\ProcessorManager::has($key);
        if ($has) {
            echo "✅ {$name}: REGISTERED\n";
            $success[] = $key;
        } else {
            echo "❌ {$name}: NOT REGISTERED\n";
            $errors[] = "Processor {$key} not registered";
        }
    } else {
        echo "⚠️  ProcessorManager not available (plugin may not be loaded)\n";
        $warnings[] = "ProcessorManager not available";
        break;
    }
}

// Test 3: Check processor classes exist
echo "\n📋 Testing Processor Classes...\n";
echo str_repeat('─', 80) . "\n";

$classes = [
    '\RamphorRake\Adapter\Processor\GetProductPricesProcessor' => 'Get Product Prices',
    '\RamphorRake\Adapter\Processor\ImportWooCommerceProductCategoryProcessor' => 'Import Category',
    '\RamphorRake\Adapter\Processor\ImportWooCommerceProductProcessor' => 'Import Product',
    '\RamphorRake\Adapter\Processor\ScanCategoryPagesProcessor' => 'Scan Pages',
    '\RamphorRake\Adapter\Processor\CollectResourcesProcessor' => 'Collect Resources',
    '\RamphorRake\Adapter\Helper\UrlExtractorHelper' => 'URL Extractor Helper',
];

foreach ($classes as $class => $name) {
    if (class_exists($class)) {
        echo "✅ {$name}: CLASS EXISTS\n";
        $success[] = $class;
    } else {
        echo "❌ {$name}: CLASS NOT FOUND\n";
        $errors[] = "Class {$class} not found";
    }
}

// Test 4: Check WooCommerce (if needed)
echo "\n📋 Testing WooCommerce...\n";
echo str_repeat('─', 80) . "\n";

if (class_exists('WooCommerce')) {
    echo "✅ WooCommerce: ACTIVE\n";
    $success[] = 'woocommerce';
} else {
    echo "⚠️  WooCommerce: NOT ACTIVE (required for product/category import)\n";
    $warnings[] = "WooCommerce not active";
}

// Test 5: Test URL extraction
echo "\n📋 Testing URL Extraction...\n";
echo str_repeat('─', 80) . "\n";

if (class_exists('\RamphorRake\Adapter\Helper\UrlExtractorHelper')) {
    $html = '<a href="https://www.dentalpart.com/products_detail/test1.html">Product 1</a>
             <a href="/products_detail/test2.html">Product 2</a>';
    
    $baseUrl = 'https://www.dentalpart.com/products_list/CateId-123';
    $urls = \RamphorRake\Adapter\Helper\UrlExtractorHelper::extractUrls($html, $baseUrl);
    
    if (count($urls) >= 2) {
        echo "✅ URL Extraction: WORKING ({count($urls)} URLs extracted)\n";
        $success[] = 'url_extraction';
    } else {
        echo "❌ URL Extraction: FAILED (expected 2, got " . count($urls) . ")\n";
        $errors[] = "URL extraction failed";
    }
} else {
    echo "❌ URL Extractor Helper: NOT FOUND\n";
    $errors[] = "UrlExtractorHelper not found";
}

// Summary
echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║         TEST SUMMARY                                                     ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

echo "✅ Success: " . count($success) . " tests passed\n";
if (!empty($warnings)) {
    echo "⚠️  Warnings: " . count($warnings) . "\n";
    foreach ($warnings as $warning) {
        echo "   - {$warning}\n";
    }
}
if (!empty($errors)) {
    echo "❌ Errors: " . count($errors) . "\n";
    foreach ($errors as $error) {
        echo "   - {$error}\n";
    }
} else {
    echo "✅ No errors found!\n";
}

echo "\n";

if (empty($errors)) {
    echo "🎉 All tests passed! Setup is complete.\n";
    echo "\nNext steps:\n";
    echo "1. Activate plugin 'wp-crawlflow-dentalpart'\n";
    echo "2. Create a new project in CrawlFlow UI\n";
    echo "3. Setup data source: URL Source with https://www.dentalpart.com\n";
    echo "4. Setup workers with processors as documented\n";
} else {
    echo "⚠️  Some errors found. Please fix them before proceeding.\n";
}

echo "\n";
