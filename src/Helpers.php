<?php

namespace AdminMenuAggregator;

class Helpers
{

    /**
     * 获取资源 URL
     *
     * @param string $path 资源组名称
     * @param string $manifest_directory
     *
     * @return string
     */
    public static function get_assets_url(string $path, string $manifest_directory = ADMIN_MENU_AGGREGATOR_PATH): string
    {
        static $manifest;
        static $manifest_path;

        if ( ! $manifest_path) {
            $manifest_path = $manifest_directory . 'frontend/mix-manifest.json';
        }

        if ( ! $manifest) {
            // @codingStandardsIgnoreLine
            $manifest = json_decode(file_get_contents($manifest_path), true);
        }

        // Remove manifest directory from path
        $path = str_replace($manifest_directory, '', $path);
        // Make sure there’s a leading slash
        $path = '/' . ltrim($path, '/');

        // Get file URL from manifest file
        $path = $manifest[ $path ];
        // Make sure there’s no leading slash
        $path = ltrim($path, '/');

        return ADMIN_MENU_AGGREGATOR_URL . 'frontend/' . $path;
    }


    /**
     * 获取指定值的默认值
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public static function value($value)
    {
        return $value instanceof \Closure ? $value() : $value;
    }


    /**
     * 使用点注释获取数据
     *
     * @param array  $array
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public static function data_get($array, $key, $default = null)
    {
        if (is_null($key)) {
            return $array;
        }

        if (isset($array[ $key ])) {
            return $array[ $key ];
        }

        foreach (explode('.', $key) as $segment) {
            if ( ! is_array($array) || ! array_key_exists($segment, $array)) {
                return static::value($default);
            }

            $array = $array[ $segment ];
        }

        return $array;
    }


    /**
     * Get request var, if no value return default value.
     *
     * @param null $key
     * @param null $default
     *
     * @return mixed|null
     */
    public static function input_get($key = null, $default = null)
    {
        return static::data_get($_REQUEST, $key, $default);
    }


    public static function log( $message, $label = 'DEBUG', $log_file = null ) {
        // 指定默认日志路径
        if ( ! $log_file ) {
            $upload_dir = wp_upload_dir();
            $log_file = trailingslashit( $upload_dir['basedir'] ) . 'admin-menu-aggregator-debug.log';
        }

        // 构造时间戳和标签
        $timestamp = date( 'Y-m-d H:i:s' );
        $output = "[$timestamp] [$label] ";

        // 支持数组或对象格式化
        if ( is_array( $message ) || is_object( $message ) ) {
            $output .= print_r( $message, true );
        } else {
            $output .= $message;
        }

        // 写入文件
        file_put_contents( $log_file, $output . PHP_EOL, FILE_APPEND );
    }
}
