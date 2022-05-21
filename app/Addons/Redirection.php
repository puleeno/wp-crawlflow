<?php
namespace App\Addons;

use App\Abstracts\Addon;

class Redirection extends Addon
{
    protected function getUrlFromResource($resource) {
        switch($resource->new_type) {
            case 'attachment':
                return wp_get_attachment_url($resource->new_guid);
            case 'term':
                return get_term_link($resource->new_guid, $resource->new_type);
            case 'post':
                return get_the_permalink($resource->new_guid);
        }
    }

    protected function redirect($resource, $preempt) {
        $url = $this->getUrlFromResource($resource);
        if (!$url) {
            return $preempt;
        }
        return wp_safe_redirect($url, 301, 'Rake Migration Tool');
    }

    public function bootstrap() {

        add_filter('pre_handle_404', function($preempt) {
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

            global $wpdb;

            $sql  = "SELECT * FROM {$wpdb->prefix}rake_resources WHERE guid LIKE '%";
            $sql .= $wpdb->use_mysqli
                ? mysqli_real_escape_string($wpdb->dbh, $requestUrl)
                : mysql_real_escape_string($requestUrl, $wpdb->dbh);

            $sql .= "' AND imported=1 AND (new_guid IS NOT NULL OR new_guid > 0)";

            $resource = $wpdb->get_row($sql);

            if (empty($resource)) {
                return $preempt;
            }

            return $this->redirect($resource, $preempt);
        }, 5, 1);
    }
}
