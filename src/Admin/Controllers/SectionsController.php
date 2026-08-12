<?php
namespace CoderEmbassy\CheckoutFieldsManager\Admin\Controllers;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Models\CECFM_Section;
use CoderEmbassy\CheckoutFieldsManager\Modules\Licensing\FeatureGate;
use CoderEmbassy\CheckoutFieldsManager\Modules\Sections\SectionRepository;
use WP_REST_Request;
use WP_REST_Server;

class SectionsController extends BaseController {
	public function __construct( private SectionRepository $repository ) {
		$this->namespace = 'coderembassy-checkout-fields-manager/v1';
		$this->rest_base = 'sections';
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

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/lookup/products',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'lookupProducts' ),
					'permission_callback' => array( $this, 'permissionsCheck' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/lookup/categories',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'lookupCategories' ),
					'permission_callback' => array( $this, 'permissionsCheck' ),
				),
			)
		);
	}

	public function index() {
		return $this->success( array_map( array( $this, 'transform' ), $this->repository->findAll() ) );
	}

	public function create( WP_REST_Request $request ) {
		$data = $request->get_json_params() ?: array();
		$section = CECFM_Section::fromArray(
			array(
				'section_key'    => sanitize_key( (string) ( $data['section_key'] ?? '' ) ),
				'title'          => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
				'description'    => sanitize_text_field( (string) ( $data['description'] ?? '' ) ),
				'position'       => sanitize_key( (string) ( $data['position'] ?? 'before_order_notes' ) ),
				'priority'       => absint( $data['priority'] ?? 10 ),
				'enabled'        => ! array_key_exists( 'enabled', $data ) || (bool) $data['enabled'],
				'conditions'     => is_array( $data['conditions'] ?? null ) ? $data['conditions'] : array(),
				'customer_types' => is_array( $data['customer_types'] ?? null ) ? $data['customer_types'] : array(),
				'meta'           => is_array( $data['meta'] ?? null ) ? $data['meta'] : array(),
			)
		);

		// Free allows one section so the feature is visible and usable; the add-on
		// raises the ceiling. Enforced here as well as in the UI, because a
		// UI-only limit is bypassed by calling the REST route directly.
		$max = FeatureGate::maxSections();
		if ( count( $this->repository->findAll() ) >= $max ) {
			return $this->error(
				sprintf(
					/* translators: %d: maximum number of sections allowed on the current plan. */
					_n(
						'Your plan allows %d custom section. Upgrade to add more.',
						'Your plan allows %d custom sections. Upgrade to add more.',
						$max,
						'coderembassy-checkout-fields-manager'
					),
					$max
				),
				403
			);
		}

		$id = $this->repository->save( $section );
		$item = $this->repository->findById( $id );
		return $this->success( $item ? $this->transform( $item ) : array(), 201 );
	}

	public function update( WP_REST_Request $request ) {
		$section = $this->repository->findById( absint( $request['id'] ) );
		if ( ! $section instanceof CECFM_Section ) {
			return $this->error( 'Section not found.', 404 );
		}

		$data = $request->get_json_params() ?: array();
		$section->section_key    = isset( $data['section_key'] ) ? sanitize_key( (string) $data['section_key'] ) : $section->section_key;
		$section->title          = isset( $data['title'] ) ? sanitize_text_field( (string) $data['title'] ) : $section->title;
		$section->description    = isset( $data['description'] ) ? sanitize_text_field( (string) $data['description'] ) : $section->description;
		$section->position       = isset( $data['position'] ) ? sanitize_key( (string) $data['position'] ) : $section->position;
		$section->priority       = isset( $data['priority'] ) ? absint( $data['priority'] ) : $section->priority;
		$section->enabled        = array_key_exists( 'enabled', $data ) ? (bool) $data['enabled'] : $section->enabled;
		$section->conditions     = isset( $data['conditions'] ) && is_array( $data['conditions'] ) ? $data['conditions'] : $section->conditions;
		$section->customer_types = isset( $data['customer_types'] ) && is_array( $data['customer_types'] ) ? $data['customer_types'] : $section->customer_types;
		$section->meta           = isset( $data['meta'] ) && is_array( $data['meta'] ) ? $data['meta'] : $section->meta;

		$this->repository->save( $section );
		return $this->success( $this->transform( $section ) );
	}

	public function destroy( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		$ok = $this->repository->delete( $id );
		if ( ! $ok ) {
			return $this->error( 'Failed to delete section.', 500 );
		}

		return $this->success( array( 'deleted' => true, 'id' => $id ) );
	}

	public function lookupProducts( WP_REST_Request $request ) {
		$search = sanitize_text_field( (string) ( $request->get_param( 'search' ) ?? '' ) );
		$ids    = $request->get_param( 'ids' );
		$limit  = max( 1, min( 30, absint( $request->get_param( 'limit' ) ?? 20 ) ) );

		$post_in = array();
		if ( is_array( $ids ) ) {
			$post_in = array_values( array_filter( array_map( 'absint', $ids ) ) );
		} elseif ( is_string( $ids ) && '' !== $ids ) {
			$post_in = array_values(
				array_filter(
					array_map( 'absint', array_map( 'trim', explode( ',', $ids ) ) )
				)
			);
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => array( 'publish', 'private' ),
			'posts_per_page' => $limit,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'suppress_filters' => false,
		);

		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}
		if ( ! empty( $post_in ) ) {
			$args['post__in'] = $post_in;
			$args['orderby']  = 'post__in';
		}

		$posts   = get_posts( $args );
		$results = array();
		foreach ( $posts as $post_id ) {
			$results[] = array(
				'id'    => (int) $post_id,
				'label' => get_the_title( (int) $post_id ),
			);
		}

		return $this->success( $results );
	}

	public function lookupCategories( WP_REST_Request $request ) {
		$search = sanitize_text_field( (string) ( $request->get_param( 'search' ) ?? '' ) );
		$ids    = $request->get_param( 'ids' );
		$limit  = max( 1, min( 30, absint( $request->get_param( 'limit' ) ?? 20 ) ) );

		$include = array();
		if ( is_array( $ids ) ) {
			$include = array_values( array_filter( array_map( 'absint', $ids ) ) );
		} elseif ( is_string( $ids ) && '' !== $ids ) {
			$include = array_values(
				array_filter(
					array_map( 'absint', array_map( 'trim', explode( ',', $ids ) ) )
				)
			);
		}

		$args = array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'number'     => $limit,
			'orderby'    => 'name',
			'order'      => 'ASC',
		);
		if ( '' !== $search ) {
			$args['search'] = $search;
		}
		if ( ! empty( $include ) ) {
			$args['include'] = $include;
		}

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return $this->success( array() );
		}

		$results = array();
		foreach ( $terms as $term ) {
			$results[] = array(
				'id'    => (int) $term->term_id,
				'label' => (string) $term->name,
			);
		}

		return $this->success( $results );
	}

	private function transform( CECFM_Section $section ): array {
		return array(
			'id'             => $section->id,
			'section_key'    => $section->section_key,
			'title'          => $section->title,
			'description'    => $section->description,
			'position'       => $section->position,
			'priority'       => $section->priority,
			'enabled'        => $section->enabled,
			'conditions'     => $section->conditions,
			'customer_types' => $section->customer_types,
			'meta'           => $section->meta,
			'created_at'     => $section->created_at,
		);
	}
}


