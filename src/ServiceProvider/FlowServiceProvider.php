<?php

namespace CrawlFlow\ServiceProvider;

use Rake\Rake;
use Rake\ServiceProvider\AbstractServiceProvider;

/**
 * Flow Service Provider
 * Registers flow execution services
 */
class FlowServiceProvider extends AbstractServiceProvider
{
    /**
     * Register flow services
     */
    protected function registerServices(): void
    {
        // Register FlowService
        $this->app->singleton('CrawlFlow\Flow\FlowService', function ($app) {
            return new \CrawlFlow\Flow\FlowService($app);
        });

        // Register NodeRegistry
        $this->app->singleton('CrawlFlow\Flow\NodeRegistry', function ($app) {
            return new \CrawlFlow\Flow\NodeRegistry();
        });

        // Register RakeAdapter
        $this->app->singleton('CrawlFlow\Flow\RakeAdapter', function ($app) {
            return new \CrawlFlow\Flow\RakeAdapter($app);
        });

        // Register FlowExecutor
        $this->app->singleton('CrawlFlow\Flow\FlowExecutor', function ($app) {
            $nodeRegistry = $app->make('CrawlFlow\Flow\NodeRegistry');
            $rakeAdapter = $app->make('CrawlFlow\Flow\RakeAdapter');
            return new \CrawlFlow\Flow\FlowExecutor($nodeRegistry, $rakeAdapter);
        });

        // Register FlowRunnerService
        $this->app->singleton('CrawlFlow\Admin\FlowRunnerService', function ($app) {
            return new \CrawlFlow\Admin\FlowRunnerService();
        });
    }

    /**
     * Boot flow services
     */
    protected function bootServices(): void
    {
        // Register node executors
        $this->registerNodeExecutors();
    }

    /**
     * Register all node executors
     */
    private function registerNodeExecutors(): void
    {
        $nodeRegistry = $this->app->make('CrawlFlow\Flow\NodeRegistry');
        $rakeAdapter = $this->app->make('CrawlFlow\Flow\RakeAdapter');

        // Register StartNodeExecutor
        if (class_exists('CrawlFlow\Flow\Executors\StartNodeExecutor')) {
            $nodeRegistry->register(
                new \CrawlFlow\Flow\Executors\StartNodeExecutor($rakeAdapter)
            );
        }

        // Register WorkerNodeExecutor
        if (class_exists('CrawlFlow\Flow\Executors\WorkerNodeExecutor')) {
            $nodeRegistry->register(
                new \CrawlFlow\Flow\Executors\WorkerNodeExecutor($rakeAdapter)
            );
        }

        // Register HTMLDataExtractorExecutor
        if (class_exists('CrawlFlow\Flow\Executors\HTMLDataExtractorExecutor')) {
            $nodeRegistry->register(
                new \CrawlFlow\Flow\Executors\HTMLDataExtractorExecutor($rakeAdapter)
            );
        }

        // Register HtmlDataExtractorNodeExecutor (new implementation)
        if (class_exists('CrawlFlow\Flow\Executors\HtmlDataExtractorNodeExecutor')) {
            $nodeRegistry->register(
                new \CrawlFlow\Flow\Executors\HtmlDataExtractorNodeExecutor($rakeAdapter)
            );
        }

        // Register ProcessorNodeExecutor
        if (class_exists('CrawlFlow\Flow\Executors\ProcessorNodeExecutor')) {
            $nodeRegistry->register(
                new \CrawlFlow\Flow\Executors\ProcessorNodeExecutor($rakeAdapter)
            );
        }
    }
}

