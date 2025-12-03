<?php
/**
 * Mock WordPress Functions for Unit Tests
 * These mocks allow tests to run without full WordPress installation
 */

// Define WordPress constants
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('ARRAY_N')) {
    define('ARRAY_N', 'ARRAY_N');
}
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(dirname(dirname(dirname(__DIR__))))));
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        // Mock implementation
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        // Mock implementation
        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value) {
        return $value;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook_name, ...$args) {
        // Mock implementation
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        return true;
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        return true;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'test_nonce_' . md5($action);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return strip_tags($str);
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
        return strip_tags($str);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null) {
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null) {
        echo json_encode(['success' => false, 'data' => $data]);
        exit;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'http://localhost/wp-admin/' . $path;
    }
}

if (!function_exists('get_current_screen')) {
    function get_current_screen() {
        return (object) ['id' => 'test_screen'];
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) {
        if ($type === 'mysql') {
            return date('Y-m-d H:i:s');
        }
        return time();
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        return true; // Default to true for tests
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target) {
        if (file_exists($target)) {
            return @is_dir($target);
        }
        
        // Create directory recursively
        if (@mkdir($target, 0755, true)) {
            return true;
        }
        
        return false;
    }
}

if (!function_exists('wp_insert_post')) {
    function wp_insert_post($postarr, $wp_error = false) {
        // Mock post creation
        return rand(1, 9999);
    }
}

if (!function_exists('wp_update_post')) {
    function wp_update_post($postarr, $wp_error = false) {
        return $postarr['ID'] ?? rand(1, 9999);
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '') {
        return true;
    }
}

if (!function_exists('wp_set_post_categories')) {
    function wp_set_post_categories($post_ID = 0, $post_categories = []) {
        return true;
    }
}

if (!function_exists('wp_set_post_tags')) {
    function wp_set_post_tags($post_id = 0, $tags = '') {
        return true;
    }
}

if (!function_exists('set_post_thumbnail')) {
    function set_post_thumbnail($post, $thumbnail_id) {
        return true;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return false;
    }
}

// Define WordPress database constants
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'test_db');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'test_user');
}
if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', 'test_password');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}
if (!defined('DB_COLLATE')) {
    define('DB_COLLATE', 'utf8mb4_unicode_ci');
}

// Define WordPress content directory
if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', dirname(dirname(dirname(__DIR__))) . '/wp-content');
}

// Define time constants
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

// Mock wpdb if needed
if (!class_exists('wpdb')) {
    class wpdb {
        public $prefix = 'wp_';
        public $insert_id = 0;
        
        public function query($query) {
            return true;
        }
        
        public function get_results($query, $output = OBJECT) {
            return [];
        }
        
        public function get_row($query, $output = OBJECT, $offset = 0) {
            return null;
        }
        
        public function get_var($query = null, $x = 0, $y = 0) {
            return 0;
        }
        
        public function insert($table, $data, $format = null) {
            $this->insert_id = rand(1, 9999);
            return true;
        }
        
        public function update($table, $data, $where, $format = null, $where_format = null) {
            return 1; // Number of rows updated
        }
        
        public function delete($table, $where, $where_format = null) {
            return 1; // Number of rows deleted
        }
        
        public function prepare($query, ...$args) {
            // Simple prepare mock - just return query with placeholders
            return vsprintf(str_replace(['%d', '%s', '%f'], ['%d', "'%s'", '%f'], $query), $args);
        }
    }
}

// Global $wpdb for tests
global $wpdb;
if (!isset($wpdb)) {
    $wpdb = new wpdb();
}

