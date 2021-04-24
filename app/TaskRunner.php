<?php
namespace App;

class TaskRunner
{
    const TASK_CRON_NAME = 'rake-wordpress-migration-example';

    private function __construct()
    {
    }

    public static function get_instance()
    {
        if (is_null(static::$intance)) {
            static::$intance = new static();
        }
        return static::$intance;
    }


    protected $tasks = array();

    public function set_tasks()
    {
    }

    public function run()
    {
        die('zo');
    }
}
