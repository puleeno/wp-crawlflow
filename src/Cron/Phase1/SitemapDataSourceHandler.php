<?php

namespace CrawlFlow\Cron\Phase1;

use CrawlFlow\DataSource\HttpDataSource;

/**
 * Sitemap Data Source Handler
 * 
 * Handles crawling and processing of XML sitemaps:
 * 1. Sitemap Index: First run imports all sitemap URLs, second run crawls URLs from those sitemaps
 * 2. Regular Sitemap: Directly imports all URLs from the sitemap
 * 3. XML Raw Object: Imports raw XML for Phase 2 processing with XML extractor
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
            
            // Fetch sitemap XML
            $dataSource = new HttpDataSource();
            $sitemapUrl = $sourceConfig['url'];
            
            error_log("CrawlFlow Phase 1 (Sitemap): Fetching sitemap: {$sitemapUrl}");
            
            $response = $dataSource->fetch($sitemapUrl);

            if (isset($response['status_code']) && $response['status_code'] === 200) {
                $xmlContent = $response['body'] ?? '';
                error_log("CrawlFlow Phase 1 (Sitemap): Fetched " . strlen($xmlContent) . " bytes from {$sitemapUrl}");
                
                // Save sitemap XML to origins
                $originId = $this->saveToDataOrigins(
                    $projectId, 
                    $sourceId, 
                    $sitemapUrl, 
                    $xmlContent,
                    ['type' => 'sitemap', 'source_type' => 'sitemap']
                );
                $result['items_saved']++;

                // Parse XML and determine type
                $sitemapType = $this->detectSitemapType($xmlContent);
                error_log("CrawlFlow Phase 1 (Sitemap): Detected sitemap type: {$sitemapType}");

                if ($sitemapType === 'index') {
                    // Sitemap Index: Process in 2 steps
                    $this->processSitemapIndex($projectId, $originId, $xmlContent, $sitemapUrl, $result);
                } elseif ($sitemapType === 'sitemap') {
                    // Regular Sitemap: Extract URLs directly
                    $this->processRegularSitemap($projectId, $originId, $xmlContent, $sitemapUrl, $result);
                } else {
                    // XML Raw Object: Save as raw XML for Phase 2 processing
                    error_log("CrawlFlow Phase 1 (Sitemap): Treating as raw XML object for Phase 2 processing");
                    // Already saved above, just mark it as raw XML
                    $this->updateOriginMetadata($originId, ['type' => 'xml-raw', 'source_type' => 'sitemap']);
                }
            } else {
                $statusCode = $response['status_code'] ?? 'unknown';
                error_log("CrawlFlow Phase 1 (Sitemap): Failed to fetch {$sitemapUrl} - Status: {$statusCode}");
                $result['errors'][] = "Failed to fetch sitemap: Status {$statusCode}";
            }

        } catch (\Exception $e) {
            error_log("CrawlFlow Phase 1 (Sitemap): Error processing source - " . $e->getMessage());
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
    private function processSitemapIndex(int $projectId, int $parentOriginId, string $xmlContent, string $baseUrl, array &$result): void
    {
        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($xmlContent);
        
        if ($xml === false) {
            error_log("CrawlFlow Phase 1 (Sitemap): Failed to parse sitemap index XML");
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
            AND o.crawled = 0
            LIMIT 100",
            $parentOriginId
        ), ARRAY_A);

        if (!empty($existingSitemaps)) {
            // Step 2: Fetch sitemap URLs and extract URLs from them
            error_log("CrawlFlow Phase 1 (Sitemap): Step 2 - Processing " . count($existingSitemaps) . " sitemap URLs");
            $this->processSitemapUrls($projectId, $existingSitemaps, $result);
        } else {
            // Step 1: Extract and save all sitemap URLs
            error_log("CrawlFlow Phase 1 (Sitemap): Step 1 - Extracting sitemap URLs from index");
            
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

            error_log("CrawlFlow Phase 1 (Sitemap): Found " . count($sitemapUrls) . " sitemap URLs in index");
            
            // Save each sitemap URL as a child origin with type 'sitemap'
            foreach ($sitemapUrls as $sitemapUrl) {
                $childOriginId = $this->saveToDataOrigins(
                    $projectId,
                    null, // No source_id for child sitemaps
                    $sitemapUrl,
                    '', // Empty raw_data - will be fetched in step 2
                    ['type' => 'sitemap', 'source_type' => 'sitemap', 'parent_sitemap_index' => $baseUrl]
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
    private function processRegularSitemap(int $projectId, int $parentOriginId, string $xmlContent, string $baseUrl, array &$result): void
    {
        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($xmlContent);
        
        if ($xml === false) {
            error_log("CrawlFlow Phase 1 (Sitemap): Failed to parse sitemap XML");
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

        error_log("CrawlFlow Phase 1 (Sitemap): Extracted " . count($urls) . " URLs from regular sitemap");
        
        // Save each URL as a child origin with type 'url'
        foreach ($urls as $url) {
            $childOriginId = $this->saveToDataOrigins(
                $projectId,
                null,
                $url,
                '', // Empty raw_data - will be fetched later
                ['type' => 'url', 'source_type' => 'sitemap', 'parent_sitemap' => $baseUrl]
            );
            
            if ($childOriginId) {
                $this->saveReference($parentOriginId, $childOriginId, 'url');
                $result['references_saved']++;
            }
        }
    }

    /**
     * Process sitemap URLs (Step 2 of sitemap index processing)
     * Fetch each sitemap and extract URLs from it
     */
    private function processSitemapUrls(int $projectId, array $sitemapOrigins, array &$result): void
    {
        $dataSource = new HttpDataSource();
        
        foreach ($sitemapOrigins as $sitemapOrigin) {
            $sitemapUrl = $sitemapOrigin['guid'];
            $sitemapOriginId = (int)$sitemapOrigin['id'];
            
            try {
                error_log("CrawlFlow Phase 1 (Sitemap): Fetching sitemap URL: {$sitemapUrl}");
                $response = $dataSource->fetch($sitemapUrl);
                
                if (isset($response['status_code']) && $response['status_code'] === 200) {
                    $xmlContent = $response['body'] ?? '';
                    
                    // Update origin with fetched content
                    $this->updateOriginData($sitemapOriginId, $xmlContent);
                    
                    // Extract URLs from this sitemap
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
                        
                        error_log("CrawlFlow Phase 1 (Sitemap): Extracted " . count($urls) . " URLs from {$sitemapUrl}");
                        
                        // Save each URL as a child origin
                        foreach ($urls as $url) {
                            $childOriginId = $this->saveToDataOrigins(
                                $projectId,
                                null,
                                $url,
                                '', // Empty raw_data - will be fetched later
                                ['type' => 'url', 'source_type' => 'sitemap', 'parent_sitemap' => $sitemapUrl]
                            );
                            
                            if ($childOriginId) {
                                $this->saveReference($sitemapOriginId, $childOriginId, 'url');
                                $result['references_saved']++;
                            }
                        }
                    }
                } else {
                    error_log("CrawlFlow Phase 1 (Sitemap): Failed to fetch {$sitemapUrl} - Status: " . ($response['status_code'] ?? 'unknown'));
                }
            } catch (\Exception $e) {
                error_log("CrawlFlow Phase 1 (Sitemap): Error processing sitemap URL {$sitemapUrl}: " . $e->getMessage());
            }
        }
    }

    /**
     * Update origin data and metadata
     */
    private function updateOriginData(int $originId, string $rawData): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_data_origins';
        
        $now = current_time('mysql');
        $wpdb->update(
            $table,
            [
                'raw_data' => $rawData,
                'fetched_at' => $now,
                'updated_at' => $now,
                'crawled' => 1,
            ],
            ['id' => $originId]
        );
    }

    /**
     * Update origin metadata only
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

