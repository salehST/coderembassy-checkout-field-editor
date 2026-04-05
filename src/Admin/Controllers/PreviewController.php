<?php
namespace CoderEmbassy\CheckoutFieldEditor\Admin\Controllers;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldEditor\Modules\Conditions\ConditionEngine;
use CoderEmbassy\CheckoutFieldEditor\Modules\Fields\FieldRepository;
use CoderEmbassy\CheckoutFieldEditor\Modules\Fields\VisibilityResolver;
use WP_REST_Request;
use WP_REST_Server;

class PreviewController extends BaseController {
	public function __construct(
		private FieldRepository $fieldRepository,
		private VisibilityResolver $resolver,
		private ConditionEngine $conditionEngine
	) {
		$this->namespace = 'checkout-architect/v1';
		$this->rest_base = 'preview';
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'preview' ),
					'permission_callback' => array( $this, 'permissionsCheck' ),
				),
			)
		);
	}

	public function preview( WP_REST_Request $request ) {
		$data    = $request->get_json_params() ?: array();
		$context = $this->conditionEngine->buildContext();
		$context['customer_type']   = sanitize_key( (string) ( $data['customer_type'] ?? $context['customer_type'] ?? '' ) );
		$context['cart_total']      = isset( $data['cart_total'] ) ? (float) $data['cart_total'] : (float) ( $context['cart_total'] ?? 0 );
		$context['country']         = sanitize_key( (string) ( $data['country'] ?? $context['country'] ?? '' ) );
		$context['user_role']       = sanitize_key( (string) ( $data['user_role'] ?? $context['user_role'] ?? '' ) );
		$context['payment_method']  = sanitize_key( (string) ( $data['payment_method'] ?? $context['payment_method'] ?? '' ) );
		$context['shipping_method'] = sanitize_text_field( (string) ( $data['shipping_method'] ?? $context['shipping_method'] ?? '' ) );
		$context['coupon_codes']    = isset( $data['coupon_codes'] ) && is_array( $data['coupon_codes'] ) ? array_map( 'sanitize_text_field', $data['coupon_codes'] ) : array();

		// In preview mode, show ALL fields (enabled or not) so the user can audit everything.
		$fields         = $this->fieldRepository->findAll();
		$has_type_filter = '' !== $context['customer_type'];
		$result          = array();

		foreach ( $fields as $field ) {
			$groups = isset( $field->conditions['groups'] ) && is_array( $field->conditions['groups'] )
				? count( $field->conditions['groups'] )
				: 0;

			if ( $has_type_filter ) {
				// A specific customer type is selected — run full visibility logic.
				$visible  = $this->resolver->isFieldVisible( $field, $context );
				$required = $visible ? $this->resolver->isFieldRequired( $field, $context ) : false;
			} else {
				// No customer type selected: skip type-based filtering so all fields
				// are visible regardless of their customer_types restriction.
				// Still evaluate condition groups so the user can see conditional logic.
				$cond_pass = $groups === 0
					|| $this->conditionEngine->evaluate( $field->conditions, $context );
				$visible  = $cond_pass;
				$required = $visible && $field->required;
			}

			$result[] = array(
				'field_key'          => $field->field_key,
				'label'              => $field->label,
				'type'               => $field->type,
				'section'            => $field->section,
				'enabled'            => $field->enabled,
				'visible'            => $visible,
				'required'           => $required,
				'customer_types'     => $field->customer_types,
				'conditions_count'   => $groups,
			);
		}

		return $this->success( $result );
	}
}
