<?php

namespace CrawlFlow\Cron;

use CrawlFlow\Admin\ProjectService;
use CrawlFlow\Reception\Reception;
use Rake\Rake;

/**
 * Phase 2: Process Service
 * Get data from dpc_rake_data_origins, process through workers-processors
 * Detect resources in parsed items (HTML, URLs) and call complete actions
 */
class Phase2ProcessService
{
    /**
     * @var ProjectService
     */
    private ProjectService $projectService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $rake = Rake::getInstance();
        $this->projectService = $rake->make('CrawlFlow\Admin\ProjectService');
    }

    /**
     * Execute Phase 2: Process raw data
     * 
     * @param int $projectId Project ID
     * @return array Execution results
     */
    public function execute(int $projectId): array
    {
        try {
            error_log("CrawlFlow Phase 2: Starting processing for project {$projectId}");

            // Load project
            $project = $this->projectService->getProject($projectId);
            if (!$project) {
                throw new \RuntimeException("Project {$projectId} not found");
            }

            // Get flow config
            $flowConfig = $this->projectService->getFlowConfig($projectId);
            if (!$flowConfig) {
                throw new \RuntimeException("No flow config for project {$projectId}");
            }

            // Get raw items from rake_data_origins for this project
            $rawItems = $this->getRawItems($projectId);

            if (empty($rawItems)) {
                error_log("CrawlFlow Phase 2: No raw items found for project {$projectId}");
                return [
                    'project_id' => $projectId,
                    'phase' => 'process',
                    'items_processed' => 0,
                    'resources_detected' => 0,
                    'errors' => [],
                ];
            }

            // Create Reception with workers from config
            $reception = new Reception($flowConfig);

            // Process raw items
            $results = $reception->processRawItems($rawItems);

            // Detect and save resources
            $resourcesDetected = $this->detectAndSaveResources($projectId, $rawItems, $results);

            $summary = [
                'project_id' => $projectId,
                'phase' => 'process',
                'items_processed' => count($results),
                'items_success' => count(array_filter($results, function($r) { return $r['success'] ?? false; })),
                'items_failed' => count(array_filter($results, function($r) { return !($r['success'] ?? false); })),
                'resources_detected' => $resourcesDetected,
                'errors' => array_map(function($r) { return $r['error'] ?? null; }, array_filter($results, function($r) { return !($r['success'] ?? false); })),
            ];

            error_log(sprintf(
                "CrawlFlow Phase 2: Completed for project %d - Processed: %d, Success: %d, Resources: %d",
                $projectId,
                $summary['items_processed'],
                $summary['items_success'],
                $summary['resources_detected']
            ));

            return $summary;

        } catch (\Exception $e) {
            error_log("CrawlFlow Phase 2: Failed for project {$projectId} - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get raw items from rake_data_origins for project
     */
    private function getRawItems(int $projectId): array
    {
        global $wpdb;
        
        $originsTable = $wpdb->prefix . 'rake_data_origins';
        $sourcesTable = $wpdb->prefix . 'rake_data_sources';

        // Get items via data sources linked to project
        // Note: dpc_rake_data_origins doesn't have processed_at column, so we get all items
        // In production, you might want to track processed items in a separate table
        $query = "
            SELECT o.* 
            FROM {$originsTable} o
            INNER JOIN {$sourcesTable} s ON o.source_id = s.id
            WHERE s.tooth_id = %d
            ORDER BY o.fetched_at ASC
            LIMIT 100
        ";

        return $wpdb->get_results($wpdb->prepare($query, $projectId), ARRAY_A) ?: [];
    }

    /**
     * Detect and save resources from processed items
     */
    private function detectAndSaveResources(int $projectId, array $rawItems, array $results): int
    {
        global $wpdb;
        $resourcesTable = $wpdb->prefix . 'rake_data_sources';
        $referencesTable = $wpdb->prefix . 'rake_data_origins_references';

        $resourcesCount = 0;

        foreach ($results as $index => $result) {
            if (!($result['success'] ?? false)) {
                continue;
            }

            $rawItem = $rawItems[$index] ?? null;
            if (!$rawItem) {
                continue;
            }

            $processedData = $result['data'] ?? [];
            
            // Extract resources from processed data
            $resources = $this->extractResources($processedData, $rawItem);

            foreach ($resources as $resource) {
                // Save to rake_data_sources if not exists
                $resourceId = $this->saveResource($projectId, $resource);
                
                if ($resourceId) {
                    // Save reference link
                    $this->saveResourceReference($rawItem['id'], $resourceId, $resource['type']);
                    $resourcesCount++;
                }
            }

            // Mark origin as processed
            $this->markAsProcessed($rawItem['id']);
        }

        return $resourcesCount;
    }

    /**
     * Extract resources from processed data
     */
    private function extractResources(array $processedData, array $rawItem): array
    {
        $resources = [];
        $html = $processedData['html'] ?? $rawItem['raw_data'] ?? '';

        if (empty($html)) {
            return $resources;
        }

        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        // Extract images
        $images = $xpath->query('//img[@src]');
        foreach ($images as $img) {
            $src = $img->getAttribute('src');
            if ($src) {
                $resources[] = [
                    'url' => $this->resolveUrl($rawItem['guid'] ?? '', $src),
                    'type' => 'image',
                ];
            }
        }

        // Extract links
        $links = $xpath->query('//a[@href]');
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if ($href && !preg_match('/^(#|javascript:)/', $href)) {
                $resources[] = [
                    'url' => $this->resolveUrl($rawItem['guid'] ?? '', $href),
                    'type' => 'url',
                ];
            }
        }

        // Extract files (PDF, DOC, etc.)
        $fileLinks = $xpath->query('//a[contains(@href, ".pdf") or contains(@href, ".doc") or contains(@href, ".zip")]');
        foreach ($fileLinks as $fileLink) {
            $href = $fileLink->getAttribute('href');
            if ($href) {
                $resources[] = [
                    'url' => $this->resolveUrl($rawItem['guid'] ?? '', $href),
                    'type' => 'file',
                ];
            }
        }

        return $resources;
    }

    /**
     * Save resource to rake_data_sources
     */
    private function saveResource(int $projectId, array $resource): ?int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_data_sources';

        // Check if already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE tooth_id = %d AND config LIKE %s",
            $projectId,
            '%' . $wpdb->esc_like($resource['url']) . '%'
        ));

        if ($existing) {
            return (int)$existing;
        }

        // Insert new resource
        $wpdb->insert($table, [
            'tooth_id' => $projectId,
            'type' => 'resource',
            'name' => basename(parse_url($resource['url'], PHP_URL_PATH)) ?: 'Resource',
            'config' => json_encode([
                'url' => $resource['url'],
                'resource_type' => $resource['type'],
                'status' => 'pending',
            ]),
            'created_at' => current_time('mysql'),
        ]);

        return $wpdb->insert_id ? (int)$wpdb->insert_id : null;
    }

    /**
     * Save resource reference
     */
    private function saveResourceReference(int $originId, int $resourceId, string $type): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_data_origins_references';
        $originsTable = $wpdb->prefix . 'rake_data_origins';
        $sourcesTable = $wpdb->prefix . 'rake_data_sources';

        // Get parent URL from origin
        $origin = $wpdb->get_row($wpdb->prepare(
            "SELECT guid FROM {$originsTable} WHERE id = %d",
            $originId
        ), ARRAY_A);

        // Get child URL from resource
        $resource = $wpdb->get_row($wpdb->prepare(
            "SELECT config FROM {$sourcesTable} WHERE id = %d",
            $resourceId
        ), ARRAY_A);

        if (!$origin || !$resource) {
            return;
        }

        $parentUrl = $origin['guid'] ?? '';
        $resourceConfig = json_decode($resource['config'] ?? '{}', true);
        $childUrl = $resourceConfig['url'] ?? '';

        if (empty($parentUrl) || empty($childUrl)) {
            return;
        }

        $parentUrlHash = md5($parentUrl);
        $childUrlHash = md5($childUrl);

        // Check if already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE parent_url_hash = %s AND child_url_hash = %s",
            $parentUrlHash,
            $childUrlHash
        ));

        if (!$existing) {
            $wpdb->insert($table, [
                'parent_url' => $parentUrl,
                'parent_url_hash' => $parentUrlHash,
                'child_url' => $childUrl,
                'child_url_hash' => $childUrlHash,
                'relationship_type' => $type,
                'source_id' => $originId,
                'created_at' => current_time('mysql'),
            ]);
        }
    }

    /**
     * Mark origin as processed
     * Note: dpc_rake_data_origins doesn't have processed_at column
     * In production, you might want to track processed items in a separate table
     */
    private function markAsProcessed(int $originId): void
    {
        // For now, we don't mark as processed since table doesn't have the column
        // You could create a separate tracking table if needed
        error_log("CrawlFlow Phase 2: Origin {$originId} processed (not marked in DB - no processed_at column)");
    }

    /**
     * Resolve relative URL to absolute
     */
    private function resolveUrl(string $baseUrl, string $url): string
    {
        if (empty($url) || preg_match('/^https?:\/\//', $url)) {
            return $url;
        }

        $parsed = parse_url($baseUrl);
        $base = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? '');
        if (isset($parsed['port'])) {
            $base .= ':' . $parsed['port'];
        }
        $basePath = dirname($parsed['path'] ?? '/');

        if (strpos($url, '/') === 0) {
            return $base . $url;
        }

        return $base . $basePath . '/' . ltrim($url, '/');
    }
}

