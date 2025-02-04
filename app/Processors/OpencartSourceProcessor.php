<?php

namespace CrawlFlow\Processors;

use Puleeno\Rake\WordPress\Content\OpencartProcessor as OpencartSource;
use Puleeno\Rake\WordPress\Traits\WooCommerceProcessor;
use Puleeno\Rake\WordPress\Traits\WordPressProcessor;
use Ramphor\Rake\ProcessResult;

class OpencartSourceProcessor extends OpencartSource
{
    const NAME = 'opencart';

    use WooCommerceProcessor;
    use WordPressProcessor;

    public function process(): ProcessResult
    {
        return ProcessResult::createErrorResult('The Opencart source processor is not implemented');
    }
}
