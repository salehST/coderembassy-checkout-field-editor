<?php
namespace CoderEmbassy\CheckoutFieldsManager\Modules\Sections;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Abstracts\AbstractRepository;
use CoderEmbassy\CheckoutFieldsManager\Models\CECFM_Section;
use InvalidArgumentException;

class SectionRepository extends AbstractRepository {
	public function __construct( \wpdb $db ) {
		parent::__construct( $db );
		$this->table_name = $this->db->prefix . 'cecfm_sections';
	}

	/**
	 * @return CECFM_Section[]
	 */
	public function findAll(): array {
		$rows = $this->db->get_results( "SELECT * FROM {$this->table_name} ORDER BY priority ASC, id ASC", ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'toModel' ), $rows );
	}

	public function findById( int $id ): ?CECFM_Section {
		$model = parent::findById( $id );
		return $model instanceof CECFM_Section ? $model : null;
	}

	public function findByKey( string $key ): ?CECFM_Section {
		$query = $this->db->prepare( "SELECT * FROM {$this->table_name} WHERE section_key = %s LIMIT 1", $key );
		$row   = $this->db->get_row( $query, ARRAY_A );
		return is_array( $row ) ? $this->toModel( $row ) : null;
	}

	public function save( mixed $entity ): int {
		if ( ! $entity instanceof CECFM_Section ) {
			throw new InvalidArgumentException( 'SectionRepository expects a CECFM_Section entity.' );
		}

		$payload = array(
			'section_key'    => $entity->section_key,
			'title'          => $entity->title,
			'description'    => $entity->description,
			'position'       => $entity->position,
			'priority'       => $entity->priority,
			'enabled'        => $entity->enabled ? 1 : 0,
			'conditions'     => $this->jsonEncode( $entity->conditions ),
			'customer_types' => $this->jsonEncode( $entity->customer_types ),
			'meta'           => $this->jsonEncode( $entity->meta ),
		);

		if ( $entity->id > 0 ) {
			$this->db->update( $this->table_name, $payload, array( 'id' => $entity->id ) );
			return $entity->id;
		}

		$payload['created_at'] = current_time( 'mysql' );
		$this->db->insert( $this->table_name, $payload );
		return (int) $this->db->insert_id;
	}

	public function delete( int $id ): bool {
		return false !== $this->db->delete( $this->table_name, array( 'id' => $id ), array( '%d' ) );
	}

	protected function toModel( array $row ): CECFM_Section {
		foreach ( array( 'conditions', 'customer_types', 'meta' ) as $json_key ) {
			if ( isset( $row[ $json_key ] ) && is_string( $row[ $json_key ] ) ) {
				$row[ $json_key ] = $this->jsonDecode( $row[ $json_key ] );
			}
		}

		return CECFM_Section::fromRow( $row );
	}
}


