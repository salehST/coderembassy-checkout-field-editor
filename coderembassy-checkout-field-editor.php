<?php
/**
 * Plugin Name:       CoderEmbassy Checkout Field Editor
 * Plugin URI:        https://github.com/salehST/coderembassy-checkout-field-editor
 * Description:       Add and customize checkout fields in WooCommerce. Control visibility by customer type, add validation, and support both classic and block-based checkout.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            codersaleh
 * Author URI:        https://coderembassy.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       coderembassy-checkout-field-editor
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:      9.9
 */

defined( 'ABSPATH' ) || exit;

define( 'CECFE_VERSION', '1.0.0' );
define( 'CECFE_FILE', __FILE__ );
define( 'CECFE_PATH', plugin_dir_path( __FILE__ ) );
define( 'CECFE_URL', plugin_dir_url( __FILE__ ) );
define( 'CECFE_DB_VERSION', '1.0.0' );

$cecfe_autoload = CECFE_PATH . 'vendor/autoload.php';
if ( file_exists( $cecfe_autoload ) ) {
	require_once $cecfe_autoload;
}

if ( ! class_exists( \CoderEmbassy\CheckoutFieldEditor\Plugin::class ) ) {
	spl_autoload_register(
		static function ( string $class ): void {
			$prefix = 'CoderEmbassy\CheckoutFieldEditor\\';
			$base   = CECFE_PATH . 'src/';

			if ( 0 !== strpos( $class, $prefix ) ) {
				return;
			}

			$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
			$file     = $base . $relative . '.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	);
}

register_activation_hook( __FILE__, array( \CoderEmbassy\CheckoutFieldEditor\Database\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \CoderEmbassy\CheckoutFieldEditor\Database\Installer::class, 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		\CoderEmbassy\CheckoutFieldEditor\Plugin::getInstance()->init();
	}
);
