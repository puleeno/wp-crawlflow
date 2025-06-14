<?php

namespace CrawlFlow\Feeds;

if (!defined('ABSPATH')) {
    exit('Cheatin huh?');
}

use Ramphor\Rake\Abstracts\Feed;

class GeneralFeed extends Feed
{
    const NAME = 'general';

    public function get_name()
    {
        return self::NAME;
    }

    public function fetch()
    {
    }

    public function valid()
    {
    }

    public function rewind()
    {
    }

    public function next()
    {
    }
}
