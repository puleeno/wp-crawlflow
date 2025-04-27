<?php

namespace CrawlFlow\Addons;

use CrawlFlow\Abstracts\Addon;

class Redirection extends Addon
{
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
        $dataType = crawlflow_get_wordpress_builtin_data_type($resource->new_type);
        switch ($dataType) {
            case 'attachment':
                return wp_get_attachment_url($resource->new_guid);
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
            return wp_safe_redirect($url, 301, 'WP CrawlFlow');
        }



        return $preempt;
    }

    public function bootstrap()
    {
        add_filter('crawlflow/taxonomy/named', [$this, 'filterWooCommerceTypes']);
        if (apply_filters('crawlflow/redirect/enabled', true)) {
            add_filter('pre_handle_404', [$this, 'redirectHandle'], 5, 1);
        } else {
            add_action('parse_request', [$this, 'customQueryHandle']);
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
        if (strpos($requestUrl, '%') === false) {
            $requestUrl = urlencode($requestUrl);
        }
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


    public function customQueryHandle(\WP &$wp)
    {
        $resource = $this->getResourceFromRequest();
        if ($resource) {
            $url = $this->getUrlFromResource($resource);
            $originUrl = sprintf('%s%s', $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']);
            if (strpos($url, $originUrl) !== false) {
                return $wp;
            }


            // Delete slug for page
            unset($wp->query_vars['page']);
            unset($wp->query_vars['name']);
            unset($wp->query_vars['attachment']);

            $parsed_url = explode('/', rtrim($url, '/'));
            $path = end($parsed_url);
            $query_name = crawlflow_get_wordpress_taxonomy_name($resource->new_type);

            $wp->set_query_var($query_name, $path);

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
}
