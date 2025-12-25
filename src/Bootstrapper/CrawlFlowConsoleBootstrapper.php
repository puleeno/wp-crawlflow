<?php

namespace CrawlFlow\Bootstrapper;

use Rake\Rake;
use Rake\Bootstrapper\BootstrapperInterface;
use CrawlFlow\Bootstrapper\ApplicationBootstrapper;

/**
 * Console Bootstrapper for CrawlFlow
 *
 * Loads console-related services for CrawlFlow plugin
 */
class CrawlFlowConsoleBootstrapper implements BootstrapperInterface
{
    /**
     * Bootstrap console services
     *
     * @param Rake $app
     * @return void
     */
    public function bootstrap(Rake $app): void
    {
        // Load full application services including processors
        new ApplicationBootstrapper($app);
        
        // Register console-specific services
        $this->registerConsoleServices($app);
    }

    /**
     * Register console services
     *
     * @param Rake $app
     * @return void
     */
    protected function registerConsoleServices(Rake $app): void
    {
        // Register console-specific services if needed
        // $app->singleton('CrawlFlow\Console\ConsoleService', function () use ($app) {
        //     return new \CrawlFlow\Console\ConsoleService();
        // });
    }
}
