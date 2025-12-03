<?php

namespace CrawlFlow\ServiceProvider;

use Rake\Rake;
use Rake\ServiceProvider\AbstractServiceProvider;

class CrawlFlowMigrationServiceProvider extends AbstractServiceProvider
{
    protected function registerServices(): void
    {
        $this->app->singleton('CrawlFlow\Admin\MigrationService', function ($app) {
            return new \CrawlFlow\Admin\MigrationService($app);
        });
    }

    protected function bootServices(): void
    {
        // Boot logic nếu cần
    }
}
