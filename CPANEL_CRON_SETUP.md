# Hướng dẫn Setup Cron trên cPanel cho CrawlFlow Plugin

## 📋 Tổng quan

CrawlFlow plugin sử dụng **WordPress Cron** để tự động chạy các project crawling theo lịch. Tuy nhiên, WordPress Cron chỉ chạy khi có visitor truy cập website. Để đảm bảo cron chạy đúng lịch, bạn cần setup **Real Cron Job** trên cPanel để trigger WordPress cron.

## ⚠️ Vấn đề với WordPress Cron

WordPress Cron (`wp-cron.php`) chỉ chạy khi:
- Có người dùng truy cập website
- Có HTTP request đến WordPress

**Vấn đề:**
- Nếu không có visitor, cron sẽ không chạy
- Cron có thể bị delay nếu traffic thấp
- Không đảm bảo chạy đúng thời gian đã lên lịch

**Giải pháp:** Setup Real Cron Job trên cPanel để trigger WordPress cron mỗi phút.

---

## 🚀 Bước 1: Tắt WordPress Cron (DISABLE_WP_CRON)

### 1.1. Thêm vào `wp-config.php`

Mở file `wp-config.php` trong thư mục gốc của WordPress và thêm dòng sau **TRƯỚC** dòng `/* That's all, stop editing! Happy publishing. */`:

```php
define('DISABLE_WP_CRON', true);
```

**Vị trí đúng:**
```php
// ... existing code ...

define('DISABLE_WP_CRON', true);

/* That's all, stop editing! Happy publishing. */
```

### 1.2. Lưu file

Lưu file `wp-config.php` sau khi thêm dòng trên.

---

## 🔧 Bước 2: Setup Cron Job trên cPanel

### 2.1. Đăng nhập cPanel

1. Đăng nhập vào cPanel của hosting
2. Tìm mục **"Cron Jobs"** hoặc **"Advanced" → "Cron Jobs"**

### 2.2. Tạo Cron Job mới

1. Click **"Create New Cron Job"** hoặc **"Add New Cron Job"**
2. Chọn **"Standard (cPanel vixie-cron)"** hoặc **"Standard"**

### 2.3. Cấu hình Cron Job

#### Option 1: Chạy mỗi phút (Khuyến nghị)

**Common Settings:** Chọn **"Every Minute"**

**Hoặc nhập thủ công:**
```
Minute: *
Hour: *
Day: *
Month: *
Weekday: *
```

**Command:**
```bash
/usr/bin/php /home/username/public_html/wp-cron.php
```

**Lưu ý:** Thay `username` bằng username cPanel của bạn.

#### Option 2: Chạy mỗi 5 phút

**Common Settings:** Chọn **"Every 5 Minutes"**

**Hoặc nhập thủ công:**
```
Minute: */5
Hour: *
Day: *
Month: *
Weekday: *
```

**Command:**
```bash
/usr/bin/php /home/username/public_html/wp-cron.php
```

#### Option 3: Chạy mỗi 15 phút

**Common Settings:** Chọn **"Every 15 Minutes"**

**Hoặc nhập thủ công:**
```
Minute: */15
Hour: *
Day: *
Month: *
Weekday: *
```

**Command:**
```bash
/usr/bin/php /home/username/public_html/wp-cron.php
```

### 2.4. Tìm đường dẫn PHP

Nếu không chắc đường dẫn PHP, kiểm tra bằng cách:

1. Tạo file `phpinfo.php` trong thư mục public_html:
```php
<?php phpinfo(); ?>
```

2. Truy cập `https://yourdomain.com/phpinfo.php`
3. Tìm dòng **"System"** → **"Server API"** → thường là `/usr/bin/php` hoặc `/usr/local/bin/php`

**Hoặc** sử dụng command:
```bash
which php
```

### 2.5. Tìm đường dẫn wp-cron.php

Đường dẫn thường là:
```
/home/username/public_html/wp-cron.php
```

**Hoặc nếu WordPress ở subdirectory:**
```
/home/username/public_html/wordpress/wp-cron.php
```

**Hoặc nếu WordPress ở subdomain:**
```
/home/username/public_html/subdomain/wp-cron.php
```

### 2.6. Lưu Cron Job

Click **"Add New Cron Job"** hoặc **"Create"** để lưu.

---

## ✅ Bước 3: Kiểm tra Cron Job hoạt động

### 3.1. Kiểm tra trong cPanel

1. Vào lại **Cron Jobs** trong cPanel
2. Xem danh sách cron jobs đã tạo
3. Kiểm tra **"Last Run"** để xem cron có chạy không

### 3.2. Kiểm tra WordPress Cron

1. Cài plugin **"WP Crontrol"** hoặc **"Advanced Cron Manager"**
2. Vào **Tools → Cron Events** (hoặc **WP Crontrol → Cron Events**)
3. Tìm event `crawlflow_execute_project`
4. Kiểm tra **"Next Run"** để xem có được schedule không

### 3.3. Kiểm tra Log

Kiểm tra error log trong cPanel hoặc file `wp-content/debug.log`:

```bash
tail -f wp-content/debug.log
```

Tìm các dòng:
```
CrawlFlow: Scheduled project X with interval 'hourly'
CrawlFlow: Starting cron execution for project X
CrawlFlow: Project X executed in X.XXXs
```

---

## 🔍 Bước 4: Troubleshooting

### Vấn đề 1: Cron không chạy

**Nguyên nhân:**
- Đường dẫn PHP sai
- Đường dẫn wp-cron.php sai
- Permissions không đúng

**Giải pháp:**
1. Kiểm tra đường dẫn PHP: `which php`
2. Kiểm tra đường dẫn wp-cron.php: `ls -la wp-cron.php`
3. Test command thủ công:
```bash
/usr/bin/php /home/username/public_html/wp-cron.php
```

### Vấn đề 2: Cron chạy nhưng project không execute

**Nguyên nhân:**
- Project chưa được schedule
- Project status không phải "active"
- Project settings chưa enable

**Giải pháp:**
1. Vào CrawlFlow admin panel
2. Kiểm tra project status = "active"
3. Kiểm tra project settings → "Enabled" = true
4. Kiểm tra schedule settings

### Vấn đề 3: Permission denied

**Nguyên nhân:**
- File wp-cron.php không có quyền execute

**Giải pháp:**
```bash
chmod 644 wp-cron.php
```

### Vấn đề 4: PHP version không đúng

**Nguyên nhân:**
- Cron dùng PHP version khác với website

**Giải pháp:**
1. Kiểm tra PHP version trong cPanel
2. Sử dụng đường dẫn PHP cụ thể:
```bash
/usr/bin/php7.4 /home/username/public_html/wp-cron.php
```

**Hoặc** sử dụng PHP version selector trong cPanel để set default PHP version.

---

## 📝 Ví dụ Cron Command đầy đủ

### Ví dụ 1: Shared Hosting (cPanel)

```bash
/usr/bin/php /home/username/public_html/wp-cron.php
```

### Ví dụ 2: VPS/Dedicated Server

```bash
/usr/local/bin/php /var/www/html/wp-cron.php
```

### Ví dụ 3: Với PHP version cụ thể

```bash
/usr/bin/php7.4 /home/username/public_html/wp-cron.php
```

### Ví dụ 4: Với output log

```bash
/usr/bin/php /home/username/public_html/wp-cron.php >> /home/username/public_html/wp-content/cron.log 2>&1
```

---

## 🎯 Best Practices

### 1. Chạy mỗi phút (Khuyến nghị)

- Đảm bảo WordPress cron được trigger thường xuyên
- CrawlFlow có thể schedule project với interval ngắn (5 phút, 15 phút)
- Không ảnh hưởng performance vì chỉ trigger, không chạy nếu không có job

### 2. Monitor Cron Logs

Tạo file log riêng để monitor:
```bash
/usr/bin/php /home/username/public_html/wp-cron.php >> /home/username/public_html/wp-content/cron.log 2>&1
```

### 3. Backup Cron Jobs

Ghi lại cấu hình cron job để backup:
- Schedule: `* * * * *` (mỗi phút)
- Command: `/usr/bin/php /home/username/public_html/wp-cron.php`

### 4. Test trước khi deploy

Test cron job trên staging environment trước khi deploy production.

---

## 📊 Kiểm tra CrawlFlow Cron Status

### Qua WordPress Admin

1. Cài plugin **"WP Crontrol"**
2. Vào **Tools → Cron Events**
3. Tìm `crawlflow_execute_project`
4. Xem **Next Run** và **Schedule**

### Qua Database

```sql
SELECT * FROM wp_options 
WHERE option_name LIKE '%cron%' 
ORDER BY option_id DESC LIMIT 1;
```

### Qua Code

```php
// Check if project is scheduled
$cronScheduler = \CrawlFlow\Cron\CronScheduler::getInstance();
$isScheduled = $cronScheduler->isProjectScheduled($projectId);
$nextRun = $cronScheduler->getNextRunTime($projectId);
```

---

## 🔐 Security Notes

1. **Không expose wp-cron.php trực tiếp qua browser**
   - WordPress đã có protection, nhưng nên giữ nguyên

2. **Kiểm tra file permissions**
   ```bash
   chmod 644 wp-cron.php
   ```

3. **Không hardcode credentials trong cron command**
   - WordPress tự động load từ wp-config.php

---

## 📞 Support

Nếu gặp vấn đề, kiểm tra:

1. ✅ `DISABLE_WP_CRON` đã được set trong wp-config.php
2. ✅ Cron job đã được tạo trong cPanel
3. ✅ Đường dẫn PHP và wp-cron.php đúng
4. ✅ Project status = "active" và enabled = true
5. ✅ Error logs trong cPanel hoặc wp-content/debug.log

---

## 📚 Tài liệu tham khảo

- [WordPress Cron Documentation](https://developer.wordpress.org/plugins/cron/)
- [cPanel Cron Jobs Documentation](https://docs.cpanel.net/knowledge-base/general/creating-a-cron-job/)
- [CrawlFlow Cron Implementation](../CRON_IMPLEMENTATION.md)

---

**Last Updated:** 2025-12-05
**Plugin Version:** 2.0.0
