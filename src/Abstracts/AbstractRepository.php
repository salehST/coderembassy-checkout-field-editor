<?php
namespace CoderEmbassy\CheckoutFieldEditor\Abstracts;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldEditor\Interfaces\RepositoryInterface;
use RuntimeException;

abstract class AbstractRepository implements RepositoryInterface {
	protected string $table_name = '';

	public function __construct( protected readonly \wpdb $db ) {}

	public function findById( int $id ): mixed {
		if ( '' === $this->table_name ) {
			return null;
		}

		$query = $this->db->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id );
		$row   = $this->db->get_row( $query, \ARRAY_A );

		if ( ! is_array( $row ) ) {
			return null;
		}

		return $this->toModel( $row );
	}

	protected function toModel( array $row ): mixed {
		return $row;
	}

	protected function jsonEncode( mixed $value ): string {
		$encoded = \wp_json_encode( $value );
		if ( false === $encoded ) {
			throw new RuntimeException( 'Failed to encode JSON value.' );
		}

		return $encoded;
	}

	protected function jsonDecode( string $value ): mixed {
		$decoded = json_decode( $value, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return array();
		}

		return $decoded;
	}
}
