const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
	'ngsign-files': path.join(__dirname, 'src', 'ngsign-files.js'),
	'ngsign-settings': path.join(__dirname, 'src', 'ngsign-settings.js'),
	'ngsign-transactions': path.join(__dirname, 'src', 'ngsign-transactions.js'),
}

webpackConfig.output.filename = '[name].js'

module.exports = webpackConfig
