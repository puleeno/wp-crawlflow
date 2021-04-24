<?php
namespace App;

class Task
{
    protected $id;

    protected $sources = array();

    public function __construct($task_id)
    {
        $this->id = $task_id;
    }

    public function createTooth()
    {
    }

    public function addSource($name)
    {
    }

    public function getSources()
    {
        return $this->sources;
    }
}
