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
    protected ProjectService $projectService;

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
    const PHASE_BONUS_HOOK = 'crawlflow_bonus_phase';
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
        // Test mode: when running via wp-cron.php and CRAWLFLOW_TEST_CRON=true,
        // bypass schedule and execute all phases immediately in 'init' hook
        // (with high priority to ensure all plugins/data types are loaded)
        if (defined('CRAWLFLOW_TEST_CRON') && CRAWLFLOW_TEST_CRON) {
            add_action('init', [$this, 'maybeRunTestCron'], 20);
        }

        // Register system maintenance hook
        add_action(self::SYSTEM_MAINTENANCE_HOOK, [$this, 'executeSystemMaintenance']);
        
        // Schedule system maintenance after init to avoid translation loading too early
        // Use higher priority to ensure cron schedules are registered first
        add_action('init', [$this, 'scheduleSystemMaintenance'], 15);
        
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

        // Determine schedule from project settings: use crawlDelay (ms) as interval between runs
        $crawlDelayMs = (int)($projectSettings['crawlDelay'] ?? 300000); // default 5 minutes
        $intervalSeconds = max(60, (int)round($crawlDelayMs / 1000)); // enforce minimum 60s
        $scheduleSlug = 'crawlflow_project_' . $projectId;

        // Register custom schedule for this project (per-interval)
        add_filter('cron_schedules', function ($schedules) use ($scheduleSlug, $intervalSeconds) {
            $schedules[$scheduleSlug] = [
                'interval' => $intervalSeconds,
                'display' => "CrawlFlow Project interval ({$intervalSeconds}s)",
            ];
            return $schedules;
        });
        $schedule = $scheduleSlug;

        // Unschedule existing phases
        $this->unscheduleProject($projectId);

        // Get unique hooks for each phase
        $hash = substr(md5($projectId), 0, 6);
        $bonusHook = self::PHASE_BONUS_HOOK . '_' . $hash;
        $phase1Hook = self::PHASE_1_CRAWL_HOOK . '_' . $hash;
        $phase2Hook = self::PHASE_2_PROCESS_HOOK . '_' . $hash;
        $phase3Hook = self::PHASE_3_RESOURCES_HOOK . '_' . $hash;

        // Register phase hooks if not already registered
        if (!has_action($bonusHook, [$this, 'executeBonusPhase'])) {
            add_action($bonusHook, [$this, 'executeBonusPhase'], 10, 1);
        }
        if (!has_action($phase1Hook, [$this, 'executePhase1'])) {
            add_action($phase1Hook, [$this, 'executePhase1'], 10, 1);
        }
        if (!has_action($phase2Hook, [$this, 'executePhase2'])) {
            add_action($phase2Hook, [$this, 'executePhase2'], 10, 1);
        }
        if (!has_action($phase3Hook, [$this, 'executePhase3'])) {
            add_action($phase3Hook, [$this, 'executePhase3'], 10, 1);
        }

        // Schedule Bonus Phase - runs before Phase 1
        $timestamp = time();
        $scheduledBonus = wp_schedule_event($timestamp, $schedule, $bonusHook, [$projectId]);

        // Schedule Phase 1 (Crawl) - runs after bonus (delay by 30s)
        $timestamp1 = $timestamp + 30;
        $scheduled1 = wp_schedule_event($timestamp1, $schedule, $phase1Hook, [$projectId]);

        // Schedule Phase 2 (Process) - runs after Phase 1 (delay by 1 minute)
        $timestamp2 = $timestamp1 + 60;
        $scheduled2 = wp_schedule_event($timestamp2, $schedule, $phase2Hook, [$projectId]);

        // Schedule Phase 3 (Resources) - runs after Phase 2 (delay by 2 minutes)
        $timestamp3 = $timestamp2 + 60;
        $scheduled3 = wp_schedule_event($timestamp3, $schedule, $phase3Hook, [$projectId]);

        if ($scheduledBonus === false || $scheduled1 === false || $scheduled2 === false || $scheduled3 === false) {
            throw new \RuntimeException("Failed to schedule project {$projectId} phases");
        }

        error_log("[CRON SCHEDULE MODE] CrawlFlow: Scheduled project {$projectId} with bonus + 3 phases - Bonus: {$bonusHook}, Phase1: {$phase1Hook}, Phase2: {$phase2Hook}, Phase3: {$phase3Hook}");
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
        $bonusHook = self::PHASE_BONUS_HOOK . '_' . $hash;
        $phase1Hook = self::PHASE_1_CRAWL_HOOK . '_' . $hash;
        $phase2Hook = self::PHASE_2_PROCESS_HOOK . '_' . $hash;
        $phase3Hook = self::PHASE_3_RESOURCES_HOOK . '_' . $hash;

        // Unschedule Bonus phase
        $timestamp = wp_next_scheduled($bonusHook, [$projectId]);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $bonusHook, [$projectId]);
        }

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

        error_log("[CRON SCHEDULE MODE] CrawlFlow: Unscheduled all phases for project {$projectId}");
    }

    /**
     * Execute Phase 1: Crawl (called by WordPress cron)
     * 
     * @param mixed $arg Project ID (passed from wp_schedule_event args) or hook name
     */
    public function executeBonusPhase($arg = null): void
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
            error_log("CrawlFlow Bonus Phase: Could not determine project ID. Arg: " . print_r($arg, true) . ", Hook: " . current_filter());
            return;
        }

        try {
            error_log("CrawlFlow Bonus Phase: Hook triggered for project {$projectId}");
            
            $projectCacheService = new ProjectCacheService();
            $project = $projectCacheService->getProject($projectId);
            if (!$project) {
                error_log("CrawlFlow Bonus Phase: Project {$projectId} not found");
                return;
            }

            if ($project['status'] !== 'active') {
                error_log("CrawlFlow Bonus Phase: Project {$projectId} is not active (status: {$project['status']})");
                return;
            }

            // Execute only bonus actions (phase1 actions) without data source fetch
            $this->phase1Service->executeBonus($projectId);

        } catch (\Exception $e) {
            error_log("CrawlFlow Bonus Phase: Failed for project {$projectId} - " . $e->getMessage());
        }
    }

    /**
     * Get project service instance (for CronServiceProvider)
     * 
     * @return ProjectService
     */
    public function getProjectService(): ProjectService
    {
        return $this->projectService;
    }

    /**
     * Execute Phase 1: Crawl (called by WordPress cron)
     * 
     * @param mixed $arg Project ID (passed from wp_schedule_event args) or hook name
     */
    public function executePhase1($arg = null): void
    {
        // Determine execution mode
        $isTestMode = defined('CRAWLFLOW_TEST_CRON') && CRAWLFLOW_TEST_CRON;
        $mode = $isTestMode ? 'TEST MODE' : 'CRON SCHEDULE MODE';
        $eventName = current_filter() ?: 'crawlflow_phase1_crawl';
        
        error_log("CrawlFlow: Starting Phase 1 (Crawl) - Event: {$eventName} [{$mode}]");
        
        $lockId = $this->acquireEventLock($eventName);
        if ($lockId === null) {
            error_log("CrawlFlow: Phase 1 (Crawl) - Could not acquire lock, skipping [{$mode}]");
            return;
        }

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
            
            $isTestMode = defined('CRAWLFLOW_TEST_CRON') && CRAWLFLOW_TEST_CRON;
            $mode = $isTestMode ? 'TEST MODE' : 'CRON SCHEDULE MODE';
            error_log("CrawlFlow Phase 1: Completed for project {$projectId} [{$mode}] - " . json_encode($result));
            $this->completeEventLock($lockId, 'complete');
            
        } catch (\Exception $e) {
            error_log("CrawlFlow Phase 1: Exception for project {$projectId} - " . $e->getMessage());
            error_log("CrawlFlow Phase 1: Stack trace: " . $e->getTraceAsString());
            $this->completeEventLock($lockId, 'error');
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
     * Try to acquire lock for an event; returns lock id or null if canceled
     */
    private function acquireEventLock(string $eventName): ?int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'rake_event_status';
        $now = current_time('mysql');
        $pid = getmypid();

        // Check existing running/pending event
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE event_name = %s AND status IN ('pending','running')
             ORDER BY updated_at DESC
             LIMIT 1",
            $eventName
        ), ARRAY_A);

        if ($existing) {
            $alive = $this->isProcessAlive((int)$existing['process_id'], $existing['updated_at'] ?? null);
            if ($alive) {
                // Insert cancel record for current process and skip
                $wpdb->insert($table, [
                    'event_name' => $eventName,
                    'process_id' => $pid,
                    'status' => 'cancel',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                error_log("CrawlFlow Cron: Event {$eventName} already running by PID {$existing['process_id']}, skipping current PID {$pid}");
                return null;
            }

            // Mark stale process as error
            $wpdb->update(
                $table,
                [
                    'status' => 'error',
                    'updated_at' => $now,
                ],
                ['id' => $existing['id']],
                ['%s', '%s'],
                ['%d']
            );
        }

        // Insert new running record
        $wpdb->insert($table, [
            'event_name' => $eventName,
            'process_id' => $pid,
            'status' => 'running',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int)$wpdb->insert_id;
    }

    /**
     * Complete lock with status
     */
    private function completeEventLock(int $lockId, string $status): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_event_status';
        $wpdb->update(
            $table,
            [
                'status' => $status,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $lockId],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * Check if OS process is still alive
     */
    private function isProcessAlive(int $pid, ?string $updatedAt = null): bool
    {
        if ($pid <= 0) {
            return false;
        }

        // POSIX check
        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        if (function_exists('posix_getpgid')) {
            return posix_getpgid($pid) !== false;
        }

        // Linux /proc
        if (DIRECTORY_SEPARATOR === '/' && file_exists("/proc/{$pid}")) {
            return true;
        }

        // Fallback: consider stale if last update > 10 minutes
        if ($updatedAt) {
            $updatedTs = strtotime($updatedAt);
            if ($updatedTs && (time() - $updatedTs) > 600) {
                return false;
            }
        }

        // Unknown platform, assume alive to be safe
        return true;
    }

    /**
     * Test-cron mode: when CRAWLFLOW_TEST_CRON=true and running inside wp-cron.php,
     * execute all three phases immediately for all active projects (ignore schedules).
     * Runs in 'init' hook to ensure all plugins and data types (like taxonomies) are loaded.
     */
    public function maybeRunTestCron(): void
    {
        // Only run in cron context (when called from wp-cron.php)
        if (!defined('DOING_CRON') || !DOING_CRON) {
            error_log('[TEST MODE] CrawlFlow: Test cron mode detected but not in DOING_CRON context, skipping');
            return;
        }

        // Ensure we run only once per request
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;

        error_log('[TEST MODE] CrawlFlow: Test cron mode detected, executing all phases for active projects in init hook');

        $projects = $this->projectService->getAllProjects();
        error_log('[TEST MODE] CrawlFlow: Found ' . count($projects) . ' total projects for test cron execution');
        
        foreach ($projects as $project) {
            $projectId = (int)($project['id'] ?? 0);
            if (!$projectId || ($project['status'] ?? '') !== 'active') {
                error_log("[TEST MODE] CrawlFlow: Skipping project {$projectId} - status: " . ($project['status'] ?? 'unknown'));
                continue;
            }

            error_log("[TEST MODE] CrawlFlow: Starting sequential phase execution for project {$projectId}");

            // Execute phases sequentially
            // All phases run in 'init' hook context, ensuring plugins/data types are loaded
            try {
                error_log("[TEST MODE] CrawlFlow: Executing Phase 1 (Crawl) for project {$projectId}");
                $this->executePhase1($projectId);
                
                error_log("[TEST MODE] CrawlFlow: Executing Phase 2 (Process) for project {$projectId}");
                $this->executePhase2($projectId);
                
                error_log("[TEST MODE] CrawlFlow: Executing Phase 3 (Resources) for project {$projectId}");
                $this->executePhase3($projectId);
                
                error_log("[TEST MODE] CrawlFlow: Completed all phases for project {$projectId}");
            } catch (\Exception $e) {
                error_log("[TEST MODE] CrawlFlow: Error executing phases for project {$projectId}: " . $e->getMessage());
            }
        }
        
        error_log('[TEST MODE] CrawlFlow: Test cron execution completed for all active projects');
    }

    /**
     * Execute Phase 2: Process (called by WordPress cron)
     * 
     * @param mixed $arg Project ID (passed from wp_schedule_event args) or hook name
     */
    public function executePhase2($arg = null): void
    {
        // Determine execution mode
        $isTestMode = defined('CRAWLFLOW_TEST_CRON') && CRAWLFLOW_TEST_CRON;
        $mode = $isTestMode ? 'TEST MODE' : 'CRON SCHEDULE MODE';
        $eventName = current_filter() ?: 'crawlflow_phase2_process';
        
        error_log("CrawlFlow: Starting Phase 2 (Process) - Event: {$eventName} [{$mode}]");
        
        $lockId = $this->acquireEventLock($eventName);
        if ($lockId === null) {
            error_log("CrawlFlow: Phase 2 (Process) - Could not acquire lock, skipping [{$mode}]");
            return;
        }

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
            
            $isTestMode = defined('CRAWLFLOW_TEST_CRON') && CRAWLFLOW_TEST_CRON;
            $mode = $isTestMode ? 'TEST MODE' : 'CRON SCHEDULE MODE';
            error_log("CrawlFlow Phase 2: Completed for project {$projectId} [{$mode}] - " . json_encode($result));
            $this->completeEventLock($lockId, 'complete');
            
        } catch (\Exception $e) {
            error_log("CrawlFlow Phase 2: Failed for project {$projectId} - " . $e->getMessage());
            $this->completeEventLock($lockId, 'error');
        }
    }

    /**
     * Execute Phase 3: Resources (called by WordPress cron)
     * 
     * @param mixed $arg Project ID (passed from wp_schedule_event args) or hook name
     */
    public function executePhase3($arg = null): void
    {
        // Determine execution mode
        $isTestMode = defined('CRAWLFLOW_TEST_CRON') && CRAWLFLOW_TEST_CRON;
        $mode = $isTestMode ? 'TEST MODE' : 'CRON SCHEDULE MODE';
        $eventName = current_filter() ?: 'crawlflow_phase3_resources';
        
        error_log("CrawlFlow: Starting Phase 3 (Resources) - Event: {$eventName} [{$mode}]");
        
        $lockId = $this->acquireEventLock($eventName);
        if ($lockId === null) {
            error_log("CrawlFlow: Phase 3 (Resources) - Could not acquire lock, skipping [{$mode}]");
            return;
        }

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
            
            $isTestMode = defined('CRAWLFLOW_TEST_CRON') && CRAWLFLOW_TEST_CRON;
            $mode = $isTestMode ? 'TEST MODE' : 'CRON SCHEDULE MODE';
            error_log("CrawlFlow Phase 3: Completed for project {$projectId} [{$mode}] - " . json_encode($result));
            $this->completeEventLock($lockId, 'complete');
            
        } catch (\Exception $e) {
            error_log("CrawlFlow Phase 3: Failed for project {$projectId} - " . $e->getMessage());
            $this->completeEventLock($lockId, 'error');
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
     * Register custom cron schedules globally
     * This ensures schedules are available when WordPress tries to reschedule events
     */
    public function registerCustomSchedules(): void
    {
        add_filter('cron_schedules', function ($schedules) {
            // Get all active projects and register their schedules
            try {
                // Use existing projectService to avoid conflicts with test cron
                $projects = $this->projectService->getAllProjects();
                
                foreach ($projects as $project) {
                    if (($project['status'] ?? '') === 'active') {
                        $projectId = $project['id'] ?? 0;
                        $projectCacheService = new ProjectCacheService();
                        $flowConfig = $projectCacheService->getFlowConfig($projectId);
                        $projectSettings = $flowConfig['projectSettings'] ?? [];
                        
                        if ($projectSettings['enabled'] ?? false) {
                            $crawlDelayMs = (int)($projectSettings['crawlDelay'] ?? 300000);
                            $intervalSeconds = max(60, (int)round($crawlDelayMs / 1000));
                            $scheduleSlug = 'crawlflow_project_' . $projectId;
                            
                            $schedules[$scheduleSlug] = [
                                'interval' => $intervalSeconds,
                                'display' => "CrawlFlow Project interval ({$intervalSeconds}s)",
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log("CrawlFlow: Error registering custom schedules: " . $e->getMessage());
            }
            
            return $schedules;
        });
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
        // Check if already scheduled
        $timestamp = wp_next_scheduled(self::SYSTEM_MAINTENANCE_HOOK);
        if ($timestamp !== false) {
            // Already scheduled, no need to reschedule
            return;
        }

        // Schedule new event (every 5 minutes)
        $scheduled = wp_schedule_event(
            time(),
            'every_5_minutes',
            self::SYSTEM_MAINTENANCE_HOOK
        );

        if ($scheduled === false) {
            error_log("[CRON SCHEDULE MODE] CrawlFlow: Failed to schedule system maintenance");
        } else {
            error_log("[CRON SCHEDULE MODE] CrawlFlow: Scheduled system maintenance (every 5 minutes)");
        }
    }

    /**
     * Execute system maintenance (called by WordPress cron)
     */
    public function executeSystemMaintenance(): void
    {
        try {
            error_log("[CRON SCHEDULE MODE] CrawlFlow: System maintenance task executed at " . date('Y-m-d H:i:s'));
            
            // Add your maintenance tasks here
            // For example: cleanup old logs, check project status, etc.
            
        } catch (\Exception $e) {
            error_log("[CRON SCHEDULE MODE] CrawlFlow: System maintenance failed - " . $e->getMessage());
        }
    }
}

