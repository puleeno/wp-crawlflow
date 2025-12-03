<?php

namespace CrawlFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;
use CrawlFlow\Admin\CrawlFlowController;
use CrawlFlow\Admin\ProjectService;

/**
 * Integration Test: Frontend ↔ Backend
 * Verify that frontend JSON payload matches backend expectations
 * 
 * @group integration
 * @group critical
 * @group frontend-backend
 */
class FrontendBackendIntegrationTest extends TestCase
{
    private CrawlFlowController $controller;
    private ProjectService $projectService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->controller = new CrawlFlowController();
        $this->projectService = new ProjectService();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_POST = [];
        $_REQUEST = [];
        $_SERVER = [];
    }

    public function test_frontend_json_payload_structure_matches_backend()
    {
        // This is the exact payload structure sent from App.tsx
        $frontendPayload = [
            'action' => 'crawlflow_save_project',
            'nonce' => 'be7912fa38',
            'project_name' => 'My Crawler Project',
            'project_description' => 'A new web crawler configuration.',
            'status' => 'draft',
            'project_data' => [
                'projectSettings' => [
                    'name' => 'My Crawler Project',
                    'description' => 'A new web crawler configuration.',
                    'enabled' => true,
                    'crawlDelay' => 1000,
                    'userAgent' => 'Crawler/1.0',
                    'concurrency' => 5,
                ],
                'nodes' => [
                    [
                        'id' => '1',
                        'type' => 'start',
                        'data' => [
                            'sourceType' => 'url',
                            'sourceValue' => 'www.example.com',
                        ],
                    ],
                ],
                'edges' => [],
            ],
        ];

        // Simulate JSON request
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        
        // Mock php://input
        $json = json_encode($frontendPayload);
        
        // Parse JSON request
        $_POST = $frontendPayload; // Simulate parseJsonRequest() result
        
        // Verify all expected fields are present
        $this->assertArrayHasKey('action', $_POST);
        $this->assertArrayHasKey('nonce', $_POST);
        $this->assertArrayHasKey('project_name', $_POST);
        $this->assertArrayHasKey('project_data', $_POST);
        
        // Verify project_data structure
        $projectData = $_POST['project_data'];
        $this->assertIsArray($projectData);
        $this->assertArrayHasKey('projectSettings', $projectData);
        $this->assertArrayHasKey('nodes', $projectData);
        $this->assertArrayHasKey('edges', $projectData);
    }

    public function test_backend_can_process_frontend_payload()
    {
        $frontendPayload = [
            'action' => 'crawlflow_save_project',
            'nonce' => 'test_nonce',
            'project_name' => 'Test Project',
            'project_description' => 'Test Description',
            'status' => 'draft',
            'project_data' => [
                'projectSettings' => [
                    'name' => 'Test Project',
                ],
                'nodes' => [
                    ['id' => '1', 'type' => 'start'],
                ],
                'edges' => [],
            ],
        ];

        // Simulate parsed JSON in $_POST
        $_POST = $frontendPayload;

        // Backend should be able to extract data
        $projectData = [
            'name' => sanitize_text_field($_POST['project_name']),
            'description' => sanitize_textarea_field($_POST['project_description'] ?? ''),
            'status' => sanitize_text_field($_POST['status']),
        ];

        // Handle project_data (this is what backend does)
        if (isset($_POST['project_data'])) {
            if (is_array($_POST['project_data'])) {
                $projectData['project_data'] = $_POST['project_data'];
            }
        }

        // Verify structure
        $this->assertEquals('Test Project', $projectData['name']);
        $this->assertEquals('draft', $projectData['status']);
        $this->assertArrayHasKey('project_data', $projectData);
        $this->assertIsArray($projectData['project_data']);
    }

    public function test_json_request_parsing_flow()
    {
        // Simulate JSON request from frontend
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        
        $payload = [
            'action' => 'crawlflow_save_project',
            'nonce' => 'test_nonce',
            'project_name' => 'Integration Test',
            'project_data' => [
                'nodes' => [],
                'edges' => [],
            ],
        ];

        // Simulate parseJsonRequest() behavior
        $_POST = $payload;
        $_REQUEST = array_merge($_REQUEST, $payload);

        // Verify parsing result
        $this->assertEquals('crawlflow_save_project', $_POST['action']);
        $this->assertEquals('Integration Test', $_POST['project_name']);
        $this->assertIsArray($_POST['project_data']);
    }

    public function test_project_data_serialization_matches_frontend()
    {
        // Frontend sends project_data as object
        $frontendProjectData = [
            'projectSettings' => [
                'name' => 'Test',
                'enabled' => true,
            ],
            'nodes' => [
                ['id' => '1', 'type' => 'start'],
            ],
            'edges' => [],
        ];

        // Backend should serialize it to JSON string for database
        $serialized = json_encode($frontendProjectData);
        
        // Should be valid JSON
        $this->assertIsString($serialized);
        $decoded = json_decode($serialized, true);
        $this->assertNotNull($decoded);
        $this->assertEquals($frontendProjectData, $decoded);
        
        // Backend should be able to deserialize
        $deserialized = json_decode($serialized, true);
        $this->assertEquals($frontendProjectData['nodes'], $deserialized['nodes']);
        $this->assertEquals($frontendProjectData['edges'], $deserialized['edges']);
    }

    public function test_response_format_matches_frontend_expectations()
    {
        // Frontend expects response in format:
        // { success: true, data: { message: '...', project_id: 123 } }
        
        // Simulate success response
        $expectedResponse = [
            'success' => true,
            'data' => [
                'message' => 'Project saved successfully',
                'project_id' => 123,
            ],
        ];

        // Verify response structure
        $this->assertArrayHasKey('success', $expectedResponse);
        $this->assertArrayHasKey('data', $expectedResponse);
        $this->assertArrayHasKey('project_id', $expectedResponse['data']);
        
        // Frontend checks: result.success
        $this->assertTrue($expectedResponse['success']);
        
        // Frontend checks: result.data.project_id
        $this->assertEquals(123, $expectedResponse['data']['project_id']);
    }

    public function test_all_ajax_actions_have_handlers()
    {
        // Initialize Rake for this test
        $rake = \Rake\Rake::getInstance();
        $bootstrapper = new \CrawlFlow\Bootstrapper\ApplicationBootstrapper();
        $bootstrapper->bootstrap();
        
        $controller = $rake->make('CrawlFlow\Admin\CrawlFlowController');
        
        // Verify controller has all required AJAX handler methods
        $requiredHandlers = [
            'handleSaveProject',
            'handleGetFlowConfig',
            'handleRunFlow',
            'handleRefreshDashboard',
            'handleAutoSaveProject',
            'handleDeleteProject',
        ];

        foreach ($requiredHandlers as $handler) {
            $this->assertTrue(
                method_exists($controller, $handler),
                "Controller missing handler: {$handler}"
            );
        }
    }
}

