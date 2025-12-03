<?php

namespace CrawlFlow\Tests\Unit\Worker;

use PHPUnit\Framework\TestCase;
use CrawlFlow\Worker\Worker;
use CrawlFlow\Contracts\WorkerInterface;

/**
 * Test Worker
 * 
 * @group worker
 * @group unit
 * @group critical
 */
class WorkerTest extends TestCase
{
    public function test_worker_implements_interface()
    {
        $worker = new Worker([
            'name' => 'test_worker',
            'priority' => 5,
        ]);

        $this->assertInstanceOf(WorkerInterface::class, $worker);
    }

    public function test_worker_has_name_and_priority()
    {
        $worker = new Worker([
            'name' => 'blog_worker',
            'priority' => 10,
        ]);

        $this->assertEquals('blog_worker', $worker->getName());
        $this->assertEquals(10, $worker->getPriority());
    }

    public function test_worker_detection_rule_dom_exists()
    {
        $worker = new Worker([
            'name' => 'article_worker',
            'priority' => 1,
            'detectionRules' => [
                [
                    'type' => 'dom-value',
                    'selector' => 'article',
                    'condition' => 'exists',
                ],
            ],
        ]);

        // Item with article tag
        $rawItem = [
            'id' => 1,
            'raw_data' => '<html><article><h1>Test</h1></article></html>',
        ];

        $this->assertTrue($worker->canHandle($rawItem));

        // Item without article tag
        $rawItem2 = [
            'id' => 2,
            'raw_data' => '<html><div>No article</div></html>',
        ];

        $this->assertFalse($worker->canHandle($rawItem2));
    }

    public function test_worker_detection_rule_content_contains()
    {
        $worker = new Worker([
            'name' => 'product_worker',
            'priority' => 1,
            'detectionRules' => [
                [
                    'type' => 'content-contains',
                    'value' => 'price',
                ],
            ],
        ]);

        $rawItem = [
            'id' => 1,
            'raw_data' => json_encode(['name' => 'Product', 'price' => 100]),
        ];

        $this->assertTrue($worker->canHandle($rawItem));
    }

    public function test_worker_detection_logic_and()
    {
        $worker = new Worker([
            'name' => 'strict_worker',
            'priority' => 1,
            'detectionRules' => [
                ['type' => 'content-contains', 'value' => 'article'],
                ['type' => 'content-contains', 'value' => 'title'],
            ],
            'detectionLogic' => 'and',
        ]);

        // Has both
        $this->assertTrue($worker->canHandle([
            'raw_data' => '<article><title>Test</title></article>',
        ]));

        // Has only one (has 'article' but not 'title')
        $this->assertFalse($worker->canHandle([
            'raw_data' => '<article>No heading tag</article>',
        ]));
    }

    public function test_worker_detection_logic_or()
    {
        $worker = new Worker([
            'name' => 'flexible_worker',
            'priority' => 1,
            'detectionRules' => [
                ['type' => 'content-contains', 'value' => 'article'],
                ['type' => 'content-contains', 'value' => 'post'],
            ],
            'detectionLogic' => 'or',
        ]);

        // Has first
        $this->assertTrue($worker->canHandle([
            'raw_data' => '<article>Test</article>',
        ]));

        // Has second
        $this->assertTrue($worker->canHandle([
            'raw_data' => '<div class="post">Test</div>',
        ]));

        // Has neither
        $this->assertFalse($worker->canHandle([
            'raw_data' => '<div>Nothing</div>',
        ]));
    }

    public function test_worker_process_extracts_and_saves()
    {
        $worker = new Worker([
            'name' => 'test_worker',
            'priority' => 1,
            'parser' => [
                'rules' => [
                    ['name' => 'title', 'selector' => 'h1', 'extract' => 'text'],
                ],
            ],
            'processors' => [
                [
                    'type' => 'save_to_wordpress',
                    'settings' => [
                        'postType' => 'post',
                        'postStatus' => 'draft',
                    ],
                ],
            ],
        ]);

        $rawItem = [
            'id' => 1,
            'raw_data' => '<html><h1>Test Article</h1><p>Content here</p></html>',
        ];

        $result = $worker->process($rawItem);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('post_id', $result);
        $this->assertArrayHasKey('processed', $result);
        $this->assertTrue($result['processed']);
    }
}

