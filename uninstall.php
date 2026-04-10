<?php
/**
 * Uninstall handler for Mpire Image Optimizer.
 *
 * Cleans up all plugin data when the plugin is deleted.
 *
 * @package Mpire_Image_Optimizer
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove plugin options.
delete_option( 'mpio_settings' );

// Remove all post meta.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_mpio_%'" );

// Remove custom files table.
$table_name = $wpdb->prefix . 'mpio_custom_files';
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

// Remove transients.
delete_transient( 'mpio_bulk_queue' );
delete_transient( 'mpio_bulk_running' );
delete_transient( 'mpio_bulk_progress' );
delete_transient( 'mpio_activation_redirect' );

// Remove backup directory.
$upload_dir = wp_upload_dir();
$backup_dir = $upload_dir['basedir'] . '/mpio-backups';

if ( is_dir( $backup_dir ) ) {
    // Recursively remove backup directory.
    $iterator = new RecursiveDirectoryIterator( $backup_dir, RecursiveDirectoryIterator::SKIP_DOTS );
    $files    = new RecursiveIteratorIterator( $iterator, RecursiveIteratorIterator::CHILD_FIRST );

    foreach ( $files as $file ) {
        if ( $file->isDir() ) {
            rmdir( $file->getRealPath() );
        } else {
            unlink( $file->getRealPath() );
        }
    }

    rmdir( $backup_dir );
}

// Clear scheduled cron events.
wp_clear_scheduled_hook( 'mpio_bulk_optimization_cron' );
wp_clear_scheduled_hook( 'mpio_cleanup_backups_cron' );

// Remove .htaccess rewrite rules.
$htaccess = ABSPATH . '.htaccess';
if ( file_exists( $htaccess ) && is_writable( $htaccess ) ) {
    $content = file_get_contents( $htaccess );
    $marker  = 'Mpire Image Optimizer';
    $pattern = '/# BEGIN ' . preg_quote( $marker, '/' ) . '.*?# END ' . preg_quote( $marker, '/' ) . '\s*/s';
    $content = preg_replace( $pattern, '', $content );
    file_put_contents( $htaccess, $content );
}
