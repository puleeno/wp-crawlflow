<?php
namespace App\Processors;

use Puleeno\Rake\WordPress\Content\OpencartProcessor as OpencartSource;
use Puleeno\Rake\WordPress\Traits\WooCommerceProcessor;
use Puleeno\Rake\WordPress\Traits\WordPressProcessor;

class OpencartSourceProcessor extends OpencartSource
{
    const NAME = 'general';

    use WooCommerceProcessor;
    use WordPressProcessor;
}
