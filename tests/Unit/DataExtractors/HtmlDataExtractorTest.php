<?php

namespace CrawlFlow\Tests\Unit\DataExtractors;

use PHPUnit\Framework\TestCase;
use CrawlFlow\DataExtractors\HtmlDataExtractor;

/**
 * Test HtmlDataExtractor
 * 
 * @group unit
 * @group data-extractors
 */
class HtmlDataExtractorTest extends TestCase
{
    private HtmlDataExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new HtmlDataExtractor();
    }

    public function test_extracts_text()
    {
        $html = '<html><body><h1>Test Title</h1><p>Test content</p></body></html>';
        
        $rules = [
            ['name' => 'title', 'selector' => 'h1', 'extract' => 'text'],
            ['name' => 'content', 'selector' => 'p', 'extract' => 'text'],
        ];

        $result = $this->extractor->extract($html, $rules);

        $this->assertEquals('Test Title', $result['title']);
        $this->assertEquals('Test content', $result['content']);
    }

    public function test_extracts_html_content()
    {
        $html = '<html><body><div class="content"><strong>Bold</strong> text</div></body></html>';
        
        $rules = [
            ['name' => 'content', 'selector' => '.content', 'extract' => 'html'],
        ];

        $result = $this->extractor->extract($html, $rules);

        $this->assertStringContainsString('<strong>Bold</strong>', $result['content']);
    }

    public function test_extracts_attribute_with_parameter()
    {
        $html = '<html><body><a href="/products/123.html" class="product-link">Product</a></body></html>';
        
        $rules = [
            ['name' => 'url', 'selector' => 'a', 'extract' => 'attribute', 'attribute' => 'href'],
            ['name' => 'class', 'selector' => 'a', 'extract' => 'attribute', 'attribute' => 'class'],
        ];

        $result = $this->extractor->extract($html, $rules);

        $this->assertEquals('/products/123.html', $result['url']);
        $this->assertEquals('product-link', $result['class']);
    }

    public function test_extracts_multiple_text_values()
    {
        $html = '<html><body>
            <div class="item">Item 1</div>
            <div class="item">Item 2</div>
            <div class="item">Item 3</div>
        </body></html>';
        
        $rules = [
            ['name' => 'items', 'selector' => '.item', 'extract' => 'text', 'extractMultiple' => true],
        ];

        $result = $this->extractor->extract($html, $rules);

        $this->assertIsArray($result['items']);
        $this->assertCount(3, $result['items']);
        $this->assertEquals('Item 1', $result['items'][0]);
        $this->assertEquals('Item 2', $result['items'][1]);
        $this->assertEquals('Item 3', $result['items'][2]);
    }

    public function test_extracts_multiple_html_values()
    {
        $html = '<html><body>
            <div class="content"><p>Content 1</p></div>
            <div class="content"><p>Content 2</p></div>
        </body></html>';
        
        $rules = [
            ['name' => 'contents', 'selector' => '.content', 'extract' => 'html', 'extractMultiple' => true],
        ];

        $result = $this->extractor->extract($html, $rules);

        $this->assertIsArray($result['contents']);
        $this->assertCount(2, $result['contents']);
        $this->assertStringContainsString('Content 1', $result['contents'][0]);
        $this->assertStringContainsString('Content 2', $result['contents'][1]);
    }

    public function test_extracts_multiple_attributes()
    {
        $html = '<html><body>
            <a href="/product/1.html" class="link">Link 1</a>
            <a href="/product/2.html" class="link">Link 2</a>
            <a href="/product/3.html" class="link">Link 3</a>
        </body></html>';
        
        $rules = [
            ['name' => 'urls', 'selector' => 'a.link', 'extract' => 'attribute', 'attribute' => 'href', 'extractMultiple' => true],
        ];

        $result = $this->extractor->extract($html, $rules);

        $this->assertIsArray($result['urls']);
        $this->assertCount(3, $result['urls']);
        $this->assertEquals('/product/1.html', $result['urls'][0]);
        $this->assertEquals('/product/2.html', $result['urls'][1]);
        $this->assertEquals('/product/3.html', $result['urls'][2]);
    }

    public function test_extracts_gallery_images_with_href_attribute()
    {
        // Simulating the dentalpart.com structure
        $html = '<html><body>
            <div class="slide m-pic">
                <img src="/tmp/thumbnail/img1.600x620.0.png" alt="Product 1" href="/u_file/photo/20251204/img1.png" />
            </div>
            <div class="slide m-pic">
                <img src="/tmp/thumbnail/img2.600x620.0.png" alt="Product 2" href="/u_file/photo/20251204/img2.jpg" />
            </div>
        </body></html>';
        
        $rules = [
            ['name' => 'gallery_images', 'selector' => '.slide > img', 'extract' => 'attribute', 'attribute' => 'href', 'extractMultiple' => true],
        ];

        $result = $this->extractor->extract($html, $rules);

        $this->assertIsArray($result['gallery_images']);
        $this->assertCount(2, $result['gallery_images']);
        $this->assertEquals('/u_file/photo/20251204/img1.png', $result['gallery_images'][0]);
        $this->assertEquals('/u_file/photo/20251204/img2.jpg', $result['gallery_images'][1]);
    }

    public function test_filters_out_null_empty_values_when_extract_multiple()
    {
        $html = '<html><body>
            <div class="item">Item 1</div>
            <div class="item"></div>
            <div class="item">Item 3</div>
            <div class="item">   </div>
        </body></html>';
        
        $rules = [
            ['name' => 'items', 'selector' => '.item', 'extract' => 'text', 'extractMultiple' => true],
        ];

        $result = $this->extractor->extract($html, $rules);

        $this->assertIsArray($result['items']);
        // Should only contain non-empty values
        $this->assertCount(2, $result['items']);
        $this->assertEquals('Item 1', $result['items'][0]);
        $this->assertEquals('Item 3', $result['items'][1]);
    }

    public function test_converts_relative_urls_to_absolute_with_base_tag()
    {
        $html = '<html>
            <head><base href="https://www.dentalpart.com/" /></head>
            <body>
                <a href="/products/123.html">Product</a>
                <img src="/images/product.jpg" alt="Product" />
            </body>
        </html>';
        
        $rules = [
            ['name' => 'url', 'selector' => 'a', 'extract' => 'attribute', 'attribute' => 'href'],
            ['name' => 'image', 'selector' => 'img', 'extract' => 'attribute', 'attribute' => 'src'],
        ];

        $result = $this->extractor->extract($html, $rules);

        $this->assertEquals('https://www.dentalpart.com/products/123.html', $result['url']);
        $this->assertEquals('https://www.dentalpart.com/images/product.jpg', $result['image']);
    }

    public function test_converts_relative_urls_in_multiple_extraction()
    {
        $html = '<html>
            <head><base href="https://www.dentalpart.com/" /></head>
            <body>
                <a href="/product/1.html">Link 1</a>
                <a href="/product/2.html">Link 2</a>
            </body>
        </html>';
        
        $rules = [
            ['name' => 'urls', 'selector' => 'a', 'extract' => 'attribute', 'attribute' => 'href', 'extractMultiple' => true],
        ];

        $result = $this->extractor->extract($html, $rules);

        $this->assertIsArray($result['urls']);
        $this->assertCount(2, $result['urls']);
        $this->assertEquals('https://www.dentalpart.com/product/1.html', $result['urls'][0]);
        $this->assertEquals('https://www.dentalpart.com/product/2.html', $result['urls'][1]);
    }

    public function test_does_not_convert_absolute_urls()
    {
        $html = '<html>
            <head><base href="https://www.dentalpart.com/" /></head>
            <body>
                <a href="https://external.com/page.html">External</a>
                <img src="https://cdn.example.com/image.jpg" alt="Image" />
            </body>
        </html>';
        
        $rules = [
            ['name' => 'url', 'selector' => 'a', 'extract' => 'attribute', 'attribute' => 'href'],
            ['name' => 'image', 'selector' => 'img', 'extract' => 'attribute', 'attribute' => 'src'],
        ];

        $result = $this->extractor->extract($html, $rules);

        $this->assertEquals('https://external.com/page.html', $result['url']);
        $this->assertEquals('https://cdn.example.com/image.jpg', $result['image']);
    }

    public function test_extracts_breadcrumbs()
    {
        $html = '<html>
            <head><base href="https://www.dentalpart.com/" /></head>
            <body>
                <nav>
                    <a href="/">Home</a>
                    <a href="/categories/">Categories</a>
                    <a href="/categories/product.html">Product</a>
                </nav>
            </body>
        </html>';
        
        $rules = [
            ['name' => 'breadcrumbs', 'selector' => 'nav a', 'extract' => 'text', 'extractMultiple' => true],
        ];

        $result = $this->extractor->extract($html, $rules);

        // Note: breadcrumbs extraction has special handling, but for now we test basic multiple extraction
        $this->assertIsArray($result['breadcrumbs']);
        $this->assertCount(3, $result['breadcrumbs']);
    }

    public function test_handles_missing_selectors_gracefully()
    {
        $html = '<html><body><h1>Title</h1></body></html>';
        
        $rules = [
            ['name' => 'title', 'selector' => 'h1', 'extract' => 'text'],
            ['name' => 'missing', 'selector' => '.nonexistent', 'extract' => 'text'],
            ['name' => 'missing_multiple', 'selector' => '.nonexistent', 'extract' => 'text', 'extractMultiple' => true],
        ];

        $result = $this->extractor->extract($html, $rules);

        $this->assertEquals('Title', $result['title']);
        $this->assertNull($result['missing']);
        $this->assertIsArray($result['missing_multiple']);
        $this->assertEmpty($result['missing_multiple']);
    }

    public function test_extracts_all_attributes_when_no_specific_attribute()
    {
        $html = '<html><body><a href="/test.html" class="link" data-id="123">Link</a></body></html>';
        
        $rules = [
            ['name' => 'attrs', 'selector' => 'a', 'extract' => 'attribute'],
        ];

        $result = $this->extractor->extract($html, $rules);

        $this->assertIsArray($result['attrs']);
        $this->assertArrayHasKey('href', $result['attrs']);
        $this->assertArrayHasKey('class', $result['attrs']);
        $this->assertEquals('/test.html', $result['attrs']['href']);
        $this->assertEquals('link', $result['attrs']['class']);
    }

    public function test_real_world_dentalpart_product_page()
    {
        // Simulating a real dentalpart.com product page structure
        $html = '<html>
            <head><base href="https://www.dentalpart.com/" /></head>
            <body>
                <div class="t1">RT-MG235-56 Middle Gear For COXO C6-22</div>
                <div class="t3">
                    <span class="sp2">RT-MG235-56</span>
                </div>
                <div class="detail">
                    <p>Product description with <strong>bold</strong> text.</p>
                </div>
                <div class="slide m-pic">
                    <img src="/tmp/thumbnail/img1.png" href="/u_file/photo/20251204/img1.png" />
                </div>
                <div class="slide m-pic">
                    <img src="/tmp/thumbnail/img2.jpg" href="/u_file/photo/20251204/img2.jpg" />
                </div>
            </body>
        </html>';
        
        $rules = [
            ['name' => 'product_name', 'selector' => '.t1', 'extract' => 'text'],
            ['name' => 'sku', 'selector' => '.t3 > .sp2', 'extract' => 'text'],
            ['name' => 'description', 'selector' => '.detail', 'extract' => 'html'],
            ['name' => 'gallery_images', 'selector' => '.slide > img', 'extract' => 'attribute', 'attribute' => 'href', 'extractMultiple' => true],
        ];

        $result = $this->extractor->extract($html, $rules);

        $this->assertEquals('RT-MG235-56 Middle Gear For COXO C6-22', $result['product_name']);
        $this->assertEquals('RT-MG235-56', $result['sku']);
        $this->assertStringContainsString('Product description', $result['description']);
        $this->assertStringContainsString('<strong>bold</strong>', $result['description']);
        $this->assertIsArray($result['gallery_images']);
        $this->assertCount(2, $result['gallery_images']);
        $this->assertEquals('https://www.dentalpart.com/u_file/photo/20251204/img1.png', $result['gallery_images'][0]);
        $this->assertEquals('https://www.dentalpart.com/u_file/photo/20251204/img2.jpg', $result['gallery_images'][1]);
    }
}

