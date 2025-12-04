# 🐛 DEBUG: Registry Data Not Loading in UI

**Date**: December 4, 2025  
**Status**: 🔍 **INVESTIGATING**

---

## ✅ BACKEND WORKING

### Registry Data Available

```
✓ Data Sources: 0
✓ Processors: 1
  - 💾 Save to WordPress (save_to_wordpress)
✓ Parsers: 3
✓ HTTP Clients: 1
```

### Localized Data Structure

```javascript
window.crawlflowRegistry = {
  dataSources: [0 items],
  processors: [1 items],  // ✓ HAS DATA
  parsers: [3 items],
  httpClients: [1 items],
  nonce: '...',
  ajaxUrl: '/wp-admin/admin-ajax.php'
}
```

**Size**: 1,832 bytes  
**JSON Valid**: ✓ YES

---

## 🔍 FRONTEND CHECK

### Steps to Debug

1. **Open Browser DevTools** (F12)
2. **Go to Console tab**
3. **Type**: `window.crawlflowRegistry`
4. **Check output**:
   - If `undefined` → Script not loaded or localized data missing
   - If object exists → Check `processors` array

### Expected Output

```javascript
{
  dataSources: [],
  processors: [
    {
      type: "save_to_wordpress",
      label: "Save to WordPress",
      description: "Save extracted data as WordPress posts",
      icon: "💾",
      configFields: [...]
    }
  ],
  parsers: [...],
  httpClients: [...]
}
```

---

## 🔧 TROUBLESHOOTING

### Issue 1: `window.crawlflowRegistry` is undefined

**Cause**: Script not enqueued or localized data not injected

**Fix**:
1. Check if `wp_enqueue_script('crawlflow-ui', ...)` is called
2. Check if `wp_localize_script('crawlflow-ui', ...)` is called AFTER enqueue
3. Verify hook: `add_action('admin_enqueue_scripts', ...)`

### Issue 2: `processors` array is empty

**Cause**: ProcessorManager not initialized or no processors registered

**Fix**:
1. Check `ProcessorServiceProvider` is in `ApplicationBootstrapper`
2. Verify `ProcessorManager::register()` is called
3. Run: `php -r "require 'wp-load.php'; var_dump(Rake\Manager\ProcessorManager::getRegisteredTypes());"`

### Issue 3: React component not using registry

**Cause**: Component not calling `useRegistry()` hook

**Fix**:
```typescript
// In ProcessorNodeSettings
const { processors } = useRegistry();

// Render dropdown
{processors.map(p => (
  <option key={p.type} value={p.type}>
    {p.icon} {p.label}
  </option>
))}
```

---

## 📝 CHECKLIST

Backend:
- [x] ProcessorManager has processors
- [x] RegistryService collects data
- [x] DashboardRenderer enqueues script
- [x] DashboardRenderer localizes data
- [x] JSON encoding works

Frontend:
- [ ] Script loaded in browser
- [ ] `window.crawlflowRegistry` exists
- [ ] `useRegistry()` hook reads data
- [ ] Dropdown renders processors

---

## 🎯 NEXT STEPS

1. **Check Browser Console**:
   ```javascript
   console.log(window.crawlflowRegistry);
   console.log(window.crawlflowRegistry?.processors);
   ```

2. **Check Network Tab**:
   - Look for `crawlflow-ui.*.js`
   - Verify it loads (200 OK)

3. **Check HTML Source**:
   - Look for `<script>` tag with `crawlflowRegistry`
   - Should be BEFORE React script

4. **Check React Component**:
   - Add `console.log` in `useRegistry()` hook
   - Verify data is loaded

---

## 💡 COMMON FIXES

### Fix 1: Enqueue Order

```php
// WRONG
wp_localize_script('crawlflow-ui', ...);
wp_enqueue_script('crawlflow-ui', ...);

// CORRECT
wp_enqueue_script('crawlflow-ui', ...);
wp_localize_script('crawlflow-ui', ...); // AFTER enqueue
```

### Fix 2: Script Handle

```php
// Must match
wp_enqueue_script('crawlflow-ui', ...);
wp_localize_script('crawlflow-ui', ...); // Same handle
```

### Fix 3: Hook Priority

```php
add_action('admin_enqueue_scripts', [$this, 'enqueueScriptsAndData']);
// Default priority 10 is fine
```

---

**Status**: 🔍 **NEED BROWSER CHECK**  
**Backend**: ✅ **WORKING**  
**Frontend**: ❓ **UNKNOWN**

