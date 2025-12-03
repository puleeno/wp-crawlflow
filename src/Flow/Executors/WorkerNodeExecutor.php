<?php

namespace CrawlFlow\Flow\Executors;

use CrawlFlow\Flow\NodeExecutorInterface;
use CrawlFlow\Flow\ExecutionContext;
use CrawlFlow\Flow\NodeResult;
use CrawlFlow\Flow\RakeAdapter;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Worker Node Executor
 * Filters resources based on detection rules
 */
class WorkerNodeExecutor implements NodeExecutorInterface
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
        return 'worker';
    }

    /**
     * Check if this executor supports the given node type
     */
    public function supports(string $nodeType): bool
    {
        return $nodeType === 'worker';
    }

    /**
     * Execute node
     */
    public function execute(array $node, ExecutionContext $context): NodeResult
    {
        $nodeData = $node['data'] ?? [];
        $detectionRules = $nodeData['detectionRules'] ?? [];
        $detectionLogic = $nodeData['detectionLogic'] ?? 'and';

        // Get resources from repository
        $resources = $context->getRepository();

        if (empty($resources)) {
            return NodeResult::error('No resources in repository');
        }

        $matched = [];

        foreach ($resources as $resource) {
            $html = $resource['content'] ?? '';
            $url = $resource['url'] ?? '';

            if (empty($html)) {
                continue;
            }

            try {
                $isMatch = $this->checkDetectionRules($html, $detectionRules, $detectionLogic);

                if ($isMatch) {
                    $matched[] = $resource;
                    $context->addLog("Worker matched: {$url}", 'info');
                } else {
                    $context->addLog("Worker skipped: {$url}", 'debug');
                }
            } catch (\Exception $e) {
                $context->addLog("Detection error for {$url}: " . $e->getMessage(), 'error');
            }
        }

        return NodeResult::success($matched, [
            'matched_count' => count($matched),
            'total_checked' => count($resources),
        ]);
    }

    /**
     * Check if resource matches detection rules
     */
    private function checkDetectionRules(string $html, array $rules, string $logic): bool
    {
        if (empty($rules)) {
            return true; // No rules = match all
        }

        $crawler = new Crawler($html);
        $results = [];

        foreach ($rules as $rule) {
            $type = $rule['type'] ?? 'dom-value';
            $selector = $rule['selector'] ?? '';
            $condition = $rule['condition'] ?? 'exists';
            $value = $rule['value'] ?? '';

            $ruleResult = $this->checkRule($crawler, $type, $selector, $condition, $value);
            $results[] = $ruleResult;
        }

        // Apply logic (AND/OR)
        if ($logic === 'and') {
            return !in_array(false, $results, true);
        } else { // 'or'
            return in_array(true, $results, true);
        }
    }

    /**
     * Check single detection rule
     */
    private function checkRule(Crawler $crawler, string $type, string $selector, string $condition, string $value): bool
    {
        try {
            $filtered = $crawler->filter($selector);

            switch ($condition) {
                case 'exists':
                    return $filtered->count() > 0;

                case 'not-exists':
                    return $filtered->count() === 0;

                case 'contains':
                    if ($filtered->count() === 0) {
                        return false;
                    }
                    $text = $filtered->first()->text();
                    return stripos($text, $value) !== false;

                case 'equals':
                    if ($filtered->count() === 0) {
                        return false;
                    }
                    $text = $filtered->first()->text();
                    return trim($text) === trim($value);

                case 'regex':
                    if ($filtered->count() === 0) {
                        return false;
                    }
                    $text = $filtered->first()->text();
                    return preg_match($value, $text) === 1;

                default:
                    return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }
}

