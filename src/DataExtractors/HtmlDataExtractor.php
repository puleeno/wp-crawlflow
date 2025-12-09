<?php

namespace CrawlFlow\DataExtractors;

use Masterminds\HTML5;
use Symfony\Component\DomCrawler\Crawler;
use Rake\Contracts\Parser\ParserInterface;

/**
 * HTML Data Extractor
 * Extracts structured data from HTML using CSS selectors
 */
class HtmlDataExtractor implements ParserInterface
{
    /**
     * @var Crawler
     */
    private Crawler $crawler;

    /**
     * Extract data from HTML
     * 
     * @param string $html HTML content
     * @param array $rules Extraction rules
     * @return array Extracted data
     */
    public function extract(string $html, array $rules): array
    {
        $this->crawler = new Crawler($html);
        $results = [];

        // Log extraction start
        $this->logExtraction('Starting HTML data extraction', [
            'rules_count' => count($rules),
            'html_length' => strlen($html),
        ]);

        foreach ($rules as $rule) {
            $name = $rule['name'] ?? 'field';
            $selector = $rule['selector'] ?? '';
            $extractType = $rule['extract'] ?? 'text';
            $attribute = $rule['attribute'] ?? null; // Specific attribute to extract
            $multiple = $rule['extractMultiple'] ?? $rule['multiple'] ?? false;

            if (empty($selector)) {
                $this->logExtraction("Skipping rule '{$name}': empty selector", [
                    'rule' => $name,
                ]);
                continue;
            }

            try {
                $value = null;
                
                // Special handling for breadcrumbs: extract both text and href
                if ($name === 'breadcrumbs' && $multiple) {
                    $value = $this->extractBreadcrumbs($selector);
                } elseif ($multiple) {
                    $value = $this->extractMultiple($selector, $extractType, $attribute);
                } else {
                    $value = $this->extractSingle($selector, $extractType, $attribute);
                }

                $results[$name] = $value;

                // Log extraction result for each rule
                $this->logExtraction("Extracted data for rule '{$name}'", [
                    'rule' => $name,
                    'selector' => $selector,
                    'extract_type' => $extractType,
                    'attribute' => $attribute,
                    'multiple' => $multiple,
                    'value_type' => gettype($value),
                    'value' => $this->formatValueForLog($value),
                    'value_length' => is_string($value) ? strlen($value) : (is_array($value) ? count($value) : null),
                ]);

            } catch (\Exception $e) {
                $results[$name] = null;
                $this->logExtraction("Failed to extract data for rule '{$name}'", [
                    'rule' => $name,
                    'selector' => $selector,
                    'extract_type' => $extractType,
                    'error' => $e->getMessage(),
                ], 'error');
            }
        }

        // Log extraction summary
        $this->logExtraction('HTML data extraction completed', [
            'rules_processed' => count($rules),
            'results_count' => count($results),
            'successful_extractions' => count(array_filter($results, fn($v) => $v !== null)),
        ]);

        return $results;
    }

    /**
     * Format value for logging (truncate long values)
     * 
     * @param mixed $value Value to format
     * @return mixed Formatted value
     */
    private function formatValueForLog($value)
    {
        if (is_string($value)) {
            // Truncate long strings
            $maxLength = 200;
            if (strlen($value) > $maxLength) {
                return substr($value, 0, $maxLength) . '... (truncated, length: ' . strlen($value) . ')';
            }
            return $value;
        }
        
        if (is_array($value)) {
            // For arrays, show count and first few items
            $count = count($value);
            if ($count > 5) {
                $preview = array_slice($value, 0, 3);
                return [
                    'count' => $count,
                    'preview' => $preview,
                    'note' => '... (showing first 3 of ' . $count . ' items)',
                ];
            }
            return $value;
        }
        
        return $value;
    }

    /**
     * Log extraction message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @param string $level Log level (info, error, debug)
     * @return void
     */
    private function logExtraction(string $message, array $context = [], string $level = 'info'): void
    {
        if (class_exists('\Rake\Facade\Logger')) {
            $context['component'] = 'HtmlDataExtractor';
            switch ($level) {
                case 'error':
                    \Rake\Facade\Logger::error($message, $context);
                    break;
                case 'debug':
                    \Rake\Facade\Logger::debug($message, $context);
                    break;
                default:
                    \Rake\Facade\Logger::info($message, $context);
                    break;
            }
        }
    }

    /**
     * Extract single value
     */
    private function extractSingle(string $selector, string $extractType, ?string $attribute = null)
    {
        $element = $this->crawler->filter($selector);

        if ($element->count() === 0) {
            return null;
        }

        return $this->extractFromElement($element->first(), $extractType, $attribute);
    }

    /**
     * Extract breadcrumbs as array of {text => guid}
     */
    private function extractBreadcrumbs(string $selector): array
    {
        $elements = $this->crawler->filter($selector);
        $results = [];

        $elements->each(function (Crawler $element) use (&$results) {
            $text = trim($element->text());
            $href = $element->attr('href') ?? '';
            
            // Convert relative URLs to absolute if needed
            if (!empty($href) && !preg_match('/^https?:\/\//', $href)) {
                // Try to get base URL from current page context
                $baseUrl = $this->getBaseUrl();
                if ($baseUrl) {
                    $href = rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
                }
            }
            
            if (!empty($text)) {
                $results[] = [
                    'text' => $text,
                    'guid' => $href ?: $text, // Use text as guid if no href
                ];
            }
        });

        return $results;
    }
    
    /**
     * Get base URL from crawler context
     */
    private function getBaseUrl(): ?string
    {
        // Try to extract base URL from HTML
        $baseTag = $this->crawler->filter('base[href]');
        if ($baseTag->count() > 0) {
            return $baseTag->attr('href');
        }
        
        return null;
    }

    /**
     * Extract multiple values
     */
    private function extractMultiple(string $selector, string $extractType, ?string $attribute = null): array
    {
        $elements = $this->crawler->filter($selector);
        $results = [];

        $elements->each(function (Crawler $element) use ($extractType, $attribute, &$results) {
            $value = $this->extractFromElement($element, $extractType, $attribute);
            if ($value !== null && $value !== '') {
                $results[] = $value;
            }
        });

        return $results;
    }

    /**
     * Extract from element based on type
     */
    private function extractFromElement(Crawler $element, string $extractType, ?string $attribute = null)
    {
        switch ($extractType) {
            case 'text':
                return trim($element->text());

            case 'html':
                return $element->html();

            case 'attribute':
            case 'attr':
                // If specific attribute is requested, extract that
                if ($attribute !== null) {
                    $value = $element->attr($attribute);
                    // Convert relative URLs to absolute for href/src attributes
                    if (($attribute === 'href' || $attribute === 'src') && $value && !preg_match('/^https?:\/\//', $value)) {
                        $baseUrl = $this->getBaseUrl();
                        if ($baseUrl) {
                            $value = rtrim($baseUrl, '/') . '/' . ltrim($value, '/');
                        }
                    }
                    return $value;
                }
                // Extract all attributes
                $node = $element->getNode(0);
                if (!$node || !$node->attributes) {
                    return [];
                }
                $attrs = [];
                foreach ($node->attributes as $attr) {
                    $attrs[$attr->name] = $attr->value;
                }
                return $attrs;

            case 'href':
                $value = $element->attr('href');
                // Convert relative URLs to absolute
                if ($value && !preg_match('/^https?:\/\//', $value)) {
                    $baseUrl = $this->getBaseUrl();
                    if ($baseUrl) {
                        $value = rtrim($baseUrl, '/') . '/' . ltrim($value, '/');
                    }
                }
                return $value;

            case 'src':
                $value = $element->attr('src');
                // Convert relative URLs to absolute
                if ($value && !preg_match('/^https?:\/\//', $value)) {
                    $baseUrl = $this->getBaseUrl();
                    if ($baseUrl) {
                        $value = rtrim($baseUrl, '/') . '/' . ltrim($value, '/');
                    }
                }
                return $value;

            default:
                // Try as attribute name
                $value = $element->attr($extractType);
                if ($value !== null) {
                    // Convert relative URLs to absolute for href/src
                    if (($extractType === 'href' || $extractType === 'src') && !preg_match('/^https?:\/\//', $value)) {
                        $baseUrl = $this->getBaseUrl();
                        if ($baseUrl) {
                            $value = rtrim($baseUrl, '/') . '/' . ltrim($value, '/');
                        }
                    }
                    return $value;
                }
                return null;
        }
    }

    /**
     * Extract blog posts from HTML
     * Convenience method for common blog structure
     */
    public function extractBlogPosts(string $html): array
    {
        $this->crawler = new Crawler($html);
        $posts = [];

        // Common blog post selectors
        $postSelectors = [
            'article',
            '.post',
            '.blog-post',
            '.entry',
            '[class*="post-"]',
        ];

        foreach ($postSelectors as $selector) {
            $elements = $this->crawler->filter($selector);
            
            if ($elements->count() > 0) {
                $elements->each(function (Crawler $post) use (&$posts) {
                    $posts[] = $this->extractPostData($post);
                });
                break; // Found posts, stop trying other selectors
            }
        }

        return $posts;
    }

    /**
     * Extract data from single post element
     */
    private function extractPostData(Crawler $post): array
    {
        $data = [];

        // Title
        $titleSelectors = ['h1', 'h2', 'h3', '.entry-title', '.post-title'];
        foreach ($titleSelectors as $sel) {
            $title = $post->filter($sel);
            if ($title->count() > 0) {
                $data['title'] = trim($title->first()->text());
                break;
            }
        }

        // Link
        $linkSelectors = ['a[href]', 'h2 a', 'h3 a', '.entry-title a'];
        foreach ($linkSelectors as $sel) {
            $link = $post->filter($sel);
            if ($link->count() > 0) {
                $data['url'] = $link->first()->attr('href');
                break;
            }
        }

        // Excerpt/Content
        $contentSelectors = ['.entry-content', '.post-content', '.excerpt', 'p'];
        foreach ($contentSelectors as $sel) {
            $content = $post->filter($sel);
            if ($content->count() > 0) {
                $data['content'] = trim($content->first()->text());
                break;
            }
        }

        // Image
        $imageSelectors = ['img', '.post-thumbnail img', '.featured-image img'];
        foreach ($imageSelectors as $sel) {
            $image = $post->filter($sel);
            if ($image->count() > 0) {
                $data['image'] = $image->first()->attr('src');
                break;
            }
        }

        // Date
        $dateSelectors = ['time', '.post-date', '.entry-date', '[datetime]'];
        foreach ($dateSelectors as $sel) {
            $date = $post->filter($sel);
            if ($date->count() > 0) {
                $data['date'] = $date->first()->attr('datetime') ?: trim($date->first()->text());
                break;
            }
        }

        // Author
        $authorSelectors = ['.author', '.post-author', '[rel="author"]'];
        foreach ($authorSelectors as $sel) {
            $author = $post->filter($sel);
            if ($author->count() > 0) {
                $data['author'] = trim($author->first()->text());
                break;
            }
        }

        return $data;
    }
}

