<?php

namespace CrawlFlow\Cron;

use CrawlFlow\Admin\ProjectService;
use CrawlFlow\Cron\ProjectCacheService;
use CrawlFlow\DataSources\HttpDataSource;
use Rake\Rake;

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
                    $sourceResult = $this->processDataSource($projectId, $source);
                    $results['sources_processed']++;
                    $results['items_saved'] += $sourceResult['items_saved'];
                    $results['references_saved'] += $sourceResult['references_saved'];
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
     * Process a data source
     */
    private function processDataSource(int $projectId, array $source): array
    {
        $result = [
            'items_saved' => 0,
            'references_saved' => 0,
        ];

        $sourceType = $source['type'] ?? 'url';
        $sourceConfig = isset($source['config']) ? json_decode($source['config'], true) : [];

        if ($sourceType === 'url' && isset($sourceConfig['url'])) {
            try {
                // Get or create source in database
                $sourceId = $this->ensureDataSourceInDb($projectId, $source);
                
                // Fetch data using HttpDataSource
                $dataSource = new HttpDataSource();
                
                $url = $sourceConfig['url'];
                error_log("CrawlFlow Phase 1: Fetching URL: {$url}");
                
                $response = $dataSource->fetch($url);

                if (isset($response['status_code']) && $response['status_code'] === 200) {
                    $body = $response['body'] ?? '';
                    error_log("CrawlFlow Phase 1: Fetched " . strlen($body) . " bytes from {$url}");
                    
                    // Save to rake_data_origins
                    $originId = $this->saveToDataOrigins($projectId, $sourceId, $url, $body);
                    $result['items_saved']++;

                    // Extract URLs, save to origins, and create references
                    $urls = $this->extractUrls($body, $url);
                    error_log("CrawlFlow Phase 1: Extracted " . count($urls) . " URLs from {$url}");
                    
                    foreach ($urls as $extractedUrl) {
                        // Save child URL to origins (if not exists)
                        // Child URLs don't have source_id yet (will be fetched later)
                        $childOriginId = $this->saveToDataOrigins($projectId, null, $extractedUrl, '');
                        
                        // Create reference relationship
                        if ($childOriginId) {
                            $this->saveReference($originId, $childOriginId, $extractedUrl);
                            $result['references_saved']++;
                        }
                    }
                } else {
                    $statusCode = $response['status_code'] ?? 'unknown';
                    error_log("CrawlFlow Phase 1: Failed to fetch {$url} - Status: {$statusCode}");
                }
            } catch (\Exception $e) {
                error_log("CrawlFlow Phase 1: Error processing source {$source['name']}: " . $e->getMessage());
                throw $e;
            }
        }

        return $result;
    }

    /**
     * Ensure data source exists in database
     */
    private function ensureDataSourceInDb(int $projectId, array $source): ?int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_data_sources';

        // If source has ID, return it
        if (isset($source['id']) && $source['id']) {
            return (int)$source['id'];
        }

        // Check if source already exists
        $sourceConfig = isset($source['config']) ? json_decode($source['config'], true) : [];
        $url = $sourceConfig['url'] ?? '';
        
        if ($url) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE tooth_id = %d AND type = 'url' AND config LIKE %s",
                $projectId,
                '%' . $wpdb->esc_like($url) . '%'
            ));

            if ($existing) {
                return (int)$existing;
            }

            // Create new source
            $wpdb->insert($table, [
                'tooth_id' => $projectId,
                'type' => $source['type'] ?? 'url',
                'name' => $source['name'] ?? 'Data Source',
                'config' => $source['config'] ?? json_encode([]),
                'created_at' => current_time('mysql'),
            ]);

            return $wpdb->insert_id ? (int)$wpdb->insert_id : null;
        }

        return null;
    }

    /**
     * Save data to rake_data_origins
     * Returns origin ID (existing or newly created)
     */
    private function saveToDataOrigins(int $projectId, ?int $sourceId, string $guid, string $rawData): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_data_origins';

        // Check if already exists (by guid, unique)
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE guid = %s",
            $guid
        ));

        if ($existing) {
            // Update existing if we have new data
            if (!empty($rawData)) {
                $wpdb->update(
                    $table,
                    [
                        'raw_data' => $rawData,
                        'fetched_at' => current_time('mysql'),
                    ],
                    ['id' => $existing]
                );
            }
            return (int)$existing;
        }

        // Insert new
        $wpdb->insert($table, [
            'source_id' => $sourceId,
            'guid' => $guid,
            'raw_data' => $rawData,
            'fetched_at' => current_time('mysql'),
        ]);

        return (int)$wpdb->insert_id;
    }

    /**
     * Extract URLs from HTML content
     */
    private function extractUrls(string $html, string $baseUrl): array
    {
        $urls = [];
        $dom = new \DOMDocument();
        
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        
        // Extract all links
        $links = $xpath->query('//a[@href]');
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            $absoluteUrl = $this->resolveUrl($baseUrl, $href);
            if ($absoluteUrl && !in_array($absoluteUrl, $urls)) {
                $urls[] = $absoluteUrl;
            }
        }

        // Extract images
        $images = $xpath->query('//img[@src]');
        foreach ($images as $img) {
            $src = $img->getAttribute('src');
            $absoluteUrl = $this->resolveUrl($baseUrl, $src);
            if ($absoluteUrl && !in_array($absoluteUrl, $urls)) {
                $urls[] = $absoluteUrl;
            }
        }

        return $urls;
    }

    /**
     * Resolve relative URL to absolute
     */
    private function resolveUrl(string $baseUrl, string $url): ?string
    {
        if (empty($url) || $url === '#') {
            return null;
        }

        // Already absolute
        if (preg_match('/^https?:\/\//', $url)) {
            return $url;
        }

        // Resolve relative URL
        $parsed = parse_url($baseUrl);
        $base = $parsed['scheme'] . '://' . $parsed['host'];
        if (isset($parsed['port'])) {
            $base .= ':' . $parsed['port'];
        }
        $basePath = dirname($parsed['path'] ?? '/');

        if (strpos($url, '/') === 0) {
            // Absolute path
            return $base . $url;
        }

        // Relative path
        return $base . $basePath . '/' . ltrim($url, '/');
    }

    /**
     * Save reference to rake_data_origins_references
     * Now only stores relationship (parent_origin_id, child_origin_id)
     */
    private function saveReference(int $parentOriginId, int $childOriginId, string $url): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_data_origins_references';

        // Check if already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE parent_origin_id = %d AND child_origin_id = %d",
            $parentOriginId,
            $childOriginId
        ));

        if (!$existing) {
            $wpdb->insert($table, [
                'parent_origin_id' => $parentOriginId,
                'child_origin_id' => $childOriginId,
                'relationship_type' => $this->detectReferenceType($url),
                'created_at' => current_time('mysql'),
            ]);
        }
    }

    /**
     * Detect reference type (url, image, file)
     */
    private function detectReferenceType(string $url): string
    {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
        $fileExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar'];

        if (in_array($extension, $imageExtensions)) {
            return 'image';
        }
        if (in_array($extension, $fileExtensions)) {
            return 'file';
        }

        return 'url';
    }
}

