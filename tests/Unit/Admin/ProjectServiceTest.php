<?php

namespace CrawlFlow\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use CrawlFlow\Admin\ProjectService;

/**
 * Test ProjectService
 * 
 * @group admin
 * @group unit
 * @group project-management
 */
class ProjectServiceTest extends TestCase
{
    private ProjectService $service;
    private $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock wpdb
        global $wpdb;
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        $wpdb = $this->wpdb;
        
        $this->service = new ProjectService();
    }

    public function test_service_can_be_instantiated()
    {
        $this->assertInstanceOf(ProjectService::class, $this->service);
    }

    public function test_create_project_returns_project_id()
    {
        $this->wpdb->expects($this->once())
            ->method('insert')
            ->willReturn(true);
        
        $this->wpdb->insert_id = 123;
        
        $projectData = [
            'name' => 'Test Project',
            'description' => 'Test Description',
            'status' => 'draft',
        ];
        
        $result = $this->service->createProject($projectData);
        
        $this->assertEquals(123, $result);
    }

    public function test_create_project_validates_required_fields()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Project name is required');

        $projectData = [
            'description' => 'Test Description',
            // Missing 'name'
        ];
        
        $this->service->createProject($projectData);
    }

    public function test_update_project_returns_true_on_success()
    {
        $this->wpdb->expects($this->once())
            ->method('update')
            ->willReturn(1);
        
        $projectData = [
            'name' => 'Updated Project',
            'description' => 'Updated Description',
        ];
        
        $result = $this->service->updateProject(123, $projectData);
        
        $this->assertTrue($result);
    }

    public function test_delete_project_returns_true_on_success()
    {
        $this->wpdb->expects($this->once())
            ->method('delete')
            ->willReturn(1);
        
        $result = $this->service->deleteProject(123);
        
        $this->assertTrue($result);
    }

    public function test_get_project_returns_project_data()
    {
        $expectedProject = [
            'id' => 123,
            'name' => 'Test Project',
            'description' => 'Test Description',
            'status' => 'draft',
        ];
        
        $this->wpdb->expects($this->once())
            ->method('get_row')
            ->willReturn($expectedProject);
        
        $result = $this->service->getProject(123);
        
        $this->assertEquals($expectedProject, $result);
        $this->assertIsArray($result);
    }

    public function test_get_all_projects_returns_array()
    {
        $expectedProjects = [
            (object) ['id' => 1, 'name' => 'Project 1'],
            (object) ['id' => 2, 'name' => 'Project 2'],
        ];
        
        $this->wpdb->expects($this->once())
            ->method('get_results')
            ->willReturn($expectedProjects);
        
        $result = $this->service->getAllProjects();
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function test_project_data_is_serialized_correctly()
    {
        $projectData = [
            'name' => 'Test',
            'project_data' => [
                'nodes' => [],
                'edges' => [],
            ],
        ];
        
        // Project data should be JSON serialized when saving
        $this->wpdb->expects($this->once())
            ->method('insert')
            ->with(
                $this->stringContains('rake_tooths'),
                $this->callback(function($data) {
                    // Verify config field contains JSON
                    if (!isset($data['config'])) {
                        return false;
                    }
                    
                    // Config should be JSON string
                    if (!is_string($data['config'])) {
                        return false;
                    }
                    
                    // Should be valid JSON
                    $decoded = json_decode($data['config'], true);
                    return $decoded !== null && json_last_error() === JSON_ERROR_NONE;
                })
            )
            ->willReturn(true);
        
        $this->wpdb->insert_id = 1;
        
        $result = $this->service->createProject($projectData);
        $this->assertEquals(1, $result);
    }
}

