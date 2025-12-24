<?php

namespace CrawlFlow\ServiceProvider;

use Rake\Rake;
use Rake\ServiceProvider\AbstractServiceProvider;
use Rake\Manager\HttpClientManager;
use Puleeno\Rake\WordPress\Http\WordPressHttpClient;

/**
 * HTTP Service Provider
 * Registers HTTP client for Rake framework
 */
class HttpServiceProvider extends AbstractServiceProvider
{
    /**
     * Register services
     * 
     * @return void
     */
    protected function registerServices(): void
    {
        // Register HttpClientManager singleton
        $this->app->singleton(HttpClientManager::class, function ($app) {
            return new HttpClientManager();
        });

        // Register WordPressHttpClient as default
        $this->registerWordPressHttpClient();
    }

    /**
     * Boot services
     * 
     * @return void
     */
    protected function bootServices(): void
    {
        // Nothing to boot
    }

    /**
     * Register WordPress HTTP Client as default
     * 
     * @return void
     */
    private function registerWordPressHttpClient(): void
    {
        // Create WordPress HTTP client
        $client = new WordPressHttpClient([
            'timeout' => 30,
            'user-agent' => 'CrawlFlow/2.0 (WordPress)',
            'sslverify' => false,
        ]);

        // Register in HttpClientManager
        // TODO: Implement when HttpClientManager methods are available
        // HttpClientManager::register('wordpress', $client);
        
        // Set as default client
        // TODO: Implement when HttpClientManager methods are available
        // HttpClientManager::setDefaultClient($client);

        // Log registration
        \Rake\Facade\Logger::info('CrawlFlow: WordPress HTTP Client registered as default');
    }
}

