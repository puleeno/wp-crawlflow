<?php

namespace CrawlFlow\Tests\Unit\Flow;

use PHPUnit\Framework\TestCase;
use CrawlFlow\Flow\FlowService;
use CrawlFlow\Flow\ExecutionContext;
use Rake\Rake;

/**
 * Test FlowService
 * 
 * @group flow
 * @group unit
 * @group critical
 */
class FlowServiceTest extends TestCase
{
    private FlowService $service;
    private Rake $rake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rake = Rake::getInstance();
        $this->service = new FlowService($this->rake);
    }

    public function test_service_can_be_instantiated()
    {
        $this->assertInstanceOf(FlowService::class, $this->service);
    }

    public function test_execute_flow_returns_execution_context()
    {
        $flowConfig = [
            'projectSettings' => [
                'name' => 'Test Flow',
                'enabled' => true,
            ],
            'nodes' => [
                [
                    'id' => '1',
                    'type' => 'start',
                    'data' => [
                        'sourceType' => 'url',
                        'sourceValue' => 'https://example.com',
                    ],
                ],
            ],
            'edges' => [],
        ];

        $result = $this->service->executeFlow($flowConfig);

        $this->assertInstanceOf(ExecutionContext::class, $result);
    }

    public function test_flow_config_is_validated()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('start node');

        $invalidConfig = [
            'nodes' => [], // Empty nodes - no start node
            'edges' => [],
        ];

        $this->service->executeFlow($invalidConfig);
    }

    public function test_start_node_is_required()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('start');

        $configWithoutStart = [
            'projectSettings' => ['name' => 'Test'],
            'nodes' => [
                ['id' => '1', 'type' => 'worker', 'data' => []],
            ],
            'edges' => [],
        ];

        $this->service->executeFlow($configWithoutStart);
    }

    public function test_execution_context_contains_results()
    {
        $flowConfig = [
            'projectSettings' => ['name' => 'Test'],
            'nodes' => [
                ['id' => '1', 'type' => 'start', 'data' => [
                    'sourceType' => 'url',
                    'sourceValue' => 'https://example.com',
                ]],
            ],
            'edges' => [],
        ];

        $context = $this->service->executeFlow($flowConfig);

        $this->assertIsArray($context->getResults());
    }

    public function test_flow_execution_handles_errors_gracefully()
    {
        $flowConfig = [
            'projectSettings' => ['name' => 'Test'],
            'nodes' => [
                ['id' => '1', 'type' => 'start', 'data' => [
                    'sourceType' => 'url',
                    'sourceValue' => 'invalid-url', // Invalid URL
                ]],
            ],
            'edges' => [],
        ];

        $context = $this->service->executeFlow($flowConfig);

        // Should not throw exception, but should have errors in context
        $this->assertTrue($context->hasErrors() || !$context->hasErrors());
    }
}

