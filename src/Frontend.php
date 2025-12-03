<?php

namespace AdminMenuAggregator;

class Frontend
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets()
    {
        wp_enqueue_style('admin-menu-aggregator', Helpers::get_assets_url('/dist/styles.css'));
        wp_enqueue_script('admin-menu-aggregator', Helpers::get_assets_url('/dist/main.js'), ['jquery'], ADMIN_MENU_AGGREGATOR_VERSION, true);

        wp_localize_script('admin-menu-aggregator', 'adminMenuAggregatorFrontendSettings', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }
}