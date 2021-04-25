<?php
namespace App\Tooths;

use Ramphor\Rake\Abstracts\CrawlerTooth;

use Puleeno\Rake\WordPress\Traits\WordPressTooth;
use Puleeno\Rake\WordPress\Traits\WooCommerceProcessor;

class GeneralTooth extends CrawlerTooth
{
    const NAME = 'general';

    use WordPressTooth, WooCommerceProcessor;

    public function validateURL($url)
    {
    }
}
