<?php

namespace CrawlFlow\Flow;

/**
 * Flow Executor
 * Executes a flow configuration
 * Integrates with Rake Framework components
 */
class FlowExecutor
{
    /**
     * @var NodeRegistry
     */
    private NodeRegistry $nodeRegistry;

    /**
     * @var RakeAdapter
     */
    private RakeAdapter $rakeAdapter;

    /**
     * Constructor
     */
    public function __construct(NodeRegistry $nodeRegistry, RakeAdapter $rakeAdapter)
    {
        $this->nodeRegistry = $nodeRegistry;
        $this->rakeAdapter = $rakeAdapter;
    }

    /**
     * Execute flow
     */
    public function execute(FlowConfig $flow): ExecutionContext
    {
        // Validate flow
        $errors = $flow->validate();
        if (!empty($errors)) {
            $context = new ExecutionContext($flow);
            foreach ($errors as $error) {
                $context->addError($error);
            }
            return $context;
        }

        // Initialize execution context
        $context = new ExecutionContext($flow);

        try {
            // 1. Execute Start nodes
            $this->executeStartNodes($context);

            // 2. Execute Reception nodes
            $this->executeReceptionNodes($context);

            // 3. Execute Navigation nodes (Click, Loop)
            $this->executeNavigationNodes($context);

            // 4. Execute Worker nodes
            $this->executeWorkerNodes($context);

            // 5. Execute Extractor nodes
            $this->executeExtractorNodes($context);

            // 6. Execute Processor nodes
            $this->executeProcessorNodes($context);

            // 7. Complete execution
            $context->complete();
            $context->addLog('Flow execution completed successfully', 'info');

        } catch (\Exception $e) {
            $context->addError('Flow execution failed: ' . $e->getMessage());
            $context->addLog('Flow execution failed: ' . $e->getMessage(), 'error');
        }

        return $context;
    }

    /**
     * Execute Start nodes
     */
    private function executeStartNodes(ExecutionContext $context): void
    {
        $flow = $context->getFlow();
        $startNodes = $flow->getNodesByType('start');

        $context->addLog('Executing ' . count($startNodes) . ' start node(s)', 'info');

        foreach ($startNodes as $node) {
            $this->executeNode($node, $context);
        }
    }

    /**
     * Execute Reception nodes
     */
    private function executeReceptionNodes(ExecutionContext $context): void
    {
        $flow = $context->getFlow();
        $receptionNodes = $flow->getNodesByType('reception');

        if (empty($receptionNodes)) {
            return;
        }

        $context->addLog('Executing ' . count($receptionNodes) . ' reception node(s)', 'info');

        foreach ($receptionNodes as $node) {
            $this->executeNode($node, $context);
        }
    }

    /**
     * Execute Navigation nodes (Click, Loop)
     */
    private function executeNavigationNodes(ExecutionContext $context): void
    {
        $flow = $context->getFlow();
        $clickNodes = $flow->getNodesByType('click');
        $loopNodes = $flow->getNodesByType('loop');

        $totalNodes = count($clickNodes) + count($loopNodes);
        if ($totalNodes > 0) {
            $context->addLog('Executing ' . $totalNodes . ' navigation node(s)', 'info');
        }

        foreach ($clickNodes as $node) {
            $this->executeNode($node, $context);
        }

        foreach ($loopNodes as $node) {
            $this->executeNode($node, $context);
        }
    }

    /**
     * Execute Worker nodes
     */
    private function executeWorkerNodes(ExecutionContext $context): void
    {
        $flow = $context->getFlow();
        $workerNodes = $flow->getNodesByType('worker');

        if (empty($workerNodes)) {
            return;
        }

        $context->addLog('Executing ' . count($workerNodes) . ' worker node(s)', 'info');

        foreach ($workerNodes as $node) {
            $this->executeNode($node, $context);
        }
    }

    /**
     * Execute Extractor nodes
     */
    private function executeExtractorNodes(ExecutionContext $context): void
    {
        $flow = $context->getFlow();
        $extractorTypes = [
            'html-data-extractor',
            'csv-extractor',
            'json-extractor',
            'xml-extractor',
            'mysql-extractor',
        ];

        $extractorNodes = [];
        foreach ($extractorTypes as $type) {
            $extractorNodes = array_merge($extractorNodes, $flow->getNodesByType($type));
        }

        if (empty($extractorNodes)) {
            return;
        }

        $context->addLog('Executing ' . count($extractorNodes) . ' extractor node(s)', 'info');

        foreach ($extractorNodes as $node) {
            $this->executeNode($node, $context);
        }
    }

    /**
     * Execute Processor nodes
     */
    private function executeProcessorNodes(ExecutionContext $context): void
    {
        $flow = $context->getFlow();
        $processorNodes = $flow->getNodesByType('processor');

        if (empty($processorNodes)) {
            return;
        }

        $context->addLog('Executing ' . count($processorNodes) . ' processor node(s)', 'info');

        foreach ($processorNodes as $node) {
            $this->executeNode($node, $context);
        }
    }

    /**
     * Execute a single node
     */
    private function executeNode(array $node, ExecutionContext $context): void
    {
        $nodeType = $node['type'] ?? 'unknown';
        $nodeId = $node['id'] ?? 'unknown';

        $context->addLog("Executing node: {$nodeId} (type: {$nodeType})", 'info');

        $executor = $this->nodeRegistry->getExecutor($nodeType);

        if (!$executor) {
            $context->addError("No executor found for node type: {$nodeType}", $nodeId);
            return;
        }

        try {
            $result = $executor->execute($node, $context);

            if ($result->isSuccess()) {
                $context->addLog("Node {$nodeId} executed successfully", 'info');
                
                // Process output based on node type
                $this->processNodeOutput($node, $result, $context);
            } else {
                $context->addError("Node {$nodeId} failed: " . $result->getError(), $nodeId);
            }

            // Store node state
            $context->setNodeState($nodeId, [
                'success' => $result->isSuccess(),
                'output_count' => count($result->getOutput()),
                'metadata' => $result->getMetadata(),
            ]);

        } catch (\Exception $e) {
            $context->addError("Node {$nodeId} threw exception: " . $e->getMessage(), $nodeId);
        }
    }

    /**
     * Process node output
     */
    private function processNodeOutput(array $node, NodeResult $result, ExecutionContext $context): void
    {
        $nodeType = $node['type'] ?? '';
        $nodeId = $node['id'] ?? '';
        $output = $result->getOutput();

        switch ($nodeType) {
            case 'start':
            case 'click':
            case 'loop':
                // Add resources to repository
                foreach ($output as $resource) {
                    $resource['source_node_id'] = $nodeId;
                    $context->addResource($resource);
                }
                break;

            case 'html-data-extractor':
            case 'csv-extractor':
            case 'json-extractor':
            case 'xml-extractor':
            case 'mysql-extractor':
                // Add extracted data
                foreach ($output as $dataItem) {
                    $dataItem['extractor_id'] = $nodeId;
                    $context->addExtractedData($dataItem);
                }
                break;

            case 'processor':
                // Add processed results
                foreach ($output as $result) {
                    $result['processor_id'] = $nodeId;
                    $context->addProcessedResult($result);
                }
                break;
        }
    }
}

