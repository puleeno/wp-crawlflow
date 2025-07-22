<?php

namespace CrawlFlow\Admin;

use Puleeno\Rake\WordPress\Adapter\WordPressDatabaseAdapter;

/**
 * Project Service for CrawlFlow
 * Manages project-related operations
 */
class ProjectService
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
     * Get total number of projects
     */
    public function getTotalProjects(): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_tooths';

        $result = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        return (int) $result;
    }

    /**
     * Get number of active projects
     */
    public function getActiveProjects(): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_tooths';

        $result = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'active'");
        return (int) $result;
    }

    /**
     * Get total URLs processed
     */
    public function getTotalUrlsProcessed(): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_urls';

        $result = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'done'");
        return (int) $result;
    }

    /**
     * Get total URLs pending
     */
    public function getTotalUrlsPending(): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_urls';

        $result = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending'");
        return (int) $result;
    }

    /**
     * Get total URLs skipped
     */
    public function getTotalUrlsSkipped(): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_urls';

        $result = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE skipped = 1");
        return (int) $result;
    }

    /**
     * Get total URLs failed
     */
    public function getTotalUrlsFailed(): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_urls';

        $result = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'failed'");
        return (int) $result;
    }

    /**
     * Get recent projects
     */
    public function getRecentProjects(int $limit = 5): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_tooths';

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
     * Get project by ID
     */
    public function getProject(int $projectId): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_tooths';

        $result = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $projectId),
            ARRAY_A
        );

        return $result ?: null;
    }

    /**
     * Get projects with pagination
     */
    public function getProjects(int $page = 1, int $perPage = 10): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_tooths';

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
     * Get available tooths
     */
    public function getAvailableTooths(): array
    {
        // This would typically come from a configuration or database
        return [
            [
                'id' => 'basic_crawler',
                'name' => 'Basic Web Crawler'
            ],
            [
                'id' => 'rss_reader',
                'name' => 'RSS Feed Reader'
            ],
            [
                'id' => 'sitemap_parser',
                'name' => 'Sitemap Parser'
            ],
            [
                'id' => 'api_crawler',
                'name' => 'API Crawler'
            ],
        ];
    }

    /**
     * Get data sources
     */
    public function getDataSources(): array
    {
        // This would typically come from a configuration or database
        return [
            'urls' => 'URL List',
            'rss' => 'RSS Feed',
            'sitemap' => 'XML Sitemap',
            'api' => 'API Endpoint',
        ];
    }

    /**
     * Save project
     */
    public function saveProject(array $projectData): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_tooths';

        $data = [
            'name' => sanitize_text_field($projectData['name'] ?? ''),
            'description' => sanitize_textarea_field($projectData['description'] ?? ''),
            'tooth_type' => sanitize_text_field($projectData['tooth_type'] ?? ''),
            'status' => sanitize_text_field($projectData['status'] ?? 'active'),
            'config' => json_encode($projectData['config'] ?? []),
            'updated_at' => current_time('mysql'),
        ];

        if (isset($projectData['id'])) {
            // Update existing project
            $result = $wpdb->update(
                $table,
                $data,
                ['id' => (int) $projectData['id']]
            );
        } else {
            // Create new project
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert($table, $data);
        }

        return $result !== false;
    }

    /**
     * Create new project
     */
    public function createProject(array $projectData): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_tooths';

        $data = [
            'name' => sanitize_text_field($projectData['name'] ?? ''),
            'description' => sanitize_textarea_field($projectData['description'] ?? ''),
            'tooth_type' => sanitize_text_field($projectData['tooth_type'] ?? ''),
            'base_url' => esc_url_raw($projectData['base_url'] ?? ''),
            'max_urls' => (int) ($projectData['max_urls'] ?? 1000),
            'status' => sanitize_text_field($projectData['status'] ?? 'draft'),
            'config' => json_encode($projectData['config'] ?? []),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        $result = $wpdb->insert($table, $data);

        if ($result === false) {
            return 0;
        }

        return $wpdb->insert_id;
    }

    /**
     * Update existing project
     */
    public function updateProject(int $projectId, array $projectData): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_tooths';

        $data = [
            'name' => sanitize_text_field($projectData['name'] ?? ''),
            'description' => sanitize_textarea_field($projectData['description'] ?? ''),
            'tooth_type' => sanitize_text_field($projectData['tooth_type'] ?? ''),
            'base_url' => esc_url_raw($projectData['base_url'] ?? ''),
            'max_urls' => (int) ($projectData['max_urls'] ?? 1000),
            'status' => sanitize_text_field($projectData['status'] ?? 'draft'),
            'config' => json_encode($projectData['config'] ?? []),
            'updated_at' => current_time('mysql'),
        ];

        $result = $wpdb->update(
            $table,
            $data,
            ['id' => $projectId]
        );

        return $result !== false;
    }

    /**
     * Delete project
     */
    public function deleteProject(int $projectId): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_tooths';

        $result = $wpdb->delete(
            $table,
            ['id' => $projectId],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Get URLs processed chart data
     */
    public function getUrlsProcessedChart(string $period = '7days'): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_urls';

        $dateFormat = $period === '7days' ? '%Y-%m-%d' : '%Y-%m';
        $daysBack = $period === '7days' ? 7 : 30;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE_FORMAT(crawled_at, %s) as date, COUNT(*) as count
                 FROM $table
                 WHERE crawled_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                 AND status = 'done'
                 GROUP BY DATE_FORMAT(crawled_at, %s)
                 ORDER BY date",
                $dateFormat,
                $daysBack,
                $dateFormat
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get projects performance data
     */
    public function getProjectsPerformance(string $period = '7days'): array
    {
        global $wpdb;
        $toothsTable = $wpdb->prefix . 'rake_tooths';
        $urlsTable = $wpdb->prefix . 'rake_urls';

        $daysBack = $period === '7days' ? 7 : 30;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.name,
                        COUNT(u.id) as total_urls,
                        SUM(CASE WHEN u.status = 'done' THEN 1 ELSE 0 END) as processed_urls,
                        SUM(CASE WHEN u.status = 'failed' THEN 1 ELSE 0 END) as failed_urls,
                        SUM(CASE WHEN u.skipped = 1 THEN 1 ELSE 0 END) as skipped_urls
                 FROM $toothsTable t
                 LEFT JOIN $urlsTable u ON t.id = u.tooth_id
                 WHERE u.crawled_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                 GROUP BY t.id, t.name
                 ORDER BY processed_urls DESC",
                $daysBack
            ),
            ARRAY_A
        );

        return $results ?: [];
    }
}