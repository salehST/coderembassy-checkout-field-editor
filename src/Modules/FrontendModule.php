<?php
namespace CoderEmbassy\CheckoutFieldsManager\Modules;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Abstracts\AbstractModule;
use CoderEmbassy\CheckoutFieldsManager\Frontend\BlocksIntegration;
use CoderEmbassy\CheckoutFieldsManager\Frontend\CheckoutContext;
use CoderEmbassy\CheckoutFieldsManager\Frontend\CheckoutRenderer;
use CoderEmbassy\CheckoutFieldsManager\Modules\CustomerTypes\CustomerTypeManager;
use CoderEmbassy\CheckoutFieldsManager\Modules\CustomerTypes\CustomerTypeRepository;
use CoderEmbassy\CheckoutFieldsManager\Modules\Fields\FieldRepository;
use CoderEmbassy\CheckoutFieldsManager\Modules\Fields\VisibilityResolver;
use CoderEmbassy\CheckoutFieldsManager\Modules\OrderMeta\OrderMetaHandler;
use CoderEmbassy\CheckoutFieldsManager\Modules\Sections\SectionRepository;
use CoderEmbassy\CheckoutFieldsManager\Modules\Validation\ValidationEngine;

class FrontendModule extends AbstractModule {
	public function register(): void {
		$this->container->singleton(
			CheckoutContext::class,
			static fn (): CheckoutContext => new CheckoutContext()
		);

		$this->container->singleton(
			CheckoutRenderer::class,
			fn (): CheckoutRenderer => new CheckoutRenderer(
				$this->container->make( FieldRepository::class ),
				$this->container->make( VisibilityResolver::class ),
				$this->container->make( ValidationEngine::class ),
				$this->container->make( SectionRepository::class ),
				$this->container->make( CustomerTypeManager::class )
			)
		);

		$this->container->singleton(
			CustomerTypeManager::class,
			fn (): CustomerTypeManager => new CustomerTypeManager(
				$this->container->make( CustomerTypeRepository::class ),
				$this->container->make( FieldRepository::class )
			)
		);

		$this->container->singleton(
			BlocksIntegration::class,
			fn (): BlocksIntegration => new BlocksIntegration(
				$this->container->make( FieldRepository::class ),
				$this->container->make( VisibilityResolver::class )
			)
		);

		$this->container->singleton(
			OrderMetaHandler::class,
			fn (): OrderMetaHandler => new OrderMetaHandler(
				$this->container->make( FieldRepository::class ),
				$this->container->make( VisibilityResolver::class ),
				$this->container->make( CheckoutContext::class ),
				$this->container->make( SectionRepository::class )
			)
		);
	}

	public function boot(): void {
		$renderer = $this->container->make( CheckoutRenderer::class );
		$field_repository = $this->container->make( FieldRepository::class );

		$settings_raw = \get_option( 'cecfm_settings', '' );
		$settings     = is_string( $settings_raw ) && '' !== $settings_raw
			? ( json_decode( $settings_raw, true ) ?? array() )
			: array();

		\add_filter( 'woocommerce_checkout_fields', array( $renderer, 'filterCheckoutFields' ), 20, 1 );
		\add_filter( 'woocommerce_form_field', array( $renderer, 'filterNativeFieldHtml' ), 20, 4 );

		// Customer type switcher. This plugin manages a fixed Private/Company
		// pair; the renderer only outputs it when more than one type exists.
		$switcher_pos = $settings['switcher_position'] ?? 'before_form';
		if ( 'inside_billing' === $switcher_pos ) {
			\add_action( 'woocommerce_checkout_billing', array( $renderer, 'renderCustomerTypeSwitcher' ), 1, 0 );
		} else {
			\add_action( 'woocommerce_before_checkout_form', array( $renderer, 'renderCustomerTypeSwitcher' ), 10, 0 );
		}
		\add_action( 'wp_ajax_cecfm_set_customer_type', array( $renderer, 'ajaxSetCustomerType' ) );
		\add_action( 'wp_ajax_nopriv_cecfm_set_customer_type', array( $renderer, 'ajaxSetCustomerType' ) );

		\add_action( 'woocommerce_before_checkout_form', array( $renderer, 'renderSectionsBeforeCheckoutForm' ), 20, 0 );
		\add_action( 'woocommerce_after_checkout_form', array( $renderer, 'renderSectionsAfterCheckoutForm' ), 10, 0 );
		\add_action( 'woocommerce_checkout_before_order_review', array( $renderer, 'renderSectionsBefore' ), 10, 0 );
		\add_action( 'woocommerce_checkout_after_order_review', array( $renderer, 'renderSectionsAfter' ), 10, 0 );
		\add_action( 'woocommerce_before_checkout_billing_form', array( $renderer, 'renderSectionsBeforeBilling' ), 10, 0 );
		\add_action( 'woocommerce_checkout_billing', array( $renderer, 'renderSectionsInsideBilling' ), 40, 0 );
		\add_action( 'woocommerce_after_checkout_billing_form', array( $renderer, 'renderSectionsAfterBilling' ), 10, 0 );
		\add_action( 'woocommerce_before_checkout_shipping_form', array( $renderer, 'renderSectionsBeforeShipping' ), 10, 0 );
		\add_action( 'woocommerce_checkout_shipping', array( $renderer, 'renderSectionsInsideShipping' ), 40, 0 );
		\add_action( 'woocommerce_after_checkout_shipping_form', array( $renderer, 'renderSectionsAfterShipping' ), 10, 0 );
		\add_action( 'woocommerce_checkout_before_customer_details', array( $renderer, 'renderSectionsBeforeCustomerDetails' ), 20, 0 );
		\add_action( 'woocommerce_checkout_after_customer_details', array( $renderer, 'renderSectionsAfterCustomerDetails' ), 10, 0 );
		\add_action( 'woocommerce_before_order_notes', array( $renderer, 'renderSectionsBeforeOrderNotes' ), 10, 0 );
		\add_action( 'woocommerce_after_order_notes', array( $renderer, 'renderSectionsAfterOrderNotes' ), 10, 0 );
		\add_action( 'woocommerce_checkout_order_review', array( $renderer, 'renderSectionsInsideOrderReview' ), 80, 0 );

		\add_action( 'woocommerce_before_checkout_form', array( $renderer, 'renderFieldsBeforeCheckoutForm' ), 30, 0 );
		\add_action( 'woocommerce_checkout_before_customer_details', array( $renderer, 'renderFieldsBeforeCustomerDetails' ), 30, 0 );
		\add_action( 'woocommerce_before_checkout_billing_form', array( $renderer, 'renderFieldsBeforeBilling' ), 20, 0 );
		\add_action( 'woocommerce_checkout_billing', array( $renderer, 'renderFieldsInsideBilling' ), 20, 0 );
		\add_action( 'woocommerce_after_checkout_billing_form', array( $renderer, 'renderFieldsAfterBilling' ), 20, 0 );
		\add_action( 'woocommerce_before_checkout_shipping_form', array( $renderer, 'renderFieldsBeforeShipping' ), 20, 0 );
		\add_action( 'woocommerce_checkout_shipping', array( $renderer, 'renderFieldsInsideShipping' ), 20, 0 );
		\add_action( 'woocommerce_after_checkout_shipping_form', array( $renderer, 'renderFieldsAfterShipping' ), 20, 0 );
		\add_action( 'woocommerce_checkout_after_customer_details', array( $renderer, 'renderFieldsAfterCustomerDetails' ), 20, 0 );
		\add_action( 'woocommerce_before_order_notes', array( $renderer, 'renderFieldsBeforeOrderNotes' ), 20, 0 );
		\add_action( 'woocommerce_after_order_notes', array( $renderer, 'renderFieldsAfterOrderNotes' ), 20, 0 );
		\add_action( 'woocommerce_checkout_before_order_review', array( $renderer, 'renderFieldsBeforeOrderReview' ), 20, 0 );
		\add_action( 'woocommerce_checkout_order_review', array( $renderer, 'renderFieldsInsideOrderReview' ), 95, 0 );
		\add_action( 'woocommerce_checkout_after_order_review', array( $renderer, 'renderFieldsAfterOrderReview' ), 20, 0 );
		\add_action( 'woocommerce_after_checkout_form', array( $renderer, 'renderFieldsAfterCheckoutForm' ), 20, 0 );
		\add_action( 'woocommerce_cart_calculate_fees', array( $renderer, 'applyFees' ), 10, 1 );
		// Add multipart/form-data enctype to checkout form when a file upload field exists.
		\add_action( 'woocommerce_checkout_before_customer_details', array( $renderer, 'maybeAddFileEnctypeScript' ), 1, 0 );

		// ── Order Meta ─────────────────────────────────────────────────────────
		$meta_handler = $this->container->make( OrderMetaHandler::class );
		\add_action( 'woocommerce_checkout_update_order_meta', array( $meta_handler, 'saveCheckoutMeta' ), 10, 2 );
		\add_action( 'woocommerce_order_details_after_order_table', array( $meta_handler, 'displayOrderMeta' ), 20, 1 );
		\add_action( 'woocommerce_admin_order_data_after_billing_address', array( $meta_handler, 'displayAdminOrderMeta' ), 10, 1 );
		\add_action( 'woocommerce_email_order_meta', array( $meta_handler, 'displayEmailMeta' ), 10, 3 );

		// ── Order review enhancements ───────────────────────────────────────────
		if ( ! empty( $settings['order_review_show_thumbs'] ) ) {
			\add_filter( 'woocommerce_cart_item_name', array( $renderer, 'prependCartItemThumb' ), 10, 3 );
		}
		if ( ! empty( $settings['order_review_show_quantity'] ) ) {
			\add_filter( 'woocommerce_checkout_cart_item_quantity', array( $renderer, 'renderQuantityControl' ), 10, 3 );
			\add_action( 'wp_ajax_cecfm_update_cart_qty', array( $renderer, 'ajaxUpdateCartQty' ) );
			\add_action( 'wp_ajax_nopriv_cecfm_update_cart_qty', array( $renderer, 'ajaxUpdateCartQty' ) );
		}

		// ── Heading/label overrides ─────────────────────────────────────────────
		if ( ! empty( $settings['billing_heading'] ) ) {
			$heading = $settings['billing_heading'];
			\add_filter( 'woocommerce_checkout_billing_heading', static fn() => esc_html( $heading ) );
		}
		if ( ! empty( $settings['shipping_heading'] ) ) {
			$heading = $settings['shipping_heading'];
			\add_filter( 'woocommerce_checkout_shipping_heading', static fn() => esc_html( $heading ) );
		}
		if ( ! empty( $settings['order_notes_label'] ) ) {
			$label = $settings['order_notes_label'];
			\add_filter( 'woocommerce_checkout_order_notes_placeholder', static fn() => esc_html( $label ) );
			\add_filter(
				'woocommerce_checkout_fields',
				function ( array $fields ) use ( $label ): array {
					if ( isset( $fields['order']['order_comments']['label'] ) ) {
						$fields['order']['order_comments']['label'] = $label;
					}
					return $fields;
				},
				30
			);
		}

		\add_action(
			'woocommerce_init',
			function () use ( $settings, $field_repository ): void {
				if ( empty( $settings['enable_blocks_support'] ) ) {
					return;
				}
				if ( ! \function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
					return;
				}

				$registered = array();
				$blocks_supported_types = array( 'text', 'select', 'date', 'radio', 'checkbox' );
				$is_blocks_enabled_field = static function ( $field ) use ( $blocks_supported_types ): bool {
					if ( ! isset( $field->meta ) || ! is_array( $field->meta ) || empty( $field->meta['blocks_enabled'] ) ) {
						return false;
					}
					return in_array( (string) $field->type, $blocks_supported_types, true );
				};
				foreach ( $field_repository->findAll( array( 'enabled' => true ) ) as $field ) {
					$field_key = \sanitize_key( (string) $field->field_key );
					if ( '' === $field_key || 'cecfm_customer_type' === $field_key ) {
						continue;
					}
					if ( ! $is_blocks_enabled_field( $field ) ) {
						continue;
					}

					$id = 'coderembassy-checkout-fields-manager/' . $field_key;
					if ( isset( $registered[ $id ] ) ) {
						continue;
					}

					$location = ( 'billing' === $field->section || 'shipping' === $field->section ) ? 'address' : 'order';

					$field_type = 'text';
					if ( 'select' === $field->type || 'radio' === $field->type ) {
						$field_type = 'select';
					} elseif ( 'checkbox' === $field->type ) {
						$field_type = 'checkbox';
					} elseif ( 'date' === $field->type ) {
						$field_type = 'date';
					}

					$args = array(
						'id'       => $id,
						'label'    => (string) $field->label,
						'location' => $location,
						'type'     => $field_type,
						'required' => (bool) $field->required,
					);

					if ( 'select' === $field_type ) {
						$options = array();
						foreach ( (array) $field->options as $option ) {
							if ( ! \is_array( $option ) ) {
								continue;
							}
							$value = (string) ( $option['value'] ?? '' );
							$label = (string) ( $option['label'] ?? $value );
							if ( '' === $value || '' === $label ) {
								continue;
							}
							$options[] = array(
								'value' => $value,
								'label' => $label,
							);
						}
						if ( empty( $options ) ) {
							continue;
						}
						$args['options'] = $options;
					}

					$rules = is_array( $field->validation_rules ) ? $field->validation_rules : array();

					if ( ! empty( $rules['email'] ) ) {
						$args['sanitize_callback'] = static fn ( $value ) => \sanitize_email( (string) $value );
					}

					if ( ! empty( $rules ) ) {
						$label = (string) $field->label;
						$args['validate_callback'] = static function ( $value ) use ( $rules, $label ) {
							$string = trim( (string) $value );
							if ( '' === $string ) {
								return null;
							}

							if ( ! empty( $rules['email'] ) && ! \is_email( $string ) ) {
								/* translators: %s: the label shown for the checkout field. */
								return new \WP_Error( 'cecfm_invalid_email', sprintf( \__( '%s must be a valid email address.', 'coderembassy-checkout-fields-manager' ), $label ) );
							}
							if ( isset( $rules['min_length'] ) && strlen( $string ) < (int) $rules['min_length'] ) {
								/* translators: %s: the label shown for the checkout field. */
								return new \WP_Error( 'cecfm_min_length', sprintf( \__( '%s is too short.', 'coderembassy-checkout-fields-manager' ), $label ) );
							}
							if ( isset( $rules['max_length'] ) && strlen( $string ) > (int) $rules['max_length'] ) {
								/* translators: %s: the label shown for the checkout field. */
								return new \WP_Error( 'cecfm_max_length', sprintf( \__( '%s is too long.', 'coderembassy-checkout-fields-manager' ), $label ) );
							}
							if ( isset( $rules['regex'] ) && '' !== (string) $rules['regex'] ) {
								try {
									$match = preg_match( (string) $rules['regex'], $string );
								} catch ( \ValueError $e ) {
									$match = false;
								}
								if ( 1 !== $match ) {
									/* translators: %s: the label shown for the checkout field. */
									return new \WP_Error( 'cecfm_invalid_pattern', sprintf( \__( '%s format is invalid.', 'coderembassy-checkout-fields-manager' ), $label ) );
								}
							}
							if ( isset( $rules['min_value'] ) && is_numeric( $string ) && (float) $string < (float) $rules['min_value'] ) {
								/* translators: %s: the label shown for the checkout field. */
								return new \WP_Error( 'cecfm_min_value', sprintf( \__( '%s is below the minimum allowed value.', 'coderembassy-checkout-fields-manager' ), $label ) );
							}
							if ( isset( $rules['max_value'] ) && is_numeric( $string ) && (float) $string > (float) $rules['max_value'] ) {
								/* translators: %s: the label shown for the checkout field. */
								return new \WP_Error( 'cecfm_max_value', sprintf( \__( '%s exceeds the maximum allowed value.', 'coderembassy-checkout-fields-manager' ), $label ) );
							}
							return null;
						};
					}

					\woocommerce_register_additional_checkout_field( $args );
					$registered[ $id ] = true;
				}
			},
			20
		);

		\add_filter(
			'woocommerce_localisation_address_formats',
			static function ( array $formats ) use ( $settings ): array {
				$raw = isset( $settings['address_format_overrides'] ) ? (string) $settings['address_format_overrides'] : '';
				if ( '' === trim( $raw ) ) {
					return $formats;
				}
				$chunks = array_filter( array_map( 'trim', explode( '|', $raw ) ) );
				foreach ( $chunks as $chunk ) {
					if ( ! str_contains( $chunk, '=>' ) ) {
						continue;
					}
					$parts = explode( '=>', $chunk, 2 );
					$code  = trim( (string) ( $parts[0] ?? '' ) );
					$fmt   = trim( (string) ( $parts[1] ?? '' ) );
					if ( '' === $code || '' === $fmt ) {
						continue;
					}
					$formats[ $code ] = str_replace( '\n', "\n", $fmt );
				}
				return $formats;
			}
		);
		\add_filter(
			'woocommerce_formatted_address_replacements',
			static function ( array $replacements, array $args ): array {
				foreach ( $args as $key => $value ) {
					if ( ! is_scalar( $value ) ) {
						continue;
					}
					$replacements[ '{' . $key . '}' ] = (string) $value;
				}
				return $replacements;
			},
			20,
			2
		);
		\add_filter(
			'woocommerce_order_formatted_billing_address',
			static function ( array $address, \WC_Order $order ) use ( $settings ): array {
				$keys = array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( ',', (string) ( $settings['custom_billing_address_keys'] ?? '' ) ) ) ) );
				foreach ( $keys as $key ) {
					$value = (string) $order->get_meta( '_cecfm_' . $key );
					if ( '' !== $value ) {
						$address[ $key ] = $value;
					}
				}
				return $address;
			},
			20,
			2
		);
		\add_filter(
			'woocommerce_order_formatted_shipping_address',
			static function ( array $address, \WC_Order $order ) use ( $settings ): array {
				$keys = array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( ',', (string) ( $settings['custom_shipping_address_keys'] ?? '' ) ) ) ) );
				foreach ( $keys as $key ) {
					$value = (string) $order->get_meta( '_cecfm_' . $key );
					if ( '' !== $value ) {
						$address[ $key ] = $value;
					}
				}
				return $address;
			},
			20,
			2
		);

		// ── Blocks integration ──────────────────────────────────────────────────
		if ( interface_exists( '\Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface' ) ) {
			\add_action(
				'woocommerce_blocks_checkout_block_registration',
				function ( $integration_registry ): void {
					if ( is_object( $integration_registry ) && method_exists( $integration_registry, 'register' ) ) {
						$integration_registry->register( $this->container->make( BlocksIntegration::class ) );
					}
				},
				5,
				1
			);
		}

		// ── Frontend asset enqueue ──────────────────────────────────────────────
		\add_action(
			'wp_enqueue_scripts',
			function (): void {
				if ( ! function_exists( 'is_checkout' ) || ! \is_checkout() ) {
					return;
				}

				\wp_enqueue_style(
					'cecfm-frontend',
					CECFM_URL . 'assets/frontend/checkout.css',
					array(),
					CECFM_VERSION
				);
				\wp_enqueue_script(
					'cecfm-frontend',
					CECFM_URL . 'assets/frontend/checkout.js',
					array( 'jquery', 'wc-checkout' ),
					CECFM_VERSION,
					true
				);

				$settings_raw = \get_option( 'cecfm_settings', '' );
				$settings     = is_string( $settings_raw ) && '' !== $settings_raw
					? ( json_decode( $settings_raw, true ) ?? array() )
					: array();

				// Always-on inline CSS: checkbox layout + currency inline fix.
				\wp_add_inline_style(
					'cecfm-frontend',
					'.cecfm-field--checkbox .cecfm-checkbox-label { display:inline-flex !important; align-items:center !important; gap:6px; cursor:pointer; font-weight:normal !important; line-height:1.4 !important; }
.cecfm-field--checkbox .cecfm-checkbox-label input[type="checkbox"] { margin:0; width:16px; height:16px; flex-shrink:0; }
.cecfm-field--checkbox .cecfm-field-description { display:block; margin-top:4px; font-size:0.875em; color:#666; }
.cecfm-field-inner { display:flex; flex-wrap:wrap; align-items:center; }
.cecfm-field-inner > label { width:100%; flex:0 0 100%; font-weight:normal; }
.cecfm-field-inner > .cecfm-currency { flex-shrink:0; font-weight:normal; line-height:normal; padding-right:2px; }
.cecfm-field-inner > input, .cecfm-field-inner > select, .cecfm-field-inner > textarea { flex:1; min-width:0; }
.cecfm-field-inner > .cecfm-field-description { width:100%; flex:0 0 100%; margin-top:4px; font-size:0.875em; color:#666; }
' );

				// Switcher Styles.
				$css_parts = array();
				if ( ! empty( $settings['switcher_bg_color'] ) ) {
					$css_parts[] = '.cecfm-type-switcher .cecfm-type-btn { background-color: ' . \sanitize_hex_color( (string) $settings['switcher_bg_color'] ) . ' !important; }';
				}
				if ( ! empty( $settings['switcher_active_color'] ) ) {
					$active      = \sanitize_hex_color( (string) $settings['switcher_active_color'] );
					$css_parts[] = '.cecfm-type-switcher .cecfm-type-btn.is-active, .cecfm-type-switcher .cecfm-type-btn:focus { background-color: ' . $active . ' !important; border-color: ' . $active . ' !important; }';
				}
				if ( ! empty( $settings['switcher_border_color'] ) ) {
					$css_parts[] = '.cecfm-type-switcher .cecfm-type-btn { border-color: ' . \sanitize_hex_color( (string) $settings['switcher_border_color'] ) . ' !important; }';
				}
				if ( isset( $settings['switcher_border_radius'] ) && '' !== (string) $settings['switcher_border_radius'] ) {
					$css_parts[] = '.cecfm-type-switcher .cecfm-type-btn { border-radius: ' . absint( $settings['switcher_border_radius'] ) . 'px !important; }';
				}
				if ( ! empty( $settings['switcher_shadow'] ) ) {
					$css_parts[] = '.cecfm-type-switcher .cecfm-type-btn { box-shadow: 0 2px 8px rgba(0,0,0,0.15) !important; }';
					$css_parts[] = '.cecfm-type-switcher .cecfm-type-btn.is-active { box-shadow: 0 4px 12px rgba(0,0,0,0.2) !important; }';
				}
				if ( ! empty( $settings['switcher_font_color'] ) ) {
					$font_color  = \sanitize_hex_color( (string) $settings['switcher_font_color'] );
					$css_parts[] = '.cecfm-type-switcher .cecfm-type-btn { color: ' . $font_color . ' !important; }';
					$css_parts[] = '.cecfm-type-switcher .cecfm-type-radio-label { color: ' . $font_color . ' !important; }';
				}
				if ( ! empty( $settings['switcher_font_size'] ) && (int) $settings['switcher_font_size'] > 0 ) {
					$font_size   = absint( $settings['switcher_font_size'] );
					$css_parts[] = '.cecfm-type-switcher .cecfm-type-btn { font-size: ' . $font_size . 'px !important; }';
					$css_parts[] = '.cecfm-type-switcher .cecfm-type-radio-label { font-size: ' . $font_size . 'px !important; }';
				}
				if ( ! empty( $settings['switcher_padding'] ) ) {
					$padding = self::sanitizeCssLengthShorthand( (string) $settings['switcher_padding'] );
					if ( '' !== $padding ) {
						$css_parts[] = '.cecfm-type-switcher .cecfm-type-btn { padding: ' . $padding . ' !important; }';
					}
				}
				if ( ! empty( $settings['switcher_margin'] ) ) {
					$margin = self::sanitizeCssLengthShorthand( (string) $settings['switcher_margin'] );
					if ( '' !== $margin ) {
						$css_parts[] = '.cecfm-type-switcher .cecfm-type-btn { margin: ' . $margin . ' !important; }';
					}
				}
				if ( ! empty( $css_parts ) ) {
					$switcher_css = self::sanitizeInlineCssRules( implode( ' ', $css_parts ) );
					if ( '' !== $switcher_css ) {
						\wp_add_inline_style( 'cecfm-frontend', $switcher_css );
					}
				}

				// Output merchant-authored custom CSS.
				if ( ! empty( $settings['custom_css'] ) ) {
					$safe_css = self::sanitizeInlineCssRules( (string) $settings['custom_css'] );
					if ( '' !== $safe_css ) {
						\wp_add_inline_style( 'cecfm-frontend', $safe_css );
					}
				}

				if ( ! empty( $settings['order_review_show_thumbs'] ) || ! empty( $settings['order_review_show_quantity'] ) ) {
					$inline_css = '
/* Product thumbnail in order review */
.cecfm-order-thumb-wrap { display:inline-block; vertical-align:middle; margin-right:8px; }
.cecfm-order-thumb-wrap img.cecfm-order-thumb { width:40px; height:40px; object-fit:cover; border-radius:4px; }

/* Quantity control: always on its own line, never wraps mid-control */
.cecfm-qty-control {
	display:flex;
	align-items:center;
	gap:4px;
	margin-top:6px;
	flex-wrap:nowrap;
	width:fit-content;
}
.cecfm-qty-btn {
	background:#7c3aed;
	color:#fff;
	border:none;
	border-radius:4px;
	width:26px;
	height:26px;
	font-size:15px;
	line-height:1;
	cursor:pointer;
	display:inline-flex;
	align-items:center;
	justify-content:center;
	padding:0;
	flex-shrink:0;
	transition:background 0.15s;
}
.cecfm-qty-btn:hover { background:#6d28d9; }
.cecfm-qty-remove { background:#e53e3e !important; font-size:11px !important; }
.cecfm-qty-remove:hover { background:#c53030 !important; }
.cecfm-qty-value { min-width:22px; text-align:center; font-weight:600; font-size:14px; flex-shrink:0; }
';
					\wp_add_inline_style( 'cecfm-frontend', $inline_css );
				}

				// Inline JS for quantity controls.
				if ( ! empty( $settings['order_review_show_quantity'] ) ) {
					$ajax_url  = \admin_url( 'admin-ajax.php' );
					$inline_js = '
(function(){
	document.addEventListener("click",function(e){
		var btn=e.target.closest(".cecfm-qty-btn");
		if(!btn)return;
		var wrap=btn.closest(".cecfm-qty-control");
		if(!wrap)return;
		var key=wrap.dataset.key;
		var nonce=wrap.dataset.nonce;
		var val=wrap.querySelector(".cecfm-qty-value");
		var isRemove=btn.classList.contains("cecfm-qty-remove");
		var qty;
		if(isRemove){
			qty=0;
		} else {
			var current=parseInt(val?val.textContent:0,10)||0;
			qty=btn.classList.contains("cecfm-qty-plus")?current+1:Math.max(1,current-1);
			if(val)val.textContent=qty;
		}
		// Disable buttons while request is in flight.
		wrap.querySelectorAll(".cecfm-qty-btn").forEach(function(b){b.disabled=true;});
		var params=new URLSearchParams({action:"cecfm_update_cart_qty",cart_key:key,qty:qty,nonce:nonce});
		fetch("' . \esc_js( $ajax_url ) . '",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:params.toString()})
			.then(function(r){return r.json();})
			.then(function(data){
				if(data.success&&typeof jQuery!=="undefined"){
					jQuery(document.body).trigger("update_checkout");
				}
			})
			.catch(function(){
				// Re-enable on failure so the user can retry.
				wrap.querySelectorAll(".cecfm-qty-btn").forEach(function(b){b.disabled=false;});
			});
	});
})();
';
					\wp_add_inline_script( 'wc-checkout', $inline_js );
				}

				// Build native field customer-type restriction map for JS visibility toggling.
				$raw          = \get_option( 'cecfm_native_fields', '{}' );
				$overrides    = is_string( $raw ) ? ( json_decode( $raw, true ) ?? array() ) : ( is_array( $raw ) ? $raw : array() );
				$native_types = array();
				foreach ( $overrides as $field_key => $ov ) {
					if ( ! empty( $ov['customer_types'] ) && is_array( $ov['customer_types'] ) ) {
						$native_types[ \sanitize_key( (string) $field_key ) ] = array_map( 'sanitize_key', $ov['customer_types'] );
					}
				}

				$type_label = isset( $settings['customer_type_switcher_label'] ) && '' !== $settings['customer_type_switcher_label']
					? $settings['customer_type_switcher_label']
					: \__( 'Customer Type', 'coderembassy-checkout-fields-manager' );

				\wp_localize_script(
					'cecfm-frontend',
					'CECFM_Frontend',
					array(
						'ajax_url'     => \admin_url( 'admin-ajax.php' ),
						'type_nonce'   => \wp_create_nonce( 'cecfm_set_type' ),
						'native_types' => $native_types,
						'type_label'   => $type_label,
					)
				);

				if ( ! empty( $settings['enable_google_address_autofill'] ) && ! empty( $settings['google_maps_api_key'] ) ) {
					$key = rawurlencode( (string) $settings['google_maps_api_key'] );
					\wp_enqueue_script(
						'cecfm-google-places',
						'https://maps.googleapis.com/maps/api/js?key=' . $key . '&libraries=places',
						array(),
						CECFM_VERSION,
						true
					);
					$inline_autofill_js = '
(function(){
	function pick(c,t,useShort){for(var i=0;i<c.length;i++){if(c[i].types&&c[i].types.indexOf(t)!==-1){return useShort?(c[i].short_name||""):(c[i].long_name||"");}}return "";}
	function fill(prefix, place){
		if(!place||!place.address_components)return;
		var c=place.address_components;
		var streetNum=pick(c,"street_number",false);
		var route=pick(c,"route",false);
		var city=pick(c,"locality",false)||pick(c,"postal_town",false)||pick(c,"administrative_area_level_2",false);
		var stateCode=pick(c,"administrative_area_level_1",true);
		var stateName=pick(c,"administrative_area_level_1",false);
		var postcode=pick(c,"postal_code",false);
		var countryCode=pick(c,"country",true);
		var countryName=pick(c,"country",false);
		var line=[streetNum,route].filter(Boolean).join(" ");
		var set=function(id,val){
			var el=document.getElementById(id);
			if(!el||!val)return;
			var asSelect=(el.tagName||"").toLowerCase()==="select";
			if(asSelect){
				var found=false;
				for(var i=0;i<el.options.length;i++){
					var ov=String(el.options[i].value||"").toLowerCase();
					var ot=String(el.options[i].text||"").toLowerCase();
					var vv=String(val).toLowerCase();
					if(ov===vv||ot===vv){el.value=el.options[i].value;found=true;break;}
				}
				if(!found){el.value=val;}
			}else{
				el.value=val;
			}
			el.dispatchEvent(new Event("change",{bubbles:true}));
		};
		set(prefix+"_address_1",line);
		set(prefix+"_city",city);
		set(prefix+"_state",stateCode||stateName);
		set(prefix+"_postcode",postcode);
		set(prefix+"_country",countryCode||countryName);
		if(typeof jQuery!=="undefined"){jQuery(document.body).trigger("update_checkout");}
	}
	function initOne(id,prefix){
		var el=document.getElementById(id);
		if(!el||!window.google||!google.maps||!google.maps.places)return;
		var ac=new google.maps.places.Autocomplete(el,{types:["address"]});
		ac.addListener("place_changed",function(){fill(prefix,ac.getPlace());});
	}
	function init(){
		initOne("billing_address_1","billing");
		initOne("shipping_address_1","shipping");
	}
	if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",init);}else{init();}
})();
';
					\wp_add_inline_script( 'cecfm-google-places', $inline_autofill_js );
				}
			},
			20
		);
	}

	private static function sanitizeCssLengthShorthand( string $value ): string {
		$value = trim( \wp_strip_all_tags( $value ) );
		if ( '' === $value ) {
			return '';
		}

		$pattern = '/^-?(?:\d+(?:\.\d+)?|\.\d+)(?:px|em|rem|%|vh|vw)?(?:\s+-?(?:\d+(?:\.\d+)?|\.\d+)(?:px|em|rem|%|vh|vw)?){0,3}$/';
		return 1 === preg_match( $pattern, $value ) ? $value : '';
	}

	private static function sanitizeInlineCssRules( string $css ): string {
		$css = trim( \wp_kses_no_null( $css ) );
		if ( '' === $css ) {
			return '';
		}

		$css = (string) preg_replace( '#</?style[^>]*>#i', '', $css );

		$rules      = explode( '}', $css );
		$safe_rules = array();

		foreach ( $rules as $rule ) {
			if ( ! str_contains( $rule, '{' ) ) {
				continue;
			}

			$parts        = explode( '{', $rule, 2 );
			$selector_raw = trim( (string) ( $parts[0] ?? '' ) );
			$decl_raw     = trim( (string) ( $parts[1] ?? '' ) );
			if ( '' === $selector_raw || '' === $decl_raw ) {
				continue;
			}

			$selector = (string) preg_replace( '/[^a-zA-Z0-9_\-\.\#\:\,\s>\+\~\*\[\]\(\)=\'"]/', '', $selector_raw );
			$selector = trim( $selector );
			if ( '' === $selector ) {
				continue;
			}

			$declarations = \safecss_filter_attr( $decl_raw );
			if ( '' === $declarations ) {
				continue;
			}

			$safe_rules[] = $selector . '{' . $declarations . '}';
		}

		return implode( "\n", $safe_rules );
	}
}

