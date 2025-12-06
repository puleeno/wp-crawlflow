# Hướng dẫn chạy Cron qua WP CLI

## 📋 Tổng quan

WordPress CLI (WP-CLI) cung cấp các lệnh để quản lý và chạy cron jobs. Đây là cách tốt để test và debug cron jobs của CrawlFlow.

## 🎯 CrawlFlow CLI Commands (Khuyến nghị)

CrawlFlow cung cấp các lệnh CLI riêng để quản lý cron schedules:

### List tất cả projects và trạng thái schedule

```bash
# List tất cả projects
wp crawlflow cron list

# Chỉ list các projects đã được schedule
wp crawlflow cron list --scheduled

# Chỉ list các projects đang active
wp crawlflow cron list --active
```

**Output ví dụ:**
```
+------------+------------------+--------+---------+----------------------------------------+-----------+------------+---------------------+
| project_id | name             | status | enabled | hook                                   | scheduled | schedule   | next_run            |
+------------+------------------+--------+---------+----------------------------------------+-----------+------------+---------------------+
| 2          | My Project       | active | yes     | crawlflow_execute_project_a1b2c3       | yes       | hourly     | 2025-12-06 11:00:00 |
| 3          | Another Project  | active | no      | crawlflow_execute_project_d4e5f6       | no        | N/A        | N/A                 |
+------------+------------------+--------+---------+----------------------------------------+-----------+------------+---------------------+
```

### Schedule một project

```bash
wp crawlflow cron schedule 2
```

### Unschedule một project

```bash
wp crawlflow cron unschedule 2
```

### Chạy một project thủ công

```bash
wp crawlflow cron run 2
```

---

## 🔧 WordPress Cron Commands (Lệnh gốc)

## 🚀 Các lệnh WP CLI cơ bản

### 1. Chạy tất cả cron events đang chờ

```bash
wp cron event run --due-now
```

Lệnh này sẽ chạy tất cả các cron events đã đến thời gian thực thi.

### 2. Chạy một cron event cụ thể

#### Chạy System Maintenance (mỗi 5 phút)
```bash
wp cron event run crawlflow_system_maintance
```

#### Chạy một project cụ thể
```bash
# Hook name có dạng: crawlflow_execute_project_xxxxxx
# (xxxxxx là 6 ký tự đầu của MD5(project_id))
wp cron event run crawlflow_execute_project_xxxxxx
```

**Lưu ý:** Để tìm hook name của project, xem phần "Xem danh sách cron events" bên dưới.

### 3. Xem danh sách tất cả cron events

```bash
wp cron event list
```

Output sẽ hiển thị:
- Hook name
- Next run time
- Schedule (interval)
- Arguments

**Ví dụ output:**
```
+----------------------------------------+---------------------+------------+----------+
| hook                                   | next_run_gmt        | recurrence | args     |
+----------------------------------------+---------------------+------------+----------+
| crawlflow_system_maintance             | 2025-12-03 10:05:00 | 5 min      |          |
| crawlflow_execute_project_a1b2c3       | 2025-12-03 10:15:00 | 15 min     | [2]      |
| crawlflow_execute_project_d4e5f6       | 2025-12-03 11:00:00 | hourly     | [3]      |
+----------------------------------------+---------------------+------------+----------+
```

### 4. Xem cron events của CrawlFlow

#### Xem system maintenance
```bash
wp cron event list --hook=crawlflow_system_maintance
```

#### Xem tất cả project hooks
```bash
wp cron event list | grep crawlflow_execute_project
```

### 5. Test cron (chạy tất cả events đã đến hạn)

```bash
wp cron test
```

Lệnh này sẽ:
- Kiểm tra các cron events đã đến thời gian
- Chạy các events đó
- Hiển thị kết quả

### 6. Xem danh sách schedules

```bash
wp cron schedule list
```

Output sẽ hiển thị các schedule intervals đã đăng ký:
```
+------------------+-------------+---------------+
| name             | interval    | display_name  |
+------------------+-------------+---------------+
| every_5_minutes  | 300         | Every 5 Minutes |
| every_15_minutes | 900         | Every 15 Minutes |
| every_30_minutes | 1800        | Every 30 Minutes |
| hourly           | 3600        | Hourly        |
| every_6_hours    | 21600       | Every 6 Hours |
| twicedaily       | 43200       | Twice Daily   |
| daily            | 86400       | Daily         |
+------------------+-------------+---------------+
```

## 🔍 Tìm hook name của project

### Cách 1: Qua WP CLI
```bash
# Xem tất cả hooks của CrawlFlow
wp cron event list | grep crawlflow_execute_project

# Hoặc xem chi tiết với format JSON
wp cron event list --format=json | grep -A 5 "crawlflow_execute_project"
```

### Cách 2: Tính toán từ project_id
Hook name được tạo bằng công thức:
```
crawlflow_execute_project_ + substr(md5(project_id), 0, 6)
```

**Ví dụ với project_id = 2:**
```php
$projectId = 2;
$hash = substr(md5($projectId), 0, 6); // Ví dụ: "a1b2c3"
$hook = "crawlflow_execute_project_" . $hash; // "crawlflow_execute_project_a1b2c3"
```

## 🧪 Test và Debug

### Test system maintenance
```bash
# 1. Xem thông tin
wp cron event list --hook=crawlflow_system_maintance

# 2. Chạy thủ công
wp cron event run crawlflow_system_maintance

# 3. Kiểm tra log
tail -f wp-content/debug.log | grep "CrawlFlow"
```

### Test một project cụ thể
```bash
# 1. Tìm hook name của project
wp cron event list | grep "project_id"

# 2. Chạy thủ công (thay xxxxxx bằng hash thực tế)
wp cron event run crawlflow_execute_project_xxxxxx

# 3. Kiểm tra log
tail -f wp-content/debug.log | grep "CrawlFlow"
```

### Chạy tất cả CrawlFlow cron events
```bash
# Lấy danh sách hooks
wp cron event list --format=json | grep -o '"hook":"crawlflow[^"]*"' | cut -d'"' -f4 | while read hook; do
    echo "Running: $hook"
    wp cron event run "$hook"
done
```

## 📝 Ví dụ thực tế

### Scenario 1: Test system maintenance
```bash
# Xem khi nào chạy tiếp theo
wp cron event list --hook=crawlflow_system_maintance

# Chạy ngay bây giờ
wp cron event run crawlflow_system_maintance

# Kiểm tra log
tail -20 wp-content/debug.log
```

### Scenario 2: Test một project với ID = 2
```bash
# Bước 1: Tìm hook name
wp cron event list | grep -E "crawlflow_execute_project.*\[2\]"

# Bước 2: Chạy (giả sử hook là crawlflow_execute_project_a1b2c3)
wp cron event run crawlflow_execute_project_a1b2c3

# Bước 3: Kiểm tra kết quả
tail -50 wp-content/debug.log | grep "Project 2"
```

### Scenario 3: Chạy tất cả cron đang chờ
```bash
# Chạy tất cả events đã đến hạn
wp cron event run --due-now

# Xem log
tail -100 wp-content/debug.log
```

## ⚙️ Cấu hình WP CLI

### Sử dụng với URL cụ thể
```bash
wp cron event list --url=example.com
```

### Sử dụng với user cụ thể
```bash
wp cron event list --user=admin
```

### Format output
```bash
# JSON format
wp cron event list --format=json

# Table format (mặc định)
wp cron event list --format=table

# CSV format
wp cron event list --format=csv
```

## 🔄 Tự động hóa với Cron Job

Bạn có thể sử dụng WP CLI trong server cron job:

```bash
# Chạy mỗi phút
* * * * * cd /path/to/wordpress && wp cron event run --due-now --quiet

# Chạy mỗi 5 phút
*/5 * * * * cd /path/to/wordpress && wp cron event run --due-now --quiet
```

**Lưu ý:** Thêm `--quiet` để không hiển thị output trong cron logs.

## 🆘 Troubleshooting

### Cron event không chạy?
```bash
# Kiểm tra event có tồn tại không
wp cron event list --hook=crawlflow_system_maintance

# Kiểm tra schedule
wp cron schedule list

# Test chạy thủ công
wp cron event run crawlflow_system_maintance
```

### Không thấy hook trong danh sách?
```bash
# Kiểm tra project có được schedule chưa
# (cần chạy qua code PHP hoặc admin interface)

# Hoặc schedule lại
wp eval 'CrawlFlow\Cron\CronScheduler::scheduleSystemMaintenance();'
```

### Xem error logs
```bash
# Xem log WordPress
tail -f wp-content/debug.log

# Hoặc qua WP CLI
wp debug log list
```

## 📚 Tài liệu tham khảo

- [WP-CLI Cron Commands](https://developer.wordpress.org/cli/commands/cron/)
- [WordPress Cron API](https://developer.wordpress.org/plugins/cron/)

