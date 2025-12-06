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
        
        // Register WP CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            $this->registerCliCommands();
        }
    }

    /**
     * Register WP CLI commands
     */
    private function registerCliCommands(): void
    {
        if (!class_exists('WP_CLI')) {
            return;
        }

        $command = new \CrawlFlow\CLI\CrawlFlowCronCommand();

        // Register main command (list)
        \WP_CLI::add_command('crawlflow cron list', [$command, 'list'], [
            'shortdesc' => 'List all CrawlFlow projects with their cron schedule status',
            'synopsis' => [
                [
                    'type' => 'flag',
                    'name' => 'scheduled',
                    'optional' => true,
                    'description' => 'Show only scheduled projects',
                ],
                [
                    'type' => 'flag',
                    'name' => 'active',
                    'optional' => true,
                    'description' => 'Show only active projects',
                ],
            ],
        ]);

        // Register schedule command
        \WP_CLI::add_command('crawlflow cron schedule', [$command, 'schedule'], [
            'shortdesc' => 'Schedule a project',
            'synopsis' => [
                [
                    'type' => 'positional',
                    'name' => 'project_id',
                    'optional' => false,
                    'description' => 'Project ID to schedule',
                ],
            ],
        ]);

        // Register unschedule command
        \WP_CLI::add_command('crawlflow cron unschedule', [$command, 'unschedule'], [
            'shortdesc' => 'Unschedule a project',
            'synopsis' => [
                [
                    'type' => 'positional',
                    'name' => 'project_id',
                    'optional' => false,
                    'description' => 'Project ID to unschedule',
                ],
            ],
        ]);

        // Register run command
        \WP_CLI::add_command('crawlflow cron run', [$command, 'run'], [
            'shortdesc' => 'Run a project manually',
            'synopsis' => [
                [
                    'type' => 'positional',
                    'name' => 'project_id',
                    'optional' => false,
                    'description' => 'Project ID to run',
                ],
            ],
        ]);
    }

    /**
     * Add custom cron schedules
     */
    public function addCustomSchedules(array $schedules): array
    {
        // Use plain text instead of __() to avoid translation loading too early
        $schedules['every_5_minutes'] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display' => 'Every 5 Minutes',
        ];
        
        $schedules['every_15_minutes'] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => 'Every 15 Minutes',
        ];
        
        $schedules['every_30_minutes'] = [
            'interval' => 30 * MINUTE_IN_SECONDS,
            'display' => 'Every 30 Minutes',
        ];
        
        $schedules['every_6_hours'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => 'Every 6 Hours',
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

