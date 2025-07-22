<?php

namespace CrawlFlow\Kernel;

use Rake\Kernel\AbstractKernel;
use CrawlFlow\Admin\DashboardService;

/**
 * CrawlFlow Dashboard Kernel
 * Manages dashboard rendering based on current WordPress admin screen
 */
class CrawlFlowDashboardKernel extends AbstractKernel
{
    /**
     * @var DashboardService
     */
    private $dashboardService;

    /**
     * @var \CrawlFlow\Admin\CrawlFlowController
     */
    private $controller;

    /**
     * @var string
     */
    private $currentScreen;

    /**
     * @var array
     */
    private $screenData;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->dashboardService = new DashboardService();
        $this->controller = new \CrawlFlow\Admin\CrawlFlowController();
        $this->detectCurrentScreen();
        $this->loadScreenData();
    }

    /**
     * Register bootstrappers for dashboard kernel
     */
    protected function registerBootstrappers(): void
    {
        $this->addCustomBootstrapper(\CrawlFlow\Bootstrapper\CrawlFlowCoreBootstrapper::class);
        $this->addCustomBootstrapper(\CrawlFlow\Bootstrapper\CrawlFlowDashboardBootstrapper::class);
    }

    /**
     * Initialize dashboard kernel
     */
    public function initialize(): void
    {
        // Screen detection and data loading already done in constructor
        // This method can be used for additional initialization if needed
    }

    /**
     * Detect current WordPress admin screen
     */
    private function detectCurrentScreen(): void
    {
        global $pagenow, $plugin_page, $typenow, $taxnow;

        // Get current screen from WordPress
        $screen = get_current_screen();

        if ($screen) {
            $this->currentScreen = $screen->id;
        } else {
            // Fallback detection
            if (isset($_GET['page'])) {
                $this->currentScreen = sanitize_text_field($_GET['page']);
            } elseif ($pagenow) {
                $this->currentScreen = $pagenow;
            } else {
                $this->currentScreen = 'dashboard';
            }
        }
    }

    /**
     * Load data for current screen
     */
    private function loadScreenData(): void
    {
        $this->screenData = $this->dashboardService->getScreenData($this->currentScreen);
    }

    /**
     * Render dashboard content based on current screen
     */
    public function render(): void
    {
        // Use controller to render based on current screen
        $this->controller->renderPage();
    }

    /**
     * Get template path for current screen
     */
    private function getTemplateForScreen(): ?string
    {
        $templateMap = [
            'crawlflow' => 'dashboard.php',
            'crawlflow-logs' => 'logs.php',
            'crawlflow-projects' => 'projects.php',
            'crawlflow-analytics' => 'analytics.php',
        ];

        $template = $templateMap[$this->currentScreen] ?? null;

        if ($template) {
            return CRAWLFLOW_PLUGIN_DIR . 'templates/admin/' . $template;
        }

        return null;
    }

        /**
     * Render specific template
     */
    private function renderTemplate(string $templatePath): void
    {
        $renderer = new \CrawlFlow\Admin\DashboardRenderer();

        // Render based on screen type
        switch ($this->currentScreen) {
            case 'crawlflow':
                $renderer->renderDashboardOverview($this->screenData);
                break;
            case 'crawlflow-logs':
                $renderer->renderLogs($this->screenData);
                break;
            default:
                $renderer->renderDashboardOverview($this->screenData);
                break;
        }
    }

    /**
     * Render default dashboard
     */
    private function renderDefaultDashboard(): void
    {
        $renderer = new \CrawlFlow\Admin\DashboardRenderer();
        $renderer->renderDashboardOverview($this->screenData);
    }

    /**
     * Get current screen
     */
    public function getCurrentScreen(): string
    {
        return $this->currentScreen;
    }

    /**
     * Get screen data
     */
    public function getScreenData(): array
    {
        return $this->screenData;
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
     * Set current screen (for testing or manual override)
     */
    public function setCurrentScreen(string $screen): void
    {
        $this->currentScreen = $screen;
        $this->loadScreenData();
    }
}