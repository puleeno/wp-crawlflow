<?php

namespace CrawlFlow\Tests\Unit\Processor;

use PHPUnit\Framework\TestCase;
use RamphorRake\Adapter\Processor\GetProductPricesProcessor;
use Rake\Entities\ParsedData\ExtractedDataItem;
use Mockery;

/**
 * Unit tests for GetProductPricesProcessor
 */
class GetProductPricesProcessorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test extract product ID from URL
     */
    public function testExtractProductIdFromUrl()
    {
        $processor = new GetProductPricesProcessor([
            'api_url' => 'https://www.dentalpart.com/api/web/attr_price',
            'request_delay' => 0, // No delay in tests
        ]);

        $item = new ExtractedDataItem([
            'url' => 'https://www.dentalpart.com/products_detail/3401.html',
        ]);

        // Use reflection to test private method
        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('extractProductId');
        $method->setAccessible(true);

        $productId = $method->invoke($processor, $item);
        
        $this->assertNotEmpty($productId);
        $this->assertStringContainsString('3401', $productId);
    }

    /**
     * Test extract product ID from item data
     */
    public function testExtractProductIdFromItemData()
    {
        $processor = new GetProductPricesProcessor();

        $item = new ExtractedDataItem([
            'product_id' => '3401',
            'url' => 'https://www.dentalpart.com/products_detail/test.html',
        ]);

        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('extractProductId');
        $method->setAccessible(true);

        $productId = $method->invoke($processor, $item);
        
        $this->assertEquals('3401', $productId);
    }

    /**
     * Test extract images from HTML
     */
    public function testExtractImagesFromHtml()
    {
        $processor = new GetProductPricesProcessor();

        $html = '<div class="slide m-pic">
            <img src="/tmp/thumbnail/test1.png" alt="Test" href="/u_file/photo/test1.png">
            <img src="/tmp/thumbnail/test2.jpg" alt="Test" href="/u_file/photo/test2.jpg">
        </div>';

        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('extractImagesFromHtml');
        $method->setAccessible(true);

        $images = $method->invoke($processor, $html);
        
        $this->assertIsArray($images);
        $this->assertNotEmpty($images);
        $this->assertStringContainsString('dentalpart.com', $images[0]);
    }

    /**
     * Test threshold settings are applied
     */
    public function testThresholdSettingsApplied()
    {
        $config = [
            'request_delay' => 1000,
            'max_retries' => 5,
            'timeout' => 60,
        ];

        $processor = new GetProductPricesProcessor($config);

        $this->assertEquals(1000, $processor->getConfig('request_delay'));
        $this->assertEquals(5, $processor->getConfig('max_retries'));
        $this->assertEquals(60, $processor->getConfig('timeout'));
    }

    /**
     * Test returns null item when product ID not found
     */
    public function testReturnsNullItemWhenProductIdNotFound()
    {
        $processor = new GetProductPricesProcessor([
            'request_delay' => 0,
        ]);

        $item = new ExtractedDataItem([
            'url' => 'https://www.dentalpart.com/invalid-url',
        ]);

        $result = $processor->process($item);

        $this->assertTrue($result->isNull());
    }
}
