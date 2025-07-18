const path = require('path');
const TerserPlugin = require('terser-webpack-plugin');

module.exports = [
  // Development build (unminified)
  {
    entry: './assets/js/project-editor/App.jsx',
    output: {
      path: path.resolve(__dirname, 'assets/js'),
      filename: 'crawlflow-project-editor.js',
    },
    module: {
      rules: [
        {
          test: /\.jsx?$/,
          exclude: /node_modules/,
          use: {
            loader: 'babel-loader',
            options: {
              presets: [
                '@babel/preset-env',
                '@babel/preset-react'
              ]
            }
          }
        },
        {
          test: /\.css$/,
          use: ['style-loader', 'css-loader']
        }
      ]
    },
    resolve: {
      extensions: ['.js', '.jsx']
    },
    devtool: 'source-map',
    mode: 'development',
    optimization: {
      minimize: false
    }
  },
  // Production build (minified)
  {
    entry: './assets/js/project-editor/App.jsx',
    output: {
      path: path.resolve(__dirname, 'assets/js'),
      filename: 'crawlflow-project-editor.min.js',
    },
    module: {
      rules: [
        {
          test: /\.jsx?$/,
          exclude: /node_modules/,
          use: {
            loader: 'babel-loader',
            options: {
              presets: [
                '@babel/preset-env',
                '@babel/preset-react'
              ]
            }
          }
        },
        {
          test: /\.css$/,
          use: ['style-loader', 'css-loader']
        }
      ]
    },
    resolve: {
      extensions: ['.js', '.jsx']
    },
    devtool: 'source-map',
    mode: 'production',
    optimization: {
      minimize: true,
      minimizer: [new TerserPlugin()]
    }
  }
];