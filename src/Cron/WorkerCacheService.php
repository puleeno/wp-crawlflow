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

            // Get parser rules from worker node
            $parserRules = $nodeData['parser']['rules'] ?? [];
            
            // Find extractor nodes connected to this worker (extractor -> worker)
            $extractorRules = $this->findExtractorRules($nodeId, $nodes, $edges);
            
            // Merge extractor rules with worker parser rules
            // Extractor rules take precedence (they are more specific)
            $mergedRules = array_merge($parserRules, $extractorRules);
            
            // Remove duplicates by name (keep extractor rules if duplicate)
            $uniqueRules = [];
            $seenNames = [];
            foreach (array_reverse($mergedRules) as $rule) {
                $ruleName = $rule['name'] ?? '';
                if (!empty($ruleName) && !isset($seenNames[$ruleName])) {
                    $uniqueRules[] = $rule;
                    $seenNames[$ruleName] = true;
                } elseif (empty($ruleName)) {
                    // Keep rules without name (they might be unique)
                    $uniqueRules[] = $rule;
                }
            }
            $mergedRules = array_reverse($uniqueRules);

            // Build worker config from node
            $workerConfig = [
                'name' => $nodeId,
                'priority' => (int)($nodeData['priority'] ?? 0),
                'detectionRules' => $nodeData['detectionRules'] ?? [],
                'detectionLogic' => $nodeData['detectionLogic'] ?? 'and',
                'parser' => [
                    'rules' => $mergedRules,
                ],
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

    /**
     * Find extractor rules from extractor nodes connected to worker
     * 
     * @param string $workerNodeId Worker node ID
     * @param array $nodes All nodes
     * @param array $edges All edges
     * @return array Extractor rules
     */
    private function findExtractorRules(string $workerNodeId, array $nodes, array $edges): array
    {
        $rules = [];
        
        // Find edges pointing to this worker (extractor -> worker)
        $incomingEdges = array_filter($edges, function($edge) use ($workerNodeId) {
            return ($edge['target'] ?? '') === $workerNodeId;
        });
        
        foreach ($incomingEdges as $edge) {
            $sourceNodeId = $edge['source'] ?? '';
            
            // Find the source node
            foreach ($nodes as $node) {
                if (($node['id'] ?? '') !== $sourceNodeId) {
                    continue;
                }
                
                // Check if it's an extractor node
                $nodeType = $node['type'] ?? '';
                if (!in_array($nodeType, ['html-data-extractor', 'html-extractor', 'csv-extractor', 'json-extractor', 'xml-extractor', 'mysql-extractor'])) {
                    continue;
                }
                
                $nodeData = $node['data'] ?? [];
                
                // Get rules from extractor node
                // Check customRules first (user-defined), then presets
                if (!empty($nodeData['customRules'])) {
                    $rules = array_merge($rules, $nodeData['customRules']);
                } elseif (!empty($nodeData['presets'])) {
                    // If using presets, we need to expand them
                    // For now, just log that presets are used
                    error_log("CrawlFlow WorkerCache: Extractor node {$sourceNodeId} uses presets, but preset expansion not implemented yet");
                }
                
                break;
            }
        }
        
        return $rules;
    }
}

