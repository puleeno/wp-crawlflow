<?php

namespace CrawlFlow\Core;

if (!defined('ABSPATH')) {
    exit('Cheating huh?');
}

use CrawlFlow\Migrator;

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

    public function create_feed($tooth)
    {
        $support_feeds = Migrator::get_support_feeds();

        if (isset($support_feeds[$this->type])) {
            $clsFeed = $support_feeds[$this->type];
            $feed    = new $clsFeed($this->generateFeedId(), $tooth);

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

    protected function getHashFromArgs($args)
    {
        if (isset($args['url'])) {
            return md5($args['url']);
        }
        return md5(var_export($args, true));
    }

    protected function generateFeedId()
    {
        $hash = $this->getHashFromArgs($this->args);

        return sprintf('%s_%s', $this->type, $hash);
    }

    public function getArgs($name = null)
    {
        if (is_null($name)) {
            return $this->args;
        }
        if (isset($this->args[$name])) {
            return $this->args[$name];
        }
        return null;
    }
}
