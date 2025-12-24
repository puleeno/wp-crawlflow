<?php

namespace CrawlFlow\Cron;

use CrawlFlow\Admin\ProjectService;

/**
 * Project Cache Service
 * Cache project data by project ID to avoid repeated database queries
 * Especially useful when project is called from multiple wp-cron.php requests
 */
class ProjectCacheService
{
    /**
     * @var array Cache of project data by project ID
     */
    private static array $projectCache = [];

    /**
     * @var array Cache of flow config by project ID
     */
    private static array $flowConfigCache = [];

    /**
     * @var array Cache of data sources by project ID
     */
    private static array $dataSourcesCache = [];

    /**
     * @var ProjectService
     */
    private ProjectService $projectService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $rake = \Rake\Rake::getInstance();
        $this->projectService = $rake->make('CrawlFlow\Admin\ProjectService');
    }

    /**
     * Get project data (cached)
     * 
     * @param int $projectId Project ID
     * @return array|null Project data or null if not found
     */
    public function getProject(int $projectId): ?array
    {
        // Check cache first
        if (isset(self::$projectCache[$projectId])) {
            \Rake\Facade\Logger::debug("CrawlFlow ProjectCache: Using cached project data for project {$projectId}");
            return self::$projectCache[$projectId];
        }

        // Load from database
        $project = $this->projectService->getProject($projectId);
        
        if ($project) {
            // Cache it
            self::$projectCache[$projectId] = $project;
            \Rake\Facade\Logger::debug("CrawlFlow ProjectCache: Cached project data for project {$projectId}");
        }

        return $project;
    }

    /**
     * Get flow config (cached)
     * 
     * @param int $projectId Project ID
     * @return array|null Flow config or null if not found
     */
    public function getFlowConfig(int $projectId): ?array
    {
        // Check cache first
        if (isset(self::$flowConfigCache[$projectId])) {
            \Rake\Facade\Logger::debug("CrawlFlow ProjectCache: Using cached flow config for project {$projectId}");
            return self::$flowConfigCache[$projectId];
        }

        // Load from database
        $flowConfig = $this->projectService->getFlowConfig($projectId);
        
        if ($flowConfig) {
            // Cache it
            self::$flowConfigCache[$projectId] = $flowConfig;
            \Rake\Facade\Logger::debug("CrawlFlow ProjectCache: Cached flow config for project {$projectId}");
        }

        return $flowConfig;
    }

    /**
     * Get data sources (cached)
     * Note: This is a helper method that combines project and flow config
     * 
     * @param int $projectId Project ID
     * @return array Data sources
     */
    public function getDataSources(int $projectId): array
    {
        // Check cache first
        if (isset(self::$dataSourcesCache[$projectId])) {
            \Rake\Facade\Logger::debug("CrawlFlow ProjectCache: Using cached data sources for project {$projectId}");
            return self::$dataSourcesCache[$projectId];
        }

        // Get flow config (will use cache if available)
        $flowConfig = $this->getFlowConfig($projectId);
        if (!$flowConfig) {
            return [];
        }

        // Extract data sources from flow config (similar to Phase1CrawlService)
        $sources = [];
        $nodes = $flowConfig['nodes'] ?? [];
        
        foreach ($nodes as $node) {
            if ($node['type'] === 'dataSource' || $node['type'] === 'start') {
                $nodeData = $node['data'] ?? [];
                
                // Check for URL source
                if (isset($nodeData['url']) && !empty($nodeData['url'])) {
                    $sources[] = [
                        'id' => null,
                        'type' => 'url',
                        'name' => $nodeData['label'] ?? $nodeData['url'] ?? 'Data Source',
                        'config' => json_encode([
                            'url' => $nodeData['url'],
                            'scope' => $nodeData['scope'] ?? 'current-url',
                            'domainWhitelist' => $nodeData['domainWhitelist'] ?? [],
                        ]),
                    ];
                } elseif (isset($nodeData['sourceValue']) && !empty($nodeData['sourceValue'])) {
                    $sources[] = [
                        'id' => null,
                        'type' => $nodeData['sourceType'] ?? 'url',
                        'name' => $nodeData['label'] ?? $nodeData['sourceValue'] ?? 'Data Source',
                        'config' => json_encode([
                            'url' => $nodeData['sourceValue'],
                            'scope' => $nodeData['urlSettings']['scope'] ?? 'current-url',
                            'whitelistPatterns' => $nodeData['urlSettings']['whitelistPatterns'] ?? [],
                        ]),
                    ];
                }
            }
        }

        // If no sources from flow config, get from database
        if (empty($sources)) {
            global $wpdb;
            $sourcesTable = $wpdb->prefix . 'rake_data_sources';
            $dbSources = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$sourcesTable} WHERE tooth_id = %d", $projectId),
                ARRAY_A
            );
            $sources = $dbSources ?: [];
        }

        // Cache it
        self::$dataSourcesCache[$projectId] = $sources;
        \Rake\Facade\Logger::debug("CrawlFlow ProjectCache: Cached data sources for project {$projectId}");

        return $sources;
    }

    /**
     * Clear cache for a project
     * Call this when project is updated
     * 
     * @param int $projectId Project ID
     */
    public function clearCache(int $projectId): void
    {
        unset(self::$projectCache[$projectId]);
        unset(self::$flowConfigCache[$projectId]);
        unset(self::$dataSourcesCache[$projectId]);
        \Rake\Facade\Logger::info("CrawlFlow ProjectCache: Cleared cache for project {$projectId}");
    }

    /**
     * Clear all cache
     */
    public function clearAllCache(): void
    {
        self::$projectCache = [];
        self::$flowConfigCache = [];
        self::$dataSourcesCache = [];
        \Rake\Facade\Logger::info("CrawlFlow ProjectCache: Cleared all cache");
    }

    /**
     * Get cache statistics
     * 
     * @return array Cache statistics
     */
    public function getCacheStats(): array
    {
        return [
            'projects_cached' => count(self::$projectCache),
            'flow_configs_cached' => count(self::$flowConfigCache),
            'data_sources_cached' => count(self::$dataSourcesCache),
        ];
    }
}

