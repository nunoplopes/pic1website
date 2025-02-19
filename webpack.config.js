const Encore = require('@symfony/webpack-encore');

Encore
  .setOutputPath('assets/public')
  .setPublicPath('/assets/public')
  .addEntry('app', './assets/app.js')
  .enableSingleRuntimeChunk()
  .cleanupOutputBeforeBuild()
  .enableVersioning(Encore.isProduction());

module.exports = Encore.getWebpackConfig();
