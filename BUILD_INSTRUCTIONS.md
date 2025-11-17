# Hướng dẫn Build CrawlFlow UI

## Cài đặt Dependencies

```bash
cd assets/js/crawflow-ui
npm install
```

## Development Mode

```bash
npm run dev
```

App sẽ chạy tại `http://localhost:3000` (standalone mode)

## Build cho Production

```bash
npm run build
```

Build output sẽ được tạo trong `assets/js/crawflow-ui/dist/`

## Tích hợp với WordPress

Sau khi build, React app sẽ tự động được load trong WordPress admin tại:

**URL:** `wp-admin/admin.php?page=crawlflow-projects&sub=compose&editor=flow`

### Cấu trúc Build Output

```
assets/js/crawflow-ui/dist/
├── manifest.json          # Vite manifest file
├── crawflow-ui.[hash].js  # Main bundle
├── chunks/                # Code splitting chunks
└── assets/                # CSS và other assets
```

### WordPress Integration

- **Container ID:** `#crawlflow-react-flow-root`
- **Config Object:** `window.crawlflowConfig`
- **API Endpoints:**
  - `crawlflow_save_flow_config`: Lưu config
  - `crawlflow_load_flow_config`: Load config

## Troubleshooting

### Build không tạo manifest.json

Kiểm tra `vite.config.ts` và đảm bảo `manifest: true` được set.

### Assets không load trong WordPress

1. Kiểm tra file `manifest.json` có tồn tại không
2. Kiểm tra paths trong `enqueueReactFlowAssets()` method
3. Clear WordPress cache nếu có

### React app không mount

1. Kiểm tra container `#crawlflow-react-flow-root` có tồn tại không
2. Kiểm tra console errors
3. Đảm bảo React và ReactFlow scripts được load

