<?php

namespace CrawlFlow\Tooths;

if (!defined('ABSPATH')) {
    exit('Cheatin huh?');
}

use Ramphor\Rake\Abstracts\Tooth;
use Puleeno\Rake\WordPress\Traits\WordPressTooth;

class FileTooth extends Tooth
{
    const NAME = 'file';

    use WordPressTooth;

    protected $csvHasHeader = false;

    public function parseArgs($args)
    {
        if (isset($args['csv_has_header'])) {
            $this->csvHasHeader = boolval($args['csv_has_header']);
        }
    }

    function parserOptions()
    {
        $options = array();
        if (in_array($this->toothFormat, array(static::FORMAT_CSV))) {
            $options['header'] = $this->csvHasHeader;
        }

        return $options;
    }
}
