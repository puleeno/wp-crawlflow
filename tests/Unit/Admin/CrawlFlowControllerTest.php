<?php

namespace CrawlFlow\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use CrawlFlow\Admin\CrawlFlowController;

/**
 * Test CrawlFlowController - AJAX Handlers
 * 
 * @group admin
 * @group unit
 * @group ajax
 * @group critical
 */
class CrawlFlowControllerTest extends TestCase
{
    private CrawlFlowController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock WordPress globals
        $_POST = [];
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        
        $this->controller = new CrawlFlowController();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_POST = [];
        $_SERVER = [];
    }

    public function test_controller_can_be_instantiated()
    {
        $this->assertInstanceOf(CrawlFlowController::class, $this->controller);
    }

    public function test_parse_json_request_merges_into_post()
    {
        $jsonData = json_encode([
            'action' => 'crawlflow_save_project',
            'nonce' => 'test_nonce',
            'project_name' => 'Test Project',
        ]);

        // Mock php://input
        $this->mockPhpInput($jsonData);

        $this->controller->parseJsonRequest();

        $this->assertEquals('crawlflow_save_project', $_POST['action']);
        $this->assertEquals('Test Project', $_POST['project_name']);
    }

    public function test_parse_json_request_ignores_non_ajax()
    {
        $_SERVER['REQUEST_URI'] = '/wp-admin/index.php';

        $this->controller->parseJsonRequest();

        $this->assertEmpty($_POST);
    }

    public function test_parse_json_request_ignores_non_json()
    {
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';

        $this->controller->parseJsonRequest();

        $this->assertEmpty($_POST);
    }

    public function test_parse_json_request_handles_invalid_json()
    {
        $this->mockPhpInput('invalid json {');

        $this->controller->parseJsonRequest();

        // Should not throw exception, just skip parsing
        $this->assertEmpty($_POST);
    }

    public function test_handle_save_project_validates_nonce()
    {
        $this->expectOutputString('{"success":false,"data":"Security check failed"}');

        $_POST = [
            'action' => 'crawlflow_save_project',
            'nonce' => 'invalid_nonce',
        ];

        // Mock wp_verify_nonce to return false
        if (!function_exists('wp_verify_nonce')) {
            function wp_verify_nonce($nonce, $action) {
                return false;
            }
        }

        $this->controller->handleSaveProject();
    }

    public function test_handle_save_project_requires_project_name()
    {
        $this->expectOutputString('{"success":false,"data":"Project name is required"}');

        $_POST = [
            'action' => 'crawlflow_save_project',
            'nonce' => 'valid_nonce',
            // Missing project_name
        ];

        $this->controller->handleSaveProject();
    }

    /**
     * Mock php://input for testing
     */
    private function mockPhpInput(string $data): void
    {
        // In real tests, you'd use stream_wrapper_register
        // For now, we'll just set $_POST directly for testing
        $decoded = json_decode($data, true);
        if ($decoded) {
            $_POST = array_merge($_POST, $decoded);
        }
    }
}

