<?php

namespace CrawlFlow\Reception;

use CrawlFlow\Worker\Worker;

/**
 * Reception
 * Loads workers and assigns raw items to appropriate workers based on detection rules
 * Follows Rake Reception pattern
 * 
 * Note: Rake\Reception\ReceptionInterface is a stub with no methods defined,
 * so we don't implement it. We follow the Rake reception pattern instead.
 */
class Reception
{
    /**
     * @var array Workers loaded from config
     */
    private array $workers = [];

    /**
     * @var array Project configuration
     */
    private array $config;

    /**
     * Constructor
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->loadWorkers();
    }

    /**
     * Load workers from project configuration
     * Sorted by priority (higher priority first)
     */
    private function loadWorkers(): void
    {
        $workerConfigs = $this->config['workers'] ?? [];

        foreach ($workerConfigs as $workerConfig) {
            $this->workers[] = new Worker($workerConfig);
        }

        // Sort by priority (descending)
        usort($this->workers, function (Worker $a, Worker $b) {
            return $b->getPriority() - $a->getPriority();
        });
    }

    /**
     * Get all loaded workers
     */
    public function getWorkers(): array
    {
        return $this->workers;
    }

    /**
     * Process raw items from data origins
     * 
     * @param array $rawItems Raw items from rake_data_origins
     * @return array Processing results
     */
    public function processRawItems(array $rawItems): array
    {
        $results = [];

        foreach ($rawItems as $rawItem) {
            $result = $this->processRawItem($rawItem);
            $results[] = $result;
        }

        return $results;
    }

    /**
     * Process single raw item
     * 
     * @param array $rawItem Raw item data
     * @return array Processing result
     */
    private function processRawItem(array $rawItem): array
    {
        // Find appropriate worker for this item
        $worker = $this->assignToWorker($rawItem);

        if (!$worker) {
            return [
                'success' => false,
                'item_id' => $rawItem['id'] ?? null,
                'error' => 'No worker can handle this item',
            ];
        }

        try {
            // Worker processes the item
            $processedData = $worker->process($rawItem);

            return [
                'success' => true,
                'item_id' => $rawItem['id'] ?? null,
                'worker' => $worker->getName(),
                'data' => $processedData,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'item_id' => $rawItem['id'] ?? null,
                'worker' => $worker->getName(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Assign raw item to appropriate worker based on detection rules
     * 
     * @param array $rawItem Raw item data
     * @return Worker|null Assigned worker or null
     */
    public function assignToWorker(array $rawItem): ?Worker
    {
        // Workers are already sorted by priority
        foreach ($this->workers as $worker) {
            if ($worker->canHandle($rawItem)) {
                return $worker;
            }
        }

        return null;
    }

    /**
     * Get statistics about item assignment
     */
    public function getAssignmentStats(array $rawItems): array
    {
        $stats = [
            'total' => count($rawItems),
            'assigned' => 0,
            'unassigned' => 0,
            'by_worker' => [],
        ];

        foreach ($rawItems as $rawItem) {
            $worker = $this->assignToWorker($rawItem);

            if ($worker) {
                $stats['assigned']++;
                $workerName = $worker->getName();
                $stats['by_worker'][$workerName] = ($stats['by_worker'][$workerName] ?? 0) + 1;
            } else {
                $stats['unassigned']++;
            }
        }

        return $stats;
    }
}

