<?php
namespace App\Tooths;

use Ramphor\Rake\Abstracts\Tooth;

use Puleeno\Rake\WordPress\Traits\WordPressTooth;
use Puleeno\Rake\WordPress\Traits\WooCommerceProcessor;

class FileTooth extends Tooth
{
    const NAME = 'file';

    use WordPressTooth;
    use WooCommerceProcessor;
}
