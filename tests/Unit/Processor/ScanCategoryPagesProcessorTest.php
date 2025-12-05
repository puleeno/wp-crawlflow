<?php

namespace CrawlFlow\Tests\Unit\Processor;

use PHPUnit\Framework\TestCase;
use RamphorRake\Adapter\Processor\ScanCategoryPagesProcessor;
use Rake\Entities\ParsedData\ExtractedDataItem;

/**
 * Unit tests for ScanCategoryPagesProcessor
 */
class ScanCategoryPagesProcessorTest extends TestCase
{
    /**
     * Test extract product URLs from HTML
     */
    public function testExtractProductUrls()
    {
        $processor = new ScanCategoryPagesProcessor();

        $html = '<a href="/products_detail/product1.html">Product 1</a>
                 <a href="/products_detail/product2.html">Product 2</a>
                 <a href="https://www.dentalpart.com/products_detail/product3.html">Product 3</a>';

        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('extractProductUrls');
        $method->setAccessible(true);

        $baseUrl = 'https://www.dentalpart.com/products_list/CateId-123';
        $urls = $method->invoke($processor, $html, $baseUrl);

        $this->assertIsArray($urls);
        $this->assertCount(3, $urls);
        $this->assertStringContainsString('products_detail', $urls[0]);
    }

    /**
     * Test build page URL
     */
    public function testBuildPageUrl()
    {
        $processor = new ScanCategoryPagesProcessor();

        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('buildPageUrl');
        $method->setAccessible(true);

        $baseUrl = 'https://www.dentalpart.com/products_list/CateId-123';
        
        // Page 1 should return base URL
        $page1Url = $method->invoke($processor, $baseUrl, 1);
        $this->assertEquals($baseUrl, $page1Url);

        // Page 2 should have page parameter
        $page2Url = $method->invoke($processor, $baseUrl, 2);
        $this->assertStringContainsString('page=2', $page2Url);
    }

    /**
     * Test threshold settings
     */
    public function testThresholdSettings()
    {
        $config = [
            'max_pages' => 50,
            'page_delay' => 1000000, // 1 second
            'timeout' => 60,
        ];

        $processor = new ScanCategoryPagesProcessor($config);

        $this->assertEquals(50, $processor->getConfig('max_pages'));
        $this->assertEquals(1000000, $processor->getConfig('page_delay'));
        $this->assertEquals(60, $processor->getConfig('timeout'));
    }

    /**
     * Test returns null item when category URL missing
     */
    public function testReturnsNullItemWhenCategoryUrlMissing()
    {
        $processor = new ScanCategoryPagesProcessor();

        $item = new ExtractedDataItem([]);

        $result = $processor->process($item);

        $this->assertTrue($result->isNull());
    }
}
