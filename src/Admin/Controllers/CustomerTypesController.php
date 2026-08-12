<?php
namespace CoderEmbassy\CheckoutFieldsManager\Admin\Controllers;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Models\CECFM_CustomerType;
use CoderEmbassy\CheckoutFieldsManager\Modules\Licensing\FeatureGate;
use CoderEmbassy\CheckoutFieldsManager\Modules\CustomerTypes\CustomerTypeRepository;
use WP_REST_Request;
use WP_REST_Server;

class CustomerTypesController extends BaseController {
	public function __construct( private CustomerTypeRepository $repository ) {
		$this->namespace = 'coderembassy-checkout-fields-manager/v1';
		$this->rest_base = 'customer-types';
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
			'/' . $this->rest_base . '/company-toggle',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'toggle_company' ),
					'permission_callback' => array( $this, 'permissionsCheck' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
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
	}

	public function index() {
		$types   = $this->repository->findAll();
		$allowed = FeatureGate::allowedCustomerTypes();

		// Without the add-on, only the built-in pair is offered. Types it created
		// stay in the database untouched — they are just not listed, so the field
		// editor never shows a type this install cannot manage.
		if ( null !== $allowed ) {
			$types = array_values(
				array_filter(
					$types,
					static fn ( $type ): bool => in_array( $type->slug, $allowed, true )
				)
			);
		}

		return $this->success( array_map( array( $this, 'transform' ), $types ) );
	}

	/**
	 * Add or remove the built-in `company` customer type (used by the free admin UI toggle).
	 */
	public function toggle_company( WP_REST_Request $request ) {
		$params  = $request->get_json_params() ?: array();
		$enabled = array_key_exists( 'enabled', $params ) ? (bool) $params['enabled'] : false;

		if ( $enabled ) {
			$existing = $this->repository->findBySlug( 'company' );
			if ( $existing instanceof CECFM_CustomerType ) {
				return $this->success(
					array(
						'enabled' => true,
						'type'    => $this->transform( $existing ),
					)
				);
			}

			$type = CECFM_CustomerType::fromArray(
				array(
					'slug'        => 'company',
					'label'       => \__( 'Company', 'coderembassy-checkout-fields-manager' ),
					'description' => \__( 'Business or organization customer.', 'coderembassy-checkout-fields-manager' ),
					'is_default'  => false,
					'priority'    => 20,
					'meta'        => array(),
				)
			);
			$id   = $this->repository->save( $type );
			$item = $this->repository->findById( $id );

			return $this->success(
				array(
					'enabled' => true,
					'type'    => $item instanceof CECFM_CustomerType ? $this->transform( $item ) : null,
				)
			);
		}

		$company = $this->repository->findBySlug( 'company' );
		if ( ! $company instanceof CECFM_CustomerType ) {
			return $this->success( array( 'enabled' => false ) );
		}

		$all = $this->repository->findAll();
		if ( count( $all ) <= 1 ) {
			return $this->error( 'At least one customer type is required.', 422 );
		}

		$ok = $this->repository->delete( $company->id );
		if ( ! $ok ) {
			return $this->error( 'Failed to remove company type.', 500 );
		}

		return $this->success( array( 'enabled' => false ) );
	}

	public function create( WP_REST_Request $request ) {
		// No limits on customer types.

		$data = $request->get_json_params() ?: array();
		$type = CECFM_CustomerType::fromArray(
			array(
				'slug'        => sanitize_key( (string) ( $data['slug'] ?? '' ) ),
				'label'       => sanitize_text_field( (string) ( $data['label'] ?? '' ) ),
				'description' => sanitize_text_field( (string) ( $data['description'] ?? '' ) ),
				'is_default'  => ! empty( $data['is_default'] ),
				'priority'    => absint( $data['priority'] ?? 10 ),
				'meta'        => is_array( $data['meta'] ?? null ) ? $data['meta'] : array(),
			)
		);
		$id   = $this->repository->save( $type );
		$item = $this->repository->findById( $id );

		return $this->success( $item ? $this->transform( $item ) : array(), 201 );
	}

	public function update( WP_REST_Request $request ) {
		$current = $this->repository->findById( absint( $request['id'] ) );
		if ( ! $current instanceof CECFM_CustomerType ) {
			return $this->error( 'Customer type not found.', 404 );
		}

		$data = $request->get_json_params() ?: array();
		$current->slug        = isset( $data['slug'] ) ? sanitize_key( (string) $data['slug'] ) : $current->slug;
		$current->label       = isset( $data['label'] ) ? sanitize_text_field( (string) $data['label'] ) : $current->label;
		$current->description = isset( $data['description'] ) ? sanitize_text_field( (string) $data['description'] ) : $current->description;
		$current->is_default  = array_key_exists( 'is_default', $data ) ? (bool) $data['is_default'] : $current->is_default;
		$current->priority    = isset( $data['priority'] ) ? absint( $data['priority'] ) : $current->priority;
		$current->meta        = isset( $data['meta'] ) && is_array( $data['meta'] ) ? $data['meta'] : $current->meta;

		$this->repository->save( $current );

		return $this->success( $this->transform( $current ) );
	}

	public function destroy( WP_REST_Request $request ) {
		$all = $this->repository->findAll();
		if ( count( $all ) <= 1 ) {
			return $this->error( 'At least one customer type is required.', 422 );
		}

		$id = absint( $request['id'] );
		$ok = $this->repository->delete( $id );
		if ( ! $ok ) {
			return $this->error( 'Failed to delete customer type.', 500 );
		}

		return $this->success( array( 'deleted' => true, 'id' => $id ) );
	}

	private function transform( CECFM_CustomerType $type ): array {
		return array(
			'id'          => $type->id,
			'slug'        => $type->slug,
			'label'       => $type->label,
			'description' => $type->description,
			'is_default'  => $type->is_default,
			'priority'    => $type->priority,
			'meta'        => $type->meta,
			'created_at'  => $type->created_at,
		);
	}
}


