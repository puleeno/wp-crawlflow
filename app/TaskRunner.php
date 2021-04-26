<?php
namespace App;

use Ramphor\Rake\Rake;
use Puleeno\Rake\WordPress\Driver;
use App\Core\Task;

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
        if (empty($this->tasks)) {
            return;
        }

        $rake = new Rake(static::RAKE_ID, new Driver());

        foreach ($this->tasks as $task) {
            $tooth   = $task->create_tooth();
            $tooth->setRake($rake);
            if (is_null($tooth)) {
                continue;
            }

            $data_rules = $task->get_data_rules();

            foreach ($data_rules as $data_rule) {
                $tooth->addMappingField(
                    $data_rule->get_field_name(),
                    $data_rule->create_mapping_field()
                );
            }

            $sources = $task->get_sources();

            foreach ($sources as $source) {
                $feed = $source->create_feed();
                if (is_null($feed)) {
                    continue;
                }
                $feed->setTooth($tooth);
                $tooth->addFeed($feed);
            }

            $rake->addTooth($tooth);
        }

        // Execute all tooths
        $rake->execute();
    }
}
