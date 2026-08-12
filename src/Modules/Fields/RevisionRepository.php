<?php
namespace CoderEmbassy\CheckoutFieldsManager\Modules\Fields;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Abstracts\AbstractRepository;

class RevisionRepository extends AbstractRepository {
	public function __construct( \wpdb $db ) {
		parent::__construct( $db );
		$this->table_name = $this->db->prefix . 'cecfm_revisions';
	}

	public function findById( int $id ): mixed {
		return parent::findById( $id );
	}

	public function save( mixed $entity ): int {
		$entity_type = (string) ( $entity['entity_type'] ?? '' );
		$entity_id   = (int) ( $entity['entity_id'] ?? 0 );
		$snapshot    = is_array( $entity['snapshot'] ?? null ) ? $entity['snapshot'] : array();
		$user_id     = (int) ( $entity['changed_by'] ?? 0 );
		$note        = (string) ( $entity['change_note'] ?? '' );

		return $this->createRevision( $entity_type, $entity_id, $snapshot, $user_id, $note );
	}

	public function delete( int $id ): bool {
		return false !== $this->db->delete( $this->table_name, array( 'id' => $id ), array( '%d' ) );
	}

	public function createRevision( string $entity_type, int $entity_id, array $snapshot, int $user_id, string $note = '' ): int {
		$version_query = $this->db->prepare(
			"SELECT MAX(version) FROM {$this->table_name} WHERE entity_type = %s AND entity_id = %d",
			$entity_type,
			$entity_id
		);
		$max_version   = (int) $this->db->get_var( $version_query );
		$next_version  = max( 1, $max_version + 1 );

		$this->db->insert(
			$this->table_name,
			array(
				'entity_type' => $entity_type,
				'entity_id'   => $entity_id,
				'version'     => $next_version,
				'snapshot'    => $this->jsonEncode( $snapshot ),
				'changed_by'  => $user_id,
				'change_note' => $note,
				'created_at'  => \current_time( 'mysql' ),
			)
		);

		return (int) $this->db->insert_id;
	}

	public function getRevisions( string $entity_type, int $entity_id ): array {
		$query = $this->db->prepare(
			"SELECT * FROM {$this->table_name} WHERE entity_type = %s AND entity_id = %d ORDER BY version DESC, id DESC",
			$entity_type,
			$entity_id
		);
		$rows  = $this->db->get_results( $query, \ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		foreach ( $rows as &$row ) {
			if ( isset( $row['snapshot'] ) && is_string( $row['snapshot'] ) ) {
				$row['snapshot'] = $this->jsonDecode( $row['snapshot'] );
			}
		}
		unset( $row );

		return $rows;
	}

	public function rollback( int $revision_id ): bool {
		return $revision_id > 0;
	}
}


