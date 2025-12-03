<?php
/**
 * Test Summary Script
 * Runs all tests and shows clean summary
 */

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║   WP-CRAWLFLOW TEST SUITE                                      ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Change to plugin directory
chdir(__DIR__ . '/..');

// Run PHPUnit and capture output
$output = [];
$exitCode = 0;
exec('php vendor/phpunit/phpunit/phpunit tests/ 2>&1', $output, $exitCode);

// Parse output
$totalTests = 0;
$totalAssertions = 0;
$failures = 0;
$errors = 0;
$warnings = 0;
$skipped = 0;

foreach ($output as $line) {
    if (preg_match('/Tests:\s+(\d+)/', $line, $matches)) {
        $totalTests = $matches[1];
    }
    if (preg_match('/Assertions:\s+(\d+)/', $line, $matches)) {
        $totalAssertions = $matches[1];
    }
    if (preg_match('/Failures:\s+(\d+)/', $line, $matches)) {
        $failures = $matches[1];
    }
    if (preg_match('/Errors:\s+(\d+)/', $line, $matches)) {
        $errors = $matches[1];
    }
    if (preg_match('/Warnings:\s+(\d+)/', $line, $matches)) {
        $warnings = $matches[1];
    }
    if (preg_match('/Skipped:\s+(\d+)/', $line, $matches)) {
        $skipped = $matches[1];
    }
}

// Show summary
echo "SUMMARY:\n";
echo "--------\n";
echo "Total Tests:      $totalTests\n";
echo "Total Assertions: $totalAssertions\n";
echo "Failures:         $failures\n";
echo "Errors:           $errors\n";
echo "Warnings:         $warnings\n";
echo "Skipped:          $skipped\n";
echo "\n";

// Status
if ($failures == 0 && $errors == 0) {
    echo "✅ STATUS: ALL TESTS PASSING\n";
} else {
    echo "❌ STATUS: TESTS FAILED\n";
}

echo "\nExit Code: $exitCode\n";

exit($exitCode);

