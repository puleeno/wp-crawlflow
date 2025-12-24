<?php

namespace CrawlFlow\Admin;

/**
 * Main Controller for CrawlFlow Admin
 * Handles all admin functionality based on screen type
 */
class CrawlFlowController
{
    /**
     * @var mixed DashboardService instance
     */
    private $dashboardService;

    /**
     * @var mixed ProjectService instance
     */
    private $projectService;

    /**
     * @var mixed LogService instance
     */
    private $logService;

    /**
     * @var mixed MigrationService instance
     */
    private $migrationService;

    /**
     * @var mixed DashboardRenderer instance
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
        // Initialize services with lazy loading to avoid circular dependencies
        $this->dashboardService = null;
        $this->projectService = null;
        $this->logService = null;
        $this->migrationService = null;
        $this->renderer = null;

        $this->registerHooks();
    }

    /**
     * Get dashboard service (lazy loaded)
     */
    private function getDashboardService()
    {
        if ($this->dashboardService === null) {
            $className = 'CrawlFlow\Admin\DashboardService';
            if (class_exists($className)) {
                $this->dashboardService = new $className();
            } else {
                // Return a mock object if class doesn't exist
                $this->dashboardService = new class {
                    public function getScreenData($screen) { return []; }
                };
            }
        }
        return $this->dashboardService;
    }

    /**
     * Get project service (lazy loaded)
     */
    private function getProjectService()
    {
        if ($this->projectService === null) {
            $className = 'CrawlFlow\Admin\ProjectService';
            if (class_exists($className)) {
                $this->projectService = new $className();
            } else {
                // Return a mock object if class doesn't exist
                $this->projectService = new class {
                    public function createProject($data) { return null; }
                    public function updateProject($id, $data) { return false; }
                    public function deleteProject($id) { return false; }
                    public function getProject($id) { return null; }
                    public function getProjects($page = 1, $per_page = 10) { return []; }
                    public function getTotalProjects() { return 0; }
                    public function getActiveProjects() { return 0; }
                    public function getProjectStats($id) { return []; }
                    public function getTotalUrlsProcessed() { return 0; }
                    public function getTotalUrlsPending() { return 0; }
                    public function getTotalUrlsFailed() { return 0; }
                    public function getTotalUrlsSkipped() { return 0; }
                    public function getAvailableTooths() { return []; }
                    public function getUrlsProcessedChart($period) { return []; }
                    public function getProjectsPerformance($period) { return []; }
                    public function getFlowConfig($projectId) { return []; }
                };
            }
        }
        return $this->projectService;
    }

    /**
     * Get log service (lazy loaded)
     */
    private function getLogService()
    {
        if ($this->logService === null) {
            $className = 'CrawlFlow\Admin\LogService';
            if (class_exists($className)) {
                $this->logService = new $className();
            } else {
                // Return a mock object if class doesn't exist
                $this->logService = new class {
                    public function clearLogs() { return true; }
                    public function getLogs($page = 1, $per_page = 20) { return []; }
                    public function getTotalLogs() { return 0; }
                    public function exportLogs($format) { return ''; }
                    public function getLogsByLevel($level, $page = 1, $per_page = 100) { return []; }
                    public function clearOldLogs($days) { return 0; }
                };
            }
        }
        return $this->logService;
    }

    /**
     * Get migration service (lazy loaded)
     */
    private function getMigrationService()
    {
        if ($this->migrationService === null) {
            $className = 'CrawlFlow\Admin\MigrationService';
            if (class_exists($className)) {
                $this->migrationService = new $className();
            } else {
                // Return a mock object if class doesn't exist
                $this->migrationService = new class {
                    public function runMigrations() { return true; }
                    public function checkMigrationStatus() { return []; }
                    public function getMigrationHistory() { return []; }
                };
            }
        }
        return $this->migrationService;
    }

    /**
     * Get dashboard renderer (lazy loaded)
     */
    private function getRenderer()
    {
        if ($this->renderer === null) {
            $className = 'CrawlFlow\Admin\DashboardRenderer';
            if (class_exists($className)) {
                $this->renderer = new $className();
            } else {
                // Return a mock object if class doesn't exist
                $this->renderer = new class {
                    public function render($template, $data) { return ''; }
                    public function renderScreen($screen, $data) { return ''; }
                    public function renderDashboardOverview($data) { echo '<div>Dashboard Overview</div>'; }
                    public function renderLogs($data) { echo '<div>Logs</div>'; }
                    public function renderProjectsList($projects) { echo '<div>Projects List</div>'; }
                    public function renderProjectCompose($data) { echo '<div>Project Compose</div>'; }
                    public function renderAnalytics($data) { echo '<div>Analytics</div>'; }
                };
            }
        }
        return $this->renderer;
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
        $screenData = $this->getDashboardService()->getScreenData($this->currentScreen);

        switch ($this->currentScreen) {
            case 'crawlflow':
                $this->getRenderer()->renderDashboardOverview($screenData);
                break;
            case 'crawlflow-logs':
                $this->getRenderer()->renderLogs($screenData);
                break;
            case 'crawlflow-projects':
                $this->renderProjectsPage();
                break;
            case 'crawlflow-analytics':
                $this->renderAnalyticsPage();
                break;
            default:
                $this->getRenderer()->renderDashboardOverview($screenData);
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
            $projects = $this->getProjectService()->getProjects(1, 10);
            $this->getRenderer()->renderProjectsList($projects);
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
            $project = $this->getProjectService()->getProject($projectId);
            $data = [
                'project' => $project,
                'is_edit' => true,
                'available_tooths' => $this->getProjectService()->getAvailableTooths(),
            ];
        } else {
            $data = [
                'project' => [],
                'is_edit' => false,
                'available_tooths' => $this->getProjectService()->getAvailableTooths(),
            ];
        }

        $this->getRenderer()->renderProjectCompose($data);
    }
    
    /**
     * Render React Flow editor page
     */
    public function renderReactFlowEditor(?int $projectId = null): void
    {
        $project = null;
        $projectConfig = null;
        
        if ($projectId) {
            $project = $this->getProjectService()->getProject($projectId);
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
            $logs = $this->getLogService()->getLogsByLevel($level, $page);
        } else {
            $logs = $this->getLogService()->getLogs($page, 20);
        }

        $data = [
            'logs' => $logs,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($this->getLogService()->getTotalLogs() / 20),
            ],
        ];

        $this->getRenderer()->renderLogs($data);
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
        $selectedProjectId = \sanitize_text_field($_GET['project_id'] ?? '');

        // Get comprehensive analytics data
        $projects = $this->getProjectService()->getProjects(1, 10);
        $activeProjects = array_filter($projects, fn($p) => ($p['status'] ?? '') === 'active');
        $draftProjects = array_filter($projects, fn($p) => ($p['status'] ?? '') === 'draft');
        
        // Get URL statistics
        $totalUrls = $this->projectService->getTotalUrlsProcessed() + $this->projectService->getTotalUrlsPending() + $this->projectService->getTotalUrlsFailed();
        $processedUrls = $this->projectService->getTotalUrlsProcessed();
        $pendingUrls = $this->projectService->getTotalUrlsPending();
        $failedUrls = $this->projectService->getTotalUrlsFailed();
        
        // Calculate success rate
        $successRate = $totalUrls > 0 ? ($processedUrls / $totalUrls) * 100 : 0;
        
        // Get cron job statistics
        $scheduledJobs = $this->getScheduledJobsCount();
        $runningJobs = $this->getRunningJobsCount();
        $failedJobs = $this->getFailedJobsCount();
        
        // Get system performance metrics
        $memoryUsage = $this->getMemoryUsage();
        $avgResponseTime = $this->getAverageResponseTime();
        
        // Get system health data
        $systemHealth = $this->getSystemHealth();
        
        // Get performance metrics for the period
        $performanceMetrics = $this->getPerformanceMetrics($period);
        
        // Get error analysis
        $errorAnalysis = $this->getErrorAnalysis($period);
        
        // Get top performing projects
        $topProjects = $this->getTopProjects($period);
        
        // Get projects progress data
        $projectsProgress = $this->getProjectsProgress($activeProjects);
        
        // Get recent activity
        $recentActivity = $this->getRecentActivity();
        
        // Get projects summary for table
        $projectsSummary = $this->getProjectsSummary($projects);
        
        // Get selected project details if specified
        $selectedProject = null;
        if (!empty($selectedProjectId) && is_numeric($selectedProjectId)) {
            $selectedProject = $this->getSelectedProjectDetails((int)$selectedProjectId, $period);
        }
        
        // Get chart data
        $chartData = [
            'urls_chart' => $this->projectService->getUrlsProcessedChart($period),
            'performance_chart' => $this->projectService->getProjectsPerformance($period),
            'error_chart' => $this->getErrorRateChart($period),
            'resource_chart' => $this->getResourceUsageChart($period),
            'project_timeline_chart' => $selectedProject ? $this->getProjectTimelineChart((int)$selectedProjectId, $period) : null,
        ];

        $data = [
            // Summary statistics
            'total_projects' => count($projects),
            'active_projects' => count($activeProjects),
            'draft_projects' => count($draftProjects),
            
            // Cron job statistics
            'scheduled_jobs' => $scheduledJobs,
            'running_jobs' => $runningJobs,
            'failed_jobs' => $failedJobs,
            
            // URL statistics
            'total_urls' => $totalUrls,
            'processed_urls' => $processedUrls,
            'pending_urls' => $pendingUrls,
            'failed_urls' => $failedUrls,
            
            // Performance metrics
            'success_rate' => round($successRate, 1),
            'avg_response_time' => $avgResponseTime,
            'memory_usage' => $memoryUsage,
            
            // Detailed overview data
            'system_health' => $systemHealth,
            'performance_metrics' => $performanceMetrics,
            'error_analysis' => $errorAnalysis,
            'top_projects' => $topProjects,
            
            // Progress tracking
            'projects_progress' => $projectsProgress,
            
            // Recent activity
            'recent_activity' => $recentActivity,
            
            // Project-specific data
            'all_projects' => $projects,
            'selected_project_id' => $selectedProjectId,
            'selected_project' => $selectedProject,
            'projects_summary' => $projectsSummary,
            
            // Chart data
            'chart_data' => $chartData,
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

        $status = $this->getMigrationService()->checkMigrationStatus();
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

        $result = $this->getMigrationService()->runMigrations();

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

    
    // ============================================================================
    // ANALYTICS HELPER METHODS
    // ============================================================================

    /**
     * Get scheduled cron jobs count
     */
    private function getScheduledJobsCount(): int
    {
        $cronJobs = _get_cron_array();
        $crawlflowJobs = 0;
        
        if (is_array($cronJobs)) {
            foreach ($cronJobs as $timestamp => $hooks) {
                if (is_array($hooks)) {
                    foreach ($hooks as $hook => $events) {
                        if (strpos($hook, 'crawlflow') !== false) {
                            $crawlflowJobs += count($events);
                        }
                    }
                }
            }
        }
        
        return $crawlflowJobs;
    }

    /**
     * Get running jobs count (approximate)
     */
    private function getRunningJobsCount(): int
    {
        // This is an approximation - in reality you'd need to track running processes
        // For now, we'll return 0 as WordPress cron doesn't track running jobs
        return 0;
    }

    /**
     * Get failed jobs count from logs
     */
    private function getFailedJobsCount(): int
    {
        try {
            $failedLogs = $this->getLogService()->getLogsByLevel('error', 1, 100);
            return count($failedLogs);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get memory usage in human readable format
     */
    private function getMemoryUsage(): string
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        
        // Convert memory limit to bytes
        $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
        
        $usagePercent = $memoryLimitBytes > 0 ? ($memoryUsage / $memoryLimitBytes) * 100 : 0;
        
        return sprintf(
            '%s / %s (%.1f%%)',
            $this->formatBytes($memoryUsage),
            $memoryLimit,
            $usagePercent
        );
    }

    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;
        
        switch ($last) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return $value;
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Get average response time (mock implementation)
     */
    private function getAverageResponseTime(): float
    {
        // This would typically come from performance monitoring
        // For now, return a reasonable default
        return 2.5;
    }

    /**
     * Get projects progress data
     */
    private function getProjectsProgress(array $projects): array
    {
        $progressData = [];
        
        foreach ($projects as $project) {
            $projectId = $project['id'] ?? 0;
            
            // Get URL counts for this project
            $totalUrls = $this->getProjectTotalUrls($projectId);
            $processedUrls = $this->getProjectProcessedUrls($projectId);
            
            // Calculate progress percentage
            $progressPercentage = $totalUrls > 0 ? ($processedUrls / $totalUrls) * 100 : 0;
            
            // Get next run time
            $nextRun = $this->getProjectNextRun($projectId);
            $lastRun = $this->getProjectLastRun($projectId);
            
            // Get schedule info
            $schedule = $this->getProjectSchedule($projectId);
            
            // Get recent errors
            $recentErrors = $this->getProjectRecentErrors($projectId);
            
            $progressData[] = [
                'id' => $projectId,
                'name' => $project['name'] ?? 'Unknown Project',
                'status' => $project['status'] ?? 'unknown',
                'total_count' => $totalUrls,
                'processed_count' => $processedUrls,
                'progress_percentage' => round($progressPercentage, 1),
                'next_run' => $nextRun,
                'last_run' => $lastRun,
                'schedule' => $schedule,
                'recent_errors' => $recentErrors,
            ];
        }
        
        return $progressData;
    }

    /**
     * Get total URLs for a project
     */
    private function getProjectTotalUrls(int $projectId): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_urls';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE tooth_id = %d",
            $projectId
        ));
        
        return (int) $count;
    }

    /**
     * Get processed URLs for a project
     */
    private function getProjectProcessedUrls(int $projectId): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_urls';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE tooth_id = %d AND status = 'processed'",
            $projectId
        ));
        
        return (int) $count;
    }

    /**
     * Get next run time for a project
     */
    private function getProjectNextRun(int $projectId): string
    {
        $cronJobs = _get_cron_array();
        
        if (is_array($cronJobs)) {
            foreach ($cronJobs as $timestamp => $hooks) {
                if (is_array($hooks)) {
                    foreach ($hooks as $hook => $events) {
                        if (strpos($hook, 'crawlflow') !== false && strpos($hook, (string)$projectId) !== false) {
                            return date('Y-m-d H:i:s', $timestamp);
                        }
                    }
                }
            }
        }
        
        return 'Not scheduled';
    }

    /**
     * Get last run time for a project
     */
    private function getProjectLastRun(int $projectId): string
    {
        try {
            $logs = $this->logService->getLogs(1, 10);
            
            foreach ($logs as $log) {
                if (strpos($log['message'] ?? '', "project {$projectId}") !== false) {
                    return $log['created_at'] ?? 'Unknown';
                }
            }
        } catch (\Exception $e) {
            // Ignore errors
        }
        
        return 'Never';
    }

    /**
     * Get project schedule information
     */
    private function getProjectSchedule(int $projectId): string
    {
        try {
            $flowConfig = $this->projectService->getFlowConfig($projectId);
            $projectSettings = $flowConfig['projectSettings'] ?? [];
            $crawlDelay = $projectSettings['crawlDelay'] ?? 300000;
            
            // Convert milliseconds to human readable format
            $minutes = $crawlDelay / 60000;
            
            if ($minutes < 60) {
                return "Every {$minutes} minutes";
            } else {
                $hours = $minutes / 60;
                if ($hours < 24) {
                    return "Every " . round($hours, 1) . " hours";
                } else {
                    $days = $hours / 24;
                    return "Every " . round($days, 1) . " days";
                }
            }
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    /**
     * Get recent errors for a project
     */
    private function getProjectRecentErrors(int $projectId): array
    {
        try {
            $errorLogs = $this->logService->getLogsByLevel('error', 1, 50);
            $projectErrors = [];
            
            foreach ($errorLogs as $log) {
                if (strpos($log['message'] ?? '', "project {$projectId}") !== false) {
                    $projectErrors[] = [
                        'message' => $log['message'] ?? 'Unknown error',
                        'time' => $log['created_at'] ?? '',
                    ];
                    
                    if (count($projectErrors) >= 3) {
                        break;
                    }
                }
            }
            
            return $projectErrors;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get recent activity
     */
    private function getRecentActivity(): array
    {
        try {
            $logs = $this->logService->getLogs(1, 20);
            $activity = [];
            
            foreach ($logs as $log) {
                $type = 'info';
                $message = $log['message'] ?? '';
                $project = 'System';
                
                // Determine activity type and extract project name
                if (strpos($message, 'error') !== false || strpos($message, 'failed') !== false) {
                    $type = 'error';
                } elseif (strpos($message, 'warning') !== false) {
                    $type = 'warning';
                } elseif (strpos($message, 'success') !== false || strpos($message, 'completed') !== false) {
                    $type = 'success';
                }
                
                // Extract project name if present
                if (preg_match('/project (\d+)/', $message, $matches)) {
                    $projectId = $matches[1];
                    $projectData = $this->projectService->getProject((int)$projectId);
                    $project = $projectData['name'] ?? "Project {$projectId}";
                }
                
                $activity[] = [
                    'type' => $type,
                    'time' => $log['created_at'] ?? '',
                    'project' => $project,
                    'message' => $message,
                ];
            }
            
            return $activity;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get system health status
     */
    private function getSystemHealth(): array
    {
        $health = [];
        
        // Check cron system
        $cronJobs = _get_cron_array();
        $health['cron_status'] = is_array($cronJobs) && !empty($cronJobs) ? 'good' : 'warning';
        
        // Check database connectivity
        global $wpdb;
        $health['database_status'] = $wpdb->last_error ? 'critical' : 'good';
        
        // Check memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        $memoryPercent = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) * 100 : 0;
        
        if ($memoryPercent > 90) {
            $health['memory_status'] = 'critical';
        } elseif ($memoryPercent > 75) {
            $health['memory_status'] = 'warning';
        } else {
            $health['memory_status'] = 'good';
        }
        
        // Check disk space (basic check)
        $uploadDir = wp_upload_dir();
        $freeSpace = disk_free_space($uploadDir['basedir']);
        $totalSpace = disk_total_space($uploadDir['basedir']);
        $diskPercent = $totalSpace > 0 ? (($totalSpace - $freeSpace) / $totalSpace) * 100 : 0;
        
        if ($diskPercent > 90) {
            $health['disk_status'] = 'critical';
        } elseif ($diskPercent > 80) {
            $health['disk_status'] = 'warning';
        } else {
            $health['disk_status'] = 'good';
        }
        
        return $health;
    }

    /**
     * Get performance metrics for a period
     */
    private function getPerformanceMetrics(string $period): array
    {
        // Calculate period in days
        $periodDays = match($period) {
            '7days' => 7,
            '30days' => 30,
            '90days' => 90,
            default => 7
        };
        
        // Get metrics from logs or database
        $totalProcessed = $this->projectService->getTotalUrlsProcessed();
        $avgProcessingTime = $this->getAverageProcessingTime();
        $successRate = $this->getOverallSuccessRate();
        
        // Calculate throughput (urls per minute)
        $throughput = $periodDays > 0 ? ($totalProcessed / ($periodDays * 24 * 60)) : 0;
        
        return [
            'total_processed' => $totalProcessed,
            'avg_processing_time' => $avgProcessingTime,
            'success_rate' => $successRate,
            'throughput' => $throughput,
        ];
    }

    /**
     * Get error analysis for a period
     */
    private function getErrorAnalysis(string $period): array
    {
        try {
            $errorLogs = $this->logService->getLogsByLevel('error', 1, 100);
            $errorAnalysis = [];
            
            foreach ($errorLogs as $log) {
                $message = $log['message'] ?? '';
                
                // Categorize errors
                if (strpos($message, 'timeout') !== false || strpos($message, 'connection') !== false) {
                    $errorAnalysis['network'] = ($errorAnalysis['network'] ?? 0) + 1;
                } elseif (strpos($message, '404') !== false || strpos($message, 'not found') !== false) {
                    $errorAnalysis['not_found'] = ($errorAnalysis['not_found'] ?? 0) + 1;
                } elseif (strpos($message, '500') !== false || strpos($message, 'server error') !== false) {
                    $errorAnalysis['server'] = ($errorAnalysis['server'] ?? 0) + 1;
                } elseif (strpos($message, 'database') !== false || strpos($message, 'sql') !== false) {
                    $errorAnalysis['database'] = ($errorAnalysis['database'] ?? 0) + 1;
                } else {
                    $errorAnalysis['other'] = ($errorAnalysis['other'] ?? 0) + 1;
                }
            }
            
            return $errorAnalysis;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get top performing projects
     */
    private function getTopProjects(string $period): array
    {
        $projects = $this->getProjectService()->getProjects(1, 10);
        $topProjects = [];
        
        foreach ($projects as $project) {
            $projectId = $project['id'] ?? 0;
            $processedUrls = $this->getProjectProcessedUrls($projectId);
            $totalUrls = $this->getProjectTotalUrls($projectId);
            $successRate = $totalUrls > 0 ? ($processedUrls / $totalUrls) * 100 : 0;
            
            $topProjects[] = [
                'id' => $projectId,
                'name' => $project['name'] ?? 'Unknown',
                'processed_urls' => $processedUrls,
                'success_rate' => $successRate,
            ];
        }
        
        // Sort by processed URLs descending
        usort($topProjects, fn($a, $b) => $b['processed_urls'] - $a['processed_urls']);
        
        return array_slice($topProjects, 0, 5); // Top 5 projects
    }

    /**
     * Get projects summary for table
     */
    private function getProjectsSummary(array $projects): array
    {
        $summary = [];
        
        foreach ($projects as $project) {
            $projectId = $project['id'] ?? 0;
            $totalUrls = $this->getProjectTotalUrls($projectId);
            $processedUrls = $this->getProjectProcessedUrls($projectId);
            $pendingUrls = $this->getProjectPendingUrls($projectId);
            $failedUrls = $this->getProjectFailedUrls($projectId);
            $successRate = $totalUrls > 0 ? ($processedUrls / $totalUrls) * 100 : 0;
            
            $summary[] = [
                'id' => $projectId,
                'name' => $project['name'] ?? 'Unknown',
                'description' => $project['description'] ?? '',
                'status' => $project['status'] ?? 'unknown',
                'total_urls' => $totalUrls,
                'processed_urls' => $processedUrls,
                'pending_urls' => $pendingUrls,
                'failed_urls' => $failedUrls,
                'success_rate' => round($successRate, 1),
                'last_run' => $this->getProjectLastRun($projectId),
                'next_run' => $this->getProjectNextRun($projectId),
            ];
        }
        
        return $summary;
    }

    /**
     * Get selected project details
     */
    private function getSelectedProjectDetails(int $projectId, string $period): array
    {
        $project = $this->getProjectService()->getProject($projectId);
        
        if (!$project) {
            return [];
        }
        
        $totalUrls = $this->getProjectTotalUrls($projectId);
        $processedUrls = $this->getProjectProcessedUrls($projectId);
        $pendingUrls = $this->getProjectPendingUrls($projectId);
        $failedUrls = $this->getProjectFailedUrls($projectId);
        $successRate = $totalUrls > 0 ? ($processedUrls / $totalUrls) * 100 : 0;
        
        return [
            'id' => $projectId,
            'name' => $project['name'] ?? 'Unknown',
            'description' => $project['description'] ?? '',
            'status' => $project['status'] ?? 'unknown',
            'created_at' => $project['created_at'] ?? 'Unknown',
            'schedule' => $this->getProjectSchedule($projectId),
            'total_urls' => $totalUrls,
            'processed_urls' => $processedUrls,
            'pending_urls' => $pendingUrls,
            'failed_urls' => $failedUrls,
            'success_rate' => round($successRate, 1),
            'avg_response_time' => $this->getProjectAverageResponseTime($projectId),
            'last_run' => $this->getProjectLastRun($projectId),
            'next_run' => $this->getProjectNextRun($projectId),
            'recent_activity' => $this->getProjectRecentActivity($projectId),
            'error_analysis' => $this->getProjectErrorAnalysis($projectId),
        ];
    }

    /**
     * Get project pending URLs
     */
    private function getProjectPendingUrls(int $projectId): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_urls';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE tooth_id = %d AND status = 'pending'",
            $projectId
        ));
        
        return (int) $count;
    }

    /**
     * Get project failed URLs
     */
    private function getProjectFailedUrls(int $projectId): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rake_urls';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE tooth_id = %d AND status = 'failed'",
            $projectId
        ));
        
        return (int) $count;
    }

    /**
     * Get average processing time
     */
    private function getAverageProcessingTime(): float
    {
        // Mock implementation - would typically calculate from logs
        return 2.5;
    }

    /**
     * Get overall success rate
     */
    private function getOverallSuccessRate(): float
    {
        $totalProcessed = $this->projectService->getTotalUrlsProcessed();
        $totalFailed = $this->projectService->getTotalUrlsFailed();
        $total = $totalProcessed + $totalFailed;
        
        return $total > 0 ? ($totalProcessed / $total) * 100 : 0;
    }

    /**
     * Get project average response time
     */
    private function getProjectAverageResponseTime(int $projectId): float
    {
        // Mock implementation - would typically calculate from project logs
        return 2.5;
    }

    /**
     * Get project recent activity
     */
    private function getProjectRecentActivity(int $projectId): array
    {
        try {
            $logs = $this->logService->getLogs(1, 20);
            $projectActivity = [];
            
            foreach ($logs as $log) {
                $message = $log['message'] ?? '';
                if (strpos($message, "project {$projectId}") !== false) {
                    $type = 'info';
                    if (strpos($message, 'error') !== false) $type = 'error';
                    elseif (strpos($message, 'warning') !== false) $type = 'warning';
                    elseif (strpos($message, 'success') !== false) $type = 'success';
                    
                    $projectActivity[] = [
                        'type' => $type,
                        'time' => $log['created_at'] ?? '',
                        'message' => $message,
                    ];
                }
            }
            
            return array_slice($projectActivity, 0, 10);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get project error analysis
     */
    private function getProjectErrorAnalysis(int $projectId): array
    {
        try {
            $errorLogs = $this->logService->getLogsByLevel('error', 1, 50);
            $projectErrors = [];
            
            foreach ($errorLogs as $log) {
                $message = $log['message'] ?? '';
                if (strpos($message, "project {$projectId}") !== false) {
                    $errorType = 'other';
                    if (strpos($message, 'timeout') !== false) $errorType = 'timeout';
                    elseif (strpos($message, '404') !== false) $errorType = 'not_found';
                    elseif (strpos($message, '500') !== false) $errorType = 'server';
                    
                    $projectErrors[$errorType][] = [
                        'time' => $log['created_at'] ?? '',
                        'message' => $message,
                    ];
                }
            }
            
            return $projectErrors;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get project timeline chart data
     */
    private function getProjectTimelineChart(int $projectId, string $period): array
    {
        // Mock implementation - would typically generate timeline data
        return [
            'labels' => ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5', 'Day 6', 'Day 7'],
            'datasets' => [
                [
                    'label' => 'Processed URLs',
                    'data' => [45, 52, 48, 55, 50, 47, 53],
                    'borderColor' => '#46b450',
                    'backgroundColor' => 'rgba(70, 180, 80, 0.1)',
                ],
                [
                    'label' => 'Failed URLs',
                    'data' => [5, 3, 7, 2, 4, 6, 3],
                    'borderColor' => '#d63638',
                    'backgroundColor' => 'rgba(214, 54, 56, 0.1)',
                ]
            ]
        ];
    }

    /**
     * Get error rate chart data
     */
    private function getErrorRateChart(string $period): array
    {
        // Mock implementation - would typically query database for error trends
        return [
            'labels' => ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5', 'Day 6', 'Day 7'],
            'datasets' => [
                [
                    'label' => 'Error Rate (%)',
                    'data' => [5, 3, 7, 2, 4, 6, 3],
                    'borderColor' => '#d63638',
                    'backgroundColor' => 'rgba(214, 54, 56, 0.1)',
                ]
            ]
        ];
    }

    /**
     * Get resource usage chart data
     */
    private function getResourceUsageChart(string $period): array
    {
        // Mock implementation - would typically collect system metrics
        return [
            'labels' => ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5', 'Day 6', 'Day 7'],
            'datasets' => [
                [
                    'label' => 'Memory Usage (%)',
                    'data' => [45, 52, 48, 55, 50, 47, 53],
                    'borderColor' => '#0073aa',
                    'backgroundColor' => 'rgba(0, 115, 170, 0.1)',
                ],
                [
                    'label' => 'CPU Usage (%)',
                    'data' => [30, 35, 32, 38, 33, 31, 36],
                    'borderColor' => '#00a0d2',
                    'backgroundColor' => 'rgba(0, 160, 210, 0.1)',
                ]
            ]
        ];
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
        
        $project = $this->getProjectService()->getProject($projectId);
        
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
