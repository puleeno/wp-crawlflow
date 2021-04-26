<?php
namespace App\Processors;

use Ramphor\Rake\Abstracts\Processor;

use Puleeno\Rake\WordPress\Traits\WooCommerceProcessor;
use Puleeno\Rake\WordPress\Traits\WordPressProcessor;

class GeneralProcessor extends Processor
{
    const NAME = 'general';

    use WooCommerceProcessor;
    use WordPressProcessor;

    public function execute()
    {
    }
}
