<?php

namespace CrawlFlow\Cron;

use CrawlFlow\Cron\ProjectCacheService;
use Rake\Facade\Logger;
use Rake\Rake;

/**
 * Phase 3: Resources Service
 * Get resources that need processing, download images/files, import to WordPress
 * Replace URLs in content with new WordPress URLs
 */
class Phase3ResourcesService
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
     * Execute Phase 3: Process resources
     * 
     * @param int $projectId Project ID
     * @return array Execution results
     */
    public function execute(int $projectId): array
    {
        try {
            Logger::info("CrawlFlow Phase 3: Starting resource processing for project {$projectId}");

            // Load project (cached)
            $project = $this->projectCacheService->getProject($projectId);
            if (!$project) {
                throw new \RuntimeException("Project {$projectId} not found");
            }

            Logger::info("CrawlFlow Phase 3: Project loaded successfully for project {$projectId}");

            // Get pending resources
            Logger::info("CrawlFlow Phase 3: About to call getPendingResources() for project {$projectId}");
            $resources = $this->getPendingResources($projectId);
            Logger::info("CrawlFlow Phase 3: getPendingResources() returned " . (is_array($resources) ? count($resources) : 'NOT_ARRAY') . " resources for project {$projectId}");

            if (empty($resources)) {
                Logger::warning("CrawlFlow Phase 3: No pending resources for project {$projectId}");
                return [
                    'project_id' => $projectId,
                    'phase' => 'resources',
                    'resources_processed' => 0,
                    'images_imported' => 0,
                    'files_downloaded' => 0,
                    'urls_replaced' => 0,
                    'errors' => [],
                ];
            }

            $results = [
                'project_id' => $projectId,
                'phase' => 'resources',
                'resources_processed' => 0,
                'images_imported' => 0,
                'files_downloaded' => 0,
                'urls_sent_to_origins' => 0,
                'urls_replaced' => 0,
                'errors' => [],
            ];

            foreach ($resources as $index => $resource) {
                try {
                    Logger::info("CrawlFlow Phase 3: Processing resource {$index}/" . count($resources) . " (ID: {$resource['id']}, Type: {$resource['data_type']}) for project {$projectId}");
                    $resourceResult = $this->processResource($projectId, $resource);
                    $results['resources_processed']++;
                    Logger::info("CrawlFlow Phase 3: Completed resource {$index} for project {$projectId}");
                    
                    if ($resourceResult['type'] === 'image' && $resourceResult['imported']) {
                        $results['images_imported']++;
                        if ($resourceResult['url_replaced']) {
                            $results['urls_replaced']++;
                        }
                    } elseif ($resourceResult['type'] === 'file' && $resourceResult['downloaded']) {
                        $results['files_downloaded']++;
                        if ($resourceResult['url_replaced']) {
                            $results['urls_replaced']++;
                        }
                    } elseif ($resourceResult['type'] === 'url' && $resourceResult['sent_to_origins']) {
                        $results['urls_sent_to_origins']++;
                    }
                } catch (\Exception $e) {
                    Logger::error("CrawlFlow Phase 3: Error processing resource {$index} (ID: {$resource['id']}) for project {$projectId}: " . $e->getMessage());
                    $results['errors'][] = [
                        'resource_id' => $resource['id'],
                        'error' => $e->getMessage(),
                    ];
                    Logger::error("CrawlFlow Phase 3: Error processing resource {$resource['id']} - " . $e->getMessage());
                }
            }

            Logger::info(sprintf(
                "CrawlFlow Phase 3: Completed for project %d - Processed: %d, Images: %d, Files: %d, URLs Replaced: %d",
                $projectId,
                $results['resources_processed'],
                $results['images_imported'],
                $results['files_downloaded'],
                $results['urls_replaced']
            ));

            return $results;

        } catch (\Exception $e) {
            \Rake\Facade\Logger::error("CrawlFlow Phase 3: Failed for project {$projectId} - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get pending resources for project
     */
    private function getPendingResources(int $projectId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_resources';

        $resources = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE tooth_id = %d AND import_status = 'pending' LIMIT 50",
                $projectId
            ),
            ARRAY_A
        );

        $resourceCount = is_array($resources) ? count($resources) : 0;
        Logger::info("CrawlFlow Phase 3: Found {$resourceCount} pending resources for project {$projectId}");
        
        // ALWAYS log detailed information about resources (even if empty for debugging)
        Logger::info("CrawlFlow Phase 3: Resources to be processed in detail:");
        
        if (!is_array($resources)) {
            Logger::error("CrawlFlow Phase 3: ERROR - Resources is not an array: " . gettype($resources));
            return [];
        }
        
        if ($resourceCount === 0) {
            Logger::warning("CrawlFlow Phase 3: NO RESOURCES FOUND - Debugging database query:");
            Logger::info("CrawlFlow Phase 3: - Query executed: SELECT * FROM {$table} WHERE tooth_id = {$projectId} AND import_status = 'pending' LIMIT 50");
            Logger::info("CrawlFlow Phase 3: - Table used: {$table}");
            Logger::info("CrawlFlow Phase 3: - Project ID: {$projectId}");
            
            // Check if table exists
            $tableCheck = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            Logger::info("CrawlFlow Phase 3: - Table exists: " . ($tableCheck ? 'YES' : 'NO'));
            
            // Check total records in table
            $totalRecords = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            Logger::info("CrawlFlow Phase 3: - Total records in table: {$totalRecords}");
            
            // Check records for this project (without filters)
            $projectRecords = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE tooth_id = %d",
                $projectId
            ));
            Logger::info("CrawlFlow Phase 3: - Records for project {$projectId}: {$projectRecords}");
            
            // Check resource records for this project
            $resourceRecords = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE tooth_id = %d",
                $projectId
            ));
            Logger::info("CrawlFlow Phase 3: - Resource records for project {$projectId}: {$resourceRecords}");
            
            // Check pending resources for this project
            $pendingRecords = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE tooth_id = %d AND import_status = 'pending'",
                $projectId
            ));
            Logger::info("CrawlFlow Phase 3: - Pending resource records for project {$projectId}: {$pendingRecords}");
            
            // Show sample records for debugging
            $sampleRecords = $wpdb->get_results($wpdb->prepare(
                "SELECT id, data_type, guid, import_status FROM {$table} WHERE tooth_id = %d LIMIT 3",
                $projectId
            ), ARRAY_A);
            Logger::info("CrawlFlow Phase 3: - Sample resource records:");
            foreach ($sampleRecords as $index => $record) {
                Logger::info("CrawlFlow Phase 3:   Sample #{$index}: ID={$record['id']}, Type={$record['data_type']}, Status={$record['import_status']}, GUID={$record['guid']}");
            }
        } else {
            foreach ($resources as $index => $resource) {
                $resourceId = $resource['id'] ?? 'unknown';
                $dataType = $resource['data_type'] ?? 'unknown';
                $guid = $resource['guid'] ?? 'no_guid';
                $importStatus = $resource['import_status'] ?? 'unknown';
                
                Logger::info("CrawlFlow Phase 3: Resource #{$index} - ID: {$resourceId}, Type: {$dataType}, Status: {$importStatus}, GUID: {$guid}");
                
                // Log first few resources' additional metadata
                if ($index < 3) {
                    $metadata = json_decode($resource['metadata'] ?? '{}', true);
                    $metadataPreview = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    Logger::info("CrawlFlow Phase 3: Resource #{$index} metadata: {$metadataPreview}");
                }
            }
        }

        return $resources;
    }

    /**
     * Process a single resource
     */
    private function processResource(int $projectId, array $resource): array
    {
        $metadata = json_decode($resource['metadata'] ?? '{}', true);
        $url = $resource['guid'] ?? '';
        $resourceType = $resource['data_type'] ?? 'url';

        $result = [
            'type' => $resourceType,
            'imported' => false,
            'downloaded' => false,
            'sent_to_origins' => false,
            'url_replaced' => false,
            'new_url' => null,
        ];

        if (empty($url)) {
            return $result;
        }

        switch ($resourceType) {
            case 'image':
                Logger::info("CrawlFlow Phase 3: Processing image resource ID {$resource['id']} with URL: {$url}");
                $result = $this->processImage($projectId, $resource, $url);
                break;
            case 'file':
                Logger::info("CrawlFlow Phase 3: Processing file resource ID {$resource['id']} with URL: {$url}");
                $result = $this->processFile($projectId, $resource, $url);
                break;
            case 'url':
                Logger::info("CrawlFlow Phase 3: Processing URL resource ID {$resource['id']} with URL: {$url}");
                $result = $this->processUrl($projectId, $resource, $url);
                break;
        }

        // Mark resource as processed
        $this->markResourceAsProcessed($resource['id'], $result);

        return $result;
    }

    /**
     * Process image: download and import to WordPress media library
     */
    private function processImage(int $projectId, array $resource, string $url): array
    {
        $result = [
            'type' => 'image',
            'imported' => false,
            'downloaded' => false,
            'sent_to_origins' => false,
            'url_replaced' => false,
            'new_url' => null,
        ];

        // Download and import to WordPress
        Logger::info('CrawlFlow Phase 3: Importing image to WordPress', [
            'project_id' => $projectId,
            'resource_id' => $resource['id'] ?? null,
            'url' => $url,
        ]);
        $attachmentId = $this->importImageToWordPress($url);

        Logger::info('CrawlFlow Phase 3: importImageToWordPress result', [
            'project_id' => $projectId,
            'resource_id' => $resource['id'] ?? null,
            'url' => $url,
            'attachment_id' => $attachmentId,
        ]);

        if ($attachmentId) {
            $newUrl = wp_get_attachment_url($attachmentId);
            $result['imported'] = true;
            $result['new_url'] = $newUrl;

            // Replace URLs in content
            $replaced = $this->replaceUrlInContent($projectId, $url, $newUrl);
            $result['url_replaced'] = $replaced > 0;

            Logger::info('CrawlFlow Phase 3: Image imported and URL replaced', [
                'project_id' => $projectId,
                'resource_id' => $resource['id'] ?? null,
                'attachment_id' => $attachmentId,
                'old_url' => $url,
                'new_url' => $newUrl,
                'replaced_count' => $replaced,
            ]);
        } else {
            Logger::warning('CrawlFlow Phase 3: Failed to import image to WordPress', [
                'project_id' => $projectId,
                'resource_id' => $resource['id'] ?? null,
                'url' => $url,
            ]);
        }

        return $result;
    }

    /**
     * Process file: download file
     */
    private function processFile(int $projectId, array $resource, string $url): array
    {
        $result = [
            'type' => 'file',
            'imported' => false,
            'downloaded' => false,
            'sent_to_origins' => false,
            'url_replaced' => false,
            'new_url' => null,
        ];

        // Download file
        $filePath = $this->downloadFile($url);

        if ($filePath) {
            $result['downloaded'] = true;
            $result['new_url'] = $filePath;

            // If it's an image file, also import to WordPress
            $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            
            if (in_array($extension, $imageExtensions)) {
                $attachmentId = $this->importImageToWordPress($url);
                if ($attachmentId) {
                    $newUrl = wp_get_attachment_url($attachmentId);
                    $result['imported'] = true;
                    $result['new_url'] = $newUrl;
                    
                    // Replace URLs
                    $replaced = $this->replaceUrlInContent($projectId, $url, $newUrl);
                    $result['url_replaced'] = $replaced > 0;
                }
            }
        }

        return $result;
    }

    /**
     * Process URL: send to rake_data_origins for further processing if needed
     */
    private function processUrl(int $projectId, array $resource, string $url): array
    {
        $result = [
            'type' => 'url',
            'imported' => false,
            'downloaded' => false,
            'sent_to_origins' => false,
            'url_replaced' => false,
            'new_url' => null,
        ];

        // Check if URL should be crawled (same domain, etc.)
        $shouldCrawl = $this->shouldCrawlUrl($projectId, $url);

        if ($shouldCrawl) {
            // Send to rake_data_origins for Phase 1 processing
            $originId = $this->sendUrlToDataOrigins($projectId, $url);
            if ($originId) {
                $result['sent_to_origins'] = true;
            }
        }

        return $result;
    }

    /**
     * Import image to WordPress media library
     */
    private function importImageToWordPress(string $url): ?int
    {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Increase execution time limit for large downloads
        $originalTimeLimit = ini_get('max_execution_time');
        set_time_limit(300); // 5 minutes
        
        try {
            // Download file with shorter timeout
            Logger::debug('CrawlFlow Phase 3: Downloading image for sideload', [
                'url' => $url,
            ]);
            $tmp = download_url($url, 30); // 30 second timeout
            if (is_wp_error($tmp)) {
                Logger::warning('CrawlFlow Phase 3: download_url failed', [
                    'url' => $url,
                    'error' => $tmp->get_error_message(),
                ]);
                return null;
            }
        } catch (\Exception $e) {
            // Restore original time limit on error
            set_time_limit($originalTimeLimit);
            Logger::error('CrawlFlow Phase 3: Exception during download_url', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
        
        // Restore original time limit
        set_time_limit($originalTimeLimit);

        // Prepare file array
        $file_array = [
            'name' => basename(parse_url($url, PHP_URL_PATH)) ?: 'image.jpg',
            'tmp_name' => $tmp,
        ];

        // Import to media library
        $attachmentId = media_handle_sideload($file_array, 0);

        if (is_wp_error($attachmentId)) {
            @unlink($tmp);
            Logger::warning('CrawlFlow Phase 3: media_handle_sideload failed', [
                'url' => $url,
                'error' => $attachmentId->get_error_message(),
            ]);
            return null;
        }

        Logger::debug('CrawlFlow Phase 3: media_handle_sideload succeeded', [
            'url' => $url,
            'attachment_id' => $attachmentId,
        ]);

        return $attachmentId;
    }

    /**
     * Download file
     */
    private function downloadFile(string $url): ?string
    {
        $uploadDir = wp_upload_dir();
        $filePath = $uploadDir['path'] . '/' . basename(parse_url($url, PHP_URL_PATH));

        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        if (file_put_contents($filePath, $body)) {
            return $filePath;
        }

        return null;
    }

    /**
     * Replace URL in content
     */
    private function replaceUrlInContent(int $projectId, string $oldUrl, string $newUrl): int
    {
        global $wpdb;
        $originsTable = $wpdb->prefix . 'rake_data_origins';
        $sourcesTable = $wpdb->prefix . 'rake_data_sources';

        // Update in rake_data_origins raw_data
        $query = "
            UPDATE {$originsTable} o
            INNER JOIN {$sourcesTable} s ON o.source_id = s.id
            SET o.raw_data = REPLACE(o.raw_data, %s, %s)
            WHERE s.tooth_id = %d
        ";

        $replaced = $wpdb->query($wpdb->prepare($query, $oldUrl, $newUrl, $projectId));

        return $replaced ?: 0;
    }

    /**
     * Check if URL should be crawled
     */
    private function shouldCrawlUrl(int $projectId, string $url): bool
    {
        // Get project base URL from config (cached)
        $flowConfig = $this->projectCacheService->getFlowConfig($projectId);
        $baseUrl = $flowConfig['projectSettings']['baseUrl'] ?? '';

        if (empty($baseUrl)) {
            return false;
        }

        $parsedUrl = parse_url($url);
        $parsedBase = parse_url($baseUrl);

        // Only crawl same domain
        return ($parsedUrl['host'] ?? '') === ($parsedBase['host'] ?? '');
    }

    /**
     * Send URL to rake_data_origins for Phase 1 processing
     */
    private function sendUrlToDataOrigins(int $projectId, string $url, ?string $processorId = null): ?int
    {
        global $wpdb;
        $originsTable = $wpdb->prefix . 'rake_data_origins';
        $sourcesTable = $wpdb->prefix . 'rake_data_sources';

        // Get or create source for this URL
        $source = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$sourcesTable} WHERE tooth_id = %d AND type = 'url' AND config LIKE %s",
            $projectId,
            '%' . $wpdb->esc_like($url) . '%'
        ), ARRAY_A);

        if (!$source) {
            // Create new source
            $wpdb->insert($sourcesTable, [
                'tooth_id' => $projectId,
                'type' => 'url',
                'name' => 'Resource URL',
                'config' => json_encode(['url' => $url]),
                'created_at' => current_time('mysql'),
            ]);
            $sourceId = $wpdb->insert_id;
        } else {
            $sourceId = $source['id'];
        }

        // Check if already in origins
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$originsTable} WHERE guid = %s",
            $url
        ));

        if ($existing) {
            return (int)$existing;
        }

        // Detect worker priority for URL
        $priority = 100; // Default priority
        try {
            $flowConfig = $this->projectCacheService->getFlowConfig($projectId);
            if ($flowConfig && !empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                $workerCacheService = new \CrawlFlow\Cron\WorkerCacheService();
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
                        $priority = $worker->getPriority();
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            \Rake\Facade\Logger::error("CrawlFlow Phase 3: Error detecting worker priority for URL {$url}: " . $e->getMessage());
        }
        
        // Insert to origins (from processor, will be fetched in Phase 1)
        $wpdb->insert($originsTable, [
            'source_id' => $sourceId,
            'guid' => $url,
            'raw_data' => '',
            'fetched_at' => null,
            'source_type' => 'processor',
            'processor_id' => $processorId ?? 'phase3_resources',
            'priority' => $priority,
        ]);

        return $wpdb->insert_id ? (int)$wpdb->insert_id : null;
    }

    /**
     * Mark resource as processed
     */
    private function markResourceAsProcessed(int $resourceId, array $result): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_resources';

        $metadata = $wpdb->get_var($wpdb->prepare(
            "SELECT metadata FROM {$table} WHERE id = %d",
            $resourceId
        ));
        
        // Handle null metadata
        $config = $metadata ? json_decode($metadata, true) : [];
        if (!is_array($config)) {
            $config = [];
        }

        $config['status'] = 'processed';
        $config['processed_at'] = current_time('mysql');
        if ($result['new_url']) {
            $config['new_url'] = $result['new_url'];
        }

        $wpdb->update(
            $table,
            ['metadata' => json_encode($config)],
            ['id' => $resourceId]
        );
    }
}

