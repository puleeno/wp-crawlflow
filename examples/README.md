# CrawlFlow Examples

## SimpleBlog OceanWP Crawler

### Description
Crawl blog posts from https://simpleblog.oceanwp.org/blog/ and save to WordPress.

### Flow Configuration
File: `simpleblog-oceanwp-config.json`

### Flow Structure
```
START (URL)
  ↓
REPOSITORY (Collect URLs)
  ↓
WORKER (Filter blog post pages)
  ↓
HTML EXTRACTOR (Extract post data)
  ↓
PROCESSOR (Save to WordPress)
  ↓
COMPLETION
```

### Data Extracted
- Title
- Content (HTML)
- Excerpt
- Author
- Date
- Featured Image
- Categories

### Usage

#### 1. Via Test Script
```bash
cd wp-content/plugins/wp-crawlflow
php tests/test-simpleblog-crawl.php
```

#### 2. Via React UI
1. Go to CrawlFlow → Projects → New Project
2. Import config: `examples/simpleblog-oceanwp-config.json`
3. Click Save
4. Click Run

#### 3. Via PHP
```php
$rake = Rake::getInstance();
$projectService = $rake->make('CrawlFlow\Admin\ProjectService');
$flowService = $rake->make('CrawlFlow\Flow\FlowService');

// Create project
$configJson = file_get_contents('examples/simpleblog-oceanwp-config.json');
$flowConfig = json_decode($configJson, true);

$projectId = $projectService->createProject([
    'name' => 'SimpleBlog OceanWP',
    'status' => 'active',
    'project_data' => $flowConfig,
]);

// Execute
$context = $flowService->executeFlow($flowConfig);
$result = $context->getResult();

echo "Processed: {$result['processed_count']} posts\n";
```

### Customization

#### Change Target URL
Edit `simpleblog-oceanwp-config.json`:
```json
{
  "nodes": [
    {
      "id": "start-1",
      "data": {
        "sourceValue": "YOUR_BLOG_URL_HERE"
      }
    }
  ]
}
```

#### Change Selectors
Modify extraction rules:
```json
{
  "id": "extractor-post-data",
  "data": {
    "customRules": [
      {
        "name": "title",
        "selector": ".your-title-selector"
      }
    ]
  }
}
```

#### Change Post Status
Modify processor settings:
```json
{
  "id": "processor-save",
  "data": {
    "settings": {
      "postStatus": "publish"
    }
  }
}
```

### Notes
- Posts are saved as drafts by default
- Duplicate detection via source URL
- Can be scheduled to run automatically
- Schedule interval: 60 minutes (configurable)

