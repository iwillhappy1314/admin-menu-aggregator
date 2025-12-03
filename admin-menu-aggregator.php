<?php

/**
 * Plugin Name: Admin Menu Aggregator
 * Plugin URI: https://www.wpzhiku.com/
 * Description: Description
 * Version: 1.0.0
 * Author: 文普睿思信息科技
 */

use AdminMenuAggregator\Init;

if (! defined('ABSPATH')) {
    exit;
}

const ADMIN_MENU_AGGREGATOR_PLUGIN_SLUG = 'admin-menu-aggregator';
const ADMIN_MENU_AGGREGATOR_VERSION = '1.0.0';
const ADMIN_MENU_AGGREGATOR_MAIN_FILE = __FILE__;
define('ADMIN_MENU_AGGREGATOR_PATH', plugin_dir_path(__FILE__));
define('ADMIN_MENU_AGGREGATOR_URL', plugin_dir_url(__FILE__));

define('ADMIN_MENU_AGGREGATOR_DEV_MODE', true);

require_once ADMIN_MENU_AGGREGATOR_PATH . 'vendor/autoload.php';

add_action('plugins_loaded', function () {
    Init::get_instance();
});

add_action('init', function () {
    load_plugin_textdomain('admin-menu-aggregator', false, basename(dirname(__FILE__)) . '/languages/');
});
