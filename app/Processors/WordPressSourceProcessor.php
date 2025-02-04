<?php

namespace CrawlFlow\Processors;

use Puleeno\Rake\WordPress\Content\WordPressProcessor as WordPressSource;
use Puleeno\Rake\WordPress\Traits\WooCommerceProcessor;
use Puleeno\Rake\WordPress\Traits\WordPressProcessor;
use Ramphor\Rake\ProcessResult;

class WordPressSourceProcessor extends WordPressSource
{
    const NAME = 'wordpress';

    use WooCommerceProcessor;
    use WordPressProcessor;

    public function process(): ProcessResult
    {
        return ProcessResult::createErrorResult('The WordPress source processor is not implemented');
    }
}
