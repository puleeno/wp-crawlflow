<?php

namespace CrawlFlow\Cron;

use CrawlFlow\Reception\Reception;

/**
 * Worker Cache Service
 * Cache workers by project ID to avoid repeated query and setup
 */
class WorkerCacheService
{
    /**
     * @var array Cache of Reception instances by project ID
     */
    private static array $receptionCache = [];

    /**
     * @var array Cache of workers config by project ID
     */
    private static array $workersConfigCache = [];

    /**
     * Get Reception instance for project (cached)
     * 
     * @param int $projectId Project ID
     * @param array $flowConfig Flow configuration
     * @return Reception Reception instance
     */
    public function getReception(int $projectId, array $flowConfig): Reception
    {
        // Check cache first
        if (isset(self::$receptionCache[$projectId])) {
            error_log("CrawlFlow WorkerCache: Using cached Reception for project {$projectId}");
            return self::$receptionCache[$projectId];
        }

        // Convert flow config to workers config
        $workersConfig = $this->getWorkersConfig($projectId, $flowConfig);

        // Create Reception instance
        $reception = new Reception($workersConfig);
        
        // Cache it
        self::$receptionCache[$projectId] = $reception;
        error_log("CrawlFlow WorkerCache: Cached Reception for project {$projectId}");

        return $reception;
    }

    /**
     * Get workers config for project (cached)
     * 
     * @param int $projectId Project ID
     * @param array $flowConfig Flow configuration
     * @return array Workers config
     */
    public function getWorkersConfig(int $projectId, array $flowConfig): array
    {
        // Check cache first
        if (isset(self::$workersConfigCache[$projectId])) {
            error_log("CrawlFlow WorkerCache: Using cached workers config for project {$projectId}");
            return self::$workersConfigCache[$projectId];
        }

        // Convert flow config (nodes/edges) to workers config
        $workersConfig = $this->convertFlowConfigToWorkersConfig($flowConfig);

        // Cache it
        self::$workersConfigCache[$projectId] = $workersConfig;
        error_log("CrawlFlow WorkerCache: Cached workers config for project {$projectId}");

        return $workersConfig;
    }

    /**
     * Clear cache for a project
     * 
     * @param int $projectId Project ID
     */
    public function clearCache(int $projectId): void
    {
        unset(self::$receptionCache[$projectId]);
        unset(self::$workersConfigCache[$projectId]);
        error_log("CrawlFlow WorkerCache: Cleared cache for project {$projectId}");
    }

    /**
     * Clear all cache
     */
    public function clearAllCache(): void
    {
        self::$receptionCache = [];
        self::$workersConfigCache = [];
        error_log("CrawlFlow WorkerCache: Cleared all cache");
    }

    /**
     * Convert flow config (nodes/edges) to workers config
     * This allows Reception to work with flow-based config
     */
    private function convertFlowConfigToWorkersConfig(array $flowConfig): array
    {
        // If config already has workers, return as is
        if (isset($flowConfig['workers']) && is_array($flowConfig['workers'])) {
            return $flowConfig;
        }

        $nodes = $flowConfig['nodes'] ?? [];
        $edges = $flowConfig['edges'] ?? [];
        $workers = [];

        // Find all worker nodes
        foreach ($nodes as $node) {
            if ($node['type'] !== 'worker') {
                continue;
            }

            $nodeId = $node['id'] ?? '';
            $nodeData = $node['data'] ?? [];

            // Build worker config from node
            $workerConfig = [
                'name' => $nodeId,
                'priority' => (int)($nodeData['priority'] ?? 0),
                'detectionRules' => $nodeData['detectionRules'] ?? [],
                'detectionLogic' => $nodeData['detectionLogic'] ?? 'and',
                'parser' => $nodeData['parser'] ?? [],
                'processors' => [],
            ];

            // Find processors connected to this worker via edges
            $processorChain = $this->buildProcessorChain($nodeId, $nodes, $edges);
            $workerConfig['processors'] = $processorChain;

            $workers[] = $workerConfig;
        }

        // Return config in format expected by Reception
        return [
            'workers' => $workers,
            'finishActions' => $flowConfig['finishActions'] ?? [],
        ];
    }

    /**
     * Build processor chain for a worker by following edges
     */
    private function buildProcessorChain(string $workerNodeId, array $nodes, array $edges): array
    {
        $processors = [];
        $currentNodeId = $workerNodeId;
        $visited = [];

        // Follow edges from worker to processors
        while (true) {
            if (isset($visited[$currentNodeId])) {
                break; // Prevent cycles
            }
            $visited[$currentNodeId] = true;

            // Find edges starting from current node
            $outgoingEdges = array_filter($edges, function($edge) use ($currentNodeId) {
                return ($edge['source'] ?? '') === $currentNodeId;
            });

            if (empty($outgoingEdges)) {
                break; // No more processors
            }

            // Get first processor node (assuming single chain)
            $nextEdge = reset($outgoingEdges);
            $nextNodeId = $nextEdge['target'] ?? '';

            // Find the node
            $nextNode = null;
            foreach ($nodes as $node) {
                if (($node['id'] ?? '') === $nextNodeId) {
                    $nextNode = $node;
                    break;
                }
            }

            if (!$nextNode || $nextNode['type'] !== 'processor') {
                break; // Not a processor, end chain
            }

            // Add processor to chain
            $processorData = $nextNode['data'] ?? [];
            $processors[] = [
                'type' => $processorData['processorType'] ?? 'unknown',
                'name' => $nextNodeId,
                'settings' => $processorData['settings'] ?? [],
            ];

            $currentNodeId = $nextNodeId;
        }

        return $processors;
    }
}

