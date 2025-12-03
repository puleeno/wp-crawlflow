<?php

namespace CrawlFlow\ServiceProvider;

use Rake\Rake;

/**
 * Admin Service Provider
 * Registers admin-related services
 */
class AdminServiceProvider extends CrawlFlowServiceProvider
{
    /**
     * Register admin services
     */
    protected function registerServices(): void
    {
        // Register DashboardService
        $this->app->singleton('CrawlFlow\Admin\DashboardService', function ($app) {
            return new \CrawlFlow\Admin\DashboardService();
        });

        // Register ProjectService
        $this->app->singleton('CrawlFlow\Admin\ProjectService', function ($app) {
            return new \CrawlFlow\Admin\ProjectService();
        });

        // Register LogService
        $this->app->singleton('CrawlFlow\Admin\LogService', function ($app) {
            return new \CrawlFlow\Admin\LogService();
        });

        // Register MigrationService
        $this->app->singleton('CrawlFlow\Admin\MigrationService', function ($app) {
            return new \CrawlFlow\Admin\MigrationService($app);
        });

        // Register CrawlFlowController
        $this->app->singleton('CrawlFlow\Admin\CrawlFlowController', function ($app) {
            return new \CrawlFlow\Admin\CrawlFlowController();
        });
    }

    /**
     * Boot admin services
     */
    protected function bootServices(): void
    {
        // Boot controller if in admin
        if (is_admin()) {
            $this->app->make('CrawlFlow\Admin\CrawlFlowController');
        }
    }
}

