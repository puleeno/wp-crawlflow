<?php

namespace CrawlFlow\Cron\Phase1;

/**
 * Data Source Handler Factory
 * 
 * Creates and manages data source handlers for different source types
 */
class DataSourceHandlerFactory
{
    /**
     * @var array Cache of handler instances
     */
    private static array $handlers = [];

    /**
     * Get handler for a specific data source type
     * 
     * @param string $sourceType Data source type (e.g., 'url', 'rss', 'api')
     * @return AbstractDataSourceHandler|null Handler instance or null if not found
     */
    public static function getHandler(string $sourceType): ?AbstractDataSourceHandler
    {
        // Check cache first
        if (isset(self::$handlers[$sourceType])) {
            return self::$handlers[$sourceType];
        }

        // Map source types to handler classes
        $handlerMap = [
            'url' => UrlDataSourceHandler::class,
            'sitemap' => SitemapDataSourceHandler::class,
            // Add more handlers here as needed:
            // 'rss' => RssDataSourceHandler::class,
            // 'api' => ApiDataSourceHandler::class,
        ];

        if (!isset($handlerMap[$sourceType])) {
            \Rake\Facade\Logger::warning("CrawlFlow Phase 1: No handler found for source type: {$sourceType}");
            return null;
        }

        $handlerClass = $handlerMap[$sourceType];
        
        if (!class_exists($handlerClass)) {
            \Rake\Facade\Logger::error("CrawlFlow Phase 1: Handler class not found: {$handlerClass}");
            return null;
        }

        // Create and cache handler instance
        $handler = new $handlerClass();
        if (!$handler instanceof AbstractDataSourceHandler) {
            \Rake\Facade\Logger::error("CrawlFlow Phase 1: Handler class does not extend AbstractDataSourceHandler: {$handlerClass}");
            return null;
        }

        self::$handlers[$sourceType] = $handler;
        return $handler;
    }

    /**
     * Register a custom handler for a source type
     * 
     * @param string $sourceType Source type
     * @param string $handlerClass Handler class name
     * @return bool Success
     */
    public static function registerHandler(string $sourceType, string $handlerClass): bool
    {
        if (!class_exists($handlerClass)) {
            \Rake\Facade\Logger::error("CrawlFlow Phase 1: Cannot register handler - class not found: {$handlerClass}");
            return false;
        }

        if (!is_subclass_of($handlerClass, AbstractDataSourceHandler::class)) {
            \Rake\Facade\Logger::error("CrawlFlow Phase 1: Cannot register handler - class does not extend AbstractDataSourceHandler: {$handlerClass}");
            return false;
        }

        // Clear cache for this type
        unset(self::$handlers[$sourceType]);
        
        return true;
    }

    /**
     * Get all registered source types
     * 
     * @return array List of supported source types
     */
    public static function getSupportedTypes(): array
    {
        return ['url', 'sitemap']; // Add more as handlers are created
    }
}

