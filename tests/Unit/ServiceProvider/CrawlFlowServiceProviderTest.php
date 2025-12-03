<?php

namespace CrawlFlow\Tests\Unit\ServiceProvider;

use PHPUnit\Framework\TestCase;
use CrawlFlow\ServiceProvider\CrawlFlowServiceProvider;
use Rake\ServiceProvider\ServiceProviderInterface;
use Rake\Rake;

/**
 * Test CrawlFlowServiceProvider
 * 
 * @group service-provider
 * @group unit
 */
class CrawlFlowServiceProviderTest extends TestCase
{
    public function test_implements_service_provider_interface()
    {
        $provider = $this->getMockForAbstractClass(CrawlFlowServiceProvider::class);
        
        $this->assertInstanceOf(ServiceProviderInterface::class, $provider);
    }

    public function test_has_register_method()
    {
        $provider = $this->getMockForAbstractClass(CrawlFlowServiceProvider::class);
        
        $this->assertTrue(method_exists($provider, 'register'));
    }

    public function test_has_boot_method()
    {
        $provider = $this->getMockForAbstractClass(CrawlFlowServiceProvider::class);
        
        $this->assertTrue(method_exists($provider, 'boot'));
    }

    public function test_has_register_services_method()
    {
        $provider = $this->getMockForAbstractClass(CrawlFlowServiceProvider::class);
        
        $this->assertTrue(method_exists($provider, 'registerServices'));
    }

    public function test_has_boot_services_method()
    {
        $provider = $this->getMockForAbstractClass(CrawlFlowServiceProvider::class);
        
        $this->assertTrue(method_exists($provider, 'bootServices'));
    }

    public function test_all_service_providers_extend_abstract_service_provider()
    {
        $providers = [
            \CrawlFlow\ServiceProvider\AdminServiceProvider::class,
            \CrawlFlow\ServiceProvider\CoreServiceProvider::class,
            \CrawlFlow\ServiceProvider\CrawlFlowDashboardServiceProvider::class,
            \CrawlFlow\ServiceProvider\CrawlFlowMigrationServiceProvider::class,
            \CrawlFlow\ServiceProvider\CronServiceProvider::class,
            \CrawlFlow\ServiceProvider\FlowServiceProvider::class,
            \CrawlFlow\ServiceProvider\HttpServiceProvider::class,
        ];

        foreach ($providers as $providerClass) {
            $reflection = new \ReflectionClass($providerClass);
            $parent = $reflection->getParentClass();
            
            $this->assertNotNull($parent, "{$providerClass} should have a parent class");
            $this->assertEquals(
                \Rake\ServiceProvider\AbstractServiceProvider::class,
                $parent->getName(),
                "{$providerClass} should extend AbstractServiceProvider"
            );
        }
    }

    public function test_all_service_providers_implement_interface()
    {
        $providers = [
            \CrawlFlow\ServiceProvider\AdminServiceProvider::class,
            \CrawlFlow\ServiceProvider\CoreServiceProvider::class,
            \CrawlFlow\ServiceProvider\CrawlFlowDashboardServiceProvider::class,
            \CrawlFlow\ServiceProvider\CrawlFlowMigrationServiceProvider::class,
            \CrawlFlow\ServiceProvider\CronServiceProvider::class,
            \CrawlFlow\ServiceProvider\FlowServiceProvider::class,
            \CrawlFlow\ServiceProvider\HttpServiceProvider::class,
        ];

        foreach ($providers as $providerClass) {
            $this->assertTrue(
                is_subclass_of($providerClass, ServiceProviderInterface::class),
                "{$providerClass} should implement ServiceProviderInterface"
            );
        }
    }
}

