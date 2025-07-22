<?php

namespace CrawlFlow\Admin;

/**
 * Dashboard Renderer for CrawlFlow
 * Handles rendering of dashboard components and templates
 */
class DashboardRenderer
{
    /**
     * @var DashboardService
     */
    private $dashboardService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->dashboardService = new DashboardService();
    }

    /**
     * Render dashboard overview
     */
    public function renderDashboardOverview(array $data): void
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($data['title'] ?? 'CrawlFlow Dashboard'); ?></h1>

            <div class="crawlflow-overview-cards">
                <div class="crawlflow-card">
                    <h3>Projects</h3>
                    <div class="card-content">
                        <div class="stat-item">
                            <span class="stat-label">Total Projects:</span>
                            <span class="stat-value"><?php echo esc_html($data['total_projects'] ?? 0); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Active Projects:</span>
                            <span class="stat-value"><?php echo esc_html($data['active_projects'] ?? 0); ?></span>
                        </div>
                    </div>
                </div>

                <div class="crawlflow-card">
                    <h3>URLs</h3>
                    <div class="card-content">
                        <div class="stat-item">
                            <span class="stat-label">Processed:</span>
                            <span class="stat-value"><?php echo esc_html($data['total_urls_processed'] ?? 0); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Pending:</span>
                            <span class="stat-value"><?php echo esc_html($data['total_urls_pending'] ?? 0); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Failed:</span>
                            <span class="stat-value"><?php echo esc_html($data['total_urls_failed'] ?? 0); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Skipped:</span>
                            <span class="stat-value"><?php echo esc_html($data['total_urls_skipped'] ?? 0); ?></span>
                        </div>
                    </div>
                </div>

                <div class="crawlflow-card">
                    <h3>System</h3>
                    <div class="card-content">
                        <div class="stat-item">
                            <span class="stat-label">Total Logs:</span>
                            <span class="stat-value"><?php echo esc_html($data['total_logs'] ?? 0); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <?php $this->renderSystemStatus($data['system_status'] ?? []); ?>
            <?php $this->renderRecentProjects($data['recent_projects'] ?? []); ?>
        </div>
        <?php
    }

    /**
     * Render system status
     */
    private function renderSystemStatus(array $systemStatus): void
    {
        ?>
        <div class="crawlflow-system-status">
            <h2>System Status</h2>
            <div class="status-grid">
                <?php if (isset($systemStatus['database'])): ?>
                <div class="status-item">
                    <span class="status-label">Database:</span>
                    <span class="status-value status-<?php echo esc_attr($systemStatus['database']['status'] ?? 'unknown'); ?>">
                        <?php echo esc_html($systemStatus['database']['message'] ?? 'Unknown'); ?>
                    </span>
                </div>
                <?php endif; ?>

                <?php if (isset($systemStatus['disk_space'])): ?>
                <div class="status-item">
                    <span class="status-label">Disk Space:</span>
                    <span class="status-value status-<?php echo esc_attr($systemStatus['disk_space']['status'] ?? 'unknown'); ?>">
                        <?php echo esc_html($systemStatus['disk_space']['usage_percentage'] ?? 0); ?>% used
                    </span>
                </div>
                <?php endif; ?>

                <?php if (isset($systemStatus['memory_usage'])): ?>
                <div class="status-item">
                    <span class="status-label">Memory Usage:</span>
                    <span class="status-value status-<?php echo esc_attr($systemStatus['memory_usage']['status'] ?? 'unknown'); ?>">
                        <?php echo esc_html($systemStatus['memory_usage']['usage_percentage'] ?? 0); ?>% used
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render recent projects
     */
    private function renderRecentProjects(array $projects): void
    {
        if (empty($projects)) {
            return;
        }
        ?>
        <div class="crawlflow-recent-projects">
            <h2>Recent Projects</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                    <tr>
                        <td><?php echo esc_html($project['name'] ?? ''); ?></td>
                        <td><?php echo esc_html($project['tooth_type'] ?? ''); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($project['status'] ?? 'unknown'); ?>">
                                <?php echo esc_html(ucfirst($project['status'] ?? 'unknown')); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($project['created_at'] ?? ''); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=crawlflow-project-editor&project_id=' . ($project['id'] ?? '')); ?>" class="button button-small">
                                Edit
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render project editor
     */
    public function renderProjectEditor(array $data): void
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($data['title'] ?? 'Project Editor'); ?></h1>

            <form method="post" action="" class="crawlflow-project-form">
                <?php wp_nonce_field('crawlflow_project_nonce', 'project_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="project_name">Project Name</label>
                        </th>
                        <td>
                            <input type="text" id="project_name" name="project_name"
                                   value="<?php echo esc_attr($data['project']['name'] ?? ''); ?>"
                                   class="regular-text" required>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="project_description">Description</label>
                        </th>
                        <td>
                            <textarea id="project_description" name="project_description"
                                      rows="3" class="large-text"><?php echo esc_textarea($data['project']['description'] ?? ''); ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="project_type">Project Type</label>
                        </th>
                        <td>
                            <select id="project_type" name="project_type" required>
                                <option value="">Select Type</option>
                                <?php foreach ($data['available_tooths'] as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"
                                        <?php selected(($data['project']['tooth_type'] ?? ''), $key); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="project_status">Status</label>
                        </th>
                        <td>
                            <select id="project_status" name="project_status">
                                <option value="active" <?php selected(($data['project']['status'] ?? ''), 'active'); ?>>Active</option>
                                <option value="inactive" <?php selected(($data['project']['status'] ?? ''), 'inactive'); ?>>Inactive</option>
                                <option value="paused" <?php selected(($data['project']['status'] ?? ''), 'paused'); ?>>Paused</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary"
                           value="<?php echo $data['is_edit'] ? 'Update Project' : 'Create Project'; ?>">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render settings page
     */
    public function renderSettings(array $data): void
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($data['title'] ?? 'CrawlFlow Settings'); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('crawlflow_settings'); ?>
                <?php do_settings_sections('crawlflow_settings'); ?>

                <h2>General Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="max_concurrent_projects">Max Concurrent Projects</label>
                        </th>
                        <td>
                            <input type="number" id="max_concurrent_projects" name="crawlflow_general_settings[max_concurrent_projects]"
                                   value="<?php echo esc_attr($data['settings']['general']['max_concurrent_projects'] ?? 5); ?>"
                                   min="1" max="20" class="small-text">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="log_retention_days">Log Retention (Days)</label>
                        </th>
                        <td>
                            <input type="number" id="log_retention_days" name="crawlflow_general_settings[log_retention_days]"
                                   value="<?php echo esc_attr($data['settings']['general']['log_retention_days'] ?? 30); ?>"
                                   min="1" max="365" class="small-text">
                        </td>
                    </tr>
                </table>

                <h2>System Information</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">PHP Version</th>
                        <td><?php echo esc_html($data['system_info']['php_version'] ?? ''); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">WordPress Version</th>
                        <td><?php echo esc_html($data['system_info']['wordpress_version'] ?? ''); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">MySQL Version</th>
                        <td><?php echo esc_html($data['system_info']['mysql_version'] ?? ''); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Memory Limit</th>
                        <td><?php echo esc_html($data['system_info']['memory_limit'] ?? ''); ?></td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Settings">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render logs page
     */
    public function renderLogs(array $data): void
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($data['title'] ?? 'System Logs'); ?></h1>

            <div class="crawlflow-logs-controls">
                <form method="get" class="crawlflow-logs-filter">
                    <input type="hidden" name="page" value="crawlflow-logs">
                    <select name="level">
                        <option value="">All Levels</option>
                        <option value="debug">Debug</option>
                        <option value="info">Info</option>
                        <option value="warning">Warning</option>
                        <option value="error">Error</option>
                        <option value="critical">Critical</option>
                    </select>
                    <input type="submit" value="Filter" class="button">
                </form>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
                    <?php wp_nonce_field('crawlflow_admin_nonce', 'nonce'); ?>
                    <input type="hidden" name="action" value="crawlflow_clear_logs">
                    <input type="number" name="days" value="30" min="1" max="365" style="width: 60px;">
                    <input type="submit" value="Clear Old Logs" class="button">
                </form>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Level</th>
                        <th>Message</th>
                        <th>Project</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['logs'] as $log): ?>
                    <tr>
                        <td><?php echo esc_html($log['created_at'] ?? ''); ?></td>
                        <td>
                            <span class="log-level log-level-<?php echo esc_attr($log['level'] ?? 'info'); ?>">
                                <?php echo esc_html(ucfirst($log['level'] ?? 'info')); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($log['message'] ?? ''); ?></td>
                        <td><?php echo esc_html($log['tooth_id'] ?? 'System'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php $this->renderPagination($data['pagination'] ?? []); ?>
        </div>
        <?php
    }

        /**
     * Render pagination
     */
    private function renderPagination(array $pagination): void
    {
        if (empty($pagination) || $pagination['total_pages'] <= 1) {
            return;
        }

        $current_page = $pagination['current_page'];
        $total_pages = $pagination['total_pages'];

        echo '<div class="tablenav">';
        echo '<div class="tablenav-pages">';

        if ($current_page > 1) {
            echo '<a href="' . add_query_arg('paged', $current_page - 1) . '" class="prev page-numbers">&laquo;</a>';
        }

        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i == $current_page) {
                echo '<span class="page-numbers current">' . $i . '</span>';
            } else {
                echo '<a href="' . add_query_arg('paged', $i) . '" class="page-numbers">' . $i . '</a>';
            }
        }

        if ($current_page < $total_pages) {
            echo '<a href="' . add_query_arg('paged', $current_page + 1) . '" class="next page-numbers">&raquo;</a>';
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Render projects list
     */
    public function renderProjectsList(array $projects): void
    {
        ?>
        <div class="wrap">
            <h1>Projects</h1>

            <div class="crawlflow-projects-controls">
                <a href="<?php echo admin_url('admin.php?page=crawlflow-project-editor'); ?>" class="button button-primary">
                    Add New Project
                </a>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>URLs Processed</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                    <tr>
                        <td><?php echo esc_html($project['name'] ?? ''); ?></td>
                        <td><?php echo esc_html($project['tooth_type'] ?? ''); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($project['status'] ?? 'unknown'); ?>">
                                <?php echo esc_html(ucfirst($project['status'] ?? 'unknown')); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($project['urls_processed'] ?? 0); ?></td>
                        <td><?php echo esc_html($project['created_at'] ?? ''); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=crawlflow-project-editor&project_id=' . ($project['id'] ?? '')); ?>" class="button button-small">
                                Edit
                            </a>
                            <button class="button button-small button-link-delete" onclick="deleteProject(<?php echo $project['id'] ?? 0; ?>)">
                                Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render migration page
     */
    public function renderMigration(array $migrationStatus): void
    {
        ?>
        <div class="wrap">
            <h1>Database Migration</h1>

            <div class="crawlflow-migration-status">
                <h2>Migration Status</h2>

                <?php if (isset($migrationStatus['status'])): ?>
                <div class="migration-status-item">
                    <span class="status-label">Database Status:</span>
                    <span class="status-value status-<?php echo esc_attr($migrationStatus['status']); ?>">
                        <?php echo esc_html(ucfirst($migrationStatus['status'])); ?>
                    </span>
                </div>
                <?php endif; ?>

                <?php if (isset($migrationStatus['current_version'])): ?>
                <div class="migration-status-item">
                    <span class="status-label">Current Version:</span>
                    <span class="status-value"><?php echo esc_html($migrationStatus['current_version']); ?></span>
                </div>
                <?php endif; ?>

                <?php if (isset($migrationStatus['required_version'])): ?>
                <div class="migration-status-item">
                    <span class="status-label">Required Version:</span>
                    <span class="status-value"><?php echo esc_html($migrationStatus['required_version']); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <?php if (isset($migrationStatus['needs_migration']) && $migrationStatus['needs_migration']): ?>
            <div class="crawlflow-migration-actions">
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('crawlflow_admin_nonce', 'nonce'); ?>
                    <input type="hidden" name="action" value="crawlflow_run_migration">
                    <input type="submit" value="Run Migration" class="button button-primary">
                </form>
            </div>
            <?php else: ?>
            <div class="crawlflow-migration-success">
                <p>Database is up to date!</p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render analytics page
     */
    public function renderAnalytics(array $data): void
    {
        ?>
        <div class="wrap">
            <h1>Analytics</h1>

            <div class="crawlflow-analytics-controls">
                <form method="get" class="crawlflow-analytics-filter">
                    <input type="hidden" name="page" value="crawlflow-analytics">
                    <select name="period">
                        <option value="7days" <?php selected($data['period'], '7days'); ?>>Last 7 Days</option>
                        <option value="30days" <?php selected($data['period'], '30days'); ?>>Last 30 Days</option>
                        <option value="90days" <?php selected($data['period'], '90days'); ?>>Last 90 Days</option>
                    </select>
                    <input type="submit" value="Filter" class="button">
                </form>
            </div>

            <div class="crawlflow-analytics-charts">
                <div class="crawlflow-chart">
                    <h3>URLs Processed</h3>
                    <div class="chart-container">
                        <!-- Chart will be rendered here via JavaScript -->
                    </div>
                </div>

                <div class="crawlflow-chart">
                    <h3>Project Performance</h3>
                    <div class="chart-container">
                        <!-- Chart will be rendered here via JavaScript -->
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}