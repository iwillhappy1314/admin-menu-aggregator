# Admin Menu Aggregator

Admin Menu Aggregator streamlines a cluttered WordPress dashboard by consolidating third‑party top‑level admin menus into a single "Extensions" entry and surfacing a contextual sub‑navigation bar when you view any aggregated plugin page.

## Features
- Collects non‑core plugin top‑level menus and nests them under a new "Extensions" parent while preserving original capabilities.
- Keeps core menus (Dashboard, Posts, Media, Pages, Comments, Appearance, Plugins, Users, Tools, Settings, WooCommerce) untouched.
- Tracks each aggregated plugin’s submenu items and renders a lightweight navigation bar in the admin header for quick switching between its pages.
- Stores collected menu data globally (`$GLOBALS['wp_ext_plugins']`) so links point to the correct admin URLs even when slugs vary.
- Enqueues compiled frontend assets (`frontend/dist`) for any UI the plugin may expose on the site front end.

## How It Works
- On `admin_menu`, the plugin scans `$menu`/`$submenu`, whitelists core entries, and removes remaining third‑party top‑level menus after recreating them under "Extensions" (`wp-third-party-plugins`).
- The first submenu item becomes that plugin’s default overview target; otherwise the original top‑level slug is used.
- On `in_admin_header`, when you are on an aggregated plugin page, the plugin outputs a secondary navigation bar listing that plugin’s submenu items with active highlighting.

## Requirements
- PHP 7.4+.
- WordPress environment with administrator access (for `manage_options`).

## Installation
- Copy the plugin folder to `wp-content/plugins/admin-menu-aggregator/`.
- Ensure Composer dependencies are present (`vendor/`); run `composer install` if developing from source.
- Activate **Admin Menu Aggregator** from the WordPress Plugins screen.

## Usage
- After activation, open the WordPress admin menu: a new **Extensions** entry appears at the bottom containing links to all aggregated third‑party plugins.
- When viewing any of those plugin pages, use the injected sub‑navigation bar above the content area to jump between the plugin’s submenu pages.
- To keep a plugin as a top‑level menu, add its slug to the `$core_keep` whitelist in `src/Init.php`. Set `$collect_cpt` to `true` if you also want custom post type menus collected.

## Development Notes
- Asset paths are resolved via `frontend/mix-manifest.json`; edit and rebuild assets from `frontend/` using your preferred Node toolchain (Laravel Mix setup provided).
- Debugging helper `Helpers::log()` writes to `wp-content/uploads/admin-menu-aggregator-debug.log` by default.
