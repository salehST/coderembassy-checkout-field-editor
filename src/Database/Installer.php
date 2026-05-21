<?php
namespace CoderEmbassy\CheckoutFieldsManager\Database;

defined( 'ABSPATH' ) || exit;

class Installer {
	public static function activate(): void {
		Schema::createTables();

		if ( ! get_option( 'cecfm_seeded' ) ) {
			self::seedDefaultData();
			update_option( 'cecfm_seeded', '1' );
		}

		update_option( 'cecfm_version', CECFM_VERSION );
		update_option( 'cecfm_db_version', CECFM_DB_VERSION );
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
		\do_action( 'CECFM_deactivate' );
	}

	private static function seedDefaultData(): void {
		global $wpdb;

		$now = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'cecfm_customer_types',
			array(
				'slug'        => 'retail',
				'label'       => 'Retail Customer',
				'description' => 'Standard retail customer.',
				'is_default'  => 1,
				'enabled'     => 1,
				'priority'    => 10,
				'meta'        => '{}',
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'cecfm_sections',
			array(
				'section_key' => 'additional_info',
				'title'       => 'Additional Information',
				'description' => '',
				'position'    => 'after_billing',
				'priority'    => 10,
				'enabled'     => 1,
				'conditions'  => '[]',
				'meta'        => '{}',
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		$section_id = (int) $wpdb->insert_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'cecfm_fields',
			array(
				'section_id'       => $section_id,
				'field_key'        => 'cecfm_order_notes',
				'type'             => 'textarea',
				'label'            => 'Order Notes',
				'placeholder'      => 'Any special instructions for your order?',
				'description'      => '',
				'section'          => 'billing',
				'position'         => 'after_address',
				'priority'         => 100,
				'width'            => 'full',
				'required'         => 0,
				'enabled'          => 1,
				'conditions'       => '[]',
				'customer_types'   => '[]',
				'pricing_rules'    => '[]',
				'validation_rules' => '[]',
				'options'          => '[]',
				'meta'             => '{}',
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array(
				'%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
				'%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
			)
		);
	}
}


