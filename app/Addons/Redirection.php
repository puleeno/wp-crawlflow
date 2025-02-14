<?php

namespace CrawlFlow\Addons;

use CrawlFlow\Abstracts\Addon;

class Redirection extends Addon
{
    protected function getUrlFromResource($resource)
    {
        $dataType = crawlflow_get_wordpress_builtin_data_type($resource->new_type);
        switch ($dataType) {
            case 'attachment':
                return wp_get_attachment_url($resource->new_guid);
            case 'term':
                return get_term_link($resource->new_guid, $resource->new_type);
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
        return wp_safe_redirect($url, 301, 'Rake Migration Tool');
    }

    public function bootstrap()
    {

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

            $sql  = "SELECT `new_guid`, `new_type`  FROM {$wpdb->prefix}rake_resources WHERE guid LIKE '%";
            $sql .= $wpdb->use_mysqli
                ? mysqli_real_escape_string($wpdb->dbh, $requestUrl)
                : call_user_func('mysql_real_escape_string', $requestUrl, $wpdb->dbh);

            $sql .= "' AND imported=1 AND (new_guid IS NOT NULL OR new_guid > 0)";

            $resource = $wpdb->get_row($sql);

            if (empty($resource)) {
                return $preempt;
            }
            return $this->redirect($resource, $preempt);
        }, 5, 1);
    }
}
