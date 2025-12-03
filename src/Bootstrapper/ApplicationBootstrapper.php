<?php

namespace CrawlFlow\Bootstrapper;

use Rake\Rake;

/**
 * Application Bootstrapper
 * Centralized bootstrapper for registering and booting all service providers
 */
class ApplicationBootstrapper
{
    /**
     * @var Rake
     */
    private Rake $app;

    /**
     * @var array Service providers to register
     */
    private array $providers = [
        \CrawlFlow\ServiceProvider\CoreServiceProvider::class,
        \CrawlFlow\ServiceProvider\AdminServiceProvider::class,
        \CrawlFlow\ServiceProvider\FlowServiceProvider::class,
        \CrawlFlow\ServiceProvider\CronServiceProvider::class,
    ];

    /**
     * @var array Registered providers
     */
    private array $registeredProviders = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->app = Rake::getInstance();
    }

    /**
     * Bootstrap the application
     * 
     * @return self
     * @throws \RuntimeException If bootstrapping fails
     */
    public function bootstrap(): self
    {
        $this->registerProviders();
        $this->bootProviders();
        
        return $this;
    }

    /**
     * Register all service providers
     * 
     * @throws \RuntimeException If provider registration fails
     */
    private function registerProviders(): void
    {
        foreach ($this->providers as $providerClass) {
            if (!class_exists($providerClass)) {
                throw new \RuntimeException("Service provider not found: {$providerClass}");
            }

            $provider = new $providerClass();
            
            // Register services
            if (method_exists($provider, 'register')) {
                $provider->register($this->app);
            }

            $this->registeredProviders[] = $provider;
        }
    }

    /**
     * Boot all registered service providers
     */
    private function bootProviders(): void
    {
        foreach ($this->registeredProviders as $provider) {
            if (method_exists($provider, 'boot')) {
                $provider->boot($this->app);
            }
        }
    }

    /**
     * Get Rake application instance
     */
    public function getApp(): Rake
    {
        return $this->app;
    }

    /**
     * Get registered providers
     */
    public function getRegisteredProviders(): array
    {
        return $this->registeredProviders;
    }

    /**
     * Add custom provider
     */
    public function addProvider(string $providerClass): self
    {
        $this->providers[] = $providerClass;
        return $this;
    }
}

