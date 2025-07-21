<?php

namespace CrawlFlow\Admin;

class AdminController
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'registerMenu']);
// Initialize MigrationController
        if (class_exists('CrawlFlow\Admin\MigrationController')) {
            new \CrawlFlow\Admin\MigrationController();
        }
    }

    public function registerMenu()
    {
        add_menu_page(__('CrawlFlow', 'crawlflow'), __('CrawlFlow', 'crawlflow'), 'manage_options', 'crawlflow', [$this, 'dashboardPage'], 'dashicons-download', 30);
// Chỉ thêm submenu cho trang Project Editor, KHÔNG thêm lại submenu cho dashboard
        add_submenu_page('crawlflow', __('Tạo/Sửa Dự Án', 'crawlflow'), __('Tạo/Sửa Dự Án', 'crawlflow'), 'manage_options', 'crawlflow-project-editor', [$this, 'projectEditorPage']);
        add_submenu_page('crawlflow', __('Cài đặt', 'crawlflow'), __('Cài đặt', 'crawlflow'), 'manage_options', 'crawlflow-settings', [$this, 'settingsPage']);
    }

    public function dashboardPage()
    {
        include CRAWLFLOW_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    public function projectEditorPage()
    {
        include CRAWLFLOW_PLUGIN_DIR . 'templates/admin/project-editor.php';
    }

    public function settingsPage()
    {
        include CRAWLFLOW_PLUGIN_DIR . 'templates/admin/settings.php';
    }
}
