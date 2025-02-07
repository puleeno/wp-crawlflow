<?php

namespace CrawlFlow\Addons;

use CrawlFlow\Abstracts\Addon;
use CrawlFlow\Processors\CrawlFlowProcessor;
use Ramphor\Rake\DataSource\FeedItem;

class ImportSeoData extends Addon
{
    public function bootstrap()
    {
    }

    public function init()
    {
        add_action('crawlflow_after_imported', [$this, 'importSeoData'], 10, 4);
    }

    public function importSeoData($wordPressId, FeedItem $feedItem, $crawlData, CrawlFlowProcessor $processor)
    {
        // Doesn't know WordPress data type to import SEO data
        if (empty($processor->getWordPressDataType())) {
            return;
        }

        switch ($processor->getWordPressDataType()) {
            case 'post':
                $processor->importSeo($wordPressId);
                break;
            case 'taxonomy':
                $processor->importTermSeo($wordPressId);
                break;
        }
    }
}
