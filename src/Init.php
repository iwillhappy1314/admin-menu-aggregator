<?php

namespace AdminMenuAggregator;

class Init
{
    private const OPTION_WHITELIST = 'admin_menu_aggregator_whitelist';

    private const DEFAULT_CORE_KEEP = [
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


        add_action('admin_init', [$this, 'register_settings']);
    }


    function add_ext_menu()
    {
        global $menu, $submenu;

        if ( ! is_array($menu)) {
            return;
        }

        // print_r($menu);

        // 白名单：保留为顶级菜单的不动
        $core_keep = $this->get_menu_whitelist();

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

            // print_r($ext_plugins);

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

                $first_sub = $submenu[ $slug ][ 0 ];

                if ( ! empty($first_sub[ 2 ])) {
                    $ext_plugins[ $slug ][ 'overview_slug' ] = $slug;
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
            [$this, 'render_main_page'],  // 展示说明和白名单配置
            'dashicons-admin-plugins',
            9999                          // 放到菜单最后
        );

        // 遍历第三方插件：为每个插件在“扩展插件”下面创建一个入口
        foreach ($ext_plugins as $parent_slug => $data) {
            add_submenu_page(
                'wp-third-party-plugins',
                $data[ 'title' ],
                $data[ 'title' ],
                $data[ 'cap' ],
                $data[ 'overview_slug' ] // 优先跳到插件首页
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
            $base = $pagenow ?? '';

            if ($base === 'edit.php' || $base === 'post-new.php') {
                $current_page = $base . '?post_type=' . sanitize_text_field($_GET[ 'post_type' ]);
            }
        }

        if (empty($current_page)) {
            return;
        }
        $ext_plugins = $GLOBALS[ 'wp_ext_plugins' ] ?? [];

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

            .toplevel_page_kadence-blocks .wp-ext-subnav-wrap{
                margin: 10px 20px;
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


    public function register_settings()
    {
        register_setting(
            'admin_menu_aggregator',
            self::OPTION_WHITELIST,
            [
                'type'              => 'array',
                'default'           => [],
                'sanitize_callback' => [$this, 'sanitize_whitelist'],
            ]
        );
    }


    private function get_menu_whitelist(): array
    {
        $option = get_option(self::OPTION_WHITELIST, []);

        if ( ! is_array($option)) {
            $option = [];
        }

        $option = array_filter(array_map('sanitize_text_field', array_map('trim', $option)));

        return array_values(array_unique(array_merge(self::DEFAULT_CORE_KEEP, $option)));
    }


    public function sanitize_whitelist($input): array
    {
        if (is_string($input)) {
            $input = preg_split('/[\r\n,]+/', $input);
        }

        if ( ! is_array($input)) {
            return [];
        }

        $sanitized = [];

        foreach ($input as $item) {
            $slug = sanitize_text_field(trim($item));

            if ($slug !== '') {
                $sanitized[] = $slug;
            }
        }

        return array_values(array_unique($sanitized));
    }


    public function render_main_page()
    {
        if ( ! current_user_can('manage_options')) {
            return;
        }

        $whitelist      = get_option(self::OPTION_WHITELIST, []);
        $whitelist_text = '';

        if (is_array($whitelist)) {
            $whitelist_text = implode("\n", $whitelist);
        }

        $default_text = implode('、', self::DEFAULT_CORE_KEEP);
        ?>
        <div class="wrap">
            <h1>扩展插件</h1>

            <p>该插件会扫描后台左侧菜单，将除核心菜单以外的第三方顶级菜单统一收纳到“扩展”下，并在浏览这些插件页面时自动生成位于内容区域上方的二级导航，方便跳转插件内部的子菜单页面。</p>

            <h2>工作原理</h2>
            <ol>
                <li>在 <code>admin_menu</code> 钩子中遍历 <code>$menu</code> / <code>$submenu</code>，记录所有第三方顶级菜单。</li>
                <li>为每个被收纳的插件在“扩展”下创建一个子菜单入口，并移除其原有的顶级菜单。</li>
                <li>进入被收纳插件的任意页面时，在 <code>in_admin_header</code> 钩子里渲染该插件的子菜单导航。</li>
            </ol>

            <h2>白名单设置</h2>
            <p>默认保留的顶级菜单：<?php echo esc_html($default_text); ?>。</p>
            <p>如需额外保留某些插件的顶级菜单，在下方输入它们的菜单 slug（每行或用逗号分隔一个）。保存后会与默认白名单合并，不会被收纳到“扩展”下。</p>

            <form method="post" action="options.php">
                <?php settings_fields('admin_menu_aggregator'); ?>
                <textarea name="<?php echo esc_attr(self::OPTION_WHITELIST); ?>" rows="8" class="large-text code" placeholder="例如：woocommerce, rank-math"><?php echo esc_textarea($whitelist_text); ?></textarea>
                <?php submit_button('保存白名单'); ?>
            </form>
        </div>
        <?php
    }
}
