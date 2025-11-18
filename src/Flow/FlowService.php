<?php

namespace CrawlFlow\Flow;

use Rake\Rake;

/**
 * Flow Service
 * Main service for managing and executing flows
 * Integrates Flow-Based Architecture with Rake Framework
 */
class FlowService
{
    /**
     * @var Rake
     */
    private Rake $rake;

    /**
     * @var RakeAdapter
     */
    private RakeAdapter $rakeAdapter;

    /**
     * @var NodeRegistry
     */
    private NodeRegistry $nodeRegistry;

    /**
     * @var FlowExecutor
     */
    private FlowExecutor $flowExecutor;

    /**
     * Constructor
     */
    public function __construct(Rake $rake)
    {
        $this->rake = $rake;
        $this->rakeAdapter = new RakeAdapter($rake);
        $this->nodeRegistry = new NodeRegistry();
        $this->flowExecutor = new FlowExecutor($this->nodeRegistry, $this->rakeAdapter);
        
        $this->registerNodeExecutors();
    }

    /**
     * Register all node executors
     */
    private function registerNodeExecutors(): void
    {
        // Register Start Node Executor
        $this->nodeRegistry->register(new Executors\StartNodeExecutor($this->rakeAdapter));

        // TODO: Register other node executors
        // $this->nodeRegistry->register(new Executors\ClickNodeExecutor($this->rakeAdapter));
        // $this->nodeRegistry->register(new Executors\LoopNodeExecutor($this->rakeAdapter));
        // $this->nodeRegistry->register(new Executors\ReceptionNodeExecutor($this->rakeAdapter));
        // $this->nodeRegistry->register(new Executors\WorkerNodeExecutor($this->rakeAdapter));
        // $this->nodeRegistry->register(new Executors\ExtractorNodeExecutor($this->rakeAdapter));
        // $this->nodeRegistry->register(new Executors\ProcessorNodeExecutor($this->rakeAdapter));
    }

    /**
     * Execute a flow from configuration array
     */
    public function executeFlow(array $flowConfig): ExecutionContext
    {
        $flow = FlowConfig::fromArray($flowConfig);
        return $this->flowExecutor->execute($flow);
    }

    /**
     * Execute a flow from FlowConfig object
     */
    public function executeFlowConfig(FlowConfig $flow): ExecutionContext
    {
        return $this->flowExecutor->execute($flow);
    }

    /**
     * Get Rake adapter
     */
    public function getRakeAdapter(): RakeAdapter
    {
        return $this->rakeAdapter;
    }

    /**
     * Get node registry
     */
    public function getNodeRegistry(): NodeRegistry
    {
        return $this->nodeRegistry;
    }
}

