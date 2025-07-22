<?php

namespace CrawlFlow\Admin;

use Puleeno\Rake\WordPress\Adapter\WordPressDatabaseAdapter;

/**
 * Log Service for CrawlFlow
 * Manages log-related operations
 */
class LogService
{
    /**
     * @var WordPressDatabaseAdapter
     */
    private $databaseAdapter;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->databaseAdapter = new WordPressDatabaseAdapter();
    }

    /**
     * Get total number of logs
     */
    public function getTotalLogs(): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_logs';

        $result = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        return (int) $result;
    }

    /**
     * Get logs with pagination
     */
    public function getLogs(int $page = 1, int $perPage = 20): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_logs';

        $offset = ($page - 1) * $perPage;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $perPage,
                $offset
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get logs by level
     */
    public function getLogsByLevel(string $level, int $page = 1, int $perPage = 20): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_logs';

        $offset = ($page - 1) * $perPage;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE level = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $level,
                $perPage,
                $offset
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get logs by project
     */
    public function getLogsByProject(int $projectId, int $page = 1, int $perPage = 20): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_logs';

        $offset = ($page - 1) * $perPage;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE tooth_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $projectId,
                $perPage,
                $offset
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get recent logs
     */
    public function getRecentLogs(int $limit = 10): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_logs';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get error logs
     */
    public function getErrorLogs(int $limit = 10): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_logs';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE level IN ('error', 'critical') ORDER BY created_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get log statistics
     */
    public function getLogStatistics(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_logs';

        $stats = $wpdb->get_results(
            "SELECT level, COUNT(*) as count FROM $table GROUP BY level",
            ARRAY_A
        );

        $result = [
            'total' => 0,
            'debug' => 0,
            'info' => 0,
            'warning' => 0,
            'error' => 0,
            'critical' => 0,
        ];

        foreach ($stats as $stat) {
            $level = $stat['level'];
            $count = (int) $stat['count'];
            $result[$level] = $count;
            $result['total'] += $count;
        }

        return $result;
    }

    /**
     * Clear old logs
     */
    public function clearOldLogs(int $days = 30): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_logs';

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );

        return $result ?: 0;
    }

    /**
     * Add log entry
     */
    public function addLog(string $level, string $message, array $context = [], ?int $projectId = null): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_logs';

        $data = [
            'level' => sanitize_text_field($level),
            'message' => sanitize_textarea_field($message),
            'context' => json_encode($context),
            'tooth_id' => $projectId,
            'created_at' => current_time('mysql'),
        ];

        $result = $wpdb->insert($table, $data);

        return $result !== false;
    }

    /**
     * Get log by ID
     */
    public function getLog(int $logId): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_logs';

        $result = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $logId),
            ARRAY_A
        );

        return $result ?: null;
    }

    /**
     * Delete log by ID
     */
    public function deleteLog(int $logId): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_logs';

        $result = $wpdb->delete($table, ['id' => $logId]);

        return $result !== false;
    }

    /**
     * Search logs
     */
    public function searchLogs(string $search, int $page = 1, int $perPage = 20): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_logs';

        $offset = ($page - 1) * $perPage;
        $searchTerm = '%' . $wpdb->esc_like($search) . '%';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE message LIKE %s OR context LIKE %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $searchTerm,
                $searchTerm,
                $perPage,
                $offset
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get logs by date range
     */
    public function getLogsByDateRange(string $startDate, string $endDate, int $page = 1, int $perPage = 20): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_logs';

        $offset = ($page - 1) * $perPage;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE created_at BETWEEN %s AND %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $startDate,
                $endDate,
                $perPage,
                $offset
            ),
            ARRAY_A
        );

        return $results ?: [];
    }
}