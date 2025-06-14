<?php

namespace CrawlFlow\Tooths;

if (!defined('ABSPATH')) {
    exit('Cheatin huh?');
}

use Puleeno\Rake\WordPress\Traits\WordPressTooth;
use Ramphor\Rake\Facades\Logger;
use Ramphor\Rake\Facades\Option;
use Ramphor\Rake\Resource;

class CrawlFlowTooth extends CrawlerTooth
{
    const NAME = 'general';

    protected $limitQueryUrls = 20;
    protected $limitQueryResources = 100;

    protected $isCrawlUrlInContent = false;
    protected $isCrawlUrlInHtml = false;

    use WordPressTooth;


    public function isCrawlUrlInContent()
    {
        return apply_filters(
            'crawlflow/crawl/url_in_content',
            $this->isCrawlUrlInContent
        );
    }

    public function isCrawlUrlInHtml()
    {
        return apply_filters(
            'crawlflow/crawl/url_in_html',
            $this->isCrawlUrlInHtml
        );
    }


    public function getLimitQueryUrls()
    {
        return apply_filters(
            'crawflow/query/limit/urls',
            $this->limitQueryUrls,
            $this
        );
    }

    public function getLimitQueryResources()
    {
        $notifiedKey = sprintf('tooth_%s_notified', $this->getId());
        $notified = Option::get($notifiedKey, false);
        $limitResources = apply_filters('crawflow/query/limit/resources', $this->limitQueryResources);
        if ($notified) {
            Logger::info(sprintf('[%s]Load %d resources for downloading', $this->getId(), $limitResources));
            return $limitResources;
        }

        Logger::info(sprintf('[%s]Load %d resources for downloading', $this->getId(), $limitResources));
        return $limitResources;
    }


    public function downloadResource(Resource &$resource): Resource
    {
        $resource = apply_filters_ref_array('crawlflow/resource/download', [
            &$resource
        ]);

        // support hook to download on backup site
        $resource = parent::downloadResource($resource);
        do_action_ref_array('crawlflow/resource/downloaded', [
            &$resource
        ]);
        return $resource;
    }
}
