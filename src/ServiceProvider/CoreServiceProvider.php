<?php

namespace CrawlFlow\ServiceProvider;

use Rake\Rake;
use Rake\ServiceProvider\AbstractServiceProvider;
use Rake\Contracts\Database\Adapter\DatabaseAdapterInterface;
use Puleeno\Rake\WordPress\Adapter\WordPressDatabaseAdapter;

/**
 * Core Service Provider
 * Registers core services and configurations
 */
class CoreServiceProvider extends AbstractServiceProvider
{
    /**
     * Register core services
     */
    protected function registerServices(): void
    {
        // Register Database Adapter
        $this->app->singleton(DatabaseAdapterInterface::class, function ($app) {
            return new \Puleeno\Rake\WordPress\Adapter\WordPressDatabaseAdapter();
        });

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
                    'path' => WP_CONTENT_DIR . '/crawlflow/',
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
            \Rake\Facade\Logger::error('CrawlFlow: Failed to initialize logger - ' . $e->getMessage());
        }
    }
}

