<?php

namespace CrawlFlow\Tooths;

use Ramphor\Rake\Abstracts\CrawlerTooth;
use Puleeno\Rake\WordPress\Traits\WordPressTooth;
use Ramphor\Rake\Facades\Logger;
use Ramphor\Rake\Facades\Option;

class CrawlFlowTooth extends CrawlerTooth
{
    const NAME = 'general';

    const MAXIMUM_RESOURCES_DOWNLOADING = 20;

    use WordPressTooth;

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
