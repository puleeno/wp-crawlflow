<?php

namespace CrawlFlow\Bootstrapper;

use Rake\Rake;
use Rake\Bootstrapper\BootstrapperInterface;

/**
 * Core Bootstrapper for CrawlFlow
 *
 * Loads core system components for CrawlFlow plugin
 */
class CrawlFlowCoreBootstrapper implements BootstrapperInterface
{
    /**
     * Bootstrap core services
     *
     * @param Rake $app
     * @return void
     */
    public function bootstrap(Rake $app): void
    {
        // Register core services
        $this->registerCoreServices($app);
    }

    /**
     * Register core services
     *
     * @param Rake $app
     * @return void
     */
    protected function registerCoreServices(Rake $app): void
    {
        // Register configuration
        $app->singleton('config', function () {
            return [
                'plugin' => [
                    'name' => 'CrawlFlow',
                    'version' => defined('CRAWLFLOW_VERSION') ? CRAWLFLOW_VERSION : '2.0.0',
                    'debug_mode' => get_option('crawlflow_debug_mode', false),
                ],
            ];
        });
    }
}
