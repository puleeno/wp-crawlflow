<?php

namespace CrawlFlow\Tests\Unit\Kernel;

use PHPUnit\Framework\TestCase;
use CrawlFlow\Kernel\CrawlFlowDashboardKernel;

/**
 * Test CrawlFlowDashboardKernel
 * 
 * @group kernel
 * @group unit
 */
class CrawlFlowDashboardKernelTest extends TestCase
{
    private CrawlFlowDashboardKernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kernel = new CrawlFlowDashboardKernel();
    }

    public function test_kernel_can_be_instantiated()
    {
        $this->assertInstanceOf(CrawlFlowDashboardKernel::class, $this->kernel);
    }

    public function test_kernel_has_rake_instance()
    {
        $app = $this->kernel->getApp();
        $this->assertInstanceOf(\Rake\Rake::class, $app);
    }

    public function test_kernel_can_boot()
    {
        $result = $this->kernel->boot();
        $this->assertInstanceOf(CrawlFlowDashboardKernel::class, $result);
        $this->assertTrue($this->kernel->isBooted());
    }

    public function test_kernel_registers_bootstrappers()
    {
        $bootstrappers = $this->kernel->getBootstrappers();
        $this->assertIsArray($bootstrappers);
        $this->assertNotEmpty($bootstrappers);
        
        // Should have CrawlFlowCoreBootstrapper
        $this->assertContains(
            'CrawlFlow\Bootstrapper\CrawlFlowCoreBootstrapper',
            $bootstrappers
        );
        
        // Should have CrawlFlowDashboardBootstrapper
        $this->assertContains(
            'CrawlFlow\Bootstrapper\CrawlFlowDashboardBootstrapper',
            $bootstrappers
        );
    }

    public function test_kernel_status()
    {
        $this->kernel->boot();
        $status = $this->kernel->getStatus();
        
        $this->assertIsArray($status);
        $this->assertArrayHasKey('booted', $status);
        $this->assertTrue($status['booted']);
    }

    public function test_kernel_can_set_and_get_config()
    {
        $config = ['test' => 'value'];
        $this->kernel->setConfig($config);
        
        $this->assertEquals('value', $this->kernel->getConfig('test'));
        $this->assertNull($this->kernel->getConfig('nonexistent'));
    }
}

