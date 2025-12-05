<?php

namespace CrawlFlow\Hooks;

use Rake\Manager\DataSourceManager;
use Rake\Manager\ProcessorManager;
use Rake\Manager\CompletionActionManager;
use Rake\DataSource\UrlDataSource;
use Rake\DataSource\ApiDataSource;
use Rake\DataSource\CsvDataSource;
use Rake\DataSource\XmlDataSource;
use Rake\DataSource\MySqlDataSource;
use Rake\CompletionAction\ReportingAction;
use Rake\CompletionAction\SendEmailNotificationAction;
use Rake\CompletionAction\WebhookAction;
use Rake\CompletionAction\CreateResourceReferencesAction;
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

        // Hook to register completion actions
        do_action('crawlflow_register_completion_actions');

        // Register default types
        self::registerDefaults();
    }

    /**
     * Register default data sources, processors, parsers
     */
    private static function registerDefaults(): void
    {
        // Register built-in data source types
        self::registerBuiltInDataSources();

        // Register built-in Rake processors
        self::registerBuiltInProcessors();

        // Register built-in completion actions
        self::registerBuiltInCompletionActions();

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
     * Register built-in data source types
     */
    private static function registerBuiltInDataSources(): void
    {
        if (!DataSourceManager::hasType('url')) {
            DataSourceManager::registerType('url', UrlDataSource::class);
        }

        if (!DataSourceManager::hasType('api')) {
            DataSourceManager::registerType('api', ApiDataSource::class);
        }

        if (!DataSourceManager::hasType('csv')) {
            DataSourceManager::registerType('csv', CsvDataSource::class);
        }

        if (!DataSourceManager::hasType('xml')) {
            DataSourceManager::registerType('xml', XmlDataSource::class);
        }

        if (!DataSourceManager::hasType('mysql')) {
            DataSourceManager::registerType('mysql', MySqlDataSource::class);
        }
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
     * Register built-in completion actions
     */
    private static function registerBuiltInCompletionActions(): void
    {
        // Reporting action
        if (!CompletionActionManager::hasAction('reporting')) {
            CompletionActionManager::register(
                'reporting',
                new ReportingAction(),
                [
                    'label' => 'Generate Report',
                    'description' => 'Generate and log summary report',
                    'icon' => '📊',
                    'category' => 'reporting',
                    'enabled' => true
                ]
            );
        }

        // Send email notification action
        if (!CompletionActionManager::hasAction('send_email_notification')) {
            CompletionActionManager::register(
                'send_email_notification',
                new SendEmailNotificationAction(),
                [
                    'label' => 'Send Email Notification',
                    'description' => 'Send email when crawl completes',
                    'icon' => '📧',
                    'category' => 'notification',
                    'enabled' => true
                ]
            );
        }

        // Webhook action
        if (!CompletionActionManager::hasAction('webhook')) {
            CompletionActionManager::register(
                'webhook',
                new WebhookAction(),
                [
                    'label' => 'Webhook',
                    'description' => 'Send HTTP POST to webhook URL',
                    'icon' => '🔗',
                    'category' => 'integration',
                    'enabled' => true
                ]
            );
        }

        // Create Resource References action (with factory for dependency injection)
        if (!CompletionActionManager::hasAction('create_resource_references')) {
            CompletionActionManager::register(
                'create_resource_references',
                function() {
                    // Resolve dependencies from container
                    $app = \Rake\Rake::getInstance();
                    
                    $fileDownloader = $app->make(\Rake\Contracts\File\FileDownloaderClientInterface::class);
                    $databaseAdapter = $app->make(\Rake\Contracts\Database\Adapter\DatabaseAdapterInterface::class);
                    $checksumManager = new \Rake\Manager\FileChecksumManager($databaseAdapter);
                    
                    return new CreateResourceReferencesAction(
                        $fileDownloader,
                        $checksumManager,
                        $databaseAdapter
                    );
                },
                [
                    'label' => 'Create Resource References',
                    'description' => 'Import media files with checksum deduplication and URL mapping',
                    'icon' => '📁',
                    'category' => 'media',
                    'enabled' => true
                ]
            );
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

// Register custom completion action:
add_action('crawlflow_register_completion_actions', function() {
    \Rake\Manager\CompletionActionManager::register(
        'my_action',
        new MyCompletionAction(),
        [
            'label' => 'My Action',
            'description' => 'My custom action',
            'icon' => '🎯',
            'category' => 'custom'
        ]
    );
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


