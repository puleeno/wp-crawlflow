<?php
namespace CrawlFlow\Processors;

use Puleeno\Rake\WordPress\Content\OpencartProcessor as OpencartSource;
use Puleeno\Rake\WordPress\Traits\WooCommerceProcessor;
use Puleeno\Rake\WordPress\Traits\WordPressProcessor;

class OpencartSourceProcessor extends OpencartSource
{
    const NAME = 'opencart';

    use WooCommerceProcessor;
    use WordPressProcessor;

    public function execute()
    {
    }
}
