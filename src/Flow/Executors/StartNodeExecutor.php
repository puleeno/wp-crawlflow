<?php

namespace CrawlFlow\Flow\Executors;

use CrawlFlow\Flow\ExecutionContext;
use CrawlFlow\Flow\NodeExecutorInterface;
use CrawlFlow\Flow\NodeResult;
use CrawlFlow\Flow\RakeAdapter;

/**
 * Start Node Executor
 * Handles data source nodes (URL, API, MySQL, CSV, JSON, XML)
 * Uses Rake HttpClientManager and DatabaseDriverManager
 */
class StartNodeExecutor implements NodeExecutorInterface
{
    /**
     * @var RakeAdapter
     */
    private RakeAdapter $rakeAdapter;

    /**
     * Constructor
     */
    public function __construct(RakeAdapter $rakeAdapter)
    {
        $this->rakeAdapter = $rakeAdapter;
    }
    /**
     * Execute start node
     */
    public function execute(array $node, ExecutionContext $context): NodeResult
    {
        $data = $node['data'] ?? [];
        $sourceType = $data['sourceType'] ?? '';

        try {
            switch ($sourceType) {
                case 'url':
                    return $this->executeUrlSource($data, $context);
                case 'api':
                    return $this->executeApiSource($data, $context);
                case 'mysql':
                    return $this->executeMySQLSource($data, $context);
                case 'csv':
                    return $this->executeCsvSource($data, $context);
                case 'json':
                    return $this->executeJsonSource($data, $context);
                case 'xml':
                    return $this->executeXmlSource($data, $context);
                default:
                    return NodeResult::error("Unsupported source type: {$sourceType}");
            }
        } catch (\Exception $e) {
            return NodeResult::error("Start node execution failed: " . $e->getMessage());
        }
    }

    /**
     * Check if supports node type
     */
    public function supports(string $nodeType): bool
    {
        return $nodeType === 'start';
    }

    /**
     * Execute URL source
     */
    private function executeUrlSource(array $data, ExecutionContext $context): NodeResult
    {
        $sourceValue = $data['sourceValue'] ?? '';
        $urlSettings = $data['urlSettings'] ?? [];

        if (empty($sourceValue)) {
            return NodeResult::error('URL source value is required');
        }

        try {
            $context->addLog("Fetching URL: {$sourceValue}", 'info');

            // Use HttpDataSource to fetch URL
            $httpSource = new \CrawlFlow\DataSources\HttpDataSource();
            $response = $httpSource->fetch($sourceValue, [
                'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'timeout' => 30,
            ]);

            $content = $response['body'];
            $urls = $this->extractUrls($content, $urlSettings, $sourceValue);

            $resources = [];
            foreach ($urls as $url) {
                $resources[] = [
                    'id' => uniqid('resource_'),
                    'url' => $url,
                    'type' => 'html',
                    'metadata' => [
                        'source_type' => 'url',
                        'url_settings' => $urlSettings,
                        'original_url' => $sourceValue,
                    ],
                ];
            }

            $context->addLog("Extracted " . count($resources) . " URLs", 'info');
            return NodeResult::success($resources);

        } catch (\Exception $e) {
            return NodeResult::error("URL source execution failed: " . $e->getMessage());
        }
    }

    /**
     * Extract URLs from content based on URL settings
     */
    private function extractUrls(string $content, array $urlSettings, string $baseUrl): array
    {
        $urls = [];
        $scope = $urlSettings['scope'] ?? 'current-url';
        
        if ($scope === 'current-url') {
            // Only return the base URL
            return [$baseUrl];
        }

        // Extract URLs from HTML content
        preg_match_all('/href=["\']([^"\']+)["\']/i', $content, $matches);
        $foundUrls = $matches[1] ?? [];

        // Apply filters
        $excludeExtensions = $urlSettings['excludeExtensions'] ?? [];
        $excludePatterns = $urlSettings['excludePatterns'] ?? [];
        $whitelistPatterns = $urlSettings['whitelistPatterns'] ?? [];
        $domainPolicy = $urlSettings['domainPolicy'] ?? 'all';
        $domainWhitelist = $urlSettings['domainWhitelist'] ?? [];

        foreach ($foundUrls as $url) {
            // Convert relative URLs to absolute
            if (!preg_match('/^https?:\/\//', $url)) {
                $url = rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
            }

            // Check exclude extensions
            $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            if (in_array($extension, $excludeExtensions)) {
                continue;
            }

            // Check exclude patterns
            $excluded = false;
            foreach ($excludePatterns as $pattern) {
                if (preg_match($pattern, $url)) {
                    $excluded = true;
                    break;
                }
            }
            if ($excluded) {
                continue;
            }

            // Check whitelist patterns
            if (!empty($whitelistPatterns)) {
                $whitelisted = false;
                foreach ($whitelistPatterns as $pattern) {
                    if (preg_match($pattern, $url)) {
                        $whitelisted = true;
                        break;
                    }
                }
                if (!$whitelisted) {
                    continue;
                }
            }

            // Check domain policy
            if ($domainPolicy === 'whitelist-only') {
                $urlDomain = parse_url($url, PHP_URL_HOST);
                if (!in_array($urlDomain, $domainWhitelist)) {
                    continue;
                }
            }

            $urls[] = $url;
        }

        return array_unique($urls);
    }

    /**
     * Execute API source
     */
    private function executeApiSource(array $data, ExecutionContext $context): NodeResult
    {
        $sourceValue = $data['sourceValue'] ?? '';
        $apiSettings = $data['apiSettings'] ?? [];

        if (empty($sourceValue)) {
            return NodeResult::error('API source value is required');
        }

        try {
            $httpClient = $this->rakeAdapter->getHttpClientManager();
            $context->addLog("Fetching API: {$sourceValue}", 'info');

            // Prepare headers with authentication
            $headers = [];
            $authType = $apiSettings['authType'] ?? 'none';
            $authDetails = $apiSettings['authDetails'] ?? [];

            switch ($authType) {
                case 'api-key':
                    if (isset($authDetails['location']) && $authDetails['location'] === 'header') {
                        $headers[$authDetails['keyName'] ?? 'X-API-Key'] = $authDetails['keyValue'] ?? '';
                    }
                    break;
                case 'bearer':
                    $headers['Authorization'] = 'Bearer ' . ($authDetails['token'] ?? '');
                    break;
                case 'basic':
                    $credentials = base64_encode(
                        ($authDetails['username'] ?? '') . ':' . ($authDetails['password'] ?? '')
                    );
                    $headers['Authorization'] = 'Basic ' . $credentials;
                    break;
            }

            // Handle pagination
            $paginationType = $apiSettings['paginationType'] ?? 'none';
            $paginationDetails = $apiSettings['paginationDetails'] ?? [];
            $resources = [];

            if ($paginationType === 'none') {
                $response = $httpClient->get($sourceValue, ['headers' => $headers]);
                if ($response && $response->isSuccessful()) {
                    $resources[] = $this->createApiResource($sourceValue, $response->getBody(), $apiSettings);
                }
            } else {
                // Handle pagination
                $resources = $this->fetchPaginatedApi($httpClient, $sourceValue, $headers, $paginationType, $paginationDetails, $apiSettings);
            }

            $context->addLog("Fetched " . count($resources) . " API resources", 'info');
            return NodeResult::success($resources);

        } catch (\Exception $e) {
            return NodeResult::error("API source execution failed: " . $e->getMessage());
        }
    }

    /**
     * Create API resource
     */
    private function createApiResource(string $url, string $content, array $apiSettings): array
    {
        return [
            'id' => uniqid('resource_'),
            'url' => $url,
            'type' => 'api',
            'content' => $content,
            'metadata' => [
                'source_type' => 'api',
                'api_settings' => $apiSettings,
            ],
        ];
    }

    /**
     * Fetch paginated API
     */
    private function fetchPaginatedApi($httpClient, string $baseUrl, array $headers, string $paginationType, array $paginationDetails, array $apiSettings): array
    {
        $resources = [];
        $maxPages = 100; // Safety limit
        $page = 0;

        while ($page < $maxPages) {
            $url = $baseUrl;

            // Build URL with pagination params
            switch ($paginationType) {
                case 'page':
                    $paramName = $paginationDetails['paramName'] ?? 'page';
                    $startsAt = $paginationDetails['startsAt'] ?? 1;
                    $url .= (strpos($url, '?') !== false ? '&' : '?') . $paramName . '=' . ($startsAt + $page);
                    break;
                case 'offset-limit':
                    $offsetParam = $paginationDetails['offsetParam'] ?? 'offset';
                    $limitParam = $paginationDetails['limitParam'] ?? 'limit';
                    $limitValue = $paginationDetails['limitValue'] ?? 10;
                    $startsAt = $paginationDetails['startsAt'] ?? 0;
                    $offset = $startsAt + ($page * $limitValue);
                    $url .= (strpos($url, '?') !== false ? '&' : '?') . $offsetParam . '=' . $offset . '&' . $limitParam . '=' . $limitValue;
                    break;
            }

            $response = $httpClient->get($url, ['headers' => $headers]);
            if (!$response || !$response->isSuccessful()) {
                break;
            }

            $resources[] = $this->createApiResource($url, $response->getBody(), $apiSettings);

            // Check for next URL (for next-url pagination)
            if ($paginationType === 'next-url') {
                $jsonPath = $paginationDetails['jsonPath'] ?? '$.next';
                $data = json_decode($response->getBody(), true);
                $nextUrl = $this->getJsonPathValue($data, $jsonPath);
                if (empty($nextUrl)) {
                    break;
                }
                $baseUrl = $nextUrl;
            }

            $page++;
        }

        return $resources;
    }

    /**
     * Get value from JSON using path (simplified)
     */
    private function getJsonPathValue(array $data, string $path): ?string
    {
        // Simplified JSON path - just check for 'next' key
        return $data['next'] ?? null;
    }

    /**
     * Execute MySQL source
     */
    private function executeMySQLSource(array $data, ExecutionContext $context): NodeResult
    {
        $sourceValue = $data['sourceValue'] ?? [];

        if (empty($sourceValue) || !is_array($sourceValue)) {
            return NodeResult::error('MySQL connection details are required');
        }

        try {
            $dbManager = $this->rakeAdapter->getDatabaseDriverManager();
            $context->addLog("Connecting to MySQL database", 'info');

            // Use Rake's DatabaseDriverManager to connect
            $driver = $dbManager->getDriver('mysql');
            $connection = $driver->connect($sourceValue);

            if (!$connection) {
                return NodeResult::error('Failed to connect to MySQL database');
            }

            // Query data (simplified - in real implementation, would query based on config)
            $resources = [
                [
                    'id' => uniqid('resource_'),
                    'type' => 'mysql',
                    'connection' => $connection,
                    'metadata' => [
                        'source_type' => 'mysql',
                        'connection' => [
                            'host' => $sourceValue['host'] ?? '',
                            'database' => $sourceValue['database'] ?? '',
                        ],
                    ],
                ],
            ];

            $context->addLog("Connected to MySQL database successfully", 'info');
            return NodeResult::success($resources);

        } catch (\Exception $e) {
            return NodeResult::error("MySQL source execution failed: " . $e->getMessage());
        }
    }

    /**
     * Execute CSV source
     */
    private function executeCsvSource(array $data, ExecutionContext $context): NodeResult
    {
        $sourceValue = $data['sourceValue'] ?? '';
        $inputMethod = $data['inputMethod'] ?? 'paste';

        if (empty($sourceValue)) {
            return NodeResult::error('CSV source value is required');
        }

        // TODO: Implement CSV parsing logic

        $resources = [
            [
                'id' => uniqid('resource_'),
                'type' => 'csv',
                'content' => $sourceValue,
                'metadata' => [
                    'source_type' => 'csv',
                    'input_method' => $inputMethod,
                ],
            ],
        ];

        return NodeResult::success($resources);
    }

    /**
     * Execute JSON source
     */
    private function executeJsonSource(array $data, ExecutionContext $context): NodeResult
    {
        $sourceValue = $data['sourceValue'] ?? '';
        $jsonSettings = $data['jsonSettings'] ?? [];

        if (empty($sourceValue)) {
            return NodeResult::error('JSON source value is required');
        }

        // TODO: Implement JSON parsing logic

        $resources = [
            [
                'id' => uniqid('resource_'),
                'type' => 'json',
                'content' => $sourceValue,
                'metadata' => [
                    'source_type' => 'json',
                    'json_settings' => $jsonSettings,
                ],
            ],
        ];

        return NodeResult::success($resources);
    }

    /**
     * Execute XML source
     */
    private function executeXmlSource(array $data, ExecutionContext $context): NodeResult
    {
        $sourceValue = $data['sourceValue'] ?? '';
        $xmlSettings = $data['xmlSettings'] ?? [];

        if (empty($sourceValue)) {
            return NodeResult::error('XML source value is required');
        }

        // TODO: Implement XML parsing logic

        $resources = [
            [
                'id' => uniqid('resource_'),
                'type' => 'xml',
                'content' => $sourceValue,
                'metadata' => [
                    'source_type' => 'xml',
                    'xml_settings' => $xmlSettings,
                ],
            ],
        ];

        return NodeResult::success($resources);
    }
}

