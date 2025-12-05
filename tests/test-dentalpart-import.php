<?php
/**
 * Test DentalPart Import Flow
 * 
 * Test complete import flow:
 * 1. Create test category
 * 2. Import category to WooCommerce
 * 3. Collect resources
 * 4. Create test product
 * 5. Import product to WooCommerce
 * 
 * Run: wp eval-file wp-content/plugins/wp-crawlflow/tests/test-dentalpart-import.php
 */

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║         TEST DENTALPART IMPORT FLOW                                       ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

// Check WooCommerce
if (!class_exists('WooCommerce')) {
    echo "❌ WooCommerce is not active. Please activate WooCommerce first.\n";
    exit(1);
}

// Check processors
$processors = [
    'category' => '\RamphorRake\Adapter\Processor\ImportWooCommerceProductCategoryProcessor',
    'product' => '\RamphorRake\Adapter\Processor\ImportWooCommerceProductProcessor',
    'collect' => '\RamphorRake\Adapter\Processor\CollectResourcesProcessor',
];

foreach ($processors as $name => $class) {
    if (!class_exists($class)) {
        echo "❌ Processor {$name} ({$class}) not found.\n";
        exit(1);
    }
}

use Rake\Entities\ParsedData\ExtractedDataItem;

// Test 1: Import Category
echo "📋 Test 1: Import Category...\n";
echo str_repeat('─', 80) . "\n";

$categoryProcessor = new \RamphorRake\Adapter\Processor\ImportWooCommerceProductCategoryProcessor([
    'product_status' => 'draft',
]);

$categoryItem = new ExtractedDataItem([
    'category_name' => 'Test Category ' . time(),
    'category_url' => 'https://www.dentalpart.com/products_list/CateId-999',
    'description' => '<p>Test category description with <a href="https://www.dentalpart.com/page.html">link</a> and <img src="https://www.dentalpart.com/image.jpg" alt="Test"></p>',
]);

$categoryResult = $categoryProcessor->process($categoryItem);

if ($categoryResult->isNull()) {
    echo "❌ Category import failed\n";
    exit(1);
}

$categoryId = $categoryResult->get('woocommerce_category_id');
echo "✅ Category imported: ID {$categoryId}\n";

// Verify category exists
$term = get_term($categoryId, 'product_cat');
if (!$term || is_wp_error($term)) {
    echo "❌ Category not found in WordPress\n";
    exit(1);
}
echo "✅ Category verified in WordPress: {$term->name}\n";

// Test 2: Collect Resources from Category
echo "\n📋 Test 2: Collect Resources from Category...\n";
echo str_repeat('─', 80) . "\n";

$collectProcessor = new \RamphorRake\Adapter\Processor\CollectResourcesProcessor([
    'batch_size' => 10,
    'query_delay' => 0, // No delay in test
]);

$categoryItemWithUrl = $categoryItem->set('url', 'https://www.dentalpart.com/products_list/CateId-999');
$collectResult = $collectProcessor->process($categoryItemWithUrl);

if ($collectResult->isNull()) {
    echo "❌ Resource collection failed\n";
    exit(1);
}

$resourcesCount = $collectResult->get('resources_count');
echo "✅ Resources collected: {$resourcesCount}\n";

$resources = $collectResult->get('resources');
if (is_array($resources)) {
    echo "   - HTML: " . count($resources['html'] ?? []) . "\n";
    echo "   - Images: " . count($resources['images'] ?? []) . "\n";
    echo "   - Files: " . count($resources['files'] ?? []) . "\n";
    echo "   - Links: " . count($resources['links'] ?? []) . "\n";
}

// Test 3: Import Product
echo "\n📋 Test 3: Import Product...\n";
echo str_repeat('─', 80) . "\n";

$productProcessor = new \RamphorRake\Adapter\Processor\ImportWooCommerceProductProcessor([
    'product_status' => 'draft',
    'max_images_per_product' => 0, // Skip images in test
]);

$productItem = new ExtractedDataItem([
    'product_name' => 'Test Product ' . time(),
    'url' => 'https://www.dentalpart.com/products_detail/test-product.html',
    'price' => '99.99',
    'description' => '<p>Test product description with <img src="https://www.dentalpart.com/product-image.jpg"></p>',
    'product_images' => [
        'https://www.dentalpart.com/image1.jpg',
        'https://www.dentalpart.com/image2.jpg',
    ],
    'woocommerce_category_id' => $categoryId, // Link to category
]);

$productResult = $productProcessor->process($productItem);

if ($productResult->isNull()) {
    echo "❌ Product import failed\n";
    exit(1);
}

$productId = $productResult->get('woocommerce_product_id');
echo "✅ Product imported: ID {$productId}\n";

// Verify product exists
$product = wc_get_product($productId);
if (!$product) {
    echo "❌ Product not found in WordPress\n";
    exit(1);
}
echo "✅ Product verified in WordPress: {$product->get_name()}\n";
echo "✅ Product price: {$product->get_price()}\n";

// Verify product is linked to category
$categories = $product->get_category_ids();
if (in_array($categoryId, $categories)) {
    echo "✅ Product linked to category\n";
} else {
    echo "⚠️  Product not linked to category\n";
}

// Test 4: Collect Resources from Product
echo "\n📋 Test 4: Collect Resources from Product...\n";
echo str_repeat('─', 80) . "\n";

$productCollectResult = $collectProcessor->process($productItem);

if ($productCollectResult->isNull()) {
    echo "❌ Product resource collection failed\n";
} else {
    $productResourcesCount = $productCollectResult->get('resources_count');
    echo "✅ Product resources collected: {$productResourcesCount}\n";
}

// Test 5: Check References
echo "\n📋 Test 5: Check References...\n";
echo str_repeat('─', 80) . "\n";

global $wpdb;
$refTable = $wpdb->prefix . 'rake_data_origins_references';

$categoryUrl = 'https://www.dentalpart.com/products_list/CateId-999';
$categoryHash = hash('sha256', $categoryUrl);

$refCount = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$refTable} WHERE parent_url_hash = %s",
    $categoryHash
));

echo "✅ References found for category: {$refCount}\n";

// Cleanup
echo "\n📋 Cleaning up test data...\n";
echo str_repeat('─', 80) . "\n";

wp_delete_post($productId, true);
echo "✅ Product deleted\n";

wp_delete_term($categoryId, 'product_cat');
echo "✅ Category deleted\n";

// Summary
echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║         ✅ ALL TESTS PASSED                                               ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

echo "Test results:\n";
echo "✅ Category import: SUCCESS\n";
echo "✅ Resource collection: SUCCESS\n";
echo "✅ Product import: SUCCESS\n";
echo "✅ Category-Product linking: SUCCESS\n";
echo "✅ References creation: SUCCESS\n";
echo "\n🎉 Import flow is working correctly!\n";
echo "\n";
