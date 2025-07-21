<?php

namespace CrawlFlow\Admin;

/**
 * Migration Controller for Admin Interface
 */
class MigrationController
{
    /**
     * @var MigrationService
     */
    private $migrationService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->migrationService = new MigrationService();
        $this->initHooks();
    }

    /**
     * Initialize hooks
     */
    private function initHooks()
    {
        add_action('admin_menu', [$this, 'addMigrationMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_crawlflow_run_migration', [$this, 'ajaxRunMigration']);
        add_action('wp_ajax_crawlflow_get_migration_status', [$this, 'ajaxGetMigrationStatus']);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAssets($hook)
    {
        if (strpos($hook, 'crawlflow-migration') !== false) {
            wp_enqueue_style(
                'crawlflow-migration',
                CRAWLFLOW_PLUGIN_URL . 'assets/css/migration.css',
                [],
                CRAWLFLOW_VERSION
            );
        }
    }

    /**
     * Add migration menu to admin
     */
    public function addMigrationMenu()
    {
        add_submenu_page(
            'crawlflow',
            __('Database Migration', 'crawlflow'),
            __('Migration', 'crawlflow'),
            'manage_options',
            'crawlflow-migration',
            [$this, 'renderMigrationPage']
        );
    }

        /**
     * Render migration page
     */
    public function renderMigrationPage()
    {
        global $wpdb;
        $migrationStatus = $this->migrationService->checkMigrationStatus();
        $migrationHistory = $this->migrationService->getMigrationHistory();

        ?>
        <div class="wrap">
            <h1><?php _e('CrawlFlow Database Migration', 'crawlflow'); ?></h1>

            <div class="crawlflow-database-info">
                <h2><?php _e('Database Information', 'crawlflow'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <tbody>
                        <tr>
                            <td><strong><?php _e('WordPress Prefix', 'crawlflow'); ?></strong></td>
                            <td><code><?php echo esc_html($wpdb->prefix); ?></code></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Rake Tables Prefix', 'crawlflow'); ?></strong></td>
                            <td><code><?php echo esc_html($wpdb->prefix); ?></code></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Database Name', 'crawlflow'); ?></strong></td>
                            <td><code><?php echo esc_html(DB_NAME); ?></code></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="crawlflow-migration-status">
                <h2><?php _e('Migration Status', 'crawlflow'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Table', 'crawlflow'); ?></th>
                            <th><?php _e('Database Table', 'crawlflow'); ?></th>
                            <th><?php _e('Current Version', 'crawlflow'); ?></th>
                            <th><?php _e('Required Version', 'crawlflow'); ?></th>
                            <th><?php _e('Status', 'crawlflow'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($migrationStatus as $table => $status) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($table); ?></strong></td>
                            <td><code><?php echo esc_html($wpdb->prefix . $table); ?></code></td>
                            <td><?php echo esc_html($status['current_version']); ?></td>
                            <td><?php echo esc_html($status['required_version']); ?></td>
                            <td>
                                <?php if ($status['needs_migration']) : ?>
                                    <span class="crawlflow-status-needs-migration"><?php _e('Needs Migration', 'crawlflow'); ?></span>
                                <?php else : ?>
                                    <span class="crawlflow-status-up-to-date"><?php _e('Up to Date', 'crawlflow'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="crawlflow-migration-actions">
                <h2><?php _e('Migration Actions', 'crawlflow'); ?></h2>
                <p>
                    <button type="button" class="button button-primary" id="run-migration">
                        <?php _e('Run Migration', 'crawlflow'); ?>
                    </button>
                    <button type="button" class="button" id="check-status">
                        <?php _e('Check Status', 'crawlflow'); ?>
                    </button>
                </p>
                <div id="migration-result"></div>
            </div>

            <?php if (!empty($migrationHistory)) : ?>
            <div class="crawlflow-migration-history">
                <h2><?php _e('Migration History', 'crawlflow'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Table', 'crawlflow'); ?></th>
                            <th><?php _e('From Version', 'crawlflow'); ?></th>
                            <th><?php _e('To Version', 'crawlflow'); ?></th>
                            <th><?php _e('Changes', 'crawlflow'); ?></th>
                            <th><?php _e('Date', 'crawlflow'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($migrationHistory as $history) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($history['table_name']); ?></strong></td>
                            <td><?php echo esc_html($history['from_version']); ?></td>
                            <td><?php echo esc_html($history['to_version']); ?></td>
                            <td><?php echo esc_html($history['changes_summary']); ?></td>
                            <td><?php echo esc_html($history['created_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#run-migration').on('click', function() {
                var button = $(this);
                button.prop('disabled', true).text('<?php _e('Running...', 'crawlflow'); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'crawlflow_run_migration',
                        nonce: '<?php echo wp_create_nonce('crawlflow_migration_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#migration-result').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                        } else {
                            $('#migration-result').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        $('#migration-result').html('<div class="notice notice-error"><p><?php _e('Migration failed', 'crawlflow'); ?></p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('Run Migration', 'crawlflow'); ?>');
                    }
                });
            });

            $('#check-status').on('click', function() {
                location.reload();
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX: Run migration
     */
    public function ajaxRunMigration()
    {
        check_ajax_referer('crawlflow_migration_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'crawlflow'));
        }

        try {
            $result = $this->migrationService->runMigrations();

            if ($result) {
                wp_send_json_success([
                    'message' => __('Migration completed successfully', 'crawlflow')
                ]);
            } else {
                wp_send_json_error(__('Migration failed', 'crawlflow'));
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Get migration status
     */
    public function ajaxGetMigrationStatus()
    {
        check_ajax_referer('crawlflow_migration_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'crawlflow'));
        }

        try {
            $status = $this->migrationService->checkMigrationStatus();
            wp_send_json_success($status);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}