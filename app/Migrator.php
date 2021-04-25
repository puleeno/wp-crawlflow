<?php
namespace App;

use Ramphor\Rake\Feeds\Sitemap\Sitemap;
use Ramphor\Rake\Feeds\Sitemap\SitemapIndex;
use Ramphor\Rake\Feeds\CsvFile;
use App\Feeds\GeneralFeed;

use App\Tooths\GeneralTooth;
use App\Tooths\FileTooth;

class Migrator
{
    protected static $instance;

    private function __construct()
    {
        $this->bootstrap();
        $this->init_hooks();
    }

    public static function get_instance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    protected function bootstrap()
    {
    }

    public function init_hooks()
    {
        register_activation_hook(
            RAKE_WORDPRESS_MIGRATION_EXAMPLE_PLUGIN_FILE,
            array(Installer::class, 'active')
        );
        register_deactivation_hook(
            RAKE_WORDPRESS_MIGRATION_EXAMPLE_PLUGIN_FILE,
            array(Installer::class, 'deactive')
        );

        if ($this->is_request('cron')) {
            add_action('init', array($this, 'setup_task_events'));
        }
    }

    private function is_request($type)
    {
        switch ($type) {
            case 'admin':
                return is_admin();
            case 'ajax':
                return defined('DOING_AJAX');
            case 'cron':
                return defined('DOING_CRON');
            case 'frontend':
                return ( !is_admin() || defined('DOING_AJAX') ) && ! defined('DOING_CRON');
        }
    }

    protected function is_debug()
    {
        return defined('PLUGIN_MIGRATION_DEBUG') && boolval(PLUGIN_MIGRATION_DEBUG);
    }

    public function setup_task_events()
    {
        $tasks           = new Tasks();
        $available_tasks = $tasks->get_available_tasks();

        if (count($available_tasks) > 0 || $this->is_debug()) {
            $runner = TaskRunner::get_instance();
            $runner->set_tasks($available_tasks);

            add_action(TaskRunner::TASK_CRON_NAME, array($runner, 'run'));

            if ($this->is_debug()) {
                $timestamp = wp_next_scheduled(TaskRunner::TASK_CRON_NAME);
                wp_unschedule_event(
                    $timestamp,
                    TaskRunner::TASK_CRON_NAME
                );

                do_action(TaskRunner::TASK_CRON_NAME);
            }
        }
    }

    public static function get_support_feeds()
    {
        $default_feeds = array(
            GeneralFeed::NAME  => GeneralFeed::class,
            Sitemap::NAME      => Sitemap::class,
            SitemapIndex::NAME => SitemapIndex::class,
            CsvFile::FEED_NAME => CsvFile::class,
        );

        return apply_filters(
            'migration_support_feeds',
            $default_feeds
        );
    }

    public static function get_support_tooths()
    {
        $default_tooths = array(
            GeneralTooth::NAME => GeneralTooth::class,
            FileTooth::NAME    => FileTooth::class
        );

        return apply_filters(
            'migration_support_tooths',
            $default_tooths
        );
    }
}
