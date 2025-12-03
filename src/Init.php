<?php

namespace AdminMenuAggregator;

class Init
{
    /**
     * 存储单例实例
     */
    private static ?Init $instance = null;


    /**
     * 私有克隆方法，防止克隆对象
     */
    private function __clone()
    {
    }

    /**
     * 私有反序列化方法，防止反序列化创建对象
     */
    public function __wakeup()
    {
    }

    /**
     * 获取单例实例的静态方法
     */
    public static function get_instance(): ?Init
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }


    /**
     * 私有构造函数，防止直接实例化
     */
    private function __construct()
    {
        $this->init();
    }


    function init()
    {
        $classes = [
            Frontend::class,
        ];

        foreach ($classes as $class) {
            new $class;
        }

        // 全局记录插件菜单结构
        $GLOBALS[ 'wp_ext_plugins' ] = [];


        /**
         * 收纳第三方顶级菜单，只保留一个入口到“扩展插件”
         */
        add_action('admin_menu', [$this, 'add_ext_menu'], 999);


        /**
         * 在“左侧菜单”和“右侧内容”之间渲染一个插件内部二级导航
         * 仅在进入某个收纳的插件页面时显示
         */
        add_action('in_admin_header', [$this, 'add_sub_menus']);
    }


    function add_ext_menu()
    {
        global $menu, $submenu;

        if ( ! is_array($menu)) {
            return;
        }

        remove_menu_page('wc-admin&path=/payments/overview');

        // 白名单：保留为顶级菜单的不动
        $core_keep = [
            'index.php',                             // 仪表盘
            'edit.php',                              // 文章
            'upload.php',                            // 媒体
            'edit.php?post_type=page',               // 页面
            'edit-comments.php',                     // 评论
            'themes.php',                            // 外观
            'plugins.php',                           // 插件
            'users.php',                             // 用户
            'tools.php',                             // 工具
            'options-general.php',                   // 设置
            'woocommerce',                           // WooCommerce
            'woocommerce-marketing',                 // WooCommerce
            'ec-hub-dashboard',                      // WooCommerce
        ];

        // 是否把 CPT 的顶级菜单也收纳进去（看需求）
        $collect_cpt = false;

        $ext_plugins = [];

        foreach ($menu as $index => $item) {
            $slug = isset($item[ 2 ]) ? $item[ 2 ] : '';

            if (empty($slug)) {
                continue;
            }

            // 分隔线跳过
            if (strpos($slug, 'separator') === 0) {
                continue;
            }

            // 白名单保留
            if (in_array($slug, $core_keep, true)) {
                continue;
            }

            // CPT 顶级菜单看开关
            if ( ! $collect_cpt && strpos($slug, 'edit.php?post_type=') === 0) {
                continue;
            }

            // 记录这个插件顶级菜单
            $ext_plugins[ $slug ] = [
                'title'         => wp_strip_all_tags($item[ 0 ]),
                'cap'           => $item[ 1 ],
                'subs'          => [],
                'overview_slug' => $slug,
                'overview_url'  => menu_page_url($slug, false),
                'url'           => menu_page_url($slug, false),
            ];

            // 同时把该顶级菜单下面的子菜单也记录下来（用于中间导航）
            if ( ! empty($submenu[ $slug ]) && is_array($submenu[ $slug ])) {
                foreach ($submenu[ $slug ] as $sub_item) {
                    $ext_plugins[ $slug ][ 'subs' ][] = [
                        'title'      => wp_strip_all_tags($sub_item[ 0 ]),
                        'cap'        => $sub_item[ 1 ],
                        'slug'       => $sub_item[ 2 ],
                        'page_title' => ! empty($sub_item[ 3 ]) ? $sub_item[ 3 ] : wp_strip_all_tags($sub_item[ 0 ]),
                        'url'        => menu_page_url($sub_item[ 2 ], false),
                    ];
                }

                // 以第一条子菜单作为默认“总览”入口（若存在）
                $first_sub = $submenu[ $slug ][ 0 ];
                if ( ! empty($first_sub[ 2 ])) {
                    $ext_plugins[ $slug ][ 'overview_slug' ] = $first_sub[ 2 ];
                    $ext_plugins[ $slug ][ 'overview_url' ]  = menu_page_url($first_sub[ 2 ], false);
                }
            }
        }

        if (empty($ext_plugins)) {
            return;
        }

        // 保存到全局，后面渲染中间二级导航要用
        $GLOBALS[ 'wp_ext_plugins' ] = $ext_plugins;

        // 创建“扩展插件”顶级菜单
        add_menu_page(
            '扩展',
            '扩展',
            'manage_options',
            'wp-third-party-plugins',
            '__return_null',              // 内容由子菜单页面决定
            'dashicons-admin-plugins',
            9999                          // 放到菜单最后
        );

        // 遍历第三方插件：为每个插件在“扩展插件”下面创建一个入口
        foreach ($ext_plugins as $parent_slug => $data) {

            // print_r($data);
            $submenu_url = $data[ 'url' ];
            if (strpos($submenu_url, '.php') !== false && substr_count($submenu_url, '.php') >= 2 && strpos($submenu_url, 'admin.php?page=') !== false) {
                $submenu_url = ($pos = strpos($submenu_url, 'admin.php?page=')) !== false
                    ? substr_replace($submenu_url, '', $pos, strlen('admin.php?page='))
                    : $submenu_url;
            }

            add_submenu_page(
                'wp-third-party-plugins',
                $data[ 'title' ],
                $data[ 'title' ],
                $data[ 'cap' ],
                $submenu_url // 优先跳到插件首页
            );

            // 移除原来的顶级菜单（仅入口被移除，页面仍然可访问）
            remove_menu_page($parent_slug);
        }
    }

    function add_sub_menus()
    {
        if ( ! current_user_can('manage_options')) {
            // 如需要对普通管理员显示，可放宽权限检查
            // 例如使用 `manage_options` 换成更低权限
            return;
        }

        $current_page = null;

        if ( ! empty($_GET[ 'page' ])) {
            $current_page = sanitize_text_field($_GET[ 'page' ]);
        } elseif ( ! empty($_GET[ 'post_type' ])) {
            global $pagenow;
            $base = isset($pagenow) ? $pagenow : '';

            if ($base === 'edit.php' || $base === 'post-new.php') {
                $current_page = $base . '?post_type=' . sanitize_text_field($_GET[ 'post_type' ]);
            }
        }

        if (empty($current_page)) {
            return;
        }
        $ext_plugins = isset($GLOBALS[ 'wp_ext_plugins' ]) ? $GLOBALS[ 'wp_ext_plugins' ] : [];

        if (empty($ext_plugins)) {
            return;
        }

        // 找到当前页面属于哪个插件
        $current_parent = null;
        foreach ($ext_plugins as $parent_slug => $data) {
            if ($current_page === $parent_slug) {
                $current_parent = $parent_slug;
                break;
            }
            foreach ($data[ 'subs' ] as $sub) {
                if ($current_page === $sub[ 'slug' ]) {
                    $current_parent = $parent_slug;
                    break 2;
                }
            }
        }

        if ( ! $current_parent) {
            return; // 不属于已收纳的插件，不显示中间导航
        }

        $plugin_data = $ext_plugins[ $current_parent ];

        // 构造菜单链接：优先使用存储的 URL，修正缺少 admin.php 的情况
        $build_menu_url = function ($slug, $stored_url = '')
        {
            if ( ! empty($stored_url)) {
                $url = $stored_url;

                // slug 指向 .php 或 CPT 列表且 URL 携带 admin.php?page= 时，标准化为 admin_url($slug)
                if (strpos($slug, '.php') !== false && strpos($url, 'admin.php?page=') !== false) {
                    return admin_url($slug);
                }

                // 如果 slug 不是 .php，但 URL 中没有 admin.php，则补标准形式
                if (strpos($slug, '.php') === false && strpos($url, 'admin.php') === false) {
                    // URL 带 ?page= 的情况下，保持原查询参数
                    if (strpos($url, '?page=') !== false) {
                        $query = strstr($url, '?');
                        $url   = admin_url('admin.php' . $query);
                    } else {
                        $url = admin_url('admin.php?page=' . $slug);
                    }
                }

                return $url;
            }

            if (empty($slug)) {
                return '';
            }

            if (strpos($slug, 'http://') === 0 || strpos($slug, 'https://') === 0) {
                return $slug;
            }

            if (strpos($slug, '.php') !== false) {
                return admin_url($slug);
            }

            if (strpos($slug, '?') !== false && strpos($slug, 'admin.php') === 0) {
                return admin_url($slug);
            }

            return admin_url('admin.php?page=' . $slug);
        };

        // 如果插件没有子菜单，不显示中间导航
        if (empty($plugin_data[ 'subs' ])) {
            return;
        }

        // 简单样式，可以根据后台主题再美化
        ?>
        <style>
            .wp-ext-subnav-wrap {
                margin: 10px 20px 10px 0;
                padding: 10px 15px;
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
            }

            .wp-ext-subnav-title {
                margin: 0 0 8px 0;
                font-size: 16px;
                font-weight: 600;
            }

            .wp-ext-subnav {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }

            .wp-ext-subnav a {
                text-decoration: none;
                padding: 4px 10px;
                border-radius: 3px;
                border: 1px solid transparent;
                background: #f6f7f7;
                color: #1d2327;
                font-size: 13px;
            }

            .wp-ext-subnav a:hover {
                background: #f0f0f1;
            }

            .wp-ext-subnav a.is-active {
                background: #2271b1;
                color: #fff;
                border-color: #135e96;
            }
        </style>

        <div class="wp-ext-subnav-wrap">
            <div class="wp-ext-subnav-title">
                <?php echo esc_html($plugin_data[ 'title' ]); ?>
            </div>
            <div class="wp-ext-subnav">
                <?php
                // 插件的原子菜单
                foreach ($plugin_data[ 'subs' ] as $sub) {
                    $url    = $build_menu_url($sub[ 'slug' ], ! empty($sub[ 'url' ]) ? $sub[ 'url' ] : '');
                    $active = ($current_page === $sub[ 'slug' ]) ? ' is-active' : '';
                    ?>
                    <a href="<?php echo esc_url($url); ?>" class="<?php echo esc_attr($active); ?>">
                        <?php echo esc_html($sub[ 'title' ]); ?>
                    </a>
                    <?php
                }
                ?>
            </div>
        </div>
        <?php
    }
}