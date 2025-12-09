<?php

namespace CrawlFlow\Tests\Integration\Actions;

use PHPUnit\Framework\TestCase;
use Rake\Actions\ContextActionManager;
use Rake\Actions\AbstractContextAction;
use Rake\Actions\ActionContext;
use Rake\Actions\ActionResult;

/**
 * Integration Test for ContextActionManager with real actions
 * 
 * @group integration
 * @group actions
 */
class ContextActionManagerIntegrationTest extends TestCase
{
    private function createLoggingAction(string $id, int $priority = 10): object
    {
        $action = new class($id, $priority) extends AbstractContextAction {
            public array $executionLog = [];
            private string $actionId;

            public function __construct(string $id, int $priority)
            {
                $this->actionId = $id;
                parent::__construct($id, "Test Action {$id}", "Test action for integration", ['phase1'], $priority);
            }

            public function execute(ActionContext $context): ActionResult
            {
                $this->executionLog[] = [
                    'project_id' => $context->getProjectId(),
                    'phase' => $context->getPhase(),
                    'timestamp' => microtime(true),
                ];

                return ActionResult::success([
                    'action_id' => $this->actionId,
                    'executed_at' => time(),
                ]);
            }
        };
        return $action;
    }

    private function createDataProcessingAction(): AbstractContextAction
    {
        return new class extends AbstractContextAction {
            public function __construct()
            {
                parent::__construct('data_processor', 'Data Processor', 'Processes data from context', ['phase1'], 5);
            }

            public function execute(ActionContext $context): ActionResult
            {
                $results = $context->getResults();
                $itemsSaved = $results['items_saved'] ?? 0;

                return ActionResult::success([
                    'processed_items' => $itemsSaved,
                    'multiplied' => $itemsSaved * 2,
                ]);
            }
        };
    }

    private function createValidationAction(): AbstractContextAction
    {
        return new class extends AbstractContextAction {
            public function __construct()
            {
                parent::__construct('validator', 'Validator', 'Validates context data', ['phase1', 'phase2'], 1);
            }

            public function execute(ActionContext $context): ActionResult
            {
                $projectId = $context->getProjectId();
                $results = $context->getResults();

                if ($projectId <= 0) {
                    return ActionResult::error('Invalid project ID', ['project_id' => $projectId]);
                }

                if (!isset($results['items_saved'])) {
                    return ActionResult::error('Missing items_saved in results', ['results' => $results]);
                }

                return ActionResult::success(['validated' => true]);
            }
        };
    }

    protected function setUp(): void
    {
        parent::setUp();
        ContextActionManager::clearAll();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        ContextActionManager::clearAll();
    }

    public function test_executes_multiple_actions_in_priority_order()
    {
        $action1 = $this->createLoggingAction('action1', 30);
        $action2 = $this->createLoggingAction('action2', 10);
        $action3 = $this->createLoggingAction('action3', 20);

        ContextActionManager::register($action1);
        ContextActionManager::register($action2);
        ContextActionManager::register($action3);

        $context = new ActionContext('phase1', 123, ['items_saved' => 5]);
        $results = ContextActionManager::execute('phase1', $context);

        // Verify all actions executed
        $this->assertCount(3, $results);
        $this->assertTrue($results['action2']['success']); // Priority 10
        $this->assertTrue($results['action3']['success']); // Priority 20
        $this->assertTrue($results['action1']['success']); // Priority 30

        // Verify execution order (lower priority = executed first)
        $this->assertCount(1, $action2->executionLog);
        $this->assertCount(1, $action3->executionLog);
        $this->assertCount(1, $action1->executionLog);
    }

    public function test_actions_receive_correct_context_data()
    {
        $action = $this->createDataProcessingAction();
        ContextActionManager::register($action);

        $context = new ActionContext('phase1', 456, [
            'items_saved' => 10,
            'references_saved' => 5,
        ]);

        $results = ContextActionManager::execute('phase1', $context);

        $this->assertTrue($results['data_processor']['success']);
        $this->assertEquals(10, $results['data_processor']['data']['processed_items']);
        $this->assertEquals(20, $results['data_processor']['data']['multiplied']);
    }

    public function test_validation_action_fails_with_invalid_context()
    {
        $action = $this->createValidationAction();
        ContextActionManager::register($action);

        $context = new ActionContext('phase1', 0, []); // Invalid project ID

        $results = ContextActionManager::execute('phase1', $context);

        $this->assertFalse($results['validator']['success']);
        $this->assertStringContainsString('Invalid project ID', $results['validator']['error']);
    }

    public function test_validation_action_succeeds_with_valid_context()
    {
        $action = $this->createValidationAction();
        ContextActionManager::register($action);

        $context = new ActionContext('phase1', 123, [
            'items_saved' => 5,
            'references_saved' => 2,
        ]);

        $results = ContextActionManager::execute('phase1', $context);

        $this->assertTrue($results['validator']['success']);
        $this->assertTrue($results['validator']['data']['validated']);
    }

    public function test_actions_can_access_flow_config_from_context()
    {
        $action = new class('flow_config_reader', 10) extends AbstractContextAction {
            public function __construct(string $id, int $priority)
            {
                parent::__construct($id, 'Flow Config Reader', 'Reads flow config', ['phase1'], $priority);
            }

            public function execute(ActionContext $context): ActionResult
            {
                $flowConfig = $context->getFlowConfig();
                $nodes = $flowConfig['nodes'] ?? [];

                return ActionResult::success([
                    'nodes_count' => count($nodes),
                    'has_nodes' => !empty($nodes),
                ]);
            }
        };

        ContextActionManager::register($action);

        $flowConfig = [
            'nodes' => [
                ['id' => 'node1', 'type' => 'dataSource'],
                ['id' => 'node2', 'type' => 'worker'],
            ],
            'edges' => [],
        ];

        $context = new ActionContext('phase1', 123, [], $flowConfig);
        $results = ContextActionManager::execute('phase1', $context);

        $this->assertTrue($results['flow_config_reader']['success']);
        $this->assertEquals(2, $results['flow_config_reader']['data']['nodes_count']);
        $this->assertTrue($results['flow_config_reader']['data']['has_nodes']);
    }

    public function test_actions_can_set_additional_data_in_context()
    {
        $action = new class('data_setter', 10) extends AbstractContextAction {
            public function __construct()
            {
                parent::__construct('data_setter', 'Data Setter', 'Sets data in context', ['phase1'], 10);
            }

            public function execute(ActionContext $context): ActionResult
            {
                $context->setData('custom_value', 'test_value');
                $context->setData('processed', true);

                return ActionResult::success([
                    'data_set' => true,
                ]);
            }
        };

        ContextActionManager::register($action);

        $context = new ActionContext('phase1', 123);
        ContextActionManager::execute('phase1', $context);

        // Verify data was set
        $this->assertEquals('test_value', $context->getData('custom_value'));
        $this->assertTrue($context->getData('processed'));
    }

    public function test_action_execution_includes_metadata()
    {
        $action = $this->createLoggingAction('metadata_test', 10);
        ContextActionManager::register($action);

        $context = new ActionContext('phase1', 123);
        $results = ContextActionManager::execute('phase1', $context);

        $this->assertArrayHasKey('metadata', $results['metadata_test']);
        $this->assertArrayHasKey('execution_time_ms', $results['metadata_test']['metadata']);
        $this->assertArrayHasKey('action_label', $results['metadata_test']['metadata']);
        $this->assertGreaterThan(0, $results['metadata_test']['metadata']['execution_time_ms']);
    }

    public function test_actions_only_execute_for_supported_phases()
    {
        $action = $this->createValidationAction(); // Supports phase1 and phase2
        ContextActionManager::register($action);

        // Execute for phase1
        $context1 = new ActionContext('phase1', 123, ['items_saved' => 5]);
        $results1 = ContextActionManager::execute('phase1', $context1);

        $this->assertTrue($results1['validator']['success']);

        // Execute for phase2
        $context2 = new ActionContext('phase2', 123, ['items_saved' => 5]);
        $results2 = ContextActionManager::execute('phase2', $context2);

        $this->assertTrue($results2['validator']['success']);

        // Execute for phase3 (not supported)
        $context3 = new ActionContext('phase3', 123, ['items_saved' => 5]);
        $results3 = ContextActionManager::execute('phase3', $context3);

        $this->assertEmpty($results3);
    }

    public function test_action_exception_is_handled_gracefully()
    {
        $action = new class('exception_action', 10) extends AbstractContextAction {
            public function __construct()
            {
                parent::__construct('exception_action', 'Exception Action', 'Throws exception', ['phase1'], 10);
            }

            public function execute(ActionContext $context): ActionResult
            {
                throw new \RuntimeException('Intentional exception for testing');
            }
        };

        ContextActionManager::register($action);

        $context = new ActionContext('phase1', 123);
        $results = ContextActionManager::execute('phase1', $context);

        $this->assertCount(1, $results);
        $this->assertFalse($results['exception_action']['success']);
        $this->assertStringContainsString('Intentional exception', $results['exception_action']['error']);
        $this->assertArrayHasKey('exception', $results['exception_action']['metadata']);
    }
}

