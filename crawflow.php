<?php
/**
 * Plugin Name: CrawlFlow
 * Plugin URI: https://github.com/puleeno/wp-crawflow/
 * Author: Puleeno Nguyen
 * Author URI: https://puleeno.com
 * Version: 1.0.0
 * Description: The GUI create a flow for crawler to migrate or crawl data from other sources: web, rss, xml, csv or more
 */

define( 'RAKE_WORDPRESS_MIGRATION_EXAMPLE_PLUGIN_FILE', __FILE__ );

$composerAutoloader = sprintf( '%s/vendor/autoload.php', dirname( RAKE_WORDPRESS_MIGRATION_EXAMPLE_PLUGIN_FILE ) );
if (file_exists($composerAutoloader)) {
    require_once $composerAutoloader;
}

if (!class_exists(\CrawlFlow\Migrator::class)) {
    return error_log(__('The migrator is not found', 'wp-crawflow'));
}

$GLOBALS['migrator'] = \CrawlFlow\Migrator::get_instance();
