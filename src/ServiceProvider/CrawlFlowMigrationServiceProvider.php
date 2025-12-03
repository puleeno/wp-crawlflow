<?php

namespace CrawlFlow\ServiceProvider;

use Rake\Rake;

class CrawlFlowMigrationServiceProvider extends CrawlFlowServiceProvider
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
