<?php

namespace CrawlFlow\Flow;

/**
 * Node Executor Interface
 * All node executors must implement this interface
 */
interface NodeExecutorInterface
{
    /**
     * Execute the node
     *
     * @param array $node Node configuration
     * @param ExecutionContext $context Execution context
     * @return NodeResult Execution result
     */
    public function execute(array $node, ExecutionContext $context): NodeResult;

    /**
     * Check if this executor supports the given node type
     *
     * @param string $nodeType Node type
     * @return bool
     */
    public function supports(string $nodeType): bool;
}

