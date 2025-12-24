<?php

namespace CrawlFlow\Cron\Phase1;

use CrawlFlow\DataSources\HttpDataSource;

/**
 * Sitemap Data Source Handler
 * 
 * Phase 1: Only extracts URLs from sitemaps and saves them to database
 * - Fetches XML once to extract URLs (but does NOT save raw_data)
 * - Saves URLs to rake_data_origins (without raw_data)
 * - Detects worker priority for each URL
 * - Phase 2 will fetch raw_data for these URLs
 * 
 * Handles:
 * 1. Sitemap Index: First run imports all sitemap URLs, second run extracts URLs from those sitemaps
 * 2. Regular Sitemap: Directly extracts all URLs from the sitemap
 * 3. XML Raw Object: Marks for Phase 2 processing with XML extractor
 */
class SitemapDataSourceHandler extends AbstractDataSourceHandler
{
    /**
     * Get supported data source type
     */
    public function getSupportedType(): string
    {
        return 'sitemap';
    }

    /**
     * Process sitemap data source
     */
    public function process(int $projectId, array $source, array $flowConfig): array
    {
        $result = [
            'items_saved' => 0,
            'references_saved' => 0,
            'errors' => [],
        ];

        $sourceType = $source['type'] ?? 'sitemap';
        $sourceConfig = isset($source['config']) ? json_decode($source['config'], true) : [];

        if ($sourceType !== 'sitemap' || !isset($sourceConfig['url'])) {
            $result['errors'][] = 'Invalid sitemap data source configuration';
            return $result;
        }

        try {
            // Get or create source in database
            $sourceId = $this->ensureDataSourceInDb($projectId, $source);
            
            $sitemapUrl = $sourceConfig['url'];
            \Rake\Facade\Logger::info("CrawlFlow Phase 1 (Sitemap): Handler started for sitemap: {$sitemapUrl}");
            
            // Phase 1: Only save sitemap URL to database, do NOT save raw_data
            $originId = $this->saveToDataOrigins(
                $projectId, 
                $sourceId, 
                $sitemapUrl, 
                '', // Empty raw_data - Phase 1 does not fetch/crawl
                ['type' => 'sitemap', 'source_type' => 'sitemap'],
                $flowConfig
            );
            $result['items_saved']++;
            \Rake\Facade\Logger::info("CrawlFlow Phase 1 (Sitemap): Saved sitemap URL to origins: {$sitemapUrl}");

            // Phase 1: Fetch XML only to extract URLs, but do NOT save raw_data
            // This is a one-time fetch just to discover URLs
            $dataSource = new HttpDataSource();
            $response = $dataSource->fetch($sitemapUrl);

            if (isset($response['status_code']) && $response['status_code'] === 200) {
                $xmlContent = $response['body'] ?? '';
                \Rake\Facade\Logger::debug("CrawlFlow Phase 1 (Sitemap): Fetched " . strlen($xmlContent) . " bytes from {$sitemapUrl} to extract URLs (not saving raw_data)");

                // Parse XML and determine type
                $sitemapType = $this->detectSitemapType($xmlContent);
                \Rake\Facade\Logger::info("CrawlFlow Phase 1 (Sitemap): Detected sitemap type: {$sitemapType}");

                if ($sitemapType === 'index') {
                    // Sitemap Index: Extract sitemap URLs (but don't save XML)
                    $this->processSitemapIndex($projectId, $originId, $xmlContent, $sitemapUrl, $result, $sourceId, $flowConfig);
                } elseif ($sitemapType === 'sitemap') {
                    // Regular Sitemap: Extract URLs directly (but don't save XML)
                    $this->processRegularSitemap($projectId, $originId, $xmlContent, $sitemapUrl, $result, $sourceId, $flowConfig);
                } else {
                    // XML Raw Object: Mark for Phase 2 processing
                    \Rake\Facade\Logger::info("CrawlFlow Phase 1 (Sitemap): Treating as raw XML object for Phase 2 processing");
                    $this->updateOriginMetadata($originId, ['type' => 'xml-raw', 'source_type' => 'sitemap']);
                }
                
                \Rake\Facade\Logger::info("CrawlFlow Phase 1 (Sitemap): Phase 1 complete - URLs saved, no data fetched. Phase 2 will fetch raw_data.");
            } else {
                $statusCode = $response['status_code'] ?? 'unknown';
                \Rake\Facade\Logger::error("CrawlFlow Phase 1 (Sitemap): Failed to fetch {$sitemapUrl} for URL extraction - Status: {$statusCode}");
                // Don't treat this as error - we still saved the sitemap URL
                // Phase 2 will try to fetch it
            }

        } catch (\Exception $e) {
            \Rake\Facade\Logger::error("CrawlFlow Phase 1 (Sitemap): Error processing source - " . $e->getMessage());
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Detect sitemap type: index, sitemap, or raw XML
     */
    private function detectSitemapType(string $xmlContent): string
    {
        // Try to parse as XML
        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($xmlContent);
        
        if ($xml === false) {
            // Not valid XML, treat as raw
            return 'raw';
        }

        // Check for sitemap index namespace
        $namespaces = $xml->getNamespaces(true);
        $sitemapIndexNs = null;
        foreach ($namespaces as $prefix => $ns) {
            if (strpos($ns, 'sitemap') !== false && strpos($ns, 'index') !== false) {
                $sitemapIndexNs = $ns;
                break;
            }
        }

        // Default sitemap namespace
        $sitemapNs = 'http://www.sitemaps.org/schemas/sitemap/0.9';
        
        // Check if it's a sitemap index (has <sitemap> elements)
        if ($xml->getName() === 'sitemapindex' || 
            (isset($xml->sitemap) && count($xml->sitemap) > 0) ||
            ($sitemapIndexNs && isset($xml->children($sitemapIndexNs)->sitemap))) {
            return 'index';
        }

        // Check if it's a regular sitemap (has <url> elements)
        if ($xml->getName() === 'urlset' || 
            (isset($xml->url) && count($xml->url) > 0) ||
            (isset($xml->children($sitemapNs)->url))) {
            return 'sitemap';
        }

        // Otherwise, treat as raw XML
        return 'raw';
    }

    /**
     * Process sitemap index (2-step process)
     * Step 1: Import all sitemap URLs as "sitemap" type origins
     * Step 2: On next run, fetch those sitemap URLs and extract URLs
     */
    private function processSitemapIndex(int $projectId, int $parentOriginId, string $xmlContent, string $baseUrl, array &$result, int $sourceId, array $flowConfig): void
    {
        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($xmlContent);
        
        if ($xml === false) {
            \Rake\Facade\Logger::error("CrawlFlow Phase 1 (Sitemap): Failed to parse sitemap index XML");
            return;
        }

        // Get sitemap namespace
        $namespaces = $xml->getNamespaces(true);
        $sitemapNs = 'http://www.sitemaps.org/schemas/sitemap/0.9';
        foreach ($namespaces as $prefix => $ns) {
            if (strpos($ns, 'sitemap') !== false) {
                $sitemapNs = $ns;
                break;
            }
        }

        // Check if we have already processed sitemap URLs (step 1 completed)
        // Look for child origins with type 'sitemap' that haven't been crawled yet
        global $wpdb;
        $originsTable = $wpdb->prefix . 'rake_data_origins';
        $referencesTable = $wpdb->prefix . 'rake_data_origins_references';
        
        $existingSitemaps = $wpdb->get_results($wpdb->prepare(
            "SELECT o.* FROM {$originsTable} o
            INNER JOIN {$referencesTable} r ON o.id = r.child_origin_id
            WHERE r.parent_origin_id = %d 
            AND JSON_EXTRACT(o.metadata, '$.type') = 'sitemap'
            AND (o.crawled = 0 OR o.crawled IS NULL)
            LIMIT 100",
            $parentOriginId
        ), ARRAY_A);

        if (!empty($existingSitemaps)) {
            // Step 2: Fetch sitemap URLs and extract URLs from them
            \Rake\Facade\Logger::info("CrawlFlow Phase 1 (Sitemap): Step 2 - Processing " . count($existingSitemaps) . " sitemap URLs");
            $this->processSitemapUrls($projectId, $existingSitemaps, $result, $sourceId, $flowConfig);
        } else {
            // Step 1: Extract and save all sitemap URLs
            \Rake\Facade\Logger::info("CrawlFlow Phase 1 (Sitemap): Step 1 - Extracting sitemap URLs from index");
            
            $sitemapUrls = [];
            if (isset($xml->sitemap)) {
                foreach ($xml->sitemap as $sitemap) {
                    $loc = (string)($sitemap->loc ?? '');
                    if (!empty($loc)) {
                        $sitemapUrls[] = $loc;
                    }
                }
            } else {
                // Try with namespace
                $sitemaps = $xml->children($sitemapNs)->sitemap ?? [];
                foreach ($sitemaps as $sitemap) {
                    $loc = (string)($sitemap->loc ?? '');
                    if (!empty($loc)) {
                        $sitemapUrls[] = $loc;
                    }
                }
            }

            \Rake\Facade\Logger::info("CrawlFlow Phase 1 (Sitemap): Found " . count($sitemapUrls) . " sitemap URLs in index");
            
            // Save each sitemap URL as a child origin with type 'sitemap' (without raw_data)
            foreach ($sitemapUrls as $sitemapUrl) {
                $childOriginId = $this->saveToDataOrigins(
                    $projectId,
                    $sourceId > 0 ? $sourceId : null, // Use same source_id as parent
                    $sitemapUrl,
                    '', // Empty raw_data - Phase 1 does not fetch/crawl
                    ['type' => 'sitemap', 'source_type' => 'sitemap', 'parent_sitemap_index' => $baseUrl],
                    $flowConfig
                );
                
                if ($childOriginId) {
                    $this->saveReference($parentOriginId, $childOriginId, 'sitemap');
                    $result['references_saved']++;
                }
            }
        }
    }

    /**
     * Process regular sitemap (extract URLs directly)
     */
    private function processRegularSitemap(int $projectId, int $parentOriginId, string $xmlContent, string $baseUrl, array &$result, int $sourceId, array $flowConfig): void
    {
        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($xmlContent);
        
        if ($xml === false) {
            \Rake\Facade\Logger::error("CrawlFlow Phase 1 (Sitemap): Failed to parse sitemap XML");
            return;
        }

        // Get sitemap namespace
        $namespaces = $xml->getNamespaces(true);
        $sitemapNs = 'http://www.sitemaps.org/schemas/sitemap/0.9';
        foreach ($namespaces as $prefix => $ns) {
            if (strpos($ns, 'sitemap') !== false) {
                $sitemapNs = $ns;
                break;
            }
        }

        $urls = [];
        
        // Extract URLs from sitemap
        if (isset($xml->url)) {
            foreach ($xml->url as $url) {
                $loc = (string)($url->loc ?? '');
                if (!empty($loc)) {
                    $urls[] = $loc;
                }
            }
        } else {
            // Try with namespace
            $urlElements = $xml->children($sitemapNs)->url ?? [];
            foreach ($urlElements as $url) {
                $loc = (string)($url->loc ?? '');
                if (!empty($loc)) {
                    $urls[] = $loc;
                }
            }
        }

        \Rake\Facade\Logger::info("CrawlFlow Phase 1 (Sitemap): Extracted " . count($urls) . " URLs from regular sitemap");
        
        // Save each URL as a child origin with type 'url' (without raw_data)
        foreach ($urls as $url) {
            $childOriginId = $this->saveToDataOrigins(
                $projectId,
                $sourceId > 0 ? $sourceId : null, // Use same source_id as parent
                $url,
                '', // Empty raw_data - Phase 1 does not fetch/crawl
                ['type' => 'url', 'source_type' => 'sitemap', 'parent_sitemap' => $baseUrl],
                $flowConfig
            );
            
            if ($childOriginId) {
                $this->saveReference($parentOriginId, $childOriginId, 'url');
                $result['references_saved']++;
            }
        }
    }

    /**
     * Process sitemap URLs (Step 2 of sitemap index processing)
     * Fetch each sitemap to extract URLs, but do NOT save raw_data
     */
    private function processSitemapUrls(int $projectId, array $sitemapOrigins, array &$result, int $sourceId, array $flowConfig): void
    {
        $dataSource = new HttpDataSource();
        
        foreach ($sitemapOrigins as $sitemapOrigin) {
            $sitemapUrl = $sitemapOrigin['guid'];
            $sitemapOriginId = (int)$sitemapOrigin['id'];
            
            try {
                // Phase 1: Fetch XML only to extract URLs, do NOT save raw_data
                \Rake\Facade\Logger::debug("CrawlFlow Phase 1 (Sitemap): Fetching sitemap URL: {$sitemapUrl} to extract URLs (not saving raw_data)");
                $response = $dataSource->fetch($sitemapUrl);
                
                if (isset($response['status_code']) && $response['status_code'] === 200) {
                    $xmlContent = $response['body'] ?? '';
                    \Rake\Facade\Logger::debug("CrawlFlow Phase 1 (Sitemap): Fetched " . strlen($xmlContent) . " bytes from {$sitemapUrl} (not saving)");
                    
                    // Extract URLs from this sitemap (but don't save the XML)
                    libxml_use_internal_errors(true);
                    $xml = @simplexml_load_string($xmlContent);
                    
                    if ($xml !== false) {
                        $urls = [];
                        
                        // Get sitemap namespace
                        $namespaces = $xml->getNamespaces(true);
                        $sitemapNs = 'http://www.sitemaps.org/schemas/sitemap/0.9';
                        foreach ($namespaces as $prefix => $ns) {
                            if (strpos($ns, 'sitemap') !== false) {
                                $sitemapNs = $ns;
                                break;
                            }
                        }
                        
                        if (isset($xml->url)) {
                            foreach ($xml->url as $url) {
                                $loc = (string)($url->loc ?? '');
                                if (!empty($loc)) {
                                    $urls[] = $loc;
                                }
                            }
                        } else {
                            $urlElements = $xml->children($sitemapNs)->url ?? [];
                            foreach ($urlElements as $url) {
                                $loc = (string)($url->loc ?? '');
                                if (!empty($loc)) {
                                    $urls[] = $loc;
                                }
                            }
                        }
                        
                        \Rake\Facade\Logger::info("CrawlFlow Phase 1 (Sitemap): Extracted " . count($urls) . " URLs from {$sitemapUrl}");
                        
                        // Save each URL as a child origin (without raw_data)
                        foreach ($urls as $url) {
                            $childOriginId = $this->saveToDataOrigins(
                                $projectId,
                                $sourceId > 0 ? $sourceId : null, // Use same source_id as parent
                                $url,
                                '', // Empty raw_data - Phase 1 does not fetch/crawl
                                ['type' => 'url', 'source_type' => 'sitemap', 'parent_sitemap' => $sitemapUrl],
                                $flowConfig
                            );
                            
                            if ($childOriginId) {
                                $this->saveReference($sitemapOriginId, $childOriginId, 'url');
                                $result['references_saved']++;
                            }
                        }
                    }
                } else {
                    \Rake\Facade\Logger::error("CrawlFlow Phase 1 (Sitemap): Failed to fetch {$sitemapUrl} - Status: " . ($response['status_code'] ?? 'unknown'));
                }
            } catch (\Exception $e) {
                \Rake\Facade\Logger::error("CrawlFlow Phase 1 (Sitemap): Error processing sitemap URL {$sitemapUrl}: " . $e->getMessage());
            }
        }
    }

    /**
     * Update origin metadata only (Phase 1 does not update raw_data)
     */
    private function updateOriginMetadata(int $originId, array $metadata): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_data_origins';
        
        // Get existing metadata
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT metadata FROM {$table} WHERE id = %d",
            $originId
        ));
        
        $existingMetadata = [];
        if ($existing) {
            $decoded = json_decode($existing, true);
            if (is_array($decoded)) {
                $existingMetadata = $decoded;
            }
        }
        
        // Merge with new metadata
        $mergedMetadata = array_merge($existingMetadata, $metadata);
        
        $wpdb->update(
            $table,
            [
                'metadata' => json_encode($mergedMetadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $originId]
        );
    }
}
