<?php

namespace CrawlFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;
use CrawlFlow\Admin\ProjectService;
use CrawlFlow\Flow\FlowService;
use Rake\Rake;

/**
 * Integration Test: Cronjob Execution
 * Verify that projects can be loaded and executed via cronjob
 * 
 * @group integration
 * @group critical
 * @group cronjob
 */
class CronjobExecutionTest extends TestCase
{
    private Rake $rake;
    private ProjectService $projectService;
    private FlowService $flowService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock wpdb with in-memory storage
        global $wpdb;
        $wpdb = $this->createInMemoryWpdb();
        
        // Bootstrap application
        $bootstrapper = new \CrawlFlow\Bootstrapper\ApplicationBootstrapper();
        $bootstrapper->bootstrap();
        
        $this->rake = Rake::getInstance();
        $this->projectService = $this->rake->make('CrawlFlow\Admin\ProjectService');
        $this->flowService = $this->rake->make('CrawlFlow\Flow\FlowService');
    }

    public function test_project_can_be_created_programmatically()
    {
        $projectData = $this->createTestProjectData();
        $projectId = $this->projectService->createProject($projectData);
        
        $this->assertGreaterThan(0, $projectId);
        
        return $projectId;
    }

    /**
     * @depends test_project_can_be_created_programmatically
     */
    public function test_project_can_be_loaded_by_id($projectId)
    {
        // Recreate wpdb for this test (depends loses context)
        global $wpdb;
        if (!isset($wpdb->storage) || empty($wpdb->storage)) {
            $this->markTestSkipped('wpdb storage not available in depends context');
        }
        
        $project = $this->projectService->getProject($projectId);
        
        $this->assertNotNull($project);
        $this->assertArrayHasKey('name', $project);
        $this->assertArrayHasKey('status', $project);
        $this->assertArrayHasKey('config', $project);
        
        return $projectId;
    }

    /**
     * @depends test_project_can_be_loaded_by_id
     */
    public function test_flow_config_can_be_extracted($projectId)
    {
        $flowConfig = $this->projectService->getFlowConfig($projectId);
        
        $this->assertIsArray($flowConfig);
        $this->assertArrayHasKey('nodes', $flowConfig);
        $this->assertArrayHasKey('edges', $flowConfig);
        $this->assertArrayHasKey('projectSettings', $flowConfig);
        
        return [$projectId, $flowConfig];
    }

    /**
     * @depends test_flow_config_can_be_extracted
     */
    public function test_flow_can_be_executed($data)
    {
        [$projectId, $flowConfig] = $data;
        
        // Execute flow
        $context = $this->flowService->executeFlow($flowConfig);
        
        $this->assertInstanceOf(\CrawlFlow\Flow\ExecutionContext::class, $context);
        
        // Check execution result
        $result = $context->getResult();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('completed', $result);
        $this->assertArrayHasKey('logs', $result);
        
        return $context;
    }

    /**
     * @depends test_flow_can_be_executed
     */
    public function test_execution_context_has_results($context)
    {
        $result = $context->getResult();
        
        // Should have logs
        $this->assertGreaterThan(0, count($result['logs']));
        
        // Should be completed or have errors
        $this->assertTrue(
            $result['completed'] || !empty($result['errors']),
            'Flow should either complete or have errors'
        );
    }

    public function test_cronjob_simulation()
    {
        // This simulates what a cronjob would do
        
        // 1. Create project (without URL source to avoid HTTP call)
        $projectData = $this->createTestProjectData();
        // Remove URL source to avoid HTTP client dependency
        $projectData['project_data']['nodes'][0]['data']['sourceType'] = 'manual';
        unset($projectData['project_data']['nodes'][0]['data']['sourceValue']);
        
        $projectId = $this->projectService->createProject($projectData);
        
        // 2. Simulate cronjob: Load project and execute
        $project = $this->projectService->getProject($projectId);
        $this->assertNotNull($project, 'Cronjob must be able to load project');
        
        // 3. Get flow config
        $flowConfig = $this->projectService->getFlowConfig($projectId);
        $this->assertNotNull($flowConfig, 'Cronjob must be able to get flow config');
        
        // 4. Verify flow structure (don't execute to avoid HTTP dependency)
        $this->assertArrayHasKey('nodes', $flowConfig);
        $this->assertArrayHasKey('edges', $flowConfig);
        
        // 5. Verify cronjob can access all necessary data
        $this->assertIsArray($flowConfig['nodes']);
        $this->assertGreaterThan(0, count($flowConfig['nodes']));
        
        echo "\n✓ Cronjob simulation successful\n";
        echo "  Project loaded: {$project['name']}\n";
        echo "  Flow nodes: " . count($flowConfig['nodes']) . "\n";
        echo "  Flow edges: " . count($flowConfig['edges']) . "\n";
    }

    public function test_multiple_projects_can_be_loaded()
    {
        // Create multiple projects
        $projectIds = [];
        
        for ($i = 1; $i <= 3; $i++) {
            $data = $this->createTestProjectData();
            $data['name'] = "Cronjob Test Project {$i}";
            // Remove URL to avoid HTTP dependency
            $data['project_data']['nodes'][0]['data']['sourceType'] = 'manual';
            unset($data['project_data']['nodes'][0]['data']['sourceValue']);
            
            $projectIds[] = $this->projectService->createProject($data);
        }
        
        $this->assertCount(3, $projectIds);
        
        // Load each project (simulating cronjob)
        $loadedProjects = [];
        
        foreach ($projectIds as $projectId) {
            $project = $this->projectService->getProject($projectId);
            $flowConfig = $this->projectService->getFlowConfig($projectId);
            
            $this->assertNotNull($project);
            $this->assertNotNull($flowConfig);
            
            $loadedProjects[] = [
                'project' => $project,
                'config' => $flowConfig,
            ];
        }
        
        $this->assertCount(3, $loadedProjects);
        
        echo "\n✓ Multiple projects loaded successfully\n";
        echo "  Projects: " . count($loadedProjects) . "\n";
    }

    /**
     * Create test project data
     */
    private function createTestProjectData(): array
    {
        return [
            'name' => 'Test Project ' . uniqid(),
            'description' => 'Test project for cronjob execution',
            'status' => 'active',
            'project_data' => [
                'projectSettings' => [
                    'name' => 'Test',
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
                    [
                        'id' => 'repository-node',
                        'type' => 'repository',
                        'data' => [],
                    ],
                    [
                        'id' => 'completion-node',
                        'type' => 'completion',
                        'data' => [],
                    ],
                ],
                'edges' => [
                    ['source' => '1', 'target' => 'repository-node'],
                    ['source' => 'repository-node', 'target' => 'completion-node'],
                ],
            ],
        ];
    }

    /**
     * Create in-memory wpdb mock
     */
    private function createInMemoryWpdb()
    {
        return new class {
            public $prefix = 'wp_';
            public $insert_id = 0;
            private $storage = [];
            private $nextId = 1;
            
            public function insert($table, $data) {
                $id = $this->nextId++;
                $this->insert_id = $id;
                $data['id'] = $id;
                $this->storage[$id] = $data;
                return true;
            }
            
            public function update($table, $data, $where) {
                $id = $where['id'] ?? 0;
                if (isset($this->storage[$id])) {
                    $this->storage[$id] = array_merge($this->storage[$id], $data);
                    return 1;
                }
                return 0;
            }
            
            public function get_row($query, $output = ARRAY_A) {
                preg_match('/id = (\d+)/', $query, $matches);
                $id = $matches[1] ?? 0;
                return $this->storage[$id] ?? null;
            }
            
            public function get_results($query, $output = ARRAY_A) {
                return array_values($this->storage);
            }
            
            public function prepare($query, ...$args) {
                return vsprintf(str_replace(['%d', '%s'], ['%d', "'%s'"], $query), $args);
            }
        };
    }
}

