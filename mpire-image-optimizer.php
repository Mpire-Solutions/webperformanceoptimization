<?php
/**
 * Plugin Name: Mpire Image Optimizer
 * Plugin URI: https://mpire.io/image-optimizer
 * Description: Optimize, convert, and resize images in your WordPress media library. Supports WebP, AVIF, PNG, and JPG with server-side processing (GD/Imagick) and a client-side batch converter. Auto-optimize on upload, bulk optimization, before/after previews, watermarking, and more.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Mpire
 * Author URI: https://mpire.io
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mpire-image-optimizer
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'MPIO_VERSION', '1.0.0' );
define( 'MPIO_SLUG', 'mpire-image-optimizer' );
define( 'MPIO_FILE', __FILE__ );
define( 'MPIO_PATH', plugin_dir_path( __FILE__ ) );
define( 'MPIO_URL', plugin_dir_url( __FILE__ ) );
define( 'MPIO_BASENAME', plugin_basename( __FILE__ ) );
define( 'MPIO_ADMIN_URL', MPIO_URL . 'admin/' );
define( 'MPIO_INCLUDES_PATH', MPIO_PATH . 'includes/' );

// Default settings.
define( 'MPIO_DEFAULT_QUALITY', 80 );
define( 'MPIO_DEFAULT_FORMAT', 'image/webp' );
define( 'MPIO_MAX_BACKUP_DAYS', 30 );

/**
 * Load plugin textdomain.
 */
function mpio_load_textdomain() {
    load_plugin_textdomain( 'mpire-image-optimizer', false, dirname( MPIO_BASENAME ) . '/languages/' );
}
add_action( 'init', 'mpio_load_textdomain' );

/**
 * Check minimum requirements.
 */
function mpio_check_requirements() {
    $errors = [];

    if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
        $errors[] = sprintf(
            /* translators: %s: Required PHP version */
            __( 'Mpire Image Optimizer requires PHP %s or higher.', 'mpire-image-optimizer' ),
            '7.4'
        );
    }

    if ( ! extension_loaded( 'gd' ) && ! extension_loaded( 'imagick' ) ) {
        $errors[] = __( 'Mpire Image Optimizer requires either the GD or Imagick PHP extension.', 'mpire-image-optimizer' );
    }

    return $errors;
}

/**
 * Show admin notice if requirements not met.
 */
function mpio_requirements_notice() {
    $errors = mpio_check_requirements();
    if ( empty( $errors ) ) {
        return;
    }

    echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Mpire Image Optimizer', 'mpire-image-optimizer' ) . '</strong></p>';
    foreach ( $errors as $error ) {
        echo '<p>' . esc_html( $error ) . '</p>';
    }
    echo '</div>';
}

/**
 * Initialize the plugin.
 */
function mpio_init() {
    $errors = mpio_check_requirements();
    if ( ! empty( $errors ) ) {
        add_action( 'admin_notices', 'mpio_requirements_notice' );
        return;
    }

    // Load core classes.
    require_once MPIO_INCLUDES_PATH . 'class-mpio-logger.php';
    require_once MPIO_INCLUDES_PATH . 'class-mpio-settings.php';
    require_once MPIO_INCLUDES_PATH . 'class-mpio-optimizer.php';
    require_once MPIO_INCLUDES_PATH . 'class-mpio-backup.php';
    require_once MPIO_INCLUDES_PATH . 'class-mpio-media-library.php';
    require_once MPIO_INCLUDES_PATH . 'class-mpio-auto-optimize.php';
    require_once MPIO_INCLUDES_PATH . 'class-mpio-bulk-optimizer.php';
    require_once MPIO_INCLUDES_PATH . 'class-mpio-custom-folders.php';
    require_once MPIO_INCLUDES_PATH . 'class-mpio-ajax.php';
    require_once MPIO_INCLUDES_PATH . 'class-mpio-rest-api.php';
    require_once MPIO_INCLUDES_PATH . 'class-mpio-admin-notices.php';
    require_once MPIO_INCLUDES_PATH . 'class-mpio-cdn-compat.php';
    require_once MPIO_INCLUDES_PATH . 'class-mpio-dashboard-widget.php';
    require_once MPIO_INCLUDES_PATH . 'class-mpio-content-rewriter.php';
    require_once MPIO_INCLUDES_PATH . 'class-mpio-rewrite-rules.php';

    // Load admin.
    if ( is_admin() ) {
        require_once MPIO_PATH . 'admin/class-mpio-admin.php';
        MPIO_Admin::get_instance();
    }

    // Register WP-CLI commands.
    if ( defined( 'WP_CLI' ) && WP_CLI ) {
        require_once MPIO_INCLUDES_PATH . 'class-mpio-cli.php';
        WP_CLI::add_command( 'mpio', 'MPIO_CLI' );
    }

    // Initialize components.
    MPIO_Settings::get_instance();
    MPIO_Optimizer::get_instance();
    MPIO_Backup::get_instance();
    MPIO_Media_Library::get_instance();
    MPIO_Auto_Optimize::get_instance();
    MPIO_Bulk_Optimizer::get_instance();
    MPIO_Custom_Folders::get_instance();
    MPIO_Ajax::get_instance();
    MPIO_REST_API::get_instance();
    MPIO_Admin_Notices::get_instance();
    MPIO_CDN_Compat::get_instance();
    MPIO_Dashboard_Widget::get_instance();
    MPIO_Content_Rewriter::get_instance();
    MPIO_Rewrite_Rules::get_instance();

    /**
     * Fires after all Mpire Image Optimizer components are loaded.
     */
    do_action( 'mpio_loaded' );
}
add_action( 'plugins_loaded', 'mpio_init' );

/**
 * Plugin activation.
 *
 * Handles both single-site and multisite (network) activation.
 *
 * @param bool $network_wide Whether the plugin is being activated network-wide.
 */
function mpio_activate( $network_wide = false ) {
    if ( is_multisite() && $network_wide ) {
        // Network activation: run for each site.
        $sites = get_sites( [ 'number' => 0, 'fields' => 'ids' ] );
        foreach ( $sites as $site_id ) {
            switch_to_blog( $site_id );
            mpio_activate_single_site();
            restore_current_blog();
        }
    } else {
        mpio_activate_single_site();
    }
}

/**
 * Activation logic for a single site.
 */
function mpio_activate_single_site() {
    // Create backup directory.
    $upload_dir = wp_upload_dir();
    $backup_dir = $upload_dir['basedir'] . '/mpio-backups';
    if ( ! file_exists( $backup_dir ) ) {
        wp_mkdir_p( $backup_dir );
        file_put_contents( $backup_dir . '/.htaccess', 'Deny from all' );
        file_put_contents( $backup_dir . '/index.php', '<?php // Silence is golden.' );
    }

    // Create custom folders table.
    mpio_create_custom_folders_table();

    // Set default options.
    if ( false === get_option( 'mpio_settings' ) ) {
        update_option( 'mpio_settings', mpio_get_default_settings() );
    }

    // Set activation flag.
    set_transient( 'mpio_activation_redirect', true, 30 );

    flush_rewrite_rules();
}

/**
 * When a new site is created on multisite, run activation for it.
 */
function mpio_on_new_blog( $blog_id ) {
    if ( is_plugin_active_for_network( MPIO_BASENAME ) ) {
        switch_to_blog( $blog_id );
        mpio_activate_single_site();
        restore_current_blog();
    }
}
add_action( 'wp_initialize_site', function( $new_site ) {
    mpio_on_new_blog( $new_site->blog_id );
}, 10, 1 );

register_activation_hook( MPIO_FILE, 'mpio_activate' );

/**
 * Plugin deactivation.
 */
function mpio_deactivate() {
    // Clear scheduled events.
    wp_clear_scheduled_hook( 'mpio_bulk_optimization_cron' );
    wp_clear_scheduled_hook( 'mpio_cleanup_backups_cron' );
    wp_clear_scheduled_hook( 'mpio_custom_folder_scan_cron' );

    // Remove .htaccess rewrite rules so images don't 404.
    $htaccess = ABSPATH . '.htaccess';
    if ( file_exists( $htaccess ) && is_writable( $htaccess ) ) {
        $content = file_get_contents( $htaccess );
        $marker  = 'Mpire Image Optimizer';
        $pattern = '/# BEGIN ' . preg_quote( $marker, '/' ) . '.*?# END ' . preg_quote( $marker, '/' ) . '\s*/s';
        $cleaned = preg_replace( $pattern, '', $content );
        if ( $cleaned !== $content ) {
            file_put_contents( $htaccess, $cleaned );
        }
    }

    // Stop any running bulk optimization.
    delete_transient( 'mpio_bulk_queue' );
    delete_transient( 'mpio_bulk_running' );
    delete_transient( 'mpio_bulk_progress' );

    flush_rewrite_rules();
}
register_deactivation_hook( MPIO_FILE, 'mpio_deactivate' );

/**
 * Create custom folders tracking table.
 */
function mpio_create_custom_folders_table() {
    global $wpdb;

    $table_name      = $wpdb->prefix . 'mpio_custom_files';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        file_path varchar(500) NOT NULL DEFAULT '',
        folder_path varchar(500) NOT NULL DEFAULT '',
        file_hash varchar(32) NOT NULL DEFAULT '',
        original_size bigint(20) unsigned NOT NULL DEFAULT 0,
        optimized_size bigint(20) unsigned NOT NULL DEFAULT 0,
        status varchar(20) NOT NULL DEFAULT 'unoptimized',
        optimization_level varchar(20) NOT NULL DEFAULT '',
        output_format varchar(20) NOT NULL DEFAULT '',
        created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY (id),
        UNIQUE KEY file_path (file_path(191)),
        KEY folder_path (folder_path(191)),
        KEY status (status)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

/**
 * Get default plugin settings.
 */
function mpio_get_default_settings() {
    return [
        // General.
        'auto_optimize'      => true,
        'optimization_level' => 'lossy',   // lossless, lossy, ultra
        'output_format'      => 'original', // original, webp, avif
        'quality'            => 80,
        'strip_exif'         => true,
        'keep_backup'        => true,
        'backup_days'        => 30,

        // Resize.
        'resize_enabled'     => false,
        'resize_max_width'   => 2560,
        'resize_max_height'  => 2560,

        // WebP/AVIF conversion.
        'convert_to_webp'    => true,
        'convert_to_avif'    => false,
        'delete_original'    => false,

        // Watermark.
        'watermark_enabled'  => false,
        'watermark_text'     => '',
        'watermark_position' => 'bottom-right',
        'watermark_opacity'  => 40,
        'watermark_size'     => 16,

        // Bulk.
        'bulk_chunk_size'    => 1,
        'bulk_thumbnails'    => true,

        // Custom folders.
        'custom_folders'     => [],

        // Display / Serving.
        'display_mode'       => 'picture', // picture, rewrite, both, none
        'rewrite_rules'      => false,     // add .htaccess/nginx rules

        // Exclude.
        'exclude_patterns'   => '',      // newline-separated patterns: filenames, paths, or sizes

        // Advanced.
        'preferred_engine'   => 'auto',  // auto, gd, imagick
        'max_file_size'      => 0,       // 0 = unlimited, in KB
        'slugify_filenames'  => false,
    ];
}

/**
 * Get a plugin setting.
 */
function mpio_get_setting( string $key, $default = null ) {
    $settings = get_option( 'mpio_settings', [] );
    $defaults = mpio_get_default_settings();

    if ( isset( $settings[ $key ] ) ) {
        return $settings[ $key ];
    }

    if ( null !== $default ) {
        return $default;
    }

    return $defaults[ $key ] ?? null;
}

/**
 * Format bytes to human-readable string.
 */
function mpio_format_bytes( int $bytes, int $decimals = 1 ): string {
    if ( $bytes <= 0 ) {
        return '0 B';
    }

    $units = [ 'B', 'KB', 'MB', 'GB' ];
    $i     = floor( log( $bytes ) / log( 1024 ) );

    return round( $bytes / pow( 1024, $i ), $decimals ) . ' ' . $units[ $i ];
}

/**
 * Get the available image processing engine.
 */
function mpio_get_engine(): string {
    $preferred = mpio_get_setting( 'preferred_engine', 'auto' );

    if ( 'imagick' === $preferred && extension_loaded( 'imagick' ) ) {
        return 'imagick';
    }

    if ( 'gd' === $preferred && extension_loaded( 'gd' ) ) {
        return 'gd';
    }

    // Auto: prefer Imagick, fall back to GD.
    if ( extension_loaded( 'imagick' ) ) {
        return 'imagick';
    }

    return 'gd';
}

/**
 * Check if a MIME type is supported for optimization.
 */
function mpio_is_supported_mime( string $mime ): bool {
    $supported = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif',
        'image/bmp',
        'image/tiff',
    ];

    return in_array( $mime, $supported, true );
}

/**
 * Check if a file should be excluded from optimization.
 *
 * @param string $file_path Full path to the file.
 * @param int    $attachment_id Optional attachment ID.
 * @return bool True if the file should be skipped.
 */
function mpio_is_excluded( string $file_path, int $attachment_id = 0 ): bool {
    // Check per-image exclusion flag first (independent of pattern settings).
    if ( $attachment_id > 0 && get_post_meta( $attachment_id, '_mpio_excluded', true ) ) {
        return true;
    }

    $patterns = mpio_get_setting( 'exclude_patterns', '' );

    if ( ! empty( $patterns ) ) {
        $lines    = array_filter( array_map( 'trim', explode( "\n", $patterns ) ) );
        $filename = basename( $file_path );

        foreach ( $lines as $pattern ) {
            // Skip comments.
            if ( 0 === strpos( $pattern, '#' ) ) {
                continue;
            }

            // Exact filename match.
            if ( $pattern === $filename ) {
                return true;
            }

            // Wildcard match (e.g., "logo*", "*.svg", "banner-*").
            if ( strpos( $pattern, '*' ) !== false && fnmatch( $pattern, $filename ) ) {
                return true;
            }

            // Path contains match (e.g., "/uploads/logos/").
            if ( 0 === strpos( $pattern, '/' ) && strpos( $file_path, $pattern ) !== false ) {
                return true;
            }
        }
    }

    /**
     * Filter whether an image should be excluded from optimization.
     *
     * @param bool   $excluded      Whether the image is excluded.
     * @param string $file_path     The file path.
     * @param int    $attachment_id The attachment ID (0 for custom folder files).
     */
    return (bool) apply_filters( 'mpio_is_excluded', false, $file_path, $attachment_id );
}

/**
 * Delete WebP/AVIF companions for all images matching current exclude patterns.
 *
 * Runs via wp-cron after exclude_patterns setting changes.
 */
function mpio_cleanup_excluded_companions(): void {
    $backup = MPIO_Backup::get_instance();

    $query = new WP_Query( [
        'post_type'      => 'attachment',
        'post_mime_type' => [ 'image/jpeg', 'image/png', 'image/gif' ],
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ] );

    foreach ( $query->posts as $attachment_id ) {
        $file_path = get_attached_file( $attachment_id );
        if ( ! $file_path || ! mpio_is_excluded( $file_path, $attachment_id ) ) {
            continue;
        }

        // Delete main file companions.
        $backup->delete_conversion_files( $file_path );

        // Delete thumbnail companions.
        $metadata = wp_get_attachment_metadata( $attachment_id );
        if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
            $upload_dir = dirname( $file_path );
            foreach ( $metadata['sizes'] as $size_data ) {
                $thumb_path = trailingslashit( $upload_dir ) . $size_data['file'];
                if ( file_exists( $thumb_path ) ) {
                    $backup->delete_conversion_files( $thumb_path );
                }
            }
        }

        delete_post_meta( $attachment_id, '_mpio_companions' );
    }
}
add_action( 'mpio_cleanup_excluded_companions', 'mpio_cleanup_excluded_companions' );

/**
 * Add settings link on plugin page.
 */
function mpio_plugin_action_links( array $links ): array {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        admin_url( 'options-general.php?page=mpio-settings' ),
        __( 'Settings', 'mpire-image-optimizer' )
    );
    array_unshift( $links, $settings_link );

    return $links;
}
add_filter( 'plugin_action_links_' . MPIO_BASENAME, 'mpio_plugin_action_links' );
