<?php
namespace CrawlFlow;

use CrawlFlow\Core\Task;

class Tasks
{
    protected $tasks = array();

    public function __construct()
    {
        $this->load_tasks();
    }

    private function loadFromContent()
    {
        $tasksDir = sprintf('%s/tasks', rtrim(constant('WP_CONTENT_DIR'), '/'));
        $globalTasksFile = sprintf('%s/tasks.php', rtrim(constant('WP_CONTENT_DIR'), '/'));
        $taskFiles = glob($tasksDir . '/*.php');
        if (!file_exists($globalTasksFile) && count($taskFiles) <= 0) {
            return null;
        }

        $tasks = [];

        if (file_exists($globalTasksFile)) {
            // Create tasks from global
            $globalTasks = require $globalTasksFile;
            if (is_array($globalTasks)) {
                foreach ($globalTasks as $task) {
                    if (!isset($task['id'])) {
                        continue;
                    }
                    $tasks[$task['id']] = $task;
                }
            }
        }

        foreach ($taskFiles as $taskFile) {
            $task = require $taskFile;
            if (!is_array($task)) {
                continue;
            }

            if (!isset($task['id'])) {
                error_log(sprintf('The task is do not have the ID: %s', json_encode($task)));
                continue;
            }
            $id = $task['id'];
            $task = array_merge(
                isset($tasks[$id]) ? $tasks[$id] : [],
                $task
            );

            $tasks[$id] = $task;
        }

        return $tasks;
    }

    private function loadFromDefaultPath()
    {
        $configDir = dirname(RAKE_WORDPRESS_MIGRATION_EXAMPLE_PLUGIN_FILE);
        $taskConfigFile = sprintf('%s/configs/tasks.php', $configDir);

        $tasks = require $taskConfigFile;
        if (!is_array($tasks)) {
            return [];
        }
        return $tasks;
    }

    protected function get_tasks_from_config()
    {
        $tasks = $this->loadFromContent();
        if (!is_null($tasks)) {
            return $tasks;
        }
        return $this->loadFromDefaultPath();
    }

    public function load_tasks()
    {
        $raw_tasks = apply_filters(
            'migration_prepare_tasks',
            $this->get_tasks_from_config()
        );

        foreach ($raw_tasks as $index => $raw_task) {
            $raw_task = wp_parse_args($raw_task, array(
                'id' => '',
                'format' => '',
                'type' => '',
                'data_rules' => array(),
                'sources' => array(),
            ));

            $raw_task = apply_filters('migration_setup_task', $raw_task, $raw_tasks, $this);

            if (empty($raw_task['id']) || empty($raw_task['format'])) {
                error_log(sprintf(
                    __('Task #%d is invalid [%s]', 'wp-crawflow'),
                    $index,
                    print_r($raw_tasks[$index], true)
                ));
                continue;
            }

            $task = new Task($raw_task['id'], $raw_task['format'], array_filter($raw_task, function ($key) {
                return !in_array($key, array('id', 'format', 'sources', 'data_rules'));
            }, ARRAY_FILTER_USE_KEY));
            if (trim($raw_task['type']) != false) {
                $task->set_type($raw_task['type']);
            }
            if (trim($raw_task['source_cms']) != false) {
                $task->set_cms_name($raw_task['source_cms']);
            }

            $task->set_data_rules($raw_task['data_rules']);
            $task->set_sources($raw_task['sources']);
            if (isset($raw_task['url_validator'])) {
                $task->get_url_validator($raw_task['url_validator']);
            }

            if (isset($raw_task['data_type_checker'])) {
                $task->set_data_type_checker($raw_task['data_type_checker']);
                $task->setup_data_type_checker();
            }

            if (isset($raw_task['product_categories_filter'])) {
                $task->set_product_categories_filter($raw_task['product_categories_filter']);
                $task->setup_product_categories_filter();
            }

            if ($task->validate()) {
                array_push($this->tasks, $task);
            } else {
                error_log(sprintf(
                    __('Task "%s" is invalid configurations', 'wp-crawflow'),
                    $raw_task['id']
                ));
            }
        }
    }

    public function get_available_tasks()
    {
        return $this->tasks;
    }
}
