<?php

namespace CrawlFlow\Core;

if (!defined('ABSPATH')) {
    exit('Cheating huh?');
}

use CrawlFlow\Addons\ImportSeoData;
use CrawlFlow\Addons\Redirection;

class AddonManager
{
    protected function getDefaultAdddons()
    {
        return [
            Redirection::class,
            ImportSeoData::class,
        ];
    }

    protected function getAddons()
    {
        $addons = $this->getDefaultAdddons();

        return $addons;
    }

    public function loadAddons()
    {
        foreach ($this->getAddons() as $addonCls) {
            if (!class_exists($addonCls)) {
                continue;
            }
            $addon = new $addonCls();
            if (method_exists($addon, 'bootstrap')) {
                add_action('plugins_loaded', [$addon, 'bootstrap']);
            }

            if (method_exists($addon, 'init')) {
                add_action('init', [$addon, 'init']);
            }
        }
    }
}
