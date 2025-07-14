<?php

namespace CrawlFlow\Addons;

if (!defined('ABSPATH')) {
    exit('Cheating huh?');
}

use CrawlFlow\Abstracts\Addon;

class Redirection extends Addon
{
    protected $permalinkFormat;

    protected $tagParams = [
        'category' => 'category_name',
        'post_id' => 'p',
        'year' => 'year',
        'monthnum' => 'monthnum',
        'day' => 'day',
        'hour' => 'hour',
        'minute' => 'minute',
        'second' => 'second',
        'author' => 'author_name',
        'postname' => 'name'
    ];

    protected function getRealTaxonomy($resource)
    {
        return apply_filters(
            'crawlflow/taxonomy/named',
            $resource->new_type,
            $resource
        );
    }
    protected function getUrlFromResource($resource)
    {
        $dataType = rake_wp_get_builtin_data_type($resource->new_type);
        switch ($dataType) {
            case 'attachment':
                return wp_get_attachment_image_url($resource->new_guid, 'full');
            case 'term':
            case 'taxonomy':
                return get_term_link(intval($resource->new_guid), $this->getRealTaxonomy($resource));
            case 'post':
                return get_the_permalink($resource->new_guid);
        }

        return apply_filters('crawlflow/redirection/url', null, $resource, $dataType);
    }

    protected function redirect($resource, $preempt)
    {
        $url = $this->getUrlFromResource($resource);
        if (!$url) {
            return $preempt;
        }
        if (!isset($_SERVER['HTTP_HOST'])) {
            return $preempt;
        }

        $originUrl = sprintf('%s%s', $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']);
        if (strpos($url, $originUrl) === false) {
            wp_safe_redirect($url, 301, 'WP CrawlFlow');
            exit();
        }

        return $preempt;
    }

    public function bootstrap()
    {
        add_filter('crawlflow/taxonomy/named', [$this, 'filterWooCommerceTypes']);
        $excludeExtensions = apply_filters('crawlflow/redirect/excludes', ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp']);
        $ext = isset($_SERVER['REQUEST_URI']) ? pathinfo($_SERVER['REQUEST_URI'], PATHINFO_EXTENSION) : '';

        if (apply_filters('crawlflow/redirect/enabled', false) || in_array(strtolower($ext), $excludeExtensions)) {
            add_filter('pre_handle_404', [$this, 'redirectHandle'], 5, 1);
        } else {
            add_action('parse_request', [$this, 'customQueryHandle']);
            add_action('crawlflow/custom_query/post', [$this, 'customPostQuery'], 10, 3);
        }
    }

    public function filterWooCommerceTypes($type)
    {
        switch ($type) {
            case 'product_category':
                return 'product_cat';
        }
        return $type;
    }

    protected function getResourceFromRequest()
    {
        $requestUrl = rtrim($_SERVER['REQUEST_URI'], '/');
        if (strpos($requestUrl, '%') !== false) {
            $requestUrl = urldecode($requestUrl);
        }
        $requestUrl = rawurlencode($requestUrl);

        $requestUrl = str_replace(array(
            '%2F',
            '%3F',
            '%3D',
            '%26'
        ), array(
            '/',
            '?',
            '=',
            '&'
        ), $requestUrl);

        $filteredPagedParams = apply_filters(
            'crawlflow/request/url',
            $requestUrl
        );

        if (empty($filteredPagedParams)) {
            return null;
        }

        global $wpdb;

        $keyword = $wpdb->use_mysqli
            ? mysqli_real_escape_string($wpdb->dbh, $filteredPagedParams)
            : call_user_func('mysql_real_escape_string', $filteredPagedParams, $wpdb->dbh);

        $sql = "SELECT `new_guid`, `new_type`  FROM {$wpdb->prefix}rake_resources WHERE (guid LIKE '%" . $keyword . "' OR guid LIKE '%" . $keyword . "/') AND imported=1 AND (new_guid IS NOT NULL OR new_guid > 0)";

        $resource = $wpdb->get_row($sql);

        return $resource;
    }

    protected function trimUrlIncludeExtension($url)
    {
        $parsedUrl = parse_url($url);
        if (strpos($parsedUrl['path'], '.') === false) {
            return $url;
        }
        $paths = explode('.', $parsedUrl['path']);
        unset($paths[count($paths) - 1]);

        return sprintf('%s://%s/%s%s', $parsedUrl['scheme'], $parsedUrl['host'], implode('', $paths), empty($parsedUrl['query']) ? '' : '?' . $parsedUrl['query']);
    }


    public function customQueryHandle(\WP &$wp)
    {
        $resource = $this->getResourceFromRequest();


        if ($resource) {
            $url = $this->getUrlFromResource($resource);
            $originUrl = sprintf('%s%s', $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']);
            if (empty($url) || str_ends_with($url, $originUrl)) {
                return $wp;
            }

            // Delete slug for page
            unset($wp->query_vars['page']);
            unset($wp->query_vars['attachment']);
            unset($wp->query_vars['category_name']);

            $builtInType = rake_wp_get_builtin_data_type($resource->new_type);
            if ($builtInType !== 'post') {
                unset($wp->query_vars['name']);
            }

            $parsed_url = explode('/', rtrim($url, '/'));
            $path = end($parsed_url);
            $query_name = $builtInType === 'post'
                ? rake_wp_get_wordpress_post_type($resource->new_type)
                : rake_wp_get_wordpress_taxonomy_name($resource->new_type);

            if ($builtInType === 'post' && !in_array($resource->new_type, ['page'])) {
                $wp->set_query_var('post_type', $query_name);
            }

            if ($resource->new_type === 'page') {
                $wp->set_query_var('pagename', $path);
            } else {
                $wp->set_query_var($query_name, $path);
                $wp->matched_query = sprintf('%s=%s', $query_name, $path);
            }

            do_action_ref_array("crawlflow/custom_query/{$resource->new_type}", [
                &$wp,
                $url,
                $originUrl,
                $builtInType
            ]);

            // paged
            $paged = $this->extractPageNumberFromRequest($_SERVER['REQUEST_URI']);

            // Set paged parameter to query to get posts
            add_filter('pre_get_posts', function ($wp_query) use ($paged) {
                $wp_query->set('paged', $paged);
            });

            // Avoid redirect to pagination link format
            add_filter('redirect_canonical', function ($redirect_url, $request_url) {
                return $request_url;
            }, 20, 2);

            add_filter('paginate_links', function ($link) use ($url) {
                $separatedLink = explode('/page/', $link);
                $index = isset($separatedLink[1]) ? preg_replace('/[^\d]/', '', $separatedLink[1]) : 1;

                $link = sprintf('%s/page/%d', rtrim($url, '/'), $index);

                return apply_filters('crawlflow/custom_query/paginate/link', $link, $url);
            }, 10, 2);

            do_action('crawlflow/custom_query/handle', $url, $resource, $paged, $wp);
        }
    }

    protected function extractPageNumberFromRequest($requestUri)
    {
        $pagedSeparater = apply_filters('crawlflow/request/params/paged', 'page=');
        $separatedUrl = explode($pagedSeparater, $requestUri);
        if (isset($separatedUrl[1]) && preg_match('/\d{1,}/', $separatedUrl[1], $matches)) {
            return intval($matches[0]);
        }
        return null;
    }

    public function redirectHandle($preempt)
    {
        $resource = $this->getResourceFromRequest();
        if (empty($resource)) {
            return $preempt;
        }
        return $this->redirect($resource, $preempt);
    }

    protected function convertTagToParam($tag)
    {
        if (isset($this->tagParams[$tag])) {
            return $this->tagParams[$tag];
        }
        return null;
    }

    public function customPostQuery(\WP &$wp, $url, $originUrl)
    {
        if (is_null($this->permalinkFormat)) {
            $this->permalinkFormat = get_option('permalink_structure');
        }
        $requestParts = explode('/', $wp->request);
        $postFormatParts = explode('/', ltrim($this->permalinkFormat, '/'));
        $supportTags = apply_filters('crawlflow/custom_query/tags', ['category', 'postname']);

        foreach ($postFormatParts as $index => $postFormatPart) {
            if (!preg_match_all('/%([^%]{1,})%/', $postFormatPart, $matches)) {
                continue;
            }

            foreach ($matches[1] as $tag) {
                // Skip unsupport tags
                if (!in_array($tag, $supportTags)) {
                    continue;
                }

                $param = $this->convertTagToParam($tag);
                if (is_null($param) || empty($requestParts[$index])) {
                    continue;
                }
                $wp->set_query_var($param, $requestParts[$index]);
            }
        }

        // unset query var post
        if (isset($wp->query_vars['post'])) {
            unset($wp->query_vars['post']);
        }
    }
}
