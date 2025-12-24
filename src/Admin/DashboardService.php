<?php

namespace CrawlFlow\Admin;

use CrawlFlow\Admin\MigrationService;
use CrawlFlow\Admin\ProjectService;
use CrawlFlow\Admin\LogService;

/**
 * Dashboard Service for CrawlFlow
 * Manages data and logic for different admin screens
 */
class DashboardService
{
    /**
     * @var MigrationService
     */
    private $migrationService;

    /**
     * @var ProjectService
     */
    private $projectService;

    /**
     * @var LogService
     */
    private $logService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->migrationService = new MigrationService();
        $this->projectService = new ProjectService();
        $this->logService = new LogService();
    }

    /**
     * Get screen data based on current screen
     */
    public function getScreenData(string $screen): array
    {
        switch ($screen) {
            case 'crawlflow':
                return $this->getDashboardData();





            case 'crawlflow-logs':
                return $this->getLogsData();

            case 'crawlflow-projects':
                return $this->getProjectsData();

            case 'crawlflow-analytics':
                return $this->getAnalyticsData();

            default:
                return $this->getDefaultData();
        }
    }

    /**
     * Get dashboard overview data
     */
    private function getDashboardData(): array
    {
        return [
            'title' => 'CrawlFlow Dashboard',
            'total_projects' => $this->projectService->getTotalProjects(),
            'active_projects' => $this->projectService->getActiveProjects(),
            'total_urls_processed' => $this->projectService->getTotalUrlsProcessed(),
            'total_urls_pending' => $this->projectService->getTotalUrlsPending(),
            'total_urls_skipped' => $this->projectService->getTotalUrlsSkipped(),
            'total_urls_failed' => $this->projectService->getTotalUrlsFailed(),
            'total_logs' => $this->logService->getTotalLogs(),
            'recent_projects' => $this->projectService->getRecentProjects(5),
            'system_status' => $this->getSystemStatus(),
            'settings' => $this->getSettings(),
            'system_info' => $this->getSystemInfo(),
            'migration_status' => $this->migrationService->checkMigrationStatus(),

        ];
    }







    /**
     * Get logs data
     */
    private function getLogsData(): array
    {
        $page = $_GET['paged'] ?? 1;
        $per_page = 20;

        return [
            'title' => 'System Logs',
            'logs' => $this->logService->getLogs($page, $per_page),
            'total_logs' => $this->logService->getTotalLogs(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_pages' => ceil($this->logService->getTotalLogs() / $per_page),
            ],
        ];
    }

    /**
     * Get projects data
     */
    private function getProjectsData(): array
    {
        $page = $_GET['paged'] ?? 1;
        $per_page = 10;

        return [
            'title' => 'Projects Management',
            'projects' => $this->projectService->getProjects($page, $per_page),
            'total_projects' => $this->projectService->getTotalProjects(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_pages' => ceil($this->projectService->getTotalProjects() / $per_page),
            ],
        ];
    }

    /**
     * Get analytics data
     */
    private function getAnalyticsData(): array
    {
        $period = $_GET['period'] ?? '7days';

        return [
            'title' => 'Analytics',
            'period' => $period,
            'urls_processed_chart' => $this->projectService->getUrlsProcessedChart($period),
            'projects_performance' => $this->projectService->getProjectsPerformance($period),
            'system_usage' => $this->getSystemUsage($period),
        ];
    }

    /**
     * Get default data
     */
    private function getDefaultData(): array
    {
        return [
            'title' => 'CrawlFlow',
            'message' => 'Welcome to CrawlFlow',
        ];
    }

    /**
     * Get system status
     */
    private function getSystemStatus(): array
    {
        $status = [];
        
        // Database status
        try {
            $status['database'] = $this->checkDatabaseStatus();
        } catch (\Exception $e) {
            $status['database'] = [
                'status' => 'error',
                'message' => 'Unable to check database status',
            ];
        }
        
        // Migration status
        try {
            $migrationStatus = $this->migrationService->checkMigrationStatus();
            if (!empty($migrationStatus)) {
                $status['migrations'] = $migrationStatus;
            }
        } catch (\Exception $e) {
            // Migration status optional, skip if error
        }
        
        // Disk space
        try {
            $status['disk_space'] = $this->checkDiskSpace();
        } catch (\Exception $e) {
            $status['disk_space'] = [
                'status' => 'unknown',
                'usage_percentage' => 0,
                'message' => 'Unable to check disk space',
            ];
        }
        
        // Memory usage
        try {
            $status['memory_usage'] = $this->checkMemoryUsage();
        } catch (\Exception $e) {
            $status['memory_usage'] = [
                'status' => 'unknown',
                'usage_percentage' => 0,
                'message' => 'Unable to check memory usage',
            ];
        }
        
        return $status;
    }

    /**
     * Get settings
     */
    private function getSettings(): array
    {
        return [
            'general' => get_option('crawlflow_general_settings', []),
            'logging' => get_option('crawlflow_logging_settings', []),
            'performance' => get_option('crawlflow_performance_settings', []),
        ];
    }

    /**
     * Get system info
     */
    private function getSystemInfo(): array
    {
        global $wpdb;

        // Get PHP version
        $phpVersion = defined('PHP_VERSION') ? PHP_VERSION : 'Unknown';
        
        // Get WordPress version
        $wpVersion = '';
        try {
            $wpVersion = get_bloginfo('version');
        } catch (\Exception $e) {
            $wpVersion = 'Unknown';
        }
        if (empty($wpVersion)) {
            $wpVersion = get_bloginfo('version') ?: (defined('WP_VERSION') ? WP_VERSION : 'Unknown');
        }

        // Get MySQL version safely
        $mysqlVersion = 'Unknown';
        try {
            if (isset($wpdb) && method_exists($wpdb, 'db_version')) {
                $mysqlVersion = $wpdb->db_version();
            }
        } catch (\Exception $e) {
            $mysqlVersion = 'Unknown';
        }
        if (empty($mysqlVersion)) {
            $mysqlVersion = 'Unknown';
        }

        // Get memory limit
        $memoryLimit = ini_get('memory_limit');
        if (empty($memoryLimit) || $memoryLimit === false) {
            $memoryLimit = 'Unknown';
        }

        return [
            'php_version' => $phpVersion,
            'wordpress_version' => $wpVersion,
            'mysql_version' => $mysqlVersion,
            'memory_limit' => $memoryLimit,
            'max_execution_time' => ini_get('max_execution_time') ?: 'Unknown',
            'upload_max_filesize' => ini_get('upload_max_filesize') ?: 'Unknown',
            'post_max_size' => ini_get('post_max_size') ?: 'Unknown',
        ];
    }

    /**
     * Get system usage
     */
    private function getSystemUsage(string $period): array
    {
        return [
            'cpu_usage' => $this->getCpuUsage(),
            'memory_usage' => $this->getMemoryUsage(),
            'disk_usage' => $this->getDiskUsage(),
        ];
    }

    /**
     * Check database status
     */
    private function checkDatabaseStatus(): array
    {
        global $wpdb;

        try {
            if (!isset($wpdb)) {
                return [
                    'status' => 'error',
                    'message' => 'WordPress database not available',
                ];
            }
            
            $result = $wpdb->query('SELECT 1');
            if ($result !== false) {
                return [
                    'status' => 'connected',
                    'message' => 'Database connection successful',
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Database query failed',
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        } catch (\Error $e) {
            return [
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check disk space
     */
    private function checkDiskSpace(): array
    {
        try {
            $free_space = @disk_free_space(ABSPATH);
            $total_space = @disk_total_space(ABSPATH);
            
            if ($free_space === false || $total_space === false || $total_space == 0) {
                return [
                    'status' => 'unknown',
                    'usage_percentage' => 0,
                    'free_space' => 'Unknown',
                    'total_space' => 'Unknown',
                    'message' => 'Unable to check disk space',
                ];
            }
            
            $used_space = $total_space - $free_space;
            $usage_percentage = ($used_space / $total_space) * 100;

            return [
                'free_space' => $this->formatBytes($free_space),
                'total_space' => $this->formatBytes($total_space),
                'used_space' => $this->formatBytes($used_space),
                'usage_percentage' => round($usage_percentage, 2),
                'status' => $usage_percentage > 90 ? 'warning' : 'ok',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unknown',
                'usage_percentage' => 0,
                'free_space' => 'Unknown',
                'total_space' => 'Unknown',
                'message' => 'Error checking disk space: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check memory usage
     */
    private function checkMemoryUsage(): array
    {
        try {
            $memory_usage = memory_get_usage(true);
            $memory_limit = ini_get('memory_limit');
            
            if (empty($memory_limit) || $memory_limit === false) {
                return [
                    'status' => 'unknown',
                    'usage_percentage' => 0,
                    'current_usage' => $this->formatBytes($memory_usage),
                    'memory_limit' => 'Unknown',
                    'message' => 'Memory limit not configured',
                ];
            }
            
            $memory_limit_bytes = $this->parseBytes($memory_limit);
            
            if ($memory_limit_bytes == 0) {
                return [
                    'status' => 'unknown',
                    'usage_percentage' => 0,
                    'current_usage' => $this->formatBytes($memory_usage),
                    'memory_limit' => $memory_limit,
                    'message' => 'Unable to parse memory limit',
                ];
            }
            
            $usage_percentage = ($memory_usage / $memory_limit_bytes) * 100;

            return [
                'current_usage' => $this->formatBytes($memory_usage),
                'memory_limit' => $memory_limit,
                'usage_percentage' => round($usage_percentage, 2),
                'status' => $usage_percentage > 80 ? 'warning' : 'ok',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unknown',
                'usage_percentage' => 0,
                'current_usage' => 'Unknown',
                'memory_limit' => 'Unknown',
                'message' => 'Error checking memory usage: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get CPU usage (estimated)
     */
    private function getCpuUsage(): float
    {
        // Simple CPU usage estimation
        $load = sys_getloadavg();
        return $load[0] ?? 0;
    }

    /**
     * Get memory usage
     */
    private function getMemoryUsage(): array
    {
        $usage = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);

        return [
            'current' => $this->formatBytes($usage),
            'peak' => $this->formatBytes($peak),
        ];
    }

    /**
     * Get disk usage
     */
    private function getDiskUsage(): array
    {
        $free = disk_free_space(ABSPATH);
        $total = disk_total_space(ABSPATH);
        $used = $total - $free;

        return [
            'free' => $this->formatBytes($free),
            'total' => $this->formatBytes($total),
            'used' => $this->formatBytes($used),
            'percentage' => round(($used / $total) * 100, 2),
        ];
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Parse bytes from string format
     */
    private function parseBytes(string $size): int
    {
        $unit = strtoupper(substr($size, -1));
        $value = (int) substr($size, 0, -1);

        switch ($unit) {
            case 'K':
                return $value * 1024;
            case 'M':
                return $value * 1024 * 1024;
            case 'G':
                return $value * 1024 * 1024 * 1024;
            default:
                return $value;
        }
    }

    /**
     * Render default dashboard
     */
    public function renderDefaultDashboard(array $data): void
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($data['title'] ?? 'CrawlFlow'); ?></h1>
            <p><?php echo esc_html($data['message'] ?? 'Welcome to CrawlFlow'); ?></p>
        </div>
        <?php
    }
}