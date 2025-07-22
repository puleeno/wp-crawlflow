<?php

namespace CrawlFlow\ServiceProvider;

use Rake\ServiceProvider\AbstractServiceProvider;
use Rake\Rake;

/**
 * CrawlFlow Dashboard Service Provider
 * Registers dashboard-related services
 */
class CrawlFlowDashboardServiceProvider extends AbstractServiceProvider
{
    /**
     * Register dashboard services
     */
    protected function registerServices(Rake $app): void
    {
        // Register dashboard kernel
        $app->singleton('CrawlFlow\Kernel\CrawlFlowDashboardKernel', function ($app) {
            return new \CrawlFlow\Kernel\CrawlFlowDashboardKernel();
        });

        // Register dashboard controller
        $app->singleton('CrawlFlow\Admin\DashboardController', function ($app) {
            return new \CrawlFlow\Admin\DashboardController();
        });

        // Register dashboard renderer
        $app->singleton('CrawlFlow\Admin\DashboardRenderer', function ($app) {
            return new \CrawlFlow\Admin\DashboardRenderer();
        });
    }

    /**
     * Boot dashboard services
     */
    protected function bootServices(Rake $app): void
    {
        // Initialize dashboard kernel
        $kernel = $app->make('CrawlFlow\Kernel\CrawlFlowDashboardKernel');
        $kernel->initialize();

        // Register dashboard hooks
        $this->registerDashboardHooks($app);
    }

    /**
     * Register dashboard hooks
     */
    private function registerDashboardHooks(Rake $app): void
    {
        // Add action to render dashboard content
        add_action('crawlflow_render_dashboard', function ($screen = null) use ($app) {
            $this->renderDashboard($app, $screen);
        });

        // Add filter to modify dashboard data
        add_filter('crawlflow_dashboard_data', function ($data, $screen) use ($app) {
            return $this->modifyDashboardData($app, $data, $screen);
        }, 10, 2);

        // Add action for dashboard AJAX handlers
        add_action('wp_ajax_crawlflow_dashboard_action', function () use ($app) {
            $this->handleDashboardAjax($app);
        });
    }

    /**
     * Render dashboard content
     */
    private function renderDashboard(Rake $app, ?string $screen = null): void
    {
        $kernel = $app->make('CrawlFlow\Kernel\CrawlFlowDashboardKernel');

        if ($screen) {
            // Override current screen for rendering
            $kernel->setCurrentScreen($screen);
        }

        $kernel->render();
    }

    /**
     * Modify dashboard data
     */
    private function modifyDashboardData(Rake $app, array $data, string $screen): array
    {
        // Allow other plugins to modify dashboard data
        $data = apply_filters('crawlflow_dashboard_data_' . $screen, $data);

        return $data;
    }

    /**
     * Handle dashboard AJAX requests
     */
    private function handleDashboardAjax(Rake $app): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'crawlflow_admin_nonce')) {
            wp_die('Security check failed');
        }

        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'crawlflow_refresh_stats':
                $this->handleRefreshStats($app);
                break;

            case 'crawlflow_get_project_data':
                $this->handleGetProjectData($app);
                break;

            case 'crawlflow_save_project':
                $this->handleSaveProject($app);
                break;

            default:
                wp_send_json_error('Invalid action');
                break;
        }
    }

    /**
     * Handle refresh stats AJAX
     */
    private function handleRefreshStats(Rake $app): void
    {
        $dashboardService = $app->make('CrawlFlow\Admin\DashboardService');
        $data = $dashboardService->getScreenData('crawlflow');

        wp_send_json_success($data);
    }

    /**
     * Handle get project data AJAX
     */
    private function handleGetProjectData(Rake $app): void
    {
        $project_id = $_POST['project_id'] ?? null;

        if (!$project_id) {
            wp_send_json_error('Project ID is required');
        }

        $projectService = $app->make('CrawlFlow\Admin\ProjectService');
        $project = $projectService->getProject($project_id);

        if (!$project) {
            wp_send_json_error('Project not found');
        }

        wp_send_json_success($project);
    }

    /**
     * Handle save project AJAX
     */
    private function handleSaveProject(Rake $app): void
    {
        $project_data = $_POST['project_data'] ?? null;

        if (!$project_data) {
            wp_send_json_error('Project data is required');
        }

        $projectService = $app->make('CrawlFlow\Admin\ProjectService');
        $result = $projectService->saveProject($project_data);

        if ($result) {
            wp_send_json_success('Project saved successfully');
        } else {
            wp_send_json_error('Failed to save project');
        }
    }
}