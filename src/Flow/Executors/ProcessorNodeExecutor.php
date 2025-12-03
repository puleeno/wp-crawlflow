<?php

namespace CrawlFlow\Flow\Executors;

use CrawlFlow\Flow\NodeExecutorInterface;
use CrawlFlow\Flow\ExecutionContext;
use CrawlFlow\Flow\NodeResult;
use CrawlFlow\Flow\RakeAdapter;
use CrawlFlow\Processors\WordPressPostProcessor;

/**
 * Processor Node Executor
 * Executes processor nodes (save-to-database, etc.)
 */
class ProcessorNodeExecutor implements NodeExecutorInterface
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
     * Check if this executor supports the given node type
     */
    public function supports(string $nodeType): bool
    {
        return $nodeType === 'processor';
    }

    /**
     * Execute the node
     */
    public function execute(array $node, ExecutionContext $context): NodeResult
    {
        $nodeId = $node['id'] ?? 'unknown';
        $data = $node['data'] ?? [];

        try {
            $processorType = $data['processorType'] ?? '';
            $settings = $data['settings'] ?? [];

            if (empty($processorType)) {
                return NodeResult::error('Processor type not specified');
            }

            // Get extracted data from context
            $extractedData = $context->getExtractedData();

            if (empty($extractedData)) {
                return NodeResult::error('No extracted data to process');
            }

            $processedResults = [];

            switch ($processorType) {
                case 'save-to-wordpress':
                case 'save-to-database':
                    $processedResults = $this->processToWordPress($extractedData, $settings);
                    break;

                case 'export-to-json':
                    $processedResults = $this->processToJson($extractedData, $settings);
                    break;

                case 'export-to-csv':
                    $processedResults = $this->processToCsv($extractedData, $settings);
                    break;

                default:
                    return NodeResult::error("Unknown processor type: {$processorType}");
            }

            return NodeResult::success($processedResults, [
                'processed_count' => count($processedResults),
                'processor_type' => $processorType,
            ]);

        } catch (\Exception $e) {
            return NodeResult::error('Processing failed: ' . $e->getMessage());
        }
    }

    /**
     * Process to WordPress posts
     */
    private function processToWordPress(array $items, array $settings): array
    {
        $processor = new WordPressPostProcessor();
        
        $options = [
            'postType' => $settings['postType'] ?? 'post',
            'postStatus' => $settings['postStatus'] ?? 'draft',
            'authorId' => $settings['authorId'] ?? 1,
            'updateIfExists' => $settings['updateIfExists'] ?? false,
            'categories' => $settings['categories'] ?? [],
        ];

        return $processor->processBatch($items, $options);
    }

    /**
     * Process to JSON file
     */
    private function processToJson(array $items, array $settings): array
    {
        $filename = $settings['filename'] ?? 'crawlflow-export-' . time() . '.json';
        $filepath = WP_CONTENT_DIR . '/crawlflow/exports/' . $filename;

        // Create directory if needed
        wp_mkdir_p(dirname($filepath));

        // Save JSON
        file_put_contents($filepath, json_encode($items, JSON_PRETTY_PRINT));

        return [[
            'success' => true,
            'file' => $filepath,
            'count' => count($items),
        ]];
    }

    /**
     * Process to CSV file
     */
    private function processToCsv(array $items, array $settings): array
    {
        $filename = $settings['filename'] ?? 'crawlflow-export-' . time() . '.csv';
        $filepath = WP_CONTENT_DIR . '/crawlflow/exports/' . $filename;

        // Create directory if needed
        wp_mkdir_p(dirname($filepath));

        // Open CSV file
        $handle = fopen($filepath, 'w');

        if ($handle === false) {
            throw new \RuntimeException('Failed to open CSV file for writing');
        }

        // Write headers (from first item)
        if (!empty($items)) {
            fputcsv($handle, array_keys($items[0]));

            // Write rows
            foreach ($items as $item) {
                fputcsv($handle, $item);
            }
        }

        fclose($handle);

        return [[
            'success' => true,
            'file' => $filepath,
            'count' => count($items),
        ]];
    }
}
