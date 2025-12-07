<?php

namespace CrawlFlow\Cron;

/**
 * Parsed Item Versioning Service
 * Quản lý parsed item data với versioning
 * - Lưu parsed data từ workers và processors
 * - Quản lý phiên bản (versioning) của parsed item data
 * - Lưu tác nhân đã tạo version (worker, processor 1, processor 2, etc.)
 */
class ParsedItemVersioningService
{
    /**
     * Save parsed item data (creates new version if data changed)
     * 
     * @param int $originId Origin ID
     * @param array $parsedData Parsed data
     * @param string $processorType Type: 'worker', 'processor', 'extractor'
     * @param string $processorId ID/name of processor
     * @param string $processorName Human-readable name
     * @param array $metadata Optional metadata
     * @return int Version number
     */
    public function saveParsedItem(
        int $originId,
        array $parsedData,
        string $processorType,
        string $processorId,
        string $processorName = '',
        array $metadata = []
    ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_data_parsed_items';

        // Get latest version for this origin
        $latestVersion = $this->getLatestVersion($originId);
        $latestData = $this->getVersionData($originId, $latestVersion);

        // Check if data has changed
        $dataChanged = $this->hasDataChanged($latestData, $parsedData);

        if (!$dataChanged && $latestVersion > 0) {
            // Data unchanged, return existing version
            error_log("CrawlFlow ParsedItem: Origin {$originId} - Data unchanged, keeping version {$latestVersion}");
            return $latestVersion;
        }

        // Create new version with has_change = true (data has changed)
        $newVersion = $latestVersion + 1;
        $parsedDataJson = json_encode($parsedData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $metadataJson = !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null;

        $wpdb->insert($table, [
            'origin_id' => $originId,
            'version' => $newVersion,
            'processor_type' => $processorType,
            'processor_id' => $processorId,
            'processor_name' => $processorName ?: $processorId,
            'parsed_data' => $parsedDataJson,
            'status' => 'pending',
            'has_change' => 1, // Data has changed, needs saving
            'metadata' => $metadataJson,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        error_log(sprintf(
            "CrawlFlow ParsedItem: Origin %d - Created version %d by %s (%s) - has_change=true",
            $originId,
            $newVersion,
            $processorName ?: $processorId,
            $processorType
        ));

        return $newVersion;
    }

    /**
     * Mark parsed item as saved (set has_change = false)
     * Called after successful save/complete action
     * 
     * @param int $originId Origin ID
     * @param int $version Version number (if null, uses latest version)
     * @return bool Success
     */
    public function markAsSaved(int $originId, ?int $version = null): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_data_parsed_items';

        if ($version === null) {
            $version = $this->getLatestVersion($originId);
        }

        if ($version <= 0) {
            return false;
        }

        $updated = $wpdb->update(
            $table,
            [
                'has_change' => 0,
                'status' => 'processed',
                'updated_at' => current_time('mysql'),
            ],
            [
                'origin_id' => $originId,
                'version' => $version,
            ],
            ['%d', '%s', '%s'],
            ['%d', '%d']
        );

        if ($updated) {
            error_log("CrawlFlow ParsedItem: Origin {$originId} version {$version} marked as saved (has_change=false)");
        }

        return $updated !== false;
    }

    /**
     * Get latest version number for origin
     */
    public function getLatestVersion(int $originId): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_data_parsed_items';

        $version = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(version) FROM {$table} WHERE origin_id = %d",
            $originId
        ));

        return (int)($version ?? 0);
    }

    /**
     * Get parsed data for specific version
     */
    public function getVersionData(int $originId, int $version): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_data_parsed_items';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT parsed_data FROM {$table} WHERE origin_id = %d AND version = %d",
            $originId,
            $version
        ), ARRAY_A);

        if (!$row || empty($row['parsed_data'])) {
            return null;
        }

        return json_decode($row['parsed_data'], true);
    }

    /**
     * Check if data has changed
     */
    private function hasDataChanged(?array $oldData, array $newData): bool
    {
        if ($oldData === null) {
            return true; // First version
        }

        // Normalize arrays for comparison
        $oldNormalized = $this->normalizeData($oldData);
        $newNormalized = $this->normalizeData($newData);

        return $oldNormalized !== $newNormalized;
    }

    /**
     * Normalize data for comparison (remove success flag, sort arrays, etc.)
     */
    private function normalizeData(array $data): string
    {
        // Remove success flag and other metadata that shouldn't affect comparison
        $cleaned = $data;
        unset($cleaned['success']);
        unset($cleaned['error']);

        // Sort arrays recursively
        ksort($cleaned);
        foreach ($cleaned as $key => $value) {
            if (is_array($value)) {
                $cleaned[$key] = $this->normalizeData($value);
            }
        }

        $flags = JSON_UNESCAPED_UNICODE;
        if (defined('JSON_SORT_KEYS')) {
            $flags |= JSON_SORT_KEYS;
        }
        return json_encode($cleaned, $flags);
    }

    /**
     * Get all versions for an origin
     */
    public function getVersions(int $originId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_data_parsed_items';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE origin_id = %d ORDER BY version ASC",
            $originId
        ), ARRAY_A) ?: [];
    }

    /**
     * Get statistics for parsed items
     */
    public function getStatistics(int $projectId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_data_parsed_items';
        $originsTable = $wpdb->prefix . 'rake_data_origins';
        $sourcesTable = $wpdb->prefix . 'rake_data_sources';

        // Get total parsed items
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.origin_id) 
            FROM {$table} p
            INNER JOIN {$originsTable} o ON p.origin_id = o.id
            INNER JOIN {$sourcesTable} s ON o.source_id = s.id
            WHERE s.tooth_id = %d",
            $projectId
        ));

        // Get by processor type
        $byType = $wpdb->get_results($wpdb->prepare(
            "SELECT p.processor_type, COUNT(*) as count
            FROM {$table} p
            INNER JOIN {$originsTable} o ON p.origin_id = o.id
            INNER JOIN {$sourcesTable} s ON o.source_id = s.id
            WHERE s.tooth_id = %d
            GROUP BY p.processor_type",
            $projectId
        ), ARRAY_A);

        // Get average versions per item
        $avgVersions = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(version_count) as avg
            FROM (
                SELECT origin_id, MAX(version) as version_count
                FROM {$table} p
                INNER JOIN {$originsTable} o ON p.origin_id = o.id
                INNER JOIN {$sourcesTable} s ON o.source_id = s.id
                WHERE s.tooth_id = %d
                GROUP BY origin_id
            ) as versions",
            $projectId
        ));

        return [
            'total_items' => (int)($total ?? 0),
            'by_processor_type' => $byType ?: [],
            'average_versions' => round((float)($avgVersions ?? 0), 2),
        ];
    }
}

