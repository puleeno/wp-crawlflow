<?php

namespace CrawlFlow\Tests\Integration\Processor;

use PHPUnit\Framework\TestCase;
use RamphorRake\Adapter\Processor\GetProductPricesProcessor;
use RamphorRake\Adapter\Processor\ImportWooCommerceProductCategoryProcessor;
use RamphorRake\Adapter\Processor\ImportWooCommerceProductProcessor;
use RamphorRake\Adapter\Processor\ScanCategoryPagesProcessor;
use RamphorRake\Adapter\Helper\UrlExtractorHelper;
use Rake\Entities\ParsedData\ExtractedDataItem;

/**
 * Integration tests for DentalPart processors
 * 
 * These tests verify the complete flow:
 * 1. Category import -> Category scan -> Product price fetch -> Product import
 */
class DentalPartIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Skip if WooCommerce is not active
        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not active');
        }
    }

    /**
     * Test complete category import flow
     */
    public function testCategoryImportFlow()
    {
        // Step 1: Import category
        $categoryProcessor = new ImportWooCommerceProductCategoryProcessor([
            'product_status' => 'draft',
        ]);

        $categoryItem = new ExtractedDataItem([
            'category_name' => 'Test Category ' . time(),
            'category_url' => 'https://www.dentalpart.com/products_list/CateId-999',
            'description' => 'Test category description',
        ]);

        $result = $categoryProcessor->process($categoryItem);

        $this->assertFalse($result->isNull());
        $this->assertTrue($result->get('category_imported'));
        $this->assertNotEmpty($result->get('woocommerce_category_id'));

        $categoryId = $result->get('woocommerce_category_id');

        // Verify category exists in WordPress
        $term = get_term($categoryId, 'product_cat');
        $this->assertNotNull($term);
        $this->assertFalse(is_wp_error($term));

        // Cleanup
        wp_delete_term($categoryId, 'product_cat');
    }

    /**
     * Test URL extraction and saving
     */
    public function testUrlExtractionAndSaving()
    {
        global $wpdb;
        $tableName = $wpdb->prefix . 'rake_data_origins';

        $html = '<a href="https://www.dentalpart.com/products_detail/test1.html">Product 1</a>
                 <a href="https://www.dentalpart.com/products_detail/test2.html">Product 2</a>';

        $baseUrl = 'https://www.dentalpart.com/products_list/CateId-123';
        
        // Extract URLs
        $urls = UrlExtractorHelper::extractUrls($html, $baseUrl);
        
        $this->assertIsArray($urls);
        $this->assertCount(2, $urls);

        // Save URLs with threshold settings
        $savedUrls = UrlExtractorHelper::saveUrls($urls, null, [
            'batch_size' => 10,
            'query_delay' => 0, // No delay in tests
        ]);

        $this->assertIsArray($savedUrls);
        
        // Verify URLs were saved
        foreach ($savedUrls as $url) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tableName} WHERE guid = %s",
                $url
            ));
            $this->assertNotEmpty($exists);
        }

        // Cleanup
        foreach ($savedUrls as $url) {
            $wpdb->delete($tableName, ['guid' => $url], ['%s']);
        }
    }

    /**
     * Test references creation
     */
    public function testReferencesCreation()
    {
        global $wpdb;
        $tableName = $wpdb->prefix . 'rake_data_origins_references';

        $parentUrl = 'https://www.dentalpart.com/products_list/CateId-123';
        $childUrls = [
            'https://www.dentalpart.com/products_detail/product1.html',
            'https://www.dentalpart.com/products_detail/product2.html',
        ];

        $processor = new ScanCategoryPagesProcessor([
            'max_pages' => 1, // Only test first page
            'page_delay' => 0,
        ]);

        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('createReferences');
        $method->setAccessible(true);

        $createdCount = $method->invoke($processor, $parentUrl, $childUrls);

        $this->assertGreaterThan(0, $createdCount);

        // Verify references were created
        $parentHash = hash('sha256', $parentUrl);
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tableName} WHERE parent_url_hash = %s",
            $parentHash
        ));

        $this->assertGreaterThan(0, $count);

        // Cleanup
        $wpdb->delete($tableName, ['parent_url_hash' => $parentHash], ['%s']);
    }

    /**
     * Test processor chain: Category -> Scan -> Product
     */
    public function testProcessorChain()
    {
        // This test verifies that processors can be chained together
        // Category processor sets woocommerce_category_id
        // Product processor should use it to link product to category

        $categoryProcessor = new ImportWooCommerceProductCategoryProcessor();
        $productProcessor = new ImportWooCommerceProductProcessor([
            'product_status' => 'draft',
            'max_images_per_product' => 0, // Skip image downloads in test
        ]);

        // Create category
        $categoryItem = new ExtractedDataItem([
            'category_name' => 'Test Category Chain ' . time(),
            'category_url' => 'https://www.dentalpart.com/products_list/CateId-888',
        ]);

        $categoryResult = $categoryProcessor->process($categoryItem);
        $this->assertFalse($categoryResult->isNull());

        $categoryId = $categoryResult->get('woocommerce_category_id');

        // Create product linked to category
        $productItem = new ExtractedDataItem([
            'product_name' => 'Test Product ' . time(),
            'price' => '99.99',
            'description' => 'Test product description',
            'woocommerce_category_id' => $categoryId, // From previous processor
        ]);

        $productResult = $productProcessor->process($productItem);
        $this->assertFalse($productResult->isNull());
        $this->assertTrue($productResult->get('product_imported'));

        $productId = $productResult->get('woocommerce_product_id');

        // Verify product is linked to category
        $product = wc_get_product($productId);
        $this->assertNotNull($product);
        $categories = $product->get_category_ids();
        $this->assertContains($categoryId, $categories);

        // Cleanup
        wp_delete_post($productId, true);
        wp_delete_term($categoryId, 'product_cat');
    }

    /**
     * Test threshold settings are respected
     */
    public function testThresholdSettingsRespected()
    {
        $processor = new GetProductPricesProcessor([
            'request_delay' => 100,
            'max_retries' => 2,
            'timeout' => 15,
        ]);

        $this->assertEquals(100, $processor->getConfig('request_delay'));
        $this->assertEquals(2, $processor->getConfig('max_retries'));
        $this->assertEquals(15, $processor->getConfig('timeout'));

        $scanProcessor = new ScanCategoryPagesProcessor([
            'max_pages' => 5,
            'page_delay' => 100000,
            'timeout' => 20,
        ]);

        $this->assertEquals(5, $scanProcessor->getConfig('max_pages'));
        $this->assertEquals(100000, $scanProcessor->getConfig('page_delay'));
        $this->assertEquals(20, $scanProcessor->getConfig('timeout'));
    }
}
