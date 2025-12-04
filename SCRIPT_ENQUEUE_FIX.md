# ✅ SCRIPT ENQUEUE & REGISTRY LOCALIZATION FIX

**Date**: December 4, 2025  
**Status**: ✅ **FIXED**

---

## 🐛 PROBLEM

**Issue**: Processor dropdown in React UI was empty  
**Cause**: 
1. `wp_localize_script` called BEFORE `wp_enqueue_script`
2. Script path hardcoded with hash
3. `httpClients` missing from localized data

---

## ✅ SOLUTION

### 1. Enqueue Script BEFORE Localizing

```php
// BEFORE (Wrong)
wp_localize_script('crawlflow-ui', ...); // Script not enqueued yet!

// AFTER (Correct)
wp_enqueue_script('crawlflow-ui', ...);
wp_localize_script('crawlflow-ui', ...); // Now script exists
```

### 2. Dynamic Script Path from Manifest

```php
// Read Vite manifest.json
$manifest_path = $plugin_dir . 'assets/js/crawflow-ui/dist/.vite/manifest.json';
$manifest = json_decode(file_get_contents($manifest_path), true);
$script_file = $manifest['index.tsx']['file']; // e.g., crawlflow-ui.Dk2KSQ2S.js
```

### 3. Include All Registry Data

```php
wp_localize_script('crawlflow-ui', 'crawlflowRegistry', [
    'dataSources' => $registryData['dataSources'],
    'processors' => $registryData['processors'],     // ✅ Added
    'parsers' => $registryData['parsers'],
    'httpClients' => $registryData['httpClients'],   // ✅ Added
    'nonce' => wp_create_nonce('crawlflow_nonce'),
    'ajaxUrl' => admin_url('admin-ajax.php'),
]);
```

---

## 📊 DATA VERIFICATION

### Registry Data Available

```
✓ Data Sources: 0
✓ Processors: 1
  - 💾 Save to WordPress (save_to_wordpress)
✓ Parsers: 3
✓ HTTP Clients: 1
```

### Localized JavaScript Object

```javascript
window.crawlflowRegistry = {
  dataSources: [],
  processors: [
    {
      type: 'save_to_wordpress',
      label: 'Save to WordPress',
      description: 'Save extracted data as WordPress posts',
      icon: '💾',
      configFields: [...]
    }
  ],
  parsers: [...],
  httpClients: [...],
  nonce: '...',
  ajaxUrl: '/wp-admin/admin-ajax.php'
}
```

---

## 🔄 EXECUTION ORDER

### Correct Order

1. **Hook**: `admin_enqueue_scripts`
2. **Check**: Is CrawlFlow page?
3. **Enqueue**: `wp_enqueue_script('crawlflow-ui', ...)`
4. **Collect**: Get data from managers
5. **Localize**: `wp_localize_script('crawlflow-ui', ...)`
6. **Render**: React app loads
7. **Use**: `useRegistry()` hook reads `window.crawlflowRegistry`

---

## 🎯 IMPLEMENTATION

### DashboardRenderer.php

```php
public function enqueueScriptsAndData($hook): void
{
    // 1. Check page
    if (strpos($hook, 'crawlflow') === false) {
        return;
    }

    // 2. Enqueue script (read from manifest)
    $manifest_path = $plugin_dir . 'assets/js/crawflow-ui/dist/.vite/manifest.json';
    $manifest = json_decode(file_get_contents($manifest_path), true);
    $script_file = $manifest['index.tsx']['file'];
    $script_url = plugin_dir_url(...) . 'assets/js/crawflow-ui/dist/' . $script_file;
    
    wp_enqueue_script('crawlflow-ui', $script_url, ['wp-element'], ..., true);

    // 3. Get registry data
    $registryData = $this->registryService->getAllRegistryData();

    // 4. Localize data (AFTER enqueue)
    wp_localize_script('crawlflow-ui', 'crawlflowRegistry', [
        'dataSources' => $registryData['dataSources'],
        'processors' => $registryData['processors'],
        'parsers' => $registryData['parsers'],
        'httpClients' => $registryData['httpClients'],
        'nonce' => wp_create_nonce('crawlflow_nonce'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
    ]);
}
```

---

## ✅ BENEFITS

1. **Dynamic Script Loading** ✅
   - Reads from Vite manifest
   - No hardcoded hash
   - Works after every build

2. **Correct Execution Order** ✅
   - Enqueue before localize
   - No warnings
   - Data available to React

3. **Complete Data** ✅
   - All registry types included
   - httpClients added
   - Ready for UI

---

## 🚀 RESULT

**Before**: Empty dropdown  
**After**: 💾 Save to WordPress (and any registered processors)

**UI Now Shows**:
- Data sources (when registered)
- Processors: Save to WordPress
- Parsers: HTML, JSON, XML
- HTTP Clients: WordPress HTTP Client

---

**Status**: ✅ **FIXED & TESTED**  
**UI**: ✅ **LOADS DATA**  
**Dynamic**: ✅ **NO HARDCODING**

