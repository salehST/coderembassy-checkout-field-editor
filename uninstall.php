<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Only run if the uninstall was triggered by WordPress.
$cecfm_settings = json_decode( get_option( 'cecfm_settings', '' ), true );
$remove_data    = isset( $cecfm_settings['remove_data_on_uninstall'] ) && $cecfm_settings['remove_data_on_uninstall'];

if ( ! $remove_data ) {
	return;
}

global $wpdb;

// Remove plugin options.
$cecfm_options = array(
	'cecfm_version',
	'cecfm_db_version',
	'cecfm_seeded',
	'cecfm_settings',
);
foreach ( $cecfm_options as $cecfm_option ) {
	delete_option( $cecfm_option );
}

// Drop custom tables.
$cecfm_tables = array(
	$wpdb->prefix . 'cecfm_fields',
	$wpdb->prefix . 'cecfm_customer_types',
	$wpdb->prefix . 'cecfm_sections',
	$wpdb->prefix . 'cecfm_revisions',
);
foreach ( $cecfm_tables as $cecfm_table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DROP TABLE IF EXISTS {$cecfm_table}" );
}



