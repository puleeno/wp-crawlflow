<?php

namespace CrawlFlow\Processors;

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
