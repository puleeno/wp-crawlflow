<?php

namespace CrawlFlow\Feeds;

use CrawlFlow\Interfaces\GeneralFeedInterface;
use Ramphor\Rake\Facades\Resources;
use Ramphor\Rake\Link;

if (!defined('ABSPATH')) {
    exit('Cheating huh?');
}

use Ramphor\Rake\Abstracts\Feed;

class GeneralFeed extends Feed implements GeneralFeedInterface
{
    const NAME = 'general';

    protected $args = [];

    protected $url;

    protected $paginateParams = [
        'tag' => '{%page%}',
        'name' => 'paged'
    ];

    public function get_name()
    {
        return self::NAME;
    }

    public function fetch()
    {
        // push current URL to database
        $link = new Link(
            $this->getUrl(),
            null,
            array_get($this->args, 'trimLastSplashURL', true)
        );
        $this->insertCrawlUrl($link);
    }

    public function valid()
    {
        return preg_match('/^https?:/', $this->getUrl());
    }

    public function rewind()
    {
    }

    public function next()
    {
    }

    public function getPageParams() {
        return apply_filters(
            'crawlflow/paginate/params',
            $this->paginateParams
        );
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }


    public function getUrl()
    {
        return $this->url;
    }
}
