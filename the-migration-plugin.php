<?php
/**
 * Plugin Name: The Migration Plugin Name
 * Plugin URI: https://github.com/puleeno/rake-wordpress-migration-example/
 * Author: Puleeno Nguyen
 * Author URI: https://puleeno.com
 * Version: 1.0.0
 * Description: Use Rake migration framework to migrate other website to WordPress
 */

define( 'RAKE_WORDPRESS_MIGRATION_EXAMPLE_PLUGIN_FILE', __FILE__ );

$composerAutoloader = sprintf( '%s/vendor/autoload.php', dirname( RAKE_WORDPRESS_MIGRATION_EXAMPLE_PLUGIN_FILE ) );
if (file_exists($composerAutoloader)) {
    require_once $composerAutoloader;
}

if (!class_exists(\App\Migrator::class)) {
    return error_log(__('The migrator is not found', 'rake-wordpress-migration-example'));
}

$GLOBALS['migrator'] = \App\Migrator::get_instance();
