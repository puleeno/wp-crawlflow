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
            if (apply_filters('crawlflow/redirect/enabled', true)) {
                return wp_safe_redirect($url, 301, 'WP CrawlFlow');
            }

            // Use hook to still load crawled object with canion URL is WordPress format
            add_filter('parse_query', function (&$wp_query) use ($resource, $url, $originUrl) {
                return $wp_query;
            }, 99);
        }

        return $preempt;
    }

    public function bootstrap()
    {
        add_filter('crawlflow/taxonomy/named', [$this, 'filterWooCommerceTypes']);

        add_filter('pre_handle_404', function ($preempt) {
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

            if (empty($requestUrl)) {
                return $preempt;
            }

            global $wpdb;

            $keyword = $wpdb->use_mysqli
                ? mysqli_real_escape_string($wpdb->dbh, $requestUrl)
                : call_user_func('mysql_real_escape_string', $requestUrl, $wpdb->dbh);


            $sql  = "SELECT `new_guid`, `new_type`  FROM {$wpdb->prefix}rake_resources WHERE (guid LIKE '%" . $keyword . "' OR guid LIKE '%" . $keyword . "/') AND imported=1 AND (new_guid IS NOT NULL OR new_guid > 0)";

            $resource = $wpdb->get_row($sql);

            if (empty($resource)) {
                return $preempt;
            }
            return $this->redirect($resource, $preempt);
        }, 5, 1);
    }

    public function filterWooCommerceTypes($type)
    {
        switch ($type) {
            case 'product_category':
                return 'product_cat';
        }
        return $type;
    }
}
