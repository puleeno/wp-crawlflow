<?php
/**
 * Test Full Crawl Pipeline
 * Tests complete flow: Fetch → Extract → Process with mock HTML
 */

// Load WordPress  
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

echo "=== Full Crawl Pipeline Test ===\n\n";

// Mock HTML from a blog
$mockHtml = <<<HTML
<!DOCTYPE html>
<html>
<body>
<article class="post">
    <h2 class="entry-title"><a href="https://example.com/post-1">First Blog Post</a></h2>
    <time datetime="2025-12-01">December 1, 2025</time>
    <div class="entry-content">This is the first blog post content with some interesting information.</div>
    <img src="https://example.com/image1.jpg" alt="Post 1">
</article>
<article class="post">
    <h2 class="entry-title"><a href="https://example.com/post-2">Second Blog Post</a></h2>
    <time datetime="2025-12-02">December 2, 2025</time>
    <div class="entry-content">This is the second blog post with more details and content.</div>
    <img src="https://example.com/image2.jpg" alt="Post 2">
</article>
<article class="post">
    <h2 class="entry-title"><a href="https://example.com/post-3">Third Amazing Post</a></h2>
    <time datetime="2025-12-03">December 3, 2025</time>
    <div class="entry-content">The third post contains valuable information about web development.</div>
    <img src="https://example.com/image3.jpg" alt="Post 3">
</article>
</body>
</html>
HTML;

try {
    $rake = \Rake\Rake::getInstance();
    
    echo "--- Step 1: Extract Data from HTML ---\n";
    $extractor = new \CrawlFlow\DataExtractors\HtmlDataExtractor();
    
    // Extract using blog post preset
    $posts = $extractor->extractBlogPosts($mockHtml);
    
    echo "✓ Extracted: " . count($posts) . " blog posts\n\n";
    
    foreach ($posts as $i => $post) {
        echo "Post " . ($i + 1) . ":\n";
        echo "  Title: " . ($post['title'] ?? 'N/A') . "\n";
        echo "  URL: " . ($post['url'] ?? 'N/A') . "\n";
        echo "  Date: " . ($post['date'] ?? 'N/A') . "\n";
        echo "  Content: " . substr($post['content'] ?? '', 0, 50) . "...\n";
        echo "  Image: " . ($post['image'] ?? 'N/A') . "\n";
        echo "\n";
    }
    
    echo "--- Step 2: Process to WordPress Posts ---\n";
    $processor = new \CrawlFlow\Processors\WordPressPostProcessor();
    
    $options = [
        'postType' => 'post',
        'postStatus' => 'draft',
        'authorId' => 1,
        'updateIfExists' => true,
    ];
    
    $results = $processor->processBatch($posts, $options);
    
    echo "✓ Processed: " . count($results) . " items\n\n";
    
    $successCount = 0;
    $failCount = 0;
    
    foreach ($results as $result) {
        if ($result['success']) {
            $successCount++;
            echo "  ✓ Created Post ID {$result['post_id']}: {$result['title']}\n";
        } else {
            $failCount++;
            echo "  ✗ Failed: {$result['title']} - {$result['error']}\n";
        }
    }
    
    echo "\n--- Step 3: Test via Flow Execution ---\n";
    
    // Create execution context with mock data
    $flowConfig = [
        'projectSettings' => ['name' => 'Test'],
        'nodes' => [
            ['id' => '1', 'type' => 'start'],
            ['id' => '2', 'type' => 'html-data-extractor'],
            ['id' => '3', 'type' => 'processor'],
        ],
        'edges' => [],
    ];
    
    $flow = \CrawlFlow\Flow\FlowConfig::fromArray($flowConfig);
    $context = new \CrawlFlow\Flow\ExecutionContext($flow);
    
    // Add mock resource to repository
    $context->addResource([
        'url' => 'https://example.com/blog',
        'body' => $mockHtml,
    ]);
    
    echo "✓ Mock resource added to repository\n";
    
    // Execute extractor node
    $extractorExecutor = new \CrawlFlow\Flow\Executors\HtmlDataExtractorNodeExecutor($rake->make('CrawlFlow\Flow\RakeAdapter'));
    
    $extractorNode = [
        'id' => '2',
        'type' => 'html-data-extractor',
        'data' => [
            'presets' => ['blog-posts'],
        ],
    ];
    
    $extractorResult = $extractorExecutor->execute($extractorNode, $context);
    
    if ($extractorResult->isSuccess()) {
        echo "✓ Extractor executed: " . count($extractorResult->getOutput()) . " items extracted\n";
        
        // Add to context
        foreach ($extractorResult->getOutput() as $item) {
            $context->addExtractedData($item);
        }
    } else {
        echo "✗ Extractor failed: " . $extractorResult->getError() . "\n";
    }
    
    // Execute processor node
    $processorExecutor = new \CrawlFlow\Flow\Executors\ProcessorNodeExecutor($rake->make('CrawlFlow\Flow\RakeAdapter'));
    
    $processorNode = [
        'id' => '3',
        'type' => 'processor',
        'data' => [
            'processorType' => 'save-to-wordpress',
            'settings' => [
                'postType' => 'post',
                'postStatus' => 'draft',
            ],
        ],
    ];
    
    $processorResult = $processorExecutor->execute($processorNode, $context);
    
    if ($processorResult->isSuccess()) {
        echo "✓ Processor executed: " . count($processorResult->getOutput()) . " items processed\n";
    } else {
        echo "✗ Processor failed: " . $processorResult->getError() . "\n";
    }
    
    echo "\n=== Pipeline Test Complete ===\n";
    echo "Summary:\n";
    echo "  Extracted: " . count($context->getExtractedData()) . " items\n";
    echo "  Processed: " . count($context->getProcessedResults()) . " items\n";
    echo "  Success: {$successCount}\n";
    echo "  Failed: {$failCount}\n";
    
    if ($successCount > 0) {
        echo "\n✓ Pipeline hoạt động! Check WordPress admin → Posts để xem.\n";
    }
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

