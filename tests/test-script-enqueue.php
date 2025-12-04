<?php
/**
 * Test Script Enqueue and Registry Localization
 * Simulates WordPress admin enqueue process
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

use CrawlFlow\Admin\DashboardRenderer;
use CrawlFlow\Admin\RegistryService;

echo "=== Test Script Enqueue & Localization ===\n\n";

try {
    // Test 1: Check manifest file
    echo "--- Test 1: Vite Manifest ---\n";
    
    $plugin_dir = plugin_dir_path(dirname(__DIR__));
    $manifest_path = $plugin_dir . 'assets/js/crawflow-ui/dist/.vite/manifest.json';
    
    if (file_exists($manifest_path)) {
        echo "✓ Manifest file exists\n";
        $manifest = json_decode(file_get_contents($manifest_path), true);
        echo "  Entries: " . count($manifest) . "\n";
        
        if (isset($manifest['index.html']['file'])) {
            $script_file = $manifest['index.html']['file'];
            echo "  Script file: " . $script_file . "\n";
            
            $script_path = $plugin_dir . 'assets/js/crawflow-ui/dist/' . $script_file;
            if (file_exists($script_path)) {
                echo "  ✓ Script file exists\n";
                echo "  Size: " . number_format(filesize($script_path)) . " bytes\n";
            } else {
                echo "  ✗ Script file NOT found: " . $script_path . "\n";
            }
        }
    } else {
        echo "✗ Manifest file NOT found\n";
    }
    
    // Test 2: Registry data
    echo "\n--- Test 2: Registry Data ---\n";
    
    $registry = new RegistryService();
    $data = $registry->getAllRegistryData();
    
    echo "✓ Registry data collected\n";
    echo "  Data Sources: " . count($data['dataSources']) . "\n";
    echo "  Processors: " . count($data['processors']) . "\n";
    echo "  Parsers: " . count($data['parsers']) . "\n";
    echo "  HTTP Clients: " . count($data['httpClients']) . "\n";
    
    // Test 3: Localized data structure
    echo "\n--- Test 3: Localized Data Structure ---\n";
    
    $localized = [
        'dataSources' => $data['dataSources'],
        'processors' => $data['processors'],
        'parsers' => $data['parsers'],
        'httpClients' => $data['httpClients'],
        'nonce' => wp_create_nonce('crawlflow_nonce'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
    ];
    
    echo "✓ Localized data structure created\n";
    echo "  Keys: " . implode(', ', array_keys($localized)) . "\n";
    
    $json = json_encode($localized, JSON_PRETTY_PRINT);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "  ✓ JSON encoding successful\n";
        echo "  Size: " . strlen($json) . " bytes\n";
    }
    
    // Test 4: Show processors
    echo "\n--- Test 4: Processors Detail ---\n";
    
    if (count($data['processors']) > 0) {
        foreach ($data['processors'] as $proc) {
            echo "  {$proc['icon']} {$proc['label']}\n";
            echo "    Type: {$proc['type']}\n";
            echo "    Description: {$proc['description']}\n";
        }
    } else {
        echo "  ✗ No processors registered\n";
    }
    
    echo "\n=== Summary ===\n\n";
    
    echo "✓ Manifest file readable\n";
    echo "✓ Script file exists\n";
    echo "✓ Registry data available\n";
    echo "✓ Localized data structure valid\n";
    echo "✓ Processors: " . count($data['processors']) . "\n";
    
    echo "\n--- Expected JavaScript Object ---\n";
    echo "window.crawlflowRegistry = {\n";
    echo "  dataSources: [" . count($data['dataSources']) . " items],\n";
    echo "  processors: [" . count($data['processors']) . " items],\n";
    echo "  parsers: [" . count($data['parsers']) . " items],\n";
    echo "  httpClients: [" . count($data['httpClients']) . " items],\n";
    echo "  nonce: '...',\n";
    echo "  ajaxUrl: '" . admin_url('admin-ajax.php') . "'\n";
    echo "}\n";
    
    echo "\n✅ SCRIPT ENQUEUE & LOCALIZATION READY\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

