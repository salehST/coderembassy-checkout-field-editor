<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Runs when the plugin is deleted from the WP admin.
// Only runs if the uninstall was triggered by WordPress.

global $wpdb;

// Remove plugin options.
$cecfe_options = array(
	'checkout_architect_version',
	'checkout_architect_db_version',
	'checkout_architect_seeded',
	'checkout_architect_settings',
	'ca_license_key',
	'ca_license_status',
	'ca_license_data',
	'ca_license_expires',
);
foreach ( $cecfe_options as $cecfe_option ) {
	delete_option( $cecfe_option );
}

// Drop custom tables.
$cecfe_tables = array(
	$wpdb->prefix . 'ca_fields',
	$wpdb->prefix . 'ca_customer_types',
	$wpdb->prefix . 'ca_sections',
	$wpdb->prefix . 'ca_templates',
	$wpdb->prefix . 'ca_revisions',
);
foreach ( $cecfe_tables as $cecfe_table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Safe: table names are built with $wpdb->prefix and hardcoded strings.
	$wpdb->query( "DROP TABLE IF EXISTS {$cecfe_table}" );
}

// Clear scheduled cron.
$cecfe_cron_hooks = array( 'cecfe_daily_license_check', 'ca_daily_license_check' );
foreach ( $cecfe_cron_hooks as $hook ) {
	$cecfe_timestamp = wp_next_scheduled( $hook );
	if ( $cecfe_timestamp ) {
		wp_unschedule_event( $cecfe_timestamp, $hook );
	}
}

