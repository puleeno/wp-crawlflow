<?php

namespace CrawlFlow\Admin;

use Rake\Manager\DataSourceManager;
use Rake\Manager\ProcessorManager;
use Rake\Manager\ParserManager;
use Rake\Manager\HttpClientManager;

/**
 * Registry Service
 * Provides registry data for CrawlFlow UI
 * Collects registered data sources, processors, and parsers
 */
class RegistryService
{
    /**
     * Get all registered data for UI
     * 
     * @return array
     */
    public function getAllRegistryData(): array
    {
        return [
            'dataSources' => $this->getDataSources(),
            'processors' => $this->getProcessors(),
            'parsers' => $this->getParsers(),
            'httpClients' => $this->getHttpClients(),
        ];
    }

    /**
     * Get registered data sources
     * 
     * @return array
     */
    public function getDataSources(): array
    {
        $types = DataSourceManager::getRegisteredTypes();
        $dataSources = [];

        foreach ($types as $type) {
            // Allow filtering/modification via hook
            $sourceData = apply_filters('crawlflow_data_source_ui_data', [
                'type' => $type,
                'label' => $this->getDataSourceLabel($type),
                'description' => $this->getDataSourceDescription($type),
                'icon' => $this->getDataSourceIcon($type),
                'configFields' => $this->getDataSourceConfigFields($type),
            ], $type);

            $dataSources[] = $sourceData;
        }

        // Allow external plugins to add more data sources
        return apply_filters('crawlflow_registered_data_sources', $dataSources);
    }

    /**
     * Get registered processors
     * 
     * @return array
     */
    public function getProcessors(): array
    {
        $types = ProcessorManager::getRegisteredTypes();
        $processors = [];

        foreach ($types as $type) {
            // Allow filtering/modification via hook
            $processorData = apply_filters('crawlflow_processor_ui_data', [
                'type' => $type,
                'label' => $this->getProcessorLabel($type),
                'description' => $this->getProcessorDescription($type),
                'icon' => $this->getProcessorIcon($type),
                'configFields' => $this->getProcessorConfigFields($type),
            ], $type);

            $processors[] = $processorData;
        }

        // Allow external plugins to add more processors
        return apply_filters('crawlflow_registered_processors', $processors);
    }

    /**
     * Get registered parsers
     * 
     * @return array
     */
    public function getParsers(): array
    {
        // For now, parsers are configured per-worker
        // Return available parser types
        $parsers = [
            [
                'type' => 'html',
                'label' => 'HTML Parser',
                'description' => 'Extract data from HTML using CSS selectors',
                'icon' => '🌐',
            ],
            [
                'type' => 'json',
                'label' => 'JSON Parser',
                'description' => 'Parse JSON data',
                'icon' => '📊',
            ],
            [
                'type' => 'xml',
                'label' => 'XML Parser',
                'description' => 'Parse XML data',
                'icon' => '📄',
            ],
        ];

        // Allow external plugins to add more parsers
        return apply_filters('crawlflow_registered_parsers', $parsers);
    }

    /**
     * Get data source label
     */
    private function getDataSourceLabel(string $type): string
    {
        $labels = [
            'url' => 'From URL',
            'api' => 'From API',
            'csv' => 'From CSV',
            'xml' => 'From XML',
            'mysql' => 'From MySQL',
        ];

        return $labels[$type] ?? 'From ' . ucfirst($type);
    }

    /**
     * Get data source description
     */
    private function getDataSourceDescription(string $type): string
    {
        $descriptions = [
            'url' => 'Crawl and extract data from web URLs',
            'api' => 'Fetch data from REST API endpoints',
            'csv' => 'Import and parse CSV files',
            'xml' => 'Parse XML feeds, sitemaps, and files',
            'mysql' => 'Query data from MySQL databases',
        ];

        return $descriptions[$type] ?? 'Data source: ' . $type;
    }

    /**
     * Get data source icon
     */
    private function getDataSourceIcon(string $type): string
    {
        $icons = [
            'url' => '🌐',
            'api' => '☁️',
            'csv' => '📊',
            'xml' => '📄',
            'mysql' => '🗄️',
        ];

        return $icons[$type] ?? '📦';
    }

    /**
     * Get data source config fields
     * Loads from data source class if available, otherwise returns empty array
     */
    private function getDataSourceConfigFields(string $type): array
    {
        try {
            $className = DataSourceManager::getRegisteredClass($type);
            
            if ($className && class_exists($className)) {
                // Check if class has static method getDataSourceConfigFields
                if (method_exists($className, 'getDataSourceConfigFields')) {
                    $fields = call_user_func([$className, 'getDataSourceConfigFields']);
                    
                    // Ensure fields are in correct format
                    return is_array($fields) ? $fields : [];
                }
            }
        } catch (\Exception $e) {
            // Log error but don't break UI
            error_log('Error loading data source config fields for ' . $type . ': ' . $e->getMessage());
        }

        // Return empty array if no config fields found
        return [];
    }

    /**
     * Get processor label
     */
    private function getProcessorLabel(string $type): string
    {
        $labels = [
            'save_to_wordpress' => 'Save to WordPress',
            'wordpress_post' => 'WordPress Post',
            'wp_post' => 'WP Post',
            'save_to_database' => 'Save to Database',
            'send_to_api' => 'Send to API',
            'generate_csv_file' => 'Generate CSV File',
            'send_email_notification' => 'Send Email Notification',
            'transform' => 'Transform Data',
            'filter' => 'Filter Data',
        ];

        return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * Get processor description
     */
    private function getProcessorDescription(string $type): string
    {
        $descriptions = [
            'save_to_wordpress' => 'Save extracted data as WordPress posts',
            'save_to_database' => 'Save data to external MySQL/PostgreSQL database',
            'send_to_api' => 'Send data to external API endpoint',
            'generate_csv_file' => 'Export data to CSV file',
            'send_email_notification' => 'Send email notification with extracted data',
            'transform' => 'Transform data using mapping rules',
            'filter' => 'Filter data based on conditions',
        ];

        return $descriptions[$type] ?? 'Process data: ' . $type;
    }

    /**
     * Get processor icon
     */
    private function getProcessorIcon(string $type): string
    {
        $icons = [
            'save_to_wordpress' => '💾',
            'save_to_database' => '🗄️',
            'send_to_api' => '🌐',
            'generate_csv_file' => '📊',
            'send_email_notification' => '📧',
            'transform' => '🔄',
            'filter' => '🔍',
        ];

        return $icons[$type] ?? '⚙️';
    }

    /**
     * Get processor config fields
     */
    private function getProcessorConfigFields(string $type): array
    {
        $fields = [
            'save_to_wordpress' => [
                [
                    'name' => 'postType', 
                    'type' => 'select', 
                    'label' => 'Post Type', 
                    'options' => ['post' => 'Post', 'page' => 'Page'], 
                    'default' => 'post',
                ],
                [
                    'name' => 'postStatus', 
                    'type' => 'select', 
                    'label' => 'Post Status', 
                    'options' => ['draft' => 'Draft', 'publish' => 'Publish'], 
                    'default' => 'draft',
                ],
            ],
            'save_to_database' => [
                [
                    'name' => 'connectionType', 
                    'type' => 'select', 
                    'label' => 'Database Type', 
                    'options' => ['mysql' => 'MySQL', 'postgresql' => 'PostgreSQL'], 
                    'default' => 'mysql',
                ],
                [
                    'name' => 'host', 
                    'type' => 'text', 
                    'label' => 'Host', 
                    'default' => 'localhost',
                    'placeholder' => 'localhost',
                ],
                [
                    'name' => 'port', 
                    'type' => 'text', 
                    'label' => 'Port', 
                    'default' => '3306',
                    'placeholder' => '3306',
                ],
                [
                    'name' => 'user', 
                    'type' => 'text', 
                    'label' => 'Username', 
                    'default' => 'root',
                    'placeholder' => 'root',
                ],
                [
                    'name' => 'password', 
                    'type' => 'password', 
                    'label' => 'Password', 
                    'default' => '',
                    'placeholder' => 'Enter database password',
                ],
                [
                    'name' => 'database', 
                    'type' => 'text', 
                    'label' => 'Database Name', 
                    'default' => 'scraped_data', 
                    'required' => true,
                    'placeholder' => 'scraped_data',
                ],
                [
                    'name' => 'tableName', 
                    'type' => 'text', 
                    'label' => 'Table Name', 
                    'default' => 'results', 
                    'required' => true,
                    'placeholder' => 'results',
                ],
                [
                    'name' => 'conflictStrategy', 
                    'type' => 'select', 
                    'label' => 'Duplicate Handling', 
                    'options' => [
                        'insert' => 'Insert (Fail on Duplicate)', 
                        'upsert' => 'Upsert (Update on Duplicate)', 
                        'skip' => 'Skip on Duplicate'
                    ], 
                    'default' => 'upsert',
                ],
            ],
            'send_to_api' => [
                [
                    'name' => 'endpointUrl', 
                    'type' => 'url', 
                    'label' => 'API Endpoint URL', 
                    'required' => true, 
                    'placeholder' => 'https://api.example.com/data',
                    'description' => 'The REST API endpoint to send extracted data',
                ],
                [
                    'name' => 'method', 
                    'type' => 'select', 
                    'label' => 'HTTP Method', 
                    'options' => ['POST' => 'POST', 'PUT' => 'PUT', 'PATCH' => 'PATCH'], 
                    'default' => 'POST',
                ],
                [
                    'name' => 'authType', 
                    'type' => 'select', 
                    'label' => 'Authentication Type', 
                    'options' => [
                        'none' => 'None', 
                        'api-key' => 'API Key', 
                        'bearer' => 'Bearer Token', 
                        'basic' => 'Basic Auth'
                    ], 
                    'default' => 'none',
                ],
            ],
            'generate_csv_file' => [
                [
                    'name' => 'fileName', 
                    'type' => 'text', 
                    'label' => 'File Name Pattern', 
                    'default' => 'crawl_results_{{date}}.csv', 
                    'placeholder' => 'crawl_results_{{date}}.csv',
                    'description' => 'Use {{date}}, {{datetime}}, {{timestamp}} placeholders',
                ],
                [
                    'name' => 'delimiter', 
                    'type' => 'select', 
                    'label' => 'Delimiter', 
                    'options' => [
                        ',' => 'Comma (,)', 
                        ';' => 'Semicolon (;)', 
                        '\t' => 'Tab'
                    ], 
                    'default' => ',',
                ],
                [
                    'name' => 'includeHeader', 
                    'type' => 'checkbox', 
                    'label' => 'Include Header Row', 
                    'default' => true,
                    'placeholder' => 'Add column names as first row',
                ],
            ],
            'send_email_notification' => [
                [
                    'name' => 'recipients', 
                    'type' => 'text', 
                    'label' => 'Recipients (comma-separated)', 
                    'required' => true, 
                    'placeholder' => 'admin@example.com, user@example.com',
                    'description' => 'Multiple emails separated by commas',
                ],
                [
                    'name' => 'subject', 
                    'type' => 'text', 
                    'label' => 'Email Subject', 
                    'default' => 'Crawl Finished: New Data Found', 
                    'placeholder' => 'Crawl results for {{url}}',
                    'description' => 'Use {{field_name}} for data placeholders',
                ],
                [
                    'name' => 'body', 
                    'type' => 'textarea', 
                    'label' => 'Email Body', 
                    'default' => 'Data extracted successfully.', 
                    'placeholder' => 'Title: {{title}}\nPrice: {{price}}',
                    'description' => 'Use {{field_name}} to insert extracted data',
                ],
            ],
        ];

        return $fields[$type] ?? [];
    }

    /**
     * Get registered HTTP clients
     * 
     * @return array
     */
    public function getHttpClients(): array
    {
        $clients = HttpClientManager::getClients();
        $httpClients = [];

        foreach (array_keys($clients) as $name) {
            // Allow filtering/modification via hook
            $clientData = apply_filters('crawlflow_http_client_ui_data', [
                'name' => $name,
                'label' => $this->getHttpClientLabel($name),
                'description' => $this->getHttpClientDescription($name),
                'icon' => $this->getHttpClientIcon($name),
            ], $name);

            $httpClients[] = $clientData;
        }

        // Allow external plugins to add more HTTP clients
        return apply_filters('crawlflow_registered_http_clients', $httpClients);
    }

    /**
     * Get HTTP client label
     */
    private function getHttpClientLabel(string $name): string
    {
        $labels = [
            'wordpress' => 'WordPress HTTP Client',
            'curl' => 'cURL Client',
            'guzzle' => 'Guzzle HTTP Client',
            'default' => 'Default HTTP Client',
        ];

        return $labels[$name] ?? ucfirst(str_replace('_', ' ', $name));
    }

    /**
     * Get HTTP client description
     */
    private function getHttpClientDescription(string $name): string
    {
        $descriptions = [
            'wordpress' => 'Uses WordPress wp_remote_request() for HTTP requests',
            'curl' => 'Uses PHP cURL extension for HTTP requests',
            'guzzle' => 'Uses Guzzle HTTP library for requests',
            'default' => 'Default HTTP client implementation',
        ];

        return $descriptions[$name] ?? 'HTTP client: ' . $name;
    }

    /**
     * Get HTTP client icon
     */
    private function getHttpClientIcon(string $name): string
    {
        $icons = [
            'wordpress' => '🔌',
            'curl' => '🌐',
            'guzzle' => '⚡',
            'default' => '📡',
        ];

        return $icons[$name] ?? '🔗';
    }
}


