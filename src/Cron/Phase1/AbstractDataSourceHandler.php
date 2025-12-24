<?php

namespace CrawlFlow\Cron\Phase1;

use CrawlFlow\Cron\WorkerCacheService;
use CrawlFlow\Reception\Reception;
use CrawlFlow\Worker\Worker;

/**
 * Abstract Data Source Handler for Phase 1
 * 
 * Each data source type (URL, RSS, API, etc.) should have its own handler
 * that extends this abstract class and implements the required methods.
 */
abstract class AbstractDataSourceHandler
{
    /**
     * Cache whether rake_data_origins has is_archive column
     */
    private static ?bool $hasIsArchiveColumn = null;

    /**
     * Check if origins table has is_archive column (cached)
     */
    private function originsHasIsArchiveColumn(): bool
    {
        if (self::$hasIsArchiveColumn !== null) {
            return self::$hasIsArchiveColumn;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'rake_data_origins';
        $col = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'is_archive'",
            $table
        ));
        self::$hasIsArchiveColumn = ((int) $col) > 0;
        return self::$hasIsArchiveColumn;
    }
    /**
     * Get the data source type this handler supports
     * 
     * @return string Data source type (e.g., 'url', 'rss', 'api')
     */
    abstract public function getSupportedType(): string;

    /**
     * Process the data source and extract URLs
     * 
     * @param int $projectId Project ID
     * @param array $source Data source configuration
     * @param array $flowConfig Full flow configuration
     * @return array Result with keys: items_saved, references_saved, errors
     */
    abstract public function process(int $projectId, array $source, array $flowConfig): array;

    /**
     * Normalize pattern array - ensure it's a valid array and filter empty values
     * 
     * @param array|mixed $patterns Patterns array
     * @return array Normalized patterns array
     */
    protected function normalizePatternArray($patterns): array
    {
        if (!is_array($patterns)) {
            return [];
        }
        
        // Filter out empty values and ensure all items are strings
        $normalized = [];
        foreach ($patterns as $pattern) {
            if (is_string($pattern) && !empty(trim($pattern))) {
                $normalized[] = $pattern;
            }
        }
        
        return $normalized;
    }

    /**
     * Get URL settings from data source config
     * 
     * @param array $source Data source configuration
     * @return array URL settings (excludeExtensions, excludePatterns, whitelistPatterns, etc.)
     */
    protected function getUrlSettingsFromSourceConfig(array $source): array
    {
        $configJson = $source['config'] ?? null;
        if (empty($configJson)) {
            \Rake\Facade\Logger::warning("CrawlFlow Phase 1: No config found in data source");
            return [];
        }
        
        $config = json_decode($configJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            \Rake\Facade\Logger::error("CrawlFlow Phase 1: Failed to parse source config JSON: " . json_last_error_msg());
            return [];
        }
        
        // Extract URL settings from config
        $urlSettings = [
            'excludeExtensions' => $config['excludeExtensions'] ?? [],
            'excludePatterns' => $this->normalizePatternArray($config['excludePatterns'] ?? []),
            'whitelistPatterns' => $this->normalizePatternArray($config['whitelistPatterns'] ?? []),
            'domainPolicy' => $config['domainPolicy'] ?? 'all',
            'domainWhitelist' => $config['domainWhitelist'] ?? [],
        ];
        
        return $urlSettings;
    }

    /**
     * Detect worker priority for a URL
     * 
     * @param int $projectId Project ID
     * @param array $flowConfig Flow configuration
     * @param string $url URL to check
     * @return int Priority (default: 100 if no worker matches)
     */
    protected function detectWorkerPriority(int $projectId, array $flowConfig, string $url): int
    {
        try {
            $workerCacheService = new WorkerCacheService();
            $reception = $workerCacheService->getReception($projectId, $flowConfig);
            $workers = $reception->getWorkers();
            
            // Create a mock raw item for detection
            $mockRawItem = [
                'id' => 0,
                'guid' => $url,
                'raw_data' => '',
            ];
            
            // Check each worker (already sorted by priority)
            foreach ($workers as $worker) {
                if ($worker->canHandle($mockRawItem)) {
                    return $worker->getPriority();
                }
            }
        } catch (\Exception $e) {
            \Rake\Facade\Logger::error("CrawlFlow Phase 1: Error detecting worker priority for URL {$url}: " . $e->getMessage());
        }
        
        // Default priority if no worker matches
        return 100;
    }

    /**
     * Detect archive flag for a URL based on the first matched worker
     *
     * @return int 1 if archive, 0 otherwise
     */
    protected function detectIsArchive(int $projectId, array $flowConfig, string $url): int
    {
        try {
            $workerCacheService = new WorkerCacheService();
            $reception = $workerCacheService->getReception($projectId, $flowConfig);
            $workers = $reception->getWorkers();

            $mockRawItem = [
                'id' => 0,
                'guid' => $url,
                'raw_data' => '',
            ];

            foreach ($workers as $worker) {
                if ($worker->canHandle($mockRawItem)) {
                    return $worker->isArchive() ? 1 : 0;
                }
            }
        } catch (\Exception $e) {
            \Rake\Facade\Logger::error("CrawlFlow Phase 1: Error detecting archive flag for URL {$url}: " . $e->getMessage());
        }

        return 0;
    }

    /**
     * Save data to rake_data_origins
     * 
     * @param int $projectId Project ID
     * @param int|null $sourceId Source ID
     * @param string $guid URL/guid
     * @param string $rawData Raw data content
     * @param array $metadata Optional metadata (will be JSON encoded)
     * @param array|null $flowConfig Flow configuration (for worker priority detection)
     * @return int Origin ID
     */
    protected function saveToDataOrigins(int $projectId, ?int $sourceId, string $guid, string $rawData, array $metadata = [], ?array $flowConfig = null): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_data_origins';

        // Check if already exists (by source_id and guid combination)
        if ($sourceId !== null && $sourceId > 0) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE source_id = %d AND guid = %s",
                $sourceId,
                $guid
            ));
        } else {
            // For records without source_id, check only guid
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE source_id IS NULL AND guid = %s",
                $guid
            ));
        }

        $now = current_time('mysql');
        // Phase 1 does not change crawled or ignored flags for existing records
        // Only set crawled when inserting new records with raw_data
        $metadataJson = !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        
        // Detect worker priority/is_archive if flowConfig is provided and guid is a URL
        $priority = 100; // Default priority
        $isArchive = 0;
        if ($flowConfig !== null && !empty($guid) && filter_var($guid, FILTER_VALIDATE_URL)) {
            $priority = $this->detectWorkerPriority($projectId, $flowConfig, $guid);
            if ($this->originsHasIsArchiveColumn()) {
                $isArchive = $this->detectIsArchive($projectId, $flowConfig, $guid);
            }
        }

        if ($existing) {
            // Update existing record
            // Phase 1 does NOT update raw_data, crawled, or ignored flags
            // Phase 1 only updates: priority, metadata, updated_at
            $updateData = [
                'updated_at' => $now,
            ];
            
            // Phase 1 does not update raw_data - Phase 2 will fetch it
            
            // Update metadata if provided
            if (!empty($metadata)) {
                $updateData['metadata'] = $metadataJson;
            }
            
            // Update priority if flowConfig is provided
            if ($flowConfig !== null && !empty($guid) && filter_var($guid, FILTER_VALIDATE_URL)) {
                $updateData['priority'] = $priority;
                if ($this->originsHasIsArchiveColumn()) {
                    $updateData['is_archive'] = $isArchive;
                }
            }
            
            $wpdb->update(
                $table,
                $updateData,
                ['id' => $existing]
            );
            return (int)$existing;
        }

        // Insert new
        // Phase 1 does not set crawled or ignored flags - use default values from schema
        // Phase 2 will manage crawled flag after fetching/processing
        $insertData = [
            'guid' => $guid,
            'raw_data' => $rawData,
            'fetched_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
            // crawled will use default value 0 from schema (Phase 2 will set it)
            'metadata' => $metadataJson,
            'source_type' => 'data_source',
            'processor_id' => null, // Data source doesn't have processor_id
            'priority' => $priority,
            // ignored will use default value 0 from schema
        ];

        if ($this->originsHasIsArchiveColumn()) {
            $insertData['is_archive'] = $isArchive;
        }
        
        // Only include source_id if it's not null and exists in database
        if ($sourceId !== null && $sourceId > 0) {
            // Verify source_id exists in database before inserting
            $sourceExists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}rake_data_sources WHERE id = %d",
                $sourceId
            ));
            
            if ($sourceExists) {
                $insertData['source_id'] = $sourceId;
            } else {
                \Rake\Facade\Logger::warning("CrawlFlow Phase 1: Source ID {$sourceId} does not exist in database, skipping source_id in origin insert");
            }
        }
        
        $result = $wpdb->insert($table, $insertData);

        if ($result === false) {
            \Rake\Facade\Logger::error("CrawlFlow Phase 1: Failed to insert origin - " . $wpdb->last_error);
            return 0;
        }

        $insertId = (int)$wpdb->insert_id;
        if ($insertId <= 0) {
            \Rake\Facade\Logger::error("CrawlFlow Phase 1: Failed to get insert ID for origin");
            return 0;
        }

        return $insertId;
    }

    /**
     * Save reference relationship
     * 
     * @param int $parentOriginId Parent origin ID
     * @param int $childOriginId Child origin ID
     * @param string $relationshipType Relationship type (default: 'child')
     * @return bool Success
     */
    protected function saveReference(int $parentOriginId, int $childOriginId, string $relationshipType = 'child'): bool
    {
        // Validate parent and child IDs - must be > 0 to satisfy foreign key constraints
        if ($parentOriginId <= 0 || $childOriginId <= 0) {
            \Rake\Facade\Logger::error("CrawlFlow Phase 1: Cannot save reference - invalid IDs (parent: {$parentOriginId}, child: {$childOriginId})");
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'rake_data_origins_references';

        // Verify parent origin exists in database
        $parentExists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}rake_data_origins WHERE id = %d",
            $parentOriginId
        ));

        if (!$parentExists) {
            \Rake\Facade\Logger::error("CrawlFlow Phase 1: Cannot save reference - parent origin ID {$parentOriginId} does not exist");
            return false;
        }

        // Verify child origin exists in database
        $childExists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}rake_data_origins WHERE id = %d",
            $childOriginId
        ));

        if (!$childExists) {
            \Rake\Facade\Logger::error("CrawlFlow Phase 1: Cannot save reference - child origin ID {$childOriginId} does not exist");
            return false;
        }

        // Check if reference already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE parent_origin_id = %d AND child_origin_id = %d AND relationship_type = %s",
            $parentOriginId,
            $childOriginId,
            $relationshipType
        ));

        if ($existing) {
            return true; // Already exists
        }

        // Insert new reference
        $result = $wpdb->insert($table, [
            'parent_origin_id' => $parentOriginId,
            'child_origin_id' => $childOriginId,
            'relationship_type' => $relationshipType,
            'created_at' => current_time('mysql'),
        ]);

        if ($result === false) {
            \Rake\Facade\Logger::error("CrawlFlow Phase 1: Failed to insert reference - " . $wpdb->last_error);
            return false;
        }

        return (int)$wpdb->insert_id > 0;
    }

    /**
     * Ensure data source exists in database
     * 
     * @param int $projectId Project ID
     * @param array $source Data source configuration
     * @return int Source ID (0 if failed)
     */
    protected function ensureDataSourceInDb(int $projectId, array $source): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_data_sources';

        // If source already has ID, verify it exists in database
        if (isset($source['id']) && !empty($source['id'])) {
            $sourceId = (int)$source['id'];
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %d",
                $sourceId
            ));
            
            if ($exists) {
                return $sourceId;
            } else {
                \Rake\Facade\Logger::info("CrawlFlow Phase 1: Source ID {$sourceId} from source config does not exist in database, will create new");
            }
        }

        // Check if source exists by name and project
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE tooth_id = %d AND name = %s AND type = %s",
            $projectId,
            $source['name'] ?? 'Unknown',
            $source['type'] ?? 'url'
        ));

        if ($existing) {
            return (int)$existing;
        }

        // Create new source
        $result = $wpdb->insert($table, [
            'tooth_id' => $projectId,
            'type' => $source['type'] ?? 'url',
            'name' => $source['name'] ?? 'Data Source',
            'config' => $source['config'] ?? '{}',
            'created_at' => current_time('mysql'),
        ]);

        if ($result === false) {
            \Rake\Facade\Logger::error("CrawlFlow Phase 1: Failed to create data source - " . $wpdb->last_error);
            return 0;
        }

        $insertId = (int)$wpdb->insert_id;
        if ($insertId <= 0) {
            \Rake\Facade\Logger::error("CrawlFlow Phase 1: Failed to get insert ID for data source");
            return 0;
        }

        return $insertId;
    }
}

