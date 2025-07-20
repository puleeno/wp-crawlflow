<?php

namespace CrawlFlow;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;
use Rake\Facade\Logger as RakeLogger;
use Rake\Rake;

/**
 * Logger Service for CrawlFlow Plugin
 *
 * Global logger service that works for both admin and CLI
 * Uses lazy loading - only initializes when actually needed
 */
class LoggerService
{
    /**
     * @var LoggerInterface|null
     */
    private static $logger = null;

    /**
     * @var bool
     */
    private static $initialized = false;

    /**
     * Get or create Monolog logger instance (lazy loading)
     *
     * @return LoggerInterface
     */
    public static function getLogger(): LoggerInterface
    {
        if (is_null(self::$logger)) {
            self::$logger = self::createMonologLogger();
        }

        return self::$logger;
    }

    /**
     * Create Monolog logger with handlers
     *
     * @return MonologLogger
     */
    private static function createMonologLogger(): MonologLogger
    {
        // Create log file path - one file per day
        $logFile = sprintf(
            '%s/crawlflow/crawlflow-%s.log',
            WP_CONTENT_DIR,
            date('Y-m-d')
        );

        // Ensure log directory exists
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            wp_mkdir_p($logDir);
        }

        // Create handlers
        $fileHandler = new StreamHandler(
            apply_filters('crawlflow/logger', $logFile),
            MonologLogger::DEBUG
        );

        // Only add stdout handler in CLI mode
        $logger = new MonologLogger('CRAWLFLOW');
        $logger->pushHandler($fileHandler);

        // Add stdout handler only for CLI
        if (php_sapi_name() === 'cli') {
            $stdOutHandler = new StreamHandler('php://stdout', MonologLogger::INFO);
            $logger->pushHandler($stdOutHandler);
        }

        return $logger;
    }

    /**
     * Register logger with Rake LoggerManager (lazy loading)
     *
     * @return void
     */
    public static function registerWithRake(): void
    {
        // Only register if not already done
        if (self::$initialized) {
            return;
        }

        try {
            // Ensure Rake container is initialized
            $rake = Rake::getInstance();

            // Get logger instance (lazy loading)
            $logger = self::getLogger();

            // Set logger in Rake LoggerManager
            RakeLogger::setLogger($logger);

            self::$initialized = true;
        } catch (\Exception $e) {
            // Fallback to error_log if Rake container is not available
            error_log('CrawlFlow Logger Service registration failed: ' . $e->getMessage());
        }
    }

    /**
     * Initialize logger for plugin (lazy loading)
     *
     * @return void
     */
    public static function init(): void
    {
        // Only initialize if not already done
        if (self::$initialized) {
            return;
        }

        try {
            self::registerWithRake();

            $logger = self::getLogger();
            $logger->info('CrawlFlow Logger Service initialized', [
                'pid' => getmypid(),
                'sapi' => php_sapi_name(),
                'memory_usage' => memory_get_usage(true)
            ]);

        } catch (\Exception $e) {
            // Fallback to error_log if Rake container is not available
            error_log('CrawlFlow Logger Service initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Check if logger is initialized
     *
     * @return bool
     */
    public static function isInitialized(): bool
    {
        return self::$initialized;
    }

    /**
     * Get log file path
     *
     * @return string
     */
    public static function getLogFilePath(): string
    {
        return sprintf(
            '%s/crawlflow/crawlflow-%s.log',
            WP_CONTENT_DIR,
            date('Y-m-d')
        );
    }

    /**
     * Get current log file size
     *
     * @return int
     */
    public static function getLogFileSize(): int
    {
        $logFile = self::getLogFilePath();
        return file_exists($logFile) ? filesize($logFile) : 0;
    }

    /**
     * Clear logger instance (for testing)
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$logger = null;
        self::$initialized = false;
    }
}