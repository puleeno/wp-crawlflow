# Quick Start: Setup Cron trên cPanel

## ⚡ 3 Bước Nhanh

### 1️⃣ Tắt WordPress Cron

Thêm vào `wp-config.php` (trước dòng `/* That's all... */`):

```php
define('DISABLE_WP_CRON', true);
```

### 2️⃣ Tạo Cron Job trong cPanel

**Vào:** cPanel → Cron Jobs → Add New Cron Job

**Schedule:** Chọn "Every Minute" hoặc nhập `* * * * *`

**Command:**
```bash
/usr/bin/php /home/USERNAME/public_html/wp-cron.php
```

**Lưu ý:** Thay `USERNAME` bằng username cPanel của bạn.

### 3️⃣ Kiểm tra

1. Vào cPanel → Cron Jobs → xem "Last Run"
2. Cài plugin "WP Crontrol" → Tools → Cron Events
3. Tìm event `crawlflow_execute_project`

---

## 🔍 Tìm đường dẫn đúng

### PHP Path
```bash
which php
# Thường là: /usr/bin/php hoặc /usr/local/bin/php
```

### WordPress Path
- Root: `/home/USERNAME/public_html/wp-cron.php`
- Subdirectory: `/home/USERNAME/public_html/wordpress/wp-cron.php`
- Subdomain: `/home/USERNAME/public_html/subdomain/wp-cron.php`

---

## ✅ Checklist

- [ ] Đã thêm `DISABLE_WP_CRON` vào wp-config.php
- [ ] Đã tạo cron job trong cPanel
- [ ] Đã test command thủ công
- [ ] Đã kiểm tra "Last Run" trong cPanel
- [ ] Đã kiểm tra WP Crontrol plugin
- [ ] Project status = "active" và enabled = true

---

## 🆘 Troubleshooting

**Cron không chạy?**
```bash
# Test thủ công
/usr/bin/php /home/USERNAME/public_html/wp-cron.php
```

**Project không execute?**
- Kiểm tra project status = "active"
- Kiểm tra project settings → Enabled = true
- Kiểm tra error log: `wp-content/debug.log`

---

**Xem chi tiết:** [CPANEL_CRON_SETUP.md](./CPANEL_CRON_SETUP.md)
