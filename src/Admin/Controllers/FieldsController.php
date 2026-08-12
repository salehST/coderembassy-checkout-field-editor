<?php
namespace CoderEmbassy\CheckoutFieldsManager\Admin\Controllers;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Modules\Licensing\FeatureGate;
use CoderEmbassy\CheckoutFieldsManager\Models\CECFM_Field;
use CoderEmbassy\CheckoutFieldsManager\Modules\Fields\FieldRepository;
use CoderEmbassy\CheckoutFieldsManager\Modules\Fields\RevisionRepository;
use WP_REST_Request;
use WP_REST_Server;

class FieldsController extends BaseController {
	public function __construct(
		private FieldRepository $repository,
		private RevisionRepository $revisionRepository
	) {
		$this->namespace = 'coderembassy-checkout-fields-manager/v1';
		$this->rest_base = 'fields';
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'permissionsCheck' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create' ),
					'permission_callback' => array( $this, 'permissionsCheck' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( $this, 'permissionsCheck' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'permissionsCheck' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'destroy' ),
					'permission_callback' => array( $this, 'permissionsCheck' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/reorder',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reorder' ),
					'permission_callback' => array( $this, 'permissionsCheck' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/revisions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'revisions' ),
					'permission_callback' => array( $this, 'permissionsCheck' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/rollback/(?P<rev_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rollback' ),
					'permission_callback' => array( $this, 'permissionsCheck' ),
				),
			)
		);
	}

	public function index( WP_REST_Request $request ) {
		$filters = array();
		if ( null !== $request->get_param( 'section' ) ) {
			$filters['section'] = sanitize_key( (string) $request->get_param( 'section' ) );
		}
		if ( null !== $request->get_param( 'enabled' ) ) {
			$filters['enabled'] = rest_sanitize_boolean( $request->get_param( 'enabled' ) );
		}
		if ( null !== $request->get_param( 'customer_type' ) ) {
			$filters['customer_type'] = sanitize_key( (string) $request->get_param( 'customer_type' ) );
		}

		$fields = array_map( array( $this, 'transformField' ), $this->repository->findAll( $filters ) );
		return $this->success( $fields );
	}

	public function show( WP_REST_Request $request ) {
		$field = $this->repository->findById( absint( $request['id'] ) );
		if ( ! $field instanceof CECFM_Field ) {
			return $this->error( 'Field not found.', 404 );
		}

		return $this->success( $this->transformField( $field ) );
	}

	public function create( WP_REST_Request $request ) {
		$payload = $this->sanitizeFieldPayload( $request->get_json_params() ?: array() );

		if ( '' === (string) ( $payload['field_key'] ?? '' ) || '' === (string) ( $payload['label'] ?? '' ) ) {
			return $this->error( 'Field label and field key are required.', 422 );
		}

		// No pricing rules restrictions.

		// No field count limits.

		// No repeater restrictions.

		// No condition rules limits.

		$field = CECFM_Field::fromArray( $payload );

		try {
			$id = $this->repository->save( $field );
		} catch ( \Throwable $e ) {
			$message = 'Failed to create field.';
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$message .= ' ' . $e->getMessage();
			}
			return $this->error( $message, 500 );
		}

		$field = $this->repository->findById( $id );

		if ( ! $field instanceof CECFM_Field ) {
			return $this->error( 'Failed to create field.', 500 );
		}

		$this->revisionRepository->createRevision( 'field', $id, $this->transformField( $field ), get_current_user_id(), 'Field created' );

		return $this->success( $this->transformField( $field ), 201 );
	}

	public function update( WP_REST_Request $request ) {
		$field = $this->repository->findById( absint( $request['id'] ) );
		if ( ! $field instanceof CECFM_Field ) {
			return $this->error( 'Field not found.', 404 );
		}

		$payload = $this->sanitizeFieldPayload( $request->get_json_params() ?: array(), $field );

		// No restrictions.

		// No restrictions.

		$entity  = CECFM_Field::fromArray( array_merge( $this->transformField( $field ), $payload ) );
		$entity->id = $field->id;

		$id       = $this->repository->save( $entity );
		$updated  = $this->repository->findById( $id );
		if ( ! $updated instanceof CECFM_Field ) {
			return $this->error( 'Failed to update field.', 500 );
		}

		$this->revisionRepository->createRevision( 'field', $id, $this->transformField( $updated ), get_current_user_id(), 'Field updated' );
		return $this->success( $this->transformField( $updated ) );
	}

	public function destroy( WP_REST_Request $request ) {
		$field = $this->repository->findById( absint( $request['id'] ) );
		if ( ! $field instanceof CECFM_Field ) {
			return $this->error( 'Field not found.', 404 );
		}

		global $wpdb;
		$meta_key = '_cecfm_' . $field->field_key;
		$cache_key   = 'cecfm_postmeta_count_' . md5( $meta_key );
		$cache_group = 'coderembassy-checkout-fields-manager';
		$cached      = wp_cache_get( $cache_key, $cache_group );
		if ( false !== $cached ) {
			$count = (int) $cached;
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", $meta_key ) );
			wp_cache_set( $cache_key, $count, $cache_group, 5 * MINUTE_IN_SECONDS );
		}

		if ( $count > 0 ) {
			$field->enabled = false;
			$this->repository->save( $field );
			return $this->success(
				array(
					'action' => 'soft_delete',
					'id'     => $field->id,
				)
			);
		}

		$this->repository->delete( $field->id );
		return $this->success(
			array(
				'action' => 'hard_delete',
				'id'     => $field->id,
			)
		);
	}

	public function reorder( WP_REST_Request $request ) {
		$rows = $request->get_json_params();
		if ( is_array( $rows ) && isset( $rows['order'] ) && is_array( $rows['order'] ) ) {
			$rows = $rows['order'];
		}
		if ( ! is_array( $rows ) ) {
			return $this->error( 'Invalid reorder payload.', 422 );
		}

		$map = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['id'], $row['priority'] ) ) {
				continue;
			}
			$map[ absint( $row['id'] ) ] = absint( $row['priority'] );
		}

		$this->repository->reorder( $map );
		return $this->success( array( 'updated' => count( $map ) ) );
	}

	public function revisions( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		return $this->success( $this->revisionRepository->getRevisions( 'field', $id ) );
	}

	public function rollback( WP_REST_Request $request ) {

		$ok = $this->revisionRepository->rollback( absint( $request['rev_id'] ) );
		if ( ! $ok ) {
			return $this->error( 'Rollback failed.', 422 );
		}

		return $this->success( array( 'rolled_back' => true ) );
	}

	private function transformField( CECFM_Field $field ): array {
		return array(
			'id'                  => $field->id,
			'section_id'          => $field->section_id,
			'field_key'           => $field->field_key,
			'type'                => $field->type,
			'label'               => $field->label,
			'placeholder'         => $field->placeholder,
			'description'         => $field->description,
			'section'             => $field->section,
			'position'            => $field->position,
			'priority'            => $field->priority,
			'width'               => $field->width,
			'required'            => $field->required,
			'enabled'             => $field->enabled,
			'conditions'          => $field->conditions,
			'required_conditions' => $field->required_conditions,
			'customer_types'      => $field->customer_types,
			'pricing_rules'       => $field->pricing_rules,
			'validation_rules'    => $field->validation_rules,
			'options'             => $field->options,
			'meta'                => $field->meta,
			'created_at'          => $field->created_at,
			'updated_at'          => $field->updated_at,
		);
	}

	private function sanitizeFieldPayload( array $data, ?CECFM_Field $existing = null ): array {
		$base = $existing ? $this->transformField( $existing ) : array();

		foreach ( array( 'conditions', 'required_conditions', 'customer_types', 'pricing_rules', 'validation_rules', 'options', 'meta' ) as $array_key ) {
			if (
				isset( $data[ $array_key ] )
				&& ! is_array( $data[ $array_key ] )
				&& ! is_string( $data[ $array_key ] )
			) {
				$data[ $array_key ] = array();
			}
		}

		return array(
			'id'               => $existing?->id ?? 0,
			'section_id'       => isset( $data['section_id'] ) ? absint( $data['section_id'] ) : (int) ( $base['section_id'] ?? 0 ),
			'field_key'        => isset( $data['field_key'] ) ? sanitize_key( (string) $data['field_key'] ) : ( $base['field_key'] ?? '' ),
			'type'             => isset( $data['type'] ) ? sanitize_key( (string) $data['type'] ) : ( $base['type'] ?? '' ),
			'label'            => isset( $data['label'] ) ? sanitize_text_field( (string) $data['label'] ) : ( $base['label'] ?? '' ),
			'placeholder'      => isset( $data['placeholder'] ) ? sanitize_text_field( (string) $data['placeholder'] ) : ( $base['placeholder'] ?? '' ),
			'description'      => isset( $data['description'] ) ? sanitize_text_field( (string) $data['description'] ) : ( $base['description'] ?? '' ),
			'section'          => isset( $data['section'] ) ? sanitize_key( (string) $data['section'] ) : ( $base['section'] ?? 'billing' ),
			'position'         => isset( $data['position'] ) ? sanitize_key( (string) $data['position'] ) : ( $base['position'] ?? 'after_address' ),
			'priority'         => isset( $data['priority'] ) ? absint( $data['priority'] ) : ( $base['priority'] ?? 10 ),
			'width'            => isset( $data['width'] ) ? sanitize_key( (string) $data['width'] ) : ( $base['width'] ?? 'full' ),
			'required'         => array_key_exists( 'required', $data ) ? (bool) $data['required'] : (bool) ( $base['required'] ?? false ),
			'enabled'          => array_key_exists( 'enabled', $data ) ? (bool) $data['enabled'] : (bool) ( $base['enabled'] ?? true ),
			'conditions'          => $data['conditions'] ?? ( $base['conditions'] ?? array() ),
			'required_conditions' => $data['required_conditions'] ?? ( $base['required_conditions'] ?? array() ),
			'customer_types'      => $this->mergeCustomerTypes( $data, $base ),
			'pricing_rules'    => $data['pricing_rules'] ?? ( $base['pricing_rules'] ?? array() ),
			'validation_rules' => $data['validation_rules'] ?? ( $base['validation_rules'] ?? array() ),
			'options'          => $data['options'] ?? ( $base['options'] ?? array() ),
			'meta'             => $data['meta'] ?? ( $base['meta'] ?? array() ),
		);
	}

	/**
	 * Merge submitted customer types with ones this install cannot see.
	 *
	 * The free plugin only offers Private and Company, so its editor submits at
	 * most those two. Overwriting the stored list would silently discard types an
	 * add-on created — a store that downgrades, edits one field, and upgrades
	 * again would find its assignments gone. Anything outside the allowed set is
	 * therefore carried over untouched.
	 *
	 * @param array<string, mixed> $data Incoming payload.
	 * @param array<string, mixed> $base Currently stored field.
	 * @return array<int, string>
	 */
	private function mergeCustomerTypes( array $data, array $base ): array {
		$stored = is_array( $base['customer_types'] ?? null ) ? $base['customer_types'] : array();

		if ( ! array_key_exists( 'customer_types', $data ) ) {
			return $stored;
		}

		$submitted = is_array( $data['customer_types'] ) ? $data['customer_types'] : array();
		$allowed   = FeatureGate::allowedCustomerTypes();

		// The add-on manages every type, so the payload is authoritative.
		if ( null === $allowed ) {
			return array_values( array_unique( array_map( 'sanitize_key', $submitted ) ) );
		}

		$hidden = array_filter(
			$stored,
			static fn ( $slug ): bool => ! in_array( $slug, $allowed, true )
		);

		return array_values( array_unique( array_map( 'sanitize_key', array_merge( $submitted, $hidden ) ) ) );
	}
}


