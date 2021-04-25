<?php
namespace App\Core;

use App\Migrator;

class Task
{
    protected $id;
    protected $format;

    protected $sources = array();
    protected $data_rules = array();

    public function __construct($task_id, $format)
    {
        $this->id     = $task_id;
        $this->format = $format;
    }

    public function create_tooth($rake)
    {
        $support_tooths = Migrator::get_support_tooths();
    }

    public function add_source($source)
    {
        if (is_a($source, Source::class)) {
            array_push($this->sources, $source);
        }
    }

    public function set_sources($sources)
    {
        if (!is_array($sources)) {
            return;
        }

        foreach ($sources as $source) {
            $s = new Source($source);
            if (!$s->validate()) {
                continue;
            }
            $this->add_source($s);
        }
    }

    public function get_sources()
    {
        return $this->sources;
    }

    public function add_new_rule($rule)
    {
        if (is_a($rule, DataRule::class)) {
            array_push($this->data_rules, $rule);
        }
    }

    public function set_data_rules($rules)
    {
        if (!is_array($rules)) {
            return;
        }
        foreach ($rules as $rule) {
            $data_rule = new DataRule($rule);
            if (!$data_rule->validate()) {
                continue;
            }
            $this->add_new_rule($data_rule);
        }
    }

    public function get_data_rules()
    {
        return $this->data_rules;
    }

    public function validate()
    {
        return ! empty($this->sources) && ! empty($this->data_rules);
    }
}
