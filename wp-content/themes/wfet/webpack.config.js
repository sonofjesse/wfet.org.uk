const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const TerserPlugin = require('terser-webpack-plugin');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const CompressionPlugin = require('compression-webpack-plugin');
const { BundleAnalyzerPlugin } = require('webpack-bundle-analyzer');
const fs = require('fs');

// Main entry: src/scss + src/js. Block JS stays in the main bundle (shared deps: GSAP, Barba).
// Block SCSS is compiled separately via: npm run build:blocks
function discoverMainEntries() {
    const entries = {
        main: [
            './src/js/main.js',
            './src/scss/main.scss'
        ]
    };

    const blocksDir = path.resolve(__dirname, 'blocks');
    if (fs.existsSync(blocksDir)) {
        const blockDirs = fs.readdirSync(blocksDir, { withFileTypes: true })
            .filter(dirent => dirent.isDirectory())
            .map(dirent => dirent.name);

        blockDirs.forEach(blockName => {
            const blockJsPath = path.join(blocksDir, blockName, 'js', 'script.js');
            if (fs.existsSync(blockJsPath)) {
                entries.main.push(`./blocks/${blockName}/js/script.js`);
            }
        });
    }

    return entries;
}

const scssRule = (isProduction) => ({
    test: /\.scss$/,
    use: [
        MiniCssExtractPlugin.loader,
        {
            loader: 'css-loader',
            options: { sourceMap: true }
        },
        {
            loader: 'postcss-loader',
            options: {
                sourceMap: true,
                postcssOptions: {
                    plugins: [
                        'autoprefixer',
                        isProduction && 'cssnano'
                    ].filter(Boolean)
                }
            }
        },
        {
            loader: 'sass-loader',
            options: {
                sourceMap: true,
                sassOptions: {
                    outputStyle: isProduction ? 'compressed' : 'expanded'
                }
            }
        }
    ]
});

module.exports = (env, argv) => {
    const isProduction = argv.mode === 'production';
    const isAnalyze = process.argv.includes('--analyze');

    return {
        entry: discoverMainEntries(),
        output: {
            path: path.resolve(__dirname, 'dist'),
            filename: isProduction ? 'js/[name].min.[contenthash].js' : 'js/[name].min.js',
            chunkFilename: isProduction ? 'js/[name].chunk.[contenthash].js' : 'js/[name].chunk.js',
            clean: true
        },
        module: {
            rules: [
                {
                    test: /\.js$/,
                    exclude: /node_modules/,
                    use: {
                        loader: 'babel-loader',
                        options: {
                            presets: [
                                ['@babel/preset-env', {
                                    useBuiltIns: 'usage',
                                    corejs: 3,
                                    targets: '> 0.25%, not dead'
                                }]
                            ]
                        }
                    }
                },
                scssRule(isProduction),
                {
                    test: /\.(png|jpg|jpeg|gif|svg)$/,
                    type: 'asset/resource',
                    generator: {
                        filename: 'images/[name].[hash][ext]'
                    }
                },
                {
                    test: /\.(woff|woff2|eot|ttf|otf)$/,
                    type: 'asset/resource',
                    generator: {
                        filename: 'fonts/[name].[hash][ext]'
                    }
                }
            ]
        },
        plugins: [
            new CleanWebpackPlugin({
                cleanOnceBeforeBuildPatterns: ['dist/**/*'],
                cleanStaleWebpackAssets: true,
                protectWebpackAssets: false
            }),
            new MiniCssExtractPlugin({
                filename: isProduction ? 'css/[name].min.[contenthash].css' : 'css/[name].min.css',
                experimentalUseImportModule: false
            }),
            isProduction && new CompressionPlugin({
                filename: '[path][base].gz',
                algorithm: 'gzip',
                test: /\.(js|css|html|svg)$/,
                threshold: 10240,
                minRatio: 0.8,
            }),
            isProduction && new CompressionPlugin({
                filename: '[path][base].br',
                algorithm: 'brotliCompress',
                test: /\.(js|css|html|svg)$/,
                threshold: 10240,
                minRatio: 0.8,
            }),
            isAnalyze && new BundleAnalyzerPlugin({
                analyzerMode: 'server',
                analyzerHost: '127.0.0.1',
                analyzerPort: 8889,
                reportFilename: 'bundle-report.html',
                openAnalyzer: true,
                generateStatsFile: false,
                statsFilename: 'stats.json',
                logLevel: 'info'
            })
        ].filter(Boolean),
        optimization: {
            minimize: isProduction,
            minimizer: [
                new TerserPlugin({
                    terserOptions: {
                        compress: {
                            drop_console: false
                        }
                    }
                })
            ],
            splitChunks: false
        },
        resolve: {
            alias: {
                '@': path.resolve(__dirname, 'src/js'),
                '@scss': path.resolve(__dirname, 'src/scss'),
                '@blocks': path.resolve(__dirname, 'blocks'),
                '@images': path.resolve(__dirname, 'src/images'),
                '@fonts': path.resolve(__dirname, 'src/fonts')
            }
        },
        devtool: 'source-map',
        stats: {
            preset: 'errors-warnings',
            colors: true,
            errorDetails: true,
            moduleTrace: true,
        },
        watchOptions: {
            ignored: /node_modules/,
            poll: 1000
        }
    };
};
