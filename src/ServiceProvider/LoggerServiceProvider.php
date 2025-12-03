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

        // Log directory
        $logDir = WP_CONTENT_DIR . '/crawlflow/logs/';
        
        // Ensure log directory exists
        if (!file_exists($logDir)) {
            wp_mkdir_p($logDir);
        }

        // Create formatter
        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context%\n",
            'Y-m-d\TH:i:s.uP',
            true,
            true
        );

        // Add rotating file handler (keeps 30 days of logs)
        $fileHandler = new RotatingFileHandler(
            $logDir . 'crawlflow.log',
            30, // Keep 30 days
            Logger::INFO
        );
        $fileHandler->setFormatter($formatter);
        $logger->pushHandler($fileHandler);

        // Add debug handler if debug mode enabled
        if (get_option('crawlflow_debug_mode', false)) {
            $debugHandler = new StreamHandler(
                $logDir . 'debug.log',
                Logger::DEBUG
            );
            $debugHandler->setFormatter($formatter);
            $logger->pushHandler($debugHandler);
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

