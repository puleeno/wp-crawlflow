<?php
/**
 * Run All Tests and Show Summary
 */

echo "=== Running All Tests ===\n\n";

// Change to plugin directory
chdir(__DIR__ . '/..');

// Run PHPUnit
passthru('php vendor/phpunit/phpunit/phpunit tests/ --no-coverage 2>&1', $exitCode);

echo "\n=== Test Run Complete ===\n";
echo "Exit Code: $exitCode\n";

exit($exitCode);

