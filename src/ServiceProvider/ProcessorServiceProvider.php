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
        // Register DentalPart processors
        $processorManager = $this->app->make(ProcessorManager::class);
        
        // Get Product Prices Processor
        $processorManager->register(
            'get_dentalpart_product_prices',
            \CrawlFlow\DentalPart\Processor\GetProductPricesProcessor::class,
            [
                'request_delay' => 500,
                'max_retries' => 3,
                'timeout' => 30
            ]
        );
        
        // Import WooCommerce Product Processor
        $processorManager->register(
            'import_woocommerce_product',
            \CrawlFlow\DentalPart\Processor\ImportWooCommerceProductProcessor::class,
            [
                'post_type' => 'product',
                'post_status' => 'publish'
            ]
        );
        
        // Map Product Categories Processor
        $processorManager->register(
            'map_product_categories',
            \CrawlFlow\DentalPart\Processor\MapProductCategoriesProcessor::class,
            []
        );
        
        // Looking For Parent Category Processor
        $processorManager->register(
            'looking_for_parent_category',
            \CrawlFlow\DentalPart\Processor\LookingForParentCategoryProcessor::class,
            []
        );
        
        // Scan Category Pages Processor
        $processorManager->register(
            'scan_category_pages',
            \CrawlFlow\DentalPart\Processor\ScanCategoryPagesProcessor::class,
            []
        );
        
        // Set Category Parent From Breadcrumbs Processor
        $processorManager->register(
            'set_category_parent_from_breadcrumbs',
            \CrawlFlow\DentalPart\Processor\SetCategoryParentFromBreadcrumbsProcessor::class,
            []
        );
        
        // Register aliases
        $processorManager->alias('get_product_prices', 'get_dentalpart_product_prices');
        $processorManager->alias('woocommerce_import', 'import_woocommerce_product');
        $processorManager->alias('map_categories', 'map_product_categories');

        // Log registration
        \Rake\Facade\Logger::info('CrawlFlow: All processors registered in ProcessorManager');
    }
}

