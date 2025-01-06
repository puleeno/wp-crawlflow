<?php
namespace CrawlFlow;

use Ramphor\Rake\Rake;
use Puleeno\Rake\WordPress\Driver;
use CrawlFlow\Core\Task;

class TaskRunner
{
    const TASK_CRON_NAME = 'wp-crawflow';
    const RAKE_ID        = 'wp-crawflow';

    protected static $instance;

    /**
     * @var \CrawlFlow\Core\Task[]
     */
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
            if (is_null($tooth)) {
                continue;
            }

            $tooth->setRake($rake);
            $data_rules = $task->get_data_rules();

            if (method_exists($tooth, 'wordpressBootstrap')) {
                call_user_func([$tooth, 'wordpressBootstrap']);
            }

            foreach ($data_rules as $data_rule) {
                $tooth->addMappingField(
                    $data_rule->get_field_name(),
                    $data_rule->create_mapping_field()
                );
            }

            $sources = $task->get_sources();

            foreach ($sources as $source) {
                $feed = $source->create_feed($tooth);
                if (is_null($feed)) {
                    continue;
                }

                if (method_exists($feed, 'setUrl') && $source->getArgs('url')) {
                    $feed->setUrl($source->getArgs('url'));
                }
                $tooth->addFeed($feed);
            }

            $rake->addTooth($tooth);
        }

        // Execute all tooths
        $rake->execute();
    }
}
