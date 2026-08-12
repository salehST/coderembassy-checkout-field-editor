<?php
namespace CoderEmbassy\CheckoutFieldsManager\Modules\CustomerTypes;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Abstracts\AbstractRepository;
use CoderEmbassy\CheckoutFieldsManager\Models\CECFM_CustomerType;
use InvalidArgumentException;

class CustomerTypeRepository extends AbstractRepository {
	public function __construct( \wpdb $db ) {
		parent::__construct( $db );
		$this->table_name = $this->db->prefix . 'cecfm_customer_types';
	}

	/**
	 * @return CECFM_CustomerType[]
	 */
	public function findAll(): array {
		$rows = $this->db->get_results( "SELECT * FROM {$this->table_name} ORDER BY priority ASC, id ASC", \ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'toModel' ), $rows );
	}

	public function findById( int $id ): ?CECFM_CustomerType {
		$model = parent::findById( $id );
		return $model instanceof CECFM_CustomerType ? $model : null;
	}

	public function findBySlug( string $slug ): ?CECFM_CustomerType {
		$query = $this->db->prepare( "SELECT * FROM {$this->table_name} WHERE slug = %s LIMIT 1", $slug );
		$row   = $this->db->get_row( $query, \ARRAY_A );

		return is_array( $row ) ? $this->toModel( $row ) : null;
	}

	public function save( mixed $entity ): int {
		if ( ! $entity instanceof CECFM_CustomerType ) {
			throw new InvalidArgumentException( 'CustomerTypeRepository expects a CECFM_CustomerType entity.' );
		}

		$payload = array(
			'slug'        => $entity->slug,
			'label'       => $entity->label,
			'description' => $entity->description,
			'is_default'  => $entity->is_default ? 1 : 0,
			'priority'    => $entity->priority,
			'meta'        => $this->jsonEncode( $entity->meta ),
		);

		if ( $entity->id > 0 ) {
			$this->db->update( $this->table_name, $payload, array( 'id' => $entity->id ) );
			return $entity->id;
		}

		$payload['created_at'] = \current_time( 'mysql' );
		$this->db->insert( $this->table_name, $payload );

		return (int) $this->db->insert_id;
	}

	public function delete( int $id ): bool {
		return false !== $this->db->delete( $this->table_name, array( 'id' => $id ), array( '%d' ) );
	}

	public function getDefault(): ?CECFM_CustomerType {
		$query = $this->db->prepare( "SELECT * FROM {$this->table_name} WHERE is_default = %d ORDER BY priority ASC, id ASC LIMIT 1", 1 );
		$row   = $this->db->get_row( $query, \ARRAY_A );

		return is_array( $row ) ? $this->toModel( $row ) : null;
	}

	protected function toModel( array $row ): CECFM_CustomerType {
		if ( isset( $row['meta'] ) && is_string( $row['meta'] ) ) {
			$row['meta'] = $this->jsonDecode( $row['meta'] );
		}

		return CECFM_CustomerType::fromRow( $row );
	}
}


