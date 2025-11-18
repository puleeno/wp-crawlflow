<?php

namespace CrawlFlow\Flow;

use Rake\Rake;

/**
 * Rake Adapter
 * Bridges Flow-Based Architecture with Rake Framework components
 */
class RakeAdapter
{
    /**
     * @var Rake
     */
    private Rake $rake;

    /**
     * Constructor
     */
    public function __construct(Rake $rake)
    {
        $this->rake = $rake;
    }

    /**
     * Get Rake instance
     */
    public function getRake(): Rake
    {
        return $this->rake;
    }

    /**
     * Get HttpClientManager from Rake
     */
    public function getHttpClientManager()
    {
        return $this->rake->make(\Rake\Manager\HttpClientManager::class);
    }

    /**
     * Get DatabaseDriverManager from Rake
     */
    public function getDatabaseDriverManager()
    {
        return $this->rake->make(\Rake\Manager\Database\DatabaseDriverManager::class);
    }

    /**
     * Get ProcessorManager from Rake
     */
    public function getProcessorManager()
    {
        return $this->rake->make(\Rake\Manager\ProcessorManager::class);
    }

    /**
     * Get ReceptionManager from Rake
     */
    public function getReceptionManager()
    {
        return $this->rake->make(\Rake\Manager\ReceptionManager::class);
    }

    /**
     * Get ParserManager from Rake
     */
    public function getParserManager()
    {
        return $this->rake->make(\Rake\Manager\ParserManager::class);
    }

    /**
     * Get FeedItemBuilderManager from Rake
     */
    public function getFeedItemBuilderManager()
    {
        return $this->rake->make(\Rake\Manager\FeedItemBuilderManager::class);
    }

    /**
     * Get EventBus from Rake
     */
    public function getEventBus()
    {
        return $this->rake->make(\Rake\Manager\EventBus::class);
    }

    /**
     * Get Logger from Rake
     */
    public function getLogger()
    {
        return $this->rake->make(\Rake\Facade\Logger::class);
    }
}

