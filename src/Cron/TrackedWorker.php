<?php

namespace CrawlFlow\Cron;

use CrawlFlow\Worker\Worker;
use CrawlFlow\Cron\ParsedItemVersioningService;
use Rake\Contracts\Entities\ParsedDataItemInterface;

/**
 * Tracked Worker
 * Wrapper around Worker to track parsed data versions through processor chain
 */
class TrackedWorker
{
    private Worker $worker;
    private ParsedItemVersioningService $versioningService;
    private int $originId;

    public function __construct(Worker $worker, ParsedItemVersioningService $versioningService, int $originId)
    {
        $this->worker = $worker;
        $this->versioningService = $versioningService;
        $this->originId = $originId;
    }

    /**
     * Process raw item and track versions
     */
    public function process(array $rawItem): array
    {
        // Use reflection to access private methods
        $reflection = new \ReflectionClass($this->worker);
        
        // Get extractor data (before processors)
        $hasExtractor = !empty($this->getParserConfig()['rules']) || !empty($this->getParserConfig()['customRules']);
        
        if ($hasExtractor) {
            $extractMethod = $reflection->getMethod('extractData');
            $extractMethod->setAccessible(true);
            $extractedData = $extractMethod->invoke($this->worker, $rawItem);
            
            // Save version 1: extracted data from worker
            $this->versioningService->saveParsedItem(
                $this->originId,
                $extractedData,
                'worker',
                $this->worker->getName(),
                $this->worker->getName(),
                ['step' => 'extraction']
            );
        } else {
            // No extractor - save raw data as version 1
            $rawData = $this->getRawData($rawItem);
            // Ensure rawData is an array
            if (!is_array($rawData)) {
                $rawData = ['raw_data' => $rawData];
            }
            $this->versioningService->saveParsedItem(
                $this->originId,
                $rawData,
                'worker',
                $this->worker->getName(),
                $this->worker->getName(),
                ['step' => 'raw_data']
            );
        }

        // Process through processor chain with tracking
        $processorChain = $this->getProcessorChain();
        $currentData = $hasExtractor ? $extractedData : $this->getRawData($rawItem);
        
        // Create data item
        if ($hasExtractor) {
            $dataItem = new \Rake\Entities\ParsedData\ExtractedDataItem($extractedData);
        } else {
            $dataItem = \Rake\Entities\ParsedData\PureDataItem::fromRawItem($rawItem);
        }

        // Process through each processor and track versions
        $processorIndex = 0;
        foreach ($processorChain as $processorConfig) {
            $processorIndex++;
            $processorType = $processorConfig['type'] ?? 'unknown';
            $processorName = $processorConfig['name'] ?? "Processor {$processorIndex}";
            $processorId = $processorConfig['id'] ?? $processorType . '_' . $processorIndex;

            // Run processor
            $processedItem = $this->runProcessor($processorConfig, $dataItem);
            
            if ($processedItem->isNull()) {
                // Processor failed
                $this->versioningService->saveParsedItem(
                    $this->originId,
                    array_merge($currentData, ['success' => false, 'error' => $processedItem->getReason()]),
                    'processor',
                    $processorId,
                    $processorName,
                    ['step' => 'processor_' . $processorIndex, 'failed' => true]
                );
                
                return [
                    'success' => false,
                    'error' => $processedItem->getReason(),
                ];
            }

            // Get new data
            $newData = $processedItem->getData();
            
            // Save new version if data changed
            $version = $this->versioningService->saveParsedItem(
                $this->originId,
                $newData,
                'processor',
                $processorId,
                $processorName,
                ['step' => 'processor_' . $processorIndex, 'processor_type' => $processorType]
            );

            // Update current data and data item for next processor
            $currentData = $newData;
            $dataItem = $processedItem;
        }

        // Return final result
        return array_merge($currentData, [
            'success' => true,
        ]);
    }

    /**
     * Get parser config using reflection
     */
    private function getParserConfig(): array
    {
        $reflection = new \ReflectionClass($this->worker);
        $property = $reflection->getProperty('parserConfig');
        $property->setAccessible(true);
        return $property->getValue($this->worker);
    }

    /**
     * Get processor chain using reflection
     */
    private function getProcessorChain(): array
    {
        $reflection = new \ReflectionClass($this->worker);
        $property = $reflection->getProperty('processorChain');
        $property->setAccessible(true);
        return $property->getValue($this->worker);
    }

    /**
     * Get raw data - always returns array
     */
    private function getRawData(array $rawItem): array
    {
        if (isset($rawItem['raw_data'])) {
            if (is_string($rawItem['raw_data'])) {
                $decoded = json_decode($rawItem['raw_data'], true);
                if (is_array($decoded)) {
                    return $decoded;
                }
                // If decoded is not array, wrap in array
                return ['raw_data' => $rawItem['raw_data']];
            }
            if (is_array($rawItem['raw_data'])) {
                return $rawItem['raw_data'];
            }
            // If raw_data is not array, wrap it
            return ['raw_data' => $rawItem['raw_data']];
        }
        // Return rawItem as is (should be array)
        return is_array($rawItem) ? $rawItem : ['raw_item' => $rawItem];
    }

    /**
     * Run processor (same logic as Worker)
     */
    private function runProcessor(array $processorConfig, ParsedDataItemInterface $dataItem): ParsedDataItemInterface
    {
        // Use reflection to call Worker's runProcessor method
        $reflection = new \ReflectionClass($this->worker);
        $method = $reflection->getMethod('runProcessor');
        $method->setAccessible(true);
        
        return $method->invoke($this->worker, $processorConfig, $dataItem);
    }

    /**
     * Get worker name
     */
    public function getName(): string
    {
        return $this->worker->getName();
    }

    /**
     * Check if can handle
     */
    public function canHandle(array $rawItem): bool
    {
        return $this->worker->canHandle($rawItem);
    }
}

