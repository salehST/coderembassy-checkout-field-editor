<?php
namespace CoderEmbassy\CheckoutFieldEditor\Interfaces;

defined( 'ABSPATH' ) || exit;

/**
 * Generic repository contract for persistence adapters.
 */
interface RepositoryInterface {
	/**
	 * Find a single entity by numeric identifier.
	 *
	 * @param int $id Entity identifier.
	 * @return mixed Entity data, DTO, model instance, or null when not found.
	 */
	public function findById( int $id ): mixed;

	/**
	 * Persist an entity and return its identifier.
	 *
	 * @param mixed $entity Entity payload to persist.
	 * @return int Persisted entity identifier.
	 */
	public function save( mixed $entity ): int;

	/**
	 * Delete an entity by identifier.
	 *
	 * @param int $id Entity identifier.
	 * @return bool True when deletion succeeds.
	 */
	public function delete( int $id ): bool;
}
