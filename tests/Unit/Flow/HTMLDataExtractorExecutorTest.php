<?php

namespace CrawlFlow\Tests\Unit\Flow;

use PHPUnit\Framework\TestCase;
use CrawlFlow\Flow\Executors\HTMLDataExtractorExecutor;
use CrawlFlow\Flow\ExecutionContext;
use CrawlFlow\Flow\FlowConfig;
use CrawlFlow\Flow\RakeAdapter;
use Rake\Rake;

/**
 * Test HTMLDataExtractorExecutor
 * 
 * @group flow
 * @group unit
 * @group executors
 */
class HTMLDataExtractorExecutorTest extends TestCase
{
    private HTMLDataExtractorExecutor $executor;
    private ExecutionContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        
        $rake = Rake::getInstance();
        $rakeAdapter = new RakeAdapter($rake);
        $this->executor = new HTMLDataExtractorExecutor($rakeAdapter);
        
        $flowConfig = new FlowConfig([], [], []);
        $this->context = new ExecutionContext($flowConfig);
    }

    public function test_executor_type_is_correct()
    {
        $this->assertEquals('html-data-extractor', $this->executor->getType());
    }

    public function test_extracts_text_from_html()
    {
        $html = '<html><body><h1>Test Title</h1><p>Test content</p></body></html>';
        
        $this->context->addResource([
            'url' => 'https://test.com',
            'content' => $html,
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
    }

    public function test_extracts_html_content()
    {
        $html = '<html><body><div class="content"><strong>Bold</strong> text</div></body></html>';
        
        $this->context->addResource([
            'url' => 'https://test.com',
            'content' => $html,
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

    public function test_handles_missing_selectors_gracefully()
    {
        $html = '<html><body><h1>Title</h1></body></html>';
        
        $this->context->addResource([
            'url' => 'https://test.com',
            'content' => $html,
        ]);

        $node = [
            'id' => 'extractor-1',
            'type' => 'html-data-extractor',
            'data' => [
                'customRules' => [
                    ['name' => 'title', 'selector' => 'h1', 'extract' => 'text'],
                    ['name' => 'missing', 'selector' => '.nonexistent', 'extract' => 'text'],
                ],
            ],
        ];

        $result = $this->executor->execute($node, $this->context);

        $this->assertTrue($result->isSuccess());
        $output = $result->getOutput();
        $this->assertEquals('Title', $output[0]['title']);
        $this->assertNull($output[0]['missing']);
    }

    public function test_returns_error_when_no_rules()
    {
        $this->context->addResource([
            'url' => 'https://test.com',
            'content' => '<html></html>',
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
}

