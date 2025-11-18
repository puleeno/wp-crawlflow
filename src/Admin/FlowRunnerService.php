<?php

namespace CrawlFlow\Admin;

use CrawlFlow\Flow\FlowConfig;
use CrawlFlow\Flow\FlowService;
use Rake\Rake;

/**
 * Flow Runner Service
 * Service to execute flows from WordPress admin
 */
class FlowRunnerService
{
    /**
     * @var FlowService
     */
    private FlowService $flowService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $rake = Rake::getInstance();
        $this->flowService = new FlowService($rake);
    }

    /**
     * Run a flow by project ID
     */
    public function runFlow(int $projectId): array
    {
        $projectService = new ProjectService();
        $flowConfig = $projectService->getFlowConfig($projectId);

        if (!$flowConfig) {
            return [
                'success' => false,
                'error' => 'Flow configuration not found',
            ];
        }

        try {
            $context = $this->flowService->executeFlow($flowConfig);
            $result = $context->getResult();

            return [
                'success' => $context->isCompleted(),
                'result' => $result,
                'errors' => $context->getErrors(),
                'logs' => $context->getLogs(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Run a flow from configuration array
     */
    public function runFlowConfig(array $flowConfig): array
    {
        try {
            $context = $this->flowService->executeFlow($flowConfig);
            $result = $context->getResult();

            return [
                'success' => $context->isCompleted(),
                'result' => $result,
                'errors' => $context->getErrors(),
                'logs' => $context->getLogs(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get flow service
     */
    public function getFlowService(): FlowService
    {
        return $this->flowService;
    }
}

