<?php
namespace CoderEmbassy\CheckoutFieldsManager\Database;

defined( 'ABSPATH' ) || exit;

class Schema {
	public static function createTables(): void {
		global $wpdb;

		require_once \ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$tables          = self::getTables();
		$fields_table    = $tables[0];
		$types_table     = $tables[1];
		$sections_table  = $tables[2];
		$revisions_table = $tables[3];

		$fields_sql = "CREATE TABLE {$fields_table} (
id bigint(20) NOT NULL AUTO_INCREMENT,
section_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
field_key VARCHAR(100) NOT NULL,
type VARCHAR(50) NOT NULL,
label VARCHAR(255) NOT NULL,
placeholder VARCHAR(255) DEFAULT '',
description TEXT DEFAULT '',
section VARCHAR(50) NOT NULL DEFAULT 'billing',
position VARCHAR(50) NOT NULL DEFAULT 'after_address',
priority INT NOT NULL DEFAULT 10,
width VARCHAR(10) NOT NULL DEFAULT 'full',
required TINYINT(1) NOT NULL DEFAULT 0,
enabled TINYINT(1) NOT NULL DEFAULT 1,
conditions LONGTEXT NOT NULL DEFAULT '[]',
required_conditions LONGTEXT NOT NULL DEFAULT '[]',
customer_types LONGTEXT NOT NULL DEFAULT '[]',
pricing_rules LONGTEXT NOT NULL DEFAULT '[]',
validation_rules LONGTEXT NOT NULL DEFAULT '{}',
options LONGTEXT NOT NULL DEFAULT '[]',
meta LONGTEXT NOT NULL DEFAULT '{}',
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
UNIQUE KEY field_key (field_key),
KEY section_priority (section, priority),
KEY enabled (enabled)
) {$charset_collate};";

		$customer_types_sql = "CREATE TABLE {$types_table} (
id bigint(20) NOT NULL AUTO_INCREMENT,
slug VARCHAR(50) NOT NULL,
label VARCHAR(100) NOT NULL,
description TEXT DEFAULT '',
is_default TINYINT(1) NOT NULL DEFAULT 0,
enabled TINYINT(1) NOT NULL DEFAULT 1,
priority INT NOT NULL DEFAULT 10,
meta LONGTEXT NOT NULL DEFAULT '{}',
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
UNIQUE KEY slug (slug)
) {$charset_collate};";

		$sections_sql = "CREATE TABLE {$sections_table} (
id bigint(20) NOT NULL AUTO_INCREMENT,
section_key VARCHAR(100) NOT NULL,
title VARCHAR(255) NOT NULL,
description TEXT DEFAULT '',
position VARCHAR(50) NOT NULL DEFAULT 'before_order_notes',
priority INT NOT NULL DEFAULT 10,
enabled TINYINT(1) NOT NULL DEFAULT 1,
conditions LONGTEXT NOT NULL DEFAULT '[]',
customer_types LONGTEXT NOT NULL DEFAULT '[]',
meta LONGTEXT NOT NULL DEFAULT '{}',
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
UNIQUE KEY section_key (section_key)
) {$charset_collate};";


		$revisions_sql = "CREATE TABLE {$revisions_table} (
id bigint(20) NOT NULL AUTO_INCREMENT,
entity_type VARCHAR(50) NOT NULL,
entity_id BIGINT UNSIGNED NOT NULL,
version INT NOT NULL DEFAULT 1,
snapshot LONGTEXT NOT NULL,
changed_by BIGINT UNSIGNED NOT NULL,
change_note VARCHAR(255) DEFAULT '',
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
KEY entity (entity_type, entity_id),
KEY entity_version (entity_type, entity_id, version)
) {$charset_collate};";

		\dbDelta( $fields_sql );
		\dbDelta( $customer_types_sql );
		\dbDelta( $sections_sql );
		\dbDelta( $revisions_sql );
	}

	public static function getTables(): array {
		global $wpdb;

		return array(
			$wpdb->prefix . 'cecfm_fields',
			$wpdb->prefix . 'cecfm_customer_types',
			$wpdb->prefix . 'cecfm_sections',
			$wpdb->prefix . 'cecfm_revisions',
		);
	}
}


