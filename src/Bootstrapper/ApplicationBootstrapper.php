<?php

namespace CrawlFlow\Bootstrapper;

use Rake\Rake;
use Rake\ApplicationBootstrapper as RakeApplicationBootstrapper;

/**
 * Application Bootstrapper
 * CrawlFlow-specific bootstrapper that extends Rake ApplicationBootstrapper
 */
class ApplicationBootstrapper extends RakeApplicationBootstrapper
{
    /**
     * Default service providers for CrawlFlow
     */
    private const DEFAULT_PROVIDERS = [
        \CrawlFlow\ServiceProvider\CoreServiceProvider::class,
        \CrawlFlow\ServiceProvider\LoggerServiceProvider::class,
        \CrawlFlow\ServiceProvider\HttpServiceProvider::class,
        \CrawlFlow\ServiceProvider\ProcessorServiceProvider::class,
        \CrawlFlow\ServiceProvider\AdminServiceProvider::class,
        \CrawlFlow\ServiceProvider\FlowServiceProvider::class,
        \CrawlFlow\ServiceProvider\CronServiceProvider::class,
    ];

    /**
     * Constructor
     * 
     * @param Rake|null $app Rake instance (optional)
     * @param array $providers Additional providers (optional)
     */
    public function __construct(?Rake $app = null, array $providers = [])
    {
        $app = $app ?? Rake::getInstance();
        
        // Merge default providers with custom providers
        $allProviders = array_merge(self::DEFAULT_PROVIDERS, $providers);
        
        parent::__construct($app, $allProviders);
    }
}

