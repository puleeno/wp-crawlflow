<?php

namespace CrawlFlow\Kernel;

use Rake\Kernel\MigrationKernel as BaseMigrationKernel;

/**
 * CrawlFlow Migration Kernel
 *
 * Handles database migration tasks for CrawlFlow plugin in WordPress
 */
class CrawlFlowMigrationKernel extends BaseMigrationKernel
{
    /**
     * Register custom bootstrappers for CrawlFlow migration kernel
     *
     * @return void
     */
    protected function registerBootstrappers(): void
    {
        parent::registerBootstrappers();
        $this->addCustomBootstrapper(\CrawlFlow\Bootstrapper\CrawlFlowMigrationBootstrapper::class);
    }

    /**
     * Initialize CrawlFlow migration kernel
     *
     * @return self
     */
    public function initializeMigration(): self
    {
        $this->setConfig([
            'kernel' => 'migration',
            'migration' => [
                'enabled' => true,
                'auto_run' => get_option('crawlflow_auto_migration', true),
                'backup_before' => get_option('crawlflow_backup_before_migration', true),
                'rollback_on_error' => get_option('crawlflow_rollback_on_error', true),
                'batch_size' => get_option('crawlflow_migration_batch_size', 10),
            ],
            'database' => [
                'driver' => 'wordpress',
                'prefix' => $this->getWordPressPrefix(),
                'charset' => DB_CHARSET,
                'collate' => DB_COLLATE,
            ],
            'logging' => [
                'level' => 'info',
                'file' => 'crawlflow-migration.log',
                'directory' => WP_CONTENT_DIR . '/crawlflow/logs/',
            ],
        ]);
        return $this->boot();
    }
}