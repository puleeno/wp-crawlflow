<?php
namespace App\Core;

class Source
{
    protected $type = 'general';

    public function __construct($args)
    {
    }

    public function set_type($type)
    {
    }

    public function create_feed()
    {
        $support_feeds = Migrator::get_support_feeds();
        if (isset($support_feeds[$this->type])) {
            $clsFeed = $support_feeds[$this->type];
            $feed    = new $clsFeed();
        }
    }

    public function validate()
    {
    }
}
