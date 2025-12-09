<?php

namespace CrawlFlow\Tests\Unit\Flow;

use PHPUnit\Framework\TestCase;
use CrawlFlow\Flow\Executors\HtmlDataExtractorNodeExecutor;
use CrawlFlow\Flow\ExecutionContext;
use CrawlFlow\Flow\FlowConfig;
use CrawlFlow\Flow\RakeAdapter;
use Rake\Rake;

/**
 * Test HtmlDataExtractorNodeExecutor
 * 
 * @group flow
 * @group unit
 * @group executors
 */
class HtmlDataExtractorNodeExecutorTest extends TestCase
{
    private HtmlDataExtractorNodeExecutor $executor;
    private ExecutionContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        
        $rake = Rake::getInstance();
        $rakeAdapter = new RakeAdapter($rake);
        $this->executor = new HtmlDataExtractorNodeExecutor($rakeAdapter);
        
        $flowConfig = new FlowConfig([], [], []);
        $this->context = new ExecutionContext($flowConfig);
    }

    public function test_executor_supports_html_data_extractor()
    {
        $this->assertTrue($this->executor->supports('html-data-extractor'));
        $this->assertFalse($this->executor->supports('other-type'));
    }

    public function test_extracts_text_from_html()
    {
        $html = '<html><body><h1>Test Title</h1><p>Test content</p></body></html>';
        
        $this->context->addResource([
            'url' => 'https://test.com',
            'body' => $html,
        ]);

        $node = [
            'id' => 'extractor-1',
            'type' => 'html-data-extractor',
            'data' => [
                'customRules' => [
                    ['name' => 'title', 'selector' => 'h1', 'extract' => 'text'],
                    ['name' => 'content', 'selector' => 'p', 'extract' => 'text'],
                ],
            ],
        ];

        $result = $this->executor->execute($node, $this->context);

        $this->assertTrue($result->isSuccess());
        $output = $result->getOutput();
        $this->assertNotEmpty($output);
        $this->assertEquals('Test Title', $output[0]['title']);
        $this->assertEquals('Test content', $output[0]['content']);
        $this->assertEquals('https://test.com', $output[0]['source_url']);
    }

    public function test_extracts_html_content()
    {
        $html = '<html><body><div class="content"><strong>Bold</strong> text</div></body></html>';
        
        $this->context->addResource([
            'url' => 'https://test.com',
            'body' => $html,
        ]);

        $node = [
            'id' => 'extractor-1',
            'type' => 'html-data-extractor',
            'data' => [
                'customRules' => [
                    ['name' => 'content', 'selector' => '.content', 'extract' => 'html'],
                ],
            ],
        ];

        $result = $this->executor->execute($node, $this->context);

        $this->assertTrue($result->isSuccess());
        $output = $result->getOutput();
        $this->assertStringContainsString('<strong>Bold</strong>', $output[0]['content']);
    }

    public function test_extracts_attribute_with_parameter()
    {
        $html = '<html><body><a href="/products/123.html" class="product-link">Product</a></body></html>';
        
        $this->context->addResource([
            'url' => 'https://test.com',
            'body' => $html,
        ]);

        $node = [
            'id' => 'extractor-1',
            'type' => 'html-data-extractor',
            'data' => [
                'customRules' => [
                    ['name' => 'url', 'selector' => 'a', 'extract' => 'attribute', 'attribute' => 'href'],
                    ['name' => 'class', 'selector' => 'a', 'extract' => 'attribute', 'attribute' => 'class'],
                ],
            ],
        ];

        $result = $this->executor->execute($node, $this->context);

        $this->assertTrue($result->isSuccess());
        $output = $result->getOutput();
        $this->assertEquals('/products/123.html', $output[0]['url']);
        $this->assertEquals('product-link', $output[0]['class']);
    }

    public function test_extracts_multiple_text_values()
    {
        $html = '<html><body>
            <div class="item">Item 1</div>
            <div class="item">Item 2</div>
            <div class="item">Item 3</div>
        </body></html>';
        
        $this->context->addResource([
            'url' => 'https://test.com',
            'body' => $html,
        ]);

        $node = [
            'id' => 'extractor-1',
            'type' => 'html-data-extractor',
            'data' => [
                'customRules' => [
                    ['name' => 'items', 'selector' => '.item', 'extract' => 'text', 'extractMultiple' => true],
                ],
            ],
        ];

        $result = $this->executor->execute($node, $this->context);

        $this->assertTrue($result->isSuccess());
        $output = $result->getOutput();
        $this->assertIsArray($output[0]['items']);
        $this->assertCount(3, $output[0]['items']);
        $this->assertEquals('Item 1', $output[0]['items'][0]);
        $this->assertEquals('Item 2', $output[0]['items'][1]);
        $this->assertEquals('Item 3', $output[0]['items'][2]);
    }

    public function test_extracts_multiple_attributes()
    {
        $html = '<html><body>
            <a href="/product/1.html" class="link">Link 1</a>
            <a href="/product/2.html" class="link">Link 2</a>
            <a href="/product/3.html" class="link">Link 3</a>
        </body></html>';
        
        $this->context->addResource([
            'url' => 'https://test.com',
            'body' => $html,
        ]);

        $node = [
            'id' => 'extractor-1',
            'type' => 'html-data-extractor',
            'data' => [
                'customRules' => [
                    ['name' => 'urls', 'selector' => 'a.link', 'extract' => 'attribute', 'attribute' => 'href', 'extractMultiple' => true],
                ],
            ],
        ];

        $result = $this->executor->execute($node, $this->context);

        $this->assertTrue($result->isSuccess());
        $output = $result->getOutput();
        $this->assertIsArray($output[0]['urls']);
        $this->assertCount(3, $output[0]['urls']);
        $this->assertEquals('/product/1.html', $output[0]['urls'][0]);
        $this->assertEquals('/product/2.html', $output[0]['urls'][1]);
        $this->assertEquals('/product/3.html', $output[0]['urls'][2]);
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
        
        $this->context->addResource([
            'url' => 'https://test.com',
            'body' => $html,
        ]);

        $node = [
            'id' => 'extractor-1',
            'type' => 'html-data-extractor',
            'data' => [
                'customRules' => [
                    ['name' => 'gallery_images', 'selector' => '.slide > img', 'extract' => 'attribute', 'attribute' => 'href', 'extractMultiple' => true],
                ],
            ],
        ];

        $result = $this->executor->execute($node, $this->context);

        $this->assertTrue($result->isSuccess());
        $output = $result->getOutput();
        $this->assertIsArray($output[0]['gallery_images']);
        $this->assertCount(2, $output[0]['gallery_images']);
        $this->assertEquals('/u_file/photo/20251204/img1.png', $output[0]['gallery_images'][0]);
        $this->assertEquals('/u_file/photo/20251204/img2.jpg', $output[0]['gallery_images'][1]);
    }

    public function test_filters_empty_values_in_multiple_extraction()
    {
        $html = '<html><body>
            <div class="item">Item 1</div>
            <div class="item"></div>
            <div class="item">Item 3</div>
            <div class="item">   </div>
        </body></html>';
        
        $this->context->addResource([
            'url' => 'https://test.com',
            'body' => $html,
        ]);

        $node = [
            'id' => 'extractor-1',
            'type' => 'html-data-extractor',
            'data' => [
                'customRules' => [
                    ['name' => 'items', 'selector' => '.item', 'extract' => 'text', 'extractMultiple' => true],
                ],
            ],
        ];

        $result = $this->executor->execute($node, $this->context);

        $this->assertTrue($result->isSuccess());
        $output = $result->getOutput();
        $this->assertIsArray($output[0]['items']);
        // Should filter out empty values
        $this->assertCount(2, $output[0]['items']);
        $this->assertEquals('Item 1', $output[0]['items'][0]);
        $this->assertEquals('Item 3', $output[0]['items'][1]);
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
        
        $this->context->addResource([
            'url' => 'https://test.com',
            'body' => $html,
        ]);

        $node = [
            'id' => 'extractor-1',
            'type' => 'html-data-extractor',
            'data' => [
                'customRules' => [
                    ['name' => 'url', 'selector' => 'a', 'extract' => 'attribute', 'attribute' => 'href'],
                    ['name' => 'image', 'selector' => 'img', 'extract' => 'attribute', 'attribute' => 'src'],
                ],
            ],
        ];

        $result = $this->executor->execute($node, $this->context);

        $this->assertTrue($result->isSuccess());
        $output = $result->getOutput();
        $this->assertEquals('https://www.dentalpart.com/products/123.html', $output[0]['url']);
        $this->assertEquals('https://www.dentalpart.com/images/product.jpg', $output[0]['image']);
    }

    public function test_handles_missing_selectors_gracefully()
    {
        $html = '<html><body><h1>Title</h1></body></html>';
        
        $this->context->addResource([
            'url' => 'https://test.com',
            'body' => $html,
        ]);

        $node = [
            'id' => 'extractor-1',
            'type' => 'html-data-extractor',
            'data' => [
                'customRules' => [
                    ['name' => 'title', 'selector' => 'h1', 'extract' => 'text'],
                    ['name' => 'missing', 'selector' => '.nonexistent', 'extract' => 'text'],
                    ['name' => 'missing_multiple', 'selector' => '.nonexistent', 'extract' => 'text', 'extractMultiple' => true],
                ],
            ],
        ];

        $result = $this->executor->execute($node, $this->context);

        $this->assertTrue($result->isSuccess());
        $output = $result->getOutput();
        $this->assertEquals('Title', $output[0]['title']);
        $this->assertNull($output[0]['missing']);
        $this->assertIsArray($output[0]['missing_multiple']);
        $this->assertEmpty($output[0]['missing_multiple']);
    }

    public function test_returns_error_when_no_rules()
    {
        $this->context->addResource([
            'url' => 'https://test.com',
            'body' => '<html></html>',
        ]);

        $node = [
            'id' => 'extractor-1',
            'type' => 'html-data-extractor',
            'data' => [
                'customRules' => [],
            ],
        ];

        $result = $this->executor->execute($node, $this->context);

        $this->assertFalse($result->isSuccess());
    }

    public function test_returns_error_when_no_resources()
    {
        $node = [
            'id' => 'extractor-1',
            'type' => 'html-data-extractor',
            'data' => [
                'customRules' => [
                    ['name' => 'title', 'selector' => 'h1', 'extract' => 'text'],
                ],
            ],
        ];

        $result = $this->executor->execute($node, $this->context);

        $this->assertFalse($result->isSuccess());
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
        
        $this->context->addResource([
            'url' => 'https://www.dentalpart.com/products_detail/3645.html',
            'body' => $html,
        ]);

        $node = [
            'id' => 'extractor-1',
            'type' => 'html-data-extractor',
            'data' => [
                'customRules' => [
                    ['name' => 'product_name', 'selector' => '.t1', 'extract' => 'text'],
                    ['name' => 'sku', 'selector' => '.t3 > .sp2', 'extract' => 'text'],
                    ['name' => 'description', 'selector' => '.detail', 'extract' => 'html'],
                    ['name' => 'gallery_images', 'selector' => '.slide > img', 'extract' => 'attribute', 'attribute' => 'href', 'extractMultiple' => true],
                ],
            ],
        ];

        $result = $this->executor->execute($node, $this->context);

        $this->assertTrue($result->isSuccess());
        $output = $result->getOutput();
        $this->assertEquals('RT-MG235-56 Middle Gear For COXO C6-22', $output[0]['product_name']);
        $this->assertEquals('RT-MG235-56', $output[0]['sku']);
        $this->assertStringContainsString('Product description', $output[0]['description']);
        $this->assertStringContainsString('<strong>bold</strong>', $output[0]['description']);
        $this->assertIsArray($output[0]['gallery_images']);
        $this->assertCount(2, $output[0]['gallery_images']);
        $this->assertEquals('https://www.dentalpart.com/u_file/photo/20251204/img1.png', $output[0]['gallery_images'][0]);
        $this->assertEquals('https://www.dentalpart.com/u_file/photo/20251204/img2.jpg', $output[0]['gallery_images'][1]);
    }
}

