<?php

namespace CrawlFlow\Bootstrapper;

use Rake\Bootstrapper\BootstrapperInterface;
use Rake\Rake;

/**
 * CrawlFlow Dashboard Bootstrapper
 * Loads dashboard-related services and configurations
 */
class CrawlFlowDashboardBootstrapper implements BootstrapperInterface
{
    /**
     * Bootstrap dashboard services
     */
    public function bootstrap(Rake $app): void
    {
        // Register dashboard service provider
        $app->register(\CrawlFlow\ServiceProvider\CrawlFlowDashboardServiceProvider::class);

        // Register dashboard-related services
        $app->singleton('CrawlFlow\Admin\DashboardService', function ($app) {
            return new \CrawlFlow\Admin\DashboardService();
        });

        $app->singleton('CrawlFlow\Admin\ProjectService', function ($app) {
            return new \CrawlFlow\Admin\ProjectService();
        });

        $app->singleton('CrawlFlow\Admin\LogService', function ($app) {
            return new \CrawlFlow\Admin\LogService();
        });

        // Register dashboard hooks
        $this->registerDashboardHooks($app);
    }

    /**
     * Register dashboard-related WordPress hooks
     */
    private function registerDashboardHooks(Rake $app): void
    {
        // Add dashboard widget to WordPress admin
        add_action('wp_dashboard_setup', function () use ($app) {
            wp_add_dashboard_widget(
                'crawlflow_dashboard_widget',
                'CrawlFlow Status',
                function () use ($app) {
                    $this->renderDashboardWidget($app);
                }
            );
        });

        // Add admin menu hooks
        add_action('admin_menu', function () use ($app) {
            $this->registerAdminMenu($app);
        });

        // Add admin scripts and styles
        add_action('admin_enqueue_scripts', function ($hook) use ($app) {
            $this->enqueueAdminAssets($hook, $app);
        });
    }

    /**
     * Render dashboard widget
     */
    private function renderDashboardWidget(Rake $app): void
    {
        $dashboardService = $app->make('CrawlFlow\Admin\DashboardService');
        $data = $dashboardService->getScreenData('crawlflow');

        ?>
        <div class="crawlflow-dashboard-widget">
            <div class="crawlflow-stats">
                <div class="stat-item">
                    <span class="stat-label">Active Projects:</span>
                    <span class="stat-value"><?php echo esc_html($data['active_projects'] ?? 0); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">URLs Processed:</span>
                    <span class="stat-value"><?php echo esc_html($data['total_urls_processed'] ?? 0); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Pending URLs:</span>
                    <span class="stat-value"><?php echo esc_html($data['total_urls_pending'] ?? 0); ?></span>
                </div>
            </div>
            <p>
                <a href="<?php echo admin_url('admin.php?page=crawlflow'); ?>" class="button button-primary">
                    View Dashboard
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Register admin menu
     */
    private function registerAdminMenu(Rake $app): void
    {
        // Main menu is already registered in CrawlFlowController
        // This is for additional menu items if needed
    }

    /**
     * Enqueue admin assets
     */
    private function enqueueAdminAssets(string $hook, Rake $app): void
    {
        // Only load on CrawlFlow pages
        if (strpos($hook, 'crawlflow') === false) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'crawlflow-admin',
            CRAWLFLOW_PLUGIN_URL . 'assets/css/admin.css',
            [],
            CRAWLFLOW_VERSION
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'crawlflow-admin',
            CRAWLFLOW_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            CRAWLFLOW_VERSION,
            true
        );

        // Localize script
        wp_localize_script('crawlflow-admin', 'crawlflowAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'adminUrl' => admin_url(),
            'nonce' => wp_create_nonce('crawlflow_admin_nonce'),
            'strings' => [
                'confirmDelete' => __('Are you sure you want to delete this item?', 'crawlflow'),
                'saving' => __('Saving...', 'crawlflow'),
                'saved' => __('Saved successfully!', 'crawlflow'),
                'error' => __('An error occurred.', 'crawlflow'),
            ],
        ]);
    }
}