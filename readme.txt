=== Mpire Image Optimizer ===
Contributors: mpire
Tags: image optimization, webp, avif, compress images, media library
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Optimize, convert, and resize images with zero external dependencies. Auto WebP/AVIF, bulk optimization, before/after previews, and more.

== Description ==

Mpire Image Optimizer is a comprehensive image optimization plugin that processes images locally on your server using GD or Imagick -- no external API keys or subscriptions required.

**Key Features:**

* Auto-optimize images on upload
* Bulk optimize existing media library
* Convert to WebP and AVIF with automatic HTML rewriting
* Client-side batch converter (runs in browser, no upload needed)
* Before/after comparison previews
* Backup and one-click restore
* Media Library integration with status column and filters
* Custom folder optimization
* Text watermarking
* Image resizing
* EXIF metadata stripping
* Full REST API
* WP-CLI support

== Installation ==

1. Upload the `wp-plugin` folder to `/wp-content/plugins/mpire-image-optimizer`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Mpire Optimizer to configure
4. Existing images can be optimized via Media > Bulk Optimize

== Frequently Asked Questions ==

= Does this plugin require an API key? =
No. All image processing happens locally on your server using PHP's GD or Imagick extensions.

= What image formats are supported? =
JPEG, PNG, GIF, WebP, AVIF, BMP, and TIFF.

= Will this slow down my site? =
No. Optimization happens during upload or via background cron jobs. The batch converter runs entirely in the browser.

= Can I restore original images? =
Yes. If backups are enabled (default), you can restore any image to its original with one click.

= Does it work with CDNs? =
Yes. The plugin adds Vary: Accept headers so CDNs correctly cache different format versions.

== Changelog ==

= 1.0.0 =
* Initial release
