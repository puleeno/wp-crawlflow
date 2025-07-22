<?php

namespace CrawlFlow\Admin;

/**
 * Admin Controller - Entry point for CrawlFlow admin
 * Now delegates all functionality to CrawlFlowController
 */
class AdminController
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Initialize main CrawlFlow controller
        if (class_exists('CrawlFlow\Admin\CrawlFlowController')) {
            new \CrawlFlow\Admin\CrawlFlowController();
        }
    }
}
