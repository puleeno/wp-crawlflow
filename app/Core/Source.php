<?php
namespace App\Core;

use App\Migrator;

class Source
{
    protected $type = 'general';
    protected $args = array();

    public function __construct($args)
    {
        if (isset($args['type'])) {
            $this->set_type($args['type']);
            unset($args['type']);
        }
        $this->args = $args;
    }

    public function set_type($type)
    {
        $this->type = $type;
    }

    public function create_feed()
    {
        $support_feeds = Migrator::get_support_feeds();

        if (isset($support_feeds[$this->type])) {
            $clsFeed = $support_feeds[$this->type];
            $feed    = new $clsFeed();

            $parseArgCallback = array($feed, 'parseArgs');
            if (is_callable($parseArgCallback)) {
                call_user_func($parseArgCallback, $this->args);
            }

            return $feed;
        } else {
            error_log(sprintf(
                __('Source type "%s" is invalid to create Rake\Feed'),
                $this->type
            ));
        }
    }

    public function validate()
    {
        return true;
    }
}
