<?php

namespace CrawlFlow\Flow;

/**
 * Execution Context
 * Manages state during flow execution
 */
class ExecutionContext
{
    /**
     * @var FlowConfig
     */
    private FlowConfig $flow;

    /**
     * @var array Repository of resources (URLs, data items)
     */
    private array $repository = [];

    /**
     * @var array Extracted data items
     */
    private array $extractedData = [];

    /**
     * @var array Processed results
     */
    private array $processedResults = [];

    /**
     * @var array Execution state for each node
     */
    private array $nodeStates = [];

    /**
     * @var array Execution errors
     */
    private array $errors = [];

    /**
     * @var array Execution logs
     */
    private array $logs = [];

    /**
     * @var bool Execution status
     */
    private bool $isCompleted = false;

    /**
     * Constructor
     */
    public function __construct(FlowConfig $flow)
    {
        $this->flow = $flow;
    }

    /**
     * Get flow configuration
     */
    public function getFlow(): FlowConfig
    {
        return $this->flow;
    }

    /**
     * Add resource to repository
     */
    public function addResource(array $resource): void
    {
        $this->repository[] = $resource;
    }

    /**
     * Add multiple resources to repository
     */
    public function addResources(array $resources): void
    {
        $this->repository = array_merge($this->repository, $resources);
    }

    /**
     * Get repository
     */
    public function getRepository(): array
    {
        return $this->repository;
    }

    /**
     * Get resources by source node
     */
    public function getResourcesBySource(string $nodeId): array
    {
        return array_filter($this->repository, function ($resource) use ($nodeId) {
            return ($resource['source_node_id'] ?? '') === $nodeId;
        });
    }

    /**
     * Add extracted data item
     */
    public function addExtractedData(array $dataItem): void
    {
        $this->extractedData[] = $dataItem;
    }

    /**
     * Get extracted data
     */
    public function getExtractedData(): array
    {
        return $this->extractedData;
    }

    /**
     * Get extracted data by extractor node
     */
    public function getExtractedDataByExtractor(string $extractorId): array
    {
        return array_filter($this->extractedData, function ($item) use ($extractorId) {
            return ($item['extractor_id'] ?? '') === $extractorId;
        });
    }

    /**
     * Add processed result
     */
    public function addProcessedResult(array $result): void
    {
        $this->processedResults[] = $result;
    }

    /**
     * Get processed results
     */
    public function getProcessedResults(): array
    {
        return $this->processedResults;
    }

    /**
     * Set node state
     */
    public function setNodeState(string $nodeId, array $state): void
    {
        $this->nodeStates[$nodeId] = $state;
    }

    /**
     * Get node state
     */
    public function getNodeState(string $nodeId): ?array
    {
        return $this->nodeStates[$nodeId] ?? null;
    }

    /**
     * Add error
     */
    public function addError(string $message, ?string $nodeId = null): void
    {
        $this->errors[] = [
            'message' => $message,
            'node_id' => $nodeId,
            'timestamp' => current_time('mysql'),
        ];
    }

    /**
     * Get errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Add log entry
     */
    public function addLog(string $message, string $level = 'info', ?string $nodeId = null): void
    {
        $this->logs[] = [
            'message' => $message,
            'level' => $level,
            'node_id' => $nodeId,
            'timestamp' => current_time('mysql'),
        ];
    }

    /**
     * Get logs
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    /**
     * Mark execution as completed
     */
    public function complete(): void
    {
        $this->isCompleted = true;
    }

    /**
     * Check if execution is completed
     */
    public function isCompleted(): bool
    {
        return $this->isCompleted;
    }

    /**
     * Get execution result
     */
    public function getResult(): array
    {
        return [
            'completed' => $this->isCompleted,
            'repository_count' => count($this->repository),
            'extracted_count' => count($this->extractedData),
            'processed_count' => count($this->processedResults),
            'errors' => $this->errors,
            'logs' => $this->logs,
        ];
    }
}

