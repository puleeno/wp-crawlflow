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

        add_action('init', array($this, 'setupTaskEvents'));
    }

    public function setupTaskEvents()
    {
        $tasks = new Tasks();
        $availableTasks = $tasks->get_available_tasks();
        if (count($availableTasks) > 0) {
            $runner = new TaskRunner();
            $runner->set_tasks($availableTasks);

            add_action(TaskRunner::TASK_CRON_NAME, array($runner, 'run'));
        }
    }
}
