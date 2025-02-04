<?php

namespace CrawlFlow;

use Ramphor\Rake\Initialize;
use Puleeno\Rake\WordPress\Driver;

class Installer
{
    public static function active()
    {
        $initializer = new Initialize(new Driver());
        $initializer->setUpDb();

        if (! wp_next_scheduled(TaskRunner::TASK_CRON_NAME)) {
            wp_schedule_event(time(), '5mins', TaskRunner::TASK_CRON_NAME);
        }
    }

    public static function deactive()
    {
        $timestamp = wp_next_scheduled(TaskRunner::TASK_CRON_NAME);
        wp_unschedule_event($timestamp, TaskRunner::TASK_CRON_NAME);
    }
}
