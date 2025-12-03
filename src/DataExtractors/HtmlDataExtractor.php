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

        foreach ($rules as $rule) {
            $name = $rule['name'] ?? 'field';
            $selector = $rule['selector'] ?? '';
            $extractType = $rule['extract'] ?? 'text';
            $multiple = $rule['multiple'] ?? false;

            if (empty($selector)) {
                continue;
            }

            try {
                if ($multiple) {
                    $results[$name] = $this->extractMultiple($selector, $extractType);
                } else {
                    $results[$name] = $this->extractSingle($selector, $extractType);
                }
            } catch (\Exception $e) {
                $results[$name] = null;
            }
        }

        return $results;
    }

    /**
     * Extract single value
     */
    private function extractSingle(string $selector, string $extractType)
    {
        $element = $this->crawler->filter($selector);

        if ($element->count() === 0) {
            return null;
        }

        return $this->extractFromElement($element->first(), $extractType);
    }

    /**
     * Extract multiple values
     */
    private function extractMultiple(string $selector, string $extractType): array
    {
        $elements = $this->crawler->filter($selector);
        $results = [];

        $elements->each(function (Crawler $element) use ($extractType, &$results) {
            $results[] = $this->extractFromElement($element, $extractType);
        });

        return $results;
    }

    /**
     * Extract from element based on type
     */
    private function extractFromElement(Crawler $element, string $extractType)
    {
        switch ($extractType) {
            case 'text':
                return trim($element->text());

            case 'html':
                return $element->html();

            case 'attr':
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
                return $element->attr('href');

            case 'src':
                return $element->attr('src');

            default:
                return $element->attr($extractType);
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

