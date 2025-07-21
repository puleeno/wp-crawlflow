<?php

namespace CrawlFlow\ServiceProvider;

use Rake\Rake;
use Rake\ServiceProvider\AbstractServiceProvider;

class CrawlFlowMigrationServiceProvider extends AbstractServiceProvider
{
    protected function registerServices(): void
    {
        $this->app->singleton('CrawlFlow\Admin\MigrationService', function () {
            return new \CrawlFlow\Admin\MigrationService();
        });
    }

    protected function bootServices(): void
    {
        // Boot logic nếu cần
    }
}
