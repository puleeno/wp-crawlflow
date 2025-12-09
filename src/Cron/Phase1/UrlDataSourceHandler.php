<?php

namespace CrawlFlow\Cron\Phase1;

use CrawlFlow\DataSources\HttpDataSource;

/**
 * URL Data Source Handler
 * 
 * Phase 1: Only extracts URLs from data source and saves them to database
 * - Fetches HTML/XML once to extract URLs (but does NOT save raw_data)
 * - Saves URLs to rake_data_origins (without raw_data)
 * - Detects worker priority for each URL
 * - Phase 2 will fetch raw_data for these URLs
 */
class UrlDataSourceHandler extends AbstractDataSourceHandler
{
    /**
     * Get supported data source type
     */
    public function getSupportedType(): string
    {
        return 'url';
    }

    /**
     * Process URL data source
     */
    public function process(int $projectId, array $source, array $flowConfig): array
    {
        $result = [
            'items_saved' => 0,
            'references_saved' => 0,
            'errors' => [],
        ];

        $sourceType = $source['type'] ?? 'url';
        $sourceConfig = isset($source['config']) ? json_decode($source['config'], true) : [];

        if ($sourceType !== 'url' || !isset($sourceConfig['url'])) {
            $result['errors'][] = 'Invalid URL data source configuration';
            return $result;
        }

        try {
            // Get or create source in database
            $sourceId = $this->ensureDataSourceInDb($projectId, $source);
            
            $url = $sourceConfig['url'];
            error_log("CrawlFlow Phase 1 (URL): Handler started for URL: {$url}");
            
            // Phase 1: Only save URL to database, do NOT fetch/crawl data
            // Save source URL to rake_data_origins (without raw_data)
            $originId = $this->saveToDataOrigins($projectId, $sourceId > 0 ? $sourceId : null, $url, '', [], $flowConfig);
            if ($originId > 0) {
                $result['items_saved']++;
                error_log("CrawlFlow Phase 1 (URL): Saved source URL to origins: {$url}");
            } else {
                error_log("CrawlFlow Phase 1 (URL): Failed to save origin for URL: {$url}");
                $result['errors'][] = "Failed to save origin for URL: {$url}";
                return $result;
            }

            // Phase 1: Fetch HTML only to extract child URLs, but do NOT save raw_data
            // This is a one-time fetch just to discover URLs
            $dataSource = new HttpDataSource();
            $response = $dataSource->fetch($url);

            if (isset($response['status_code']) && $response['status_code'] === 200) {
                $body = $response['body'] ?? '';
                error_log("CrawlFlow Phase 1 (URL): Fetched " . strlen($body) . " bytes from {$url} to extract URLs (not saving raw_data)");
                
                // Extract URLs from HTML (but don't save the HTML)
                // Get URL settings from data source config
                $urlSettings = $this->getUrlSettingsFromSourceConfig($source);
                error_log("CrawlFlow Phase 1 (URL): URL settings from source config: " . json_encode($urlSettings));
                
                $urls = $this->extractUrls($body, $url, $urlSettings);
                error_log("CrawlFlow Phase 1 (URL): Extracted " . count($urls) . " URLs from {$url} (after filtering)");
                
                // Save ALL extracted URLs to dpc_rake_data_origins (without raw_data)
                $savedCount = 0;
                
                foreach ($urls as $extractedUrl) {
                    // Check if URL already exists
                    global $wpdb;
                    $originsTable = $wpdb->prefix . 'rake_data_origins';
                    $existing = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$originsTable} WHERE guid = %s",
                        $extractedUrl
                    ));
                    
                    if ($existing) {
                        // URL already exists, update priority if needed
                        $childOriginId = (int)$existing;
                        
                        // Update priority if flowConfig is provided (in case worker config changed)
                        if ($flowConfig !== null && !empty($extractedUrl) && filter_var($extractedUrl, FILTER_VALIDATE_URL)) {
                            $detectedPriority = $this->detectWorkerPriority($projectId, $flowConfig, $extractedUrl);
                            $wpdb->update(
                                $originsTable,
                                ['priority' => $detectedPriority, 'updated_at' => current_time('mysql')],
                                ['id' => $childOriginId]
                            );
                        }
                    } else {
                        // Save child URL to origins (without raw_data)
                        // Pass flowConfig to detect worker priority
                        $childOriginId = $this->saveToDataOrigins($projectId, null, $extractedUrl, '', [], $flowConfig);
                        $savedCount++;
                    }
                    
                    // Create reference relationship
                    if ($childOriginId && $originId > 0) {
                        if ($this->saveReference($originId, $childOriginId, 'child')) {
                            $result['references_saved']++;
                        }
                    }
                }
                
                error_log("CrawlFlow Phase 1 (URL): Saved " . $savedCount . " new URLs to origins (total: " . count($urls) . ")");
                error_log("CrawlFlow Phase 1 (URL): Phase 1 complete - URLs saved, no data fetched. Phase 2 will fetch raw_data.");
            } else {
                $statusCode = $response['status_code'] ?? 'unknown';
                error_log("CrawlFlow Phase 1 (URL): Failed to fetch {$url} for URL extraction - Status: {$statusCode}");
                // Don't treat this as error - we still saved the source URL
                // Phase 2 will try to fetch it
            }

        } catch (\Exception $e) {
            error_log("CrawlFlow Phase 1 (URL): Error processing source - " . $e->getMessage());
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Extract URLs from HTML content with filtering
     * For URL data sources, ALL extracted URLs should be imported to dpc_rake_data_origins
     */
    private function extractUrls(string $html, string $baseUrl, array $urlSettings = []): array
    {
        $urls = [];
        $dom = new \DOMDocument();
        
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        
        // Get filter settings
        $excludeExtensions = $urlSettings['excludeExtensions'] ?? [];
        $excludePatterns = $urlSettings['excludePatterns'] ?? [];
        $whitelistPatterns = $urlSettings['whitelistPatterns'] ?? [];
        $domainPolicy = $urlSettings['domainPolicy'] ?? 'all';
        $domainWhitelist = $urlSettings['domainWhitelist'] ?? [];
        
        // Extract all links
        $links = $xpath->query('//a[@href]');
        $totalLinks = $links->length;
        $filteredCount = 0;
        $duplicateCount = 0;
        
        error_log("CrawlFlow Phase 1 (URL): Found {$totalLinks} links in HTML");
        error_log("CrawlFlow Phase 1 (URL): Whitelist patterns: " . json_encode($whitelistPatterns));
        error_log("CrawlFlow Phase 1 (URL): Exclude patterns: " . json_encode($excludePatterns));
        error_log("CrawlFlow Phase 1 (URL): Domain policy: {$domainPolicy}");
        
        $sampleUrls = [];
        $filteredReasons = ['no_absolute' => 0, 'duplicate' => 0, 'exclude_extension' => 0, 'exclude_pattern' => 0, 'whitelist_mismatch' => 0, 'domain_policy' => 0];
        
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            $absoluteUrl = $this->resolveUrl($baseUrl, $href);
            
            if (!$absoluteUrl) {
                $filteredReasons['no_absolute']++;
                if (count($sampleUrls) < 5) {
                    $sampleUrls[] = ['href' => $href, 'reason' => 'no_absolute'];
                }
                continue;
            }
            
            // Skip duplicates
            if (in_array($absoluteUrl, $urls)) {
                $duplicateCount++;
                $filteredReasons['duplicate']++;
                continue;
            }
            
            // Apply filters with detailed logging
            $includeResult = $this->shouldIncludeUrl($absoluteUrl, $excludeExtensions, $excludePatterns, $whitelistPatterns, $domainPolicy, $domainWhitelist, $baseUrl);
            if (!$includeResult) {
                $filteredCount++;
                // Try to determine why it was filtered
                $path = parse_url($absoluteUrl, PHP_URL_PATH);
                $extension = $path ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';
                if (in_array($extension, $excludeExtensions)) {
                    $filteredReasons['exclude_extension']++;
                } else {
                    // Check patterns
                    $matchedExclude = false;
                    foreach ($excludePatterns as $pattern) {
                        $normalizedPattern = $this->normalizeRegexPattern($pattern);
                        if ($normalizedPattern && @preg_match($normalizedPattern, $absoluteUrl)) {
                            $matchedExclude = true;
                            break;
                        }
                    }
                    if ($matchedExclude) {
                        $filteredReasons['exclude_pattern']++;
                    } elseif (!empty($whitelistPatterns)) {
                        $filteredReasons['whitelist_mismatch']++;
                    } else {
                        $filteredReasons['domain_policy']++;
                    }
                }
                if (count($sampleUrls) < 10) {
                    $sampleUrls[] = ['url' => $absoluteUrl, 'reason' => 'filtered'];
                }
                continue;
            }
            
            $urls[] = $absoluteUrl;
            if (count($urls) <= 5) {
                error_log("CrawlFlow Phase 1 (URL): Accepted URL: {$absoluteUrl}");
            }
        }
        
        error_log("CrawlFlow Phase 1 (URL): Extracted {$totalLinks} links, filtered {$filteredCount}, duplicates {$duplicateCount}, kept " . count($urls));
        error_log("CrawlFlow Phase 1 (URL): Filter reasons: " . json_encode($filteredReasons));
        if (!empty($sampleUrls)) {
            error_log("CrawlFlow Phase 1 (URL): Sample filtered URLs: " . json_encode(array_slice($sampleUrls, 0, 5)));
        }

        // Extract images (but apply same filters)
        $images = $xpath->query('//img[@src]');
        foreach ($images as $img) {
            $src = $img->getAttribute('src');
            $absoluteUrl = $this->resolveUrl($baseUrl, $src);
            if ($absoluteUrl && !in_array($absoluteUrl, $urls)) {
                // Apply same filters to images
                if ($this->shouldIncludeUrl($absoluteUrl, $excludeExtensions, $excludePatterns, $whitelistPatterns, $domainPolicy, $domainWhitelist, $baseUrl)) {
                    $urls[] = $absoluteUrl;
                }
            }
        }

        return array_unique($urls);
    }

    /**
     * Check if URL should be included based on filters
     */
    private function shouldIncludeUrl(
        string $url,
        array $excludeExtensions,
        array $excludePatterns,
        array $whitelistPatterns,
        string $domainPolicy,
        array $domainWhitelist,
        string $baseUrl
    ): bool {
        // Check exclude extensions
        $path = parse_url($url, PHP_URL_PATH);
        if ($path !== null) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($extension, $excludeExtensions)) {
                return false;
            }
        }

        // Check exclude patterns
        foreach ($excludePatterns as $pattern) {
            $normalizedPattern = $this->normalizeRegexPattern($pattern);
            if ($normalizedPattern) {
                $result = @preg_match($normalizedPattern, $url);
                if ($result === 1) {
                    return false; // Matched exclude pattern
                }
            }
        }

        // Check whitelist patterns (if any)
        // If whitelist is empty, allow all URLs (after exclude checks)
        if (!empty($whitelistPatterns)) {
            $matched = false;
            foreach ($whitelistPatterns as $pattern) {
                $normalizedPattern = $this->normalizeRegexPattern($pattern);
                if ($normalizedPattern) {
                    $result = @preg_match($normalizedPattern, $url);
                    if ($result === 1) {
                        $matched = true;
                        break;
                    }
                } else {
                    // If pattern normalization failed, try simple string match
                    if (strpos($url, $pattern) !== false) {
                        $matched = true;
                        break;
                    }
                }
            }
            if (!$matched) {
                return false; // No whitelist pattern matched
            }
        }

        // Check domain policy
        if ($domainPolicy === 'same-domain-only') {
            $urlDomain = parse_url($url, PHP_URL_HOST);
            $baseDomain = parse_url($baseUrl, PHP_URL_HOST);
            if ($urlDomain !== $baseDomain) {
                return false;
            }
        } elseif ($domainPolicy === 'whitelist-only') {
            $urlDomain = parse_url($url, PHP_URL_HOST);
            if (!in_array($urlDomain, $domainWhitelist)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Normalize regex pattern (add delimiters if needed)
     * Handles patterns that may already be escaped or have delimiters
     */
    private function normalizeRegexPattern(string $pattern): ?string
    {
        if (empty($pattern)) {
            return null;
        }
        
        // Step 1: Aggressively remove excessive backslashes
        // Handle cases where pattern is over-escaped (e.g., "\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\/admin\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\/")
        // Strategy: Remove all backslashes first, then rebuild the pattern correctly
        $originalPattern = $pattern;
        
        // Count consecutive backslashes - if there are too many, it's over-escaped
        if (preg_match('/\\\\{10,}/', $pattern)) {
            // Pattern is severely over-escaped - extract the actual content
            // Look for pattern structure: /.../flags or /.../
            if (preg_match('/^[\\\\\/]*(.+?)[\\\\\/]+([imsxADSUXJu]*)$/', $pattern, $matches)) {
                $patternContent = $matches[1];
                $flags = $matches[2] ?? '';
                
                // Remove all backslashes from content
                $patternContent = str_replace('\\', '', $patternContent);
                
                // Rebuild pattern: if content looks like a regex pattern, use it as-is
                // Otherwise, escape it
                if (preg_match('/^[\/\^].*[\/\$]?$/', $patternContent)) {
                    // Already looks like a regex pattern
                    $pattern = '/' . trim($patternContent, '/') . '/' . $flags;
                } else {
                    // Escape and wrap
                    $pattern = '/' . preg_quote($patternContent, '/') . '/' . $flags;
                }
            } else {
                // Fallback: remove all backslashes and rebuild
                $pattern = str_replace('\\', '', $pattern);
                if (!preg_match('/^\/.+\//', $pattern)) {
                    $pattern = '/' . preg_quote($pattern, '/') . '/';
                }
            }
        } else {
            // Step 2: Normalize moderate escaping
            // Remove excessive escaping (from JSON storage - multiple backslashes)
            $maxIterations = 10;
            $iteration = 0;
            
            while ($iteration < $maxIterations) {
                $newPattern = stripslashes($pattern);
                if ($newPattern === $pattern) {
                    break; // No more backslashes to remove
                }
                $pattern = $newPattern;
                $iteration++;
            }
            
            // If still has excessive backslashes, try regex replace
            if (preg_match('/\\\\{4,}/', $pattern)) {
                // Replace multiple consecutive backslashes with single backslash
                $pattern = preg_replace('/\\\\+/', '\\', $pattern);
            }
        }
        
        // Step 3: Validate and fix pattern structure
        // If pattern already has delimiters, validate and return
        if (preg_match('/^\/.+\/[imsxADSUXJu]*$/', $pattern)) {
            // Extract pattern and flags
            if (preg_match('/^(.+)\/([imsxADSUXJu]*)$/', $pattern, $matches)) {
                $patternBody = $matches[1];
                $flags = $matches[2] ?? '';
                
                // Clean up pattern body - remove excessive escaping
                // If pattern body has escaped slashes like \/admin\/, normalize them
                $patternBody = preg_replace('/\\\\+\//', '/', $patternBody);
                $patternBody = preg_replace('/\/\\\\+/', '/', $patternBody);
                
                // Rebuild pattern
                $testPattern = '/' . $patternBody . '/' . $flags;
                
                // Test if pattern is valid
                if (@preg_match($testPattern, '') !== false) {
                    return $testPattern;
                }
            }
        }
        
        // Step 4: Try to extract pattern from malformed structure
        if (strpos($pattern, '/') !== false || strpos($pattern, '\\') !== false) {
            // Look for pattern-like structure
            // Remove all backslashes and see if we can find a valid pattern
            $cleanPattern = str_replace('\\', '', $pattern);
            
            // Check if it looks like /pattern/flags
            if (preg_match('/^\/?([^\/]+)\/?([imsxADSUXJu]*)$/', $cleanPattern, $matches)) {
                $patternBody = $matches[1];
                $flags = $matches[2] ?? '';
                
                // Try to use as regex pattern
                $testPattern = '/' . $patternBody . '/' . $flags;
                if (@preg_match($testPattern, '') !== false) {
                    return $testPattern;
                }
            }
        }
        
        // Step 5: Final fallback - treat as simple string pattern
        // Remove all backslashes first
        $cleanPattern = str_replace('\\', '', $pattern);
        // Escape special regex chars and add delimiters
        $escaped = preg_quote($cleanPattern, '/');
        return '/' . $escaped . '/';
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
        
        $basePath = $parsed['path'] ?? '/';
        if (substr($basePath, -1) !== '/') {
            $basePath = dirname($basePath) . '/';
        }

        if (strpos($url, '/') === 0) {
            // Absolute path
            return $base . $url;
        }

        // Relative path
        return $base . $basePath . $url;
    }
}

