<?php
namespace CoderEmbassy\CheckoutFieldEditor\Admin\Controllers;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API controller for managing native WooCommerce checkout field overrides.
 *
 * Overrides (label, placeholder, required, width, customer_types, enabled, priority)
 * are stored in wp_options as JSON. The CheckoutRenderer reads them at runtime and
 * applies them via the woocommerce_checkout_fields filter.
 *
 * IMPORTANT: We intentionally do NOT call WC()->checkout()->get_checkout_fields() here
 * because that applies woocommerce_checkout_fields filters — including our own — which
 * would cause disabled fields to disappear from the admin list.
 */
class NativeFieldsController extends BaseController {
	protected $rest_base = 'native-fields'; // phpcs:ignore

	private const OPTION_KEY = 'checkout_architect_native_fields';

	/** WooCommerce's built-in default widths for each checkout field. */
	private const NATIVE_WIDTHS = array(
		'billing_first_name'  => 'half_first',
		'billing_last_name'   => 'half_last',
		'shipping_first_name' => 'half_first',
		'shipping_last_name'  => 'half_last',
	);

	public function register_routes(): void {
		$ns = 'checkout-architect/v1';

		\register_rest_route(
			$ns,
			'/native-fields',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getFields' ),
					'permission_callback' => array( $this, 'permissionsCheck' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'saveFields' ),
					'permission_callback' => array( $this, 'permissionsCheck' ),
				),
			)
		);
	}

	public function getFields( WP_REST_Request $request ): WP_REST_Response {
		$defaults  = $this->getWcDefaultFields();
		$overrides = $this->getStoredOverrides();
		$result    = array();

		foreach ( $defaults as $group => $group_fields ) {
			foreach ( $group_fields as $key => $config ) {
				$ov = $overrides[ $key ] ?? array();

				// Detect native width from WC class array.
				$wc_classes   = is_array( $config['class'] ?? null ) ? $config['class'] : array();
				$native_width = 'full';
				if ( in_array( 'form-row-first', $wc_classes, true ) ) {
					$native_width = 'half_first';
				} elseif ( in_array( 'form-row-last', $wc_classes, true ) ) {
					$native_width = 'half_last';
				}

				$result[] = array(
					'field_key'      => $key,
					'group'          => $group,
					'type'           => $config['type'] ?? 'text',
					'label'          => isset( $ov['label'] ) && '' !== $ov['label'] ? $ov['label'] : ( $config['label'] ?? '' ),
					'placeholder'    => array_key_exists( 'placeholder', $ov ) ? $ov['placeholder'] : ( $config['placeholder'] ?? '' ),
					'required'       => isset( $ov['required'] ) ? (bool) $ov['required'] : ! empty( $config['required'] ),
					'enabled'        => ! isset( $ov['enabled'] ) || (bool) $ov['enabled'],
					'priority'       => isset( $ov['priority'] ) ? (int) $ov['priority'] : (int) ( $config['priority'] ?? 10 ),
					'width'          => $ov['width'] ?? $native_width,
					'customer_types' => $ov['customer_types'] ?? array(),
				);
			}
		}

		\usort( $result, static fn( $a, $b ) => strcmp( $a['group'], $b['group'] ) ?: ( $a['priority'] <=> $b['priority'] ) );

		return $this->success( $result );
	}

	public function saveFields( WP_REST_Request $request ): WP_REST_Response {
		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			return $this->error( 'Invalid payload.', 400 );
		}

		$overrides = array();
		foreach ( $input as $field ) {
			$key = \sanitize_key( (string) ( $field['field_key'] ?? '' ) );
			if ( '' === $key ) {
				continue;
			}

			$customer_types = isset( $field['customer_types'] ) && is_array( $field['customer_types'] )
				? array_values( array_map( 'sanitize_key', $field['customer_types'] ) )
				: array();

			$width = \sanitize_key( (string) ( $field['width'] ?? 'full' ) );
			if ( ! in_array( $width, array( 'full', 'half_first', 'half_last' ), true ) ) {
				$width = 'full';
			}

			$overrides[ $key ] = array(
				'enabled'        => isset( $field['enabled'] ) ? (bool) $field['enabled'] : true,
				'label'          => \sanitize_text_field( (string) ( $field['label'] ?? '' ) ),
				'placeholder'    => \sanitize_text_field( (string) ( $field['placeholder'] ?? '' ) ),
				'required'       => isset( $field['required'] ) ? (bool) $field['required'] : false,
				'priority'       => isset( $field['priority'] ) ? (int) $field['priority'] : 10,
				'customer_types' => $customer_types,
			);

			// Only persist width when the user explicitly changed it from WC's native default.
			// This prevents old saves (which defaulted every field to 'full') from overriding
			// the natural half-width pairs like billing_first_name / billing_last_name.
			$native_w = self::NATIVE_WIDTHS[ $key ] ?? 'full';
			if ( $width !== $native_w ) {
				$overrides[ $key ]['width'] = $width;
			}
		}

		\update_option( self::OPTION_KEY, \wp_json_encode( $overrides ) );

		return $this->success( array( 'saved' => true ) );
	}

	private function getStoredOverrides(): array {
		$raw = \get_option( self::OPTION_KEY, '{}' );
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			return is_array( $decoded ) ? $decoded : array();
		}

		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Returns the hardcoded WooCommerce default fields.
	 *
	 * We deliberately do NOT call WC()->checkout()->get_checkout_fields() here
	 * because that applies our own woocommerce_checkout_fields filter, which
	 * would remove already-disabled fields from the admin list.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	private function getWcDefaultFields(): array {
		return array(
			'billing'  => array(
				'billing_first_name' => array( 'label' => \__( 'First name', 'coderembassy-checkout-field-editor' ),                                          'placeholder' => '',                                                                            'required' => true,  'type' => 'text',     'class' => array( 'form-row-first' ), 'priority' => 10  ),
				'billing_last_name'  => array( 'label' => \__( 'Last name', 'coderembassy-checkout-field-editor' ),                                           'placeholder' => '',                                                                            'required' => true,  'type' => 'text',     'class' => array( 'form-row-last' ),  'priority' => 20  ),
				'billing_company'    => array( 'label' => \__( 'Company name', 'coderembassy-checkout-field-editor' ),                                        'placeholder' => '',                                                                            'required' => false, 'type' => 'text',     'class' => array( 'form-row-wide' ),  'priority' => 30  ),
				'billing_country'    => array( 'label' => \__( 'Country / Region', 'coderembassy-checkout-field-editor' ),                                    'placeholder' => '',                                                                            'required' => true,  'type' => 'country',  'class' => array( 'form-row-wide' ),  'priority' => 40  ),
				'billing_address_1'  => array( 'label' => \__( 'Street address', 'coderembassy-checkout-field-editor' ),                                      'placeholder' => \__( 'House number and street name', 'coderembassy-checkout-field-editor' ),                         'required' => true,  'type' => 'text',     'class' => array( 'form-row-wide' ),  'priority' => 50  ),
				'billing_address_2'  => array( 'label' => \__( 'Apartment, suite, unit, etc.', 'coderembassy-checkout-field-editor' ),                        'placeholder' => \__( 'Apartment, suite, unit, etc. (optional)', 'coderembassy-checkout-field-editor' ),               'required' => false, 'type' => 'text',     'class' => array( 'form-row-wide' ),  'priority' => 60  ),
				'billing_city'       => array( 'label' => \__( 'Town / City', 'coderembassy-checkout-field-editor' ),                                         'placeholder' => '',                                                                            'required' => true,  'type' => 'text',     'class' => array( 'form-row-wide' ),  'priority' => 70  ),
				'billing_state'      => array( 'label' => \__( 'State / County', 'coderembassy-checkout-field-editor' ),                                      'placeholder' => '',                                                                            'required' => true,  'type' => 'state',    'class' => array( 'form-row-wide' ),  'priority' => 80  ),
				'billing_postcode'   => array( 'label' => \__( 'Postcode / ZIP', 'coderembassy-checkout-field-editor' ),                                      'placeholder' => '',                                                                            'required' => true,  'type' => 'text',     'class' => array( 'form-row-wide' ),  'priority' => 90  ),
				'billing_phone'      => array( 'label' => \__( 'Phone', 'coderembassy-checkout-field-editor' ),                                               'placeholder' => '',                                                                            'required' => true,  'type' => 'tel',      'class' => array( 'form-row-wide' ),  'priority' => 100 ),
				'billing_email'      => array( 'label' => \__( 'Email address', 'coderembassy-checkout-field-editor' ),                                       'placeholder' => '',                                                                            'required' => true,  'type' => 'email',    'class' => array( 'form-row-wide' ),  'priority' => 110 ),
			),
			'shipping' => array(
				'shipping_first_name' => array( 'label' => \__( 'First name', 'coderembassy-checkout-field-editor' ),                                         'placeholder' => '',                                                                            'required' => true,  'type' => 'text',     'class' => array( 'form-row-first' ), 'priority' => 10  ),
				'shipping_last_name'  => array( 'label' => \__( 'Last name', 'coderembassy-checkout-field-editor' ),                                          'placeholder' => '',                                                                            'required' => true,  'type' => 'text',     'class' => array( 'form-row-last' ),  'priority' => 20  ),
				'shipping_company'    => array( 'label' => \__( 'Company name', 'coderembassy-checkout-field-editor' ),                                       'placeholder' => '',                                                                            'required' => false, 'type' => 'text',     'class' => array( 'form-row-wide' ),  'priority' => 30  ),
				'shipping_country'    => array( 'label' => \__( 'Country / Region', 'coderembassy-checkout-field-editor' ),                                   'placeholder' => '',                                                                            'required' => false, 'type' => 'country',  'class' => array( 'form-row-wide' ),  'priority' => 40  ),
				'shipping_address_1'  => array( 'label' => \__( 'Street address', 'coderembassy-checkout-field-editor' ),                                     'placeholder' => \__( 'House number and street name', 'coderembassy-checkout-field-editor' ),                         'required' => true,  'type' => 'text',     'class' => array( 'form-row-wide' ),  'priority' => 50  ),
				'shipping_address_2'  => array( 'label' => \__( 'Apartment, suite, unit, etc.', 'coderembassy-checkout-field-editor' ),                       'placeholder' => \__( 'Apartment, suite, unit, etc. (optional)', 'coderembassy-checkout-field-editor' ),               'required' => false, 'type' => 'text',     'class' => array( 'form-row-wide' ),  'priority' => 60  ),
				'shipping_city'       => array( 'label' => \__( 'Town / City', 'coderembassy-checkout-field-editor' ),                                        'placeholder' => '',                                                                            'required' => true,  'type' => 'text',     'class' => array( 'form-row-wide' ),  'priority' => 70  ),
				'shipping_state'      => array( 'label' => \__( 'State / County', 'coderembassy-checkout-field-editor' ),                                     'placeholder' => '',                                                                            'required' => false, 'type' => 'state',    'class' => array( 'form-row-wide' ),  'priority' => 80  ),
				'shipping_postcode'   => array( 'label' => \__( 'Postcode / ZIP', 'coderembassy-checkout-field-editor' ),                                     'placeholder' => '',                                                                            'required' => false, 'type' => 'text',     'class' => array( 'form-row-wide' ),  'priority' => 90  ),
			),
			'order'    => array(
				'order_comments' => array( 'label' => \__( 'Order notes', 'coderembassy-checkout-field-editor' ), 'placeholder' => \__( 'Notes about your order, e.g. special notes for delivery.', 'coderembassy-checkout-field-editor' ), 'required' => false, 'type' => 'textarea', 'class' => array( 'form-row-wide' ), 'priority' => 10 ),
			),
		);
	}
}
