<?php
defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Database\Schema;

return array(
	'up' => static function ( \wpdb $wpdb ): void {
		unset( $wpdb );
		Schema::createTables();
	},
);


