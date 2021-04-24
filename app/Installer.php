<?php
namespace App;

class Installer
{
    public static function active()
    {
        if (! wp_next_scheduled(TaskRunner::TASK_CRON_NAME)) {
            wp_schedule_event(time(), 'five_seconds', TaskRunner::TASK_CRON_NAME);
        }
    }

    public function deactive()
    {
        $timestamp = wp_next_scheduled( TaskRunner::TASK_CRON_NAME );
        wp_unschedule_event( $timestamp, TaskRunner::TASK_CRON_NAME );
    }
}
