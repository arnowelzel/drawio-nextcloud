const path = require('path')
const webpack = require('webpack')

module.exports = {
    mode: 'production',
    entry: {
        'editor': './src/editor.js',
        'main': './src/main.js',
        'adminSettings': './src/adminSettings.js',
        'personalSettings': './src/personalSettings.js'
    },
    output: {
        filename: '[name].js',
        path: path.resolve(__dirname, 'js'),
        clean: true
    },
    module: {
        rules: [
            {
                test: /\.vue$/,
                loader: 'vue-loader'
            },
            {
                test: /\.s?(a|c)ss$/,
                use: [
                    'style-loader',
                    'css-loader',
                    'sass-loader'
                ]
            },
        ]
    },
    performance: {
        hints: false,
        maxEntrypointSize: 512000,
        maxAssetSize: 512000
    }
}
