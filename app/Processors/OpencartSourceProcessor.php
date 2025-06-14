<?php

namespace CrawlFlow\Processors;

if (!defined('ABSPATH')) {
    exit('Cheating huh?');
}

use Puleeno\Rake\WordPress\Traits\Content\OpencartDataSource;
use Puleeno\Rake\WordPress\Traits\WooCommerceProcessor;
use Puleeno\Rake\WordPress\Traits\WordPressProcessor;

class OpencartSourceProcessor extends CrawlFlowProcessor
{
    const NAME = 'opencart';

    use WooCommerceProcessor;
    use WordPressProcessor;

    // Data sources
    use OpencartDataSource;
}
