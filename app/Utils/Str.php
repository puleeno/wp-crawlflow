<?php

namespace CrawlFlow\Utils;

if (!defined('ABSPATH')) {
    exit('Cheating huh?');
}

class Str
{
    public static function convertToCamel($str)
    {
        return preg_replace_callback('/[_|-](\w)/', function ($matches) {
            if (isset($matches[1])) {
                return ucwords($matches[1]);
            }
        }, $str);
    }
}
