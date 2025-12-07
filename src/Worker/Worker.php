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
            // Pass both rawData and rawItem to checkDetectionRule for URL pattern matching
            $ruleResult = $this->checkDetectionRule($rule, $rawData, $rawItem);
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
    private function checkDetectionRule(array $rule, $rawData, array $rawItem = []): bool
    {
        $type = $rule['type'] ?? 'dom-value';
        $selector = $rule['selector'] ?? '';
        $condition = $rule['condition'] ?? 'exists';
        $value = $rule['value'] ?? '';

        switch ($type) {
            case 'dom-value':
                return $this->checkDomRule($selector, $condition, $value, $rawData);

            case 'url-pattern':
            case 'url-format': // Alias for url-pattern
                return $this->checkUrlPattern($rule, $rawData, $rawItem);

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
     * Checks URL pattern against rawItem['guid'] (the URL of the item)
     */
    private function checkUrlPattern(array $rule, $rawData, array $rawItem = []): bool
    {
        // Get URL from rawItem['guid']
        $url = $rawItem['guid'] ?? '';
        
        if (empty($url)) {
            // Fallback: try to get from rawData if it's an array
            if (is_array($rawData)) {
                $url = $rawData['guid'] ?? $rawData['url'] ?? '';
            } elseif (is_string($rawData) && filter_var($rawData, FILTER_VALIDATE_URL)) {
                $url = $rawData;
            }
        }

        if (empty($url)) {
            error_log("CrawlFlow Worker: checkUrlPattern - No URL found");
            return false;
        }

        $pattern = $rule['pattern'] ?? '';
        if (empty($pattern)) {
            error_log("CrawlFlow Worker: checkUrlPattern - No pattern in rule");
            return false;
        }
        
        // Normalize pattern (handle excessive escaping)
        $normalizedPattern = $this->normalizeRegexPattern($pattern);
        if (!$normalizedPattern) {
            error_log("CrawlFlow Worker: checkUrlPattern - Failed to normalize pattern: {$pattern}");
            return false;
        }
        
        $match = @preg_match($normalizedPattern, $url);
        $condition = $rule['condition'] ?? 'matches';
        
        error_log("CrawlFlow Worker: checkUrlPattern - Original pattern: {$pattern}, Normalized: {$normalizedPattern}, URL: {$url}, Match: " . ($match === 1 ? 'YES' : 'NO'));
        
        if ($condition === 'not-matches') {
            return $match !== 1;
        }
        
        return $match === 1;
    }
    
    /**
     * Normalize regex pattern (similar to Phase1 handler)
     */
    private function normalizeRegexPattern(string $pattern): ?string
    {
        if (empty($pattern)) {
            return null;
        }
        
        // Remove excessive escaping - replace multiple backslashes with single backslash
        // Use a loop to handle deeply nested escaping
        $maxIterations = 10;
        $iteration = 0;
        while ($iteration < $maxIterations) {
            $newPattern = preg_replace('/\\\\+/', '\\', $pattern);
            if ($newPattern === $pattern) {
                break;
            }
            $pattern = $newPattern;
            $iteration++;
        }
        
        // If pattern already has delimiters (starts with /), check if slashes inside need escaping
        if (strpos($pattern, '/') === 0) {
            $lastSlashPos = strrpos($pattern, '/');
            
            // If there's a last slash and it's not the first character
            if ($lastSlashPos !== false && $lastSlashPos > 0) {
                $afterLastSlash = substr($pattern, $lastSlashPos + 1);
                
                // Check if after last slash is flags (only letters) or empty
                if (empty($afterLastSlash) || preg_match('/^[imsxADSUXJu]+$/', $afterLastSlash)) {
                    $flags = $afterLastSlash;
                    // Extract pattern between first / and last /
                    $actualPattern = substr($pattern, 1, $lastSlashPos - 1);
                    
                    // Escape unescaped slashes in the pattern (but not already escaped ones)
                    // First normalize any multiple backslashes before slash
                    $actualPattern = preg_replace('/\\\\+\//', '\\/', $actualPattern);
                    // Then escape any remaining unescaped slashes
                    $actualPattern = preg_replace('/(?<!\\\\)\//', '\\/', $actualPattern);
                    
                    $normalizedPattern = '/' . $actualPattern . '/' . $flags;
                    
                    // Debug: log the normalization
                    error_log("CrawlFlow Worker: normalizeRegexPattern - Pattern: {$pattern}, Extracted: " . substr($pattern, 1, $lastSlashPos - 1) . ", After escape: {$actualPattern}, Final: {$normalizedPattern}");
                    
                    // Test if pattern is valid
                    if (@preg_match($normalizedPattern, '') !== false) {
                        return $normalizedPattern;
                    }
                } else {
                    // Pattern doesn't end with flags, so the last slash is part of the pattern
                    // Extract everything after first / as the pattern
                    $actualPattern = substr($pattern, 1);
                    
                    // Escape unescaped slashes in the pattern
                    $actualPattern = preg_replace('/\\\\+\//', '\\/', $actualPattern);
                    $actualPattern = preg_replace('/(?<!\\\\)\//', '\\/', $actualPattern);
                    
                    $normalizedPattern = '/' . $actualPattern . '/';
                    
                    error_log("CrawlFlow Worker: normalizeRegexPattern - Pattern (no flags): {$pattern}, After escape: {$actualPattern}, Final: {$normalizedPattern}");
                    
                    // Test if pattern is valid
                    if (@preg_match($normalizedPattern, '') !== false) {
                        return $normalizedPattern;
                    }
                }
            }
        }
        
        // If pattern looks like it has delimiters but is malformed, try to fix
        if (strpos($pattern, '/') === 0 && strrpos($pattern, '/') !== false) {
            $lastSlash = strrpos($pattern, '/');
            $actualPattern = substr($pattern, 1, $lastSlash - 1);
            $flags = substr($pattern, $lastSlash + 1);
            
            // Escape unescaped slashes
            $actualPattern = preg_replace('/(?<!\\\\)\//', '\\/', $actualPattern);
            
            // Validate the actual pattern part
            if (@preg_match('/' . $actualPattern . '/', '') !== false) {
                return '/' . $actualPattern . '/' . $flags;
            }
        }
        
        // Otherwise, treat as simple string pattern
        // Escape special regex chars and add delimiters
        $escaped = preg_quote($pattern, '/');
        return '/' . $escaped . '/';
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
        // Check if worker has data extractor configured
        $hasExtractor = !empty($this->parserConfig['rules']) || !empty($this->parserConfig['customRules']);
        
        if (!$hasExtractor) {
            // No extractor - wrap raw item as PureDataItem
            $dataItem = \Rake\Entities\ParsedData\PureDataItem::fromRawItem($rawItem);
        } else {
            // Has extractor - extract data
            $extractedData = $this->extractData($rawItem);
            
            // Convert to ExtractedDataItem
            $dataItem = new \Rake\Entities\ParsedData\ExtractedDataItem($extractedData);
        }

        // Process through chain
        $result = $this->processChain($dataItem);

        return $result;
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
     * Process data item through processor chain
     * 
     * @param \Rake\Contracts\Entities\ParsedDataItemInterface $dataItem
     * @return array
     */
    private function processChain(\Rake\Contracts\Entities\ParsedDataItemInterface $dataItem): array
    {
        $item = $dataItem;

        foreach ($this->processorChain as $processorConfig) {
            $item = $this->runProcessor($processorConfig, $item);
            
            // Stop chain if processor returns NullDataItem
            if ($item->isNull()) {
                return [
                    'success' => false,
                    'error' => $item->getReason(),
                ];
            }
        }

        // Return data array from final item
        return array_merge($item->getData(), [
            'success' => true,
        ]);
    }

    /**
     * Run single processor
     * 
     * @param array $processorConfig Processor configuration
     * @param \Rake\Contracts\Entities\ParsedDataItemInterface $dataItem Data item
     * @return \Rake\Contracts\Entities\ParsedDataItemInterface Processed item
     */
    private function runProcessor(array $processorConfig, \Rake\Contracts\Entities\ParsedDataItemInterface $dataItem): \Rake\Contracts\Entities\ParsedDataItemInterface
    {
        $type = $processorConfig['type'] ?? '';

        switch ($type) {
            case 'save_to_wordpress':
            case 'wordpress':
                $options = $processorConfig['settings'] ?? $processorConfig['options'] ?? [];
                $processor = new WordPressPostProcessor($options);
                
                // Process returns DataItemInterface
                return $processor->process($dataItem);

            case 'transform':
                // Transform data based on mapping
                $mapping = $processorConfig['mapping'] ?? [];
                
                foreach ($mapping as $from => $to) {
                    if ($dataItem->has($from)) {
                        $dataItem->set($to, $dataItem->get($from));
                    }
                }
                
                return $dataItem;

            case 'filter':
                // Filter data based on conditions
                return $dataItem; // Pass through for now

            default:
                // Try to get processor from ProcessorManager
                if (class_exists('\Rake\Manager\ProcessorManager')) {
                    // Load processor classes if needed
                    $processorPath = WP_PLUGIN_DIR . '/wp-crawlflow/vendor/puleeno/rake-wordpress-adapter/src/Processor/';
                    $processorsToLoad = [
                        'import_woocommerce_product_category' => 'ImportWooCommerceProductCategoryProcessor.php',
                        'import_woocommerce_product' => 'ImportWooCommerceProductProcessor.php',
                        'scan_category_pages' => 'ScanCategoryPagesProcessor.php',
                        'collect_resources' => 'CollectResourcesProcessor.php',
                    ];
                    
                    if (isset($processorsToLoad[$type]) && file_exists($processorPath . $processorsToLoad[$type])) {
                        require_once $processorPath . $processorsToLoad[$type];
                    }
                    
                    // Trigger hook to register processors if not already done
                    if (!did_action('crawlflow_register_processors')) {
                        do_action('crawlflow_register_processors');
                    }
                    
                    if (\Rake\Manager\ProcessorManager::has($type)) {
                        $settings = $processorConfig['settings'] ?? [];
                        $processorManager = new \Rake\Manager\ProcessorManager();
                        $processor = $processorManager->getProcessor($type, $settings);
                        
                        if ($processor) {
                            return $processor->process($dataItem);
                        }
                    }
                }
                
                // Unknown processor, pass through
                error_log("CrawlFlow Worker: Unknown processor type '{$type}', passing through");
                return $dataItem;
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

