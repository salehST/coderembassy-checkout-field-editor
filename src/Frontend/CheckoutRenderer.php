<?php
namespace CoderEmbassy\CheckoutFieldEditor\Frontend;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldEditor\Models\CA_Field;
use CoderEmbassy\CheckoutFieldEditor\Modules\CustomerTypes\CustomerTypeManager;
use CoderEmbassy\CheckoutFieldEditor\Modules\Fields\FieldRepository;
use CoderEmbassy\CheckoutFieldEditor\Modules\Fields\VisibilityResolver;
use CoderEmbassy\CheckoutFieldEditor\Modules\Sections\SectionRepository;
use CoderEmbassy\CheckoutFieldEditor\Modules\Validation\ValidationEngine;

class CheckoutRenderer {
	/** @var array<string, mixed>|null Runtime context cache — built once per request. */
	private ?array $context_cache = null;
	private ?array $settings_cache = null;

	public function __construct(
		private FieldRepository $fieldRepository,
		private VisibilityResolver $visibilityResolver,
		private ValidationEngine $validationEngine,
		private CustomerTypeManager $customerTypeManager,
		private SectionRepository $sectionRepository
	) {}

	public function filterCheckoutFields( array $fields ): array {
		try {
			return $this->doFilterCheckoutFields( $fields );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[Checkout Architect] filterCheckoutFields error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
			return $fields;
		}
	}

	private function doFilterCheckoutFields( array $fields ): array {
		$raw       = \get_option( 'checkout_architect_native_fields', '{}' );
		$overrides = is_string( $raw ) ? ( json_decode( $raw, true ) ?? array() ) : ( is_array( $raw ) ? $raw : array() );

		foreach ( $fields as $group => $group_fields ) {
			foreach ( array_keys( $group_fields ) as $key ) {
				if ( ! isset( $overrides[ $key ] ) ) {
					continue;
				}
				$ov = $overrides[ $key ];

				if ( isset( $ov['enabled'] ) && ! (bool) $ov['enabled'] ) {
					unset( $fields[ $group ][ $key ] );
					continue;
				}

				if ( ! empty( $ov['label'] ) ) {
					$fields[ $group ][ $key ]['label'] = $ov['label'];
				}
				if ( array_key_exists( 'placeholder', $ov ) ) {
					$fields[ $group ][ $key ]['placeholder'] = $ov['placeholder'];
				}
				if ( isset( $ov['required'] ) ) {
					$fields[ $group ][ $key ]['required'] = (bool) $ov['required'];
				}
				if ( isset( $ov['priority'] ) ) {
					$fields[ $group ][ $key ]['priority'] = (int) $ov['priority'];
				}

				if ( array_key_exists( 'width', $ov ) ) {
					$cls = is_array( $fields[ $group ][ $key ]['class'] ?? null ) ? $fields[ $group ][ $key ]['class'] : array();
					$cls = array_values( array_filter( $cls, static fn( $c ) => ! in_array( $c, array( 'form-row-wide', 'form-row-first', 'form-row-last' ), true ) ) );
					switch ( $ov['width'] ) {
						case 'half_first':
							$cls[] = 'form-row-first';
							break;
						case 'half_last':
							$cls[] = 'form-row-last';
							break;
						default:
							$cls[] = 'form-row-wide';
							break;
					}
					$fields[ $group ][ $key ]['class'] = $cls;
				}

				if ( ! empty( $ov['customer_types'] ) && is_array( $ov['customer_types'] ) ) {
					$allowed_types   = array_values( array_filter( array_map( 'sanitize_key', $ov['customer_types'] ) ) );
					$request_method  = isset( $_SERVER['REQUEST_METHOD'] ) ? \sanitize_key( \wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) : 'get';
					$is_post         = ( 'post' === strtolower( $request_method ) );
					if ( ! empty( $allowed_types ) && $is_post ) {
						$posted_type = isset( $_POST['ca_customer_type'] ) ? \sanitize_key( \wp_unslash( (string) $_POST['ca_customer_type'] ) ) : '';
						if ( '' === $posted_type && ! empty( $_POST['post_data'] ) ) {
							// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- post_data is a URL-encoded query string.
							$form_data   = array();
							\parse_str( \wp_unslash( (string) $_POST['post_data'] ), $form_data );
							$posted_type = isset( $form_data['ca_customer_type'] ) ? \sanitize_key( (string) $form_data['ca_customer_type'] ) : '';
						}
						$current_type = '' !== $posted_type ? $posted_type : $this->customerTypeManager->getCurrentTypeSlug();
						if ( '' !== $current_type && ! \in_array( $current_type, $allowed_types, true ) ) {
							$fields[ $group ][ $key ]['required'] = false;
						}
					}
				}
			}

			if ( ! empty( $fields[ $group ] ) ) {
				\uasort( $fields[ $group ], static fn( $a, $b ) => ( (int) ( $a['priority'] ?? 0 ) ) <=> ( (int) ( $b['priority'] ?? 0 ) ) );
			}
		}

		$core_keys = array(
			'billing_first_name', 'billing_last_name', 'billing_company',
			'billing_country', 'billing_address_1', 'billing_address_2',
			'billing_city', 'billing_state', 'billing_postcode',
			'billing_phone', 'billing_email',
			'shipping_first_name', 'shipping_last_name', 'shipping_company',
			'shipping_country', 'shipping_address_1', 'shipping_address_2',
			'shipping_city', 'shipping_state', 'shipping_postcode',
		);

		foreach ( $this->fieldRepository->findAll( array( 'enabled' => true ) ) as $field ) {
			if ( empty( $field->meta['replace_core'] ) || ! in_array( $field->field_key, $core_keys, true ) ) {
				continue;
			}
			foreach ( array( 'billing', 'shipping' ) as $group ) {
				if ( isset( $fields[ $group ][ $field->field_key ] ) ) {
					unset( $fields[ $group ][ $field->field_key ] );
				}
			}
		}

		return $fields;
	}

	public function filterNativeFieldHtml( string $field_html, string $key, array $args, mixed $value ): string {
		try {
			return $this->doFilterNativeFieldHtml( $field_html, $key, $args, $value );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[Checkout Architect] filterNativeFieldHtml error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
			return $field_html;
		}
	}

	private function doFilterNativeFieldHtml( string $field_html, string $key, array $args, mixed $value ): string {
		$raw       = \get_option( 'checkout_architect_native_fields', '{}' );
		$overrides = is_string( $raw ) ? ( json_decode( $raw, true ) ?? array() ) : ( is_array( $raw ) ? $raw : array() );

		if ( ! isset( $overrides[ $key ]['customer_types'] ) ) {
			return $field_html;
		}

		$types = array_filter( array_map( 'sanitize_key', (array) $overrides[ $key ]['customer_types'] ) );
		if ( empty( $types ) ) {
			return $field_html;
		}

		$types_str = esc_attr( implode( ',', $types ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$posted_type  = isset( $_POST['ca_customer_type'] ) ? \sanitize_key( \wp_unslash( (string) $_POST['ca_customer_type'] ) ) : '';
		$current_type = '' !== $posted_type ? $posted_type : '';
		if ( '' === $current_type ) {
			try {
				$current_type = $this->customerTypeManager->getCurrentTypeSlug();
			} catch ( \Throwable $e ) {
			}
		}

		$hidden     = ( '' !== $current_type && ! in_array( $current_type, $types, true ) );
		$field_html = preg_replace(
			'/^(<p\b)/',
			'$1 data-ca-types="' . $types_str . '"' . ( $hidden ? ' style="display:none"' : '' ),
			ltrim( $field_html ),
			1
		);

		return $field_html;
	}

	public function renderCustomerTypeSwitcher(): void {
		$types = $this->customerTypeManager->getAllTypes();
		if ( empty( $types ) || count( $types ) < 2 ) {
			return;
		}

		$current_type   = $this->customerTypeManager->getCurrentTypeSlug();
		$settings       = $this->getSettings();
		$switcher_label = isset( $settings['customer_type_switcher_label'] ) && '' !== $settings['customer_type_switcher_label']
			? $settings['customer_type_switcher_label']
			: \__( 'Customer Type', 'coderembassy-checkout-field-editor' );
		$display_type   = $settings['switcher_display_type'] ?? 'buttons';

		echo '<div class="ca-type-switcher">';
		echo '<label>' . esc_html( $switcher_label ) . '</label>';

		if ( 'radio' === $display_type ) {
			echo '<div class="ca-type-radios">';
			foreach ( $types as $type ) {
				$uid = 'ca-type-radio-' . \sanitize_key( (string) $type->slug );
				echo '<label class="ca-type-radio-label" for="' . esc_attr( $uid ) . '">';
				echo '<input type="radio" id="' . esc_attr( $uid ) . '" name="ca_customer_type_radio" value="' . esc_attr( $type->slug ) . '"' . checked( $type->slug, $current_type, false ) . ' data-type="' . esc_attr( $type->slug ) . '" class="ca-type-radio" />';
				echo ' ' . esc_html( $type->label );
				echo '</label>';
			}
			echo '</div>';
		} else {
			echo '<div class="ca-type-buttons">';
			foreach ( $types as $type ) {
				$is_active = $type->slug === $current_type;
				echo '<button type="button" class="ca-type-btn' . ( $is_active ? ' is-active' : '' ) . '" data-type="' . esc_attr( $type->slug ) . '">';
				echo esc_html( $type->label );
				echo '</button>';
			}
			echo '</div>';
		}

		wp_nonce_field( 'ca_set_type', '_ca_type_nonce', false );
		echo '<input type="hidden" name="ca_customer_type" id="ca-current-type-input" value="' . esc_attr( $current_type ) . '" />';

		$css_parts = array();
		if ( ! empty( $settings['switcher_bg_color'] ) ) {
			$css_parts[] = '.ca-type-switcher .ca-type-btn { background-color: ' . \sanitize_hex_color( (string) $settings['switcher_bg_color'] ) . ' !important; }';
		}
		if ( ! empty( $settings['switcher_active_color'] ) ) {
			$active      = \sanitize_hex_color( (string) $settings['switcher_active_color'] );
			$css_parts[] = '.ca-type-switcher .ca-type-btn.is-active, .ca-type-switcher .ca-type-btn:focus { background-color: ' . $active . ' !important; border-color: ' . $active . ' !important; }';
		}
		if ( ! empty( $settings['switcher_border_color'] ) ) {
			$css_parts[] = '.ca-type-switcher .ca-type-btn { border-color: ' . \sanitize_hex_color( (string) $settings['switcher_border_color'] ) . ' !important; }';
		}
		if ( isset( $settings['switcher_border_radius'] ) && '' !== (string) $settings['switcher_border_radius'] ) {
			$css_parts[] = '.ca-type-switcher .ca-type-btn { border-radius: ' . absint( $settings['switcher_border_radius'] ) . 'px !important; }';
		}
		if ( ! empty( $settings['switcher_shadow'] ) ) {
			$css_parts[] = '.ca-type-switcher .ca-type-btn { box-shadow: 0 2px 8px rgba(0,0,0,0.15) !important; }';
			$css_parts[] = '.ca-type-switcher .ca-type-btn.is-active { box-shadow: 0 4px 12px rgba(0,0,0,0.2) !important; }';
		}
		if ( ! empty( $settings['switcher_font_color'] ) ) {
			$font_color  = \sanitize_hex_color( (string) $settings['switcher_font_color'] );
			$css_parts[] = '.ca-type-switcher .ca-type-btn { color: ' . $font_color . ' !important; }';
			$css_parts[] = '.ca-type-switcher .ca-type-radio-label { color: ' . $font_color . ' !important; }';
		}
		if ( ! empty( $settings['switcher_font_size'] ) && (int) $settings['switcher_font_size'] > 0 ) {
			$font_size   = absint( $settings['switcher_font_size'] );
			$css_parts[] = '.ca-type-switcher .ca-type-btn { font-size: ' . $font_size . 'px !important; }';
			$css_parts[] = '.ca-type-switcher .ca-type-radio-label { font-size: ' . $font_size . 'px !important; }';
		}
		if ( ! empty( $settings['switcher_padding'] ) ) {
			$padding     = \sanitize_text_field( (string) $settings['switcher_padding'] );
			$css_parts[] = '.ca-type-switcher .ca-type-btn { padding: ' . $padding . ' !important; }';
		}
		if ( ! empty( $settings['switcher_margin'] ) ) {
			$margin      = \sanitize_text_field( (string) $settings['switcher_margin'] );
			$css_parts[] = '.ca-type-switcher .ca-type-btn { margin: ' . $margin . ' !important; }';
		}
		if ( ! empty( $css_parts ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<style>' . implode( ' ', $css_parts ) . '</style>';
		}
		echo '</div>';
	}

	public function maybeAddFileEnctypeScript(): void {
		$has_file = false;
		foreach ( $this->fieldRepository->findAll( array( 'enabled' => true ) ) as $field ) {
			if ( 'file' === $field->type ) {
				$has_file = true;
				break;
			}
		}
		if ( $has_file ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<script>document.addEventListener("DOMContentLoaded",function(){var f=document.querySelector("form.woocommerce-checkout");if(f)f.setAttribute("enctype","multipart/form-data");});</script>';
		}
	}

	public function renderFieldsBeforeCheckoutForm(): void { $this->renderFieldsAtPosition( 'before_checkout_form' ); }
	public function renderFieldsBeforeCustomerDetails(): void { $this->renderFieldsAtPosition( 'before_customer_details' ); }
	public function renderFieldsBeforeBilling(): void { $this->renderFieldsAtPosition( 'before_billing' ); }
	public function renderFieldsInsideBilling(): void { $this->renderFieldsAtPosition( 'checkout_billing' ); }
	public function renderFieldsAfterBilling(): void { $this->renderFieldsAtPosition( 'after_billing' ); }
	public function renderFieldsBeforeShipping(): void { $this->renderFieldsAtPosition( 'before_shipping' ); }
	public function renderFieldsInsideShipping(): void { $this->renderFieldsAtPosition( 'checkout_shipping' ); }
	public function renderFieldsAfterShipping(): void { $this->renderFieldsAtPosition( 'after_shipping' ); }
	public function renderFieldsAfterCustomerDetails(): void { $this->renderFieldsAtPosition( 'after_customer_details' ); }
	public function renderFieldsBeforeOrderNotes(): void { $this->renderFieldsAtPosition( 'before_order_notes' ); }
	public function renderFieldsAfterOrderNotes(): void { $this->renderFieldsAtPosition( 'after_order_notes' ); }
	public function renderFieldsBeforeOrderReview(): void { $this->renderFieldsAtPosition( 'before_order_review' ); }
	public function renderFieldsInsideOrderReview(): void { $this->renderFieldsAtPosition( 'checkout_order_review' ); }
	public function renderFieldsAfterOrderReview(): void { $this->renderFieldsAtPosition( 'after_order_review' ); }
	public function renderFieldsAfterCheckoutForm(): void { $this->renderFieldsAtPosition( 'after_checkout_form' ); }

	private function renderFieldsAtPosition( string $position ): void {
		$fields = array_filter(
			$this->fieldRepository->findAll( array( 'enabled' => true ) ),
			function ( CA_Field $field ) use ( $position ): bool {
				if ( (int) $field->section_id > 0 ) {
					return false;
				}
				if ( $field->position === $position ) {
					return true;
				}
				// Backward compatibility for older saved field positions.
				return $this->isLegacyFieldPositionMatch( $field, $position );
			}
		);
		if ( empty( $fields ) ) {
			return;
		}
		usort(
			$fields,
			static fn ( CA_Field $a, CA_Field $b ): int => ( (int) $a->priority ) <=> ( (int) $b->priority )
		);

		echo '<div class="ca-field-group ca-field-group--' . esc_attr( $position ) . '">';
		foreach ( $fields as $field ) {
			$this->renderField( $field );
		}
		echo '</div>';
	}

	private function isLegacyFieldPositionMatch( CA_Field $field, string $position ): bool {
		$legacy = (string) $field->position;
		if ( 'before_address' === $legacy ) {
			if ( 'billing' === $field->section && 'before_billing' === $position ) {
				return true;
			}
			if ( 'shipping' === $field->section && 'before_shipping' === $position ) {
				return true;
			}
		}
		if ( 'after_address' === $legacy ) {
			if ( 'billing' === $field->section && 'after_billing' === $position ) {
				return true;
			}
			if ( 'shipping' === $field->section && 'after_shipping' === $position ) {
				return true;
			}
		}
		if ( 'before_order_notes' === $legacy && 'before_order_notes' === $position ) {
			return true;
		}
		if ( 'after_order_notes' === $legacy && 'after_order_notes' === $position ) {
			return true;
		}
		return false;
	}

	public function renderSectionsBeforeCheckoutForm(): void { $this->renderSections( 'before_checkout_form' ); }
	public function renderSectionsAfterCheckoutForm(): void { $this->renderSections( 'after_checkout_form' ); }
	public function renderSectionsBefore(): void { $this->renderSections( 'before_order_review' ); }
	public function renderSectionsAfter(): void { $this->renderSections( 'after_order_review' ); }
	public function renderSectionsBeforeBilling(): void { $this->renderSections( 'before_billing' ); }
	public function renderSectionsInsideBilling(): void { $this->renderSections( 'checkout_billing' ); }
	public function renderSectionsAfterBilling(): void { $this->renderSections( 'after_billing' ); }
	public function renderSectionsBeforeShipping(): void { $this->renderSections( 'before_shipping' ); }
	public function renderSectionsInsideShipping(): void { $this->renderSections( 'checkout_shipping' ); }
	public function renderSectionsAfterShipping(): void { $this->renderSections( 'after_shipping' ); }
	public function renderSectionsBeforeCustomerDetails(): void { $this->renderSections( 'before_customer_details' ); }
	public function renderSectionsAfterCustomerDetails(): void { $this->renderSections( 'after_customer_details' ); }
	public function renderSectionsBeforeOrderNotes(): void { $this->renderSections( 'before_order_notes' ); }
	public function renderSectionsAfterOrderNotes(): void { $this->renderSections( 'after_order_notes' ); }
	public function renderSectionsInsideOrderReview(): void { $this->renderSections( 'checkout_order_review' ); }

	public function renderSections( string $position ): void {
		$sections     = array_filter(
			$this->sectionRepository->findAll(),
			static fn ( $section ): bool => $section->position === $position && $section->enabled
		);
		$fields       = $this->fieldRepository->findAll( array( 'enabled' => true ) );
		$current_slug = $this->customerTypeManager->getCurrentTypeSlug();

		foreach ( $sections as $section ) {
			$section_types      = is_array( $section->customer_types ) ? array_filter( $section->customer_types ) : array();
			$section_types_value = '';
			$section_hidden      = false;
			if ( ! empty( $section_types ) ) {
				if ( '' !== $current_slug && ! in_array( $current_slug, $section_types, true ) ) {
					$section_hidden = true;
				}
				$section_types_value = implode( ',', array_map( 'sanitize_key', $section_types ) );
			}

			// ── Phase 11: Cart-based section repetition ──────────────────────
			$repeat_rule    = isset( $section->meta['repeat_rule'] ) && is_array( $section->meta['repeat_rule'] ) ? $section->meta['repeat_rule'] : array();
			$repeat_enabled = ! empty( $repeat_rule['enabled'] );

			if ( $repeat_enabled ) {
				$n           = $this->getRepeatCount( $repeat_rule );
				$start       = max( 1, (int) ( $repeat_rule['start_index'] ?? 1 ) );
				$name_pat    = isset( $repeat_rule['name_suffix'] ) && '' !== $repeat_rule['name_suffix'] ? (string) $repeat_rule['name_suffix'] : '_{n}';
				$label_pat   = isset( $repeat_rule['label_suffix'] ) ? (string) $repeat_rule['label_suffix'] : ' {n}';

				for ( $i = $start; $i < $start + $n; $i++ ) {
					$name_sfx  = str_replace( '{n}', (string) $i, $name_pat );
					$label_sfx = str_replace( '{n}', (string) $i, $label_pat );
						$this->renderSectionInstance( $section, $fields, $section_types_value, $section_hidden, $name_sfx, $label_sfx );
				}
			} else {
					$this->renderSectionInstance( $section, $fields, $section_types_value, $section_hidden, '', '' );
			}
		}
	}

	/**
	 * Render a single section instance (used for both normal and repeated sections).
	 *
	 * @param \CoderEmbassy\CheckoutFieldEditor\Models\CA_Section  $section         Section model.
	 * @param \CoderEmbassy\CheckoutFieldEditor\Models\CA_Field[]  $all_fields      All enabled fields (filtered inside).
	 * @param string                                $types_value     Value for data-ca-types attribute (comma-separated customer type keys).
	 * @param bool                                  $is_hidden       Whether to hide the section via inline style.
	 * @param string                                $name_suffix     Suffix appended to every field_key (e.g. "_1").
	 * @param string                                $label_suffix    Suffix appended to section title and field labels (e.g. " 1").
	 */
	private function renderSectionInstance(
		\CoderEmbassy\CheckoutFieldEditor\Models\CA_Section $section,
		array $all_fields,
		string $types_value,
		bool $is_hidden,
		string $name_suffix,
		string $label_suffix
	): void {
		$section_id_raw = '' !== $name_suffix
			? (string) $section->section_key . $name_suffix
			: (string) $section->section_key;

		$type_attr = '' !== $types_value
			? ' data-ca-types="' . esc_attr( $types_value ) . '"'
			: '';

		$hidden_attr = $is_hidden ? ' style="display:none"' : '';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="ca-section" id="ca-section-' . esc_attr( $section_id_raw ) . '"' . $type_attr . $hidden_attr . '>';

		if ( '' !== $section->title ) {
			$section_title = $this->translateString( (string) $section->title, 'section_title_' . $section->section_key );
			echo '<h3 class="ca-section__title">' . esc_html( $section_title . $label_suffix ) . '</h3>';
		}

		if ( '' !== $section->description ) {
			$section_desc = $this->translateString( (string) $section->description, 'section_description_' . $section->section_key );
			echo '<p class="ca-section__desc">' . esc_html( $section_desc ) . '</p>';
		}

		foreach ( $all_fields as $field ) {
			if ( (int) $field->section_id !== (int) $section->id ) {
				continue;
			}
			$this->renderField( $field, $name_suffix, $label_suffix );
		}

		echo '</div>';
	}

	/**
	 * Calculate how many times a section should repeat based on the cart.
	 *
	 * @param array<string, mixed> $rule Repeat rule settings.
	 * @return int  Number of repetitions (minimum 1).
	 */
	private function getRepeatCount( array $rule ): int {
		if ( ! \function_exists( 'WC' ) || ! \WC()->cart ) {
			return 1;
		}

		$repeat_for = (string) ( $rule['repeat_for'] ?? 'product_quantity' );
		$apply_to   = (string) ( $rule['apply_to'] ?? 'all' );
		$items      = \WC()->cart->get_cart();
		if ( ! is_array( $items ) || empty( $items ) ) {
			return 1;
		}

		if ( 'products' === $apply_to ) {
			$product_ids = array_values(
				array_filter(
					array_map( 'absint', is_array( $rule['product_ids'] ?? null ) ? $rule['product_ids'] : array() )
				)
			);
			if ( ! empty( $product_ids ) ) {
				$items = array_filter(
					$items,
					static function ( array $item ) use ( $product_ids ): bool {
						$product_id = (int) ( $item['product_id'] ?? 0 );
						return $product_id > 0 && in_array( $product_id, $product_ids, true );
					}
				);
			}
		} elseif ( 'categories' === $apply_to ) {
			$category_ids = array_values(
				array_filter(
					array_map( 'absint', is_array( $rule['category_ids'] ?? null ) ? $rule['category_ids'] : array() )
				)
			);
			if ( ! empty( $category_ids ) ) {
				$items = array_filter(
					$items,
					static function ( array $item ) use ( $category_ids ): bool {
						$product_id = (int) ( $item['product_id'] ?? 0 );
						if ( $product_id < 1 ) {
							return false;
						}
						$terms = get_the_terms( $product_id, 'product_cat' );
						if ( ! is_array( $terms ) || empty( $terms ) ) {
							return false;
						}
						foreach ( $terms as $term ) {
							if ( in_array( (int) $term->term_id, $category_ids, true ) ) {
								return true;
							}
						}
						return false;
					}
				);
			}
		}

		if ( empty( $items ) ) {
			return 1;
		}

		if ( 'cart_count' === $repeat_for ) {
			// Number of distinct scoped line items in the cart.
			return max( 1, count( $items ) );
		}

		// Default: total quantity of scoped line items.
		$total_qty = 0;
		foreach ( $items as $item ) {
			$total_qty += max( 0, (int) ( $item['quantity'] ?? 0 ) );
		}

		return max( 1, $total_qty );
	}

	/**
	 * @param CA_Field $field        The field to render.
	 * @param string   $name_suffix  Appended to field_key for name/id HTML attributes (e.g. "_1").
	 * @param string   $label_suffix Appended to the visible label (e.g. " 1").
	 */
	public function renderField( CA_Field $field, string $name_suffix = '', string $label_suffix = '' ): void {
		$context      = $this->buildContext();
		$current_type = $this->customerTypeManager->getCurrentTypeSlug();
		$placeholder  = $this->translateString( (string) $field->placeholder, 'field_placeholder_' . $field->field_key );
		$description  = $this->translateString( (string) $field->description, 'field_description_' . $field->field_key );
		$label        = ( isset( $field->meta['labels_by_type'][ $current_type ] ) && is_string( $field->meta['labels_by_type'][ $current_type ] ) )
			? $this->translateString( $field->meta['labels_by_type'][ $current_type ], 'field_label_' . $field->field_key . '_' . sanitize_key( $current_type ) )
			: $this->translateString( (string) $field->label, 'field_label_' . $field->field_key );

		// Phase 11: Apply label suffix for repeated sections (skip structural/display types).
		if ( '' !== $label_suffix && ! in_array( $field->type, array( 'heading', 'paragraph', 'hidden' ), true ) ) {
			$label .= $label_suffix;
		}

		// Effective HTML key — field_key + suffix for name/id/for attributes (e.g. "attendee_name_1").
		$effective_key = '' !== $name_suffix ? $field->field_key . $name_suffix : $field->field_key;

		$value        = $this->getFieldValue( $effective_key );
		$is_required  = (bool) $field->required;
		$css_class    = isset( $field->meta['css_class'] ) ? sanitize_html_class( (string) $field->meta['css_class'] ) : '';
		$show_optional = (bool) ( $this->getSettings()['show_optional_label'] ?? true );
		// For checkbox: label wraps the input so both sit on the same line.
		$inline_label  = false;

		$input_html = '';
		switch ( $field->type ) {
			case 'textarea':
				$input_html = '<textarea name="' . esc_attr( $effective_key ) . '" id="' . esc_attr( $effective_key ) . '" rows="4" class="input-text">' . esc_textarea( $value ) . '</textarea>';
				break;

			case 'select':
				$input_html = '<select name="' . esc_attr( $effective_key ) . '" id="' . esc_attr( $effective_key ) . '" class="select">';
				foreach ( $field->options as $option ) {
					$opt_value   = is_array( $option ) ? (string) ( $option['value'] ?? '' ) : '';
					$opt_label   = is_array( $option ) ? (string) ( $option['label'] ?? $opt_value ) : '';
					$opt_label   = $this->translateString( $opt_label, 'field_option_' . $field->field_key . '_' . $opt_value );
					$input_html .= '<option value="' . esc_attr( $opt_value ) . '"' . selected( $value, $opt_value, false ) . '>' . esc_html( $opt_label ) . '</option>';
				}
				$input_html .= '</select>';
				break;

			case 'radio':
				foreach ( $field->options as $option ) {
					$opt_value   = is_array( $option ) ? (string) ( $option['value'] ?? '' ) : '';
					$opt_label   = is_array( $option ) ? (string) ( $option['label'] ?? $opt_value ) : '';
					$opt_label   = $this->translateString( $opt_label, 'field_option_' . $field->field_key . '_' . $opt_value );
					$input_html .= '<label><input type="radio" name="' . esc_attr( $effective_key ) . '" value="' . esc_attr( $opt_value ) . '"' . checked( $value, $opt_value, false ) . '> ' . esc_html( $opt_label ) . '</label>';
				}
				break;

			case 'checkbox':
				// Inline label: <label><input /> Label text</label> — both on one line.
				$inline_label = true;
				$req_suffix   = $is_required
					? ' <abbr class="required" title="' . esc_attr__( 'required', 'coderembassy-checkout-field-editor' ) . '">*</abbr>'
					: ( $show_optional ? ' <span class="optional">(' . esc_html__( 'optional', 'coderembassy-checkout-field-editor' ) . ')</span>' : '' );
				$input_html   = '<label for="' . esc_attr( $effective_key ) . '" class="ca-checkbox-label">'
					. '<input type="checkbox" name="' . esc_attr( $effective_key ) . '" id="' . esc_attr( $effective_key ) . '" value="1"' . checked( $value, '1', false ) . ' />'
					. ' ' . esc_html( $label ) . $req_suffix
					. '</label>';
				break;

			case 'multiselect':
				$input_html = '<select name="' . esc_attr( $effective_key ) . '[]" id="' . esc_attr( $effective_key ) . '" class="select" multiple>';
				foreach ( $field->options as $option ) {
					$opt_value   = is_array( $option ) ? (string) ( $option['value'] ?? '' ) : '';
					$opt_label   = is_array( $option ) ? (string) ( $option['label'] ?? $opt_value ) : '';
					$opt_label   = $this->translateString( $opt_label, 'field_option_' . $field->field_key . '_' . $opt_value );
					$selected    = in_array( $opt_value, (array) $value, true ) ? ' selected' : '';
					$input_html .= '<option value="' . esc_attr( $opt_value ) . '"' . $selected . '>' . esc_html( $opt_label ) . '</option>';
				}
				$input_html .= '</select>';
				break;

			case 'checkbox_group':
				foreach ( $field->options as $option ) {
					$opt_value   = is_array( $option ) ? (string) ( $option['value'] ?? '' ) : '';
					$opt_label   = is_array( $option ) ? (string) ( $option['label'] ?? $opt_value ) : '';
					$opt_label   = $this->translateString( $opt_label, 'field_option_' . $field->field_key . '_' . $opt_value );
					$checked_arr = is_array( $value ) ? $value : explode( ',', (string) $value );
					$input_html .= '<label class="ca-checkbox-item"><input type="checkbox" name="' . esc_attr( $effective_key ) . '[]" value="' . esc_attr( $opt_value ) . '"' . ( in_array( $opt_value, $checked_arr, true ) ? ' checked' : '' ) . '> ' . esc_html( $opt_label ) . '</label>';
				}
				break;

			case 'date':
				$ca_settings = $this->getSettings();
				$date_fmt    = isset( $ca_settings['date_format'] ) && is_string( $ca_settings['date_format'] ) && '' !== trim( $ca_settings['date_format'] )
					? trim( $ca_settings['date_format'] )
					: (string) \get_option( 'date_format', 'Y-m-d' );
				$date_title  = sprintf(
					/* translators: %s: PHP date format string (e.g. F j, Y). */
					\__( 'Pick a date. Values are stored as YYYY-MM-DD; displayed elsewhere using: %s', 'coderembassy-checkout-field-editor' ),
					$date_fmt
				);
				$input_html = '<input type="date" name="' . esc_attr( $effective_key ) . '" id="' . esc_attr( $effective_key ) . '" value="' . esc_attr( $value ) . '" class="input-text" title="' . esc_attr( $date_title ) . '" />';
				break;

			case 'file':
				$accept = ! empty( $field->meta['accept'] ) ? ' accept="' . esc_attr( (string) $field->meta['accept'] ) . '"' : '';
				$input_html = '<div class="ca-file-upload-wrap" data-ca-file-wrap="1">'
					. '<input type="file" name="' . esc_attr( $effective_key ) . '" id="' . esc_attr( $effective_key ) . '" class="input-text ca-file-upload-input"' . $accept . ' />'
					. '<label for="' . esc_attr( $effective_key ) . '" class="ca-file-upload-btn">' . esc_html__( 'Choose file', 'coderembassy-checkout-field-editor' ) . '</label>'
					. '<span class="ca-file-upload-name" data-ca-file-name="1" data-empty-label="' . esc_attr__( 'No file selected', 'coderembassy-checkout-field-editor' ) . '">' . esc_html__( 'No file selected', 'coderembassy-checkout-field-editor' ) . '</span>'
					. '</div>';
				break;

			case 'repeater':
				$input_html = $this->renderRepeaterField( $field, $label );
				break;

			case 'heading':
				$input_html = '<h3>' . esc_html( $label ) . '</h3>';
				break;

			case 'paragraph':
				$input_html = '<p>' . esc_html( $description ) . '</p>';
				break;

			case 'custom_price':
				$currency   = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
				$input_html = '<span class="ca-currency">' . esc_html( $currency ) . '</span>'
					. '<input data-field-type="custom_price" type="number" name="' . esc_attr( $effective_key ) . '" id="' . esc_attr( $effective_key ) . '" step="0.01" min="0" value="' . esc_attr( $value ) . '" class="input-text" />';
				break;

			default:
				$type       = in_array( $field->type, array( 'text', 'email', 'tel', 'number', 'url' ), true ) ? $field->type : 'text';
				$input_html = '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $effective_key ) . '" id="' . esc_attr( $effective_key ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" class="input-text" />';
		}

		$classes             = trim( 'form-row ' . $css_class . ' ca-field ca-field--' . sanitize_html_class( $field->type ) . ' ca-field--' . sanitize_html_class( $field->field_key ) . ( $is_required ? ' is-required' : '' ) );
		$types_attr          = '';
		$req_conditions_attr = '';
		$pricing_attr        = '';
		$hidden_style        = '';

		if ( ! empty( $field->customer_types ) && is_array( $field->customer_types ) ) {
			$slugs      = array_filter( array_map( 'sanitize_key', $field->customer_types ) );
			$types_attr = ' data-ca-types="' . esc_attr( implode( ',', $slugs ) ) . '"';
			if ( '' !== $current_type && ! in_array( $current_type, $slugs, true ) ) {
				$hidden_style = ' style="display:none"';
			}
		}

		if ( ! empty( $field->required_conditions['groups'] ) ) {
			$req_conditions_attr = ' data-ca-req-conditions="' . esc_attr( (string) \wp_json_encode( $field->required_conditions ) ) . '"';
		}
		if ( ! empty( $field->pricing_rules ) && is_array( $field->pricing_rules ) ) {
			$pricing_attr = ' data-ca-has-pricing="1"';
		}

		// Outer label — suppressed for checkbox (inlined) and heading/paragraph.
		$outer_label = '';
		if ( ! $inline_label && ! in_array( $field->type, array( 'heading', 'paragraph', 'repeater' ), true ) ) {
			$outer_label = '<label for="' . esc_attr( $effective_key ) . '">'
				. esc_html( $label )
				. ( $is_required
					? ' <abbr class="required" title="' . esc_attr__( 'required', 'coderembassy-checkout-field-editor' ) . '">*</abbr>'
					: ( $show_optional ? ' <span class="optional">(' . esc_html__( 'optional', 'coderembassy-checkout-field-editor' ) . ')</span>' : '' ) )
				. '</label>';
		}

		// data-key retains the original field_key so JS visibility / conditions still match.
		$wrapper = '<p class="' . esc_attr( $classes ) . '" id="' . esc_attr( $effective_key ) . '_field" data-key="' . esc_attr( $field->field_key ) . '"' . $types_attr . $req_conditions_attr . $pricing_attr . $hidden_style . '>'
			. '<span id="ca-field-' . esc_attr( $effective_key ) . '" class="ca-field-inner">'
			. $outer_label
			. $input_html
			. ( '' !== (string) $description ? '<span class="ca-field-description">' . esc_html( $description ) . '</span>' : '' )
			. '</span></p>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->validationEngine->filterFieldHtml( $wrapper, $field, $context );
	}

	/**
	 * @return string HTML only (no outer form-row wrapper).
	 */
	private function renderRepeaterField( CA_Field $field, string $display_label ): string {
		$sub_fields = $field->meta['sub_fields'] ?? array();
		$max_rows   = isset( $field->meta['repeater_max_rows'] ) ? (int) $field->meta['repeater_max_rows'] : 0;

		if ( empty( $sub_fields ) || ! is_array( $sub_fields ) ) {
			return '';
		}

		$key      = $field->field_key;
		$key_attr = esc_attr( $key );
		$label    = esc_html( $display_label );
		$required = $field->required ? ' <abbr class="required" title="required">*</abbr>' : '';
		$max_attr = $max_rows > 0 ? ' data-max-rows="' . (int) $max_rows . '"' : '';

		$out  = '<div class="ca-repeater-wrap" id="ca-repeater-' . $key_attr . '"' . $max_attr . '>';
		$out .= '<label class="ca-repeater-label">' . $label . $required . '</label>';
		$out .= '<div class="ca-repeater-rows">';
		$out .= $this->buildRepeaterRow( $key, 0, $sub_fields, false, $field->field_key );
		$out .= '</div>';
		$out .= '<button type="button" class="ca-repeater-add" data-key="' . $key_attr . '">'
			. esc_html__( '+ Add another', 'coderembassy-checkout-field-editor' )
			. '</button>';

		$template_html = $this->buildRepeaterRow( $key, '__IDX__', $sub_fields, true, $field->field_key );
		$template_json = \wp_json_encode( $template_html );
		$max_js        = $max_rows > 0 ? (int) $max_rows : 999;
		$out          .= '<script>';
		$out          .= '(function(){';
		$out          .= 'var tmpl=' . $template_json . ';';
		$out          .= 'var wrap=document.getElementById("ca-repeater-' . $key_attr . '");';
		$out          .= 'if(!wrap)return;';
		$out          .= 'var rows=wrap.querySelector(".ca-repeater-rows");';
		$out          .= 'var btn=wrap.querySelector(".ca-repeater-add");';
		$out          .= 'if(!btn||!rows)return;';
		$out          .= 'var maxRows=' . $max_js . ';';
		$out          .= 'var idx=1;';
		$out          .= 'btn.addEventListener("click",function(){';
		$out          .= 'if(rows.querySelectorAll(".ca-repeater-row").length>=maxRows)return;';
		$out          .= 'var html=tmpl.replace(/__IDX__/g,String(idx++));';
		$out          .= 'var div=document.createElement("div");div.innerHTML=html;';
		$out          .= 'var row=div.firstElementChild;if(!row)return;';
		$out          .= 'rows.appendChild(row);';
		$out          .= 'var rm=row.querySelector(".ca-repeater-remove");if(rm)rm.addEventListener("click",function(){row.remove();});';
		$out          .= '});';
		$out          .= '})();';
		$out          .= '</script>';
		$out          .= '</div>';

		return $out;
	}

	/**
	 * @param array<int, mixed> $sub_fields
	 */
	private function buildRepeaterRow( string $key, int|string $row_idx, array $sub_fields, bool $removable, string $field_key_for_i18n ): string {
		$out = '<div class="ca-repeater-row">';

		if ( $removable ) {
			$out .= '<button type="button" class="ca-repeater-remove" title="' . esc_attr__( 'Remove row', 'coderembassy-checkout-field-editor' ) . '">&times;</button>';
		}

		foreach ( $sub_fields as $sf ) {
			$sf       = is_array( $sf ) ? $sf : (array) $sf;
			$sf_key   = sanitize_key( (string) ( $sf['key'] ?? '' ) );
			if ( '' === $sf_key ) {
				continue;
			}
			$sf_label_raw = (string) ( $sf['label'] ?? '' );
			$sf_label     = esc_html( $this->translateString( $sf_label_raw, 'field_repeater_sub_' . $field_key_for_i18n . '_' . $sf_key ) );
			$sf_type  = (string) ( $sf['type'] ?? 'text' );
			$sf_req   = ! empty( $sf['required'] );
			$name     = esc_attr( $key . '[' . $row_idx . '][' . $sf_key . ']' );
			$req_attr = $sf_req ? ' required' : '';
			$req_star = $sf_req ? ' <abbr class="required" title="' . esc_attr__( 'required', 'coderembassy-checkout-field-editor' ) . '">*</abbr>' : '';

			$out .= '<div class="ca-repeater-subfield">';
			$out .= '<label>' . $sf_label . $req_star . '</label>';

			if ( 'textarea' === $sf_type ) {
				$out .= '<textarea name="' . $name . '" class="input-text"' . $req_attr . '></textarea>';
			} elseif ( 'checkbox' === $sf_type ) {
				$out .= '<input type="checkbox" name="' . $name . '" value="1"' . $req_attr . ' />';
			} else {
				$input_type = match ( $sf_type ) {
					'email'  => 'email',
					'phone'  => 'tel',
					'number' => 'number',
					default  => 'text',
				};
				$out .= '<input type="' . esc_attr( $input_type ) . '" name="' . $name . '" class="input-text"' . $req_attr . ' />';
			}

			$out .= '</div>';
		}

		$out .= '</div>';

		return $out;
	}

	public function prependCartItemThumb( string $name, array $cart_item, string $cart_item_key ): string {
		if ( ! function_exists( 'is_checkout' ) || ! \is_checkout() ) {
			return $name;
		}
		$product = $cart_item['data'] ?? null;
		if ( ! $product instanceof \WC_Product ) {
			return $name;
		}
		$thumb = $product->get_image( array( 40, 40 ), array( 'class' => 'ca-order-thumb' ) );
		return '<span class="ca-order-thumb-wrap">' . $thumb . '</span>' . $name;
	}

	public function renderQuantityControl( string $product_quantity, array $cart_item, string $cart_item_key ): string {
		if ( ! function_exists( 'is_checkout' ) || ! \is_checkout() ) {
			return $product_quantity;
		}
		$qty   = (int) ( $cart_item['quantity'] ?? 0 );
		$nonce = \wp_create_nonce( 'ca_cart_qty' );
		return sprintf(
			'<span class="ca-qty-control" data-key="%s" data-nonce="%s">'
			. '<button type="button" class="ca-qty-btn ca-qty-minus" aria-label="%s">&#8722;</button>'
			. '<span class="ca-qty-value">%d</span>'
			. '<button type="button" class="ca-qty-btn ca-qty-plus" aria-label="%s">&#43;</button>'
			. '<button type="button" class="ca-qty-btn ca-qty-remove" aria-label="%s" title="%s">&#10005;</button>'
			. '</span>',
			esc_attr( $cart_item_key ),
			esc_attr( $nonce ),
			esc_attr__( 'Decrease quantity', 'coderembassy-checkout-field-editor' ),
			$qty,
			esc_attr__( 'Increase quantity', 'coderembassy-checkout-field-editor' ),
			esc_attr__( 'Remove item', 'coderembassy-checkout-field-editor' ),
			esc_attr__( 'Remove item', 'coderembassy-checkout-field-editor' )
		);
	}

	public function ajaxUpdateCartQty(): void {
		\check_ajax_referer( 'ca_cart_qty', 'nonce' );
		if ( ! function_exists( 'WC' ) || ! \WC()->cart ) {
			\wp_send_json_error( array( 'message' => 'Cart not available.' ), 400 );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$cart_item_key = \sanitize_text_field( \wp_unslash( $_POST['cart_item_key'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$qty = isset( $_POST['qty'] ) ? (int) $_POST['qty'] : 0;

		if ( '' === $cart_item_key ) {
			\wp_send_json_error( array( 'message' => 'Invalid cart item.' ), 400 );
			return;
		}

		if ( $qty < 1 ) {
			\WC()->cart->remove_cart_item( $cart_item_key );
		} else {
			\WC()->cart->set_quantity( $cart_item_key, $qty );
		}

		\wp_send_json_success( array( 'count' => \WC()->cart->get_cart_contents_count() ) );
	}

	public function applyFees( \WC_Cart $cart ): void {
		// Pricing engine is a Pro feature — not available in free version.
	}

	public function ajaxSetCustomerType(): void {
		$nonce = isset( $_POST['_ca_type_nonce'] )
			? \sanitize_text_field( \wp_unslash( (string) $_POST['_ca_type_nonce'] ) )
			: ( isset( $_POST['nonce'] ) ? \sanitize_text_field( \wp_unslash( (string) $_POST['nonce'] ) ) : '' );
		if ( ! wp_verify_nonce( (string) $nonce, 'ca_set_type' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
		}
		$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( (string) $_POST['type'] ) ) : ( isset( $_POST['value'] ) ? sanitize_key( wp_unslash( (string) $_POST['value'] ) ) : '' );
		if ( '' === $type ) {
			wp_send_json_error( array( 'message' => 'Missing type.' ), 422 );
		}
		$this->customerTypeManager->setCurrentType( $type );
		wp_send_json_success( array( 'type' => $type ) );
	}

	private function buildContext(): array {
		if ( null !== $this->context_cache ) {
			return $this->context_cache;
		}

		$cart_total    = 0.0;
		$cart_subtotal = 0.0;
		$coupon_codes  = array();
		$cart_products = array();
		$cart_categories = array();
		if ( function_exists( 'WC' ) && WC()->cart ) {
			$cart_total    = (float) WC()->cart->get_total( 'edit' );
			$cart_subtotal = (float) WC()->cart->get_subtotal();
			$coupon_codes  = WC()->cart->get_applied_coupons();
			foreach ( WC()->cart->get_cart() as $item ) {
				$product_id = (int) ( $item['product_id'] ?? 0 );
				if ( $product_id > 0 ) {
					$cart_products[] = $product_id;
					$terms = get_the_terms( $product_id, 'product_cat' );
					if ( is_array( $terms ) ) {
						foreach ( $terms as $term ) {
							$cart_categories[] = (int) $term->term_id;
						}
					}
				}
			}
			$cart_products   = array_values( array_unique( $cart_products ) );
			$cart_categories = array_values( array_unique( $cart_categories ) );
		}

		$field_values = array();
		foreach ( $this->fieldRepository->findAll( array( 'enabled' => true ) ) as $f ) {
			$field_values[ $f->field_key ] = $this->getFieldValue( $f->field_key );
		}

		$country = '';
		$state   = '';
		if ( function_exists( 'WC' ) && WC()->customer ) {
			$country = (string) WC()->customer->get_billing_country();
			$state   = (string) WC()->customer->get_billing_state();
		}

		$payment_method  = '';
		$shipping_method = '';
		if ( function_exists( 'WC' ) && WC()->session ) {
			$payment_method  = (string) WC()->session->get( 'chosen_payment_method', '' );
			$chosen_shipping = WC()->session->get( 'chosen_shipping_methods', array( '' ) );
			$shipping_method = is_array( $chosen_shipping ) ? (string) ( $chosen_shipping[0] ?? '' ) : '';
		}

		$user      = wp_get_current_user();
		$role      = is_array( $user->roles ) && ! empty( $user->roles ) ? (string) $user->roles[0] : 'guest';
		$posted_type = '';
		$nonce_ok    = isset( $_POST['_ca_type_nonce'] )
			? \wp_verify_nonce( \sanitize_text_field( \wp_unslash( (string) $_POST['_ca_type_nonce'] ) ), 'ca_set_type' )
			: false;
		if ( $nonce_ok && isset( $_POST['ca_customer_type'] ) ) {
			$posted_type = \sanitize_key( \wp_unslash( (string) $_POST['ca_customer_type'] ) );
		}
		$type_slug = '' !== $posted_type ? $posted_type : $this->customerTypeManager->getCurrentTypeSlug();

		$this->context_cache = array(
			'customer_type'   => $type_slug,
			'cart_total'      => $cart_total,
			'cart_subtotal'   => $cart_subtotal,
			'cart_products'   => $cart_products,
			'cart_categories' => $cart_categories,
			'country'         => $country,
			'state'           => $state,
			'coupon_codes'    => array_map( 'strval', $coupon_codes ),
			'payment_method'  => $payment_method,
			'shipping_method' => $shipping_method,
			'user_role'       => $role,
			'user_id'         => get_current_user_id(),
			'field_values'    => $field_values,
		);

		return $this->context_cache;
	}

	private function getFieldValue( string $field_key ): string|array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce_ok = isset( $_POST['_ca_type_nonce'] )
			? \wp_verify_nonce( \sanitize_text_field( \wp_unslash( (string) $_POST['_ca_type_nonce'] ) ), 'ca_set_type' )
			: false;
		if ( $nonce_ok && isset( $_POST[ $field_key ] ) ) {
			$raw = wp_unslash( $_POST[ $field_key ] );
			if ( is_array( $raw ) ) {
				return array_map(
					static fn ( $v ): string => sanitize_text_field( (string) $v ),
					$raw
				);
			}
			return sanitize_text_field( (string) $raw );
		}

		// WooCommerce checkout AJAX requests often send serialized form data in post_data.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $nonce_ok && isset( $_POST['post_data'] ) && '' !== (string) $_POST['post_data'] ) {
			$form_data = array();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- post_data is a URL-encoded query string.
			parse_str( wp_unslash( (string) $_POST['post_data'] ), $form_data );
			if ( array_key_exists( $field_key, $form_data ) ) {
				$raw = $form_data[ $field_key ];
				if ( is_array( $raw ) ) {
					return array_map(
						static fn ( $v ): string => sanitize_text_field( (string) $v ),
						$raw
					);
				}
				return sanitize_text_field( (string) $raw );
			}
		}

		if ( function_exists( 'WC' ) && WC()->checkout() ) {
			return (string) ( WC()->checkout()->get_value( $field_key ) ?? '' );
		}

		return '';
	}

	private function getSettings(): array {
		if ( null !== $this->settings_cache ) {
			return $this->settings_cache;
		}
		$raw                  = \get_option( 'checkout_architect_settings', '' );
		$this->settings_cache = is_string( $raw ) && '' !== $raw
			? ( json_decode( $raw, true ) ?? array() )
			: array();
		return $this->settings_cache;
	}

	/**
	 * Pass a string through WPML / Polylang translation if available.
	 * Falls back to the original string on vanilla WP — no errors on standard installs.
	 *
	 * @param string $value   The source-language value.
	 * @param string $context WPML string name used during registration.
	 * @return string Translated value when supported; otherwise the original value.
	 */
	private function translateString( string $value, string $context ): string {
		if ( '' === $value ) {
			return $value;
		}

		$domain = 'coderembassy-checkout-field-editor';

		// WPML String Translation.
		if ( \function_exists( 'icl_t' ) ) {
			try {
				$translated = (string) \icl_t( $domain, (string) $context, $value );
				if ( '' !== $translated && $translated !== $value ) {
					return $translated;
				}
			} catch ( \Throwable $e ) {
				// Ignore translation errors; fall back to original string.
			}
		}

		// Polylang string translation.
		if ( \function_exists( 'pll__' ) ) {
			try {
				$translated = (string) \pll__( $value );
				if ( '' !== $translated ) {
					return $translated;
				}
			} catch ( \Throwable $e ) {
				// Ignore translation errors; fall back to original string.
			}
		}

		return $value;
	}
}
	 