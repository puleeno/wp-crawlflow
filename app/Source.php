<?php
namespace App;

class Source
{
    protected $type = 'general';

    public function setType($type)
    {
    }

    public function createFeed()
    {
        $support_feeds = Migrator::get_support_feeds();
        if (isset($support_feeds[$this->type])) {
            $clsFeed = $support_feeds[$this->type];
            $feed    = new $clsFeed();
        }
    }
}
