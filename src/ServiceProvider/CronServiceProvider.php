<?php

namespace CrawlFlow\ServiceProvider;

use Rake\Rake;
use Rake\ServiceProvider\AbstractServiceProvider;

/**
 * Cron Service Provider
 * Registers cron-related services and schedules
 */
class CronServiceProvider extends AbstractServiceProvider
{
    /**
     * Register cron services
     */
    protected function registerServices(): void
    {
        // Register CronScheduler
        $this->app->singleton('CrawlFlow\Cron\CronScheduler', function ($app) {
            return new \CrawlFlow\Cron\CronScheduler();
        });
    }

    /**
     * Boot cron services
     */
    protected function bootServices(): void
    {
        // Register custom cron schedules
        add_filter('cron_schedules', [$this, 'addCustomSchedules']);
        
        // Register cron hooks
        $cronScheduler = $this->app->make('CrawlFlow\Cron\CronScheduler');
        $cronScheduler->register();
        
        // Schedule all active projects on boot (if not already scheduled)
        add_action('init', [$this, 'scheduleActiveProjects'], 20);
    }

    /**
     * Add custom cron schedules
     */
    public function addCustomSchedules(array $schedules): array
    {
        $schedules['every_5_minutes'] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display' => __('Every 5 Minutes', 'crawlflow'),
        ];
        
        $schedules['every_15_minutes'] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => __('Every 15 Minutes', 'crawlflow'),
        ];
        
        $schedules['every_30_minutes'] = [
            'interval' => 30 * MINUTE_IN_SECONDS,
            'display' => __('Every 30 Minutes', 'crawlflow'),
        ];
        
        $schedules['every_6_hours'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => __('Every 6 Hours', 'crawlflow'),
        ];
        
        return $schedules;
    }

    /**
     * Schedule all active projects on init
     */
    public function scheduleActiveProjects(): void
    {
        // Only run once per request
        static $scheduled = false;
        if ($scheduled) {
            return;
        }
        $scheduled = true;

        try {
            $projectService = $this->app->make('CrawlFlow\Admin\ProjectService');
            $cronScheduler = $this->app->make('CrawlFlow\Cron\CronScheduler');
            
            // Get all projects (limit to reasonable number)
            $projects = $projectService->getProjects(1, 100);
            
            foreach ($projects as $project) {
                // Only schedule active projects
                if ($project['status'] !== 'active') {
                    continue;
                }

                // Skip if already scheduled
                if ($cronScheduler->isProjectScheduled($project['id'])) {
                    continue;
                }

                // Schedule project
                try {
                    $cronScheduler->scheduleProject($project['id']);
                } catch (\Exception $e) {
                    error_log("CrawlFlow: Failed to schedule project {$project['id']} - " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            error_log("CrawlFlow: Failed to schedule active projects - " . $e->getMessage());
        }
    }
}

