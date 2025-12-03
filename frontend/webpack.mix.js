let mix = require('laravel-mix');
let webpack = require('webpack');

require('laravel-mix-tailwind');
require('laravel-mix-versionhash');
require('laravel-mix-copy-watched');

mix.setPublicPath('./');

mix.webpackConfig({
  externals: {
    jquery: 'jQuery',
  },
  plugins  : [
    new webpack.ProvidePlugin({
      $              : 'jquery',
      jQuery         : 'jquery',
      'window.jQuery': 'jquery',
    })],
});

mix.sass('assets/css/styles.scss', 'dist').
    tailwind().
    options({
      outputStyle: 'compressed',
      postCss: [
        require('css-mqpacker'),
      ],
    });

mix.js('assets/js/main.js', 'dist');

if (mix.inProduction()) {
  mix.versionHash();
} else {
  mix.sourceMaps();
  mix.webpackConfig({devtool: 'eval-cheap-source-map'});
}

mix.browserSync({
  proxy         : 'admin-menu-aggregator.test',
  files         : [
    {
      match  : [
        './dist/**/*',
        '../**/*.php',
      ],
      options: {
        ignored: '*.txt',
      },
    },
  ],
  snippetOptions: {
    whitelist: ['/wp-admin/admin-ajax.php'],
    blacklist: ['/wp-admin/**'],
  },
});