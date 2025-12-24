<?php

namespace CrawlFlow\Helper;

use CrawlFlow\Admin\MigrationService;

class DatabaseStatusChecker
{
    public function registerHooks(): void
    {
        add_action('admin_notices', [$this, 'maybeShowNotice']);
    }

    public function checkDatabaseStatus(): array
    {
        $service = $this->getMigrationService();
        $status = $service->checkMigrationStatus();
        $count = 0;
        foreach ($status as $s) {
            if ($s['needs_migration']) {
                $count++;
            }
        }
        return [
            'healthy' => $count === 0,
            'migration_count' => $count,
            'status' => $status,
        ];
    }

    public function maybeShowNotice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $isDashboard = false;
        if ($screen) {
            $isDashboard = $screen->id === 'dashboard';
        }
        global $pagenow;
        if (!$isDashboard && $pagenow !== 'index.php') {
            return;
        }
        $db = $this->checkDatabaseStatus();
        if ($db['healthy']) {
            return;
        }
        $count = (int) ($db['migration_count'] ?? 0);
        $url = admin_url('admin-post.php');
        echo '<div class="notice notice-warning"><p>';
        echo esc_html(sprintf('CrawlFlow: Có %d bảng cần migration.', $count));
        echo '</p><form method="post" action="' . esc_url($url) . '">';
        wp_nonce_field('crawlflow_admin_nonce', 'nonce');
        echo '<input type="hidden" name="action" value="crawlflow_run_migration">';
        echo '<input type="submit" class="button button-primary" value="' . esc_attr__('Run Migration', 'crawlflow') . '">';
        echo '</form></div>';
    }

    private function getMigrationService(): MigrationService
    {
        return new MigrationService();
    }
}
