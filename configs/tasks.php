<?php
return array(
    array(
        'id' => 'your_task_id',
        'format' => 'html',
        'type' => 'url',
        'source_cms' => 'general',
        'url_validator' => true,
        'url_use_splash' => false,
        // 'data_type_checker' => function($type, $feedItem){},
        'data_rules' => array(
            'title' => array(
                'type'    => 'xpath',
                'pattern' => '.product-view h1.title-product, #blog-info h1.blog-title',
            ),
            'seo_title' => array(
                'type'    => 'xpath',
                'pattern' => 'title',
                'get'     => 'text',
            ),
            'seo_description' => array(
                'type'      => 'xpath',
                'pattern'   => 'meta[name="description"]',
                'get'       => 'attribute',
                'attribute' => 'content',
            ),
            'product_content' => array(
                'type'    => 'xpath',
                'pattern' => '#tab-description',
                'get'     => 'innerHtml',
            ),
            'product_attributes' => array(
                'type'    => 'xpath',
                'pattern' => '#tab-specification',
                'get'     => 'innerHtml',
            ),
            'product_price' => array(
                'type'      => 'regex',
                'pattern'   => '/\"price\":\s?\"([^\"]+)/',
                'group'     => 1,
                'callbacks' => array(
                    'str_replace' => array( array( '.' ), '', '%%argument%%' ),
                ),
            ),
            'product_metas' => array(
                'type'    => 'xpath',
                'pattern' => '.product-view > ul.list-unstyled li',
                'get'     => 'innerHtml',
                'return'  => 'array',
            ),
            'categories' => array(
                'type'    => 'xpath',
                'pattern' => '.breadcrumbs .breadcrumb-links li a, #page .breadcrumb li a, .breadcrumbs .breadcrumb-links li a',
                'get'     => 'text',
                'return'  => 'array',
            ),
            'coverImage' => array(
                'type'      => 'xpath',
                'pattern'   => 'meta[property="og:image"]',
                'get'       => 'attribute',
                'attribute' => 'content',
            ),
            'galleryImages' => array(
                'type'      => 'xpath',
                'pattern'   => '#image-additional-carousel a',
                'get'       => 'attribute',
                'attribute' => 'href',
                'return'    => 'array',
            ),
            'slug' => array(
                'type' => 'guid',
                'callback' => function ($slug) {
                    $parsedURL = parse_url($slug);
                    if (!isset($parsedURL['path'])) {
                        return '';
                    }
                    $pathArr = explode('/', $parsedURL['path']);
                    $lastPath = end($pathArr);

                    return trim($lastPath, '.html');
                },
            ),
            'published_at' => array(
                'type'     => 'custom',
                'default_value'    => 'test',
                'callback' => function () {
                    $int= rand(1582216785, time());
                    return date("Y-m-d H:i:s", $int);
                }
            ),
        ),
        'sources' => array(
            array(
                'type' => 'sitemap',
                'url' => 'https://the-opencart-site/sitemap.xml',
            )
        ),
        // 'product_categories_filter' => function($categories, $feedItem, $productId){}
    )
);
