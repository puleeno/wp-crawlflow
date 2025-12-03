<?php

namespace CrawlFlow\ServiceProvider;

use Rake\Rake;
use Rake\ServiceProvider\AbstractServiceProvider;
use Rake\Manager\ProcessorManager;
use CrawlFlow\Processors\WordPressPostProcessor;

/**
 * Processor Service Provider
 * Registers processors for data processing
 */
class ProcessorServiceProvider extends AbstractServiceProvider
{
    /**
     * Register processor services
     */
    protected function registerServices(): void
    {
        // Register ProcessorManager singleton
        $this->app->singleton(ProcessorManager::class, function ($app) {
            return new ProcessorManager();
        });

        // Register processors
        $this->registerProcessors();
    }

    /**
     * Boot processor services
     */
    protected function bootServices(): void
    {
        // Nothing to boot
    }

    /**
     * Register all processors
     */
    private function registerProcessors(): void
    {
        // Register WordPress Post Processor
        ProcessorManager::register(
            'save_to_wordpress',
            WordPressPostProcessor::class,
            [
                'postType' => 'post',
                'postStatus' => 'draft',
            ]
        );

        // Register aliases
        ProcessorManager::alias('wordpress_post', 'save_to_wordpress');
        ProcessorManager::alias('wp_post', 'save_to_wordpress');

        // Log registration
        if (function_exists('error_log')) {
            error_log('CrawlFlow: WordPressPostProcessor registered in ProcessorManager');
        }
    }
}

