<?php

namespace CrawlFlow\Tests\Unit\Cron;

use CrawlFlow\Cron\CronScheduler;
use PHPUnit\Framework\TestCase;
use Rake\Rake;

/**
 * Test cron event locking to avoid overlapping runs.
 *
 * @group cron
 * @group unit
 */
class CronSchedulerLockTest extends TestCase
{
    private CronScheduler $scheduler;
    private \wpdb $wpdb;
    private string $table;

    protected function setUp(): void
    {
        parent::setUp();
        $rake = Rake::getInstance();
        $this->scheduler = $rake->make(CronScheduler::class);
        $this->wpdb = $GLOBALS['wpdb'];
        $this->table = $this->wpdb->prefix . 'rake_event_status';
        $this->ensureTable();
        $this->wpdb->query("TRUNCATE TABLE {$this->table}");
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_name VARCHAR(191) NOT NULL,
            process_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY event_name_status (event_name, status),
            KEY process_id (process_id)
        ) {$this->wpdb->get_charset_collate()};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function test_acquire_lock_blocks_second_run_and_sets_cancel(): void
    {
        $eventName = 'test_event_lock';
        $acquire = $this->getPrivateMethod('acquireEventLock');
        $lockId = $acquire->invoke($this->scheduler, $eventName);

        $this->assertNotNull($lockId);

        // Second acquire should detect running PID and insert cancel, returning null
        $secondLock = $acquire->invoke($this->scheduler, $eventName);
        $this->assertNull($secondLock);

        $running = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE event_name = %s AND status = 'running'",
            $eventName
        ));
        $cancel = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE event_name = %s AND status = 'cancel'",
            $eventName
        ));

        $this->assertEquals(1, (int)$running, 'Should have one running record');
        $this->assertEquals(1, (int)$cancel, 'Second attempt should be cancelled');
    }

    public function test_complete_updates_status(): void
    {
        $eventName = 'test_event_complete';
        $acquire = $this->getPrivateMethod('acquireEventLock');
        $complete = $this->getPrivateMethod('completeEventLock');

        $lockId = $acquire->invoke($this->scheduler, $eventName);
        $this->assertNotNull($lockId);

        $complete->invoke($this->scheduler, $lockId, 'complete');

        $status = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT status FROM {$this->table} WHERE id = %d",
            $lockId
        ));

        $this->assertEquals('complete', $status);
    }

    private function getPrivateMethod(string $name): \ReflectionMethod
    {
        $ref = new \ReflectionClass(CronScheduler::class);
        $method = $ref->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }
}

