<?php
/**
 * Unit Test: FileChecksumManager
 * 
 * Test checksum creation, validation, and verification
 * 
 * Run: wp eval-file wp-content/plugins/wp-crawlflow/tests/Unit/Manager/FileChecksumManagerTest.php
 */

require_once __DIR__ . "/../../../vendor/autoload.php";

use Rake\Manager\FileChecksumManager;
use Puleeno\Rake\WordPress\Adapter\WordPressDatabaseAdapter;

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║         UNIT TEST: FileChecksumManager                                    ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

global $wpdb;

// Setup
$databaseAdapter = new WordPressDatabaseAdapter($wpdb);
$checksumManager = new FileChecksumManager($databaseAdapter);

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

// Test 1: Create checksum from content
echo "📋 Test 1: Create Checksum from Content\n";
echo str_repeat('─', 80) . "\n";

$testContent = "This is a test content for checksum verification";
$checksum1 = $checksumManager->createChecksum($testContent);
testResult("Checksum created", !empty($checksum1) && strlen($checksum1) === 32, $testResults);
testResult("Checksum is hex", ctype_xdigit($checksum1), $testResults);

// Same content should produce same checksum
$checksum2 = $checksumManager->createChecksum($testContent);
testResult("Checksum is deterministic", $checksum1 === $checksum2, $testResults);

// Different content should produce different checksum
$differentContent = "This is different content";
$checksum3 = $checksumManager->createChecksum($differentContent);
testResult("Different content produces different checksum", $checksum1 !== $checksum3, $testResults);

// Test 2: Validate checksum format
echo "\n📋 Test 2: Validate Checksum Format\n";
echo str_repeat('─', 80) . "\n";

testResult("Valid checksum format", $checksumManager->validateChecksum($checksum1), $testResults);
testResult("Invalid checksum (too short)", !$checksumManager->validateChecksum("abc123"), $testResults);
testResult("Invalid checksum (non-hex)", !$checksumManager->validateChecksum("xyz" . str_repeat("0", 29)), $testResults);
testResult("Invalid checksum (wrong length)", !$checksumManager->validateChecksum(str_repeat("a", 31)), $testResults);

// Test 3: Create checksum from file
echo "\n📋 Test 3: Create Checksum from File\n";
echo str_repeat('─', 80) . "\n";

$testFile = sys_get_temp_dir() . '/test_checksum_' . time() . '.txt';
file_put_contents($testFile, $testContent);

try {
    $fileChecksum = $checksumManager->createFileChecksum($testFile);
    testResult("File checksum created", !empty($fileChecksum), $testResults);
    testResult("File checksum matches content checksum", $fileChecksum === $checksum1, $testResults);
    
    // Cleanup
    @unlink($testFile);
} catch (\Exception $e) {
    testResult("File checksum creation", false, $testResults);
    $testResults['errors'][] = "File checksum error: " . $e->getMessage();
}

// Test 4: Save and retrieve checksum
echo "\n📋 Test 4: Save and Retrieve Checksum\n";
echo str_repeat('─', 80) . "\n";

$testResourceId = 999;
$saved = $checksumManager->saveChecksum($testResourceId, $checksum1);
testResult("Checksum saved", $saved, $testResults);

$retrievedChecksum = $checksumManager->getChecksumByResourceId($testResourceId);
testResult("Checksum retrieved", $retrievedChecksum === $checksum1, $testResults);

// Test 5: Check duplicate detection
echo "\n📋 Test 5: Duplicate Detection\n";
echo str_repeat('─', 80) . "\n";

$exists = $checksumManager->checksumExists($checksum1);
testResult("Checksum exists check", $exists, $testResults);

$resourceIdByChecksum = $checksumManager->getResourceIdByChecksum($checksum1);
testResult("Get resource ID by checksum", $resourceIdByChecksum === $testResourceId, $testResults);

// Test 6: Verify file integrity
echo "\n📋 Test 6: Verify File Integrity\n";
echo str_repeat('─', 80) . "\n";

$testFile2 = sys_get_temp_dir() . '/test_integrity_' . time() . '.txt';
file_put_contents($testFile2, $testContent);

try {
    $isValid = $checksumManager->verifyFileIntegrity($testResourceId, $testFile2);
    testResult("File integrity verified", $isValid, $testResults);
    
    // Modify file and verify it fails
    file_put_contents($testFile2, $testContent . " modified");
    $isValidAfterModify = $checksumManager->verifyFileIntegrity($testResourceId, $testFile2);
    testResult("File integrity fails after modification", !$isValidAfterModify, $testResults);
    
    @unlink($testFile2);
} catch (\Exception $e) {
    testResult("File integrity verification", false, $testResults);
    $testResults['errors'][] = "Integrity verification error: " . $e->getMessage();
}

// Cleanup
$checksumManager->deleteChecksum($testResourceId);

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
