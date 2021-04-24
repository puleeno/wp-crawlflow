<?php
namespace App;

class Task
{
    protected $id;

    protected $sources = array();
    protected $data_rules = array();

    public function __construct($task_id)
    {
        $this->id = $task_id;
    }


    public function create_tooth($rake)
    {
        $support_tooths = Migrator::get_support_tooths();
    }

    public function add_source($name)
    {
    }

    public function get_sources()
    {
        return $this->sources;
    }

    public function add_new_rule()
    {
    }

    public function set_data_rules($rules)
    {
    }

    public function get_data_rules()
    {
        return $this->data_rules;
    }

    public function validate()
    {
        return true;
    }
}
