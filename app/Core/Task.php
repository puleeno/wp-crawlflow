<?php

namespace CrawlFlow\Core;

if (!defined('ABSPATH')) {
    exit('Cheating huh?');
}

use Ramphor\Rake\Abstracts\Tooth;
use CrawlFlow\Migrator;
use CrawlFlow\Utils\Str;

class Task
{
    protected $id;
    protected $format;
    protected $type = 'general';
    protected $source_cms = 'general';
    protected $url_validator  = true;
    protected $url_use_splash = false;
    protected $data_type_checker = null;
    protected $product_categories_filter = null;

    protected $args = array();

    /**
     * @var \CrawlFlow\Core\Source[]
     */
    protected $sources = array();

    protected $data_rules = array();

    public function __construct($task_id, $format, $args = array())
    {
        $this->id     = $task_id;
        $this->format = $format;

        if (!empty($args)) {
            $this->args = $args;
        }
    }

    public function set_type($type)
    {
        $this->type = $type;
    }

    public function create_processor()
    {
        $support_processors = Migrator::get_support_processors();

        if (isset($support_processors[$this->source_cms])) {
            $clsProcessor = $support_processors[$this->source_cms];
            $processor = new $clsProcessor();

            return $processor;
        }
    }

    public function transformFormat()
    {
        $formats = array(
            'csv' => Tooth::FORMAT_CSV,
            'html' => Tooth::FORMAT_HTML
        );
        if (isset($formats[$this->format])) {
            return $formats[$this->format];
        }
    }

    /**
     * @return \Ramphor\Rake\Constracts\Tooth|\CrawlFlow\Tooths\CrawlFlowTooth
     */
    public function create_tooth()
    {
        $support_tooths = Migrator::get_support_tooths();

        if (!isset($support_tooths[$this->type])) {
            error_log(sprintf(
                __('The "%s" tooth is not supported', 'wp-crawflow'),
                $this->type
            ));
            return;
        }

        $processor = $this->create_processor();
        if (!$processor) {
            error_log(__('Processor is not created to register to tooth', 'wp-crawflow'));
            return;
        }

        $clsTooth = $support_tooths[$this->type];

        /**
         * @var \Ramphor\Rake\Abstracts\Tooth
         */
        $tooth = new $clsTooth($this->id);

        $parseArgsCallback = array($tooth, 'parseArgs');

        if (is_callable($parseArgsCallback)) {
            call_user_func($parseArgsCallback, $this->args);
        }

        $format = $this->transformFormat();
        if ($format) {
            $tooth->setFormat($format);
        }
        $tooth->registerProcessor($processor);
        $tooth->setUrlValidator(
            $this->get_url_validator()
        );
        $tooth->setUrlUseLastSplash(
            $this->url_has_use_splash()
        );

        return $tooth;
    }

    public function set_cms_name($cms_name)
    {
        if ($cms_name) {
            $this->source_cms = $cms_name;
        }
    }

    public function get_cms_name()
    {
        return $this->source_cms;
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
        foreach ($rules as $field_name => $rule) {
            $field_name = Str::convertToCamel($field_name);
            $data_rule  = new DataRule($field_name, $rule);
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

    public function set_url_validator($validator_or_value)
    {
        $this->url_validator = $validator_or_value;
    }

    public function get_url_validator()
    {
        return $this->url_validator;
    }

    public function url_use_slash($used = false)
    {
        $this->url_use_splash = boolval($used);
    }

    public function url_has_use_splash()
    {
        return $this->url_use_splash;
    }

    public function set_data_type_checker($callable)
    {
        $this->data_type_checker = $callable;
    }

    public function setup_data_type_checker()
    {
        if (is_callable($this->data_type_checker)) {
            add_filter("pre_the_migration_plugin_{$this->id}_data_type", $this->data_type_checker, 10, 2);
        }
    }

    public function set_product_categories_filter($filter)
    {
        $this->product_categories_filter = $filter;
    }

    public function setup_product_categories_filter()
    {
        if (is_callable($this->product_categories_filter)) {
            add_filter("{$this->id}_product_categories", $this->product_categories_filter, 10, 3);
        }
    }
}
