<?php

namespace CrawlFlow\Flow\Executors;

use CrawlFlow\Flow\NodeExecutorInterface;
use CrawlFlow\Flow\ExecutionContext;
use CrawlFlow\Flow\NodeResult;
use CrawlFlow\Flow\RakeAdapter;
use CrawlFlow\DataExtractors\HtmlDataExtractor;

/**
 * HTML Data Extractor Node Executor
 * Executes html-data-extractor nodes
 */
class HtmlDataExtractorNodeExecutor implements NodeExecutorInterface
{
    /**
     * @var RakeAdapter
     */
    private RakeAdapter $rakeAdapter;

    /**
     * @var HtmlDataExtractor
     */
    private HtmlDataExtractor $extractor;

    /**
     * Constructor
     */
    public function __construct(RakeAdapter $rakeAdapter)
    {
        $this->rakeAdapter = $rakeAdapter;
        $this->extractor = new HtmlDataExtractor();
    }

    /**
     * Check if this executor supports the given node type
     */
    public function supports(string $nodeType): bool
    {
        return $nodeType === 'html-data-extractor';
    }

    /**
     * Execute the node
     */
    public function execute(array $node, ExecutionContext $context): NodeResult
    {
        $nodeId = $node['id'] ?? 'unknown';
        $data = $node['data'] ?? [];

        try {
            // Get extraction rules
            $customRules = $data['customRules'] ?? [];
            $presets = $data['presets'] ?? [];

            if (empty($customRules) && empty($presets)) {
                return NodeResult::error('No extraction rules defined');
            }

            // Get resources from repository
            $repository = $context->getRepository();
            
            if (empty($repository)) {
                return NodeResult::error('No resources in repository to extract from');
            }

            $extractedItems = [];

            foreach ($repository as $resource) {
                $html = $resource['body'] ?? $resource['html'] ?? '';
                
                if (empty($html)) {
                    continue;
                }

                // Extract using custom rules
                if (!empty($customRules)) {
                    $extracted = $this->extractor->extract($html, $customRules);
                    $extracted['source_url'] = $resource['url'] ?? '';
                    $extractedItems[] = $extracted;
                }

                // Use blog post preset if specified
                if (in_array('blog-posts', $presets)) {
                    $posts = $this->extractor->extractBlogPosts($html);
                    foreach ($posts as $post) {
                        $post['source_url'] = $resource['url'] ?? '';
                        $extractedItems[] = $post;
                    }
                }
            }

            return NodeResult::success($extractedItems, [
                'extracted_count' => count($extractedItems),
            ]);

        } catch (\Exception $e) {
            return NodeResult::error('Extraction failed: ' . $e->getMessage());
        }
    }
}

