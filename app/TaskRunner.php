<?php
namespace App;

use Ramphor\Rake\Rake;
use Puleeno\Rake\WordPress\Driver;

class TaskRunner
{
    const TASK_CRON_NAME = 'rake-wordpress-migration-example';
    const RAKE_ID        = 'rake-wordpress-migration-example';

    protected static $instance;

    protected $tasks = array();

    private function __construct()
    {
    }

    public static function get_instance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function set_tasks($tasks)
    {
        foreach ($tasks as $task) {
            if (is_a($task, Task::class)) {
                $this->tasks[] = $task;
            }
        }
    }

    public function run()
    {
        die('zo');
        $rake = new Rake(static::RAKE_ID, new Driver());

        foreach ($this->tasks as $task) {
            $tooth = $task->createTooth();

            $sources = $task->getSources();
            foreach ($sources as $source) {
                $source->createFeed();
            }
        }
    }
}
