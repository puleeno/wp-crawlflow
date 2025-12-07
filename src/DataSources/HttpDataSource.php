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
            // Return error array instead of throwing exception
            return [
                'url' => $url,
                'status_code' => 0,
                'error' => $response->get_error_message(),
                'headers' => [],
                'body' => '',
                'size' => 0,
            ];
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);
        $contentType = wp_remote_retrieve_header($response, 'content-type');
        
        // Handle case where content-type might be an array
        if (is_array($contentType)) {
            $contentType = !empty($contentType) ? $contentType[0] : '';
        }

        // Inject <base> tag for HTML content
        if (!empty($body) && $this->isHtmlContent($body, $contentType)) {
            $body = $this->injectBaseTag($body, $url);
        }

        // Always return array with status code, don't throw exception
        // Let the caller handle non-200 status codes
        return [
            'url' => $url,
            'status_code' => $statusCode ?? 0,
            'headers' => $headers ? $headers->getAll() : [],
            'body' => $body ?? '',
            'size' => strlen($body ?? ''),
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

    /**
     * Check if content is HTML
     * 
     * @param string $body Response body
     * @param string|null $contentType Content-Type header value
     * @return bool True if HTML content
     */
    private function isHtmlContent(string $body, ?string $contentType): bool
    {
        // Check Content-Type header
        if (!empty($contentType)) {
            // If Content-Type explicitly says it's not HTML, return false
            if (stripos($contentType, 'text/html') === false && 
                stripos($contentType, 'application/xhtml') === false) {
                return false;
            }
        }
        
        // Check if body starts with HTML tags
        $trimmedBody = trim($body);
        if (stripos($trimmedBody, '<html') === 0 || stripos($trimmedBody, '<!DOCTYPE') === 0) {
            return true;
        }
        
        // Check if body contains HTML tags
        return preg_match('/<[a-z][\s\S]*>/i', $trimmedBody) === 1;
    }

    /**
     * Inject <base> tag into HTML
     * 
     * @param string $html HTML content
     * @param string $url Original URL
     * @return string HTML with base tag injected
     */
    private function injectBaseTag(string $html, string $url): string
    {
        // Extract base URL (scheme + host + path without filename)
        $parsedUrl = parse_url($url);
        if (!$parsedUrl) {
            return $html;
        }
        
        $baseUrl = '';
        if (isset($parsedUrl['scheme'])) {
            $baseUrl .= $parsedUrl['scheme'] . '://';
        }
        if (isset($parsedUrl['host'])) {
            $baseUrl .= $parsedUrl['host'];
            if (isset($parsedUrl['port'])) {
                $baseUrl .= ':' . $parsedUrl['port'];
            }
        }
        
        // Add path (directory part, not filename)
        if (isset($parsedUrl['path'])) {
            $path = $parsedUrl['path'];
            // Remove filename if exists
            if (basename($path) !== $path) {
                $path = dirname($path);
            }
            // Ensure path ends with /
            if (substr($path, -1) !== '/') {
                $path .= '/';
            }
            $baseUrl .= $path;
        } else {
            $baseUrl .= '/';
        }
        
        // Create base tag
        $baseTag = '<base href="' . esc_attr($baseUrl) . '">';
        
        // Check if base tag already exists
        if (stripos($html, '<base') !== false) {
            // Replace existing base tag
            $html = preg_replace('/<base[^>]*>/i', $baseTag, $html, 1);
            return $html;
        }
        
        // Try to inject into <head>
        if (preg_match('/<head[^>]*>/i', $html, $matches, PREG_OFFSET_CAPTURE)) {
            $headPos = $matches[0][1] + strlen($matches[0][0]);
            return substr_replace($html, "\n    " . $baseTag . "\n", $headPos, 0);
        }
        
        // Try to inject after <html> tag
        if (preg_match('/<html[^>]*>/i', $html, $matches, PREG_OFFSET_CAPTURE)) {
            $htmlPos = $matches[0][1] + strlen($matches[0][0]);
            return substr_replace($html, "\n" . $baseTag . "\n", $htmlPos, 0);
        }
        
        // If no head or html tag, prepend to body
        return $baseTag . "\n" . $html;
    }
}

