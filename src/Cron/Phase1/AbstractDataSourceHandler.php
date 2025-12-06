<?php

namespace CrawlFlow\Cron\Phase1;

/**
 * Abstract Data Source Handler for Phase 1
 * 
 * Each data source type (URL, RSS, API, etc.) should have its own handler
 * that extends this abstract class and implements the required methods.
 */
abstract class AbstractDataSourceHandler
{
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
     * Get URL settings from data source config
     * 
     * @param array $source Data source configuration
     * @return array URL settings (excludeExtensions, excludePatterns, whitelistPatterns, etc.)
     */
    protected function getUrlSettingsFromSourceConfig(array $source): array
    {
        $configJson = $source['config'] ?? null;
        if (empty($configJson)) {
            error_log("CrawlFlow Phase 1: No config found in data source");
            return [];
        }
        
        $config = json_decode($configJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("CrawlFlow Phase 1: Failed to parse source config JSON: " . json_last_error_msg());
            return [];
        }
        
        // Extract URL settings from config
        $urlSettings = [
            'excludeExtensions' => $config['excludeExtensions'] ?? [],
            'excludePatterns' => $config['excludePatterns'] ?? [],
            'whitelistPatterns' => $config['whitelistPatterns'] ?? [],
            'domainPolicy' => $config['domainPolicy'] ?? 'all',
            'domainWhitelist' => $config['domainWhitelist'] ?? [],
        ];
        
        return $urlSettings;
    }

    /**
     * Save data to rake_data_origins
     * 
     * @param int $projectId Project ID
     * @param int|null $sourceId Source ID
     * @param string $guid URL/guid
     * @param string $rawData Raw data content
     * @param array $metadata Optional metadata (will be JSON encoded)
     * @return int Origin ID
     */
    protected function saveToDataOrigins(int $projectId, ?int $sourceId, string $guid, string $rawData, array $metadata = []): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_data_origins';

        // Check if already exists (by guid, unique)
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE guid = %s",
            $guid
        ));

        $now = current_time('mysql');
        $crawled = !empty($rawData) ? 1 : 0;
        $metadataJson = !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        if ($existing) {
            // Update existing if we have new data
            $updateData = [
                'updated_at' => $now,
            ];
            
            if (!empty($rawData)) {
                $updateData['raw_data'] = $rawData;
                $updateData['fetched_at'] = $now;
                $updateData['crawled'] = 1;
            }
            
            // Update metadata if provided
            if (!empty($metadata)) {
                $updateData['metadata'] = $metadataJson;
            }
            
            $wpdb->update(
                $table,
                $updateData,
                ['id' => $existing]
            );
            return (int)$existing;
        }

        // Insert new
        $wpdb->insert($table, [
            'source_id' => $sourceId,
            'guid' => $guid,
            'raw_data' => $rawData,
            'fetched_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
            'crawled' => $crawled,
            'metadata' => $metadataJson,
        ]);

        return (int)$wpdb->insert_id;
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
        global $wpdb;
        $table = $wpdb->prefix . 'rake_data_origins_references';

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
        $wpdb->insert($table, [
            'parent_origin_id' => $parentOriginId,
            'child_origin_id' => $childOriginId,
            'relationship_type' => $relationshipType,
            'created_at' => current_time('mysql'),
        ]);

        return (int)$wpdb->insert_id > 0;
    }

    /**
     * Ensure data source exists in database
     * 
     * @param int $projectId Project ID
     * @param array $source Data source configuration
     * @return int Source ID
     */
    protected function ensureDataSourceInDb(int $projectId, array $source): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_data_sources';

        // If source already has ID, return it
        if (isset($source['id']) && !empty($source['id'])) {
            return (int)$source['id'];
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
        $wpdb->insert($table, [
            'tooth_id' => $projectId,
            'type' => $source['type'] ?? 'url',
            'name' => $source['name'] ?? 'Data Source',
            'config' => $source['config'] ?? '{}',
            'created_at' => current_time('mysql'),
        ]);

        return (int)$wpdb->insert_id;
    }
}

