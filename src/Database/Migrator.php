<?php
namespace CoderEmbassy\CheckoutFieldEditor\Database;

defined( 'ABSPATH' ) || exit;

class Migrator {
	private const VERSION_OPTION = 'checkout_architect_db_version';
	private const RAN_OPTION     = 'checkout_architect_ran_migrations';

	public function maybeMigrate(): void {
		$current_version = (string) \get_option( self::VERSION_OPTION, '0.0.0' );
		if ( version_compare( $current_version, CECFE_DB_VERSION, '>=' ) ) {
			return;
		}

		$ran_migrations = $this->getRanMigrations();
		$migration_dir  = CECFE_PATH . 'src/Database/Migrations/';
		$files          = glob( $migration_dir . '*.php' );

		if ( false === $files ) {
			$files = array();
		}

		sort( $files, SORT_NATURAL );

		global $wpdb;

		foreach ( $files as $file ) {
			$basename = basename( $file );
			if ( ! preg_match( '/^(\d{4})_.*\.php$/', $basename, $matches ) ) {
				continue;
			}

			$migration_number = (int) $matches[1];
			if ( in_array( $migration_number, $ran_migrations, true ) ) {
				continue;
			}

			$migration = require $file;
			if ( ! is_array( $migration ) || ! isset( $migration['up'] ) || ! is_callable( $migration['up'] ) ) {
				continue;
			}

			$migration['up']( $wpdb );

			$ran_migrations[] = $migration_number;
			\update_option( self::RAN_OPTION, \wp_json_encode( array_values( array_unique( $ran_migrations ) ) ) );
		}

		\update_option( self::VERSION_OPTION, CECFE_DB_VERSION );
	}

	/**
	 * @return int[]
	 */
	private function getRanMigrations(): array {
		$raw = \get_option( self::RAN_OPTION, '[]' );
		if ( ! is_string( $raw ) ) {
			return array();
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$result = array();
		foreach ( $decoded as $migration_number ) {
			$result[] = (int) $migration_number;
		}

		return $result;
	}
}
