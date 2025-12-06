<?php

namespace CrawlFlow\Cron\Phase1;

use CrawlFlow\DataSource\HttpDataSource;

/**
 * URL Data Source Handler
 * 
 * Handles crawling and URL extraction from HTTP/HTTPS URLs
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
            
            // Fetch data using HttpDataSource
            $dataSource = new HttpDataSource();
            
            $url = $sourceConfig['url'];
            error_log("CrawlFlow Phase 1 (URL): Fetching URL: {$url}");
            
            $response = $dataSource->fetch($url);

            if (isset($response['status_code']) && $response['status_code'] === 200) {
                $body = $response['body'] ?? '';
                error_log("CrawlFlow Phase 1 (URL): Fetched " . strlen($body) . " bytes from {$url}");
                
                // Save to rake_data_origins
                $originId = $this->saveToDataOrigins($projectId, $sourceId, $url, $body);
                $result['items_saved']++;

                // Extract URLs, save to origins, and create references
                // Get URL settings from data source config (dpc_rake_data_sources.config)
                $urlSettings = $this->getUrlSettingsFromSourceConfig($source);
                error_log("CrawlFlow Phase 1 (URL): URL settings from source config: " . json_encode($urlSettings));
                
                $urls = $this->extractUrls($body, $url, $urlSettings);
                error_log("CrawlFlow Phase 1 (URL): Extracted " . count($urls) . " URLs from {$url} (after filtering)");
                
                foreach ($urls as $extractedUrl) {
                    // Save child URL to origins (if not exists)
                    // Child URLs don't have source_id yet (will be fetched later)
                    $childOriginId = $this->saveToDataOrigins($projectId, null, $extractedUrl, '');
                    
                    // Create reference relationship
                    if ($childOriginId) {
                        $this->saveReference($originId, $childOriginId, 'child');
                        $result['references_saved']++;
                    }
                }
            } else {
                $statusCode = $response['status_code'] ?? 'unknown';
                error_log("CrawlFlow Phase 1 (URL): Failed to fetch {$url} - Status: {$statusCode}");
                $result['errors'][] = "Failed to fetch URL: Status {$statusCode}";
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
        
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            $absoluteUrl = $this->resolveUrl($baseUrl, $href);
            
            if (!$absoluteUrl) {
                continue;
            }
            
            // Skip duplicates
            if (in_array($absoluteUrl, $urls)) {
                $duplicateCount++;
                continue;
            }
            
            // Apply filters
            if (!$this->shouldIncludeUrl($absoluteUrl, $excludeExtensions, $excludePatterns, $whitelistPatterns, $domainPolicy, $domainWhitelist, $baseUrl)) {
                $filteredCount++;
                continue;
            }
            
            $urls[] = $absoluteUrl;
        }
        
        error_log("CrawlFlow Phase 1 (URL): Extracted {$totalLinks} links, filtered {$filteredCount}, duplicates {$duplicateCount}, kept " . count($urls));

        // Extract images
        $images = $xpath->query('//img[@src]');
        foreach ($images as $img) {
            $src = $img->getAttribute('src');
            $absoluteUrl = $this->resolveUrl($baseUrl, $src);
            if ($absoluteUrl && !in_array($absoluteUrl, $urls)) {
                $urls[] = $absoluteUrl;
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
        
        // Remove excessive escaping (from JSON storage - multiple backslashes)
        // Replace multiple backslashes with single backslash
        $pattern = preg_replace('/\\\\+/', '\\', $pattern);
        
        // If pattern already has delimiters, validate and return
        if (preg_match('/^\/.+\/[imsxADSUXJu]*$/', $pattern)) {
            // Validate the pattern is correct
            $testPattern = $pattern;
            // If it has flags, extract them
            if (preg_match('/^(.+)\/([imsxADSUXJu]+)$/', $pattern, $matches)) {
                $testPattern = $matches[1] . '/';
            }
            // Test if pattern is valid
            if (@preg_match($testPattern, '') !== false) {
                return $pattern;
            }
        }
        
        // If pattern looks like it has delimiters but is malformed, try to fix
        if (strpos($pattern, '/') === 0 && strrpos($pattern, '/') !== false) {
            $lastSlash = strrpos($pattern, '/');
            $actualPattern = substr($pattern, 1, $lastSlash - 1);
            $flags = substr($pattern, $lastSlash + 1);
            
            // Validate the actual pattern part
            if (@preg_match('/' . $actualPattern . '/', '') !== false) {
                return '/' . $actualPattern . '/' . $flags;
            }
        }
        
        // Otherwise, treat as simple string pattern
        // Escape special regex chars and add delimiters
        $escaped = preg_quote($pattern, '/');
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

