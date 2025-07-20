<?php

namespace CrawlFlow\Bootstrapper;

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
        $app->singleton('CrawlFlow\Admin\MigrationService', function () use ($app) {
            return new \CrawlFlow\Admin\MigrationService();
        });
    }
}