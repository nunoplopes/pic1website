const Encore = require('@symfony/webpack-encore');
const purgecss = require('@fullhuman/postcss-purgecss');

Encore
  .setOutputPath('assets/public')
  .setPublicPath('/assets/public')
  .addEntry('app', './assets/app.js')
  .configureTerserPlugin(options => {
    options.terserOptions = {
      ecma: 2020,
      module: true,
      compress: {
        drop_console: true,
        drop_debugger: true,
        passes: 3,
      },
      format: {
        comments: false,
      }
    };
  })
  .enablePostCssLoader(options => {
    options.postcssOptions = {
      plugins: [
        ...(Encore.isProduction() ? [
          purgecss.default({
            content: [
              'vendor/symfony/twig-bridge/Resources/views/Form/*.html.twig',
              'templates/**/*.html.twig',
              'assets/**/*.js',
            ]
          }),
          require('postcss-preset-env')({
            stage: 3,
            autoprefixer: { grid: false },
            preserve: false,
            minimumVendorImplementations: 2
          }),
          require('postcss-discard-comments')({ removeAll: true }),
          require('postcss-discard-empty'),
          require('postcss-merge-rules'),
          require('cssnano')
        ] : [])
      ]
    };
  })
  .disableSingleRuntimeChunk()
  .cleanupOutputBeforeBuild()
  .enableVersioning(Encore.isProduction());

module.exports = Encore.getWebpackConfig();
