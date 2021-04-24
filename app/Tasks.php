<?php
namespace App;

class Tasks
{
    protected $tasks = array();

    public function get_available_tasks()
    {
        return $this->tasks;
    }
}
