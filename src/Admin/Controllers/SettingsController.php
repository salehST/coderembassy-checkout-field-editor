<?php
namespace CoderEmbassy\CheckoutFieldsManager\Admin\Controllers;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Server;

class SettingsController extends BaseController {
	private const OPTION_KEY = 'cecfm_settings';

	/**
	 * Legacy key kept in sync with its replacement, for installs saved before
	 * the rename.
	 */
	private const ALIASES = array( 'hide_selector_for_single_type' => 'hide_billing_if_single' );

	public function __construct() {
		$this->namespace = 'coderembassy-checkout-fields-manager/v1';
		$this->rest_base = 'settings';
	}

	/**
	 * Every setting, as `key => array( type, default )`.
	 *
	 * This single list drives defaults, sanitising and what survives a save, so
	 * a key cannot be honoured by the front end yet silently dropped on write —
	 * declaring it here is all it takes.
	 *
	 * Types: bool, key, text, textarea, raw, hex, absint, absint_or_empty.
	 *
	 * @return array<string, array{0: string, 1: mixed}>
	 */
	public static function schema(): array {
		$schema = array(
			// General.
			'remove_data_on_uninstall'       => array( 'bool', false ),
			'enable_blocks_support'          => array( 'bool', true ),
			'enable_for_all_users'           => array( 'bool', true ),
			'show_on_thankyou_page'          => array( 'bool', true ),
			'hide_billing_if_single'         => array( 'bool', true ),
			'hide_selector_for_single_type'  => array( 'bool', true ),
			'show_optional_label'            => array( 'bool', true ),
			'save_type_to_user_meta'         => array( 'bool', true ),
			'order_review_show_thumbs'       => array( 'bool', false ),
			'order_review_show_quantity'     => array( 'bool', false ),

			// Add-on capabilities. Declared here because the front end in this
			// plugin reads them, even though only the add-on exposes controls.
			'enable_pricing'                 => array( 'bool', true ),
			'enable_revision_history'        => array( 'bool', true ),
			'enable_preview_mode'            => array( 'bool', false ),

			// Advanced.
			'log_field_errors'               => array( 'bool', false ),
			'custom_css'                     => array( 'raw', '' ),

			// Display.
			'currency_position'              => array( 'key', 'before' ),
			'date_format'                    => array( 'text', 'Y-m-d' ),

			// Headings.
			'billing_heading'                => array( 'text', 'Billing details' ),
			'shipping_heading'               => array( 'text', 'Shipping address' ),
			'order_notes_label'              => array( 'text', 'Order notes' ),
			'email_section_heading'          => array( 'text', '' ),

			// Address and autofill.
			'address_format_overrides'       => array( 'textarea', '' ),
			'custom_billing_address_keys'    => array( 'text', '' ),
			'custom_shipping_address_keys'   => array( 'text', '' ),
			'enable_google_address_autofill' => array( 'bool', false ),
			'google_maps_api_key'            => array( 'text', '' ),

			// Customer type switcher.
			'customer_type_switcher_label'   => array( 'text', 'Customer Type' ),
			'customer_type_position'         => array( 'key', 'before_billing' ),
			'type_selector_style'            => array( 'key', 'radio' ),
			'switcher_display_type'          => array( 'text', 'buttons' ),
			'switcher_position'              => array( 'text', 'before_form' ),
			'switcher_bg_color'              => array( 'hex', '' ),
			'switcher_active_color'          => array( 'hex', '' ),
			'switcher_border_color'          => array( 'hex', '' ),
			'switcher_font_color'            => array( 'hex', '' ),
			'switcher_border_radius'         => array( 'absint', '8' ),
			'switcher_font_size'             => array( 'absint_or_empty', '' ),
			'switcher_padding'               => array( 'text', '' ),
			'switcher_margin'                => array( 'text', '' ),
			'switcher_shadow'                => array( 'bool', false ),
		);

		/**
		 * Register additional settings.
		 *
		 * An add-on stores its settings in this plugin's option rather than one
		 * of its own, so it must declare its keys here or they are dropped on
		 * save. Same shape as above: `key => array( type, default )`.
		 *
		 * @param array<string, array{0: string, 1: mixed}> $schema
		 */
		$schema = (array) \apply_filters( 'cecfm_settings_schema', $schema );

		return array_filter(
			$schema,
			static fn( $spec ) => is_array( $spec ) && array_key_exists( 0, $spec ) && array_key_exists( 1, $spec )
		);
	}

	/** @return array<string, mixed> */
	public static function defaults(): array {
		return array_map( static fn( array $spec ) => $spec[1], self::schema() );
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
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
			)
		);
	}

	public function show() {
		return $this->success( $this->getSettings() );
	}

	public function update( WP_REST_Request $request ) {
		$current  = $this->getSettings();
		$input    = $request->get_json_params() ?: array();
		$settings = array();

		foreach ( self::schema() as $key => list( $type, $default ) ) {
			// Booleans need array_key_exists: an unchecked box arrives as false,
			// which isset() would treat as "not sent" and silently keep the old
			// value, making toggles impossible to switch off.
			$sent = 'bool' === $type || 'absint_or_empty' === $type
				? array_key_exists( $key, $input )
				: isset( $input[ $key ] );

			$settings[ $key ] = $sent
				? self::sanitise( $type, $input[ $key ] )
				: ( $current[ $key ] ?? $default );
		}

		foreach ( self::ALIASES as $legacy => $canonical ) {
			if ( isset( $settings[ $canonical ] ) ) {
				$settings[ $legacy ] = $settings[ $canonical ];
			}
		}

		update_option( self::OPTION_KEY, wp_json_encode( $settings ) );

		return $this->success( $settings );
	}

	/**
	 * @param  mixed $value
	 * @return mixed
	 */
	private static function sanitise( string $type, $value ) {
		switch ( $type ) {
			case 'bool':
				return (bool) $value;
			case 'key':
				return sanitize_key( (string) $value );
			case 'textarea':
				return sanitize_textarea_field( (string) $value );
			case 'hex':
				return \sanitize_hex_color( (string) $value );
			case 'absint':
				return absint( $value );
			case 'absint_or_empty':
				return '' === (string) $value ? '' : absint( $value );
			case 'raw':
				return (string) $value;
			case 'text':
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/** @return array<string, mixed> */
	private function getSettings(): array {
		$defaults = self::defaults();

		$raw     = get_option( self::OPTION_KEY, '' );
		$decoded = null;

		if ( is_string( $raw ) && '' !== $raw ) {
			$maybe = json_decode( $raw, true );
			if ( is_array( $maybe ) ) {
				$decoded = $maybe;
			}
		} elseif ( is_array( $raw ) ) {
			$decoded = $raw;
		}

		if ( null === $decoded ) {
			return $defaults;
		}

		// Carry a value saved under the old key across to the new one.
		foreach ( self::ALIASES as $legacy => $canonical ) {
			if ( isset( $decoded[ $legacy ] ) && ! isset( $decoded[ $canonical ] ) ) {
				$decoded[ $canonical ] = (bool) $decoded[ $legacy ];
			}
		}

		return array_merge( $defaults, $decoded );
	}
}
