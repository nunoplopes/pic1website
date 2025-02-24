const Encore = require('@symfony/webpack-encore');
const purgecss = require('@fullhuman/postcss-purgecss');

Encore
  .setOutputPath('assets/public')
  .setPublicPath('/assets/public')
  .addEntry('app', './assets/app.js')
  .enablePostCssLoader(options => {
    options.postcssOptions = {
      plugins: [
        ...(Encore.isProduction() ? [
          purgecss.default({
            content: [
              'templates/**/*.html.twig',
              'assets/**/*.js',
            ]
          })
        ] : [])
      ]
    };
  })
  .disableSingleRuntimeChunk()
  .cleanupOutputBeforeBuild()
  .enableVersioning(Encore.isProduction());

module.exports = Encore.getWebpackConfig();
