<?php
namespace CrawlFlow\Processors;

use Puleeno\Rake\WordPress\Content\WordPressProcessor as WordPressSource;
use Puleeno\Rake\WordPress\Traits\WooCommerceProcessor;
use Puleeno\Rake\WordPress\Traits\WordPressProcessor;

class WordPressSourceProcessor extends WordPressSource
{
    const NAME = 'wordpress';

    use WooCommerceProcessor;
    use WordPressProcessor;

    public function execute()
    {
    }
}
