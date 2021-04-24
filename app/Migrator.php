<?php
namespace App;

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
            add_action('init', array($this, 'setupTaskEvents'));
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
                return ( ! is_admin() || defined('DOING_AJAX') ) && ! defined('DOING_CRON');
        }
    }

    public function setupTaskEvents()
    {
        $tasks          = new Tasks();
        $availableTasks = $tasks->get_available_tasks();

        if (count($availableTasks) > 0) {
            $runner = TaskRunner::get_instance();
            $runner->set_tasks($availableTasks);

            add_action(TaskRunner::TASK_CRON_NAME, array($runner, 'run'));
        }
    }
}
