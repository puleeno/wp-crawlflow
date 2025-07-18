# CrawlFlow - WordPress Data Migration & Crawling Framework

CrawlFlow là một plugin WordPress mạnh mẽ cho việc crawl và migrate dữ liệu từ các nguồn khác nhau vào WordPress, sử dụng framework Rake 2.0.

## Tính năng chính

- **Crawl dữ liệu từ nhiều nguồn**: Sitemap, RSS, HTML pages
- **Xử lý dữ liệu thông minh**: Tự động trích xuất title, content, images
- **Tích hợp WordPress**: Tạo posts, pages, custom post types
- **Giao diện admin thân thiện**: Dashboard với real-time status
- **Cấu hình linh hoạt**: Hỗ trợ nhiều parser và builder
- **Event-driven architecture**: Hệ thống event bus mạnh mẽ
- **Logging và monitoring**: Theo dõi quá trình crawl chi tiết

## Yêu cầu hệ thống

- WordPress 5.0+
- PHP 8.1+
- MySQL 5.7+ hoặc MariaDB 10.2+
- Composer

## Cài đặt

### 1. Cài đặt qua Composer

```bash
composer require puleeno/wp-crawlflow
```

### 2. Cài đặt thủ công

1. Tải plugin từ GitHub
2. Upload vào thư mục `/wp-content/plugins/`
3. Kích hoạt plugin trong WordPress Admin

### 3. Cài đặt dependencies

```bash
cd wp-content/plugins/wp-crawlflow
composer install
```

## Cấu hình

### 1. File cấu hình chính

**File cấu hình chính phải đặt tại:**

```
wp-content/crawlflow.config.php
```

Sau khi cài đặt, hãy copy file mẫu từ một trong hai vị trí sau:

- Từ plugin:
  ```bash
  cp wp-content/plugins/wp-crawlflow/config/rake.config.php wp-content/crawlflow.config.php
  ```
- Hoặc từ package core (nếu đã cài qua composer):
  ```bash
  cp wp-content/plugins/wp-crawlflow/vendor/ramphor/rake/config/rake.config.php wp-content/crawlflow.config.php
  ```

Hoặc tự tạo file `crawlflow.config.php` theo mẫu dưới đây:

```php
return [
    'crawl' => [
        'max_concurrent' => 5,
        'request_delay' => 1,
        'timeout' => 30,
    ],
    'data_sources' => [
        'sitemap' => [
            'enabled' => true,
            'parser' => 'Puleeno\\Rake\\Adapter\\WordPress\\Parsers\\SitemapParser',
        ],
        'rss' => [
            'enabled' => true,
            'parser' => 'Puleeno\\Rake\\Adapter\\WordPress\\Parsers\\RssParser',
        ],
    ],
    // ... more configuration
];
```

> **Lưu ý:** Nếu không có file `wp-content/crawlflow.config.php`, plugin sẽ không hoạt động.

### 2. Cấu hình qua WordPress Admin

1. Vào **CrawlFlow > Settings**
2. Cấu hình các thông số crawl
3. Lưu cấu hình

## Sử dụng

### 1. Dashboard

- Vào **CrawlFlow > Dashboard**
- Xem status crawl real-time
- Điều khiển quá trình crawl
- Theo dõi tiến độ

### 2. Bắt đầu crawl

1. Cấu hình URL nguồn trong file config
2. Chọn parser phù hợp
3. Click "Start Crawl"
4. Theo dõi tiến độ

### 3. Xử lý dữ liệu

Plugin tự động:
- Crawl dữ liệu từ nguồn
- Parse và trích xuất thông tin
- Tạo feed items
- Import vào WordPress

## Cấu trúc dữ liệu

### Origin Data Table

```sql
CREATE TABLE wp_crawlflow_origin_data (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    url varchar(2048) NOT NULL,
    content longtext,
    type varchar(50) DEFAULT 'html',
    status varchar(20) DEFAULT 'pending',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);
```

### Feed Items Table

```sql
CREATE TABLE wp_crawlflow_feed_items (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    origin_id bigint(20) NOT NULL,
    title varchar(500),
    content longtext,
    excerpt text,
    meta_data longtext,
    post_type varchar(50) DEFAULT 'post',
    status varchar(20) DEFAULT 'draft',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (origin_id) REFERENCES wp_crawlflow_origin_data(id)
);
```

## API Reference

### Classes chính

- `WP_CrawlFlow`: Class chính của plugin
- `CrawlFlow\Admin\AdminController`: Xử lý admin interface
- `Puleeno\Rake\Adapter\WordPress\WordPressAdapter`: WordPress adapter

### Hooks WordPress

```php
// Actions
add_action('crawlflow_crawl_started', $callback);
add_action('crawlflow_crawl_completed', $callback);
add_action('crawlflow_url_processed', $callback);
add_action('crawlflow_feed_item_created', $callback);

// Filters
add_filter('crawlflow_url_filter', $callback);
add_filter('crawlflow_content_processor', $callback);
add_filter('crawlflow_feed_item_builder', $callback);
```

### AJAX Endpoints

- `crawlflow_start_crawl`: Bắt đầu crawl
- `crawlflow_stop_crawl`: Dừng crawl
- `crawlflow_get_status`: Lấy status
- `crawlflow_save_config`: Lưu cấu hình

## Development

### Cấu trúc thư mục

```
wp-crawlflow/
├── assets/
│   ├── css/
│   └── js/
├── config/   (không còn dùng, chỉ để file mẫu)
├── languages/
├── src/
├── templates/
├── vendor/
├── composer.json
├── README.md
└── wp-crawlflow.php
wp-content/
└── crawlflow.config.php   # File cấu hình chính
```

### Tạo parser tùy chỉnh

```php
namespace Puleeno\Rake\Adapter\WordPress\Parsers;

class CustomParser implements ParserInterface
{
    public function parse($data)
    {
        // Parse logic here
        return $parsedData;
    }
}
```

### Tạo builder tùy chỉnh

```php
namespace Puleeno\Rake\Adapter\WordPress\Builders;

class CustomBuilder implements FeedItemBuilderInterface
{
    public function build($data)
    {
        // Build feed item logic here
        return $feedItem;
    }
}
```

## Troubleshooting

### Logs

Plugin tạo logs tại:
- `wp-content/logs/crawlflow.log`
- WordPress debug log

### Debug mode

Bật debug mode trong Settings để xem logs chi tiết.

### Common issues

1. **Memory limit**: Tăng `memory_limit` trong php.ini
2. **Timeout**: Tăng `max_execution_time`
3. **Database connection**: Kiểm tra DB credentials

## Changelog

### v2.0.0
- Initial release
- WordPress integration
- Admin interface
- Rake 2.0 framework integration

## License

GPL v3 - Xem file LICENSE để biết thêm chi tiết.

## Support

- GitHub Issues: [https://github.com/puleeno/wp-crawlflow/issues](https://github.com/puleeno/wp-crawlflow/issues)
- Documentation: [https://github.com/puleeno/wp-crawlflow/wiki](https://github.com/puleeno/wp-crawlflow/wiki)

## Contributing

1. Fork repository
2. Tạo feature branch
3. Commit changes
4. Push to branch
5. Tạo Pull Request

## Credits

Developed by Puleeno Nguyen - [https://github.com/puleeno](https://github.com/puleeno)