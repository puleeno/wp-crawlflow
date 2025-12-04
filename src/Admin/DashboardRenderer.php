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
     * @var RegistryService
     */
    private $registryService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->dashboardService = new DashboardService();
        $this->registryService = new RegistryService();
        
        // Enqueue scripts and localize data
        add_action('admin_enqueue_scripts', [$this, 'enqueueScriptsAndData']);
    }

    /**
     * Enqueue scripts and localize registry data
     */
    public function enqueueScriptsAndData($hook): void
    {
        // Only on CrawlFlow pages
        if (strpos($hook, 'crawlflow') === false) {
            return;
        }

        // Enqueue React UI script (read from Vite manifest)
        $plugin_dir = plugin_dir_path(dirname(dirname(__FILE__)));
        $manifest_path = $plugin_dir . 'assets/js/crawflow-ui/dist/.vite/manifest.json';
        
        $script_file = 'crawflow-ui.Dk2KSQ2S.js'; // default fallback
        if (file_exists($manifest_path)) {
            $manifest = json_decode(file_get_contents($manifest_path), true);
            if (isset($manifest['index.html']['file'])) {
                $script_file = $manifest['index.html']['file'];
            }
        }
        
        $script_url = plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/crawflow-ui/dist/' . $script_file;
        $script_path = $plugin_dir . 'assets/js/crawflow-ui/dist/' . $script_file;
        
        wp_enqueue_script(
            'crawlflow-ui',
            $script_url,
            ['wp-element'],
            file_exists($script_path) ? filemtime($script_path) : '1.0.0',
            true
        );

        // Get registry data from managers
        $registryData = $this->registryService->getAllRegistryData();

        // Localize for React UI (must be AFTER wp_enqueue_script)
        wp_localize_script('crawlflow-ui', 'crawlflowRegistry', [
            'dataSources' => $registryData['dataSources'],
            'processors' => $registryData['processors'],
            'parsers' => $registryData['parsers'],
            'httpClients' => $registryData['httpClients'],
            'nonce' => wp_create_nonce('crawlflow_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
    }

    /**
     * Render dashboard overview
     */
    public function renderDashboardOverview(array $data): void
    {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
        ?>
        <div class="wrap crawlflow-dashboard-wrap">
            <div class="crawlflow-dashboard-header">
                <h1 class="crawlflow-dashboard-title"><?php echo esc_html($data['title'] ?? 'CrawlFlow Dashboard'); ?></h1>
            </div>

            <?php $this->renderAlertMessages(); ?>

            <!-- Tabs Navigation -->
            <nav class="crawlflow-tabs-nav">
                <a href="<?php echo admin_url('admin.php?page=crawlflow&tab=overview'); ?>" 
                   class="crawlflow-tab <?php echo $current_tab === 'overview' ? 'active' : ''; ?>">
                    Overview
                </a>
                <a href="<?php echo admin_url('admin.php?page=crawlflow&tab=system-status'); ?>" 
                   class="crawlflow-tab <?php echo $current_tab === 'system-status' ? 'active' : ''; ?>">
                    System Status
                </a>
                <a href="<?php echo admin_url('admin.php?page=crawlflow&tab=settings'); ?>" 
                   class="crawlflow-tab <?php echo $current_tab === 'settings' ? 'active' : ''; ?>">
                    Settings
                </a>
                <a href="<?php echo admin_url('admin.php?page=crawlflow&tab=migration'); ?>" 
                   class="crawlflow-tab <?php echo $current_tab === 'migration' ? 'active' : ''; ?>">
                    Migration
                </a>
            </nav>

            <!-- Tab Content -->
            <div class="crawlflow-tab-content">
                <?php if ($current_tab === 'overview'): ?>
                    <?php $this->renderOverviewTab($data); ?>
                <?php elseif ($current_tab === 'system-status'): ?>
                    <?php $this->renderSystemStatusTab($data); ?>
                <?php elseif ($current_tab === 'settings'): ?>
                    <?php $this->renderSettingsTab($data); ?>
                <?php elseif ($current_tab === 'migration'): ?>
                    <?php $this->renderMigrationTab($data); ?>
                <?php else: ?>
                    <?php $this->renderOverviewTab($data); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Overview Tab
     */
    private function renderOverviewTab(array $data): void
    {
        ?>
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

            <?php $this->renderRecentProjects($data['recent_projects'] ?? []); ?>
        </div>
        <?php
    }

    /**
     * Render System Status Tab
     */
    private function renderSystemStatusTab(array $data): void
    {
        $systemStatus = $data['system_status'] ?? [];
        $systemInfo = $data['system_info'] ?? [];
        ?>
        <div class="crawlflow-system-status-tab">
            <?php $this->renderSystemStatus($systemStatus); ?>
            
            <div class="crawlflow-system-info-section">
                <div class="crawlflow-section-header">
                    <h2>System Info</h2>
                    <button type="button" class="button crawlflow-copy-system-info" onclick="crawlflowCopySystemInfo()">
                        Copy System Info to Clipboard
                    </button>
                </div>
                
                <div class="crawlflow-collapsible-sections" id="crawlflow-system-info">
                    <?php $this->renderCollapsibleSection('CrawlFlow', $this->getCrawlFlowInfo($data)); ?>
                    <?php $this->renderCollapsibleSection('WordPress', $this->getWordPressInfo($systemInfo)); ?>
                    <?php $this->renderCollapsibleSection('Server', $this->getServerInfo($systemInfo)); ?>
                    <?php $this->renderCollapsibleSection('Database', $this->getDatabaseInfo($systemInfo, $systemStatus)); ?>
                </div>
            </div>
        </div>
        <script>
        function crawlflowToggleSection(sectionId) {
            const content = document.getElementById(sectionId);
            const arrow = document.getElementById('arrow-' + sectionId);
            
            if (content.style.display === 'none') {
                content.style.display = 'block';
                arrow.textContent = '▲';
            } else {
                content.style.display = 'none';
                arrow.textContent = '▼';
            }
        }

        function crawlflowCopySystemInfo() {
            const sections = document.querySelectorAll('#crawlflow-system-info .crawlflow-collapsible-section');
            let text = '=== CrawlFlow System Info ===\n\n';
            
            sections.forEach(function(section) {
                const title = section.querySelector('.crawlflow-section-title').textContent;
                const rows = section.querySelectorAll('.crawlflow-info-table tr');
                
                text += '[' + title + ']\n';
                rows.forEach(function(row) {
                    const key = row.querySelector('.crawlflow-info-key').textContent;
                    const value = row.querySelector('.crawlflow-info-value').textContent;
                    text += key + ' ' + value + '\n';
                });
                text += '\n';
            });
            
            // Copy to clipboard
            navigator.clipboard.writeText(text).then(function() {
                alert('System info copied to clipboard!');
            }, function() {
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('System info copied to clipboard!');
            });
        }
        </script>
        <?php
    }

    /**
     * Render Settings Tab
     */
    private function renderSettingsTab(array $data): void
    {
        $this->renderSettingsSection($data['settings'] ?? [], $data['system_info'] ?? []);
    }

    /**
     * Render Migration Tab
     */
    private function renderMigrationTab(array $data): void
    {
        $this->renderMigrationSection($data['migration_status'] ?? []);
    }

    /**
     * Render alert messages
     */
    private function renderAlertMessages(): void
    {
        // Check for migration result
        if (isset($_GET['migration_result'])) {
            $result = $_GET['migration_result'];
            $message = '';
            $type = '';

            switch ($result) {
                case 'success':
                    $message = 'Migration completed successfully!';
                    $type = 'success';
                    break;
                case 'error':
                    $message = 'Migration failed. Please check the logs for details.';
                    $type = 'error';
                    break;
                default:
                    return;
            }

            if ($message && $type) {
                ?>
                <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
                <?php
            }
        }

        // Check for settings update
        if (isset($_GET['updated']) && $_GET['updated'] === '1') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>Settings updated successfully!</p>
            </div>
            <?php
        }

        // Check for project save result
        if (isset($_GET['project_saved'])) {
            $result = $_GET['project_saved'];
            $message = '';
            $type = '';

            switch ($result) {
                case 'success':
                    $message = 'Project saved successfully!';
                    $type = 'success';
                    break;
                case 'error':
                    $message = 'Failed to save project. Please try again.';
                    $type = 'error';
                    break;
                default:
                    return;
            }

            if ($message && $type) {
                ?>
                <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
                <?php
            }
        }

        // Check for project delete result
        if (isset($_GET['project_deleted'])) {
            $result = $_GET['project_deleted'];
            $message = '';
            $type = '';

            switch ($result) {
                case 'success':
                    $message = 'Project deleted successfully!';
                    $type = 'success';
                    break;
                case 'error':
                    $message = 'Failed to delete project. Please try again.';
                    $type = 'error';
                    break;
                default:
                    return;
            }

            if ($message && $type) {
                ?>
                <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
                <?php
            }
        }

        // Check for logs clear result
        if (isset($_GET['logs_cleared'])) {
            $result = $_GET['logs_cleared'];
            $message = '';
            $type = '';

            switch ($result) {
                case 'success':
                    $message = 'Logs cleared successfully!';
                    $type = 'success';
                    break;
                case 'error':
                    $message = 'Failed to clear logs. Please try again.';
                    $type = 'error';
                    break;
                default:
                    return;
            }

            if ($message && $type) {
                ?>
                <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
                <?php
            }
        }
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
                        <?php 
                        $diskUsage = $systemStatus['disk_space']['usage_percentage'] ?? 0;
                        echo esc_html(number_format($diskUsage, 1)) . '% used';
                        if (isset($systemStatus['disk_space']['free_space'])) {
                            echo ' (' . esc_html($systemStatus['disk_space']['free_space']) . ' free)';
                        }
                        ?>
                    </span>
                </div>
                <?php endif; ?>

                <?php if (isset($systemStatus['memory_usage'])): ?>
                <div class="status-item">
                    <span class="status-label">Memory Usage:</span>
                    <span class="status-value status-<?php echo esc_attr($systemStatus['memory_usage']['status'] ?? 'unknown'); ?>">
                        <?php 
                        $memUsage = $systemStatus['memory_usage']['usage_percentage'] ?? 0;
                        echo esc_html(number_format($memUsage, 1)) . '% used';
                        if (isset($systemStatus['memory_usage']['current_usage'])) {
                            echo ' (' . esc_html($systemStatus['memory_usage']['current_usage']) . ')';
                        }
                        ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if (empty($systemStatus)): ?>
                <div class="status-item crawlflow-empty-status">
                    <span class="status-label">Status:</span>
                    <span class="status-value crawlflow-empty-message">No status information available</span>
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
                            <a href="<?php echo admin_url('admin.php?page=crawlflow-projects&sub=compose&project_id=' . ($project['id'] ?? '')); ?>" class="button button-small">
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
     * Render settings section in dashboard
     */
    private function renderSettingsSection(array $settings, array $systemInfo): void
    {
        ?>
        <div class="crawlflow-settings-section">
            <h2>Settings & System Information</h2>

            <div class="crawlflow-settings-grid">
                <div class="crawlflow-settings-card">
                    <h3>General Settings</h3>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="crawlflow-settings-form">
                        <?php wp_nonce_field('crawlflow_admin_nonce', 'nonce'); ?>
                        <input type="hidden" name="action" value="crawlflow_save_settings">

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="max_concurrent_projects">Max Concurrent Projects</label>
                                </th>
                                <td>
                                    <input type="number" id="max_concurrent_projects" name="crawlflow_general_settings[max_concurrent_projects]"
                                           value="<?php echo esc_attr($settings['general']['max_concurrent_projects'] ?? 5); ?>"
                                           min="1" max="20" class="small-text">
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="log_retention_days">Log Retention (Days)</label>
                                </th>
                                <td>
                                    <input type="number" id="log_retention_days" name="crawlflow_general_settings[log_retention_days]"
                                           value="<?php echo esc_attr($settings['general']['log_retention_days'] ?? 30); ?>"
                                           min="1" max="365" class="small-text">
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Settings">
                        </p>
                    </form>
                </div>

                <div class="crawlflow-settings-card">
                    <h3>System Information</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">PHP Version</th>
                            <td><?php 
                                $phpVersion = $systemInfo['php_version'] ?? '';
                                if (empty($phpVersion) && defined('PHP_VERSION')) {
                                    $phpVersion = PHP_VERSION;
                                }
                                echo esc_html($phpVersion ?: 'Unknown'); 
                            ?></td>
                        </tr>
                        <tr>
                            <th scope="row">WordPress Version</th>
                            <td><?php 
                                $wpVersion = $systemInfo['wordpress_version'] ?? '';
                                if (empty($wpVersion)) {
                                    $wpVersion = get_bloginfo('version') ?: (defined('WP_VERSION') ? WP_VERSION : 'Unknown');
                                }
                                echo esc_html($wpVersion ?: 'Unknown'); 
                            ?></td>
                        </tr>
                        <tr>
                            <th scope="row">MySQL Version</th>
                            <td><?php 
                                global $wpdb;
                                $mysqlVersion = $systemInfo['mysql_version'] ?? '';
                                if (empty($mysqlVersion) && isset($wpdb)) {
                                    try {
                                        $mysqlVersion = $wpdb->db_version() ?: 'Unknown';
                                    } catch (\Exception $e) {
                                        $mysqlVersion = 'Unknown';
                                    }
                                }
                                echo esc_html($mysqlVersion ?: 'Unknown'); 
                            ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Memory Limit</th>
                            <td><?php 
                                $memoryLimit = $systemInfo['memory_limit'] ?? '';
                                if (empty($memoryLimit)) {
                                    $memoryLimit = ini_get('memory_limit') ?: 'Unknown';
                                }
                                echo esc_html($memoryLimit ?: 'Unknown'); 
                            ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render migration section in dashboard
     */
    private function renderMigrationSection(array $migrationStatus): void
    {
        ?>
        <div class="crawlflow-migration-section">
            <h2>Database Migration</h2>
            <div class="crawlflow-migration-status">
                <h3>Migration Status</h3>
                <?php if (!empty($migrationStatus)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Table</th>
                                <th>Current Version</th>
                                <th>Required Version</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($migrationStatus as $table => $status): ?>
                            <tr>
                                <td><?php echo esc_html($table); ?></td>
                                <td><?php echo esc_html($status['current_version']); ?></td>
                                <td><?php echo esc_html($status['required_version']); ?></td>
                                <td>
                                    <?php if ($status['needs_migration']): ?>
                                        <span class="status-badge status-warning">Needs Migration</span>
                                    <?php else: ?>
                                        <span class="status-badge status-active">Up to Date</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No migration status available.</p>
                <?php endif; ?>
            </div>
            <div class="crawlflow-migration-actions" style="margin-top:20px;">
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('crawlflow_admin_nonce', 'nonce'); ?>
                    <input type="hidden" name="action" value="crawlflow_run_migration">
                    <input type="submit" value="Run Migration" class="button button-primary">
                </form>
            </div>
            <div class="crawlflow-migration-info" style="margin-top:20px;">
                <h3>Migration Information</h3>
                <p>Migration status is managed through the <code>rake_configs</code> table. Version tracking uses keys with pattern <code>table_version_{table_name}</code> and migration history uses <code>migration_history_{table_name}_{timestamp}</code>.</p>
            </div>
        </div>
        <?php
    }

    /**
     * Render projects list
     */
    public function renderProjectsList(array $projects): void
    {
        ?>
        <div class="wrap">
            <h1>Projects</h1>

            <?php $this->renderAlertMessages(); ?>

            <?php if (empty($projects)): ?>
                <!-- Empty State -->
                <div class="crawlflow-empty-state" style="text-align: center; padding: 60px 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; margin-top: 20px;">
                    <div style="max-width: 500px; margin: 0 auto;">
                        <div style="font-size: 64px; color: #ddd; margin-bottom: 30px; line-height: 1; display: block;">
                            📋
                        </div>
                        <h2 style="font-size: 24px; color: #23282d; margin: 0 0 15px 0; font-weight: 400; line-height: 1.4;">
                            No projects yet
                        </h2>
                        <p style="color: #666; font-size: 14px; margin: 0 0 30px 0; line-height: 1.6;">
                            Get started by creating your first crawler project. Use the visual flow editor to build powerful web scraping workflows.
                        </p>
                        <a href="<?php echo admin_url('admin.php?page=crawlflow-projects&sub=compose&editor=flow'); ?>" class="button button-primary button-hero" style="font-size: 14px; padding: 8px 16px; height: auto;">
                            Create Your First Project
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="crawlflow-projects-controls">
                    <a href="<?php echo admin_url('admin.php?page=crawlflow-projects&sub=compose&editor=flow'); ?>" class="button button-primary">
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
                        <?php foreach ($projects as $project): 
                            // Get tooth_type from config JSON if not directly available
                            $toothType = $project['tooth_type'] ?? '';
                            if (empty($toothType) && !empty($project['config'])) {
                                $config = json_decode($project['config'], true);
                                if (is_array($config) && isset($config['tooth_type'])) {
                                    $toothType = $config['tooth_type'];
                                } elseif (is_array($config) && isset($config['projectSettings']['tooth_type'])) {
                                    $toothType = $config['projectSettings']['tooth_type'];
                                }
                            }
                            if (empty($toothType)) {
                                $toothType = 'basic_crawler'; // Default
                            }
                            
                            // Format tooth_type for display
                            $toothTypeDisplay = ucwords(str_replace(['_', '-'], ' ', $toothType));
                            
                            $editUrl = admin_url('admin.php?page=crawlflow-projects&sub=compose&project_id=' . ($project['id'] ?? '') . '&editor=flow');
                        ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url($editUrl); ?>" style="text-decoration: none; font-weight: 500; color: #2271b1;">
                                    <?php echo esc_html($project['name'] ?? 'Untitled Project'); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($toothTypeDisplay); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($project['status'] ?? 'unknown'); ?>">
                                    <?php echo esc_html(ucfirst($project['status'] ?? 'unknown')); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($project['urls_processed'] ?? 0); ?></td>
                            <td><?php echo esc_html($project['created_at'] ?? ''); ?></td>
                            <td>
                                <a href="<?php echo esc_url($editUrl); ?>" class="button button-small">
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
            <?php endif; ?>
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
     * Render project compose page (sub screen)
     */
    public function renderProjectCompose(array $data): void
    {
        $project = $data['project'] ?? [];
        $isEdit = $data['is_edit'] ?? false;
        $availableTooths = $data['available_tooths'] ?? [];

        // Ensure project is an array
        if (!is_array($project)) {
            $project = [];
        }

        // Ensure availableTooths is an array
        if (!is_array($availableTooths)) {
            $availableTooths = [];
        }
        ?>
        <div class="wrap">
            <div class="crawlflow-project-compose-header">
                <h1><?php echo $isEdit ? 'Edit Project' : 'Create New Project'; ?></h1>
                <a href="<?php echo admin_url('admin.php?page=crawlflow-projects'); ?>" class="button">
                    ← Back to Projects
                </a>
            </div>

            <?php $this->renderAlertMessages(); ?>

            <div class="crawlflow-project-compose-form">
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="project-compose-form">
                    <?php wp_nonce_field('crawlflow_admin_nonce', 'nonce'); ?>
                    <input type="hidden" name="action" value="crawlflow_save_project">

                    <?php if ($isEdit): ?>
                    <input type="hidden" name="project_id" value="<?php echo esc_attr($project['id'] ?? ''); ?>">
                    <?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="project_name">Project Name</label>
                            </th>
                            <td>
                                <input type="text" id="project_name" name="project_name"
                                       value="<?php echo esc_attr($project['name'] ?? ''); ?>"
                                       class="regular-text" required>
                                <p class="description">Enter a descriptive name for your project</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="project_description">Description</label>
                            </th>
                            <td>
                                <textarea id="project_description" name="project_description"
                                          rows="4" class="large-text"><?php echo esc_textarea($project['description'] ?? ''); ?></textarea>
                                <p class="description">Describe what this project will do</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="tooth_type">Tooth Type</label>
                            </th>
                            <td>
                                <select id="tooth_type" name="tooth_type" required>
                                    <option value="">Select a tooth type</option>
                                    <?php if (is_array($availableTooths)): ?>
                                        <?php foreach ($availableTooths as $tooth): ?>
                                            <?php if (is_array($tooth) && isset($tooth['id']) && isset($tooth['name'])): ?>
                                            <option value="<?php echo esc_attr($tooth['id']); ?>"
                                                    <?php selected(($project['tooth_type'] ?? ''), $tooth['id']); ?>>
                                                <?php echo esc_html($tooth['name']); ?>
                                            </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <p class="description">Choose the type of tooth for this project</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="base_url">Base URL</label>
                            </th>
                            <td>
                                <input type="url" id="base_url" name="base_url"
                                       value="<?php echo esc_attr($project['base_url'] ?? ''); ?>"
                                       class="regular-text" required>
                                <p class="description">The starting URL for your crawling project</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="max_urls">Max URLs to Process</label>
                            </th>
                            <td>
                                <input type="number" id="max_urls" name="max_urls"
                                       value="<?php echo esc_attr($project['max_urls'] ?? 1000); ?>"
                                       class="small-text" min="1" max="10000">
                                <p class="description">Maximum number of URLs to process (1-10,000)</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="status">Status</label>
                            </th>
                            <td>
                                <select id="status" name="status">
                                    <option value="draft" <?php selected(($project['status'] ?? ''), 'draft'); ?>>Draft</option>
                                    <option value="active" <?php selected(($project['status'] ?? ''), 'active'); ?>>Active</option>
                                    <option value="paused" <?php selected(($project['status'] ?? ''), 'paused'); ?>>Paused</option>
                                    <option value="completed" <?php selected(($project['status'] ?? ''), 'completed'); ?>>Completed</option>
                                </select>
                                <p class="description">Current status of the project</p>
                            </td>
                        </tr>
                    </table>

                    <div class="crawlflow-project-compose-actions">
                        <button type="submit" class="button button-primary">
                            <?php echo $isEdit ? 'Update Project' : 'Create Project'; ?>
                        </button>
                        <a href="<?php echo admin_url('admin.php?page=crawlflow-projects'); ?>" class="button">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Form validation
            $('#project-compose-form').on('submit', function(e) {
                var projectName = $('#project_name').val().trim();
                var toothType = $('#tooth_type').val();
                var baseUrl = $('#base_url').val().trim();

                if (!projectName) {
                    alert('Please enter a project name');
                    $('#project_name').focus();
                    e.preventDefault();
                    return false;
                }

                if (!toothType) {
                    alert('Please select a tooth type');
                    $('#tooth_type').focus();
                    e.preventDefault();
                    return false;
                }

                if (!baseUrl) {
                    alert('Please enter a base URL');
                    $('#base_url').focus();
                    e.preventDefault();
                    return false;
                }

                // Validate URL format
                try {
                    new URL(baseUrl);
                } catch (e) {
                    alert('Please enter a valid URL');
                    $('#base_url').focus();
                    e.preventDefault();
                    return false;
                }
            });

            // Auto-save draft functionality
            var autoSaveTimer;
            $('input, textarea, select').on('change', function() {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(function() {
                    // Auto-save draft functionality can be implemented here
                    console.log('Auto-save triggered');
                }, 2000);
            });
        });
        </script>
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

    /**
     * Render collapsible section
     */
    private function renderCollapsibleSection(string $title, array $data): void
    {
        $sectionId = 'crawlflow-section-' . sanitize_title($title);
        ?>
        <div class="crawlflow-collapsible-section">
            <div class="crawlflow-collapsible-header" onclick="crawlflowToggleSection('<?php echo esc_js($sectionId); ?>')">
                <h3 class="crawlflow-section-title"><?php echo esc_html($title); ?></h3>
                <span class="crawlflow-section-arrow" id="arrow-<?php echo esc_attr($sectionId); ?>">▼</span>
            </div>
            <div class="crawlflow-collapsible-content" id="<?php echo esc_attr($sectionId); ?>" style="display: none;">
                <table class="crawlflow-info-table">
                    <tbody>
                        <?php foreach ($data as $key => $value): ?>
                        <tr>
                            <td class="crawlflow-info-key"><?php echo esc_html($key); ?>:</td>
                            <td class="crawlflow-info-value"><?php echo esc_html($value); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Get CrawlFlow info
     */
    private function getCrawlFlowInfo(array $data): array
    {
        return [
            'Version' => defined('CRAWLFLOW_VERSION') ? CRAWLFLOW_VERSION : 'Unknown',
            'Total Projects' => $data['total_projects'] ?? 0,
            'Active Projects' => $data['active_projects'] ?? 0,
            'Total URLs Processed' => $data['total_urls_processed'] ?? 0,
        ];
    }

    /**
     * Get WordPress info
     */
    private function getWordPressInfo(array $systemInfo): array
    {
        $wpVersion = $systemInfo['wordpress_version'] ?? '';
        if (empty($wpVersion)) {
            $wpVersion = get_bloginfo('version') ?: (defined('WP_VERSION') ? WP_VERSION : 'Unknown');
        }

        return [
            'Version' => $wpVersion,
            'Site URL' => get_site_url(),
            'Home URL' => get_home_url(),
            'Multisite' => is_multisite() ? 'Yes' : 'No',
            'Language' => get_locale(),
        ];
    }

    /**
     * Get Server info
     */
    private function getServerInfo(array $systemInfo): array
    {
        $phpVersion = $systemInfo['php_version'] ?? '';
        if (empty($phpVersion) && defined('PHP_VERSION')) {
            $phpVersion = PHP_VERSION;
        }

        $memoryLimit = $systemInfo['memory_limit'] ?? '';
        if (empty($memoryLimit)) {
            $memoryLimit = ini_get('memory_limit') ?: 'Unknown';
        }

        return [
            'PHP Version' => $phpVersion ?: 'Unknown',
            'Memory Limit' => $memoryLimit ?: 'Unknown',
            'Max Execution Time' => $systemInfo['max_execution_time'] ?? ini_get('max_execution_time') ?: 'Unknown',
            'Upload Max Filesize' => $systemInfo['upload_max_filesize'] ?? ini_get('upload_max_filesize') ?: 'Unknown',
            'Post Max Size' => $systemInfo['post_max_size'] ?? ini_get('post_max_size') ?: 'Unknown',
            'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        ];
    }

    /**
     * Get Database info
     */
    private function getDatabaseInfo(array $systemInfo, array $systemStatus): array
    {
        global $wpdb;
        
        $mysqlVersion = $systemInfo['mysql_version'] ?? '';
        if (empty($mysqlVersion) && isset($wpdb)) {
            try {
                $mysqlVersion = $wpdb->db_version() ?: 'Unknown';
            } catch (\Exception $e) {
                $mysqlVersion = 'Unknown';
            }
        }

        $dbStatus = 'Unknown';
        if (isset($systemStatus['database'])) {
            $dbStatus = $systemStatus['database']['status'] === 'connected' ? 'Connected' : 'Error';
        }

        return [
            'MySQL Version' => $mysqlVersion ?: 'Unknown',
            'Database Name' => DB_NAME ?? 'Unknown',
            'Database Host' => DB_HOST ?? 'Unknown',
            'Database Charset' => $wpdb->charset ?? 'Unknown',
            'Database Collate' => $wpdb->collate ?? 'Unknown',
            'Connection Status' => $dbStatus,
        ];
    }
}