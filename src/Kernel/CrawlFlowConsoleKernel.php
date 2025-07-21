<?php

namespace CrawlFlow\Kernel;

use Rake\Kernel\ConsoleKernel as BaseConsoleKernel;

/**
 * CrawlFlow Console Kernel
 *
 * Handles console tasks for CrawlFlow plugin in WordPress
 */
class CrawlFlowConsoleKernel extends BaseConsoleKernel
{
    /**
     * Register custom bootstrappers for CrawlFlow console kernel
     *
     * @return void
     */
    protected function registerBootstrappers(): void
    {
        parent::registerBootstrappers();

        $this->addCustomBootstrapper(\CrawlFlow\Bootstrapper\CrawlFlowConsoleBootstrapper::class);
    }

    /**
     * Initialize CrawlFlow console kernel
     *
     * @return self
     */
    public function initialize(): self
    {
        parent::initialize();
        $this->setConfig([
            'plugin' => [
                'name' => 'CrawlFlow',
                'version' => defined('CRAWLFLOW_VERSION') ? CRAWLFLOW_VERSION : '2.0.0',
            ],
            'logging' => [
                'level' => 'info',
                'file' => 'crawlflow-console.log',
                'directory' => WP_CONTENT_DIR . '/crawlflow/',
            ],
        ]);
        return $this;
    }
}
