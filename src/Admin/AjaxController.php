<?php

namespace CrawlFlow\Admin;

class AjaxController
{
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function registerRestRoutes()
    {
        register_rest_route('crawlflow/v1', '/fetch-html', [
            'methods' => 'GET',
            'callback' => [$this, 'fetchHtml'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => [
                'url' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);
    }

    public function fetchHtml($request)
    {
        $url = $request->get_param('url');
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new \WP_Error('invalid_url', 'URL không hợp lệ', ['status' => 400]);
        }
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'redirection' => 3,
            'user-agent' => 'CrawlFlow/2.0',
        ]);
        if (is_wp_error($response)) {
            return new \WP_Error('fetch_failed', 'Không thể tải HTML: ' . $response->get_error_message(), ['status' => 500]);
        }
        $body = wp_remote_retrieve_body($response);
        return new \WP_REST_Response($body, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
