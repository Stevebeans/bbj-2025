const defaults = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
  ...defaults,
  entry: {
    bootstrap: path.resolve(__dirname, 'src/comments/bootstrap.js'),
    main:      path.resolve(__dirname, 'src/comments/main.js'),
    composer:  path.resolve(__dirname, 'src/comments/composer.js'),
  },
  output: {
    ...defaults.output,
    path: path.resolve(__dirname, 'build/comments'),
    filename: '[name].js',
    chunkFilename: 'chunk.[name].[contenthash:8].js',
    publicPath: '/wp-content/themes/bbj-v2-theme/build/comments/',
  },
};
