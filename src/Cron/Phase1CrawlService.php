<?php

namespace CrawlFlow\Cron;

use CrawlFlow\Admin\ProjectService;
use CrawlFlow\Cron\Phase1\DataSourceHandlerFactory;
use CrawlFlow\Cron\ProjectCacheService;
use Ramphor\Rake\Rake;

/**
 * Phase 1: Crawl Service
 * Fetch data from sources and save to dpc_rake_data_origins (raw items repository)
 * Also saves references to dpc_rake_data_origins_references
 */
class Phase1CrawlService
{
    /**
     * @var ProjectCacheService
     */
    private ProjectCacheService $projectCacheService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->projectCacheService = new ProjectCacheService();
    }

    /**
     * Execute Phase 1: Crawl and fetch data
     * 
     * @param int $projectId Project ID
     * @return array Execution results
     */
    public function execute(int $projectId): array
    {
        try {
            error_log("CrawlFlow Phase 1: Starting crawl for project {$projectId}");

            // Load project (cached)
            $project = $this->projectCacheService->getProject($projectId);
            if (!$project) {
                throw new \RuntimeException("Project {$projectId} not found");
            }

            // Get flow config (cached)
            $flowConfig = $this->projectCacheService->getFlowConfig($projectId);
            if (!$flowConfig) {
                throw new \RuntimeException("No flow config for project {$projectId}");
            }

            // Get data sources from config
            $dataSources = $this->getDataSources($projectId, $flowConfig);
            
            $results = [
                'project_id' => $projectId,
                'phase' => 'crawl',
                'sources_processed' => 0,
                'items_saved' => 0,
                'references_saved' => 0,
                'errors' => [],
            ];

            // Process each data source
            foreach ($dataSources as $source) {
                try {
                    $sourceResult = $this->processDataSource($projectId, $source, $flowConfig);
                    $results['sources_processed']++;
                    $results['items_saved'] += $sourceResult['items_saved'] ?? 0;
                    $results['references_saved'] += $sourceResult['references_saved'] ?? 0;
                    
                    // Merge errors if any
                    if (isset($sourceResult['errors']) && !empty($sourceResult['errors'])) {
                        foreach ($sourceResult['errors'] as $error) {
                            $results['errors'][] = [
                                'source' => $source['name'] ?? 'unknown',
                                'error' => is_string($error) ? $error : json_encode($error),
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'source' => $source['name'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ];
                    error_log("CrawlFlow Phase 1: Error processing source - " . $e->getMessage());
                }
            }

            error_log(sprintf(
                "CrawlFlow Phase 1: Completed for project %d - Sources: %d, Items: %d, References: %d",
                $projectId,
                $results['sources_processed'],
                $results['items_saved'],
                $results['references_saved']
            ));

            return $results;

        } catch (\Exception $e) {
            error_log("CrawlFlow Phase 1: Failed for project {$projectId} - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get data sources for project
     */
    private function getDataSources(int $projectId, array $flowConfig): array
    {
        global $wpdb;
        $sources = [];

        // First, check flow config for data source nodes (priority)
        $nodes = $flowConfig['nodes'] ?? [];
        foreach ($nodes as $node) {
            if ($node['type'] === 'dataSource' || $node['type'] === 'start') {
                $nodeData = $node['data'] ?? [];
                
                // Check for URL source
                if (isset($nodeData['url']) && !empty($nodeData['url'])) {
                    $sources[] = [
                        'id' => null, // New source from flow config
                        'type' => 'url',
                        'name' => $nodeData['label'] ?? $nodeData['url'] ?? 'Data Source',
                        'config' => json_encode([
                            'url' => $nodeData['url'],
                            'scope' => $nodeData['scope'] ?? 'current-url',
                            'domainWhitelist' => $nodeData['domainWhitelist'] ?? [],
                        ]),
                    ];
                }
            }
        }

        // If no sources from flow config, get from database
        if (empty($sources)) {
            $sourcesTable = $wpdb->prefix . 'rake_data_sources';
            $dbSources = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$sourcesTable} WHERE tooth_id = %d", $projectId),
                ARRAY_A
            );
            $sources = $dbSources ?: [];
        }

        return $sources;
    }

    /**
     * Process a data source using appropriate handler
     */
    private function processDataSource(int $projectId, array $source, array $flowConfig): array
    {
        $sourceType = $source['type'] ?? 'url';
        
        // Get handler for this source type
        $handler = DataSourceHandlerFactory::getHandler($sourceType);
        
        if (!$handler) {
            error_log("CrawlFlow Phase 1: No handler available for source type: {$sourceType}");
            return [
                'items_saved' => 0,
                'references_saved' => 0,
                'errors' => ["No handler available for source type: {$sourceType}"],
            ];
        }

        // Delegate to handler
        return $handler->process($projectId, $source, $flowConfig);
    }

}

