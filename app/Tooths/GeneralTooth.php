<?php
namespace App\Tooths;

use Ramphor\Rake\Abstracts\CrawlerTooth;

use Puleeno\Rake\WordPress\Traits\WordPressTooth;

class GeneralTooth extends CrawlerTooth
{
    const NAME = 'general';

    use WordPressTooth;

    public function validateURL($url)
    {
    }
}
