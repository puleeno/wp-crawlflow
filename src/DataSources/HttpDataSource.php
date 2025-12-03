<?php

namespace CrawlFlow\DataSources;

/**
 * HTTP Data Source
 * Fetches data from HTTP URLs
 */
class HttpDataSource
{
    /**
     * Fetch URL content
     * 
     * @param string $url URL to fetch
     * @param array $options Request options
     * @return array Response data
     * @throws \RuntimeException If request fails
     */
    public function fetch(string $url, array $options = []): array
    {
        $userAgent = $options['userAgent'] ?? 'CrawlFlow/2.0';
        $timeout = $options['timeout'] ?? 30;
        
        $args = [
            'timeout' => $timeout,
            'user-agent' => $userAgent,
            'sslverify' => false, // For local/development
        ];

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            throw new \RuntimeException('HTTP request failed: ' . $response->get_error_message());
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        
        if ($statusCode !== 200) {
            throw new \RuntimeException("HTTP request returned status {$statusCode}");
        }

        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);

        return [
            'url' => $url,
            'status_code' => $statusCode,
            'headers' => $headers->getAll(),
            'body' => $body,
            'size' => strlen($body),
        ];
    }

    /**
     * Fetch multiple URLs
     */
    public function fetchMultiple(array $urls, array $options = []): array
    {
        $results = [];
        
        foreach ($urls as $url) {
            try {
                $results[$url] = $this->fetch($url, $options);
            } catch (\Exception $e) {
                $results[$url] = [
                    'error' => $e->getMessage(),
                    'url' => $url,
                ];
            }
        }
        
        return $results;
    }
}

