# CrawlFlow React Flow UI

Đây là React Flow-based visual editor để tạo và quản lý crawl projects theo triết lý của Rake Framework.

## Cài đặt Dependencies

```bash
cd assets/js/crawflow-ui
npm install
```

## Development

```bash
npm run dev
```

App sẽ chạy tại `http://localhost:3000`

## Build cho WordPress

```bash
npm run build
```

Build output sẽ được tạo trong `assets/js/crawflow-ui/dist/`

## Tích hợp với WordPress

React app được tích hợp vào WordPress admin tại:
- URL: `wp-admin/admin.php?page=crawlflow-projects&sub=compose&editor=flow`
- Container: `#crawlflow-react-flow-root`
- Config: `window.crawlflowConfig`

## API Endpoints

- `crawlflow_save_flow_config`: Lưu flow config vào WordPress
- `crawlflow_load_flow_config`: Load flow config từ WordPress

## Cấu trúc

- `App.tsx`: Main component với React Flow
- `components/`: UI components
  - `Sidebar.tsx`: Sidebar để add nodes
  - `SettingsPanel.tsx`: Panel để config nodes
  - `InspectorPanel.tsx`: HTML inspector tool
  - `nodes/`: Custom node components
- `types.ts`: TypeScript type definitions
- `presets.ts`: Preset configurations
