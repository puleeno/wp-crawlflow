<?php

namespace CrawlFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;
use CrawlFlow\Bootstrapper\ApplicationBootstrapper;
use Rake\Rake;

/**
 * Integration Test: Service Provider Boot
 * Verify that all services are properly registered and booted via service providers
 * 
 * @group integration
 * @group critical
 * @group service-providers
 */
class ServiceProviderBootTest extends TestCase
{
    private ApplicationBootstrapper $bootstrapper;
    private Rake $rake;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Bootstrap application
        $this->bootstrapper = new ApplicationBootstrapper();
        $this->bootstrapper->bootstrap();
        $this->rake = $this->bootstrapper->getApp();
    }

    public function test_application_bootstrapper_initializes()
    {
        $this->assertInstanceOf(ApplicationBootstrapper::class, $this->bootstrapper);
        $this->assertInstanceOf(Rake::class, $this->rake);
    }

    public function test_core_services_are_registered()
    {
        // Logger should be registered
        $this->assertTrue($this->rake->has('CrawlFlow\LoggerService'));
        
        // Config should be registered
        $this->assertTrue($this->rake->has('config'));
        
        $config = $this->rake->make('config');
        $this->assertIsArray($config);
        $this->assertArrayHasKey('plugin', $config);
    }

    public function test_admin_services_are_registered()
    {
        // DashboardService
        $this->assertTrue($this->rake->has('CrawlFlow\Admin\DashboardService'));
        $dashboardService = $this->rake->make('CrawlFlow\Admin\DashboardService');
        $this->assertInstanceOf(\CrawlFlow\Admin\DashboardService::class, $dashboardService);
        
        // ProjectService
        $this->assertTrue($this->rake->has('CrawlFlow\Admin\ProjectService'));
        $projectService = $this->rake->make('CrawlFlow\Admin\ProjectService');
        $this->assertInstanceOf(\CrawlFlow\Admin\ProjectService::class, $projectService);
        
        // LogService
        $this->assertTrue($this->rake->has('CrawlFlow\Admin\LogService'));
        $logService = $this->rake->make('CrawlFlow\Admin\LogService');
        $this->assertInstanceOf(\CrawlFlow\Admin\LogService::class, $logService);
        
        // MigrationService
        $this->assertTrue($this->rake->has('CrawlFlow\Admin\MigrationService'));
        $migrationService = $this->rake->make('CrawlFlow\Admin\MigrationService');
        $this->assertInstanceOf(\CrawlFlow\Admin\MigrationService::class, $migrationService);
    }

    public function test_controller_is_registered()
    {
        // CrawlFlowController should be registered
        $this->assertTrue($this->rake->has('CrawlFlow\Admin\CrawlFlowController'));
        
        // Should be able to resolve
        $controller = $this->rake->make('CrawlFlow\Admin\CrawlFlowController');
        $this->assertInstanceOf(\CrawlFlow\Admin\CrawlFlowController::class, $controller);
    }

    public function test_flow_services_are_registered()
    {
        // FlowService
        $this->assertTrue($this->rake->has('CrawlFlow\Flow\FlowService'));
        $flowService = $this->rake->make('CrawlFlow\Flow\FlowService');
        $this->assertInstanceOf(\CrawlFlow\Flow\FlowService::class, $flowService);
        
        // NodeRegistry
        $this->assertTrue($this->rake->has('CrawlFlow\Flow\NodeRegistry'));
        $nodeRegistry = $this->rake->make('CrawlFlow\Flow\NodeRegistry');
        $this->assertInstanceOf(\CrawlFlow\Flow\NodeRegistry::class, $nodeRegistry);
        
        // RakeAdapter
        $this->assertTrue($this->rake->has('CrawlFlow\Flow\RakeAdapter'));
        $rakeAdapter = $this->rake->make('CrawlFlow\Flow\RakeAdapter');
        $this->assertInstanceOf(\CrawlFlow\Flow\RakeAdapter::class, $rakeAdapter);
    }

    public function test_services_are_singletons()
    {
        // Resolve service twice
        $projectService1 = $this->rake->make('CrawlFlow\Admin\ProjectService');
        $projectService2 = $this->rake->make('CrawlFlow\Admin\ProjectService');
        
        // Should be the same instance (singleton)
        $this->assertSame($projectService1, $projectService2);
    }

    public function test_service_providers_are_booted()
    {
        $providers = $this->bootstrapper->getRegisteredProviders();
        
        // Should have 5 providers (Core, Http, Admin, Flow, Cron)
        $this->assertCount(5, $providers);
        
        // Check provider types
        $providerClasses = array_map(fn($p) => get_class($p), $providers);
        
        $this->assertContains('CrawlFlow\ServiceProvider\CoreServiceProvider', $providerClasses);
        $this->assertContains('CrawlFlow\ServiceProvider\HttpServiceProvider', $providerClasses);
        $this->assertContains('CrawlFlow\ServiceProvider\AdminServiceProvider', $providerClasses);
        $this->assertContains('CrawlFlow\ServiceProvider\FlowServiceProvider', $providerClasses);
        $this->assertContains('CrawlFlow\ServiceProvider\CronServiceProvider', $providerClasses);
    }

    public function test_controller_hooks_are_registered()
    {
        // Controller should be instantiated (registers hooks in constructor)
        $controller = $this->rake->make('CrawlFlow\Admin\CrawlFlowController');
        
        // Verify controller has required methods
        $this->assertTrue(method_exists($controller, 'parseJsonRequest'));
        $this->assertTrue(method_exists($controller, 'handleSaveProject'));
        $this->assertTrue(method_exists($controller, 'handleGetFlowConfig'));
    }

    public function test_rake_singleton_pattern()
    {
        // Get Rake instance multiple times
        $rake1 = Rake::getInstance();
        $rake2 = Rake::getInstance();
        
        // Should be the same instance
        $this->assertSame($rake1, $rake2);
    }
}

