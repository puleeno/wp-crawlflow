<?php

namespace CrawlFlow\Worker;

use CrawlFlow\Contracts\WorkerInterface;
use CrawlFlow\DataExtractors\HtmlDataExtractor;
use CrawlFlow\Processors\WordPressPostProcessor;

/**
 * Worker
 * Processes raw items using detection rules, parser, and processor chain
 * Implements WorkerInterface contract
 */
class Worker implements WorkerInterface
{
    /**
     * @var string Worker name
     */
    private string $name;

    /**
     * @var int Priority (higher = checked first)
     */
    private int $priority;

    /**
     * @var array Detection rules
     */
    private array $detectionRules;

    /**
     * @var string Detection logic (and/or)
     */
    private string $detectionLogic;

    /**
     * @var array Parser configuration
     */
    private array $parserConfig;

    /**
     * @var array Processor chain configuration
     */
    private array $processorChain;

    /**
     * Constructor
     */
    public function __construct(array $config)
    {
        $this->name = $config['name'] ?? 'unnamed_worker';
        $this->priority = $config['priority'] ?? 0;
        $this->detectionRules = $config['detectionRules'] ?? $config['detection_rules'] ?? [];
        $this->detectionLogic = $config['detectionLogic'] ?? $config['detection_logic'] ?? 'and';
        $this->parserConfig = $config['parser'] ?? [];
        $this->processorChain = $config['processors'] ?? $config['processor_chain'] ?? [];
    }

    /**
     * Get worker name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get worker priority
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Check if worker can handle this raw item
     * Uses detection rules to determine
     */
    public function canHandle(array $rawItem): bool
    {
        if (empty($this->detectionRules)) {
            return false;
        }

        $rawData = $this->getRawData($rawItem);
        $results = [];

        foreach ($this->detectionRules as $rule) {
            $ruleResult = $this->checkDetectionRule($rule, $rawData);
            $results[] = $ruleResult;
        }

        // Apply detection logic (and/or)
        if ($this->detectionLogic === 'or') {
            return in_array(true, $results, true);
        } else {
            return !in_array(false, $results, true);
        }
    }

    /**
     * Check single detection rule
     */
    private function checkDetectionRule(array $rule, $rawData): bool
    {
        $type = $rule['type'] ?? 'dom-value';
        $selector = $rule['selector'] ?? '';
        $condition = $rule['condition'] ?? 'exists';
        $value = $rule['value'] ?? '';

        switch ($type) {
            case 'dom-value':
                return $this->checkDomRule($selector, $condition, $value, $rawData);

            case 'url-pattern':
                return $this->checkUrlPattern($rule, $rawData);

            case 'content-contains':
                return $this->checkContentContains($value, $rawData);

            default:
                return false;
        }
    }

    /**
     * Check DOM-based detection rule
     */
    private function checkDomRule(string $selector, string $condition, string $value, $rawData): bool
    {
        if (!is_string($rawData)) {
            return false;
        }

        try {
            $extractor = new HtmlDataExtractor();
            $extracted = $extractor->extract($rawData, [
                ['name' => 'check', 'selector' => $selector, 'extract' => 'text']
            ]);

            $domValue = $extracted['check'] ?? null;

            switch ($condition) {
                case 'exists':
                    return $domValue !== null;

                case 'not-exists':
                    return $domValue === null;

                case 'equals':
                    return $domValue === $value;

                case 'contains':
                    return $domValue && strpos($domValue, $value) !== false;

                default:
                    return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check URL pattern rule
     */
    private function checkUrlPattern(array $rule, $rawData): bool
    {
        $url = '';
        
        if (is_array($rawData) && isset($rawData['url'])) {
            $url = $rawData['url'];
        } elseif (is_string($rawData) && filter_var($rawData, FILTER_VALIDATE_URL)) {
            $url = $rawData;
        }

        if (empty($url)) {
            return false;
        }

        $pattern = $rule['pattern'] ?? '';
        return !empty($pattern) && preg_match($pattern, $url);
    }

    /**
     * Check content contains rule
     */
    private function checkContentContains(string $needle, $rawData): bool
    {
        $content = is_string($rawData) ? $rawData : json_encode($rawData);
        return strpos($content, $needle) !== false;
    }

    /**
     * Get raw data from item
     */
    private function getRawData(array $rawItem)
    {
        // Try to get raw_data field
        if (isset($rawItem['raw_data'])) {
            // Try to decode if JSON
            if (is_string($rawItem['raw_data'])) {
                $decoded = json_decode($rawItem['raw_data'], true);
                return $decoded ?? $rawItem['raw_data'];
            }
            return $rawItem['raw_data'];
        }

        // Return whole item
        return $rawItem;
    }

    /**
     * Process raw item through parser and processor chain
     * 
     * @param array $rawItem Raw item from rake_data_origins
     * @return array Processed data
     * @throws \RuntimeException If processing fails
     */
    public function process(array $rawItem): array
    {
        // Step 1: Parse/Extract data
        $extractedData = $this->extractData($rawItem);

        // Step 2: Process through chain
        $processedData = $this->processChain($extractedData);

        return $processedData;
    }

    /**
     * Extract data from raw item using parser
     */
    private function extractData(array $rawItem): array
    {
        $rawData = $this->getRawData($rawItem);

        if (!is_string($rawData)) {
            $rawData = json_encode($rawData);
        }

        // Use HtmlDataExtractor (implements ParserInterface)
        $extractor = new HtmlDataExtractor();

        // Extract using rules from parser config
        $rules = $this->parserConfig['rules'] ?? $this->parserConfig['customRules'] ?? [];

        if (empty($rules)) {
            // Use blog post preset as default
            return $extractor->extractBlogPosts($rawData)[0] ?? [];
        }

        return $extractor->extract($rawData, $rules);
    }

    /**
     * Process extracted data through processor chain
     */
    private function processChain(array $extractedData): array
    {
        $data = $extractedData;

        foreach ($this->processorChain as $processorConfig) {
            $data = $this->runProcessor($processorConfig, $data);
        }

        return $data;
    }

    /**
     * Run single processor
     */
    private function runProcessor(array $processorConfig, array $data): array
    {
        $type = $processorConfig['type'] ?? '';

        switch ($type) {
            case 'save_to_wordpress':
            case 'wordpress':
                $processor = new WordPressPostProcessor();
                $options = $processorConfig['settings'] ?? $processorConfig['options'] ?? [];
                
                $postId = $processor->process($data, $options);
                
                // Transform data to include post_id
                $data['post_id'] = $postId;
                $data['processed'] = true;
                
                return $data;

            case 'transform':
                // Transform data based on mapping
                $mapping = $processorConfig['mapping'] ?? [];
                return $this->transformData($data, $mapping);

            case 'filter':
                // Filter data based on conditions
                return $data; // Pass through for now

            default:
                // Unknown processor, pass through
                return $data;
        }
    }

    /**
     * Transform data based on mapping rules
     */
    private function transformData(array $data, array $mapping): array
    {
        $transformed = [];

        foreach ($mapping as $targetKey => $sourceKey) {
            $transformed[$targetKey] = $data[$sourceKey] ?? null;
        }

        return $transformed;
    }
}

