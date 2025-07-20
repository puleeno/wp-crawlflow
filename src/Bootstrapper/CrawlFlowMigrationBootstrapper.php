<?php

namespace CrawlFlow\Bootstrapper;

use CrawlFlow\Admin\MigrationService;
use Rake\Rake;
use Rake\Bootstrapper\BootstrapperInterface;

/**
 * Migration Bootstrapper for CrawlFlow
 *
 * Loads migration-related services for CrawlFlow plugin
 */
class CrawlFlowMigrationBootstrapper implements BootstrapperInterface
{
    /**
     * Bootstrap migration services
     *
     * @param Rake $app
     * @return void
     */
    public function bootstrap(Rake $app): void
    {
        // Register migration services
        $this->registerMigrationServices($app);
    }

    /**
     * Register migration services
     *
     * @param Rake $app
     * @return void
     */
    protected function registerMigrationServices(Rake $app): void
    {
        // Register MigrationService
        $app->singleton(MigrationService::class, function () use ($app) {
            return new MigrationService();
        });
    }
}