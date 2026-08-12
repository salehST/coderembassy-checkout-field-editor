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
		\do_action( 'cecfm_deactivate' );
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
				'conditions'          => '[]',
				'required_conditions' => '[]',
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
				'%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
			)
		);
	}

	/**
	 * Make sure a default customer type exists.
	 *
	 * The Customer Types screen presents "Private" as the always-active default,
	 * but that is only a label — it needs a real row behind it. Installs that
	 * were seeded by an older version, or whose seed flag was carried over from
	 * another plugin, can end up with no default at all, which leaves the
	 * checkout switcher with a single entry and therefore hidden.
	 *
	 * Runs on every boot and does nothing once a default is present.
	 */
	public static function ensureDefaultCustomerType(): void {
		global $wpdb;

		if ( ! $wpdb instanceof \wpdb ) {
			return;
		}

		$table = $wpdb->prefix . 'cecfm_customer_types';

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table !== $exists ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$has_default = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE is_default = 1" );
		if ( $has_default > 0 ) {
			return;
		}

		// Promote an existing "private" row rather than creating a duplicate.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$private_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE slug = %s LIMIT 1", 'private' ) );

		if ( $private_id > 0 ) {
			$wpdb->update( $table, array( 'is_default' => 1 ), array( 'id' => $private_id ), array( '%d' ), array( '%d' ) );
			return;
		}

		$now = current_time( 'mysql' );

		$wpdb->insert(
			$table,
			array(
				'slug'        => 'private',
				'label'       => __( 'Private', 'coderembassy-checkout-fields-manager' ),
				'description' => __( 'Individual customer.', 'coderembassy-checkout-fields-manager' ),
				'is_default'  => 1,
				'enabled'     => 1,
				'priority'    => 10,
				'meta'        => '{}',
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
		);
	}
}


