<?php

namespace CrawlFlow\Tooths;

use Puleeno\Rake\WordPress\Traits\WordPressTooth;
use Ramphor\Rake\Facades\Logger;
use Ramphor\Rake\Facades\Option;
use Ramphor\Rake\Resource;

class CrawlFlowTooth extends CrawlerTooth
{
    const NAME = 'general';

    protected $limitQueryUrls = 10;
    protected $limitQueryResources = 10;

    protected $isCrawlUrlInContent = false;

    use WordPressTooth;


    public function isCrawlUrlInContent()
    {
        return apply_filters(
            'crawlflow/crawl/url_in_html',
            $this->isCrawlUrlInContent
        );
    }


    public function getLimitQueryUrls()
    {
        return apply_filters(
            'crawlflow/urls/query/limit',
            $this->limitQueryUrls,
            $this
        );
    }

    public function getLimitQueryResources()
    {
        $notifiedKey = sprintf('tooth_%s_notified', $this->getId());
        $notified    = Option::get($notifiedKey, false);
        $limitResources = $this->getLimitQueryResources();
        if ($notified) {
            Logger::info(sprintf('[%s]Load %d resources for downloading', $this->getId(), $limitResources));
            return $limitResources;
        }

        Logger::info(sprintf('[%s]Load %d resources for downloading', $this->getId(), $limitResources));
        return $limitResources;
    }


    public function downloadResource(Resource &$resource): Resource
    {
        $resource = parent::downloadResource($resource);
        do_action('crawlflow/resource/downloaded', $resource);
        return $resource;
    }
}
