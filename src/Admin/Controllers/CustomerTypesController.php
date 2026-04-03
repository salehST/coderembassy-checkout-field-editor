<?php
namespace CoderEmbassy\CheckoutFieldEditor\Admin\Controllers;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldEditor\Models\CA_CustomerType;
use CoderEmbassy\CheckoutFieldEditor\Modules\CustomerTypes\CustomerTypeRepository;
use WP_REST_Request;
use WP_REST_Server;

class CustomerTypesController extends BaseController {
	public function __construct( private CustomerTypeRepository $repository ) {
		$this->namespace = 'checkout-architect/v1';
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
		return $this->success( array_map( array( $this, 'transform' ), $this->repository->findAll() ) );
	}

	public function create( WP_REST_Request $request ) {
		$existing_count = count( $this->repository->findAll() ?? array() );
		if ( $existing_count >= 1 && ! \CoderEmbassy\CheckoutFieldEditor\Modules\Licensing\FeatureGate::can(
			\CoderEmbassy\CheckoutFieldEditor\Modules\Licensing\FeatureGate::CUSTOMER_TYPES_UNLIMITED
		) ) {
			return $this->error( 'The free plan supports 1 customer type. Upgrade to Pro for unlimited types.', 403 );
		}

		$data = $request->get_json_params() ?: array();
		$type = CA_CustomerType::fromArray(
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
		if ( ! $current instanceof CA_CustomerType ) {
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

	private function transform( CA_CustomerType $type ): array {
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
