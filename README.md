# Mpire Image Optimizer

**WordPress Plugin** | Version 1.0.0 | PHP 7.4+ | WordPress 6.0+

A comprehensive image optimization plugin for WordPress that combines **server-side processing** (GD/Imagick) with a **client-side batch converter** (React/WASM). Auto-optimizes on upload, bulk-processes existing media, generates WebP/AVIF companions, and automatically rewrites page HTML to serve modern formats — all with zero external API dependencies.

---

## Table of Contents

- [Features](#features)
- [Architecture](#architecture)
- [File Structure](#file-structure)
- [How It Works](#how-it-works)
- [Settings Reference](#settings-reference)
- [Admin Pages](#admin-pages)
- [REST API](#rest-api)
- [AJAX Endpoints](#ajax-endpoints)
- [Hooks & Filters](#hooks--filters)
- [Database](#database)
- [Requirements](#requirements)
- [Changelog](#changelog)

---

## Features

### Core Optimization
- **Dual engine support** — uses Imagick (preferred) or GD Library automatically
- **Format conversion** — convert between JPEG, PNG, GIF, WebP, and AVIF
- **Quality control** — configurable quality slider (1-100) with optimization levels: Lossless, Lossy, Ultra
- **Smart optimization** — if the optimized file is larger than the original, the original is kept
- **EXIF stripping** — remove metadata to reduce file size (configurable)
- **Image resizing** — downscale images exceeding configurable max dimensions (maintains aspect ratio)
- **Filename slugification** — optionally clean filenames to URL-friendly format

### Auto-Optimize on Upload
- Hooks into WordPress media upload pipeline
- Automatically optimizes images as they're uploaded
- Creates backups before optimization
- Respects max file size limits
- Fires `mpio_after_auto_optimize` action for extensibility

### Bulk Optimization
- Background processing via WP Cron (no timeouts)
- Configurable batch size (1-50 images per batch)
- Real-time progress bar with success/error counts
- Start/stop controls
- Also optimizes thumbnails (configurable)
- Activity log

### Automatic Image Serving (WebP/AVIF)
- **Companion file generation** — creates `.webp` and `.avif` versions alongside originals and all thumbnails
- **`<picture>` tag rewriting** — automatically wraps `<img>` tags in `<picture>` elements with `<source>` tags for WebP/AVIF
  - Filters: `the_content`, `the_excerpt`, `widget_text`, `wp_get_attachment_image`, `wp_content_img_tag`
  - Handles `srcset` responsive images — builds matching WebP/AVIF srcset entries
  - Skips external images and already-converted formats
  - Supports both naming conventions: `photo.webp` and `photo.jpg.webp`
- **Server rewrite rules** — `.htaccess` rules (Apache/LiteSpeed) or nginx config to transparently serve modern formats based on browser `Accept` header
  - Adds `Vary: Accept` header for CDN compatibility
  - Auto-syncs when settings change
- **4 display modes**: Picture Tags / Server Rewrite / Both / None

### Media Library Integration
- **"Mpire Optimizer" column** in Media Library list view showing:
  - Optimization status (optimized, error, unoptimized)
  - Savings percentage
  - Per-image Optimize/Restore/Retry buttons
- **Filter dropdown** — filter media by: All / Optimized / Unoptimized / Errors
- **Attachment edit modal** — optimization info visible in media details

### Backup & Restore
- Automatic backup of originals before optimization
- Backups stored in `wp-content/uploads/mpio-backups/{year}/{month}/`
- Protected with `.htaccess` (Deny from all) and `index.php`
- Configurable retention period (0 = keep forever)
- Automated cleanup via WP Cron
- One-click restore from Media Library or REST API
- Restore also cleans up all companion WebP/AVIF files

### Client-Side Batch Converter
- Full React application embedded as a WordPress admin page
- Runs entirely in the browser — no server upload required
- Uses WASM codecs: MozJPEG, libwebp, libaom (AVIF), OxiPNG
- Drag & drop files or folders
- Virtual scrolling for large file lists (1000+ files)
- Before/after comparison slider
- Batch download as ZIP
- Quality presets, watermarking, resize, EXIF stripping
- Dark/light theme

### Custom Folders
- Optimize images in any directory within the WordPress installation
- Add/remove folders via admin UI
- Sync to detect new/deleted files
- Per-folder optimization
- Tracked in a custom database table

### Watermarking
- Text-based watermarks
- 5 positions: top-left, top-right, center, bottom-left, bottom-right
- Configurable opacity (1-100%) and font size (8-72px)
- Applied during optimization

### REST API
Full REST API at `mpio/v1/` namespace for programmatic access:
- `GET /stats` — overall optimization statistics
- `GET /settings` — current plugin settings
- `POST /settings` — update settings
- `POST /optimize/{id}` — optimize a single attachment
- `POST /restore/{id}` — restore from backup
- `GET /bulk/status` — bulk optimization progress
- `POST /bulk/start` — start bulk optimization
- `POST /bulk/stop` — stop bulk optimization
- `GET /images` — paginated image list with status filters
- `GET /engine` — processing engine information

### Clean Uninstall
Removes all plugin data on deletion:
- Plugin options (`mpio_settings`)
- All post meta (`_mpio_*`)
- Custom database table (`mpio_custom_files`)
- Transients
- Backup directory and all backup files
- `.htaccess` rewrite rules
- Scheduled cron events

---

## Architecture

### Hybrid Processing Model

| Layer | Technology | Use Case |
|-------|-----------|----------|
| Server-side | PHP GD / Imagick | Auto-optimize on upload, bulk processing, API |
| Client-side | React + WASM (jsquash) | Manual batch conversion in browser |
| Serving | .htaccess / nginx / PHP | Transparent WebP/AVIF delivery |

### Design Patterns
- **Singleton pattern** — all core classes use `get_instance()` for single instantiation
- **WordPress hooks** — standard WP patterns for filters, actions, AJAX, REST API, Settings API
- **Separation of concerns** — each class handles one domain (optimizer, backup, bulk, media library, etc.)
- **No external dependencies** — all processing is local (no API keys, no third-party services)

---

## File Structure

```
wp-plugin/ (25 files, ~9,800 lines)
├── mpire-image-optimizer.php           # Main plugin file: constants, activation/deactivation,
│                                       # helper functions, class loading, settings defaults
├── uninstall.php                       # Full cleanup on plugin deletion
├── README.md                           # This file
│
├── includes/                           # Core PHP classes
│   ├── class-mpio-settings.php         # WordPress Settings API registration & sanitization
│   ├── class-mpio-optimizer.php        # Image optimization engine (GD + Imagick)
│   │                                   #   optimize(), optimize_file(), resize_image(),
│   │                                   #   apply_watermark(), convert_format(),
│   │                                   #   generate_companion_formats()
│   ├── class-mpio-backup.php           # Backup/restore with auto-cleanup cron
│   ├── class-mpio-media-library.php    # Media Library column, filter dropdown, AJAX actions
│   ├── class-mpio-auto-optimize.php    # Auto-optimize on upload via wp_generate_attachment_metadata
│   ├── class-mpio-bulk-optimizer.php   # Background bulk optimization via WP Cron
│   ├── class-mpio-custom-folders.php   # Custom folder scanning, tracking, optimization
│   ├── class-mpio-ajax.php             # AJAX endpoints (stats, image data, settings, engine, CSV export)
│   ├── class-mpio-rest-api.php         # REST API (mpio/v1/) — 10 endpoints
│   ├── class-mpio-admin-notices.php    # Activation notice, conflict detection, large upload warning
│   ├── class-mpio-cdn-compat.php       # CDN cache purge hooks (10+ providers)
│   ├── class-mpio-dashboard-widget.php # WP Dashboard stats widget
│   ├── class-mpio-logger.php           # Error/debug logging with auto-rotation
│   ├── class-mpio-cli.php              # WP-CLI commands (optimize, restore, bulk, stats, engine, reset)
│   ├── class-mpio-content-rewriter.php # <picture> tag + lazy loading rewriting
│   └── class-mpio-rewrite-rules.php    # .htaccess / nginx rewrite rule management
│
├── admin/                              # Admin UI
│   ├── class-mpio-admin.php            # Admin pages, menus, asset enqueuing
│   ├── views/
│   │   ├── settings-page.php           # Settings UI (toggles, sliders, radio cards, engine info)
│   │   ├── bulk-optimizer.php          # Bulk optimization page with progress bar
│   │   ├── batch-converter.php         # Client-side React converter embed page
│   │   └── custom-folders.php          # Custom folder management page
│   ├── css/
│   │   ├── mpio-admin.css              # Admin theme (cards, stats grid, progress, toggles)
│   │   └── mpio-media-library.css      # Media Library column styles
│   └── js/
│       ├── mpio-batch-converter.js     # Full React batch converter app (2,300 lines)
│       ├── mpio-bulk-optimizer.js      # Bulk optimizer with polling and progress
│       ├── mpio-media-library.js       # Single optimize/restore AJAX handlers
│       ├── mpio-custom-folders.js      # Folder management AJAX handlers
│       └── mpio-settings.js            # Settings page interactions (sliders, radio cards)
│
└── languages/                          # i18n directory (text domain: mpire-image-optimizer)
```

---

## How It Works

### Upload Flow
```
1. User uploads image
2. WordPress fires add_attachment hook
   → MPIO_Auto_Optimize::track_upload() records the ID
3. WordPress generates metadata (thumbnails)
   → wp_generate_attachment_metadata filter fires
   → MPIO_Auto_Optimize::optimize_on_upload() runs
4. Backup created → Image optimized → Companion WebP/AVIF generated
5. Metadata stored in _mpio_data, _mpio_status, _mpio_companions post meta
```

### Page Rendering Flow
```
1. Visitor requests a page
2. WordPress renders content through the_content filter
3. MPIO_Content_Rewriter::rewrite_content() processes all <img> tags
4. For each <img>, checks if companion .webp/.avif files exist on disk
5. Wraps in <picture> with <source> tags for AVIF (priority) and WebP
6. Original <img> remains as fallback for unsupported browsers
```

### Server Rewrite Flow (Apache)
```
1. Browser requests image.jpg with Accept: image/webp header
2. .htaccess RewriteCond checks if image.jpg.webp exists
3. If yes, serves the WebP version with correct Content-Type
4. Vary: Accept header ensures CDN caches both versions
```

### Bulk Optimization Flow
```
1. User clicks "Start Bulk Optimization"
2. AJAX call queues all unoptimized attachment IDs in a transient
3. WP Cron processes batch_chunk_size images per tick
4. Frontend polls status every 3 seconds via AJAX
5. Progress bar, success/error counts update in real-time
6. On completion, fires mpio_bulk_complete action
```

---

## Settings Reference

### General
| Setting | Key | Type | Default | Description |
|---------|-----|------|---------|-------------|
| Auto-optimize | `auto_optimize` | bool | `true` | Optimize images on upload |
| Optimization Level | `optimization_level` | enum | `lossy` | `lossless`, `lossy`, `ultra` |
| Output Format | `output_format` | enum | `original` | `original`, `webp`, `avif`, `jpeg`, `png` |
| Quality | `quality` | int | `80` | 1-100 |
| Strip EXIF | `strip_exif` | bool | `true` | Remove metadata |
| Slugify Filenames | `slugify_filenames` | bool | `false` | URL-friendly names |

### Backup
| Setting | Key | Type | Default | Description |
|---------|-----|------|---------|-------------|
| Keep Backup | `keep_backup` | bool | `true` | Store original files |
| Backup Days | `backup_days` | int | `30` | 0 = keep forever |

### Format Conversion
| Setting | Key | Type | Default | Description |
|---------|-----|------|---------|-------------|
| Create WebP | `convert_to_webp` | bool | `true` | Generate WebP companions |
| Create AVIF | `convert_to_avif` | bool | `false` | Generate AVIF companions |
| Delete Original | `delete_original` | bool | `false` | Remove original after conversion |

### Image Serving
| Setting | Key | Type | Default | Description |
|---------|-----|------|---------|-------------|
| Display Mode | `display_mode` | enum | `picture` | `picture`, `rewrite`, `both`, `none` |
| Rewrite Rules | `rewrite_rules` | bool | `false` | Add .htaccess rules |

### Resize
| Setting | Key | Type | Default | Description |
|---------|-----|------|---------|-------------|
| Resize Enabled | `resize_enabled` | bool | `false` | Downscale large images |
| Max Width | `resize_max_width` | int | `2560` | Max width in pixels |
| Max Height | `resize_max_height` | int | `2560` | Max height in pixels |

### Watermark
| Setting | Key | Type | Default | Description |
|---------|-----|------|---------|-------------|
| Enabled | `watermark_enabled` | bool | `false` | Add text watermark |
| Text | `watermark_text` | string | `""` | Watermark text |
| Position | `watermark_position` | enum | `bottom-right` | 5 positions |
| Opacity | `watermark_opacity` | int | `40` | 1-100% |
| Font Size | `watermark_size` | int | `16` | 8-72px |

### Bulk
| Setting | Key | Type | Default | Description |
|---------|-----|------|---------|-------------|
| Batch Size | `bulk_chunk_size` | int | `5` | Images per cron batch |
| Thumbnails | `bulk_thumbnails` | bool | `true` | Also optimize thumbnails |

### Advanced
| Setting | Key | Type | Default | Description |
|---------|-----|------|---------|-------------|
| Engine | `preferred_engine` | enum | `auto` | `auto`, `gd`, `imagick` |
| Max File Size | `max_file_size` | int | `0` | KB, 0 = no limit |

---

## Admin Pages

| Page | Menu Location | Slug | Capability |
|------|--------------|------|------------|
| Settings | Settings > Mpire Optimizer | `mpio-settings` | `manage_options` |
| Bulk Optimize | Media > Bulk Optimize | `mpio-bulk-optimizer` | `upload_files` |
| Batch Converter | Media > Batch Converter | `mpio-batch-converter` | `upload_files` |
| Custom Folders | Media > Custom Folders | `mpio-custom-folders` | `manage_options` |

---

## REST API

**Namespace:** `mpio/v1`

| Method | Endpoint | Permission | Description |
|--------|----------|-----------|-------------|
| GET | `/stats` | `manage_options` | Overall optimization statistics |
| GET | `/settings` | `manage_options` | Current settings |
| POST | `/settings` | `manage_options` | Update settings |
| POST | `/optimize/{id}` | `upload_files` | Optimize single attachment |
| POST | `/restore/{id}` | `upload_files` | Restore from backup |
| GET | `/bulk/status` | `manage_options` | Bulk progress |
| POST | `/bulk/start` | `manage_options` | Start bulk optimization |
| POST | `/bulk/stop` | `manage_options` | Stop bulk optimization |
| GET | `/images` | `upload_files` | Paginated image list |
| GET | `/engine` | `manage_options` | Engine info |

---

## AJAX Endpoints

| Action | Capability | Class |
|--------|-----------|-------|
| `mpio_optimize_single` | `upload_files` | MPIO_Media_Library |
| `mpio_restore_single` | `upload_files` | MPIO_Media_Library |
| `mpio_bulk_start` | `manage_options` | MPIO_Bulk_Optimizer |
| `mpio_bulk_stop` | `manage_options` | MPIO_Bulk_Optimizer |
| `mpio_bulk_status` | `manage_options` | MPIO_Bulk_Optimizer |
| `mpio_get_stats` | `upload_files` | MPIO_Ajax |
| `mpio_get_image_data` | `upload_files` | MPIO_Ajax |
| `mpio_save_settings` | `manage_options` | MPIO_Ajax |
| `mpio_check_engine` | `manage_options` | MPIO_Ajax |
| `mpio_reset_image` | `manage_options` | MPIO_Ajax |
| `mpio_add_folder` | `manage_options` | MPIO_Custom_Folders |
| `mpio_remove_folder` | `manage_options` | MPIO_Custom_Folders |
| `mpio_scan_folder` | `manage_options` | MPIO_Custom_Folders |
| `mpio_optimize_custom` | `manage_options` | MPIO_Custom_Folders |
| `mpio_add_rewrite_rules` | `manage_options` | MPIO_Rewrite_Rules |
| `mpio_remove_rewrite_rules` | `manage_options` | MPIO_Rewrite_Rules |
| `mpio_get_nginx_config` | `manage_options` | MPIO_Rewrite_Rules |

All AJAX endpoints verify nonces (`mpio_nonce`) and check capabilities.

---

## Hooks & Filters

### Actions
| Hook | Description |
|------|-------------|
| `mpio_loaded` | Fires after all plugin components are initialized |
| `mpio_after_auto_optimize` | Fires after auto-optimization (params: attachment_id, result) |
| `mpio_bulk_complete` | Fires when bulk optimization finishes |
| `mpio_before_optimize` | Fires before an image is optimized |
| `mpio_after_optimize` | Fires after an image is optimized |

### Filters
| Hook | Description |
|------|-------------|
| `mpio_bulk_thumbnails` | Override thumbnail optimization in bulk (bool) |
| `mpio_unoptimized_query_args` | Modify WP_Query args for unoptimized images |

---

## Database

### Options
| Key | Description |
|-----|-------------|
| `mpio_settings` | All plugin settings (serialized array) |

### Post Meta
| Key | Description |
|-----|-------------|
| `_mpio_data` | Optimization data (sizes, savings, format, timestamp) |
| `_mpio_status` | Status: `optimized`, `error`, `skipped` |
| `_mpio_backup_path` | Path to backup file |
| `_mpio_companions` | Array of companion file paths (WebP/AVIF) |

### Transients
| Key | Description |
|-----|-------------|
| `mpio_bulk_queue` | Array of attachment IDs to process |
| `mpio_bulk_running` | Boolean flag for active bulk job |
| `mpio_bulk_progress` | Progress data (total, processed, success, errors) |

### Custom Table
| Table | Description |
|-------|-------------|
| `{prefix}mpio_custom_files` | Tracks images in custom folders |

### Cron Events
| Hook | Schedule | Description |
|------|----------|-------------|
| `mpio_bulk_optimization_cron` | Single event | Processes next batch |
| `mpio_cleanup_backups_cron` | Daily | Cleans expired backups |

---

## Requirements

- **PHP:** 7.4 or higher
- **WordPress:** 6.0 or higher
- **PHP Extension:** GD or Imagick (at least one required)
- **Optional for WebP:** GD with WebP support or Imagick with WEBP delegate
- **Optional for AVIF:** PHP 8.1+ with GD, or Imagick with AVIF delegate

---

## Changelog

### 1.0.1 (2026-04-06)
- **Fix:** Per-image exclude (`_mpio_excluded` meta) was ignored when the exclude patterns textarea in settings was empty — moved per-image check before pattern check so it always runs

### 1.0.0 (2026-03-27)
- Initial release
- Server-side optimization with GD and Imagick dual engine
- Client-side React batch converter with WASM codecs
- Auto-optimize on upload
- Bulk optimization with WP Cron background processing
- Media Library column with optimize/restore actions and filter dropdown
- Backup and restore system with configurable retention
- WebP and AVIF companion file generation
- `<picture>` tag rewriting for automatic modern format serving
- Apache .htaccess and nginx rewrite rule generation
- Custom folder support with database tracking
- Text watermarking with position, opacity, and size controls
- Image resizing with aspect ratio preservation
- EXIF metadata stripping
- Full REST API (10 endpoints)
- 17 AJAX endpoints with nonce verification
- WordPress Settings API integration
- Clean uninstall (options, meta, table, backups, cron, .htaccess)
- i18n ready (text domain: mpire-image-optimizer)
- Lazy loading: `<picture>` rewrite preserves `loading="lazy"`, adds `decoding="async"` and `fetchpriority="low"` for lazy images
- WP-CLI commands: `wp mpio optimize`, `wp mpio restore`, `wp mpio bulk`, `wp mpio stats`, `wp mpio engine`, `wp mpio reset`
- Dashboard widget with stats, progress bar, and quick links
- Deactivation safeguard: removes .htaccess rules, stops bulk jobs, clears cron on deactivate
- CDN cache purge hooks: WP Rocket, W3 Total Cache, WP Super Cache, LiteSpeed, Cloudflare, SG Optimizer, Autoptimize, Kinsta, Nginx Helper, Varnish
- First activation notice with unoptimized image count and bulk optimize CTA
- Plugin conflict detection: warns when other image optimizers (Imagify, Smush, ShortPixel, EWWW, TinyPNG, etc.) are active
- Large upload warning (>10MB) with admin notice
- Error logging to `wp-content/uploads/mpio-backups/mpio-debug.log` with auto-rotation (2MB max)
- Re-optimize: restore from backup and re-optimize with current settings (Media Library + WP-CLI `--force`)
- Before/after comparison slider modal in Media Library for optimized images with backups
- CSV export of all optimization data (ID, title, MIME, status, sizes, savings, format, date)
- Multisite compatible: network activation with per-site tables/options, `wp_initialize_site` hook for new sites
- Scheduled custom folder scan (twice daily via WP Cron) to detect new/deleted files
- Security: all 20 AJAX endpoints verified with nonces + capability checks, `realpath()` path traversal protection, blocked sensitive dirs (wp-admin, wp-includes)
- Code review: 12 bugs found and fixed (6 critical, 3 high, 2 medium) — see [Code Review Log](#code-review-log)
- Admin bar stats: always-visible "X MB saved" in WP admin bar with dropdown quick links
- Auto-optimize success toast: notification after each upload showing filename, savings %, bytes saved
- One-click optimization presets: "Max Quality", "Recommended", "Max Compression" with auto-set quality slider
- Inline help tooltips on settings fields (auto-optimize, EXIF, WebP, AVIF, delete original)
- Savings celebration modal after bulk optimization with total saved, avg compression, and elapsed time
- Health check / diagnostics page: system status, format support, disk usage, plugin conflicts, test optimization, recent logs
- Bulk restore all: one-click restore every optimized image to originals
- Exclude list: skip specific files, wildcards (banner-*), or path fragments (/uploads/logos/) from optimization
- Media grid view badges: green savings % badge on optimized images, red error badge on failures

---

## Roadmap

### Testing
- [ ] Set up local WordPress environment (Docker / LocalWP)
- [ ] Smoke test each admin page (Settings, Bulk Optimizer, Batch Converter, Custom Folders)
- [ ] Test auto-optimize on upload (JPEG, PNG, GIF, WebP)
- [ ] Test bulk optimization start/stop/progress
- [ ] Test backup and restore flow
- [ ] Test WebP/AVIF companion generation
- [ ] Test `<picture>` tag rewriting on frontend
- [ ] Test `.htaccess` rewrite rule generation
- [ ] Test Media Library column (optimize, restore, re-optimize, compare)
- [ ] Test custom folder add/remove/sync/optimize
- [ ] Test WP-CLI commands
- [ ] Test CSV export
- [ ] Test dashboard widget
- [ ] Test plugin conflict detection notices
- [ ] Test deactivation (rules removed, cron cleared)
- [ ] Test uninstall (all data cleaned up)
- [ ] Test on multisite

### Unit & Integration Tests
- [ ] Set up PHPUnit with WordPress test framework
- [ ] `MPIO_Optimizer` — GD and Imagick optimization, resize, watermark, format conversion
- [ ] `MPIO_Backup` — backup creation, restore, cleanup, companion file deletion
- [ ] `MPIO_Settings` — sanitization of all field types
- [ ] `MPIO_Bulk_Optimizer` — stats queries, queue management
- [ ] `MPIO_Content_Rewriter` — `<img>` to `<picture>` conversion, srcset handling, lazy loading
- [ ] `MPIO_Custom_Folders` — path validation, scan, sync, DB operations
- [ ] `MPIO_REST_API` — endpoint responses, permission checks
- [ ] `MPIO_Media_Library` — column rendering, filter queries

### Packaging & Distribution
- [ ] Build script to create clean `.zip` for distribution
- [ ] Add `index.php` silence files to all directories
- [ ] Plugin icon (128x128, 256x256) and banner (772x250, 1544x500) assets
- [ ] Screenshots for WordPress.org listing
- [ ] Comply with WordPress.org plugin review guidelines:
  - Prefix all globals and functions
  - Use `WP_Filesystem` instead of direct `file_put_contents` / `copy`
  - Escape all output in templates
  - Sanitize all input

### CI/CD
- [ ] Initialize git repository
- [ ] GitHub Actions workflow for PHP linting (PHPCS with WordPress standards)
- [ ] GitHub Actions workflow for PHPUnit tests
- [ ] Automated `.zip` build on release tag

### Future Features

- [ ] **Exclude/skip list** — let users exclude specific images, folders, sizes, or MIME types from optimization (e.g., "don't touch my logo", "skip everything in /assets/raw/")
- [ ] **WooCommerce compatibility** — ensure product images, variations, gallery images, and zoom images are all optimized correctly; test with popular WooCommerce themes and plugins
- [ ] **Dry run / preview mode** — show estimated file sizes and savings before actually optimizing; let users review and confirm before bulk operations
- [ ] **Bulk restore all** — one-click restore ALL optimized images to originals (we have per-image restore; need "restore everything" with progress bar)
- [ ] **Disk space monitoring** — show total disk usage by companion files (WebP/AVIF), backups, and original images; warn when approaching disk limits; add a "clean up companions" button
- [ ] **Memory/resource limits** — configurable PHP memory limit per batch, auto-detect shared hosting constraints, gracefully skip images that would exceed memory instead of crashing
- [ ] **PDF awareness** — detect PDFs in the media library and skip them by default; never strip accessibility/508/ADA metadata from PDFs; add option to optimize PDF thumbnails only
- [ ] **Safe mode / conservative defaults** — default to lossless optimization on first activation to avoid quality complaints; show a clear "quality impact" preview before switching to lossy/ultra
- [ ] **Progress persistence / resume** — if bulk optimization is interrupted (server restart, timeout, browser close), resume from where it left off instead of restarting; store progress in database, not just transients
- [ ] **Health check / diagnostics page** — a dedicated admin page showing: engine status, file permissions, disk space, PHP memory limit, max execution time, GD/Imagick capabilities, last optimization run, error count, and a "run diagnostics" button that tests optimization on a sample image
- [ ] **Duplicate file prevention** — detect and warn when WebP/AVIF companions already exist before generating new ones; add a "clean duplicates" tool; track total companion file count in dashboard

#### Additional Feature Ideas
- [ ] Image lazy loading placeholder (LQIP / blur-up / dominant color)
- [ ] Usage analytics dashboard with charts over time (savings trend, images optimized per day/week)
- [ ] Import / export settings (JSON file for migrating between sites)
- [ ] Auto-optimize new files detected in custom folder scans
- [ ] Image CDN / proxy service integration (optional, for users who want it)
- [ ] SVG optimization support (SVGO-based, minify SVG files without breaking them)
- [ ] PDF thumbnail optimization (optimize the thumbnails WP generates for PDFs)
- [ ] Drag-and-drop re-ordering of optimization priority
- [ ] Email notification on bulk optimization complete
- [ ] REST API authentication via application passwords
- [ ] Scheduled optimization — run bulk optimization during low-traffic hours (e.g., 2 AM)
- [ ] Image dimension validation — warn when images are uploaded at unnecessarily large dimensions (e.g., 6000x4000px for a blog post)
- [ ] Optimization presets — save named configurations (e.g., "Blog images: lossy 80%, max 1920px" vs "Portfolio: lossless, no resize") and apply per-folder or per-upload
- [ ] Multi-format delivery strategy — automatically choose between WebP and AVIF based on per-image compression results (serve whichever is smaller)
- [ ] Rollback log — timestamped history of all optimization and restore actions for audit trail

---

## Code Review Log

Code review performed after initial build. Found and fixed 12 bugs:

| # | File | Bug | Severity |
|---|------|-----|----------|
| 1 | `class-mpio-settings.php` | Settings form group mismatch (`mpio_settings_group` vs `mpio-settings`) — form would silently fail to save | **Critical** |
| 2 | `class-mpio-settings.php` | `output_format` sanitization missing `jpeg` and `png` options from dropdown | Medium |
| 3 | `class-mpio-settings.php` | `display_mode`, `rewrite_rules`, `custom_folders` missing from sanitization — settings silently dropped on save | High |
| 4 | `class-mpio-optimizer.php` | `optimize_file()` used `get_option('mpio_quality')` etc. — individual option keys that don't exist. All settings are in the `mpio_settings` array. Optimizer used wrong defaults for quality, format, resize, watermark | **Critical** |
| 5 | `class-mpio-optimizer.php` | `get_option('mpio_bulk_thumbnails')` — wrong option key for thumbnail optimization setting | High |
| 6 | `class-mpio-optimizer.php` | `generate_companion_formats()` called `convert_format()` with MIME type `'image/webp'` instead of extension `'webp'` — companion file generation completely broken | **Critical** |
| 7 | `class-mpio-backup.php` | `cleanup_old_backups()` used `get_option('mpio_keep_backup')` / `get_option('mpio_backup_days')` — wrong option keys. Also `backup_days=0` (keep forever) would calculate threshold as current time and delete all backups | **Critical** |
| 8 | `class-mpio-bulk-optimizer.php` | `get_stats()` queried `_mpio_original_size` / `_mpio_optimized_size` meta keys that don't exist — stats always showed 0 bytes saved | **Critical** |
| 9 | `class-mpio-backup.php` | Constructor used `get_option('mpio_keep_backup')` — cleanup cron would never be scheduled | High |
| 10 | `class-mpio-custom-folders.php` | `get_folders()` read from `get_option('mpio_custom_folders')` — standalone option that doesn't exist. Custom folders completely non-functional | **Critical** |
| 11 | `class-mpio-custom-folders.php` | `add_folder()` / `remove_folder()` wrote to wrong option key — folder list changes lost on next page load | **Critical** |
| 12 | `class-mpio-ajax.php` | `ajax_check_engine()` checked `$settings['engine']` — wrong key, should be `preferred_engine` via `mpio_get_engine()` | Medium |

**Root cause:** Agent-generated classes used `get_option('mpio_<field>')` for individual settings when the plugin stores everything in a single serialized `mpio_settings` option array. The correct accessor is `mpio_get_setting('<field>')` defined in the main plugin file.

---

## Competitor Analysis

### Complaint Categories

| Category | ~Count | % | Our Status |
|----------|--------|---|------------|
| **Paid / quota too low / not free** | 45 | 21% | **Solved** — fully free, no quotas, no API key, unlimited local processing |
| **Slow / timeouts / unreliable servers** | 30 | 14% | **Solved** — local processing, no external API dependency, configurable batch size |
| **Crashes site / deletes images** | 25 | 11% | **Mitigated** — backup before optimize, skip-if-larger logic, restore capability |
| **Doesn't work / no results** | 25 | 11% | **Mitigated** — dual engine (GD/Imagick), error logging, health check planned |
| **Requires API key / account** | 15 | 7% | **Solved** — no account, no registration, works immediately on activation |
| **Poor / no support** | 15 | 7% | N/A (open source) |
| **Billing / scam / can't cancel** | 12 | 6% | **Solved** — no billing, no subscriptions, no credit card |
| **Smart compression ruined quality** | 12 | 6% | **Solved** — user controls quality directly (1-100 slider), no forced "smart" mode |
| **Images got larger after "optimization"** | 8 | 4% | **Solved** — optimizer reverts if output >= original size |
| **Expensive** | 8 | 4% | **Solved** — completely free |
| **Creates duplicate files** | 5 | 2% | **Planned** — duplicate detection and cleanup tool |
| **No clean uninstall** | 5 | 2% | **Solved** — removes all options, meta, tables, backups, cron, .htaccess |
| **Missing exclude/skip option** | 5 | 2% | **Planned** — exclude list by image, folder, MIME type, size |
| **No WooCommerce variation support** | 3 | 1% | **Planned** — WooCommerce compatibility testing |
| **Slows down frontend** | 5 | 2% | **Solved** — optimization runs in background, not on page load |
| **No bulk restore** | 3 | 1% | **Planned** — bulk restore all with progress bar |
| **Annoying review/upsell prompts** | 3 | 1% | **Solved** — dismissible notices only, no upsells |

### 2-Star Reviews (43 reviews) — "Almost works but..."

More nuanced than 1-star. Users saw potential but hit blockers:

| Complaint | Count | Example |
|-----------|-------|---------|
| **Slow / glitchy even on paid plan** | 8 | "Paid version is glitchy and stops", "Good optimization tool but SLOWWWWW" |
| **WebP output larger than originals** | 4 | "WebP images larger than originals", "Made page speed load more" |
| **Images disappear after optimization** | 4 | "Some pictures on front-end page have disappeared", "Be careful what you're doing" |
| **Free version useless** | 6 | "Don't try the free version", "Free version is not helpful" |
| **Aggressive mode destroys quality** | 3 | "Aggressive is WAY too aggressive, can't maintain whites" |
| **WooCommerce WebP broken** | 2 | "WebP does not work with Woocommerce" |
| **No backup / can't restore** | 2 | "Doesn't back up images", "Dangerous, needs much work" |
| **Confusing UX** | 2 | "Can't tell" what it did, "Not ready for primetime" |
| **Overpriced** | 3 | "Overpriced", "Mediocre performance, overpriced" |
| **Gallery plugin incompatible** | 1 | "No good if you use NextGen Pro Gallery" |

### 3-Star Reviews (31 reviews) — "Good concept, frustrating execution"

These reveal **feature gaps** — users liked the idea but wanted more:

| Complaint | Count | Example |
|-----------|-------|---------|
| **Good quality but limited free tier** | 8 | "Good but limited", "Can't even optimize 25 images with free account" |
| **Subscription-only model frustrating** | 5 | "Good quality - bad subscription-only plan", "Not one-time payment anymore" |
| **Confusing setup / options** | 3 | "I don't understand the setup options", "Very complicated, zero improvement" |
| **Destroys PDF accessibility** | 2 | "Destroys PDF accessibility tags" |
| **Annoying review/rating nags** | 2 | "Really goes on my nerves with rate us" |
| **Minimal savings on some images** | 2 | "Did not save me much on file size" |
| **Horribly slow** | 2 | "Good, yet horribly slow" |
| **Good plugin, bad support** | 2 | "Good plugin, BAD support", "Tricky product – zero support" |
| **Larger output than competitors** | 1 | "Larger files than EWWW, PageSpeed" |

### 4-Star Reviews (64 reviews) — "Love it but wish it had..."

The 4-star reviews are the goldmine — users are nearly satisfied. Their "but" reveals exactly what would make a perfect plugin:

| Gap | Count | Example |
|-----|-------|---------|
| **Wants local/offline optimization** | 3 | "Works great. Just miss local optimization" |
| **Quota still annoying even when satisfied** | 5 | "Is working great, but only 25 MB per month", "Free account only 25mb?" |
| **Nginx WebP rewrite issues** | 2 | "WebP rewrite rules from NGINX issue" |
| **Bulk optimizer underestimates library** | 2 | "Bulk optimiser underestimates media library size" |
| **WebP problems** | 2 | "Everything is great but WebP" |
| **Lots of image errors on some sites** | 2 | "Lots of image errors" |
| **Pricing could be better** | 3 | "With changes to pricing it could be perfect" |
| **Compatibility concerns** | 1 | "Excellent options, with the possibility of imperfect compatibility" |
| **Inaccurate reporting** | 1 | "Useful but inaccurate" |

### New Feature Ideas from 2/3/4-Star Reviews

These weren't in the 1-star analysis and should be added to our roadmap:

- [ ] **Optimization accuracy reporting** — show exact before/after file sizes per image, not just percentages; expose which codec was used and why
- [ ] **Guided setup wizard** — first-time setup that detects server capabilities, recommends settings, and explains each option in plain language (addresses "I don't understand the setup options")
- [ ] **Gallery plugin compatibility testing** — test with NextGen Gallery, Envira Gallery, FooGallery, Modula, and document compatibility
- [ ] **Per-image quality preview** — before committing to optimization, show a side-by-side at the chosen quality level so users can judge if it's acceptable
- [ ] **Compression comparison mode** — optimize a sample image at all quality levels and show the results in a table (size, visual diff, SSIM score) so users can pick the right level
- [ ] **"Aggressive" quality guard** — when users select ultra/aggressive compression, show a warning with a sample preview; never silently destroy quality
- [ ] **Nginx config auto-detection** — detect when running on Nginx and prominently show the config snippet instead of trying to write .htaccess
- [ ] **Bulk optimizer size estimation** — before starting bulk optimization, estimate total processing time and disk space needed based on library size
- [ ] **Optimization report email** — after bulk optimization, optionally email a summary report (X images optimized, Y MB saved, Z errors)
- [ ] **Performance benchmark** — on the health check page, run a benchmark that optimizes a test image and reports speed (images/minute) so users know what to expect

### Key Takeaway


**The 2-4 star reviews reveal the real product gaps:** confusing setup, unreliable WebP conversion, no local processing option, poor accuracy reporting, gallery plugin incompatibility, and aggressive compression without user control. These are the features that would make users switch from "it's okay" to "this is exactly what I needed."


### What This Tells Us for Mpire Image Optimizer

**Must-have qualities (non-negotiable):**
1. **"Just works" experience** — install, activate, and it's already optimizing. No configuration required for the default case. This is the #1 thing 5-star reviewers praise.
2. **Easy, clean UI** — users don't want to think. The settings page must be intuitive with sensible defaults. Complexity should be hidden behind "Advanced" sections.
3. **Visible results** — show savings prominently in the dashboard widget, media library column, and bulk optimizer. Users need to feel the improvement.
4. **Fast WebP/AVIF conversion** — this is the primary reason users install image optimizers in 2024+. It must work reliably on the first try.
5. **Set and forget** — auto-optimize on upload must be bulletproof. Users install and never think about it again.

**Our competitive advantages:**
- We match "easy to use" and "just works" — plus we don't require an account or API key
- We match "WebP conversion" — plus we do it locally without quotas
- We match "fast processing" — local processing is faster than sending to an external API
- We match "good UX" — modern card-based settings UI with toggle switches and quality slider
- We **beat** them on value — completely free with no limits vs their paid quota model
- We **beat** them on privacy — no images leave the server
- We **add** the client-side batch converter — a unique feature no competitor has

**Key risk:** free/open-source, we need to compensate with:
- Excellent documentation and inline help text
- Self-service diagnostics (health check page)
- Clear error messages with suggested fixes
- Community support via WordPress.org forums
