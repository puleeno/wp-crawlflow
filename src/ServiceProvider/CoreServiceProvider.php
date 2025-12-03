<?php

namespace CrawlFlow\ServiceProvider;

use Rake\Rake;

/**
 * Core Service Provider
 * Registers core services and configurations
 */
class CoreServiceProvider extends CrawlFlowServiceProvider
{
    /**
     * Register core services
     */
    protected function registerServices(): void
    {
        // Register LoggerService
        $this->app->singleton('CrawlFlow\LoggerService', function ($app) {
            return \CrawlFlow\LoggerService::getLogger();
        });

        // Register configuration
        $this->app->singleton('config', function () {
            return [
                'plugin' => [
                    'name' => 'CrawlFlow',
                    'version' => defined('CRAWLFLOW_VERSION') ? CRAWLFLOW_VERSION : '2.0.0',
                    'debug_mode' => get_option('crawlflow_debug_mode', false),
                ],
                'logging' => [
                    'level' => get_option('crawlflow_log_level', 'info'),
                    'path' => WP_CONTENT_DIR . '/crawlflow/logs/',
                ],
            ];
        });
    }

    /**
     * Boot core services
     */
    protected function bootServices(): void
    {
        // Initialize logger
        try {
            \CrawlFlow\LoggerService::init();
        } catch (\Exception $e) {
            error_log('CrawlFlow: Failed to initialize logger - ' . $e->getMessage());
        }
    }
}

