<?php

namespace CrawlFlow\Cron;

use CrawlFlow\Admin\ProjectService;
use CrawlFlow\Flow\FlowService;
use Rake\Rake;

/**
 * Cron Scheduler
 * Manages scheduling and execution of CrawlFlow projects via WordPress cron
 */
class CronScheduler
{
    /**
     * @var ProjectService
     */
    private ProjectService $projectService;

    /**
     * @var FlowService
     */
    private FlowService $flowService;

    /**
     * Hook name for cron event
     */
    const CRON_HOOK = 'crawlflow_execute_project';

    /**
     * Constructor
     */
    public function __construct()
    {
        $rake = Rake::getInstance();
        $this->projectService = $rake->make('CrawlFlow\Admin\ProjectService');
        $this->flowService = $rake->make('CrawlFlow\Flow\FlowService');
    }

    /**
     * Register WordPress hooks
     */
    public function register(): void
    {
        // Register cron execution hook
        add_action(self::CRON_HOOK, [$this, 'executeProject'], 10, 1);
        
        // Schedule projects on project save/update
        add_action('crawlflow_project_saved', [$this, 'scheduleProject'], 10, 1);
        add_action('crawlflow_project_updated', [$this, 'scheduleProject'], 10, 1);
        
        // Unschedule on project delete/deactivate
        add_action('crawlflow_project_deleted', [$this, 'unscheduleProject'], 10, 1);
        add_action('crawlflow_project_deactivated', [$this, 'unscheduleProject'], 10, 1);
    }

    /**
     * Schedule a project based on its settings
     * 
     * @param int $projectId Project ID
     * @throws \RuntimeException If scheduling fails
     */
    public function scheduleProject(int $projectId): void
    {
        $project = $this->projectService->getProject($projectId);
        
        if (!$project) {
            throw new \RuntimeException("Project {$projectId} not found");
        }

        // Only schedule active projects
        if ($project['status'] !== 'active') {
            $this->unscheduleProject($projectId);
            return;
        }

        // Get project settings
        $flowConfig = $this->projectService->getFlowConfig($projectId);
        $projectSettings = $flowConfig['projectSettings'] ?? [];

        // Check if project is enabled
        if (!($projectSettings['enabled'] ?? false)) {
            $this->unscheduleProject($projectId);
            return;
        }

        // Get schedule interval from project settings
        $schedule = $this->getScheduleFromSettings($projectSettings);

        // Unschedule existing
        $this->unscheduleProject($projectId);

        // Schedule new event
        $timestamp = $this->getNextRunTimeFromSettings($projectSettings);
        
        $scheduled = wp_schedule_event(
            $timestamp,
            $schedule,
            self::CRON_HOOK,
            [$projectId]
        );

        if ($scheduled === false) {
            throw new \RuntimeException("Failed to schedule project {$projectId}");
        }

        error_log("CrawlFlow: Scheduled project {$projectId} with interval '{$schedule}' at " . date('Y-m-d H:i:s', $timestamp));
    }

    /**
     * Get schedule interval from project settings
     */
    private function getScheduleFromSettings(array $projectSettings): string
    {
        $scheduleType = $projectSettings['scheduleType'] ?? 'interval';
        
        if ($scheduleType === 'interval') {
            // Interval in minutes
            $intervalMinutes = (int)($projectSettings['scheduleInterval'] ?? 60);
            
            // Map to WordPress schedules
            if ($intervalMinutes <= 5) {
                return 'every_5_minutes';
            } elseif ($intervalMinutes <= 15) {
                return 'every_15_minutes';
            } elseif ($intervalMinutes <= 30) {
                return 'every_30_minutes';
            } elseif ($intervalMinutes <= 60) {
                return 'hourly';
            } elseif ($intervalMinutes <= 360) {
                return 'every_6_hours';
            } elseif ($intervalMinutes <= 720) {
                return 'twicedaily';
            } else {
                return 'daily';
            }
        }
        
        // Default to hourly
        return 'hourly';
    }

    /**
     * Get next run time from project settings
     */
    private function getNextRunTimeFromSettings(array $projectSettings): int
    {
        $scheduleType = $projectSettings['scheduleType'] ?? 'interval';
        
        if ($scheduleType === 'specific_time') {
            // Specific time (e.g., "14:30")
            $time = $projectSettings['scheduleTime'] ?? '00:00';
            list($hour, $minute) = explode(':', $time);
            
            $timestamp = strtotime("today {$hour}:{$minute}");
            
            // If time has passed today, schedule for tomorrow
            if ($timestamp < time()) {
                $timestamp = strtotime("tomorrow {$hour}:{$minute}");
            }
            
            return $timestamp;
        }
        
        // For interval, start immediately
        return time();
    }

    /**
     * Unschedule a project
     */
    public function unscheduleProject(int $projectId): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK, [$projectId]);
        
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK, [$projectId]);
            error_log("CrawlFlow: Unscheduled project {$projectId}");
        }
    }

    /**
     * Execute a project (called by WordPress cron)
     * 
     * @param int $projectId Project ID
     */
    public function executeProject(int $projectId): void
    {
        try {
            error_log("CrawlFlow: Starting cron execution for project {$projectId}");
            
            // Load project
            $project = $this->projectService->getProject($projectId);
            
            if (!$project) {
                error_log("CrawlFlow: Project {$projectId} not found");
                return;
            }

            // Check status
            if ($project['status'] !== 'active') {
                error_log("CrawlFlow: Project {$projectId} is not active");
                return;
            }

            // Get flow config
            $flowConfig = $this->projectService->getFlowConfig($projectId);
            
            if (!$flowConfig) {
                error_log("CrawlFlow: No flow config for project {$projectId}");
                return;
            }

            // Execute flow
            $startTime = microtime(true);
            $context = $this->flowService->executeFlow($flowConfig);
            $endTime = microtime(true);
            
            // Log results
            $result = $context->getResult();
            $executionTime = round($endTime - $startTime, 3);
            
            error_log(sprintf(
                "CrawlFlow: Project %d executed in %.3fs - Processed: %d, Errors: %d",
                $projectId,
                $executionTime,
                $result['processed_count'],
                count($result['errors'])
            ));

            // Store execution log in database
            $this->logExecution($projectId, $result, $executionTime);
            
        } catch (\Exception $e) {
            error_log("CrawlFlow: Cron execution failed for project {$projectId} - " . $e->getMessage());
        }
    }

    /**
     * Log execution to database
     */
    private function logExecution(int $projectId, array $result, float $executionTime): void
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'crawlflow_executions';
        
        $wpdb->insert($table, [
            'project_id' => $projectId,
            'status' => $result['completed'] ? 'completed' : 'failed',
            'repository_count' => $result['repository_count'],
            'extracted_count' => $result['extracted_count'],
            'processed_count' => $result['processed_count'],
            'error_count' => count($result['errors']),
            'execution_time' => $executionTime,
            'executed_at' => current_time('mysql'),
        ]);
    }

    /**
     * Get scheduled projects
     */
    public function getScheduledProjects(): array
    {
        $scheduled = [];
        $crons = _get_cron_array();
        
        if (!$crons) {
            return $scheduled;
        }

        foreach ($crons as $timestamp => $cron) {
            if (isset($cron[self::CRON_HOOK])) {
                foreach ($cron[self::CRON_HOOK] as $key => $event) {
                    $projectId = $event['args'][0] ?? null;
                    if ($projectId) {
                        $scheduled[] = [
                            'project_id' => $projectId,
                            'timestamp' => $timestamp,
                            'schedule' => $event['schedule'] ?? 'once',
                            'interval' => $event['interval'] ?? 0,
                            'next_run' => date('Y-m-d H:i:s', $timestamp),
                        ];
                    }
                }
            }
        }
        
        return $scheduled;
    }

    /**
     * Check if project is scheduled
     */
    public function isProjectScheduled(int $projectId): bool
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK, [$projectId]);
        return $timestamp !== false;
    }

    /**
     * Get next run time for project
     */
    public function getNextRunTime(int $projectId): ?int
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK, [$projectId]);
        return $timestamp ?: null;
    }
}

