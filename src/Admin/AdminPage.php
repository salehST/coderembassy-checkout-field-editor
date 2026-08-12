<?php
namespace CoderEmbassy\CheckoutFieldsManager\Admin;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Modules\Fields\FieldTypes;
use CoderEmbassy\CheckoutFieldsManager\Modules\Licensing\FeatureGate;

class AdminPage {
	private string $menu_slug = 'coderembassy-checkout-fields-manager';

	public function registerMenu(): void {
		\add_submenu_page(
			'woocommerce',
			\__( 'Checkout Fields Manager', 'coderembassy-checkout-fields-manager' ),
			\__( 'Checkout Fields Manager', 'coderembassy-checkout-fields-manager' ),
			'manage_woocommerce',
			$this->menu_slug,
			array( $this, 'renderPage' )
		);
	}

	public function enqueueAssets( string $hook_suffix ): void {
		// Suppress other plugins' admin notices on our page.
		if ( false !== strpos( $hook_suffix, $this->menu_slug ) ) {
			// phpcs:ignore WordPress.WP.DiscouragedFunctions.remove_all_actions_admin_notices
			\remove_all_actions( 'admin_notices' );
			// phpcs:ignore WordPress.WP.DiscouragedFunctions.remove_all_actions_all_admin_notices
			\remove_all_actions( 'all_admin_notices' );
		}
		$this->register_admin_scripts( $hook_suffix );
	}

	public function register_admin_scripts( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, $this->menu_slug ) ) {
			return;
		}

		\wp_enqueue_style(
			'cecfm-admin',
			CECFM_URL . 'assets/admin/admin.css',
			array(),
			CECFM_VERSION
		);

		\wp_enqueue_script(
			'cecfm-admin-app',
			CECFM_URL . 'assets/admin/dist/index.js',
			array( 'wp-element', 'wp-i18n', 'wp-components', 'wp-api-fetch', 'wp-data' ),
			CECFM_VERSION,
			true
		);

		$current_user = \wp_get_current_user();

		$settings_raw = \get_option( 'cecfm_settings', '' );
		$settings     = is_string( $settings_raw ) && '' !== $settings_raw
			? ( json_decode( $settings_raw, true ) ?? array() )
			: ( is_array( $settings_raw ) ? $settings_raw : array() );

		$allowed_countries = array();
		$payment_methods   = array();
		$shipping_methods  = array();
		if ( function_exists( 'WC' ) && \WC() ) {
			// Countries.
			$countries_map   = \WC()->countries ? \WC()->countries->get_allowed_countries() : array();
			$countries_map   = is_array( $countries_map ) ? $countries_map : array();
			$country_names   = ! empty( $countries_map ) ? array_values( $countries_map ) : array();
			$country_codes   = ! empty( $countries_map ) ? array_keys( $countries_map ) : array();
			$allowed_countries = array_map(
				static fn( $name, $code ) => array( 'code' => $code, 'name' => $name ),
				$country_names,
				$country_codes
			);

			// Payment gateways (enabled only).
			if ( \WC()->payment_gateways ) {
				$gates = \WC()->payment_gateways()->payment_gateways();
				$gates = is_array( $gates ) ? $gates : array();
				$payment_methods = array_map(
					static fn( $g ) => array( 'id' => $g->id, 'title' => $g->get_title() ),
					array_values(
						array_filter(
							$gates,
							static fn( $g ) => is_object( $g ) && isset( $g->enabled ) && 'yes' === $g->enabled
						)
					)
				);
			}

			// Shipping methods.
			if ( \WC()->shipping ) {
				$methods = \WC()->shipping()->get_shipping_methods();
				$methods = is_array( $methods ) ? $methods : array();
				$shipping_methods = array_map(
					static fn( $m ) => array( 'id' => $m->id, 'title' => $m->get_title() ),
					array_values( $methods )
				);
			}
		}

		\wp_localize_script(
			'cecfm-admin-app',
			'CECFM_ADMIN',
			array(
				'rest_url'             => \rest_url( 'coderembassy-checkout-fields-manager/v1/' ),
				'nonce'                => \wp_create_nonce( 'wp_rest' ),
				'currency'             => \get_woocommerce_currency_symbol(),
				'currency_code'        => \get_woocommerce_currency(),
				'current_user'         => array(
					'display_name' => $current_user->display_name,
					'avatar_url'   => \get_avatar_url( $current_user->ID ),
				),
				'logo_light'           => CECFM_URL . 'assets/admin/logo-light.png',
				'logo_dark'            => CECFM_URL . 'assets/admin/logo-dark.png',
				'version'              => CECFM_VERSION,
				'preview_mode_enabled' => ! empty( $settings['enable_preview_mode'] ),
				'countries'            => $allowed_countries,
				'payment_methods'      => $payment_methods,
				'shipping_methods'     => $shipping_methods,
				// Add-on awareness. The admin app locks what is not allowed rather
				// than hiding it, so people can see what the Pro add-on adds.
				'is_pro'               => FeatureGate::isPro(),
				'features'             => FeatureGate::all(),
				'max_sections'         => FeatureGate::maxSections(),
				'field_types'          => FieldTypes::forAdmin(),
				'upgrade_url'          => (string) \apply_filters(
					'cecfm_upgrade_url',
					'https://coderembassy.com/checkout-fields-manager/'
				),
			)
		);
	}

	public function renderPage(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML root element for React.
		echo '<div id="cecfm-main"></div>';
	}
}


