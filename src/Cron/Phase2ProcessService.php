<?php

namespace CrawlFlow\Cron;

use CrawlFlow\Cron\ParsedItemVersioningService;
use CrawlFlow\Cron\ProjectCacheService;
use CrawlFlow\Cron\TrackedWorker;
use CrawlFlow\Cron\WorkerCacheService;
use CrawlFlow\DataSources\HttpDataSource;
use CrawlFlow\Flow\FlowService;
use CrawlFlow\LoggerService;
use CrawlFlow\Reception\Reception;
use CrawlFlow\Worker\Worker;
use Rake\Facade\Logger;
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
     * @var int
     */
    private int $currentProjectId = 0;

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
            Logger::info("CrawlFlow Phase 2: Starting processing for project {$projectId}");
            
            // Update missing ignore reasons for existing ignored items
            $this->updateMissingIgnoreReasons($projectId);

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

            Logger::info("CrawlFlow Phase 2: Flow config loaded successfully for project {$projectId}");

            // Determine batch size from project settings (concurrency = max items per cron run)
            $projectSettings = $flowConfig['projectSettings'] ?? [];
            $maxItemsPerRun = (int)($projectSettings['concurrency'] ?? 50);
            if ($maxItemsPerRun <= 0) {
                $maxItemsPerRun = 50; // sensible fallback
            }

            Logger::info("CrawlFlow Phase 2: Batch size set to {$maxItemsPerRun} for project {$projectId}");

            // Get raw items from rake_data_origins for this project
            Logger::info("CrawlFlow Phase 2: About to call getRawItems() for project {$projectId} with limit {$maxItemsPerRun}");
            $rawItems = $this->getRawItems($projectId, $maxItemsPerRun);
            Logger::info("CrawlFlow Phase 2: getRawItems() returned " . (is_array($rawItems) ? count($rawItems) : 'NOT_ARRAY') . " items for project {$projectId}");

            if (empty($rawItems)) {
                Logger::warning("CrawlFlow Phase 2: No raw items found for project {$projectId}");
                return [
                    'project_id' => $projectId,
                    'phase' => 'process',
                    'items_processed' => 0,
                    'items_success' => 0,
                    'items_failed' => 0,
                    'resources_detected' => 0,
                    'errors' => [],
                ];
            }

            // Get Reception instance (cached by project ID)
            $reception = $this->workerCacheService->getReception($projectId, $flowConfig);

            // Store project ID for use in processRawItemsWithVersioning
            $this->currentProjectId = $projectId;

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

            Logger::info(sprintf(
                "CrawlFlow Phase 2: Completed for project %d - Processed: %d, Success: %d, Resources: %d",
                $projectId,
                $summary['items_processed'],
                $summary['items_success'],
                $summary['resources_detected']
            ));

            return $summary;

        } catch (\Exception $e) {
            Logger::error("CrawlFlow Phase 2: Failed for project {$projectId} - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get raw items from rake_data_origins for project
     * Includes both parent origins (with source_id) and child origins (via references)
     */
    private function getRawItems(int $projectId, int $limit = 50): array
    {
        global $wpdb;
        
        $originsTable = $wpdb->prefix . 'rake_data_origins';
        $sourcesTable = $wpdb->prefix . 'rake_data_sources';
        $referencesTable = $wpdb->prefix . 'rake_data_origins_references';
        $parsedItemsTable = $wpdb->prefix . 'rake_data_parsed_items';

        // Simplified query to get items for project - handle both parent and child origins
        $query = $wpdb->prepare(
            "SELECT DISTINCT o.* 
            FROM {$originsTable} o
            LEFT JOIN (
                SELECT origin_id, MAX(version) as max_version
                FROM {$parsedItemsTable}
                GROUP BY origin_id
            ) AS latest_parsed ON o.id = latest_parsed.origin_id
            WHERE (
                -- Parent origins (with source_id linked to project)
                (o.source_id IS NOT NULL AND EXISTS (
                    SELECT 1 FROM {$sourcesTable} s 
                    WHERE s.id = o.source_id AND s.tooth_id = %d
                ))
                OR
                -- Child origins (source_id = NULL) - include all child items
                o.source_id IS NULL
            )
            AND (o.crawled = 0 OR o.crawled IS NULL) -- Only get items that haven't been crawled yet
            AND (o.ignored = 0 OR o.ignored IS NULL) -- Only get items that are not ignored
            AND (o.process_id IS NULL OR o.process_id = 0) -- Only items not claimed by another process
            AND latest_parsed.origin_id IS NULL -- Only get items that have never been parsed
            ORDER BY 
                o.priority ASC, -- Order by priority (lower priority = higher priority for processing)
                CASE WHEN o.source_id IS NULL THEN 0 ELSE 1 END, -- Prioritize child origins (products/categories) over parent origins
                o.fetched_at ASC
            LIMIT %d",
            $projectId,
            $limit
        );

        // Debug: Log the query and check individual conditions
        Logger::debug("CrawlFlow Phase 2: Query for project {$projectId} - LIMIT: {$limit}");
        Logger::debug("CrawlFlow Phase 2: SQL Query: " . $query);
        
        $results = $wpdb->get_results($query, ARRAY_A);
        $count = is_array($results) ? count($results) : 0;
        
        // Debug: Check all tables first to understand the schema
        global $wpdb;
        $allTables = $wpdb->get_results("SHOW TABLES", ARRAY_A);
        $tableList = array_map(function($table) { return array_values($table)[0]; }, $allTables);
        Logger::debug("CrawlFlow Phase 2: All tables in database: " . implode(', ', $tableList));
        
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
            Logger::warning("CrawlFlow Phase 2: Neither origins table found. Looking for similar tables...");
            $similarTables = array_filter($tableList, function($table) {
                return strpos($table, 'origin') !== false || strpos($table, 'rake') !== false;
            });
            Logger::info("CrawlFlow Phase 2: Similar tables: " . implode(', ', $similarTables));
            return [];
        }
        
        Logger::info("CrawlFlow Phase 2: Using table: {$actualTableName}");
        
        // Check table structure
        $tableStructureQuery = "DESCRIBE {$actualTableName}";
        $tableStructure = $wpdb->get_results($tableStructureQuery, ARRAY_A);
        $columnNames = array_map(function($col) { return $col['Field']; }, $tableStructure);
        Logger::debug("CrawlFlow Phase 2: Table {$actualTableName} columns: " . implode(', ', $columnNames));
        
        // Origins table doesn't have direct project column - it uses JOIN via sources table
        // So we don't need to find project column here, just use the main query which already has proper JOINs
        Logger::debug("CrawlFlow Phase 2: Origins table uses JOIN via sources table - no direct project column needed");
        
        // Update all queries to use actual table name
        $originsTable = $actualTableName; // Use the actual table name
        
        // Debug: Check total items in origins table for this project using JOIN
        $totalCountQuery = $wpdb->prepare(
            "SELECT COUNT(*) as total FROM {$originsTable} o 
             LEFT JOIN {$wpdb->prefix}rake_data_sources s ON o.source_id = s.id 
             WHERE s.tooth_id = %d",
            $projectId
        );
        $totalCount = $wpdb->get_var($totalCountQuery);
        
        // Debug: Check items by status using JOIN
        $crawledCountQuery = $wpdb->prepare(
            "SELECT COUNT(*) as count FROM {$originsTable} o 
             LEFT JOIN {$wpdb->prefix}rake_data_sources s ON o.source_id = s.id 
             WHERE s.tooth_id = %d AND (o.crawled = 1)",
            $projectId
        );
        $crawledCount = $wpdb->get_var($crawledCountQuery);
        
        $ignoredCountQuery = $wpdb->prepare(
            "SELECT COUNT(*) as count FROM {$originsTable} o 
             LEFT JOIN {$wpdb->prefix}rake_data_sources s ON o.source_id = s.id 
             WHERE s.tooth_id = %d AND (o.ignored = 1)",
            $projectId
        );
        $ignoredCount = $wpdb->get_var($ignoredCountQuery);
        
        $processedCountQuery = $wpdb->prepare(
            "SELECT COUNT(*) as count FROM {$originsTable} o 
             LEFT JOIN {$wpdb->prefix}rake_data_sources s ON o.source_id = s.id 
             WHERE s.tooth_id = %d AND (o.process_id IS NOT NULL AND o.process_id > 0)",
            $projectId
        );
        $processedCount = $wpdb->get_var($processedCountQuery);
        
        Logger::info("CrawlFlow Phase 2: Project {$projectId} stats - Total: {$totalCount}, Crawled: {$crawledCount}, Ignored: {$ignoredCount}, Processed: {$processedCount}, Available for processing: {$count}");

        // Claim items with current process_id in a single query to avoid duplicate processing
        if ($count > 0) {
            $ids = array_map(function ($row) {
                return (int)($row['id'] ?? 0);
            }, $results);
            $ids = array_filter($ids);
            if (!empty($ids)) {
                $pid = getmypid();
                $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$originsTable} SET process_id = %d WHERE id IN ({$placeholders})",
                    array_merge([$pid], $ids)
                ));
            }
        }

        Logger::info("CrawlFlow Phase 2: Found {$count} raw items for project {$projectId}");
        
        // ALWAYS log detailed information about items (even if empty for debugging)
        Logger::info("CrawlFlow Phase 2: Items to be processed in detail:");
        
        if (!is_array($results)) {
            Logger::error("CrawlFlow Phase 2: ERROR - Results is not an array: " . gettype($results));
            return [];
        }
        
        if ($count === 0) {
            Logger::debug("CrawlFlow Phase 2: NO ITEMS FOUND - Debugging database query:");
            Logger::debug("CrawlFlow Phase 2: - Query executed: SELECT o.*, s.tooth_id FROM {$originsTable} o LEFT JOIN {$wpdb->prefix}rake_data_sources s ON o.source_id = s.id WHERE s.tooth_id = {$projectId} AND (o.crawled = 0 OR o.crawled IS NULL) AND (o.ignored = 0 OR o.ignored IS NULL) AND (o.process_id IS NULL OR o.process_id = 0) AND latest_parsed.origin_id IS NULL LIMIT 100");
            Logger::debug("CrawlFlow Phase 2: - Table used: {$originsTable}");
            Logger::debug("CrawlFlow Phase 2: - Project ID: {$projectId}");
            
            // Check if table exists
            $tableCheck = $wpdb->get_var("SHOW TABLES LIKE '{$originsTable}'");
            Logger::debug("CrawlFlow Phase 2: - Table exists: " . ($tableCheck ? 'YES' : 'NO'));
            
            // Check total records in table
            $totalRecords = $wpdb->get_var("SELECT COUNT(*) FROM {$originsTable}");
            Logger::debug("CrawlFlow Phase 2: - Total records in table: {$totalRecords}");
            
            // Check records for this project (without filters)
            $projectRecords = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$originsTable} o LEFT JOIN {$wpdb->prefix}rake_data_sources s ON o.source_id = s.id WHERE s.tooth_id = %d",
                $projectId
            ));
            Logger::debug("CrawlFlow Phase 2: - Records for project {$projectId}: {$projectRecords}");
        } else {
            foreach ($results as $index => $item) {
                $originId = $item['id'] ?? 'unknown';
                $guid = $item['guid'] ?? 'no_guid';
                $sourceId = $item['source_id'] ?? 'no_source';
                $crawled = $item['crawled'] ?? 0;
                $ignored = $item['ignored'] ?? 0;
                $processId = $item['process_id'] ?? 0;
                
                Logger::debug("CrawlFlow Phase 2: Item #{$index} - ID: {$originId}, GUID: {$guid}, Source: {$sourceId}, Crawled: {$crawled}, Ignored: {$ignored}, ProcessID: {$processId}");
                
                // Log first few items' raw_data size for debugging
                if ($index < 3 && !empty($item['raw_data'])) {
                    $dataSize = strlen($item['raw_data']);
                    $dataPreview = substr($item['raw_data'], 0, 100) . '...';
                    Logger::debug("CrawlFlow Phase 2: Item #{$index} raw_data size: {$dataSize} bytes, preview: {$dataPreview}");
                }
            }
        }
        
        return $results;
    }

    /**
     * Update ignore reasons for existing ignored items without reasons
     */
    private function updateMissingIgnoreReasons(int $projectId): void
    {
        global $wpdb;
        $originsTable = $wpdb->prefix . 'rake_data_origins';
        $sourcesTable = $wpdb->prefix . 'rake_data_sources';
        
        // Find items that are ignored=1 but have NULL ignore_reason
        $itemsWithoutReason = $wpdb->get_results($wpdb->prepare(
            "SELECT o.id, o.guid FROM {$originsTable} o
             LEFT JOIN {$sourcesTable} s ON o.source_id = s.id
             WHERE s.tooth_id = %d AND o.ignored = 1 AND (o.ignore_reason IS NULL OR o.ignore_reason = '')",
            $projectId
        ), ARRAY_A);
        
        if (!empty($itemsWithoutReason)) {
            Logger::info("CrawlFlow Phase 2: Found " . count($itemsWithoutReason) . " ignored items without reasons, updating...");
            
            foreach ($itemsWithoutReason as $item) {
                $ignoreReason = $this->determineIgnoreReason(['guid' => $item['guid']]);
                
                $wpdb->update(
                    $originsTable,
                    [
                        'ignore_reason' => $ignoreReason,
                        'updated_at' => current_time('mysql'),
                    ],
                    ['id' => $item['id']],
                    ['%s', '%s'],
                    ['%d']
                );
                
                Logger::debug("CrawlFlow Phase 2: Updated ignore reason for item {$item['id']}: {$ignoreReason}");
            }
        }
    }

    /**
     * Determine ignore reason based on URL characteristics
     */
    private function determineIgnoreReason(array $rawItem): string
    {
        $guid = $rawItem['guid'] ?? '';
        
        // Check if URL has resource file extensions
        $resourceExtensions = [
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'ico',
            'css', 'js', 'scss', 'less', 'sass',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'zip', 'rar', 'tar', 'gz', '7z',
            'mp3', 'mp4', 'avi', 'mov', 'wmv', 'flv',
            'txt', 'xml', 'json', 'csv', 'log'
        ];
        
        $path = parse_url($guid, PHP_URL_PATH);
        if ($path) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($extension, $resourceExtensions)) {
                return 'Resource URL (should be handled in Phase 3)';
            }
        }
        
        // Check domain policy violations
        if ($this->isDomainPolicyViolation($guid)) {
            return 'Domain policy violation (different domain)';
        }
        
        // Check exclude patterns
        if ($this->isExcludePatternViolation($guid)) {
            return 'Exclude pattern violation';
        }
        
        // Default: no suitable worker found
        return 'No suitable worker found';
    }

    /**
     * Check if URL violates domain policy
     */
    private function isDomainPolicyViolation(string $url): bool
    {
        // Get project flow config to check domain policy
        $flowConfig = $this->projectCacheService->getFlowConfig($this->currentProjectId);
        if (!$flowConfig) {
            return false;
        }
        
        $nodes = $flowConfig['nodes'] ?? [];
        $baseUrl = '';
        
        // Find the first URL source node to get base domain
        foreach ($nodes as $node) {
            if ($node['type'] === 'url' && isset($node['data']['config']['url'])) {
                $baseUrl = $node['data']['config']['url'];
                break;
            }
        }
        
        if (empty($baseUrl)) {
            return false;
        }
        
        $baseDomain = parse_url($baseUrl, PHP_URL_HOST);
        $targetDomain = parse_url($url, PHP_URL_HOST);
        
        if (!$baseDomain || !$targetDomain) {
            return false;
        }
        
        // Check if domains are different
        return $baseDomain !== $targetDomain;
    }

    /**
     * Check if URL violates exclude patterns
     */
    private function isExcludePatternViolation(string $url): bool
    {
        // Get project flow config to check exclude patterns
        $flowConfig = $this->projectCacheService->getFlowConfig($this->currentProjectId);
        if (!$flowConfig) {
            return false;
        }
        
        $nodes = $flowConfig['nodes'] ?? [];
        $excludePatterns = [];
        
        // Find exclude patterns from URL source nodes
        foreach ($nodes as $node) {
            if ($node['type'] === 'url' && isset($node['data']['config']['urlSettings']['excludePatterns'])) {
                $excludePatterns = array_merge($excludePatterns, $node['data']['config']['urlSettings']['excludePatterns']);
            }
        }
        
        if (empty($excludePatterns)) {
            return false;
        }
        
        // Check against exclude patterns
        foreach ($excludePatterns as $pattern) {
            if (empty($pattern)) continue;
            
            $normalizedPattern = $this->normalizeRegexPattern($pattern);
            if ($normalizedPattern && @preg_match($normalizedPattern, $url)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Normalize regex pattern for URL matching
     */
    private function normalizeRegexPattern(string $pattern): ?string
    {
        if (empty($pattern)) {
            return null;
        }
        
        // If pattern doesn't look like regex, convert to simple match
        if (!preg_match('/^\/.*\/[imsxADSUXJu]*$/', $pattern)) {
            // Escape special regex characters and convert to wildcard pattern
            $pattern = preg_quote($pattern, '/');
            $pattern = str_replace('\\*', '.*', $pattern);
            return '/^' . $pattern . '$/';
        }
        
        return $pattern;
    }

    /**
     * Ensure process_id column exists on origins table
     */
    private function maybeAddProcessIdColumn(string $originsTable): void
    {
        global $wpdb;
        $column = $wpdb->get_var($wpdb->prepare(
            "SHOW COLUMNS FROM {$originsTable} LIKE %s",
            'process_id'
        ));

        if (!$column) {
            $wpdb->query("ALTER TABLE {$originsTable} ADD COLUMN process_id BIGINT NULL DEFAULT NULL AFTER source_id");
        }
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
                Logger::info("CrawlFlow Phase 2: Creating resource entry for: {$guid}");
                
                // Create resource entry in rake_resources table
                $resourceId = $this->createResourceEntry($this->currentProjectId, $guid, $originId);
                
                if ($resourceId) {
                    Logger::info("CrawlFlow Phase 2: Successfully created resource entry {$resourceId} for: {$guid}");
                } else {
                    Logger::error("CrawlFlow Phase 2: Failed to create resource entry for: {$guid}");
                }
                
                // Mark origin as ignored to prevent reprocessing
                global $wpdb;
                $table = $wpdb->prefix . 'rake_data_origins';
                
                $updateResult = $wpdb->update(
                    $table,
                    [
                        'ignored' => 1,
                        'ignore_reason' => 'Resource URL (should be handled in Phase 3)',
                        'crawled' => 0,
                        'updated_at' => current_time('mysql'),
                    ],
                    ['id' => $originId],
                    ['%d', '%s', '%d', '%s'],
                    ['%d']
                );
                
                if ($updateResult === false) {
                    Logger::error("CrawlFlow Phase 2: Failed to update ignored status for origin {$originId} - " . $wpdb->last_error);
                } else {
                    Logger::info("CrawlFlow Phase 2: Successfully updated ignored status for origin {$originId} - Resource URL (should be handled in Phase 3)");
                }
                
                $results[] = [
                    'success' => false,
                    'item_id' => $originId,
                    'error' => "ID:{$originId} - Resource URL (should be handled in Phase 3)",
                ];
                continue;
            }
            
            if ($crawled === 0 || empty($rawData)) {
                // Fetch raw_data before processing
                if (!empty($guid) && filter_var($guid, FILTER_VALIDATE_URL)) {
                    Logger::info("CrawlFlow Phase 2: Fetching raw_data for origin {$originId} (URL: {$guid})");
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
                        Logger::info("CrawlFlow Phase 2: Successfully fetched raw_data for origin {$originId}");
                    } else {
                        // Fetch failed - mark as ignored
                        global $wpdb;
                        $table = $wpdb->prefix . 'rake_data_origins';
                        
                        $updateResult = $wpdb->update(
                            $table,
                            [
                                'ignored' => 1,
                                'ignore_reason' => 'Failed to fetch raw_data',
                                'crawled' => 0,
                                'updated_at' => current_time('mysql'),
                            ],
                            ['id' => $originId],
                            ['%d', '%s', '%d', '%s'],
                            ['%d']
                        );
                        
                        if ($updateResult === false) {
                            Logger::error("CrawlFlow Phase 2: Failed to update ignored status for origin {$originId} - " . $wpdb->last_error);
                        } else {
                            Logger::info("CrawlFlow Phase 2: Successfully updated ignored status for origin {$originId} - Failed to fetch raw_data");
                        }
                        
                        $results[] = [
                            'success' => false,
                            'item_id' => $originId,
                            'error' => "ID:{$originId} - Failed to fetch raw_data",
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
            Logger::debug("CrawlFlow Phase 2: Processing origin {$originId}, guid: " . ($rawItem['guid'] ?? 'NOT SET') . ", keys: " . implode(', ', array_keys($rawItem)));
            
            // Find appropriate worker
            $worker = $reception->assignToWorker($rawItem);

            if (!$worker) {
                // No worker can handle this item - determine ignore reason and mark as ignored
                $ignoreReason = $this->determineIgnoreReason($rawItem);
                
                global $wpdb;
                $table = $wpdb->prefix . 'rake_data_origins';
                
                $updateResult = $wpdb->update(
                    $table,
                    [
                        'ignored' => 1,
                        'ignore_reason' => $ignoreReason,
                        'crawled' => 0,
                        'updated_at' => current_time('mysql'),
                    ],
                    ['id' => $originId],
                    ['%d', '%s', '%d', '%s'],
                    ['%d']
                );
                
                if ($updateResult === false) {
                    Logger::error("CrawlFlow Phase 2: Failed to update ignored status for origin {$originId} - " . $wpdb->last_error);
                } else {
                    Logger::info("CrawlFlow Phase 2: Successfully updated ignored status for origin {$originId} - {$ignoreReason}");
                }
                
                Logger::info("CrawlFlow Phase 2: No worker found for origin {$originId}, marked as ignored - {$ignoreReason}");
                $results[] = [
                    'success' => false,
                    'item_id' => $originId,
                    'error' => "ID:{$originId} - {$ignoreReason}",
                ];
                continue;
            }

            // If worker has isArchive flag, set is_archive flag in database
            if ($worker->isArchive()) {
                global $wpdb;
                $table = $wpdb->prefix . 'rake_data_origins';
                
                // Get current metadata to preserve it
                $currentMetadata = $wpdb->get_var($wpdb->prepare(
                    "SELECT metadata FROM {$table} WHERE id = %d",
                    $originId
                ));
                
                $metadataSource = is_string($currentMetadata) ? $currentMetadata : '';
                $metadata = json_decode($metadataSource ?: '{}', true) ?: [];
                $metadata['project_id'] = $this->currentProjectId; // Store project_id for filtering
                
                $wpdb->update(
                    $table,
                    [
                        'is_archive' => 1,
                        'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'updated_at' => current_time('mysql'),
                    ],
                    ['id' => $originId],
                    ['%d', '%s', '%s'],
                    ['%d']
                );
                Logger::info("CrawlFlow Phase 2: Set is_archive=1 for origin {$originId} (worker: {$worker->getName()})");
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
                Logger::error("CrawlFlow Phase 2: Error processing origin {$originId} - " . $e->getMessage());
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
                Logger::error("CrawlFlow Phase 2: Failed to fetch {$url} - Status: " . ($response['status_code'] ?? 'unknown'));
                return false;
            }
        } catch (\Exception $e) {
            Logger::error("CrawlFlow Phase 2: Exception fetching {$url}: " . $e->getMessage());
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
            
            // Get processor_id from result (usually 'collect_resources' or worker name)
            $processorId = $result['processor_id'] ?? $result['worker'] ?? 'collect_resources';
            
            // Extract resources from processed data
            $resources = $this->extractResources($processedData, $rawItem);

            foreach ($resources as $resource) {
                // Save to rake_data_sources if not exists
                $resourceId = $this->saveResource($projectId, $resource);
                
                if ($resourceId) {
                    // Save reference link with processor_id
                    $this->saveResourceReference($projectId, $rawItem['id'], $resourceId, $resource['type'], $processorId);
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
            /** @var \DOMElement $img */
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
            /** @var \DOMElement $link */
            $href = $link->getAttribute('href');
            if ($href && !preg_match('/^(#|javascript:)/', $href)) {
                $resources[] = [
                    'url' => $this->resolveUrl($rawItem['guid'] ?? '', $href),
                    'type' => 'url',
                ];
            }
        }

        // Extract files (PDF, DOC, etc.)
        $fileLinks = $xpath->query('//a[contains(@href, ".pdf") or contains(@href, ".doc") or contains(@href, ".xls") or contains(@href, ".zip")]');
        foreach ($fileLinks as $fileLink) {
            /** @var \DOMElement $fileLink */
            $href = @$fileLink->getAttribute('href'); // Suppress lint error
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
     * Create resource entry in rake_resources table
     */
    private function createResourceEntry(int $projectId, string $url, int $originId): ?int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_resources';
        
        // Check if already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE guid = %s",
            $url
        ));
        
        if ($existing) {
            return (int)$existing;
        }
        
        // Determine resource type from URL
        $path = parse_url($url, PHP_URL_PATH);
        $dataType = 'url'; // default
        if ($path) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'])) {
                $dataType = 'image';
            } elseif (in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar'])) {
                $dataType = 'file';
            }
        }
        
        // Insert new resource
        $result = $wpdb->insert(
            $table,
            [
                'parent_id' => null,
                'tooth_id' => $projectId,
                'data_type' => $dataType,
                'guid' => $url,
                'current_content' => '',
                'app_data_type' => '',
                'app_guid' => '',
                'import_status' => 'pending',
                'import_retry' => 0,
                'imported_at' => null,
                'metadata' => json_encode([
                    'source_url' => $url,
                    'origin_id' => $originId,
                    'created_from' => 'phase2_resource_detection'
                ]),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            [
                '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', null, '%s', '%s', '%s'
            ]
        );
        
        return $result ? (int)$wpdb->insert_id : null;
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
    private function saveResourceReference(int $projectId, int $parentOriginId, int $resourceId, string $type, ?string $processorId = null): void
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
            // Detect worker priority for URL
            $priority = 100; // Default priority
            try {
                $flowConfig = $this->projectCacheService->getFlowConfig($projectId);
                if ($flowConfig && !empty($childUrl) && filter_var($childUrl, FILTER_VALIDATE_URL)) {
                    $workerCacheService = new WorkerCacheService();
                    $reception = $workerCacheService->getReception($projectId, $flowConfig);
                    $workers = $reception->getWorkers();
                    
                    // Create a mock raw item for detection
                    $mockRawItem = [
                        'id' => 0,
                        'guid' => $childUrl,
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
                Logger::error("CrawlFlow Phase 2: Error detecting worker priority for URL {$childUrl}: " . $e->getMessage());
            }
            
            // Create child origin if not exists (from processor)
            $wpdb->insert($originsTable, [
                'source_id' => null, // Resource doesn't have source_id
                'guid' => $childUrl,
                'raw_data' => '',
                'fetched_at' => current_time('mysql'),
                'source_type' => 'processor',
                'processor_id' => $processorId,
                'priority' => $priority,
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
            Logger::info("CrawlFlow Phase 2: No finish actions configured for project {$projectId}");
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
                            Logger::debug("CrawlFlow Phase 2: Origin {$originId} version {$latestVersion} marked as saved after complete action");
                        }
                    }
                } catch (\Exception $e) {
                    Logger::error("CrawlFlow Phase 2: Error executing finish action {$actionType} for origin {$originId} - " . $e->getMessage());
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
                Logger::info("CrawlFlow Phase 2: WooCommerce product saving not yet implemented");
                return false;

            case 'log_summary':
                Logger::info(sprintf(
                    "CrawlFlow: Project %d - Item processed: %s",
                    $projectId,
                    json_encode($processedData)
                ));
                return true;

            case 'send_notification':
                // TODO: Implement notification sending
                Logger::info("CrawlFlow Phase 2: Notification sending not yet implemented");
                return false;

            default:
                Logger::info("CrawlFlow Phase 2: Unknown finish action type: {$actionType}");
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
        Logger::debug("CrawlFlow Phase 2: Origin {$originId} processed (not marked in DB - no processed_at column)");
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

