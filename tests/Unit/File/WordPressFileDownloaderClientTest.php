<?php
/**
 * Unit Test: WordPressFileDownloaderClient
 * 
 * Test file download with checksum verification
 * 
 * Run: wp eval-file wp-content/plugins/wp-crawlflow/tests/Unit/File/WordPressFileDownloaderClientTest.php
 */

require_once __DIR__ . "/../../../vendor/autoload.php";
require_once __DIR__ . "/../../../wp-content/plugins/wp-crawlflow/vendor/puleeno/rake-wordpress-adapter/src/File/WordPressFileDownloaderClient.php";

use RamphorRake\Adapter\File\WordPressFileDownloaderClient;

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║         UNIT TEST: WordPressFileDownloaderClient                         ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

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
$downloader = new WordPressFileDownloaderClient([
    'timeout' => 10,
    'sslverify' => false
]);

// Test 1: Download with checksum calculation
echo "📋 Test 1: Download with Checksum Calculation\n";
echo str_repeat('─', 80) . "\n";

$testContent = "Test file content for checksum verification " . time();
$testFileUrl = 'https://httpbin.org/base64/' . base64_encode($testContent);
$tempFile = sys_get_temp_dir() . '/test_download_' . time() . '.txt';

try {
    $result = $downloader->downloadFile($testFileUrl, $tempFile, [
        'calculate_checksum' => true
    ]);
    
    if ($result['success']) {
        testResult("File downloaded", true, $testResults);
        testResult("Checksum calculated", isset($result['checksum']), $testResults);
        testResult("Checksum is valid format", isset($result['checksum']) && strlen($result['checksum']) === 32, $testResults);
        
        if (isset($result['checksum'])) {
            testResult("Checksum is hex", ctype_xdigit($result['checksum']), $testResults);
            $downloadedChecksum = $result['checksum'];
        }
        
        @unlink($tempFile);
    } else {
        testResult("File downloaded", false, $testResults);
        echo "   Error: " . ($result['error'] ?? 'Unknown') . "\n";
    }
} catch (\Exception $e) {
    testResult("Download with checksum", false, $testResults);
    $testResults['errors'][] = "Download error: " . $e->getMessage();
}

// Test 2: Download with checksum verification
echo "\n📋 Test 2: Download with Checksum Verification\n";
echo str_repeat('─', 80) . "\n";

if (isset($downloadedChecksum)) {
    $tempFile2 = sys_get_temp_dir() . '/test_download_verify_' . time() . '.txt';
    
    try {
        // Download with correct expected checksum
        $result2 = $downloader->downloadFile($testFileUrl, $tempFile2, [
            'verify_checksum' => true,
            'expected_checksum' => $downloadedChecksum
        ]);
        
        testResult("Download with correct expected checksum", $result2['success'], $testResults);
        
        // Download with wrong expected checksum
        @unlink($tempFile2);
        $result3 = $downloader->downloadFile($testFileUrl, $tempFile2, [
            'verify_checksum' => true,
            'expected_checksum' => '00000000000000000000000000000000'
        ]);
        
        testResult("Download with wrong expected checksum fails", !$result3['success'], $testResults);
        testResult("Error message indicates checksum failure", 
            isset($result3['error']) && strpos($result3['error'], 'checksum') !== false, $testResults);
        
        @unlink($tempFile2);
    } catch (\Exception $e) {
        testResult("Download with checksum verification", false, $testResults);
    }
} else {
    echo "⚠️ Skipping Test 2 (checksum not available)\n";
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
