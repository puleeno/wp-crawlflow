<?php

namespace CrawlFlow\Tooths;

use Ramphor\Rake\Abstracts\CrawlerTooth as FrameworkCrawlerTooth;
use Puleeno\Rake\WordPress\Traits\WordPressTooth;

abstract class CrawlerTooth extends FrameworkCrawlerTooth
{
    use WordPressTooth;


    public function validateURL($url)
    {
        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['path']) or $parsedUrl['path'] === '/') {
            return false;
        }

        if (is_callable($this->urlValidator)) {
            return call_user_func($this->urlValidator, $url);
        }

        switch (gettype($this->urlValidator)) {
            case 'string':
            case 'integer':
            case 'double':
            case 'boolean':
                return boolval($this->urlValidator);
            default:
                return true;
        }
    }
}