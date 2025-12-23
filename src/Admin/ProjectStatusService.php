<?php

namespace CrawlFlow\Admin;

use CrawlFlow\Admin\ProjectService;

/**
 * Project Status Service for CrawlFlow
 * Manages project completion tracking and status updates
 */
class ProjectStatusService
{
    /**
     * Project status constants
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_ACTIVE = 'active';
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_PARTIAL = 'partial';
    const STATUS_PAUSED = 'paused';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * @var ProjectService
     */
    private $projectService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->projectService = new ProjectService();
    }

    /**
     * Get project completion status based on execution metrics
     * 
     * @param int $projectId Project ID
     * @return array Completion status with details
     */
    public function getProjectCompletionStatus(int $projectId): array
    {
        global $wpdb;
        
        $toothsTable = $wpdb->prefix . 'rake_tooths';
        $urlsTable = $wpdb->prefix . 'rake_urls';
        $executionsTable = $wpdb->prefix . 'crawlflow_executions';
        
        // Get project details
        $project = $this->projectService->getProject($projectId);
        if (!$project) {
            return [
                'status' => self::STATUS_FAILED,
                'completion_percentage' => 0,
                'details' => ['error' => 'Project not found']
            ];
        }

        // Get URL processing stats
        $urlStats = $this->getUrlProcessingStats($projectId);
        
        // Get execution stats
        $executionStats = $this->getExecutionStats($projectId);
        
        // Get project config to determine completion criteria
        $config = $this->projectService->getFlowConfig($projectId);
        $completionCriteria = $this->getCompletionCriteria($config);
        
        // Calculate completion percentage
        $completionPercentage = $this->calculateCompletionPercentage($urlStats, $executionStats, $completionCriteria);
        
        // Determine final status
        $status = $this->determineProjectStatus($urlStats, $executionStats, $completionCriteria, $project['status']);
        
        return [
            'status' => $status,
            'completion_percentage' => $completionPercentage,
            'url_stats' => $urlStats,
            'execution_stats' => $executionStats,
            'completion_criteria' => $completionCriteria,
            'details' => $this->getStatusDetails($status, $urlStats, $executionStats, $completionCriteria)
        ];
    }

    /**
     * Get URL processing statistics
     */
    private function getUrlProcessingStats(int $projectId): array
    {
        global $wpdb;
        $urlsTable = $wpdb->prefix . 'rake_urls';
        
        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_urls,
                    SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as processed_urls,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_urls,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_urls,
                    SUM(CASE WHEN skipped = 1 THEN 1 ELSE 0 END) as skipped_urls,
                    MAX(crawled_at) as last_crawled_at,
                    MIN(crawled_at) as first_crawled_at
                FROM $urlsTable 
                WHERE tooth_id = %d",
                $projectId
            ),
            ARRAY_A
        );

        return $stats ?: [
            'total_urls' => 0,
            'processed_urls' => 0,
            'pending_urls' => 0,
            'failed_urls' => 0,
            'skipped_urls' => 0,
            'last_crawled_at' => null,
            'first_crawled_at' => null
        ];
    }

    /**
     * Get execution statistics
     */
    private function getExecutionStats(int $projectId): array
    {
        global $wpdb;
        $executionsTable = $wpdb->prefix . 'crawlflow_executions';
        
        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_executions,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_executions,
                    SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) as partial_executions,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_executions,
                    SUM(repository_count) as total_repositories,
                    SUM(extracted_count) as total_extracted,
                    SUM(processed_count) as total_processed,
                    SUM(error_count) as total_errors,
                    AVG(execution_time) as avg_execution_time,
                    MAX(executed_at) as last_execution_at
                FROM $executionsTable 
                WHERE project_id = %d",
                $projectId
            ),
            ARRAY_A
        );

        return $stats ?: [
            'total_executions' => 0,
            'completed_executions' => 0,
            'partial_executions' => 0,
            'failed_executions' => 0,
            'total_repositories' => 0,
            'total_extracted' => 0,
            'total_processed' => 0,
            'total_errors' => 0,
            'avg_execution_time' => 0,
            'last_execution_at' => null
        ];
    }

    /**
     * Get completion criteria from project config
     */
    private function getCompletionCriteria(?array $config): array
    {
        $defaultCriteria = [
            'max_urls' => 100,
            'required_phases' => ['scan', 'extract', 'process'],
            'success_threshold' => 95, // 95% success rate
            'max_error_rate' => 5, // 5% max error rate
            'timeout_hours' => 24
        ];

        if (!$config) {
            return $defaultCriteria;
        }

        // Extract criteria from config
        $criteria = $defaultCriteria;
        
        if (isset($config['max_urls'])) {
            $criteria['max_urls'] = (int) $config['max_urls'];
        }
        
        if (isset($config['completion_criteria'])) {
            $userCriteria = $config['completion_criteria'];
            if (isset($userCriteria['success_threshold'])) {
                $criteria['success_threshold'] = (int) $userCriteria['success_threshold'];
            }
            if (isset($userCriteria['max_error_rate'])) {
                $criteria['max_error_rate'] = (int) $userCriteria['max_error_rate'];
            }
            if (isset($userCriteria['timeout_hours'])) {
                $criteria['timeout_hours'] = (int) $userCriteria['timeout_hours'];
            }
        }

        return $criteria;
    }

    /**
     * Calculate completion percentage
     */
    private function calculateCompletionPercentage(array $urlStats, array $executionStats, array $criteria): float
    {
        $totalScore = 0;
        $maxScore = 100;

        // URL processing score (40% weight)
        if ($urlStats['total_urls'] > 0) {
            $urlScore = ($urlStats['processed_urls'] / $urlStats['total_urls']) * 40;
            $totalScore += $urlScore;
        } else {
            $totalScore += 0;
        }

        // Execution success score (30% weight)
        if ($executionStats['total_executions'] > 0) {
            $executionScore = ($executionStats['completed_executions'] / $executionStats['total_executions']) * 30;
            $totalScore += $executionScore;
        } else {
            $totalScore += 0;
        }

        // Error rate score (20% weight)
        if ($urlStats['total_urls'] > 0) {
            $errorRate = ($urlStats['failed_urls'] / $urlStats['total_urls']) * 100;
            $errorScore = max(0, 20 - ($errorRate * 4)); // Penalize high error rates
            $totalScore += $errorScore;
        } else {
            $totalScore += 20;
        }

        // Progress toward goals (10% weight)
        $progressScore = 0;
        if ($criteria['max_urls'] > 0) {
            $progressScore = min(10, ($urlStats['processed_urls'] / $criteria['max_urls']) * 10);
        }
        $totalScore += $progressScore;

        return min(100, max(0, $totalScore));
    }

    /**
     * Determine project status based on metrics
     */
    private function determineProjectStatus(array $urlStats, array $executionStats, array $criteria, string $currentStatus): string
    {
        // If project is manually paused/cancelled, keep that status
        if (in_array($currentStatus, [self::STATUS_PAUSED, self::STATUS_CANCELLED])) {
            return $currentStatus;
        }

        // Check for timeout
        if ($executionStats['last_execution_at']) {
            $lastExecution = strtotime($executionStats['last_execution_at']);
            $timeout = $criteria['timeout_hours'] * 3600;
            if (time() - $lastExecution > $timeout && $urlStats['pending_urls'] > 0) {
                return self::STATUS_FAILED;
            }
        }

        // Check if all URLs processed
        if ($urlStats['total_urls'] > 0 && $urlStats['pending_urls'] === 0) {
            $successRate = ($urlStats['processed_urls'] / $urlStats['total_urls']) * 100;
            $errorRate = ($urlStats['failed_urls'] / $urlStats['total_urls']) * 100;

            if ($successRate >= $criteria['success_threshold'] && $errorRate <= $criteria['max_error_rate']) {
                return self::STATUS_COMPLETED;
            } elseif ($successRate >= 50) { // At least 50% processed
                return self::STATUS_PARTIAL;
            } else {
                return self::STATUS_FAILED;
            }
        }

        // Check if currently running
        if ($urlStats['pending_urls'] > 0 && $executionStats['last_execution_at']) {
            $lastExecution = strtotime($executionStats['last_execution_at']);
            if (time() - $lastExecution < 3600) { // Active in last hour
                return self::STATUS_RUNNING;
            }
        }

        // Default to current status or active
        return in_array($currentStatus, [self::STATUS_DRAFT, self::STATUS_ACTIVE]) ? $currentStatus : self::STATUS_ACTIVE;
    }

    /**
     * Get status details for display
     */
    private function getStatusDetails(string $status, array $urlStats, array $executionStats, array $criteria): array
    {
        $details = [
            'message' => '',
            'actions' => [],
            'next_steps' => []
        ];

        switch ($status) {
            case self::STATUS_COMPLETED:
                $details['message'] = 'Project completed successfully';
                $details['actions'] = ['view_results', 'export_data', 'archive_project'];
                break;

            case self::STATUS_FAILED:
                $details['message'] = 'Project failed to complete';
                $details['actions'] = ['retry_project', 'view_errors', 'reset_project'];
                $details['next_steps'] = ['Check error logs', 'Review configuration', 'Adjust completion criteria'];
                break;

            case self::STATUS_PARTIAL:
                $details['message'] = 'Project partially completed';
                $details['actions'] = ['continue_crawling', 'view_results', 'adjust_settings'];
                $details['next_steps'] = ['Review failed URLs', 'Adjust error thresholds', 'Continue processing'];
                break;

            case self::STATUS_RUNNING:
                $details['message'] = 'Project is currently running';
                $details['actions'] = ['monitor_progress', 'pause_project'];
                break;

            case self::STATUS_PAUSED:
                $details['message'] = 'Project is paused';
                $details['actions'] = ['resume_project', 'cancel_project'];
                break;

            default:
                $details['message'] = 'Project is ready to start';
                $details['actions'] = ['start_project', 'edit_configuration'];
                break;
        }

        return $details;
    }

    /**
     * Update project status with completion tracking
     */
    public function updateProjectStatus(int $projectId, string $status, ?float $completionPercentage = null): bool
    {
        global $wpdb;
        $toothsTable = $wpdb->prefix . 'rake_tooths';

        $data = [
            'status' => $status,
            'updated_at' => current_time('mysql'),
            'last_status_update' => current_time('mysql')
        ];

        if ($completionPercentage !== null) {
            $data['completion_percentage'] = $completionPercentage;
        }

        $result = $wpdb->update(
            $toothsTable,
            $data,
            ['id' => $projectId],
            ['%s', '%s', '%s', '%f'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Get all projects with their completion status
     */
    public function getProjectsWithCompletionStatus(): array
    {
        $projects = $this->projectService->getAllProjects();
        $projectsWithStatus = [];

        foreach ($projects as $project) {
            $completionStatus = $this->getProjectCompletionStatus($project['id']);
            $projectsWithStatus[] = array_merge($project, $completionStatus);
        }

        return $projectsWithStatus;
    }

    /**
     * Auto-update project statuses with completion percentage (should be called periodically)
     */
    public function autoUpdateProjectStatuses(): array
    {
        $projects = $this->projectService->getAllProjects();
        $updated = [];

        foreach ($projects as $project) {
            $currentStatus = $this->getProjectCompletionStatus($project['id']);
            
            $statusChanged = $currentStatus['status'] !== $project['status'];
            $completionChanged = abs($currentStatus['completion_percentage'] - ($project['completion_percentage'] ?? 0)) > 0.01;
            
            if ($statusChanged || $completionChanged) {
                $this->updateProjectStatus(
                    $project['id'], 
                    $currentStatus['status'], 
                    $currentStatus['completion_percentage']
                );
                
                $updateInfo = [
                    'project_id' => $project['id'],
                    'project_name' => $project['name'],
                    'old_status' => $project['status'],
                    'new_status' => $currentStatus['status'],
                    'completion_percentage' => $currentStatus['completion_percentage']
                ];
                
                if ($statusChanged) {
                    $updateInfo['status_changed'] = true;
                }
                
                if ($completionChanged) {
                    $updateInfo['completion_updated'] = true;
                }
                
                $updated[] = $updateInfo;
            }
        }

        return $updated;
    }
}
