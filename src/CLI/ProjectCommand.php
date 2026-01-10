<?php

namespace CrawlFlow\CLI;

use CrawlFlow\Admin\ProjectService;
use Rake\Rake;
use WP_CLI;
use WP_CLI_Command;

/**
 * WP CLI Command for CrawlFlow Project Management
 */
class ProjectCommand extends WP_CLI_Command
{
    /**
     * @var ProjectService
     */
    private ProjectService $projectService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $rake = Rake::getInstance();
        $this->projectService = $rake->make('CrawlFlow\Admin\ProjectService');
    }

    /**
     * List all CrawlFlow projects
     *
     * ## EXAMPLES
     *
     *     wp crawlflow project list
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function list($args, $assoc_args)
    {
        $projects = $this->projectService->getAllProjects();

        if (empty($projects)) {
            WP_CLI::warning("No projects found.");
            return;
        }

        WP_CLI\Utils\format_items('table', $projects, ['id', 'name', 'status', 'created_at']);
    }

    /**
     * Test a CrawlFlow project's operation
     *
     * ## OPTIONS
     *
     * <project_id>
     * : The ID of the project to test.
     *
     * [--fetch]
     * : Whether to attempt fetching from the data source.
     *
     * [--phase=<phase>]
     * : Specific phase to test (1, 2, or 3). If not provided, runs basic checks.
     *
     * [--run-all]
     * : Execute all 3 phases sequentially.
     *
     * ## EXAMPLES
     *
     *     wp crawlflow project test 1
     *     wp crawlflow project test 1 --fetch
     *     wp crawlflow project test 1 --phase=1
     *     wp crawlflow project test 1 --run-all
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function test($args, $assoc_args)
    {
        $projectId = (int) $args[0];
        $fetch = isset($assoc_args['fetch']);
        $phase = isset($assoc_args['phase']) ? (int) $assoc_args['phase'] : null;
        $runAll = isset($assoc_args['run-all']);

        WP_CLI::log("Testing project ID: {$projectId}...");

        $project = $this->projectService->getProject($projectId);
        if (!$project) {
            WP_CLI::error("Project not found.");
        }

        WP_CLI::log("Project Name: " . $project['name']);
        WP_CLI::log("Status: " . $project['status']);

        $flowConfig = $this->projectService->getFlowConfig($projectId);
        if (!$flowConfig) {
            WP_CLI::error("Project has no flow configuration.");
        }

        if ($runAll) {
            $this->test_all_phases($projectId);
            return;
        }

        if ($phase) {
            $this->test_specific_phase($projectId, $phase);
            return;
        }

        // Basic checks if no specific phase or run-all is requested
        $this->run_basic_checks($projectId, $flowConfig, $fetch);

        WP_CLI::success("Project basic test completed.");
    }

    /**
     * Run basic checks for a project
     */
    private function run_basic_checks(int $projectId, array $flowConfig, bool $fetch)
    {
        $nodes = $flowConfig['nodes'] ?? [];

        // Find data source node
        $dataSourceNode = null;
        $workerNodes = [];

        foreach ($nodes as $node) {
            if ($node['type'] === 'dataSource' || $node['type'] === 'start') {
                $dataSourceNode = $node;
            }
            if ($node['type'] === 'worker') {
                $workerNodes[] = $node;
            }
        }

        if ($dataSourceNode) {
            $nodeData = $dataSourceNode['data'] ?? [];
            $sourceUrl = $nodeData['sourceValue'] ?? $nodeData['url'] ?? 'N/A';
            WP_CLI::log("Data Source: " . ($nodeData['sourceType'] ?? 'url') . " - " . $sourceUrl);

            if ($fetch && $sourceUrl !== 'N/A' && !empty($sourceUrl)) {
                WP_CLI::log("Attempting to reach data source...");
                $response = wp_remote_get($sourceUrl, ['timeout' => 15]);
                if (is_wp_error($response)) {
                    WP_CLI::warning("Failed to reach URL: " . $response->get_error_message());
                } else {
                    $code = wp_remote_retrieve_response_code($response);
                    WP_CLI::log("URL reachable. Status code: {$code}");
                }
            }
        } else {
            WP_CLI::warning("No data source node found in flow.");
        }

        WP_CLI::log("Worker Nodes: " . count($workerNodes));
        foreach ($workerNodes as $worker) {
            $data = $worker['data'] ?? [];
            WP_CLI::log("- Worker: " . ($data['label'] ?? $worker['id']) . " (Parser: " . ($data['parser'] ?? 'none') . ")");
        }
    }

    /**
     * Test a specific phase
     */
    private function test_specific_phase(int $projectId, int $phase)
    {
        WP_CLI::log("Executing Phase {$phase} test...");

        try {
            $rake = Rake::getInstance();
            $cronScheduler = $rake->make('CrawlFlow\Cron\CronScheduler');

            switch ($phase) {
                case 1:
                    $cronScheduler->executePhase1($projectId);
                    break;
                case 2:
                    $cronScheduler->executePhase2($projectId);
                    break;
                case 3:
                    $cronScheduler->executePhase3($projectId);
                    break;
                default:
                    WP_CLI::error("Invalid phase: {$phase}");
            }

            WP_CLI::success("Phase {$phase} execution triggered. Check logs for results.");
        } catch (\Exception $e) {
            WP_CLI::error("Phase {$phase} test failed: " . $e->getMessage());
        }
    }

    /**
     * Execute all 3 phases sequentially
     */
    private function test_all_phases(int $projectId)
    {
        WP_CLI::log("Executing all 3 phases sequentially...");

        try {
            $rake = Rake::getInstance();
            $cronScheduler = $rake->make('CrawlFlow\Cron\CronScheduler');

            WP_CLI::log("Step 1/3: Executing Phase 1 (Crawl)...");
            $cronScheduler->executePhase1($projectId);
            WP_CLI::log("Phase 1 completed.");

            WP_CLI::log("Step 2/3: Executing Phase 2 (Process)...");
            $cronScheduler->executePhase2($projectId);
            WP_CLI::log("Phase 2 completed.");

            WP_CLI::log("Step 3/3: Executing Phase 3 (Resources)...");
            $cronScheduler->executePhase3($projectId);
            WP_CLI::log("Phase 3 completed.");

            WP_CLI::success("All 3 phases executed successfully.");
        } catch (\Exception $e) {
            WP_CLI::error("Execution failed: " . $e->getMessage());
        }
    }
}
