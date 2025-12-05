<?php

namespace CrawlFlow\ServiceProvider;

use Rake\ServiceProvider\AbstractServiceProvider;
use Rake\Contracts\File\FileDownloaderClientInterface;
use Rake\Manager\FileIntegrityManager;
use RamphorRake\Adapter\File\WordPressFileDownloaderClient;

/**
 * File Service Provider
 * 
 * Đăng ký file-related services: FileDownloaderClient, FileIntegrityManager
 */
class FileServiceProvider extends AbstractServiceProvider
{
    /**
     * Register file services
     */
    protected function registerServices(): void
    {
        $this->registerFileDownloaderClient();
        $this->registerFileIntegrityManager();
    }

    /**
     * Register WordPress File Downloader Client
     */
    private function registerFileDownloaderClient(): void
    {
        $this->app->singleton(FileDownloaderClientInterface::class, function ($app) {
            // Get default options từ WordPress settings
            $defaultOptions = apply_filters('crawlflow_file_downloader_options', [
                'timeout' => 30,
                'user-agent' => 'CrawlFlow/1.0 File Downloader',
                'sslverify' => true
            ]);

            $client = new WordPressFileDownloaderClient($defaultOptions);

            // Allow plugins to customize client
            return apply_filters('crawlflow_file_downloader_client', $client);
        });
    }

    /**
     * Register File Integrity Manager
     */
    private function registerFileIntegrityManager(): void
    {
        $this->app->singleton(FileIntegrityManager::class, function ($app) {
            // Get database adapter
            $databaseAdapter = $app->make(\Rake\Contracts\Database\DatabaseAdapterInterface::class);

            // Get file downloader client
            $downloaderClient = $app->make(FileDownloaderClientInterface::class);

            // Get upload directory (WordPress uploads dir)
            $uploadDir = wp_upload_dir()['basedir'] . '/crawlflow/';

            // Create manager with injected dependencies
            $manager = new FileIntegrityManager(
                $databaseAdapter,
                $uploadDir,
                $downloaderClient
            );

            return apply_filters('crawlflow_file_integrity_manager', $manager);
        });
    }

    /**
     * Boot services (if needed)
     */
    protected function bootServices(): void
    {
        // Register WordPress hooks if needed
        add_filter('crawlflow_file_downloader_options', [$this, 'filterDownloaderOptions'], 10, 1);
    }

    /**
     * Filter downloader options based on WordPress settings
     *
     * @param array $options
     * @return array
     */
    public function filterDownloaderOptions(array $options): array
    {
        // Có thể customize based on WordPress options
        // Ví dụ: get timeout từ plugin settings

        return $options;
    }
}
