<?php

namespace CrawlFlow\Contracts;

/**
 * Worker Interface
 * Defines contract for workers that process raw items
 */
interface WorkerInterface
{
    /**
     * Get worker name
     * 
     * @return string
     */
    public function getName(): string;

    /**
     * Get worker priority
     * Higher priority workers are checked first
     * 
     * @return int
     */
    public function getPriority(): int;

    /**
     * Check if this worker can handle the given raw item
     * Uses detection rules to determine
     * 
     * @param array $rawItem Raw item from rake_data_origins
     * @return bool True if worker can handle this item
     */
    public function canHandle(array $rawItem): bool;

    /**
     * Process raw item through parser and processor chain
     * 
     * @param array $rawItem Raw item from rake_data_origins
     * @return array Processed data
     * @throws \RuntimeException If processing fails
     */
    public function process(array $rawItem): array;
}

