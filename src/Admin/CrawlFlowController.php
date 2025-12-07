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
        // Parse JSON requests VERY early (before WordPress processes the request)
        // This ensures action parameter is available in $_POST for WordPress AJAX
        // Priority 1 to run before everything else
        \add_action('plugins_loaded', [$this, 'parseJsonRequest'], 1);

        // Admin menu
        \add_action('admin_menu', [$this, 'registerMenu']);



        // AJAX handlers
        \add_action('wp_ajax_crawlflow_refresh_dashboard', [$this, 'handleRefreshDashboard']);
        \add_action('wp_ajax_crawlflow_get_project_stats', [$this, 'handleGetProjectStats']);
        \add_action('wp_ajax_crawlflow_get_system_status', [$this, 'handleGetSystemStatus']);
        \add_action('wp_ajax_crawlflow_save_project', [$this, 'handleSaveProject']);
        \add_action('wp_ajax_crawlflow_get_flow_config', [$this, 'handleGetFlowConfig']);
        \add_action('wp_ajax_crawlflow_run_flow', [$this, 'handleRunFlow']);
        \add_action('wp_ajax_crawlflow_auto_save_project', [$this, 'handleAutoSaveProject']);
        \add_action('wp_ajax_crawlflow_delete_project', [$this, 'handleDeleteProject']);
        \add_action('wp_ajax_crawlflow_clear_logs', [$this, 'handleClearLogs']);
        \add_action('wp_ajax_crawlflow_export_data', [$this, 'handleExportData']);
        
        // React Flow AJAX handlers
        \add_action('wp_ajax_crawlflow_save_flow_config', [$this, 'handleSaveFlowConfig']);
        \add_action('wp_ajax_crawlflow_load_flow_config', [$this, 'handleLoadFlowConfig']);

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
            'Projects',
            'Projects',
            'manage_options',
            'crawlflow-projects',
            [$this, 'renderProjectsPage']
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
            case 'crawlflow-logs':
                $this->renderer->renderLogs($screenData);
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

        // Check for sub screen
        $subScreen = \sanitize_text_field($_GET['sub'] ?? '');

        if ($subScreen === 'compose') {
            $this->renderProjectComposePage();
        } else {
            $projects = $this->projectService->getProjects();
            $this->renderer->renderProjectsList($projects);
        }
    }

    /**
     * Render project compose page (sub screen)
     */
    public function renderProjectComposePage(): void
    {
        $projectId = (int) ($_GET['project_id'] ?? 0);
        
        // Check if using React Flow editor
        $editor = \sanitize_text_field($_GET['editor'] ?? 'flow');
        
        if ($editor === 'flow') {
            $this->renderReactFlowEditor($projectId);
            return;
        }

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

        $this->renderer->renderProjectCompose($data);
    }
    
    /**
     * Render React Flow editor page
     */
    public function renderReactFlowEditor(?int $projectId = null): void
    {
        $project = null;
        $projectConfig = null;
        
        if ($projectId) {
            $project = $this->projectService->getProject($projectId);
            if ($project && isset($project['config'])) {
                $projectConfig = json_decode($project['config'], true);
            }
        }
        
        ?>
        <div class="wrap crawlflow-react-flow-wrapper">
            <div id="crawlflow-react-flow-root"></div>
        </div>
        
        <script>
        window.crawlflowConfig = {
            projectId: <?php echo $projectId ?: 'null'; ?>,
            project: <?php echo json_encode($project); ?>,
            projectConfig: <?php echo json_encode($projectConfig); ?>,
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('crawlflow_admin_nonce'); ?>',
            pluginUrl: '<?php echo CRAWLFLOW_PLUGIN_URL; ?>'
        };
        </script>
        <?php
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

    // Settings are now integrated into the main dashboard

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
     * Parse JSON request body and merge into $_POST for compatibility
     * Supports both JSON and form-data requests
     * This must run VERY early (on 'plugins_loaded' hook with priority 1) 
     * so WordPress AJAX can find the action parameter
     */
    public function parseJsonRequest(): void
    {
        // Only parse for AJAX requests (admin-ajax.php)
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($requestUri, 'admin-ajax.php') === false) {
            return;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        // Check if request is JSON
        if (strpos($contentType, 'application/json') !== false) {
            $json = file_get_contents('php://input');
            
            if (!empty($json)) {
                $data = json_decode($json, true);
                
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    // Merge JSON data into $_POST for backward compatibility
                    // This ensures WordPress AJAX hooks can find the action parameter
                    $_POST = array_merge($_POST, $data);
                    
                    // Also set $_REQUEST for complete compatibility
                    $_REQUEST = array_merge($_REQUEST, $data);
                    
                    // Debug log
                    error_log('CrawlFlow: Parsed JSON request - action: ' . ($data['action'] ?? 'none'));
                }
            }
        }
    }

    /**
     * Handle save project AJAX
     */
    public function handleSaveProject(): void
    {
        // Enable error logging for debugging
        error_log('CrawlFlow: handleSaveProject called');
        error_log('CrawlFlow: POST data keys: ' . implode(', ', array_keys($_POST)));
        
        // JSON is already parsed in parseJsonRequest() hook (runs on 'init')
        // No need to parse again here

        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'crawlflow_admin_nonce')) {
            error_log('CrawlFlow: Security check failed');
            wp_send_json_error('Security check failed');
        }

        // Handle flow-based config from React Flow UI
        // Support both JSON object and stringified JSON
        $projectDataArray = null;
        if (isset($_POST['project_data'])) {
            error_log('CrawlFlow: project_data is set, type: ' . gettype($_POST['project_data']));
            if (is_array($_POST['project_data'])) {
                $projectDataArray = $_POST['project_data'];
                error_log('CrawlFlow: project_data is array, nodes count: ' . (isset($projectDataArray['nodes']) ? count($projectDataArray['nodes']) : 0));
                
                // Debug: Log worker nodes
                if (isset($projectDataArray['nodes']) && is_array($projectDataArray['nodes'])) {
                    $workerNodes = array_filter($projectDataArray['nodes'], function($node) {
                        return ($node['type'] ?? '') === 'worker';
                    });
                    foreach ($workerNodes as $workerNode) {
                        $nodeId = $workerNode['id'] ?? 'unknown';
                        $nodeData = $workerNode['data'] ?? [];
                        error_log("CrawlFlow: Worker node {$nodeId} - has parser: " . (isset($nodeData['parser']) ? 'yes' : 'no'));
                        if (isset($nodeData['parser'])) {
                            error_log("CrawlFlow: Worker node {$nodeId} parser: " . json_encode($nodeData['parser']));
                        }
                    }
                }
            } else {
                // If it's a string, try to decode it
                $decoded = json_decode($_POST['project_data'], true);
                if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                    error_log('CrawlFlow: JSON decode error: ' . json_last_error_msg());
                }
                $projectDataArray = ($decoded !== null) ? $decoded : $_POST['project_data'];
            }
        } else {
            error_log('CrawlFlow: project_data is not set in POST');
        }

        // Determine status: if enabled in projectSettings, set to 'active', otherwise use provided status or 'draft'
        $status = sanitize_text_field($_POST['status'] ?? 'draft');
        if ($projectDataArray && isset($projectDataArray['projectSettings']['enabled'])) {
            $status = $projectDataArray['projectSettings']['enabled'] ? 'active' : 'draft';
        }

        $projectData = [
            'name' => sanitize_text_field($_POST['project_name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['project_description'] ?? ''),
            'status' => $status,
        ];

        // Set project_data after determining status
        if ($projectDataArray) {
            $projectData['project_data'] = $projectDataArray;
        }

        // Legacy support: Keep old fields for backward compatibility
        if (isset($_POST['tooth_type'])) {
            $projectData['tooth_type'] = sanitize_text_field($_POST['tooth_type']);
        }
        if (isset($_POST['base_url'])) {
            $projectData['base_url'] = esc_url_raw($_POST['base_url']);
        }
        if (isset($_POST['max_urls'])) {
            $projectData['max_urls'] = (int) $_POST['max_urls'];
        }

        if (empty($projectData['name'])) {
            wp_send_json_error('Project name is required');
        }

        $projectId = (int) ($_POST['project_id'] ?? 0);
        error_log('CrawlFlow: Project ID: ' . $projectId);

        if ($projectId) {
            // Update existing project
            error_log('CrawlFlow: Updating project ' . $projectId);
            $result = $this->projectService->updateProject($projectId, $projectData);
            $success = $result;
            error_log('CrawlFlow: Update result: ' . ($success ? 'success' : 'failed'));
        } else {
            // Create new project
            error_log('CrawlFlow: Creating new project');
            $result = $this->projectService->createProject($projectData);
            $success = $result > 0;
            error_log('CrawlFlow: Create result: ' . ($success ? 'success (ID: ' . $result . ')' : 'failed'));
        }

        if ($success) {
            error_log('CrawlFlow: Project saved successfully');
            wp_send_json_success([
                'message' => 'Project saved successfully',
                'project_id' => $projectId ?: $result,
            ]);
        } else {
            error_log('CrawlFlow: Failed to save project');
            wp_send_json_error('Failed to save project');
        }
    }

    /**
     * Handle get flow config AJAX
     */
    public function handleGetFlowConfig(): void
    {
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'crawlflow_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $projectId = (int) ($_POST['project_id'] ?? 0);
        if (!$projectId) {
            wp_send_json_error('Project ID is required');
        }

        $flowConfig = $this->projectService->getFlowConfig($projectId);
        if ($flowConfig === null) {
            wp_send_json_error('Project not found or invalid config');
        }

        wp_send_json_success($flowConfig);
    }

    /**
     * Handle run flow AJAX
     */
    public function handleRunFlow(): void
    {
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'crawlflow_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $projectId = (int) ($_POST['project_id'] ?? 0);
        if (!$projectId) {
            wp_send_json_error('Project ID is required');
        }

        try {
            $flowRunner = new FlowRunnerService();
            $result = $flowRunner->runFlow($projectId);

            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['error'] ?? 'Flow execution failed');
            }
        } catch (\Exception $e) {
            wp_send_json_error('Flow execution error: ' . $e->getMessage());
        }
    }

    /**
     * Handle auto-save project AJAX
     */
    public function handleAutoSaveProject(): void
    {
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'crawlflow_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $projectId = (int) ($_POST['project_id'] ?? 0);
        $projectName = \sanitize_text_field($_POST['project_name'] ?? '');
        $projectDescription = \sanitize_textarea_field($_POST['project_description'] ?? '');
        $toothType = \sanitize_text_field($_POST['tooth_type'] ?? '');
        $baseUrl = \esc_url_raw($_POST['base_url'] ?? '');
        $maxUrls = (int) ($_POST['max_urls'] ?? 1000);
        $status = \sanitize_text_field($_POST['status'] ?? 'draft');

        // For auto-save, we only save if we have at least a project name
        if (!$projectName) {
            wp_send_json_error('Project name is required for auto-save');
        }

        $projectData = [
            'name' => $projectName,
            'description' => $projectDescription,
            'tooth_type' => $toothType,
            'base_url' => $baseUrl,
            'max_urls' => $maxUrls,
            'status' => $status,
        ];

        if ($projectId) {
            $result = $this->projectService->updateProject($projectId, $projectData);
        } else {
            // For new projects, create as draft
            $projectData['status'] = 'draft';
            $result = $this->projectService->createProject($projectData);
        }

        if ($result) {
            wp_send_json_success([
                'message' => 'Project auto-saved successfully',
                'project_id' => $result,
            ]);
        } else {
            wp_send_json_error('Failed to auto-save project');
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

        $result = $this->projectService->deleteProject($projectId);

        if ($result) {
            wp_send_json_success(['message' => 'Project deleted successfully']);
        } else {
            wp_send_json_error('Failed to delete project');
        }
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
            'page' => 'crawlflow',
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
            'page' => 'crawlflow',
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
    
    /**
     * Handle save flow config AJAX
     */
    public function handleSaveFlowConfig(): void
    {
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'crawlflow_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $projectId = (int) ($_POST['project_id'] ?? 0);
        
        // Get raw config JSON - don't sanitize as it will break JSON structure
        // wp_unslash handles magic quotes automatically
        $configJson = isset($_POST['config']) ? wp_unslash($_POST['config']) : '';
        
        // Trim whitespace only
        $configJson = trim($configJson);
        
        if (empty($configJson)) {
            wp_send_json_error(['message' => 'Config is required']);
        }
        
        // Validate JSON before decoding
        $config = json_decode($configJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Log the error for debugging
            error_log('CrawlFlow JSON Error: ' . json_last_error_msg());
            error_log('CrawlFlow JSON Error Code: ' . json_last_error());
            error_log('CrawlFlow JSON Input length: ' . strlen($configJson));
            error_log('CrawlFlow JSON Input (first 1000 chars): ' . substr($configJson, 0, 1000));
            if (strlen($configJson) > 1000) {
                error_log('CrawlFlow JSON Input (last 1000 chars): ' . substr($configJson, -1000));
            }
            
            wp_send_json_error([
                'message' => 'Invalid JSON config: ' . json_last_error_msg(),
                'error_code' => json_last_error(),
                'debug_info' => [
                    'input_length' => strlen($configJson),
                    'first_chars' => substr($configJson, 0, 200),
                ],
            ]);
        }
        
        // Extract project settings from config
        $projectSettings = $config['projectSettings'] ?? [];
        $projectData = [
            'name' => sanitize_text_field($projectSettings['name'] ?? 'New Crawler Project'),
            'description' => sanitize_textarea_field($projectSettings['description'] ?? ''),
            'tooth_type' => sanitize_text_field($projectSettings['tooth_type'] ?? 'basic_crawler'),
            'base_url' => esc_url_raw($projectSettings['base_url'] ?? ''),
            'max_urls' => (int) ($projectSettings['max_urls'] ?? 1000),
            'status' => sanitize_text_field($projectSettings['status'] ?? 'draft'),
            'config' => $configJson, // Store full flow config
        ];
        
        if ($projectId) {
            // Update existing project
            $result = $this->projectService->updateProject($projectId, $projectData);
            if ($result) {
                wp_send_json_success([
                    'message' => 'Project updated successfully',
                    'project_id' => $projectId,
                ]);
            } else {
                wp_send_json_error('Failed to update project');
            }
        } else {
            // Create new project
            $newProjectId = $this->projectService->createProject($projectData);
            if ($newProjectId > 0) {
                wp_send_json_success([
                    'message' => 'Project created successfully',
                    'project_id' => $newProjectId,
                ]);
            } else {
                wp_send_json_error('Failed to create project');
            }
        }
    }
    
    /**
     * Handle load flow config AJAX
     */
    public function handleLoadFlowConfig(): void
    {
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'crawlflow_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $projectId = (int) ($_POST['project_id'] ?? 0);
        
        if (!$projectId) {
            wp_send_json_error('Project ID is required');
        }
        
        $project = $this->projectService->getProject($projectId);
        
        if (!$project) {
            wp_send_json_error('Project not found');
        }
        
        $config = null;
        if (isset($project['config']) && !empty($project['config'])) {
            $config = json_decode($project['config'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error('Invalid config format: ' . json_last_error_msg());
            }
        }
        
        wp_send_json_success([
            'config' => $config,
            'project' => $project,
        ]);
    }
}