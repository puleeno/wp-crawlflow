<?php

/**
 * @suppress PhanUndeclaredClass
 * @suppress PhanUndeclaredClassMethod
 */

namespace CrawlFlow\Cron;

use CrawlFlow\Admin\ProjectService;
use CrawlFlow\Cron\Phase1\DataSourceHandlerFactory;
use CrawlFlow\Cron\ProjectCacheService;
use CrawlFlow\LoggerService;
use Rake\Actions\ContextActionManager; // @suppress PhanUndeclaredClass
use Rake\Actions\ActionContext; // @suppress PhanUndeclaredClass
use Ramphor\Rake\Rake; // @suppress PhanUndeclaredClass
use Rake\Facade\Logger;

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
            \CrawlFlow\LoggerService::setCurrentProjectId($projectId);
            Logger::info("CrawlFlow Phase 1: Starting crawl for project {$projectId}");

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
            Logger::info("CrawlFlow Phase 1: Found " . count($dataSources) . " data sources to process");
            foreach ($dataSources as $source) {
                try {
                    Logger::debug("CrawlFlow Phase 1: Processing source type: " . ($source['type'] ?? 'unknown'));
                    $sourceResult = $this->processDataSource($projectId, $source, $flowConfig);
                    Logger::debug("CrawlFlow Phase 1: Source result: " . json_encode($sourceResult));
                    $results['sources_processed']++;
                    $results['items_saved'] += $sourceResult['items_saved'] ?? 0;
                    $results['references_saved'] += $sourceResult['references_saved'] ?? 0;
                    
                    // Execute data-source scoped Phase1 extra actions (if any)
                    $actionContext = new ActionContext( // @suppress PhanUndeclaredClass
                        'phase1_extra_actions',
                        $projectId,
                        $sourceResult,
                        $flowConfig,
                        ['dataSource' => $source]
                    );
                    $extraActionResults = ContextActionManager::execute('phase1_extra_actions', $actionContext); // @suppress PhanUndeclaredClassMethod
                    if (!isset($results['extra_actions'])) {
                        $results['extra_actions'] = [];
                    }
                    $results['extra_actions'][] = [
                        'data_source' => $source['name'] ?? $source['type'] ?? 'unknown',
                        'actions' => $extraActionResults,
                    ];

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
                    Logger::error("CrawlFlow Phase 1: Error processing source - " . $e->getMessage());
                }
            }

            // Determine execution mode for logging
            $isTestMode = defined('CRAWLFLOW_TEST_CRON') && CRAWLFLOW_TEST_CRON;
            $mode = $isTestMode ? 'TEST MODE' : 'CRON SCHEDULE MODE';

            Logger::info(sprintf(
                "[{$mode}] CrawlFlow Phase 1: Completed for project %d - Sources: %d, Items: %d, References: %d",
                $projectId,
                $results['sources_processed'],
                $results['items_saved'],
                $results['references_saved']
            ));

            // Debug: Check what was actually saved to database
            global $wpdb;
            
            // Check all tables first to understand the schema
            $allTables = $wpdb->get_results("SHOW TABLES", ARRAY_A);
            $tableList = array_map(function($table) { return array_values($table)[0]; }, $allTables);
            Logger::debug("[{$mode}] CrawlFlow Phase 1: All tables in database: " . implode(', ', $tableList));
            
            // Check if origins table exists
            $originsTable = $wpdb->prefix . 'crawlflow_origins';
            $rakeOriginsTable = $wpdb->prefix . 'rake_data_origins';
            
            $tableExists = false;
            $actualTableName = '';
            
            if (in_array($originsTable, $tableList)) {
                $tableExists = true;
                $actualTableName = $originsTable;
            } elseif (in_array($rakeOriginsTable, $tableList)) {
                $tableExists = true;
                $actualTableName = $rakeOriginsTable;
            }
            
            if (!$tableExists) {
                Logger::warning("[{$mode}] CrawlFlow Phase 1: No origins table found");
                return $results;
            }
            
            Logger::info("[{$mode}] CrawlFlow Phase 1: Using table: {$actualTableName}");
            
            // Check table structure
            $tableStructureQuery = "DESCRIBE {$actualTableName}";
            $tableStructure = $wpdb->get_results($tableStructureQuery, ARRAY_A);
            $columnNames = array_map(function($col) { return $col['Field']; }, $tableStructure);
            Logger::debug("[{$mode}] CrawlFlow Phase 1: Table {$actualTableName} columns: " . implode(', ', $columnNames));
            
            // Origins table doesn't have direct project column - it uses JOIN via sources table
            Logger::debug("[{$mode}] CrawlFlow Phase 1: Origins table uses JOIN via sources table - no direct project column needed");
            
            // Check total items in table
            $totalItemsQuery = "SELECT COUNT(*) as count FROM {$actualTableName}";
            $totalItemsCount = $wpdb->get_var($totalItemsQuery);
            
            // Check items by project using JOIN
            $savedItemsQuery = $wpdb->prepare(
                "SELECT COUNT(*) as count FROM {$actualTableName} o 
                 LEFT JOIN {$wpdb->prefix}rake_data_sources s ON o.source_id = s.id 
                 WHERE s.tooth_id = %d",
                $projectId
            );
            $savedItemsCount = $wpdb->get_var($savedItemsQuery);
            
            Logger::info("[{$mode}] CrawlFlow Phase 1: Project {$projectId} - Total items in origins table: {$savedItemsCount}/{$totalItemsCount}");

            // Execute Phase 1 actions
            $actionContext = new ActionContext( // @suppress PhanUndeclaredClass
                'phase1',
                $projectId,
                $results,
                $flowConfig
            );
            
            $actionResults = ContextActionManager::execute('phase1', $actionContext); // @suppress PhanUndeclaredClassMethod
            $results['actions'] = $actionResults;
            
            Logger::info("[{$mode}] CrawlFlow Phase 1: Executed " . count($actionResults) . " action(s)");

            // Log executed action IDs for easy verification (e.g., data_update_checker)
            $actionSummary = [];
            foreach ($actionResults as $actionId => $actionResult) {
                $actionSummary[$actionId] = [
                    'success' => (bool) ($actionResult['success'] ?? false),
                    'error' => $actionResult['error'] ?? null,
                ];
            }
            Logger::info("[{$mode}] CrawlFlow Phase 1: Action results summary: " . json_encode($actionSummary));

            return $results;

        } catch (\Exception $e) {
            Logger::error("CrawlFlow Phase 1: Failed for project {$projectId} - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute Bonus Phase: only Phase 1 context actions (no data source fetching)
     *
     * @param int $projectId
     * @return array
     */
    public function executeBonus(int $projectId): array
    {
        try {
            Logger::info("CrawlFlow Bonus Phase: Starting actions for project {$projectId}");

            $project = $this->projectCacheService->getProject($projectId);
            if (!$project) {
                throw new \RuntimeException("Project {$projectId} not found");
            }

            $flowConfig = $this->projectCacheService->getFlowConfig($projectId);
            if (!$flowConfig) {
                throw new \RuntimeException("No flow config for project {$projectId}");
            }

            // Minimal results baseline
            $results = [
                'project_id' => $projectId,
                'phase' => 'bonus',
                'sources_processed' => 0,
                'items_saved' => 0,
                'references_saved' => 0,
                'errors' => [],
            ];

            // Execute Phase 1 actions only
            $actionContext = new ActionContext( // @suppress PhanUndeclaredClass
                'phase1',
                $projectId,
                $results,
                $flowConfig
            );

            $actionResults = ContextActionManager::execute('phase1', $actionContext); // @suppress PhanUndeclaredClassMethod
            $results['actions'] = $actionResults;

            Logger::info("CrawlFlow Bonus Phase: Executed " . count($actionResults) . " action(s) for project {$projectId}");

            return $results;
        } catch (\Exception $e) {
            Logger::error("CrawlFlow Bonus Phase: Failed for project {$projectId} - " . $e->getMessage());
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
                $sourceUrl = $nodeData['sourceValue'] ?? $nodeData['url'] ?? null;
                if (empty($sourceUrl) && isset($nodeData['sourceType']) && $nodeData['sourceType'] === 'url') {
                    $sourceUrl = $nodeData['sourceValue'] ?? null;
                }
                
                if (!empty($sourceUrl)) {
                    // Get URL settings from nodeData
                    $urlSettings = $nodeData['urlSettings'] ?? [];
                    
                    $sources[] = [
                        'id' => null, // New source from flow config
                        'type' => $nodeData['sourceType'] ?? 'url',
                        'name' => $nodeData['label'] ?? $sourceUrl ?? 'Data Source',
                        'config' => json_encode([
                            'url' => $sourceUrl,
                            'scope' => $urlSettings['scope'] ?? $nodeData['scope'] ?? 'current-url',
                            'domainWhitelist' => $urlSettings['domainWhitelist'] ?? $nodeData['domainWhitelist'] ?? [],
                            'excludeExtensions' => $urlSettings['excludeExtensions'] ?? [],
                            'excludePatterns' => $urlSettings['excludePatterns'] ?? [],
                            'whitelistPatterns' => $urlSettings['whitelistPatterns'] ?? [],
                            'domainPolicy' => $urlSettings['domainPolicy'] ?? 'all',
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
        Logger::debug("CrawlFlow Phase 1: Getting handler for source type: {$sourceType}");
        
        // Get handler for this source type
        $handler = DataSourceHandlerFactory::getHandler($sourceType);
        
        if (!$handler) {
            Logger::warning("CrawlFlow Phase 1: No handler available for source type: {$sourceType}");
            return [
                'items_saved' => 0,
                'references_saved' => 0,
                'errors' => ["No handler available for source type: {$sourceType}"],
            ];
        }

        Logger::debug("CrawlFlow Phase 1: Handler found: " . get_class($handler));
        // Delegate to handler
        $result = $handler->process($projectId, $source, $flowConfig);
        Logger::debug("CrawlFlow Phase 1: Handler returned: " . json_encode($result));
        return $result;
    }

}

