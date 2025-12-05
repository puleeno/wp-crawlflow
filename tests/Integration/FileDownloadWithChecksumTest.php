<?php
/**
 * Integration Test: File Download with Checksum Verification
 * 
 * Test the complete flow of downloading files with checksum verification
 * 
 * Run: wp eval-file wp-content/plugins/wp-crawlflow/tests/Integration/FileDownloadWithChecksumTest.php
 */

require_once __DIR__ . "/../../vendor/autoload.php";
require_once __DIR__ . "/../../wp-content/plugins/wp-crawlflow/vendor/puleeno/rake-wordpress-adapter/src/File/WordPressFileDownloaderClient.php";

use Rake\Manager\FileChecksumManager;
use Rake\Adapter\Database\WordPressDatabaseAdapter;
use RamphorRake\Adapter\File\WordPressFileDownloaderClient;

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║         INTEGRATION TEST: File Download with Checksum                   ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

global $wpdb;

$testResults = [
    'passed' => 0,
    'failed' => 0,
    'errors' => []
];

function testResult($name, $condition, &$results) {
    if ($condition) {
        echo "✅ {$name}\n";
        $results['passed']++;
    } else {
        echo "❌ {$name}\n";
        $results['failed']++;
        $results['errors'][] = $name;
    }
}

// Setup
$databaseAdapter = new WordPressDatabaseAdapter($wpdb);
$checksumManager = new FileChecksumManager($databaseAdapter);
$fileDownloader = new WordPressFileDownloaderClient([
    'timeout' => 30,
    'sslverify' => false
]);

// Test 1: Download file with checksum calculation
echo "📋 Test 1: Download File with Checksum Calculation\n";
echo str_repeat('─', 80) . "\n";

$testUrl = 'https://httpbin.org/base64/' . base64_encode('Test content for checksum');
$tempFile = sys_get_temp_dir() . '/integration_test_' . time() . '.txt';

try {
    $result = $fileDownloader->downloadFile($testUrl, $tempFile, [
        'calculate_checksum' => true
    ]);
    
    testResult("File downloaded successfully", $result['success'], $testResults);
    
    if ($result['success'] && isset($result['checksum'])) {
        $downloadedChecksum = $result['checksum'];
        testResult("Checksum calculated", true, $testResults);
        testResult("Checksum format valid", strlen($downloadedChecksum) === 32 && ctype_xdigit($downloadedChecksum), $testResults);
        echo "   Checksum: {$downloadedChecksum}\n";
    }
} catch (\Exception $e) {
    testResult("Download with checksum", false, $testResults);
    $testResults['errors'][] = "Download error: " . $e->getMessage();
}

// Test 2: Save checksum and verify integrity
echo "\n📋 Test 2: Save Checksum and Verify Integrity\n";
echo str_repeat('─', 80) . "\n";

if (file_exists($tempFile) && isset($downloadedChecksum)) {
    try {
        $testResourceId = 777;
        
        $saved = $checksumManager->saveChecksum($testResourceId, $downloadedChecksum);
        testResult("Checksum saved to database", $saved, $testResults);
        
        $isValid = $checksumManager->verifyFileIntegrity($testResourceId, $tempFile);
        testResult("File integrity verified", $isValid, $testResults);
        
        // Modify file and verify it fails
        file_put_contents($tempFile, "Modified content");
        $isValidAfterModify = $checksumManager->verifyFileIntegrity($testResourceId, $tempFile);
        testResult("Integrity check fails after modification", !$isValidAfterModify, $testResults);
        
        $checksumManager->deleteChecksum($testResourceId);
    } catch (\Exception $e) {
        testResult("Checksum save and verify", false, $testResults);
    }
} else {
    echo "⚠️ Skipping Test 2 (file not downloaded)\n";
}

// Test 3: Duplicate detection
echo "\n📋 Test 3: Duplicate Detection\n";
echo str_repeat('─', 80) . "\n";

if (file_exists($tempFile) && isset($downloadedChecksum)) {
    try {
        $resourceId1 = 100;
        $checksumManager->saveChecksum($resourceId1, $downloadedChecksum);
        
        $exists = $checksumManager->checksumExists($downloadedChecksum);
        testResult("Checksum exists check", $exists, $testResults);
        
        $foundResourceId = $checksumManager->getResourceIdByChecksum($downloadedChecksum);
        testResult("Get resource ID by checksum", $foundResourceId === $resourceId1, $testResults);
        
        $checksumManager->deleteChecksum($resourceId1);
    } catch (\Exception $e) {
        testResult("Duplicate detection", false, $testResults);
    }
} else {
    echo "⚠️ Skipping Test 3 (file not downloaded)\n";
}

// Cleanup
if (file_exists($tempFile)) {
    @unlink($tempFile);
}

// Summary
echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║         TEST SUMMARY                                                      ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

echo "✅ Passed: {$testResults['passed']}\n";
echo "❌ Failed: {$testResults['failed']}\n";

if (!empty($testResults['errors'])) {
    echo "\nErrors:\n";
    foreach ($testResults['errors'] as $error) {
        echo "  - {$error}\n";
    }
}

echo "\n";
echo ($testResults['failed'] === 0) ? "✅ ALL TESTS PASSED\n" : "⚠️ SOME TESTS FAILED\n";
echo "\n";
echo "📝 Note: This test requires internet connection (httpbin.org)\n";
echo "\n";
