<?php

namespace CrawlFlow\Cron;

use CrawlFlow\Admin\ProjectService;
use CrawlFlow\Cron\ParsedItemVersioningService;
use CrawlFlow\Cron\ProjectCacheService;
use CrawlFlow\Cron\TrackedWorker;
use CrawlFlow\Cron\WorkerCacheService;
use CrawlFlow\DataSources\HttpDataSource;
use CrawlFlow\Flow\FlowService;
use CrawlFlow\Reception\Reception;
use CrawlFlow\Worker\Worker;
use Ramphor\Rake\Rake;

/**
 * Phase 2: Process Service
 * Get data from dpc_rake_data_origins, process through workers-processors
 * Detect resources in parsed items (HTML, URLs) and call complete actions
 */
class Phase2ProcessService
{
    /**
     * @var ProjectCacheService
     */
    private ProjectCacheService $projectCacheService;

    /**
     * @var ParsedItemVersioningService
     */
    private ParsedItemVersioningService $versioningService;

    /**
     * @var WorkerCacheService
     */
    private WorkerCacheService $workerCacheService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->projectCacheService = new ProjectCacheService();
        $this->versioningService = new ParsedItemVersioningService();
        $this->workerCacheService = new WorkerCacheService();
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

            // Get Reception instance (cached by project ID)
            $reception = $this->workerCacheService->getReception($projectId, $flowConfig);

            // Process raw items with versioning tracking
            $results = $this->processRawItemsWithVersioning($rawItems, $reception);

            // Execute complete actions and mark as saved
            $this->executeCompleteActions($projectId, $results, $flowConfig);

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
     * Includes both parent origins (with source_id) and child origins (via references)
     */
    private function getRawItems(int $projectId): array
    {
        global $wpdb;
        
        $originsTable = $wpdb->prefix . 'rake_data_origins';
        $sourcesTable = $wpdb->prefix . 'rake_data_sources';
        $referencesTable = $wpdb->prefix . 'rake_data_origins_references';
        $parsedItemsTable = $wpdb->prefix . 'rake_data_parsed_items';

        // Get items that have not been processed yet (no entry in parsed_items for the latest version)
        // Include both:
        // 1. Parent origins (with source_id linked to project)
        // 2. Child origins (via references from parent origins)
        $query = $wpdb->prepare(
            "SELECT DISTINCT o.* 
            FROM {$originsTable} o
            LEFT JOIN (
                SELECT origin_id, MAX(version) as max_version
                FROM {$parsedItemsTable}
                GROUP BY origin_id
            ) AS latest_parsed ON o.id = latest_parsed.origin_id
            WHERE (
                -- Parent origins (with source_id)
                (o.source_id IS NOT NULL AND EXISTS (
                    SELECT 1 FROM {$sourcesTable} s 
                    WHERE s.id = o.source_id AND s.tooth_id = %d
                ))
                OR
                -- Child origins (via references)
                (o.source_id IS NULL AND EXISTS (
                    SELECT 1 FROM {$referencesTable} r
                    INNER JOIN {$originsTable} parent ON r.parent_origin_id = parent.id
                    INNER JOIN {$sourcesTable} s ON parent.source_id = s.id
                    WHERE r.child_origin_id = o.id AND s.tooth_id = %d
                ))
            )
            AND latest_parsed.origin_id IS NULL -- Only get items that have never been parsed
            ORDER BY o.fetched_at ASC
            LIMIT 2",
            $projectId,
            $projectId
        );

        $results = $wpdb->get_results($query, ARRAY_A);
        error_log("CrawlFlow Phase 2: Found " . count($results) . " raw items for project {$projectId}");
        
        return $results ?: [];
    }

    /**
     * Process raw items with versioning tracking
     */
    private function processRawItemsWithVersioning(array $rawItems, Reception $reception): array
    {
        $results = [];

        foreach ($rawItems as $rawItem) {
            $originId = (int)($rawItem['id'] ?? 0);
            
            if (!$originId) {
                $results[] = [
                    'success' => false,
                    'item_id' => null,
                    'error' => 'Invalid origin ID',
                ];
                continue;
            }

            // Check if item needs to be fetched first (crawled=0 or no raw_data)
            $crawled = (int)($rawItem['crawled'] ?? 0);
            $rawData = $rawItem['raw_data'] ?? '';
            $guid = $rawItem['guid'] ?? '';
            
            // Skip image URLs and other resources - they should be handled in Phase 3
            if (!empty($guid) && $this->isResourceUrl($guid)) {
                error_log("CrawlFlow Phase 2: Skipping resource URL: {$guid}");
                $results[] = [
                    'success' => false,
                    'item_id' => $originId,
                    'error' => 'Resource URL (should be handled in Phase 3)',
                ];
                continue;
            }
            
            if ($crawled === 0 || empty($rawData)) {
                // Fetch raw_data before processing
                if (!empty($guid) && filter_var($guid, FILTER_VALIDATE_URL)) {
                    error_log("CrawlFlow Phase 2: Fetching raw_data for origin {$originId} (URL: {$guid})");
                    $fetched = $this->fetchRawDataForOrigin($originId, $guid);
                    if ($fetched) {
                        // Reload rawItem with fresh data
                        $rawItem = $this->reloadRawItem($originId);
                        if (!$rawItem) {
                            $results[] = [
                                'success' => false,
                                'item_id' => $originId,
                                'error' => 'Failed to reload item after fetch',
                            ];
                            continue;
                        }
                        error_log("CrawlFlow Phase 2: Successfully fetched raw_data for origin {$originId}");
                    } else {
                        $results[] = [
                            'success' => false,
                            'item_id' => $originId,
                            'error' => 'Failed to fetch raw_data',
                        ];
                        continue;
                    }
                } else {
                    $results[] = [
                        'success' => false,
                        'item_id' => $originId,
                        'error' => 'Invalid URL for fetching',
                    ];
                    continue;
                }
            }

            // Debug: Log rawItem structure
            error_log("CrawlFlow Phase 2: Processing origin {$originId}, guid: " . ($rawItem['guid'] ?? 'NOT SET') . ", keys: " . implode(', ', array_keys($rawItem)));
            
            // Find appropriate worker
            $worker = $reception->assignToWorker($rawItem);

            if (!$worker) {
                $results[] = [
                    'success' => false,
                    'item_id' => $originId,
                    'error' => 'No worker can handle this item',
                ];
                continue;
            }

            try {
                // Use TrackedWorker to track versions
                $trackedWorker = new TrackedWorker($worker, $this->versioningService, $originId);
                $processedData = $trackedWorker->process($rawItem);

                $results[] = [
                    'success' => $processedData['success'] ?? true,
                    'item_id' => $originId,
                    'worker' => $worker->getName(),
                    'data' => $processedData,
                ];

            } catch (\Exception $e) {
                error_log("CrawlFlow Phase 2: Error processing origin {$originId} - " . $e->getMessage());
                $results[] = [
                    'success' => false,
                    'item_id' => $originId,
                    'worker' => $worker->getName(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Fetch raw_data for an origin that hasn't been crawled yet
     */
    private function fetchRawDataForOrigin(int $originId, string $url): bool
    {
        try {
            $dataSource = new HttpDataSource();
            $response = $dataSource->fetch($url);
            
            if (isset($response['status_code']) && $response['status_code'] === 200) {
                $rawData = $response['body'] ?? '';
                
                // Update origin with fetched data
                global $wpdb;
                $table = $wpdb->prefix . 'rake_data_origins';
                $now = current_time('mysql');
                
                $updated = $wpdb->update(
                    $table,
                    [
                        'raw_data' => $rawData,
                        'fetched_at' => $now,
                        'updated_at' => $now,
                        'crawled' => 1,
                    ],
                    ['id' => $originId],
                    ['%s', '%s', '%s', '%d'],
                    ['%d']
                );
                
                return $updated !== false;
            } else {
                error_log("CrawlFlow Phase 2: Failed to fetch {$url} - Status: " . ($response['status_code'] ?? 'unknown'));
                return false;
            }
        } catch (\Exception $e) {
            error_log("CrawlFlow Phase 2: Exception fetching {$url}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reload raw item from database after fetching
     */
    private function reloadRawItem(int $originId): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_data_origins';
        
        $item = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $originId),
            ARRAY_A
        );
        
        return $item ?: null;
    }

    /**
     * Check if URL is a resource (image, file, etc.) that should be handled in Phase 3
     */
    private function isResourceUrl(string $url): bool
    {
        $resourceExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'zip', 'doc', 'docx', 'xls', 'xlsx'];
        $path = parse_url($url, PHP_URL_PATH);
        if ($path) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($extension, $resourceExtensions)) {
                return true;
            }
        }
        
        // Check for common resource paths
        $resourcePaths = ['/tmp/', '/images/', '/img/', '/assets/', '/static/', '/media/', '/uploads/'];
        foreach ($resourcePaths as $resourcePath) {
            if (strpos($url, $resourcePath) !== false) {
                return true;
            }
        }
        
        return false;
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

        // Generate resource name from URL
        $urlPath = parse_url($resource['url'], PHP_URL_PATH);
        $resourceName = 'Resource';
        if ($urlPath !== null && $urlPath !== '') {
            $basename = basename($urlPath);
            if ($basename !== '' && $basename !== '/') {
                $resourceName = $basename;
            } else {
                // If path is just '/', use hostname
                $host = parse_url($resource['url'], PHP_URL_HOST);
                $resourceName = $host ?: 'Resource';
            }
        } else {
            // No path, use hostname
            $host = parse_url($resource['url'], PHP_URL_HOST);
            $resourceName = $host ?: 'Resource';
        }

        // Insert new resource
        $wpdb->insert($table, [
            'tooth_id' => $projectId,
            'type' => 'resource',
            'name' => $resourceName,
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
     * Now uses origin IDs instead of URLs
     */
    private function saveResourceReference(int $parentOriginId, int $resourceId, string $type): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_data_origins_references';
        $originsTable = $wpdb->prefix . 'rake_data_origins';
        $sourcesTable = $wpdb->prefix . 'rake_data_sources';

        // Get resource URL and find/create child origin
        $resource = $wpdb->get_row($wpdb->prepare(
            "SELECT config FROM {$sourcesTable} WHERE id = %d",
            $resourceId
        ), ARRAY_A);

        if (!$resource) {
            return;
        }

        $resourceConfig = json_decode($resource['config'] ?? '{}', true);
        $childUrl = $resourceConfig['url'] ?? '';

        if (empty($childUrl)) {
            return;
        }

        // Find or create child origin
        $childOrigin = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$originsTable} WHERE guid = %s",
            $childUrl
        ), ARRAY_A);

        if (!$childOrigin) {
            // Create child origin if not exists
            $wpdb->insert($originsTable, [
                'source_id' => null, // Resource doesn't have source_id
                'guid' => $childUrl,
                'raw_data' => '',
                'fetched_at' => current_time('mysql'),
            ]);
            $childOriginId = (int)$wpdb->insert_id;
        } else {
            $childOriginId = (int)$childOrigin['id'];
        }

        // Check if reference already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE parent_origin_id = %d AND child_origin_id = %d",
            $parentOriginId,
            $childOriginId
        ));

        if (!$existing) {
            $wpdb->insert($table, [
                'parent_origin_id' => $parentOriginId,
                'child_origin_id' => $childOriginId,
                'relationship_type' => $type,
                'created_at' => current_time('mysql'),
            ]);
        }
    }

    /**
     * Execute complete actions (finish actions) after processing
     * This includes saving to WordPress (posts, products, etc.) and marking parsed items as saved
     */
    private function executeCompleteActions(int $projectId, array $results, array $flowConfig): void
    {
        $finishActions = $flowConfig['finishActions'] ?? $flowConfig['completeActions'] ?? [];
        
        if (empty($finishActions)) {
            error_log("CrawlFlow Phase 2: No finish actions configured for project {$projectId}");
            return;
        }

        foreach ($results as $result) {
            if (!($result['success'] ?? false)) {
                continue; // Skip failed items
            }

            $originId = (int)($result['item_id'] ?? 0);
            if (!$originId) {
                continue;
            }

            $processedData = $result['data'] ?? [];
            
            // Execute each finish action
            foreach ($finishActions as $action) {
                $actionType = $action['type'] ?? 'unknown';
                $actionConfig = $action['config'] ?? [];

                try {
                    $actionSuccess = $this->executeFinishAction($actionType, $actionConfig, $processedData, $projectId);
                    
                    if ($actionSuccess) {
                        // Mark parsed item as saved (set has_change = false)
                        $latestVersion = $this->versioningService->getLatestVersion($originId);
                        if ($latestVersion > 0) {
                            $this->versioningService->markAsSaved($originId, $latestVersion);
                            error_log("CrawlFlow Phase 2: Origin {$originId} version {$latestVersion} marked as saved after complete action");
                        }
                    }
                } catch (\Exception $e) {
                    error_log("CrawlFlow Phase 2: Error executing finish action {$actionType} for origin {$originId} - " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Execute a single finish action
     */
    private function executeFinishAction(string $actionType, array $config, array $processedData, int $projectId): bool
    {
        switch ($actionType) {
            case 'wordpress_post':
            case 'save_post':
                // WordPress post is already saved by WordPressPostProcessor
                // Just verify it was saved
                if (isset($processedData['post_id'])) {
                    $post = get_post($processedData['post_id']);
                    return $post !== null;
                }
                return false;

            case 'woocommerce_product':
            case 'save_product':
                // TODO: Implement WooCommerce product saving
                error_log("CrawlFlow Phase 2: WooCommerce product saving not yet implemented");
                return false;

            case 'log_summary':
                error_log(sprintf(
                    "CrawlFlow: Project %d - Item processed: %s",
                    $projectId,
                    json_encode($processedData)
                ));
                return true;

            case 'send_notification':
                // TODO: Implement notification sending
                error_log("CrawlFlow Phase 2: Notification sending not yet implemented");
                return false;

            default:
                error_log("CrawlFlow Phase 2: Unknown finish action type: {$actionType}");
                return false;
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

