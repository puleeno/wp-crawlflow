<?php
namespace App\Processors;

use Puleeno\Rake\WordPress\Traits\WooCommerceProcessor;
use Puleeno\Rake\WordPress\Traits\WordPressProcessor;

class GeneralProcessor
{
    const NAME = 'general';

    use WooCommerceProcessor;
    use WordPressProcessor;
}
