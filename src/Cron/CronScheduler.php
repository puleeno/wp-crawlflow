<?php

namespace CrawlFlow\Cron;

use CrawlFlow\Admin\ProjectService;
use CrawlFlow\Flow\FlowService;
use CrawlFlow\Cron\Phase1CrawlService;
use CrawlFlow\Cron\Phase2ProcessService;
use CrawlFlow\Cron\Phase3ResourcesService;
use CrawlFlow\Cron\ProjectCacheService;
use CrawlFlow\Cron\WorkerCacheService;
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
     * @var Phase1CrawlService
     */
    private Phase1CrawlService $phase1Service;

    /**
     * @var Phase2ProcessService
     */
    private Phase2ProcessService $phase2Service;

    /**
     * @var Phase3ResourcesService
     */
    private Phase3ResourcesService $phase3Service;

    /**
     * Base hook name for cron events
     */
    const CRON_HOOK_BASE = 'crawlflow_execute_project';

    /**
     * Phase hooks
     */
    const PHASE_1_CRAWL_HOOK = 'crawlflow_phase1_crawl';
    const PHASE_2_PROCESS_HOOK = 'crawlflow_phase2_process';
    const PHASE_3_RESOURCES_HOOK = 'crawlflow_phase3_resources';

    /**
     * System maintenance hook name
     */
    const SYSTEM_MAINTENANCE_HOOK = 'crawlflow_system_maintance';

    /**
     * Constructor
     */
    public function __construct()
    {
        $rake = Rake::getInstance();
        $this->projectService = $rake->make('CrawlFlow\Admin\ProjectService');
        $this->flowService = $rake->make('CrawlFlow\Flow\FlowService');
        $this->phase1Service = new Phase1CrawlService();
        $this->phase2Service = new Phase2ProcessService();
        $this->phase3Service = new Phase3ResourcesService();
    }

    /**
     * Get unique hook name for a project
     * 
     * @param int $projectId Project ID
     * @return string Hook name
     */
    private function getCronHook(int $projectId): string
    {
        $hash = substr(md5($projectId), 0, 6);
        return self::CRON_HOOK_BASE . '_' . $hash;
    }

    /**
     * Register WordPress hooks
     */
    public function register(): void
    {
        // Register system maintenance hook
        add_action(self::SYSTEM_MAINTENANCE_HOOK, [$this, 'executeSystemMaintenance']);
        
        // Schedule system maintenance after init to avoid translation loading too early
        add_action('init', [$this, 'scheduleSystemMaintenance'], 10);
        
        // Register phase hooks
        add_action(self::PHASE_1_CRAWL_HOOK, [$this, 'executePhase1'], 10, 1);
        add_action(self::PHASE_2_PROCESS_HOOK, [$this, 'executePhase2'], 10, 1);
        add_action(self::PHASE_3_RESOURCES_HOOK, [$this, 'executePhase3'], 10, 1);
        
        // Schedule projects on project save/update
        add_action('crawlflow_project_saved', [$this, 'scheduleProject'], 10, 1);
        add_action('crawlflow_project_updated', [$this, 'scheduleProject'], 10, 1);
        
        // Clear cache on project save/update
        add_action('crawlflow_project_saved', [$this, 'clearProjectCache'], 5, 1);
        add_action('crawlflow_project_updated', [$this, 'clearProjectCache'], 5, 1);
        
        // Unschedule on project delete/deactivate
        add_action('crawlflow_project_deleted', [$this, 'unscheduleProject'], 10, 1);
        add_action('crawlflow_project_deactivated', [$this, 'unscheduleProject'], 10, 1);
        
        // Clear cache on project delete/deactivate
        add_action('crawlflow_project_deleted', [$this, 'clearProjectCache'], 5, 1);
        add_action('crawlflow_project_deactivated', [$this, 'clearProjectCache'], 5, 1);
    }

    /**
     * Schedule a project based on its settings
     * 
     * @param int $projectId Project ID
     * @throws \RuntimeException If scheduling fails
     */
    /**
     * Clear project cache
     * 
     * @param int $projectId Project ID
     */
    public function clearProjectCache(int $projectId): void
    {
        $projectCacheService = new ProjectCacheService();
        $projectCacheService->clearCache($projectId);
        
        // Also clear worker cache
        $workerCacheService = new WorkerCacheService();
        $workerCacheService->clearCache($projectId);
    }

    public function scheduleProject(int $projectId): void
    {
        // Use cached project data
        $projectCacheService = new ProjectCacheService();
        $project = $projectCacheService->getProject($projectId);
        
        if (!$project) {
            throw new \RuntimeException("Project {$projectId} not found");
        }

        // Only schedule active projects
        if ($project['status'] !== 'active') {
            $this->unscheduleProject($projectId);
            return;
        }

        // Get project settings (cached)
        $flowConfig = $projectCacheService->getFlowConfig($projectId);
        $projectSettings = $flowConfig['projectSettings'] ?? [];

        // Check if project is enabled
        if (!($projectSettings['enabled'] ?? false)) {
            $this->unscheduleProject($projectId);
            return;
        }

        // All phases run every 5 minutes by default
        $schedule = 'every_5_minutes';

        // Unschedule existing phases
        $this->unscheduleProject($projectId);

        // Get unique hooks for each phase
        $hash = substr(md5($projectId), 0, 6);
        $phase1Hook = self::PHASE_1_CRAWL_HOOK . '_' . $hash;
        $phase2Hook = self::PHASE_2_PROCESS_HOOK . '_' . $hash;
        $phase3Hook = self::PHASE_3_RESOURCES_HOOK . '_' . $hash;

        // Register phase hooks if not already registered
        if (!has_action($phase1Hook, [$this, 'executePhase1'])) {
            add_action($phase1Hook, [$this, 'executePhase1'], 10, 1);
        }
        if (!has_action($phase2Hook, [$this, 'executePhase2'])) {
            add_action($phase2Hook, [$this, 'executePhase2'], 10, 1);
        }
        if (!has_action($phase3Hook, [$this, 'executePhase3'])) {
            add_action($phase3Hook, [$this, 'executePhase3'], 10, 1);
        }

        // Schedule Phase 1 (Crawl) - runs first, every 5 minutes
        $timestamp = time();
        $scheduled1 = wp_schedule_event($timestamp, $schedule, $phase1Hook, [$projectId]);

        // Schedule Phase 2 (Process) - runs after Phase 1 (delay by 1 minute), every 5 minutes
        $timestamp2 = $timestamp + 60;
        $scheduled2 = wp_schedule_event($timestamp2, $schedule, $phase2Hook, [$projectId]);

        // Schedule Phase 3 (Resources) - runs after Phase 2 (delay by 2 minutes), every 5 minutes
        $timestamp3 = $timestamp + 120;
        $scheduled3 = wp_schedule_event($timestamp3, $schedule, $phase3Hook, [$projectId]);

        if ($scheduled1 === false || $scheduled2 === false || $scheduled3 === false) {
            throw new \RuntimeException("Failed to schedule project {$projectId} phases");
        }

        error_log("CrawlFlow: Scheduled project {$projectId} with 3 phases - Phase1: {$phase1Hook}, Phase2: {$phase2Hook}, Phase3: {$phase3Hook}");
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
     * Unschedule a project (all phases)
     */
    public function unscheduleProject(int $projectId): void
    {
        $hash = substr(md5($projectId), 0, 6);
        $phase1Hook = self::PHASE_1_CRAWL_HOOK . '_' . $hash;
        $phase2Hook = self::PHASE_2_PROCESS_HOOK . '_' . $hash;
        $phase3Hook = self::PHASE_3_RESOURCES_HOOK . '_' . $hash;

        // Unschedule Phase 1
        $timestamp = wp_next_scheduled($phase1Hook, [$projectId]);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $phase1Hook, [$projectId]);
        }

        // Unschedule Phase 2
        $timestamp = wp_next_scheduled($phase2Hook, [$projectId]);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $phase2Hook, [$projectId]);
        }

        // Unschedule Phase 3
        $timestamp = wp_next_scheduled($phase3Hook, [$projectId]);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $phase3Hook, [$projectId]);
        }

        error_log("CrawlFlow: Unscheduled all phases for project {$projectId}");
    }

    /**
     * Execute Phase 1: Crawl (called by WordPress cron)
     * 
     * @param mixed $arg Project ID (passed from wp_schedule_event args) or hook name
     */
    public function executePhase1($arg = null): void
    {
        $projectId = null;
        
        // Try to get project ID from argument
        if (is_numeric($arg)) {
            $projectId = (int)$arg;
        } elseif (is_array($arg) && isset($arg[0]) && is_numeric($arg[0])) {
            $projectId = (int)$arg[0];
        } else {
            // Extract from current hook name
            $currentHook = current_filter();
            if ($currentHook) {
                $projectId = $this->extractProjectIdFromHook($currentHook);
            }
        }
        
        if (!$projectId) {
            error_log("CrawlFlow Phase 1: Could not determine project ID. Arg: " . print_r($arg, true) . ", Hook: " . current_filter());
            return;
        }

        try {
            error_log("CrawlFlow Phase 1: Hook triggered for project {$projectId}");
            
            // Use cached project data
            $projectCacheService = new ProjectCacheService();
            $project = $projectCacheService->getProject($projectId);
            if (!$project) {
                error_log("CrawlFlow Phase 1: Project {$projectId} not found");
                return;
            }
            
            if ($project['status'] !== 'active') {
                error_log("CrawlFlow Phase 1: Project {$projectId} is not active (status: {$project['status']})");
                return;
            }

            error_log("CrawlFlow Phase 1: Executing service for project {$projectId}");
            $result = $this->phase1Service->execute($projectId);
            $this->logPhaseExecution($projectId, 'phase1_crawl', $result);
            
            error_log("CrawlFlow Phase 1: Completed for project {$projectId} - " . json_encode($result));
            
        } catch (\Exception $e) {
            error_log("CrawlFlow Phase 1: Exception for project {$projectId} - " . $e->getMessage());
            error_log("CrawlFlow Phase 1: Stack trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Extract project ID from hook name
     */
    private function extractProjectIdFromHook(string $hook): ?int
    {
        // Hook format: crawlflow_phase1_crawl_c4ca42
        // Extract hash and find project
        if (preg_match('/_([a-f0-9]{6})$/', $hook, $matches)) {
            $hash = $matches[1];
            // Try to find project with this hash
            // We need to check all projects
            $projects = $this->projectService->getAllProjects();
            foreach ($projects as $project) {
                $projectHash = substr(md5($project['id']), 0, 6);
                if ($projectHash === $hash) {
                    return (int)$project['id'];
                }
            }
        }
        return null;
    }

    /**
     * Execute Phase 2: Process (called by WordPress cron)
     * 
     * @param mixed $arg Project ID (passed from wp_schedule_event args) or hook name
     */
    public function executePhase2($arg = null): void
    {
        $projectId = null;
        
        if (is_numeric($arg)) {
            $projectId = (int)$arg;
        } elseif (is_array($arg) && isset($arg[0]) && is_numeric($arg[0])) {
            $projectId = (int)$arg[0];
        } else {
            $currentHook = current_filter();
            if ($currentHook) {
                $projectId = $this->extractProjectIdFromHook($currentHook);
            }
        }
        
        if (!$projectId) {
            error_log("CrawlFlow Phase 2: Could not determine project ID");
            return;
        }

        try {
            error_log("CrawlFlow Phase 2: Hook triggered for project {$projectId}");
            
            // Use cached project data
            $projectCacheService = new ProjectCacheService();
            $project = $projectCacheService->getProject($projectId);
            if (!$project || $project['status'] !== 'active') {
                return;
            }

            $result = $this->phase2Service->execute($projectId);
            $this->logPhaseExecution($projectId, 'phase2_process', $result);
            
            error_log("CrawlFlow Phase 2: Completed for project {$projectId}");
            
        } catch (\Exception $e) {
            error_log("CrawlFlow Phase 2: Failed for project {$projectId} - " . $e->getMessage());
        }
    }

    /**
     * Execute Phase 3: Resources (called by WordPress cron)
     * 
     * @param mixed $arg Project ID (passed from wp_schedule_event args) or hook name
     */
    public function executePhase3($arg = null): void
    {
        $projectId = null;
        
        if (is_numeric($arg)) {
            $projectId = (int)$arg;
        } elseif (is_array($arg) && isset($arg[0]) && is_numeric($arg[0])) {
            $projectId = (int)$arg[0];
        } else {
            $currentHook = current_filter();
            if ($currentHook) {
                $projectId = $this->extractProjectIdFromHook($currentHook);
            }
        }
        
        if (!$projectId) {
            error_log("CrawlFlow Phase 3: Could not determine project ID");
            return;
        }

        try {
            error_log("CrawlFlow Phase 3: Hook triggered for project {$projectId}");
            
            // Use cached project data
            $projectCacheService = new ProjectCacheService();
            $project = $projectCacheService->getProject($projectId);
            if (!$project || $project['status'] !== 'active') {
                return;
            }

            $result = $this->phase3Service->execute($projectId);
            $this->logPhaseExecution($projectId, 'phase3_resources', $result);
            
            error_log("CrawlFlow Phase 3: Completed for project {$projectId}");
            
        } catch (\Exception $e) {
            error_log("CrawlFlow Phase 3: Failed for project {$projectId} - " . $e->getMessage());
        }
    }

    /**
     * Log phase execution
     */
    private function logPhaseExecution(int $projectId, string $phase, array $result): void
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'crawlflow_executions';
        
        $wpdb->insert($table, [
            'project_id' => $projectId,
            'status' => empty($result['errors']) ? 'completed' : 'partial',
            'repository_count' => $result['items_saved'] ?? $result['items_processed'] ?? 0,
            'extracted_count' => $result['resources_detected'] ?? $result['references_saved'] ?? 0,
            'processed_count' => $result['items_processed'] ?? $result['resources_processed'] ?? 0,
            'error_count' => count($result['errors'] ?? []),
            'execution_time' => 0,
            'executed_at' => current_time('mysql'),
        ]);
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

        $projectPhases = [];

        foreach ($crons as $timestamp => $cron) {
            foreach ($cron as $hook => $events) {
                // Check if this is a CrawlFlow phase hook
                if (strpos($hook, self::PHASE_1_CRAWL_HOOK) === 0 ||
                    strpos($hook, self::PHASE_2_PROCESS_HOOK) === 0 ||
                    strpos($hook, self::PHASE_3_RESOURCES_HOOK) === 0) {
                    foreach ($events as $key => $event) {
                        $projectId = $event['args'][0] ?? null;
                        if ($projectId) {
                            $phase = $this->detectPhaseFromHook($hook);
                            if (!isset($projectPhases[$projectId])) {
                                $projectPhases[$projectId] = [
                                    'project_id' => $projectId,
                                    'phases' => [],
                                ];
                            }
                            $projectPhases[$projectId]['phases'][$phase] = [
                                'timestamp' => $timestamp,
                                'schedule' => $event['schedule'] ?? 'once',
                                'interval' => $event['interval'] ?? 0,
                                'next_run' => date('Y-m-d H:i:s', $timestamp),
                            ];
                        }
                    }
                }
            }
        }

        // Convert to flat array
        foreach ($projectPhases as $project) {
            $scheduled[] = $project;
        }
        
        return $scheduled;
    }

    /**
     * Detect phase from hook name
     */
    private function detectPhaseFromHook(string $hook): string
    {
        if (strpos($hook, self::PHASE_1_CRAWL_HOOK) === 0) {
            return 'phase1_crawl';
        }
        if (strpos($hook, self::PHASE_2_PROCESS_HOOK) === 0) {
            return 'phase2_process';
        }
        if (strpos($hook, self::PHASE_3_RESOURCES_HOOK) === 0) {
            return 'phase3_resources';
        }
        return 'unknown';
    }

    /**
     * Check if project is scheduled
     */
    public function isProjectScheduled(int $projectId): bool
    {
        $hash = substr(md5($projectId), 0, 6);
        $phase1Hook = self::PHASE_1_CRAWL_HOOK . '_' . $hash;
        $timestamp = wp_next_scheduled($phase1Hook, [$projectId]);
        return $timestamp !== false;
    }

    /**
     * Get next run time for project (Phase 1)
     */
    public function getNextRunTime(int $projectId): ?int
    {
        $hash = substr(md5($projectId), 0, 6);
        $phase1Hook = self::PHASE_1_CRAWL_HOOK . '_' . $hash;
        $timestamp = wp_next_scheduled($phase1Hook, [$projectId]);
        return $timestamp ?: null;
    }

    /**
     * Schedule system maintenance task (runs every 5 minutes)
     */
    public function scheduleSystemMaintenance(): void
    {
        // Unschedule existing if any
        $timestamp = wp_next_scheduled(self::SYSTEM_MAINTENANCE_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::SYSTEM_MAINTENANCE_HOOK);
        }

        // Schedule new event (every 5 minutes)
        $scheduled = wp_schedule_event(
            time(),
            'every_5_minutes',
            self::SYSTEM_MAINTENANCE_HOOK
        );

        if ($scheduled === false && !$timestamp) {
            error_log("CrawlFlow: Failed to schedule system maintenance");
        } elseif ($scheduled !== false) {
            error_log("CrawlFlow: Scheduled system maintenance (every 5 minutes)");
        }
    }

    /**
     * Execute system maintenance (called by WordPress cron)
     */
    public function executeSystemMaintenance(): void
    {
        try {
            error_log("CrawlFlow: System maintenance task executed at " . date('Y-m-d H:i:s'));
            
            // Add your maintenance tasks here
            // For example: cleanup old logs, check project status, etc.
            
        } catch (\Exception $e) {
            error_log("CrawlFlow: System maintenance failed - " . $e->getMessage());
        }
    }
}

