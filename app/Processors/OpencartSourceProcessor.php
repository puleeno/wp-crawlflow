<?php

namespace CrawlFlow\Processors;

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
