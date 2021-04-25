<?php
namespace App\Core;

use App\Migrator;

class Task
{
    protected $id;
    protected $format;
    protected $type = 'general';
    protected $source_cms = 'general';

    protected $sources = array();
    protected $data_rules = array();

    public function __construct($task_id, $format)
    {
        $this->id     = $task_id;
        $this->format = $format;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function create_processor()
    {
    }

    public function create_tooth()
    {
        $support_tooths = Migrator::get_support_tooths();

        if (!isset($support_tooths[$this->type])) {
            error_log(sprintf(
                __('The "%s" tooth is not supported', 'rake-wordpress-migration-example'),
                $this->type
            ));
            return;
        }

        $processor = $this->create_processor();
        if (!$processor) {
            error_log(__('Processor is not created to register to tooth', 'rake-wordpress-migration-example'));
            return;
        }

        $clsTooth = $support_tooths[$this->type];
        $tooth = new $clsTooth($this->id);

        $tooth->registerProcessor($processor);

        return $tooth;
    }

    public function set_cms_name($cms_name)
    {
        if ($cms_name) {
            $this->source_cms = $cms_name;
        }
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
