<?php
namespace Migrator;

use App\TaskRunner;
use Ramphor\Rake\Initialize;
use Puleeno\Rake\WordPress\Driver;

class Uninstaller
{
    public function remove_cron_events()
    {
        wp_clear_scheduled_hook(TaskRunner::TASK_CRON_NAME);
    }

    public function remove_rake_db_tables()
    {
        $initializer = new Initialize(new Driver());
        $initializer->removeDbTables();
    }

    public function uninstall()
    {
        $this->remove_cron_events();
        $this->remove_rake_db_tables();
    }
}

$uninstaller = new Uninstaller();
$uninstaller->uninstall();
