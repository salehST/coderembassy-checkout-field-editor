const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
	...defaultConfig,
	entry: {
		index: './assets/admin/src/index.js',
	},
	output: {
		...defaultConfig.output,
		// Point directly at the dist folder so webpack's clean step
		// never touches assets/admin/src/ or assets/frontend/
		path: path.resolve(__dirname, 'assets/admin/dist'),
		filename: '[name].js',
		clean: false, // Don't delete old files — NTFS mount doesn't allow unlink
	},
};
