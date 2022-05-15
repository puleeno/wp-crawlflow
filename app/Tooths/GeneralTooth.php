<?php
namespace App\Tooths;

use MailPoetVendor\Doctrine\DBAL\Types\BooleanType;
use Ramphor\Rake\Abstracts\CrawlerTooth;

use Puleeno\Rake\WordPress\Traits\WordPressTooth;

class GeneralTooth extends CrawlerTooth
{
    const NAME = 'general';

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
