<?php
namespace CoderEmbassy\CheckoutFieldEditor\Modules\Fields;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldEditor\Abstracts\AbstractRepository;
use CoderEmbassy\CheckoutFieldEditor\Models\CA_Field;
use InvalidArgumentException;

class FieldRepository extends AbstractRepository {
	public function __construct( \wpdb $db ) {
		parent::__construct( $db );
		$this->table_name = $this->db->prefix . 'ca_fields';
	}

	/**
	 * @param array<string, mixed> $filters
	 * @return CA_Field[]
	 */
	public function findAll( array $filters = array() ): array {
		$where = array();
		$args  = array();

		if ( isset( $filters['section'] ) && is_string( $filters['section'] ) && '' !== $filters['section'] ) {
			$where[] = 'section = %s';
			$args[]  = $filters['section'];
		}

		if ( array_key_exists( 'enabled', $filters ) ) {
			$where[] = 'enabled = %d';
			$args[]  = ! empty( $filters['enabled'] ) ? 1 : 0;
		}

		if ( isset( $filters['customer_type'] ) && is_string( $filters['customer_type'] ) && '' !== $filters['customer_type'] ) {
			$where[] = 'customer_types LIKE %s';
			$args[]  = '%' . $this->db->esc_like( '"' . $filters['customer_type'] . '"' ) . '%';
		}

		$sql = "SELECT * FROM {$this->table_name}";
		if ( ! empty( $where ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}
		$sql .= ' ORDER BY priority ASC, id ASC';

		$query = empty( $args ) ? $sql : $this->db->prepare( $sql, ...$args );
		$rows  = $this->db->get_results( $query, \ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'toModel' ), $rows );
	}

	public function findById( int $id ): ?CA_Field {
		$model = parent::findById( $id );
		return $model instanceof CA_Field ? $model : null;
	}

	public function findByKey( string $key ): ?CA_Field {
		$query = $this->db->prepare( "SELECT * FROM {$this->table_name} WHERE field_key = %s LIMIT 1", $key );
		$row   = $this->db->get_row( $query, \ARRAY_A );

		return is_array( $row ) ? $this->toModel( $row ) : null;
	}

	/**
	 * @return CA_Field[]
	 */
	public function findBySection( string $section ): array {
		return $this->findAll(
			array(
				'section' => $section,
			)
		);
	}

	/**
	 * @return CA_Field[]
	 */
	public function findByCustomerType( string $type_slug ): array {
		return $this->findAll(
			array(
				'customer_type' => $type_slug,
			)
		);
	}

	public function save( mixed $entity ): int {
		if ( ! $entity instanceof CA_Field ) {
			throw new InvalidArgumentException( 'FieldRepository expects a CA_Field entity.' );
		}

		$now     = \current_time( 'mysql' );
		$payload = array(
			'section_id'          => $entity->section_id,
			'field_key'           => $entity->field_key,
			'type'                => $entity->type,
			'label'               => $entity->label,
			'placeholder'         => $entity->placeholder,
			'description'         => $entity->description,
			'section'             => $entity->section,
			'position'            => $entity->position,
			'priority'            => $entity->priority,
			'width'               => $entity->width,
			'required'            => $entity->required ? 1 : 0,
			'enabled'             => $entity->enabled ? 1 : 0,
			'conditions'          => $this->jsonEncode( $entity->conditions ),
			'required_conditions' => $this->jsonEncode( $entity->required_conditions ),
			'customer_types'      => $this->jsonEncode( $entity->customer_types ),
			'pricing_rules'       => $this->jsonEncode( $entity->pricing_rules ),
			'validation_rules'    => $this->jsonEncode( $entity->validation_rules ),
			'options'             => $this->jsonEncode( $entity->options ),
			'meta'                => $this->jsonEncode( $entity->meta ),
			'updated_at'          => $now,
		);

		if ( $entity->id > 0 ) {
			$this->db->update( $this->table_name, $payload, array( 'id' => $entity->id ) );
			return $entity->id;
		}

		$payload['created_at'] = $now;
		$this->db->insert( $this->table_name, $payload );
		return (int) $this->db->insert_id;
	}

	public function delete( int $id ): bool {
		return false !== $this->db->delete( $this->table_name, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * @param array<int, int>|array<string, int> $id_priority_map
	 */
	public function reorder( array $id_priority_map ): void {
		foreach ( $id_priority_map as $id => $priority ) {
			$this->db->update(
				$this->table_name,
				array(
					'priority'   => (int) $priority,
					'updated_at' => \current_time( 'mysql' ),
				),
				array( 'id' => (int) $id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		}
	}

	protected function toModel( array $row ): CA_Field {
		if ( isset( $row['conditions'] ) && is_string( $row['conditions'] ) ) {
			$row['conditions'] = $this->jsonDecode( $row['conditions'] );
		}
		if ( isset( $row['required_conditions'] ) && is_string( $row['required_conditions'] ) ) {
			$row['required_conditions'] = $this->jsonDecode( $row['required_conditions'] );
		}
		if ( isset( $row['customer_types'] ) && is_string( $row['customer_types'] ) ) {
			$row['customer_types'] = $this->jsonDecode( $row['customer_types'] );
		}
		if ( isset( $row['pricing_rules'] ) && is_string( $row['pricing_rules'] ) ) {
			$row['pricing_rules'] = $this->jsonDecode( $row['pricing_rules'] );
		}
		if ( isset( $row['validation_rules'] ) && is_string( $row['validation_rules'] ) ) {
			$row['validation_rules'] = $this->jsonDecode( $row['validation_rules'] );
		}
		if ( isset( $row['options'] ) && is_string( $row['options'] ) ) {
			$row['options'] = $this->jsonDecode( $row['options'] );
		}
		if ( isset( $row['meta'] ) && is_string( $row['meta'] ) ) {
			$row['meta'] = $this->jsonDecode( $row['meta'] );
		}

		return CA_Field::fromRow( $row );
	}
}
