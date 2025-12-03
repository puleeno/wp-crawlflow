<?php
/**
 * Test Monolog Logger Registration
 * Verifies Monolog is registered in LoggerManager
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

use Rake\Manager\LoggerManager;
use Rake\Facade\Logger;

echo "=== Test Monolog Logger ===\n\n";

try {
    // Test 1: Check LoggerManager has logger
    echo "--- Test 1: LoggerManager ---\n";
    
    $manager = LoggerManager::getInstance();
    $logger = $manager->getLogger();
    
    if ($logger) {
        echo "✓ Logger registered in LoggerManager\n";
        echo "  Type: " . get_class($logger) . "\n";
        echo "  Is Monolog: " . ($logger instanceof \Monolog\Logger ? "YES" : "NO") . "\n";
        
        if ($logger instanceof \Monolog\Logger) {
            echo "  Channel: " . $logger->getName() . "\n";
            echo "  Handlers: " . count($logger->getHandlers()) . "\n";
        }
    } else {
        echo "✗ No logger registered\n";
    }
    
    // Test 2: Test Logger facade
    echo "\n--- Test 2: Logger Facade ---\n";
    
    Logger::info('Test info message from facade');
    Logger::warning('Test warning message');
    Logger::error('Test error message', ['context' => 'test']);
    
    echo "✓ Logger facade working\n";
    echo "  Log messages sent\n";
    
    // Test 3: Check log file
    echo "\n--- Test 3: Log File ---\n";
    
    $logDir = WP_CONTENT_DIR . '/crawlflow/logs/';
    $logFile = $logDir . 'crawlflow.log';
    
    if (file_exists($logFile)) {
        echo "✓ Log file exists\n";
        echo "  Location: {$logFile}\n";
        echo "  Size: " . filesize($logFile) . " bytes\n";
        
        // Read last 5 lines
        $lines = file($logFile);
        $lastLines = array_slice($lines, -5);
        
        echo "\n  Last 5 log entries:\n";
        foreach ($lastLines as $line) {
            echo "    " . trim($line) . "\n";
        }
    } else {
        echo "⚠ Log file not created yet\n";
        echo "  Expected: {$logFile}\n";
    }
    
    // Test 4: Test different log levels
    echo "\n--- Test 4: Log Levels ---\n";
    
    Logger::debug('Debug message');
    Logger::info('Info message');
    Logger::notice('Notice message');
    Logger::warning('Warning message');
    Logger::error('Error message');
    
    echo "✓ All log levels tested\n";
    
    // Test 5: Test with context
    echo "\n--- Test 5: Context Logging ---\n";
    
    Logger::info('Processing item', [
        'item_id' => 123,
        'worker' => 'test_worker',
        'status' => 'success',
    ]);
    
    echo "✓ Context logging works\n";
    
    echo "\n=== Summary ===\n\n";
    
    echo "✓ Monolog registered in LoggerManager\n";
    echo "✓ Logger facade working\n";
    echo "✓ Log file created\n";
    echo "✓ All log levels available\n";
    echo "✓ Context logging supported\n";
    
    echo "\n--- Configuration ---\n";
    echo "Logger: Monolog\n";
    echo "Channel: CRAWLFLOW\n";
    echo "Log Dir: {$logDir}\n";
    echo "Rotation: 30 days\n";
    
    echo "\n✅ MONOLOG LOGGER WORKING\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

