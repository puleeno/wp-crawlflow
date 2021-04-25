<?php
namespace App\Feeds;

use Ramphor\Rake\Abstracts\Feed;

class GeneralFeed extends Feed
{
    const NAME = 'general';

    public function get_name()
    {
        return self::NAME;
    }

    public function execute()
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
