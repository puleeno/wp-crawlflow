<?php

namespace CrawlFlow\Tooths;

use Puleeno\Rake\WordPress\Traits\WordPressTooth;
use Ramphor\Rake\Facades\Logger;
use Ramphor\Rake\Facades\Option;
use Ramphor\Rake\Resource;

class CrawlFlowTooth extends CrawlerTooth
{
    const NAME = 'general';

    protected $limitQueryUrls = 20;

    protected $isCrawlUrlInContent = false;

    const MAXIMUM_RESOURCES_DOWNLOADING = 50;

    use WordPressTooth;


    public function isCrawlUrlInContent() {
        return apply_filters(
            'crawlflow/crawl/url_in_html',
            $this->isCrawlUrlInContent
        );
    }

    public function limitQueryResource()
    {
        $notifiedKey = sprintf('tooth_%s_notified', $this->getId());
        $notified    = Option::get($notifiedKey, false);
        if ($notified) {
            Logger::debug(sprintf('[%s]Load %d resources for downloading', $this->getId(), static::MAXIMUM_RESOURCES_DOWNLOADING));
            return static::MAXIMUM_RESOURCES_DOWNLOADING;
        }

        Logger::debug(sprintf('[%s]Load %d resources for downloading', $this->getId(), $this->limitQueryResource));
        return $this->limitQueryResource;
    }


    public function downloadResource(Resource &$resource): Resource {
        $resource = parent::downloadResource($resource);
        do_action('crawlflow/resource/downloaded', $resource);
        return $resource;
    }
}
