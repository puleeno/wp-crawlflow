<?php

namespace CrawlFlow\Tests\Integration\Actions;

use PHPUnit\Framework\TestCase;
use CrawlFlow\Cron\Phase1CrawlService;
use CrawlFlow\Cron\ProjectCacheService;
use CrawlFlow\Admin\ProjectService;
use Rake\Actions\ContextActionManager;
use Rake\Actions\AbstractContextAction;
use Rake\Actions\ActionContext;
use Rake\Actions\ActionResult;

/**
 * Integration Test for Phase 1 with Context Actions
 * 
 * @group integration
 * @group actions
 * @group phase1
 */
class Phase1ActionIntegrationTest extends TestCase
{
    public static array $executionHistory = [];

    private function createPhase1TrackingAction(): AbstractContextAction
    {
        return new class extends AbstractContextAction {
            public function __construct()
            {
                parent::__construct(
                    'phase1_tracker',
                    'Phase 1 Tracker',
                    'Tracks Phase 1 execution',
                    ['phase1'],
                    10
                );
            }

            public function execute(ActionContext $context): ActionResult
            {
                Phase1ActionIntegrationTest::$executionHistory[] = [
                    'project_id' => $context->getProjectId(),
                    'phase' => $context->getPhase(),
                    'results' => $context->getResults(),
                    'timestamp' => time(),
                ];

                return ActionResult::success([
                    'tracked' => true,
                    'items_saved' => $context->getResults()['items_saved'] ?? 0,
                ]);
            }
        };
    }

    private function createPhase1ResultProcessor(): AbstractContextAction
    {
        return new class extends AbstractContextAction {
            public function __construct()
            {
                parent::__construct(
                    'phase1_processor',
                    'Phase 1 Result Processor',
                    'Processes Phase 1 results',
                    ['phase1'],
                    20
                );
            }

            public function execute(ActionContext $context): ActionResult
            {
                $results = $context->getResults();
                $itemsSaved = $results['items_saved'] ?? 0;
                $referencesSaved = $results['references_saved'] ?? 0;

                return ActionResult::success([
                    'total_items' => $itemsSaved + $referencesSaved,
                    'processed' => true,
                ]);
            }
        };
    }

    protected function setUp(): void
    {
        parent::setUp();
        ContextActionManager::clearAll();
        self::$executionHistory = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        ContextActionManager::clearAll();
        self::$executionHistory = [];
    }

    public function test_phase1_service_executes_registered_actions()
    {
        // Register test actions
        $trackingAction = $this->createPhase1TrackingAction();
        $processorAction = $this->createPhase1ResultProcessor();

        ContextActionManager::register($trackingAction);
        ContextActionManager::register($processorAction);

        // Mock project service to avoid database dependencies
        $projectService = $this->createMock(ProjectService::class);
        $projectService->method('getProject')
            ->willReturn([
                'id' => 999,
                'name' => 'Test Project',
                'status' => 'active',
            ]);

        // Note: This is a simplified test that focuses on action execution
        // Full Phase 1 integration would require database setup
        $this->assertTrue(
            ContextActionManager::hasAction('phase1', 'phase1_tracker'),
            'Tracking action should be registered'
        );
        $this->assertTrue(
            ContextActionManager::hasAction('phase1', 'phase1_processor'),
            'Processor action should be registered'
        );
    }

    public function test_actions_receive_phase1_results_in_context()
    {
        $action = $this->createPhase1TrackingAction();
        ContextActionManager::register($action);

        // Simulate Phase 1 results
        $phase1Results = [
            'project_id' => 123,
            'phase' => 'crawl',
            'sources_processed' => 2,
            'items_saved' => 15,
            'references_saved' => 8,
            'errors' => [],
        ];

        $flowConfig = [
            'nodes' => [],
            'edges' => [],
        ];

        $context = new ActionContext(
            'phase1',
            $phase1Results['project_id'],
            $phase1Results,
            $flowConfig
        );

        $results = ContextActionManager::execute('phase1', $context);

        $this->assertTrue($results['phase1_tracker']['success']);
        $this->assertEquals(15, $results['phase1_tracker']['data']['items_saved']);

        // Verify execution history
        $this->assertCount(1, self::$executionHistory);
        $this->assertEquals(123, self::$executionHistory[0]['project_id']);
        $this->assertEquals(15, self::$executionHistory[0]['results']['items_saved']);
    }

    public function test_actions_can_access_flow_config_from_phase1_context()
    {
        $action = new class('flow_config_accessor', 10) extends AbstractContextAction {
            public function __construct()
            {
                parent::__construct('flow_config_accessor', 'Flow Config Accessor', 'Accesses flow config', ['phase1'], 10);
            }

            public function execute(ActionContext $context): ActionResult
            {
                $flowConfig = $context->getFlowConfig();
                $hasNodes = !empty($flowConfig['nodes'] ?? []);

                return ActionResult::success([
                    'has_flow_config' => !empty($flowConfig),
                    'has_nodes' => $hasNodes,
                ]);
            }
        };

        ContextActionManager::register($action);

        $flowConfig = [
            'nodes' => [
                ['id' => 'source1', 'type' => 'dataSource'],
            ],
            'edges' => [],
        ];

        $context = new ActionContext('phase1', 123, ['items_saved' => 5], $flowConfig);
        $results = ContextActionManager::execute('phase1', $context);

        $this->assertTrue($results['flow_config_accessor']['success']);
        $this->assertTrue($results['flow_config_accessor']['data']['has_flow_config']);
        $this->assertTrue($results['flow_config_accessor']['data']['has_nodes']);
    }

    public function test_multiple_actions_execute_in_priority_order()
    {
        $action1 = $this->createPhase1TrackingAction(); // Priority 10
        $action2 = $this->createPhase1ResultProcessor(); // Priority 20

        ContextActionManager::register($action1);
        ContextActionManager::register($action2);

        $context = new ActionContext('phase1', 123, [
            'items_saved' => 10,
            'references_saved' => 5,
        ]);

        $results = ContextActionManager::execute('phase1', $context);

        // Verify both executed
        $this->assertCount(2, $results);
        $this->assertTrue($results['phase1_tracker']['success']);
        $this->assertTrue($results['phase1_processor']['success']);

        // Verify order (tracker should execute first due to lower priority)
        $resultKeys = array_keys($results);
        $this->assertEquals('phase1_tracker', $resultKeys[0]);
        $this->assertEquals('phase1_processor', $resultKeys[1]);
    }

    public function test_action_failure_does_not_stop_other_actions()
    {
        $failingAction = new class extends AbstractContextAction {
            public function __construct()
            {
                parent::__construct('failing_action', 'Failing Action', 'Always fails', ['phase1'], 5);
            }

            public function execute(ActionContext $context): ActionResult
            {
                return ActionResult::error('Intentional failure');
            }
        };

        $successAction = $this->createPhase1TrackingAction(); // Priority 10

        ContextActionManager::register($failingAction);
        ContextActionManager::register($successAction);

        $context = new ActionContext('phase1', 123, ['items_saved' => 5]);
        $results = ContextActionManager::execute('phase1', $context);

        // Both should have executed
        $this->assertCount(2, $results);
        $this->assertFalse($results['failing_action']['success']);
        $this->assertTrue($results['phase1_tracker']['success']);
    }

    public function test_actions_receive_error_information_from_phase1()
    {
        $action = new class('error_handler', 10) extends AbstractContextAction {
            public function __construct()
            {
                parent::__construct('error_handler', 'Error Handler', 'Handles errors', ['phase1'], 10);
            }

            public function execute(ActionContext $context): ActionResult
            {
                $results = $context->getResults();
                $errors = $results['errors'] ?? [];

                return ActionResult::success([
                    'error_count' => count($errors),
                    'has_errors' => !empty($errors),
                ]);
            }
        };

        ContextActionManager::register($action);

        $context = new ActionContext('phase1', 123, [
            'items_saved' => 5,
            'errors' => [
                ['source' => 'source1', 'error' => 'Connection timeout'],
                ['source' => 'source2', 'error' => 'Invalid format'],
            ],
        ]);

        $results = ContextActionManager::execute('phase1', $context);

        $this->assertTrue($results['error_handler']['success']);
        $this->assertEquals(2, $results['error_handler']['data']['error_count']);
        $this->assertTrue($results['error_handler']['data']['has_errors']);
    }
}

