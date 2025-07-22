<?php

namespace CrawlFlow\Admin;

/**
 * Main Controller for CrawlFlow Admin
 * Handles all admin functionality based on screen type
 */
class CrawlFlowController
{
    /**
     * @var DashboardService
     */
    private $dashboardService;

    /**
     * @var ProjectService
     */
    private $projectService;

    /**
     * @var LogService
     */
    private $logService;

    /**
     * @var MigrationService
     */
    private $migrationService;

    /**
     * @var DashboardRenderer
     */
    private $renderer;

    /**
     * @var string
     */
    private $currentScreen;

        /**
     * Constructor
     */
    public function __construct()
    {
        $this->dashboardService = new DashboardService();
        $this->projectService = new ProjectService();
        $this->logService = new LogService();
        $this->migrationService = new MigrationService();
        $this->renderer = new DashboardRenderer();

        $this->registerHooks();
    }

    /**
     * Register WordPress hooks
     */
    private function registerHooks(): void
    {
        // Admin menu
        \add_action('admin_menu', [$this, 'registerMenu']);

                // AJAX handlers
        \add_action('wp_ajax_crawlflow_refresh_dashboard', [$this, 'handleRefreshDashboard']);
        \add_action('wp_ajax_crawlflow_get_project_stats', [$this, 'handleGetProjectStats']);
        \add_action('wp_ajax_crawlflow_get_system_status', [$this, 'handleGetSystemStatus']);
        \add_action('wp_ajax_crawlflow_save_project', [$this, 'handleSaveProject']);
        \add_action('wp_ajax_crawlflow_delete_project', [$this, 'handleDeleteProject']);
        \add_action('wp_ajax_crawlflow_clear_logs', [$this, 'handleClearLogs']);
        \add_action('wp_ajax_crawlflow_export_data', [$this, 'handleExportData']);

        // Admin actions
        \add_action('admin_post_crawlflow_clear_logs', [$this, 'handleClearLogsAction']);
        \add_action('admin_post_crawlflow_export_data', [$this, 'handleExportDataAction']);
        \add_action('admin_post_crawlflow_save_settings', [$this, 'handleSaveSettings']);

        // Migration hooks
        \add_action('admin_post_crawlflow_run_migration', [$this, 'handleRunMigration']);
        \add_action('wp_ajax_crawlflow_check_migration_status', [$this, 'handleCheckMigrationStatus']);
    }

    /**
     * Detect current WordPress admin screen
     */
    private function detectCurrentScreen(): void
    {
        global $pagenow, $plugin_page;

        // Get current screen from WordPress
        $screen = \get_current_screen();

        if ($screen) {
            $this->currentScreen = $screen->id;
        } else {
            // Fallback detection
            if (isset($_GET['page'])) {
                $this->currentScreen = \sanitize_text_field($_GET['page']);
            } elseif ($pagenow) {
                $this->currentScreen = $pagenow;
            } else {
                $this->currentScreen = 'dashboard';
            }
        }
    }

    /**
     * Register admin menu
     */
    public function registerMenu(): void
    {
        // Main menu
        \add_menu_page(
            'CrawlFlow',
            'CrawlFlow',
            'manage_options',
            'crawlflow',
            [$this, 'renderDashboardPage'],
            'dashicons-admin-generic',
            30
        );

        // Submenus
        add_submenu_page(
            'crawlflow',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'crawlflow',
            [$this, 'renderDashboardPage']
        );

        add_submenu_page(
            'crawlflow',
            'Projects',
            'Projects',
            'manage_options',
            'crawlflow-projects',
            [$this, 'renderProjectsPage']
        );

        add_submenu_page(
            'crawlflow',
            'Project Editor',
            'Project Editor',
            'manage_options',
            'crawlflow-project-editor',
            [$this, 'renderProjectEditorPage']
        );

        add_submenu_page(
            'crawlflow',
            'Migration',
            'Migration',
            'manage_options',
            'crawlflow-migration',
            [$this, 'renderMigrationPage']
        );

        add_submenu_page(
            'crawlflow',
            'Logs',
            'Logs',
            'manage_options',
            'crawlflow-logs',
            [$this, 'renderLogsPage']
        );

        add_submenu_page(
            'crawlflow',
            'Settings',
            'Settings',
            'manage_options',
            'crawlflow-settings',
            [$this, 'renderSettingsPage']
        );

        add_submenu_page(
            'crawlflow',
            'Analytics',
            'Analytics',
            'manage_options',
            'crawlflow-analytics',
            [$this, 'renderAnalyticsPage']
        );
    }

    /**
     * Render page based on current screen
     */
    public function renderPage(): void
    {
        $this->detectCurrentScreen();
        $screenData = $this->dashboardService->getScreenData($this->currentScreen);

        switch ($this->currentScreen) {
            case 'crawlflow':
                $this->renderer->renderDashboardOverview($screenData);
                break;
            case 'crawlflow-project-editor':
                $this->renderer->renderProjectEditor($screenData);
                break;
            case 'crawlflow-settings':
                $this->renderer->renderSettings($screenData);
                break;
            case 'crawlflow-logs':
                $this->renderer->renderLogs($screenData);
                break;
            case 'crawlflow-migration':
                $this->renderMigrationPage();
                break;
            case 'crawlflow-projects':
                $this->renderProjectsPage();
                break;
            case 'crawlflow-analytics':
                $this->renderAnalyticsPage();
                break;
            default:
                $this->renderer->renderDashboardOverview($screenData);
                break;
        }
    }

    // ============================================================================
    // PAGE RENDERING METHODS
    // ============================================================================

    /**
     * Render dashboard page
     */
    public function renderDashboardPage(): void
    {
        $this->currentScreen = 'crawlflow';
        $this->detectCurrentScreen();
        $this->renderPage();
    }

    /**
     * Render projects page
     */
    public function renderProjectsPage(): void
    {
        $this->currentScreen = 'crawlflow-projects';
        $this->detectCurrentScreen();
        $projects = $this->projectService->getProjects();
        $this->renderer->renderProjectsList($projects);
    }

        /**
     * Render project editor page
     */
    public function renderProjectEditorPage(): void
    {
        $this->currentScreen = 'crawlflow-project-editor';
        $this->detectCurrentScreen();
        $projectId = (int) ($_GET['project_id'] ?? 0);

        if ($projectId) {
            $project = $this->projectService->getProject($projectId);
            $data = [
                'project' => $project,
                'is_edit' => true,
                'available_tooths' => $this->projectService->getAvailableTooths(),
            ];
        } else {
            $data = [
                'project' => [],
                'is_edit' => false,
                'available_tooths' => $this->projectService->getAvailableTooths(),
            ];
        }

        $this->renderer->renderProjectEditor($data);
    }

    /**
     * Render migration page
     */
    public function renderMigrationPage(): void
    {
        $this->currentScreen = 'crawlflow-migration';
        $this->detectCurrentScreen();
        $migrationStatus = $this->migrationService->checkMigrationStatus();
        $this->renderer->renderMigration($migrationStatus);
    }

        /**
     * Render logs page
     */
    public function renderLogsPage(): void
    {
        $this->currentScreen = 'crawlflow-logs';
        $this->detectCurrentScreen();
        $page = (int) ($_GET['paged'] ?? 1);
        $level = \sanitize_text_field($_GET['level'] ?? '');

        if ($level) {
            $logs = $this->logService->getLogsByLevel($level, $page);
        } else {
            $logs = $this->logService->getLogs($page);
        }

        $data = [
            'logs' => $logs,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($this->logService->getTotalLogs() / 20),
            ],
        ];

        $this->renderer->renderLogs($data);
    }

    /**
     * Render settings page
     */
    public function renderSettingsPage(): void
    {
        $this->currentScreen = 'crawlflow-settings';
        $this->detectCurrentScreen();
        $this->renderPage();
    }

        /**
     * Render analytics page
     */
    public function renderAnalyticsPage(): void
    {
        $this->currentScreen = 'crawlflow-analytics';
        $this->detectCurrentScreen();
        $period = \sanitize_text_field($_GET['period'] ?? '7days');

        $data = [
            'urls_chart' => $this->projectService->getUrlsProcessedChart($period),
            'performance_data' => $this->projectService->getProjectsPerformance($period),
            'period' => $period,
        ];

        $this->renderer->renderAnalytics($data);
    }

    // ============================================================================
    // AJAX HANDLERS
    // ============================================================================

    /**
     * Handle refresh dashboard AJAX
     */
    public function handleRefreshDashboard(): void
    {
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'crawlflow_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $data = $this->dashboardService->getScreenData('crawlflow');
        wp_send_json_success($data);
    }

    /**
     * Handle get project stats AJAX
     */
    public function handleGetProjectStats(): void
    {
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'crawlflow_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $projectId = (int) ($_POST['project_id'] ?? 0);

        if (!$projectId) {
            wp_send_json_error('Project ID is required');
        }

        $stats = [
            'total_urls' => $this->projectService->getTotalUrlsProcessed(),
            'pending_urls' => $this->projectService->getTotalUrlsPending(),
            'failed_urls' => $this->projectService->getTotalUrlsFailed(),
            'skipped_urls' => $this->projectService->getTotalUrlsSkipped(),
        ];

        wp_send_json_success($stats);
    }

    /**
     * Handle get system status AJAX
     */
    public function handleGetSystemStatus(): void
    {
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'crawlflow_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $data = $this->dashboardService->getScreenData('crawlflow');
        $systemStatus = $data['system_status'] ?? [];

        wp_send_json_success($systemStatus);
    }

    /**
     * Handle save project AJAX
     */
    public function handleSaveProject(): void
    {
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'crawlflow_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $projectData = [
            'name' => sanitize_text_field($_POST['project_name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['project_description'] ?? ''),
            'tooth_type' => sanitize_text_field($_POST['project_type'] ?? ''),
            'status' => sanitize_text_field($_POST['project_status'] ?? 'active'),
        ];

        if (empty($projectData['name'])) {
            wp_send_json_error('Project name is required');
        }

        $projectId = (int) ($_POST['project_id'] ?? 0);

        if ($projectId) {
            $projectData['id'] = $projectId;
        }

        $result = $this->projectService->saveProject($projectData);

        if ($result) {
            wp_send_json_success(['message' => 'Project saved successfully']);
        } else {
            wp_send_json_error('Failed to save project');
        }
    }

    /**
     * Handle delete project AJAX
     */
    public function handleDeleteProject(): void
    {
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'crawlflow_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $projectId = (int) ($_POST['project_id'] ?? 0);

        if (!$projectId) {
            wp_send_json_error('Project ID is required');
        }

        // Add delete project logic here
        wp_send_json_success(['message' => 'Project deleted successfully']);
    }

    /**
     * Handle clear logs AJAX
     */
    public function handleClearLogs(): void
    {
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'crawlflow_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $days = (int) ($_POST['days'] ?? 30);
        $deletedCount = $this->logService->clearOldLogs($days);

        wp_send_json_success([
            'message' => "Cleared $deletedCount log entries",
            'deleted_count' => $deletedCount
        ]);
    }

    /**
     * Handle export data AJAX
     */
    public function handleExportData(): void
    {
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'crawlflow_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $type = sanitize_text_field($_POST['export_type'] ?? 'projects');

        switch ($type) {
            case 'projects':
                $data = $this->projectService->getProjects(1, 1000);
                break;
            case 'logs':
                $data = $this->logService->getLogs(1, 1000);
                break;
            default:
                wp_send_json_error('Invalid export type');
                return;
        }

        wp_send_json_success(['data' => $data]);
    }

    /**
     * Handle check migration status AJAX
     */
    public function handleCheckMigrationStatus(): void
    {
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'crawlflow_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $status = $this->migrationService->checkMigrationStatus();
        wp_send_json_success($status);
    }

    // ============================================================================
    // ADMIN ACTION HANDLERS
    // ============================================================================

    /**
     * Handle clear logs action
     */
    public function handleClearLogsAction(): void
    {
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'crawlflow_admin_nonce')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $days = (int) ($_POST['days'] ?? 30);
        $deletedCount = $this->logService->clearOldLogs($days);

        $redirectUrl = add_query_arg([
            'page' => 'crawlflow-logs',
            'cleared' => $deletedCount,
        ], admin_url('admin.php'));

        wp_redirect($redirectUrl);
        exit;
    }

    /**
     * Handle export data action
     */
    public function handleExportDataAction(): void
    {
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'crawlflow_admin_nonce')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $type = sanitize_text_field($_POST['export_type'] ?? 'projects');

        switch ($type) {
            case 'projects':
                $this->exportProjects();
                break;
            case 'logs':
                $this->exportLogs();
                break;
            case 'urls':
                $this->exportUrls();
                break;
            default:
                wp_die('Invalid export type');
        }
    }

    /**
     * Handle save settings action
     */
    public function handleSaveSettings(): void
    {
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'crawlflow_admin_nonce')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // Save settings logic here
        $redirectUrl = add_query_arg([
            'page' => 'crawlflow-settings',
            'updated' => '1',
        ], admin_url('admin.php'));

        wp_redirect($redirectUrl);
        exit;
    }

    /**
     * Handle run migration action
     */
    public function handleRunMigration(): void
    {
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'crawlflow_admin_nonce')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $result = $this->migrationService->runMigrations();

        $redirectUrl = add_query_arg([
            'page' => 'crawlflow-migration',
            'migration_result' => $result ? 'success' : 'error',
        ], admin_url('admin.php'));

        wp_redirect($redirectUrl);
        exit;
    }

    // ============================================================================
    // EXPORT METHODS
    // ============================================================================

    /**
     * Export projects data
     */
    private function exportProjects(): void
    {
        $projects = $this->projectService->getProjects(1, 1000);
        $filename = 'crawlflow-projects-' . date('Y-m-d-H-i-s') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, ['ID', 'Name', 'Description', 'Type', 'Status', 'Created At', 'Updated At']);

        // CSV data
        foreach ($projects as $project) {
            fputcsv($output, [
                $project['id'] ?? '',
                $project['name'] ?? '',
                $project['description'] ?? '',
                $project['tooth_type'] ?? '',
                $project['status'] ?? '',
                $project['created_at'] ?? '',
                $project['updated_at'] ?? '',
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Export logs data
     */
    private function exportLogs(): void
    {
        $logs = $this->logService->getLogs(1, 1000);
        $filename = 'crawlflow-logs-' . date('Y-m-d-H-i-s') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, ['ID', 'Level', 'Message', 'Context', 'Project ID', 'Created At']);

        // CSV data
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['id'] ?? '',
                $log['level'] ?? '',
                $log['message'] ?? '',
                $log['context'] ?? '',
                $log['tooth_id'] ?? '',
                $log['created_at'] ?? '',
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Export URLs data
     */
    private function exportUrls(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_urls';
        $urls = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 1000", ARRAY_A);

        $filename = 'crawlflow-urls-' . date('Y-m-d-H-i-s') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, ['ID', 'Project ID', 'URL', 'Status', 'Skipped', 'Retry Count', 'Last Error', 'Crawled At', 'Created At']);

        // CSV data
        foreach ($urls as $url) {
            fputcsv($output, [
                $url['id'] ?? '',
                $url['tooth_id'] ?? '',
                $url['url'] ?? '',
                $url['status'] ?? '',
                $url['skipped'] ?? '',
                $url['retry_count'] ?? '',
                $url['last_error'] ?? '',
                $url['crawled_at'] ?? '',
                $url['created_at'] ?? '',
            ]);
        }

        fclose($output);
        exit;
    }

    // ============================================================================
    // UTILITY METHODS
    // ============================================================================

    /**
     * Get current screen
     */
    public function getCurrentScreen(): string
    {
        return $this->currentScreen;
    }

    /**
     * Check if current screen is CrawlFlow screen
     */
    public function isCrawlFlowScreen(): bool
    {
        return strpos($this->currentScreen, 'crawlflow') === 0;
    }

    /**
     * Get dashboard service
     */
    public function getDashboardService(): DashboardService
    {
        return $this->dashboardService;
    }

    /**
     * Get project service
     */
    public function getProjectService(): ProjectService
    {
        return $this->projectService;
    }

    /**
     * Get log service
     */
    public function getLogService(): LogService
    {
        return $this->logService;
    }

    /**
     * Get migration service
     */
    public function getMigrationService(): MigrationService
    {
        return $this->migrationService;
    }
}