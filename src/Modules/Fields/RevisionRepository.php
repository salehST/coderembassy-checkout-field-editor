<?php
namespace CoderEmbassy\CheckoutFieldsManager\Modules\Fields;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Abstracts\AbstractRepository;

class RevisionRepository extends AbstractRepository {
	/** Revisions retained per entity before the oldest are dropped. */
	public const KEEP_REVISIONS = 20;

	/** Filter name for overriding that cap. */
	public const PRUNE_HOOK = 'cecfm_keep_revisions';

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

		$insert_id = (int) $this->db->insert_id;

		$this->pruneRevisions( $entity_type, $entity_id );

		return $insert_id;
	}

	/**
	 * Keep only the most recent revisions for one entity.
	 *
	 * Every save writes a snapshot, so without a cap the table grows for the
	 * life of the store and the history query slows down with it.
	 */
	private function pruneRevisions( string $entity_type, int $entity_id ): void {
		/**
		 * How many revisions to keep per entity. Zero or less keeps all of them.
		 *
		 * @param int    $limit
		 * @param string $entity_type
		 * @param int    $entity_id
		 */
		$limit = (int) \apply_filters( self::PRUNE_HOOK, self::KEEP_REVISIONS, $entity_type, $entity_id );

		if ( $limit < 1 ) {
			return;
		}

		// Find the newest id we are keeping, then drop everything older. Doing
		// it by id avoids a DELETE with LIMIT, which MySQL rejects alongside a
		// subquery on the same table.
		$cutoff_query = $this->db->prepare(
			"SELECT id FROM {$this->table_name} WHERE entity_type = %s AND entity_id = %d ORDER BY version DESC, id DESC LIMIT %d, 1",
			$entity_type,
			$entity_id,
			$limit - 1
		);

		$cutoff_id = (int) $this->db->get_var( $cutoff_query );

		if ( $cutoff_id < 1 ) {
			return;
		}

		$this->db->query(
			$this->db->prepare(
				"DELETE FROM {$this->table_name} WHERE entity_type = %s AND entity_id = %d AND id < %d",
				$entity_type,
				$entity_id,
				$cutoff_id
			)
		);
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


