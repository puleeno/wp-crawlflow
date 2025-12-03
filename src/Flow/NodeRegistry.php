<?php

namespace CrawlFlow\Flow;

/**
 * Node Registry
 * Manages node executors
 */
class NodeRegistry
{
    /**
     * @var NodeExecutorInterface[]
     */
    private array $executors = [];

    /**
     * Register a node executor
     */
    public function register(NodeExecutorInterface $executor): void
    {
        // Register for all supported node types
        $supportedTypes = $this->getSupportedTypes($executor);
        foreach ($supportedTypes as $type) {
            $this->executors[$type] = $executor;
        }
    }

    /**
     * Get executor for a node type
     */
    public function getExecutor(string $nodeType): ?NodeExecutorInterface
    {
        // First try direct lookup
        if (isset($this->executors[$nodeType])) {
            return $this->executors[$nodeType];
        }

        // Try finding by supports() method
        foreach ($this->executors as $executor) {
            if ($executor->supports($nodeType)) {
                // Cache it for next time
                $this->executors[$nodeType] = $executor;
                return $executor;
            }
        }

        return null;
    }

    /**
     * Check if executor exists for node type
     */
    public function hasExecutor(string $nodeType): bool
    {
        return isset($this->executors[$nodeType]);
    }

    /**
     * Get all registered executors
     */
    public function getAllExecutors(): array
    {
        // Return unique executor instances (not by type)
        $unique = [];
        $seen = [];
        
        foreach ($this->executors as $executor) {
            $hash = spl_object_hash($executor);
            if (!isset($seen[$hash])) {
                $unique[] = $executor;
                $seen[$hash] = true;
            }
        }
        
        return $unique;
    }

    /**
     * Get supported types from executor
     */
    private function getSupportedTypes(NodeExecutorInterface $executor): array
    {
        $types = [];
        
        // Common node types
        $commonTypes = [
            'start', 'click', 'loop', 'repository', 'reception',
            'worker', 'html-data-extractor', 'csv-extractor',
            'json-extractor', 'xml-extractor', 'mysql-extractor',
            'processor', 'completion'
        ];

        foreach ($commonTypes as $type) {
            if ($executor->supports($type)) {
                $types[] = $type;
            }
        }

        return $types;
    }
}

