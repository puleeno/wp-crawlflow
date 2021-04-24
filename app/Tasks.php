<?php
namespace App;

class Tasks
{
    protected $tasks = array();

    public function __construct()
    {
        $this->load_tasks();
    }

    public function load_tasks()
    {
        $raw_tasks = array(
            array(
                'id' => 'quattranmy',
                'data_rules' => array(),
                'sources' => array(
                    'type' => 'csv_file'
                ),
            )
        );

        foreach ($raw_tasks as $index => $raw_task) {
            $raw_task = wp_parse_args($raw_task, array(
                'id' => '',
                'data_rules' => array(),
                'sources' => array(),
            ));
            if (empty($raw_task['id'])) {
                error_log(sprintf(
                    __('Task #%d is invalid [%s]', 'rake-wordpress-migration-example'),
                    $index,
                    print_r($raw_tasks[$index], true)
                ));
                continue;
            }

            $task = new Task($raw_task['id']);
            $task->set_data_rules($raw_tasks['data_rules']);

            if ($task->validate()) {
                array_push($this->tasks, $task);
            } else {
                error_log(sprintf(
                    __('Task "%s" is invalid configurations', 'rake-wordpress-migration-example'),
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
