<?php

namespace CrawlFlow\ServiceProvider;

use Rake\Rake;
use Rake\ServiceProvider\ServiceProviderInterface;

/**
 * Abstract CrawlFlow Service Provider
 * Base class for all CrawlFlow service providers
 * Implements ServiceProviderInterface from Rake framework
 */
abstract class CrawlFlowServiceProvider implements ServiceProviderInterface
{
    /**
     * The application instance
     *
     * @var Rake
     */
    protected Rake $app;

    /**
     * Register services with the container
     *
     * @param Rake $app
     * @return void
     */
    public function register(Rake $app): void
    {
        $this->app = $app;
        $this->registerServices();
    }

    /**
     * Boot services after all providers are registered
     *
     * @param Rake $app
     * @return void
     */
    public function boot(Rake $app): void
    {
        $this->app = $app;
        $this->bootServices();
    }

    /**
     * Register the provider's services
     * Must be implemented by child classes
     *
     * @return void
     */
    abstract protected function registerServices(): void;

    /**
     * Boot the provider's services
     * Can be overridden by child classes if needed
     *
     * @return void
     */
    protected function bootServices(): void
    {
        // Default implementation does nothing
        // Child classes can override if needed
    }
}

