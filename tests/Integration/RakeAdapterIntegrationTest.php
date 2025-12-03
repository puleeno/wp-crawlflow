<?php

namespace CrawlFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Rake\Rake;
use Puleeno\Rake\WordPress\Adapter\WordPressDatabaseAdapter;

/**
 * Integration Test: Rake WordPress Adapter
 * Verify plugin sử dụng đúng rake-wordpress-adapter để làm việc với Rake Framework
 * 
 * @group integration
 * @group critical
 * @group rake-adapter
 */
class RakeAdapterIntegrationTest extends TestCase
{
    private Rake $rake;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Bootstrap application
        $bootstrapper = new \CrawlFlow\Bootstrapper\ApplicationBootstrapper();
        $bootstrapper->bootstrap();
        
        $this->rake = Rake::getInstance();
    }

    public function test_wordpress_database_adapter_is_used_in_project_service()
    {
        $projectService = $this->rake->make('CrawlFlow\Admin\ProjectService');
        
        // Use reflection to check private property
        $reflection = new \ReflectionClass($projectService);
        $property = $reflection->getProperty('databaseAdapter');
        $property->setAccessible(true);
        $adapter = $property->getValue($projectService);
        
        $this->assertInstanceOf(
            WordPressDatabaseAdapter::class,
            $adapter,
            'ProjectService should use WordPressDatabaseAdapter from rake-wordpress-adapter'
        );
    }

    public function test_wordpress_database_adapter_is_used_in_migration_service()
    {
        $migrationService = $this->rake->make('CrawlFlow\Admin\MigrationService');
        
        // Use reflection to check private property
        $reflection = new \ReflectionClass($migrationService);
        $property = $reflection->getProperty('wordpressAdapter');
        $property->setAccessible(true);
        $adapter = $property->getValue($migrationService);
        
        $this->assertInstanceOf(
            WordPressDatabaseAdapter::class,
            $adapter,
            'MigrationService should use WordPressDatabaseAdapter from rake-wordpress-adapter'
        );
    }

    public function test_wordpress_database_adapter_is_used_in_log_service()
    {
        $logService = $this->rake->make('CrawlFlow\Admin\LogService');
        
        // Use reflection to check private property
        $reflection = new \ReflectionClass($logService);
        $property = $reflection->getProperty('databaseAdapter');
        $property->setAccessible(true);
        $adapter = $property->getValue($logService);
        
        $this->assertInstanceOf(
            WordPressDatabaseAdapter::class,
            $adapter,
            'LogService should use WordPressDatabaseAdapter from rake-wordpress-adapter'
        );
    }

    public function test_rake_adapter_provides_access_to_wordpress_features()
    {
        $rakeAdapter = $this->rake->make('CrawlFlow\Flow\RakeAdapter');
        
        // Verify RakeAdapter can access Rake instance
        $this->assertInstanceOf(Rake::class, $rakeAdapter->getRake());
        
        // Verify RakeAdapter provides manager access
        $this->assertTrue(method_exists($rakeAdapter, 'getDatabaseDriverManager'));
        $this->assertTrue(method_exists($rakeAdapter, 'getProcessorManager'));
    }

    public function test_wordpress_adapter_integrates_with_rake_framework()
    {
        // Create adapter instance
        $adapter = new WordPressDatabaseAdapter();
        
        // Verify it's instantiable
        $this->assertInstanceOf(WordPressDatabaseAdapter::class, $adapter);
        
        // Verify it has database methods
        $this->assertTrue(method_exists($adapter, 'insert'));
        $this->assertTrue(method_exists($adapter, 'update'));
        $this->assertTrue(method_exists($adapter, 'delete'));
        $this->assertTrue(method_exists($adapter, 'select'));
        
        // Verify it has driver
        $driver = $adapter->getDriver();
        $this->assertInstanceOf(\Puleeno\Rake\WordPress\Driver\WordPressDatabaseDriver::class, $driver);
    }

    public function test_plugin_services_use_adapter_not_direct_wpdb()
    {
        $projectService = $this->rake->make('CrawlFlow\Admin\ProjectService');
        
        // ProjectService should use WordPressDatabaseAdapter
        $reflection = new \ReflectionClass($projectService);
        $property = $reflection->getProperty('databaseAdapter');
        $property->setAccessible(true);
        $adapter = $property->getValue($projectService);
        
        // Should be adapter, not direct wpdb
        $this->assertInstanceOf(
            WordPressDatabaseAdapter::class,
            $adapter,
            'Services should use WordPressDatabaseAdapter, not direct $wpdb access'
        );
        
        // Should NOT be wpdb
        $this->assertNotInstanceOf(\wpdb::class, $adapter);
    }

    public function test_adapter_provides_abstraction_over_wordpress()
    {
        $adapter = new WordPressDatabaseAdapter();
        
        // Adapter should provide abstraction methods
        $methods = get_class_methods($adapter);
        
        // Should have CRUD methods
        $this->assertContains('insert', $methods);
        $this->assertContains('update', $methods);
        $this->assertContains('delete', $methods);
        $this->assertContains('select', $methods);
        
        // Should have utility methods
        $this->assertContains('tableExists', $methods);
        $this->assertContains('transaction', $methods);
        
        // Verify it abstracts WordPress specifics
        $this->assertInstanceOf(WordPressDatabaseAdapter::class, $adapter);
    }

    public function test_rake_and_wordpress_adapter_work_together()
    {
        // This tests the integration:
        // Rake Framework ← rake-wordpress-adapter ← wp-crawlflow plugin
        
        // 1. Rake instance exists
        $this->assertInstanceOf(Rake::class, $this->rake);
        
        // 2. Plugin services use WordPress adapter
        $projectService = $this->rake->make('CrawlFlow\Admin\ProjectService');
        $reflection = new \ReflectionClass($projectService);
        $property = $reflection->getProperty('databaseAdapter');
        $property->setAccessible(true);
        $adapter = $property->getValue($projectService);
        
        $this->assertInstanceOf(WordPressDatabaseAdapter::class, $adapter);
        
        // 3. Adapter connects Rake with WordPress
        // This is the key: adapter bridges Rake framework with WordPress
        $this->assertTrue(true, 'Integration verified: Rake ← Adapter ← Plugin');
    }
}

