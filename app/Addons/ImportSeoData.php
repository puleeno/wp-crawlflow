<?php

namespace CrawlFlow\Addons;

if (!defined('ABSPATH')) {
    exit('Cheatin huh?');
}

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

    public function importSeoData($wordPressId, FeedItem $feedItem, $dataType, CrawlFlowProcessor $processor)
    {
        $builtInType = rake_wp_get_builtin_data_type($dataType);
        // Doesn't know WordPress data type to import SEO data
        if (empty($builtInType)) {
            return;
        }

        switch ($builtInType) {
            case 'post':
                $processor->importSeo($wordPressId);
                break;
            case 'taxonomy':
                $processor->importTermSeo($wordPressId, rake_wp_get_wordpress_taxonomy_name($dataType));
                break;
        }
    }
}
