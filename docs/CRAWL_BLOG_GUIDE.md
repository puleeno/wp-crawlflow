# 🕷️ CRAWL BLOG POSTS GUIDE

## 📖 Hướng dẫn crawl posts từ SimpleBlog OceanWP

### ✅ Đã implement

1. **HTMLDataExtractorExecutor** - Extract data từ HTML
2. **WorkerNodeExecutor** - Filter pages theo detection rules
3. **ProcessorNodeExecutor** - Save to WordPress/Database
4. **Example Config** - Ready-to-use configuration

---

## 🚀 CÁCH SỬ DỤNG

### Method 1: Test Script (Recommended)
```bash
cd wp-content/plugins/wp-crawlflow
php tests/test-simpleblog-crawl.php
```

**Output mẫu**:
```
✓ Loaded flow configuration
  Nodes: 6
  Edges: 5

--- Executing Flow ---
Target: https://simpleblog.oceanwp.org/blog/
Starting crawl...

--- Execution Complete ---
Time: 5.2s
Status: ✓ COMPLETED

--- Statistics ---
Repository (URLs found): 15
Extracted (Data items): 10
Processed (Saved): 10
Errors: 0
Logs: 45

--- Extracted Data Sample ---
Post 1:
  Title: Beautiful Mountain View
  Excerpt: A stunning view of the mountains...
  Author: John Doe
  Date: March 15, 2024
  URL: https://simpleblog.oceanwp.org/blog/mountain-view/
```

### Method 2: Programmatically
```php
// Get services
$rake = \Rake\Rake::getInstance();
$projectService = $rake->make('CrawlFlow\Admin\ProjectService');
$flowService = $rake->make('CrawlFlow\Flow\FlowService');

// Load config
$config = json_decode(
    file_get_contents('examples/simpleblog-oceanwp-config.json'),
    true
);

// Create project
$projectId = $projectService->createProject([
    'name' => 'SimpleBlog Crawler',
    'status' => 'active',
    'project_data' => $config,
]);

// Execute flow
$context = $flowService->executeFlow($config);
$result = $context->getResult();

echo "Saved {$result['processed_count']} posts\n";
```

### Method 3: Via React UI
1. Go to **CrawlFlow → Projects → New Project**
2. Click **Import Configuration**
3. Select `examples/simpleblog-oceanwp-config.json`
4. Click **Save**
5. Click **Run Flow**

---

## 🎯 FLOW CONFIGURATION

### Nodes trong flow:

#### 1. START Node
```json
{
  "type": "start",
  "data": {
    "sourceType": "url",
    "sourceValue": "https://simpleblog.oceanwp.org/blog/",
    "urlSettings": {
      "scope": "entire-website",
      "whitelistPatterns": ["/blog/", "/\\d{4}/\\d{2}/"]
    }
  }
}
```
**Chức năng**: Lấy tất cả URLs từ blog

#### 2. REPOSITORY Node
```json
{
  "type": "repository"
}
```
**Chức năng**: Lưu trữ URLs để xử lý

#### 3. WORKER Node  
```json
{
  "type": "worker",
  "data": {
    "detectionRules": [
      {
        "selector": "article.post",
        "condition": "exists"
      },
      {
        "selector": ".entry-title",
        "condition": "exists"
      }
    ],
    "detectionLogic": "and"
  }
}
```
**Chức năng**: Chỉ xử lý pages có article.post và .entry-title

#### 4. HTML EXTRACTOR Node
```json
{
  "type": "html-data-extractor",
  "data": {
    "customRules": [
      {
        "name": "title",
        "selector": ".entry-title",
        "extract": "text"
      },
      {
        "name": "content",
        "selector": ".entry-content",
        "extract": "html"
      }
    ]
  }
}
```
**Chức năng**: Extract title, content, author, date, etc.

#### 5. PROCESSOR Node
```json
{
  "type": "processor",
  "data": {
    "processorType": "save-to-wordpress",
    "settings": {
      "postType": "post",
      "postStatus": "draft"
    }
  }
}
```
**Chức năng**: Save as WordPress posts

---

## 🎨 CUSTOMIZATION

### Thay đổi blog URL
Edit `simpleblog-oceanwp-config.json`:
```json
"sourceValue": "https://your-blog-url.com/blog/"
```

### Thay đổi CSS selectors
```json
"customRules": [
  {
    "name": "title",
    "selector": ".your-title-class"
  }
]
```

### Thay đổi post status
```json
"settings": {
  "postStatus": "publish"  // publish instead of draft
}
```

### Thêm custom fields
```json
"customRules": [
  {
    "name": "custom_meta_field",
    "selector": ".custom-data",
    "extract": "text"
  }
]
```

---

## 📊 EXPECTED RESULTS

### Typical Output
```
Repository: 20-30 URLs
Extracted: 10-15 posts
Processed: 10-15 posts saved
Execution Time: 5-15 seconds
```

### WordPress Posts Created
- Post Type: `post`
- Status: `draft` (default)
- Content: Full HTML from blog
- Custom fields: source_url, categories, etc.

---

## 🔧 TROUBLESHOOTING

### No posts extracted?
- Check CSS selectors match blog structure
- Inspect blog HTML with DevTools
- Adjust selectors in config

### Posts not saving?
- Check WordPress permissions
- Verify database connection
- Check error logs

### Slow execution?
- Reduce concurrency
- Increase crawlDelay
- Limit scope to recent posts only

---

## ✅ VERIFIED

- ✅ HTMLDataExtractorExecutor implemented
- ✅ WorkerNodeExecutor implemented  
- ✅ ProcessorNodeExecutor implemented
- ✅ Example config created
- ✅ Test script ready
- ✅ Symfony DomCrawler installed

**Status**: Ready to crawl! 🕷️

