<?php

namespace CrawlFlow\Cron;

use CrawlFlow\Admin\ProjectService;
use CrawlFlow\Cron\ProjectCacheService;
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
            error_log("CrawlFlow Phase 3: Starting resource processing for project {$projectId}");

            // Load project (cached)
            $project = $this->projectCacheService->getProject($projectId);
            if (!$project) {
                throw new \RuntimeException("Project {$projectId} not found");
            }

            // Get pending resources
            $resources = $this->getPendingResources($projectId);

            if (empty($resources)) {
                error_log("CrawlFlow Phase 3: No pending resources for project {$projectId}");
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

            foreach ($resources as $resource) {
                try {
                    $resourceResult = $this->processResource($projectId, $resource);
                    $results['resources_processed']++;
                    
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
                    $results['errors'][] = [
                        'resource_id' => $resource['id'],
                        'error' => $e->getMessage(),
                    ];
                    error_log("CrawlFlow Phase 3: Error processing resource {$resource['id']} - " . $e->getMessage());
                }
            }

            error_log(sprintf(
                "CrawlFlow Phase 3: Completed for project %d - Processed: %d, Images: %d, Files: %d, URLs Replaced: %d",
                $projectId,
                $results['resources_processed'],
                $results['images_imported'],
                $results['files_downloaded'],
                $results['urls_replaced']
            ));

            return $results;

        } catch (\Exception $e) {
            error_log("CrawlFlow Phase 3: Failed for project {$projectId} - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get pending resources for project
     */
    private function getPendingResources(int $projectId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_data_sources';

        $resources = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE tooth_id = %d AND type = 'resource' AND JSON_EXTRACT(config, '$.status') = 'pending' LIMIT 50",
                $projectId
            ),
            ARRAY_A
        );

        return $resources ?: [];
    }

    /**
     * Process a single resource
     */
    private function processResource(int $projectId, array $resource): array
    {
        $config = json_decode($resource['config'], true);
        $url = $config['url'] ?? '';
        $resourceType = $config['resource_type'] ?? 'url';

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
                $result = $this->processImage($projectId, $resource, $url);
                break;
            case 'file':
                $result = $this->processFile($projectId, $resource, $url);
                break;
            case 'url':
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
        $attachmentId = $this->importImageToWordPress($url);

        if ($attachmentId) {
            $newUrl = wp_get_attachment_url($attachmentId);
            $result['imported'] = true;
            $result['new_url'] = $newUrl;

            // Replace URLs in content
            $replaced = $this->replaceUrlInContent($projectId, $url, $newUrl);
            $result['url_replaced'] = $replaced > 0;
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

        // Download file
        $tmp = download_url($url);
        if (is_wp_error($tmp)) {
            return null;
        }

        // Prepare file array
        $file_array = [
            'name' => basename(parse_url($url, PHP_URL_PATH)) ?: 'image.jpg',
            'tmp_name' => $tmp,
        ];

        // Import to media library
        $attachmentId = media_handle_sideload($file_array, 0);

        if (is_wp_error($attachmentId)) {
            @unlink($tmp);
            return null;
        }

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
            error_log("CrawlFlow Phase 3: Error detecting worker priority for URL {$url}: " . $e->getMessage());
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
        $table = $wpdb->prefix . 'rake_data_sources';

        $config = json_decode($wpdb->get_var($wpdb->prepare(
            "SELECT config FROM {$table} WHERE id = %d",
            $resourceId
        )), true);

        $config['status'] = 'processed';
        $config['processed_at'] = current_time('mysql');
        if ($result['new_url']) {
            $config['new_url'] = $result['new_url'];
        }

        $wpdb->update(
            $table,
            ['config' => json_encode($config)],
            ['id' => $resourceId]
        );
    }
}

