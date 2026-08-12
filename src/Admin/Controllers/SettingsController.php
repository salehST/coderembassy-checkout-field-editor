<?php
namespace CoderEmbassy\CheckoutFieldsManager\Admin\Controllers;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Server;

class SettingsController extends BaseController {
	private const OPTION_KEY = 'cecfm_settings';

	public function __construct() {
		$this->namespace = 'coderembassy-checkout-fields-manager/v1';
		$this->rest_base = 'settings';
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
		$current = $this->getSettings();
		$input   = $request->get_json_params() ?: array();

		$settings = array(
			'remove_data_on_uninstall'   => array_key_exists( 'remove_data_on_uninstall', $input ) ? (bool) $input['remove_data_on_uninstall'] : $current['remove_data_on_uninstall'],
			'enable_blocks_support'      => array_key_exists( 'enable_blocks_support', $input ) ? (bool) $input['enable_blocks_support'] : $current['enable_blocks_support'],
			'customer_type_position'     => isset( $input['customer_type_position'] ) ? sanitize_key( (string) $input['customer_type_position'] ) : $current['customer_type_position'],
			'type_selector_style'        => isset( $input['type_selector_style'] ) ? sanitize_key( (string) $input['type_selector_style'] ) : $current['type_selector_style'],
			'hide_selector_for_single_type' => array_key_exists( 'hide_selector_for_single_type', $input ) ? (bool) $input['hide_selector_for_single_type'] : $current['hide_selector_for_single_type'],
			'save_type_to_user_meta'     => array_key_exists( 'save_type_to_user_meta', $input ) ? (bool) $input['save_type_to_user_meta'] : $current['save_type_to_user_meta'],
			'enable_pricing'             => array_key_exists( 'enable_pricing', $input ) ? (bool) $input['enable_pricing'] : $current['enable_pricing'],
			'enable_revision_history'    => array_key_exists( 'enable_revision_history', $input ) ? (bool) $input['enable_revision_history'] : $current['enable_revision_history'],
			'enable_for_all_users'       => array_key_exists( 'enable_for_all_users', $input ) ? (bool) $input['enable_for_all_users'] : $current['enable_for_all_users'],
			'show_on_thankyou_page'      => array_key_exists( 'show_on_thankyou_page', $input ) ? (bool) $input['show_on_thankyou_page'] : $current['show_on_thankyou_page'],
			'hide_billing_if_single'     => array_key_exists( 'hide_billing_if_single', $input ) ? (bool) $input['hide_billing_if_single'] : $current['hide_billing_if_single'],
			'log_field_errors'           => array_key_exists( 'log_field_errors', $input ) ? (bool) $input['log_field_errors'] : $current['log_field_errors'],
			'enable_preview_mode'        => array_key_exists( 'enable_preview_mode', $input ) ? (bool) $input['enable_preview_mode'] : $current['enable_preview_mode'],
			'currency_position'          => isset( $input['currency_position'] ) ? sanitize_key( (string) $input['currency_position'] ) : $current['currency_position'],
			'date_format'                => isset( $input['date_format'] ) ? sanitize_text_field( (string) $input['date_format'] ) : $current['date_format'],
			'custom_css'                 => isset( $input['custom_css'] ) ? (string) $input['custom_css'] : $current['custom_css'],
			'customer_type_switcher_label' => isset( $input['customer_type_switcher_label'] )
				? sanitize_text_field( (string) $input['customer_type_switcher_label'] )
				: $current['customer_type_switcher_label'],
			'billing_heading'            => isset( $input['billing_heading'] ) ? sanitize_text_field( (string) $input['billing_heading'] ) : $current['billing_heading'],
			'shipping_heading'           => isset( $input['shipping_heading'] ) ? sanitize_text_field( (string) $input['shipping_heading'] ) : $current['shipping_heading'],
			'order_notes_label'          => isset( $input['order_notes_label'] ) ? sanitize_text_field( (string) $input['order_notes_label'] ) : $current['order_notes_label'],
			'email_section_heading'      => isset( $input['email_section_heading'] ) ? sanitize_text_field( (string) $input['email_section_heading'] ) : $current['email_section_heading'],
			'show_optional_label'        => array_key_exists( 'show_optional_label', $input ) ? (bool) $input['show_optional_label'] : $current['show_optional_label'],
			'order_review_show_thumbs'   => array_key_exists( 'order_review_show_thumbs', $input ) ? (bool) $input['order_review_show_thumbs'] : $current['order_review_show_thumbs'],
			'order_review_show_quantity' => array_key_exists( 'order_review_show_quantity', $input ) ? (bool) $input['order_review_show_quantity'] : $current['order_review_show_quantity'],
			'address_format_overrides'   => isset( $input['address_format_overrides'] ) ? sanitize_textarea_field( (string) $input['address_format_overrides'] ) : $current['address_format_overrides'],
			'custom_billing_address_keys' => isset( $input['custom_billing_address_keys'] ) ? sanitize_text_field( (string) $input['custom_billing_address_keys'] ) : $current['custom_billing_address_keys'],
			'custom_shipping_address_keys' => isset( $input['custom_shipping_address_keys'] ) ? sanitize_text_field( (string) $input['custom_shipping_address_keys'] ) : $current['custom_shipping_address_keys'],
			'enable_google_address_autofill' => array_key_exists( 'enable_google_address_autofill', $input ) ? (bool) $input['enable_google_address_autofill'] : $current['enable_google_address_autofill'],
			'google_maps_api_key'        => isset( $input['google_maps_api_key'] ) ? sanitize_text_field( (string) $input['google_maps_api_key'] ) : $current['google_maps_api_key'],
			'switcher_bg_color'          => isset( $input['switcher_bg_color'] ) ? \sanitize_hex_color( (string) $input['switcher_bg_color'] ) : $current['switcher_bg_color'],
			'switcher_active_color'      => isset( $input['switcher_active_color'] ) ? \sanitize_hex_color( (string) $input['switcher_active_color'] ) : $current['switcher_active_color'],
			'switcher_border_color'      => isset( $input['switcher_border_color'] ) ? \sanitize_hex_color( (string) $input['switcher_border_color'] ) : $current['switcher_border_color'],
			'switcher_border_radius'     => isset( $input['switcher_border_radius'] ) ? absint( $input['switcher_border_radius'] ) : $current['switcher_border_radius'],
			'switcher_font_color'        => isset( $input['switcher_font_color'] ) ? \sanitize_hex_color( (string) $input['switcher_font_color'] ) : $current['switcher_font_color'],
			'switcher_font_size'         => array_key_exists( 'switcher_font_size', $input ) ? ( '' === (string) $input['switcher_font_size'] ? '' : absint( $input['switcher_font_size'] ) ) : $current['switcher_font_size'],
			'switcher_padding'           => isset( $input['switcher_padding'] ) ? sanitize_text_field( (string) $input['switcher_padding'] ) : $current['switcher_padding'],
			'switcher_margin'            => isset( $input['switcher_margin'] ) ? sanitize_text_field( (string) $input['switcher_margin'] ) : $current['switcher_margin'],
			'switcher_shadow'            => array_key_exists( 'switcher_shadow', $input ) ? (bool) $input['switcher_shadow'] : $current['switcher_shadow'],
			'switcher_display_type'      => isset( $input['switcher_display_type'] ) ? sanitize_text_field( (string) $input['switcher_display_type'] ) : $current['switcher_display_type'],
			'switcher_position'          => isset( $input['switcher_position'] ) ? sanitize_text_field( (string) $input['switcher_position'] ) : $current['switcher_position'],
		);

		$settings['hide_selector_for_single_type'] = $settings['hide_billing_if_single'];

		update_option( self::OPTION_KEY, wp_json_encode( $settings ) );
		return $this->success( $settings );
	}

	private function getSettings(): array {
		$defaults = array(
			'remove_data_on_uninstall'      => false,
			'enable_blocks_support'         => true,
			'customer_type_position'        => 'before_billing',
			'type_selector_style'           => 'radio',
			'hide_selector_for_single_type' => true,
			'save_type_to_user_meta'        => true,
			'enable_pricing'                => true,
			'enable_revision_history'       => true,
			'enable_for_all_users'          => true,
			'show_on_thankyou_page'         => true,
			'hide_billing_if_single'        => true,
			'log_field_errors'              => false,
			'enable_preview_mode'           => false,
			'currency_position'             => 'before',
			'date_format'                   => 'Y-m-d',
			'custom_css'                    => '',
			'customer_type_switcher_label'  => 'Customer Type',
			'billing_heading'               => 'Billing details',
			'shipping_heading'              => 'Shipping address',
			'order_notes_label'             => 'Order notes',
			'email_section_heading'         => '',
			'show_optional_label'           => true,
			'order_review_show_thumbs'      => false,
			'order_review_show_quantity'    => false,
			'address_format_overrides'      => '',
			'custom_billing_address_keys'   => '',
			'custom_shipping_address_keys'  => '',
			'enable_google_address_autofill' => false,
			'google_maps_api_key'           => '',
			'switcher_bg_color'             => '',
			'switcher_active_color'         => '',
			'switcher_border_color'         => '',
			'switcher_border_radius'        => '8',
			'switcher_font_color'            => '',
			'switcher_font_size'             => '',
			'switcher_padding'               => '',
			'switcher_margin'                => '',
			'switcher_shadow'               => false,
			'switcher_display_type'         => 'buttons',
			'switcher_position'             => 'before_form',
		);

		$raw = get_option( self::OPTION_KEY, '' );
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				if ( isset( $decoded['hide_selector_for_single_type'] ) && ! isset( $decoded['hide_billing_if_single'] ) ) {
					$decoded['hide_billing_if_single'] = (bool) $decoded['hide_selector_for_single_type'];
				}
				return array_merge( $defaults, $decoded );
			}
		}

		if ( is_array( $raw ) ) {
			if ( isset( $raw['hide_selector_for_single_type'] ) && ! isset( $raw['hide_billing_if_single'] ) ) {
				$raw['hide_billing_if_single'] = (bool) $raw['hide_selector_for_single_type'];
			}
			return array_merge( $defaults, $raw );
		}

		return $defaults;
	}
}


