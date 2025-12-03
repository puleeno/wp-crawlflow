<?php

namespace CrawlFlow\Flow\Executors;

use CrawlFlow\Flow\NodeExecutorInterface;
use CrawlFlow\Flow\ExecutionContext;
use CrawlFlow\Flow\NodeResult;
use CrawlFlow\Flow\RakeAdapter;
use Symfony\Component\DomCrawler\Crawler;

/**
 * HTML Data Extractor Executor
 * Extracts data from HTML content using CSS selectors
 */
class HTMLDataExtractorExecutor implements NodeExecutorInterface
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
     * Get node type
     */
    public function getType(): string
    {
        return 'html-data-extractor';
    }

    /**
     * Check if this executor supports the given node type
     */
    public function supports(string $nodeType): bool
    {
        return $nodeType === 'html-data-extractor';
    }

    /**
     * Execute node
     */
    public function execute(array $node, ExecutionContext $context): NodeResult
    {
        $nodeData = $node['data'] ?? [];
        $customRules = $nodeData['customRules'] ?? [];

        if (empty($customRules)) {
            return NodeResult::error('No extraction rules defined');
        }

        // Get resources from repository
        $resources = $context->getRepository();
        
        if (empty($resources)) {
            return NodeResult::error('No resources to extract data from');
        }

        $extracted = [];

        foreach ($resources as $resource) {
            $html = $resource['content'] ?? '';
            $url = $resource['url'] ?? '';

            if (empty($html)) {
                continue;
            }

            try {
                $data = $this->extractData($html, $customRules, $url);
                
                if (!empty($data)) {
                    $extracted[] = $data;
                }
            } catch (\Exception $e) {
                $context->addLog("Extraction failed for {$url}: " . $e->getMessage(), 'error');
            }
        }

        return NodeResult::success($extracted, [
            'extracted_count' => count($extracted),
        ]);
    }

    /**
     * Extract data from HTML using rules
     */
    private function extractData(string $html, array $rules, string $url): array
    {
        $crawler = new Crawler($html);
        $data = ['source_url' => $url];

        foreach ($rules as $rule) {
            $name = $rule['name'] ?? 'field_' . uniqid();
            $extractFrom = $rule['extractFrom'] ?? 'html-element';
            $selector = $rule['selector'] ?? '';
            $extract = $rule['extract'] ?? 'text';

            if (empty($selector)) {
                continue;
            }

            try {
                $value = $this->applyRule($crawler, $selector, $extract, $extractFrom);
                $data[$name] = $value;
            } catch (\Exception $e) {
                // Field extraction failed, skip
                $data[$name] = null;
            }
        }

        return $data;
    }

    /**
     * Apply extraction rule
     */
    private function applyRule(Crawler $crawler, string $selector, string $extract, string $extractFrom): mixed
    {
        $filtered = $crawler->filter($selector);

        if ($filtered->count() === 0) {
            return null;
        }

        switch ($extract) {
            case 'text':
                return $filtered->first()->text();

            case 'html':
                return $filtered->first()->html();

            case 'attribute':
                $attr = $extractFrom === 'attribute' ? ($selector['attribute'] ?? 'href') : 'href';
                return $filtered->first()->attr($attr);

            case 'all-text':
                return $filtered->each(function (Crawler $node) {
                    return $node->text();
                });

            case 'all-html':
                return $filtered->each(function (Crawler $node) {
                    return $node->html();
                });

            default:
                return $filtered->first()->text();
        }
    }
}

