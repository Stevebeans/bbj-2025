const defaults = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
  ...defaults,
  entry: {
    bootstrap: path.resolve(__dirname, 'src/comments/bootstrap.js'),
  },
  output: {
    ...defaults.output,
    path: path.resolve(__dirname, 'build/comments'),
    filename: '[name].js',
    chunkFilename: '[name].js',
    publicPath: '/wp-content/themes/bbj-v2-theme/build/comments/',
  },
};
