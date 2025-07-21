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
        // Đăng ký service provider cho migration
        $app->register(new \CrawlFlow\ServiceProvider\CrawlFlowMigrationServiceProvider());
    }
}
