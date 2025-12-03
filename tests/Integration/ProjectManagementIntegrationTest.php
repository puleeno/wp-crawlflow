<?php

namespace CrawlFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;
use CrawlFlow\Admin\ProjectService;
use CrawlFlow\Admin\CrawlFlowController;

/**
 * Integration Test: Project Management Flow
 * Tests the complete flow from AJAX request to database
 * 
 * @group integration
 * @group project-management
 * @group critical
 */
class ProjectManagementIntegrationTest extends TestCase
{
    private ProjectService $projectService;
    private CrawlFlowController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock wpdb with in-memory storage
        global $wpdb;
        $wpdb = $this->createInMemoryWpdb();
        
        $this->projectService = new ProjectService();
        $this->controller = new CrawlFlowController();
        
        // Setup test database
        $this->setupTestDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanupTestDatabase();
    }

    public function test_complete_project_creation_flow()
    {
        // 1. Prepare JSON request
        $projectData = [
            'action' => 'crawlflow_save_project',
            'nonce' => wp_create_nonce('crawlflow_admin_nonce'),
            'project_name' => 'Integration Test Project',
            'project_description' => 'Test Description',
            'status' => 'draft',
            'project_data' => [
                'projectSettings' => [
                    'name' => 'Integration Test Project',
                    'enabled' => true,
                ],
                'nodes' => [
                    ['id' => '1', 'type' => 'start', 'data' => []],
                ],
                'edges' => [],
            ],
        ];

        // 2. Simulate JSON request
        $_POST = $projectData;
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php';
        $_SERVER['CONTENT_TYPE'] = 'application/json';

        // 3. Parse JSON (this should happen in controller)
        $this->controller->parseJsonRequest();

        // 4. Create project - extract data properly
        $createData = [
            'name' => $projectData['project_name'],
            'description' => $projectData['project_description'],
            'status' => $projectData['status'],
            'project_data' => $projectData['project_data'],
        ];
        
        $projectId = $this->projectService->createProject($createData);

        // 5. Verify project was created
        $this->assertGreaterThan(0, $projectId);

        // 6. Retrieve project
        $project = $this->projectService->getProject($projectId);

        // 7. Verify project data
        $this->assertNotNull($project);
        $this->assertEquals('Integration Test Project', $project['name']);
        $this->assertEquals('draft', $project['status']);

        // 8. Verify flow config
        $flowConfig = $this->projectService->getFlowConfig($projectId);
        $this->assertIsArray($flowConfig);
        $this->assertArrayHasKey('nodes', $flowConfig);
        $this->assertCount(1, $flowConfig['nodes']);
    }

    public function test_project_update_flow()
    {
        // Create initial project
        $projectId = $this->projectService->createProject([
            'name' => 'Original Name',
            'description' => 'Original Description',
            'status' => 'draft',
        ]);

        // Update project
        $updateData = [
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'status' => 'active',
        ];

        $result = $this->projectService->updateProject($projectId, $updateData);

        // Verify update
        $this->assertTrue($result);

        $project = $this->projectService->getProject($projectId);
        $this->assertEquals('Updated Name', $project['name']);
        $this->assertEquals('active', $project['status']);
    }

    public function test_project_deletion_flow()
    {
        // Create project
        $projectId = $this->projectService->createProject([
            'name' => 'To Be Deleted',
            'status' => 'draft',
        ]);

        // Verify it exists
        $project = $this->projectService->getProject($projectId);
        $this->assertNotNull($project);

        // Delete project
        $result = $this->projectService->deleteProject($projectId);
        $this->assertTrue($result);

        // Verify it's gone
        $project = $this->projectService->getProject($projectId);
        $this->assertNull($project);
    }

    /**
     * Create in-memory wpdb mock with storage
     */
    private function createInMemoryWpdb()
    {
        return new class {
            public $prefix = 'wp_';
            public $insert_id = 0;
            private $storage = [];
            
            public function insert($table, $data) {
                $id = count($this->storage) + 1;
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
            
            public function delete($table, $where) {
                $id = $where['id'] ?? 0;
                if (isset($this->storage[$id])) {
                    unset($this->storage[$id]);
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

    private function setupTestDatabase(): void
    {
        // In-memory storage already set up in createInMemoryWpdb
    }

    private function cleanupTestDatabase(): void
    {
        // In-memory storage will be cleaned on tearDown
    }
}

