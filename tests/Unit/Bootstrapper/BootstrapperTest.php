<?php

namespace CrawlFlow\Tests\Unit\Bootstrapper;

use PHPUnit\Framework\TestCase;
use Rake\ApplicationBootstrapper;
use Rake\Rake;

/**
 * Test Rake ApplicationBootstrapper
 * 
 * @group bootstrapper
 * @group unit
 */
class BootstrapperTest extends TestCase
{
    public function test_bootstrapper_can_be_instantiated()
    {
        $bootstrapper = new ApplicationBootstrapper();
        
        $this->assertInstanceOf(ApplicationBootstrapper::class, $bootstrapper);
    }

    public function test_bootstrapper_accepts_rake_instance()
    {
        $rake = Rake::getInstance();
        $bootstrapper = new ApplicationBootstrapper($rake);
        
        $this->assertSame($rake, $bootstrapper->getApp());
    }

    public function test_bootstrapper_accepts_providers_array()
    {
        $providers = [
            \CrawlFlow\ServiceProvider\CoreServiceProvider::class,
            \CrawlFlow\ServiceProvider\HttpServiceProvider::class,
        ];
        
        $bootstrapper = new ApplicationBootstrapper(null, $providers);
        
        $this->assertEquals($providers, $bootstrapper->getProviders());
    }

    public function test_can_add_provider()
    {
        $bootstrapper = new ApplicationBootstrapper();
        
        $bootstrapper->addProvider(\CrawlFlow\ServiceProvider\CoreServiceProvider::class);
        
        $providers = $bootstrapper->getProviders();
        $this->assertContains(\CrawlFlow\ServiceProvider\CoreServiceProvider::class, $providers);
    }

    public function test_can_set_providers()
    {
        $bootstrapper = new ApplicationBootstrapper();
        
        $newProviders = [
            \CrawlFlow\ServiceProvider\HttpServiceProvider::class,
        ];
        
        $bootstrapper->setProviders($newProviders);
        
        $this->assertEquals($newProviders, $bootstrapper->getProviders());
    }

    public function test_bootstrap_registers_and_boots_providers()
    {
        $providers = [
            \CrawlFlow\ServiceProvider\CoreServiceProvider::class,
        ];
        
        $bootstrapper = new ApplicationBootstrapper(null, $providers);
        $bootstrapper->bootstrap();
        
        $this->assertTrue($bootstrapper->isBootstrapped());
        $this->assertCount(1, $bootstrapper->getRegisteredProviders());
    }

    public function test_bootstrap_only_runs_once()
    {
        $bootstrapper = new ApplicationBootstrapper();
        
        $bootstrapper->bootstrap();
        $first = $bootstrapper->getRegisteredProviders();
        
        $bootstrapper->bootstrap();
        $second = $bootstrapper->getRegisteredProviders();
        
        $this->assertSame($first, $second);
    }

    public function test_can_reset_bootstrapper()
    {
        $bootstrapper = new ApplicationBootstrapper();
        $bootstrapper->bootstrap();
        
        $this->assertTrue($bootstrapper->isBootstrapped());
        
        $bootstrapper->reset();
        
        $this->assertFalse($bootstrapper->isBootstrapped());
        $this->assertCount(0, $bootstrapper->getRegisteredProviders());
    }
}

