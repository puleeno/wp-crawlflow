<?php

namespace CrawlFlow\Hooks;

use Rake\Manager\DataSourceManager;
use Rake\Manager\ProcessorManager;
use Rake\DataSource\UrlDataSource;
use CrawlFlow\Processors\WordPressPostProcessor;

/**
 * Registry Hooks
 * Provides hooks for external plugins to register data sources, processors, parsers
 */
class RegistryHooks
{
    /**
     * Initialize hooks
     */
    public static function init(): void
    {
        // Hook to register data sources
        do_action('crawlflow_register_data_sources');

        // Hook to register processors
        do_action('crawlflow_register_processors');

        // Hook to register parsers
        do_action('crawlflow_register_parsers');

        // Register default types
        self::registerDefaults();
    }

    /**
     * Register default data sources, processors, parsers
     */
    private static function registerDefaults(): void
    {
        // Register default data source types
        if (!DataSourceManager::hasType('url')) {
            DataSourceManager::registerType('url', UrlDataSource::class);
        }

        // Register built-in Rake processors
        self::registerBuiltInProcessors();

        // Register WordPress-specific processors
        if (!ProcessorManager::has('save_to_wordpress')) {
            ProcessorManager::register('save_to_wordpress', WordPressPostProcessor::class, [
                'postType' => 'post',
                'postStatus' => 'draft',
            ]);
        }

        // Apply filters to allow modification
        do_action('crawlflow_after_register_defaults');
    }

    /**
     * Register built-in Rake processors
     */
    private static function registerBuiltInProcessors(): void
    {
        // Save to Database
        if (!ProcessorManager::has('save_to_database')) {
            ProcessorManager::register('save_to_database', \Rake\Processor\SaveToDatabaseProcessor::class, [
                'connectionType' => 'mysql',
                'host' => 'localhost',
                'port' => '3306',
                'user' => 'root',
                'password' => '',
                'database' => 'scraped_data',
                'tableName' => 'results',
                'conflictStrategy' => 'upsert',
            ]);
        }

        // Send to API
        if (!ProcessorManager::has('send_to_api')) {
            ProcessorManager::register('send_to_api', \Rake\Processor\SendToApiProcessor::class, [
                'endpointUrl' => 'https://api.example.com/data',
                'method' => 'POST',
                'authType' => 'none',
                'authDetails' => [],
                'headers' => [],
            ]);
        }

        // Generate CSV File
        if (!ProcessorManager::has('generate_csv_file')) {
            ProcessorManager::register('generate_csv_file', \Rake\Processor\GenerateCsvFileProcessor::class, [
                'fileName' => 'crawl_results_{{date}}.csv',
                'delimiter' => ',',
                'includeHeader' => true,
            ]);
        }

        // Send Email Notification
        if (!ProcessorManager::has('send_email_notification')) {
            ProcessorManager::register('send_email_notification', \Rake\Processor\SendEmailNotificationProcessor::class, [
                'recipients' => 'admin@example.com',
                'subject' => 'Crawl Finished: New Data Found',
                'body' => 'Data was successfully extracted.',
            ]);
        }
    }

    /**
     * Get example usage for external plugins
     * 
     * @return string
     */
    public static function getExampleUsage(): string
    {
        return <<<'PHP'
// In your plugin, register custom data source:
add_action('crawlflow_register_data_sources', function() {
    \Rake\Manager\DataSourceManager::registerType('my_source', MyDataSource::class);
});

// Register custom processor:
add_action('crawlflow_register_processors', function() {
    \Rake\Manager\ProcessorManager::register('my_processor', MyProcessor::class, [
        'option1' => 'value1',
    ]);
});

// Modify UI data:
add_filter('crawlflow_registered_data_sources', function($dataSources) {
    $dataSources[] = [
        'type' => 'my_source',
        'label' => 'My Custom Source',
        'description' => 'My description',
        'icon' => '🎯',
    ];
    return $dataSources;
});
PHP;
    }
}


