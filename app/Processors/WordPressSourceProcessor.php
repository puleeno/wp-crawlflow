<?php
namespace App\Processors;

use Puleeno\Rake\WordPress\Content\WordPressProcessor as WordPressSource;
use Puleeno\Rake\WordPress\Traits\WooCommerceProcessor;
use Puleeno\Rake\WordPress\Traits\WordPressProcessor;

class WordPressSourceProcessor extends WordPressSource
{
    const NAME = 'general';

    use WooCommerceProcessor;
    use WordPressProcessor;
}
