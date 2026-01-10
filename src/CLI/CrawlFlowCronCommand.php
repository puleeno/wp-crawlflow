<?php

namespace CrawlFlow\CLI;

use CrawlFlow\Admin\ProjectService;
use CrawlFlow\Cron\CronScheduler;
use Rake\Rake;
use WP_CLI;
use WP_CLI_Command;

/**
 * WP CLI Command for CrawlFlow Cron Management
 */
class CrawlFlowCronCommand extends WP_CLI_Command
{
    /**
     * @var ProjectService
     */
    private ProjectService $projectService;

    /**
     * @var CronScheduler
     */
    private CronScheduler $cronScheduler;

    /**
     * Constructor
     */
    public function __construct()
    {
        $rake = Rake::getInstance();
        $this->projectService = $rake->make('CrawlFlow\Admin\ProjectService');
        $this->cronScheduler = $rake->make('CrawlFlow\Cron\CronScheduler');
    }

    /**
     * List all CrawlFlow projects with their cron schedule status
     *
     * ## EXAMPLES
     *
     *     # List all projects
     *     $ wp crawlflow cron list
     *
     *     # List only scheduled projects
     *     $ wp crawlflow cron list --scheduled
     *
     *     # List only active projects
     *     $ wp crawlflow cron list --active
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function list($args, $assoc_args)
    {
        $onlyScheduled = isset($assoc_args['scheduled']);
        $onlyActive = isset($assoc_args['active']);

        // Get all projects
        $projects = $this->projectService->getAllProjects();
        
        // Get scheduled projects
        $scheduledProjects = $this->cronScheduler->getScheduledProjects();
        $scheduledMap = [];
        foreach ($scheduledProjects as $scheduled) {
            $scheduledMap[$scheduled['project_id']] = $scheduled;
        }

        // Get hook names for each project
        $items = [];
        foreach ($projects as $project) {
            $projectId = (int)$project['id'];
            
            // Filter by active if requested
            if ($onlyActive && $project['status'] !== 'active') {
                continue;
            }

            // Get hook name
            $hook = $this->getProjectHook($projectId);
            
            // Check if scheduled
            $isScheduled = isset($scheduledMap[$projectId]);
            
            // Filter by scheduled if requested
            if ($onlyScheduled && !$isScheduled) {
                continue;
            }

            // Get flow config to check enabled status
            $flowConfig = $this->projectService->getFlowConfig($projectId);
            $projectSettings = $flowConfig['projectSettings'] ?? [];
            $enabled = $projectSettings['enabled'] ?? false;

            $item = [
                'project_id' => $projectId,
                'name' => $project['name'] ?? "Project {$projectId}",
                'status' => $project['status'] ?? 'unknown',
                'enabled' => $enabled ? 'yes' : 'no',
                'hook' => $hook,
                'scheduled' => $isScheduled ? 'yes' : 'no',
            ];

            if ($isScheduled) {
                $scheduled = $scheduledMap[$projectId];
                $item['schedule'] = $scheduled['schedule'] ?? 'once';
                $item['next_run'] = $scheduled['next_run'] ?? 'N/A';
            } else {
                $item['schedule'] = 'N/A';
                $item['next_run'] = 'N/A';
            }

            $items[] = $item;
        }

        if (empty($items)) {
            WP_CLI::warning('No projects found matching criteria.');
            return;
        }

        WP_CLI\Utils\format_items('table', $items, [
            'project_id',
            'name',
            'status',
            'enabled',
            'hook',
            'scheduled',
            'schedule',
            'next_run',
        ]);
    }

    /**
     * Schedule a project
     *
     * ## EXAMPLES
     *
     *     # Schedule project with ID 2
     *     $ wp crawlflow cron schedule 2
     *
     * @param array $args Positional arguments (project_id)
     * @param array $assoc_args Associative arguments
     */
    public function schedule($args, $assoc_args)
    {
        if (empty($args[0])) {
            WP_CLI::error('Project ID is required.');
            return;
        }

        $projectId = (int)$args[0];

        try {
            $this->cronScheduler->scheduleProject($projectId);
            $hook = $this->getProjectHook($projectId);
            WP_CLI::success("Project {$projectId} scheduled successfully. Hook: {$hook}");
        } catch (\Exception $e) {
            WP_CLI::error("Failed to schedule project {$projectId}: " . $e->getMessage());
        }
    }

    /**
     * Unschedule a project
     *
     * ## EXAMPLES
     *
     *     # Unschedule project with ID 2
     *     $ wp crawlflow cron unschedule 2
     *
     * @param array $args Positional arguments (project_id)
     * @param array $assoc_args Associative arguments
     */
    public function unschedule($args, $assoc_args)
    {
        if (empty($args[0])) {
            WP_CLI::error('Project ID is required.');
            return;
        }

        $projectId = (int)$args[0];

        try {
            $this->cronScheduler->unscheduleProject($projectId);
            WP_CLI::success("Project {$projectId} unscheduled successfully.");
        } catch (\Exception $e) {
            WP_CLI::error("Failed to unschedule project {$projectId}: " . $e->getMessage());
        }
    }

    /**
     * Run a project manually
     *
     * ## EXAMPLES
     *
     *     # Run project with ID 2
     *     $ wp crawlflow cron run 2
     *
     * @param array $args Positional arguments (project_id)
     * @param array $assoc_args Associative arguments
     */
    public function run($args, $assoc_args)
    {
        if (empty($args[0])) {
            WP_CLI::error('Project ID is required.');
            return;
        }

        $projectId = (int)$args[0];

        WP_CLI::log("Executing project {$projectId}...");
        
        try {
            $this->cronScheduler->executePhase1($projectId);
            $this->cronScheduler->executePhase2($projectId);
            $this->cronScheduler->executePhase3($projectId);
            WP_CLI::success("Project {$projectId} executed successfully.");
        } catch (\Exception $e) {
            WP_CLI::error("Failed to execute project {$projectId}: " . $e->getMessage());
        }
    }

    /**
     * Test Data Update Checker specifically
     *
     * ## EXAMPLES
     *
     *     # Test Data Update Checker for project with ID 2
     *     $ wp crawlflow cron test-data-update-checker 2
     *
     *     # Test Data Update Checker with debug info
     *     $ wp crawlflow cron test-data-update-checker 2 --debug
     *
     * @param array $args Positional arguments (project_id)
     * @param array $assoc_args Associative arguments
     */
    public function test_data_update_checker($args, $assoc_args)
    {
        if (empty($args[0])) {
            WP_CLI::error('Project ID is required.');
            return;
        }

        $projectId = (int)$args[0];
        $debug = isset($assoc_args['debug']);

        WP_CLI::log("Testing Data Update Checker for project {$projectId}...");

        try {
            // Get project configuration
            $flowConfig = $this->projectService->getFlowConfig($projectId);
            $projectSettings = $flowConfig['projectSettings'] ?? [];
            
            if ($debug) {
                WP_CLI::log("Project Settings:");
                WP_CLI::log(json_encode($projectSettings, JSON_PRETTY_PRINT));
            }

            // Check if Data Update Checker is enabled
            $phase1Actions = $projectSettings['phase1Actions'] ?? [];
            if (!in_array('data_update_checker', $phase1Actions)) {
                WP_CLI::warning("Data Update Checker is not enabled in Phase 1 Actions for project {$projectId}");
                WP_CLI::log("Current Phase 1 Actions: " . implode(', ', $phase1Actions));
                return;
            }

            WP_CLI::success("Data Update Checker is enabled for project {$projectId}");

            // Check schedule
            $dataUpdateCheckerSchedule = $projectSettings['dataUpdateCheckerSchedule'] ?? null;
            if ($dataUpdateCheckerSchedule) {
                WP_CLI::log("Custom schedule: {$dataUpdateCheckerSchedule}");
            } else {
                WP_CLI::log("Using project schedule (no custom schedule set)");
            }

            // Execute Data Update Checker
            WP_CLI::log("Executing Data Update Checker...");
            $this->cronScheduler->executeDataUpdateChecker($projectId);
            
            WP_CLI::success("Data Update Checker test completed for project {$projectId}");

        } catch (\Exception $e) {
            WP_CLI::error("Failed to test Data Update Checker for project {$projectId}: " . $e->getMessage());
            
            if ($debug) {
                WP_CLI::log("Stack trace:");
                WP_CLI::log($e->getTraceAsString());
            }
        }
    }

    /**
     * List Data Update Checker schedules for projects
     *
     * ## EXAMPLES
     *
     *     # List all Data Update Checker schedules
     *     $ wp crawlflow cron list-data-update-checker-schedules
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function list_data_update_checker_schedules($args, $assoc_args)
    {
        // Get all projects
        $projects = $this->projectService->getAllProjects();
        
        $items = [];
        foreach ($projects as $project) {
            $projectId = (int)$project['id'];
            
            // Get flow config
            $flowConfig = $this->projectService->getFlowConfig($projectId);
            $projectSettings = $flowConfig['projectSettings'] ?? [];
            
            // Check if Data Update Checker is enabled
            $phase1Actions = $projectSettings['phase1Actions'] ?? [];
            $isDataUpdateCheckerEnabled = in_array('data_update_checker', $phase1Actions);
            
            // Get schedule
            $schedule = $projectSettings['dataUpdateCheckerSchedule'] ?? 'project_default';
            
            $item = [
                'project_id' => $projectId,
                'name' => $project['name'] ?? "Project {$projectId}",
                'enabled' => $isDataUpdateCheckerEnabled ? 'yes' : 'no',
                'schedule' => $schedule,
            ];

            $items[] = $item;
        }

        if (empty($items)) {
            WP_CLI::warning('No projects found.');
            return;
        }

        WP_CLI\Utils\format_items('table', $items, [
            'project_id',
            'name',
            'enabled',
            'schedule',
        ]);
    }

    /**
     * Get hook name for a project
     *
     * @param int $projectId
     * @return string
     */
    private function getProjectHook(int $projectId): string
    {
        $hash = substr(md5($projectId), 0, 6);
        return CronScheduler::CRON_HOOK_BASE . '_' . $hash;
    }
}

