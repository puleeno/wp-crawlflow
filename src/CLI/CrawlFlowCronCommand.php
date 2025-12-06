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
            $this->cronScheduler->executeProject($projectId);
            WP_CLI::success("Project {$projectId} executed successfully.");
        } catch (\Exception $e) {
            WP_CLI::error("Failed to execute project {$projectId}: " . $e->getMessage());
        }
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

