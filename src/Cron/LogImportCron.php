<?php

namespace CrawlFlow\Cron;

/**
 * Log Import Cron Service
 * Automatically imports CrawlFlow log files to wpd4_rake_logs table
 */
class LogImportCron
{
    /**
     * Register cron schedules and hooks
     */
    public static function init(): void
    {
        // Add custom cron schedule (every 5 minutes)
        add_filter('cron_schedules', function ($schedules) {
            $schedules['crawlflow_log_import'] = [
                'interval' => 300, // 5 minutes
                'display' => __('Every 5 minutes', 'crawlflow'),
            ];
            return $schedules;
        });

        // Schedule the event if not already scheduled
        if (!wp_next_scheduled('crawlflow_import_logs_event')) {
            wp_schedule_event(time(), 'crawlflow_log_import', 'crawlflow_import_logs_event');
        }

        // Hook the import function
        add_action('crawlflow_import_logs_event', [self::class, 'importLogsCallback']);
    }

    /**
     * Cron callback to import logs
     */
    public static function importLogsCallback(): void
    {
        // Allow running in both cron and CLI mode
        $lockFile = WP_CONTENT_DIR . '/crawlflow/.log-import.lock';
        
        // Check if import is already running
        if (file_exists($lockFile)) {
            $lockTime = filemtime($lockFile);
            $now = time();
            
            // If lock is older than 30 minutes, remove it (stale lock)
            if (($now - $lockTime) > 1800) {
                unlink($lockFile);
            } else {
                // Import is already running, skip
                return;
            }
        }

        // Create lock file
        file_put_contents($lockFile, getmypid());

        try {
            $result = self::executeLogImport();
            
            // Log result to WordPress debug log
            error_log(sprintf(
                'CrawlFlow Log Import: %s - Files: %d imported, %d skipped',
                $result['success'] ? 'Success' : 'Failed',
                $result['imported'],
                $result['skipped']
            ));
            
        } catch (\Exception $e) {
            error_log('CrawlFlow Log Import Error: ' . $e->getMessage());
        } finally {
            // Remove lock file
            if (file_exists($lockFile)) {
                unlink($lockFile);
            }
        }
    }

    /**
     * Execute the actual log import
     */
    private static function executeLogImport(): array
    {
        global $wpdb;

        $logDir = WP_CONTENT_DIR . '/crawlflow';
        $archiveDir = WP_CONTENT_DIR . '/crawlflow/logs';

        // Ensure archive directory exists
        if (!is_dir($archiveDir)) {
            wp_mkdir_p($archiveDir);
        }

        // Scan for log files
        $logFiles = glob($logDir . '/crawlflow-*.log');

        if (empty($logFiles)) {
            return ['success' => true, 'imported' => 0, 'skipped' => 0];
        }

        $importedCount = 0;
        $skippedCount = 0;

        foreach ($logFiles as $logFile) {
            $basename = basename($logFile);
            
            // Skip if already imported
            $markerFile = $logFile . '.imported';
            if (file_exists($markerFile)) {
                $skippedCount++;
                continue;
            }
            
            // Parse filename - handle both formats:
            // Format 1: crawlflow-<project_id>--<process_id>-YYYY-MM-DD.log
            // Format 2: crawlflow--<process_id>-YYYY-MM-DD.log (no project_id)
            
            // Try format 2 first (no project_id)
            if (preg_match('/^crawlflow--(\d+)-(\d{4}-\d{2}-\d{2})\.log$/', $basename, $matches)) {
                $projectId = null;
                $processId = (int) $matches[1];
                $logDate = $matches[2];
            }
            // Try format 1 (with project_id)
            elseif (preg_match('/^crawlflow-(\d+)--(\d+)-(\d{4}-\d{2}-\d{2})\.log$/', $basename, $matches)) {
                $projectId = (int) $matches[1];
                $processId = (int) $matches[2];
                $logDate = $matches[3];
            }
            else {
                $skippedCount++;
                continue;
            }
            
            // Check if process is still running
            $isRunning = false;
            echo "DEBUG: Checking PID {$processId} for file {$basename}\n";
            
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $output = [];
                $returnCode = 0;
                exec("tasklist /FI \"PID eq {$processId}\" /FO CSV", $output, $returnCode);
                echo "DEBUG: tasklist return code: {$returnCode}\n";
                echo "DEBUG: tasklist output: " . print_r($output, true) . "\n";
                
                // Parse CSV output properly - check if we have actual process data
                $hasProcessData = false;
                foreach ($output as $line) {
                    if (strpos($line, '"httpd.exe"') !== false || strpos($line, '"php.exe"') !== false) {
                        if (strpos($line, "\"{$processId}\"") !== false) {
                            $hasProcessData = true;
                            break;
                        }
                    }
                }
                $isRunning = ($returnCode === 0 && $hasProcessData);
            } else {
                $output = [];
                $returnCode = 0;
                exec("ps -p {$processId} -o pid= 2>/dev/null", $output, $returnCode);
                $isRunning = ($returnCode === 0 && !empty($output));
            }
            
            echo "DEBUG: Process {$processId} is " . ($isRunning ? 'RUNNING' : 'STOPPED') . "\n";
            
            if ($isRunning) {
                $skippedCount++;
                continue;
            }
            
            // Check file modification time
            $lastModified = filemtime($logFile);
            $now = time();
            $timeDiff = $now - $lastModified;
            
            if ($timeDiff < 300) { // 5 minutes
                $skippedCount++;
                continue;
            }
            
            // Import the log file
            $importedLines = self::importLogFile($logFile, $projectId, $wpdb);
            
            if ($importedLines > 0) {
                // Create marker file
                file_put_contents($markerFile, "Imported at " . date('Y-m-d H:i:s') . "\n");
                
                // Move to archive
                $archiveFile = $archiveDir . '/' . $basename;
                if (rename($logFile, $archiveFile)) {
                    $importedCount++;
                }
            } else {
                $skippedCount++;
            }
        }

        return [
            'success' => true,
            'imported' => $importedCount,
            'skipped' => $skippedCount,
        ];
    }

    /**
     * Import a single log file
     */
    private static function importLogFile(string $logFile, ?int $projectId, $wpdb): int
    {
        $table = $wpdb->prefix . 'rake_logs';
        $importedLines = 0;
        
        $handle = fopen($logFile, 'r');
        if (!$handle) {
            return 0;
        }
        
        $batch = [];
        $batchSize = 100;
        
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            $parsed = self::parseLogLine($line);
            if (!$parsed) {
                continue;
            }
            
            $batch[] = [
                'level' => $parsed['level'],
                'message' => $parsed['message'],
                'context' => $parsed['context'],
                'tooth_id' => $projectId, // Can be null for files without project_id
                'created_at' => $parsed['created_at'],
            ];
            
            if (count($batch) >= $batchSize) {
                self::insertLogBatch($table, $batch, $wpdb);
                $importedLines += count($batch);
                $batch = [];
            }
        }
        
        // Insert remaining batch
        if (!empty($batch)) {
            self::insertLogBatch($table, $batch, $wpdb);
            $importedLines += count($batch);
        }
        
        fclose($handle);
        return $importedLines;
    }

    /**
     * Parse a single Monolog log line
     */
    private static function parseLogLine(string $line): ?array
    {
        $pattern = '/^\[(?P<datetime>[^\]]+)\]\s+(?P<channel>[^.]+)\.(?P<level>[^:]+):\s+(?P<message>.*?)(?:\s+(?P<context>\{.*\}))?\s*$/';
        
        if (!preg_match($pattern, $line, $matches)) {
            return null;
        }
        
        $datetime = $matches['datetime'];
        $level = strtolower($matches['level']);
        $message = trim($matches['message']);
        $context = isset($matches['context']) ? $matches['context'] : null;
        
        $dateTimeObj = \DateTime::createFromFormat('Y-m-d\TH:i:s.uP', $datetime);
        if (!$dateTimeObj) {
            // Fallback: try without microseconds
            $dateTimeObj = \DateTime::createFromFormat('Y-m-d\TH:i:sP', $datetime);
        }
        
        if (!$dateTimeObj) {
            return null;
        }
        
        $mysqlDateTime = $dateTimeObj->format('Y-m-d H:i:s');
        
        if ($context && !json_decode($context)) {
            $context = null;
        }
        
        return [
            'created_at' => $mysqlDateTime,
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }

    /**
     * Insert batch of log entries
     */
    private static function insertLogBatch(string $table, array $batch, $wpdb): void
    {
        $values = [];
        $placeholders = [];
        
        foreach ($batch as $entry) {
            $values[] = $wpdb->prepare('%s, %s, %s, %s, %s',
                $entry['level'],
                $entry['message'],
                $entry['context'],
                $entry['tooth_id'],
                $entry['created_at']
            );
            $placeholders[] = '(%s)';
        }
        
        $sql = "INSERT INTO {$table} (level, message, context, tooth_id, created_at) VALUES " . implode(',', $placeholders);
        
        $wpdb->query($sql, ...$values);
    }

    /**
     * Test method to run log import manually (bypasses cron context)
     */
    public static function runImportManually(): array
    {
        $lockFile = WP_CONTENT_DIR . '/crawlflow/.log-import.lock';
        
        // Check if import is already running
        if (file_exists($lockFile)) {
            $lockTime = filemtime($lockFile);
            $now = time();
            
            // If lock is older than 30 minutes, remove it (stale lock)
            if (($now - $lockTime) > 1800) {
                unlink($lockFile);
            } else {
                return ['success' => false, 'message' => 'Import already running', 'imported' => 0, 'skipped' => 0];
            }
        }

        // Create lock file
        file_put_contents($lockFile, getmypid());

        try {
            $result = self::executeLogImport();
            return $result;
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'imported' => 0, 'skipped' => 0];
        } finally {
            // Remove lock file
            if (file_exists($lockFile)) {
                unlink($lockFile);
            }
        }
    }

    /**
     * Cleanup on plugin deactivation
     */
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('crawlflow_import_logs_event');
        
        // Remove lock file
        $lockFile = WP_CONTENT_DIR . '/crawlflow/.log-import.lock';
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }
}
