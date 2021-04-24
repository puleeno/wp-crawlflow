<?php
namespace Migrator;

use App\TaskRunner;

class Uninstaller
{
    public function remove_cron_events()
    {
        wp_clear_scheduled_hook(TaskRunner::TASK_CRON_NAME);
    }

    public function uninstall()
    {
        $this->remove_cron_events();
    }
}

$uninstaller = new Uninstaller();
$uninstaller->uninstall();
