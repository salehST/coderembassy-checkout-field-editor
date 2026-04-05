<?php
defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldEditor\Database\Schema;

return array(
	'up' => static function ( \wpdb $wpdb ): void {
		unset( $wpdb );
		Schema::createTables();
	},
);
