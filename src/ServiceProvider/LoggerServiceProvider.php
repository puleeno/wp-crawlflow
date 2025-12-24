<?php

namespace CrawlFlow\ServiceProvider;

use Rake\Rake;
use Rake\ServiceProvider\AbstractServiceProvider;
use Rake\Manager\LoggerManager;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

/**
 * Logger Service Provider
 * Registers Monolog logger for CrawlFlow plugin
 */
class LoggerServiceProvider extends AbstractServiceProvider
{
    /**
     * Register logger services
     */
    protected function registerServices(): void
    {
        // Register LoggerManager (already done in Rake Bootstrapper)
        // Just configure it with Monolog

        $this->configureMonologLogger();
    }

    /**
     * Boot logger services
     */
    protected function bootServices(): void
    {
        // Nothing to boot
    }

    /**
     * Configure Monolog logger
     */
    private function configureMonologLogger(): void
    {
        // Create Monolog logger
        $logger = new Logger('CRAWLFLOW');

        // Use same log file path as LoggerService to prevent duplicates
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

        // Create formatter
        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context%\n",
            'Y-m-d\TH:i:s.uP',
            true,
            true
        );

        // Add file handler (same as LoggerService)
        $fileHandler = new StreamHandler(
            $logFile,
            Logger::DEBUG
        );
        $fileHandler->setFormatter($formatter);
        $logger->pushHandler($fileHandler);

        // Add stdout handler only for CLI (same as LoggerService)
        if (php_sapi_name() === 'cli') {
            $stdOutHandler = new StreamHandler('php://stdout', Logger::INFO);
            $logger->pushHandler($stdOutHandler);
        }

        // Set logger in LoggerManager
        $loggerManager = LoggerManager::getInstance();
        $loggerManager->setLogger($logger);

        // Log initialization
        $logger->info('CrawlFlow Logger Service initialized', [
            'pid' => getmypid(),
            'sapi' => PHP_SAPI,
            'memory_usage' => memory_get_usage(),
        ]);
    }
}

