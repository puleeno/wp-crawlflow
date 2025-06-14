<?php

namespace CrawlFlow\Processors;

if (!defined('ABSPATH')) {
    exit('Cheating huh?');
}

use Puleeno\Rake\WordPress\Traits\WooCommerceProcessor;
use Puleeno\Rake\WordPress\Traits\WordPressProcessor;
use Puleeno\Rake\WordPress\Traits\Content\WordPressDataSource;

class WordPressSourceProcessor extends CrawlFlowProcessor
{
    const NAME = 'wordpress';

    use WooCommerceProcessor;
    use WordPressProcessor;

    use WordPressDataSource;
}
