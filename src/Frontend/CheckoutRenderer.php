<?php
namespace CoderEmbassy\CheckoutFieldsManager\Frontend;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\ExtensionPoints;
use CoderEmbassy\CheckoutFieldsManager\Models\CECFM_Field;
use CoderEmbassy\CheckoutFieldsManager\Modules\CustomerTypes\CustomerTypeManager;
use CoderEmbassy\CheckoutFieldsManager\Modules\Fields\FieldRepository;
use CoderEmbassy\CheckoutFieldsManager\Modules\Fields\VisibilityResolver;
use CoderEmbassy\CheckoutFieldsManager\Modules\Sections\SectionRepository;
use CoderEmbassy\CheckoutFieldsManager\Modules\Validation\ValidationEngine;

class CheckoutRenderer {
	/** @var array<string, mixed>|null Runtime context cache — built once per request. */
	private ?array $context_cache = null;
	private ?array $settings_cache = null;

	public function __construct(
		private FieldRepository $fieldRepository,
		private VisibilityResolver $visibilityResolver,
		private ValidationEngine $validationEngine,
		private SectionRepository $sectionRepository,
		private CustomerTypeManager $customerTypeManager
	) {}

	public function filterCheckoutFields( array $fields ): array {
		try {
			return $this->doFilterCheckoutFields( $fields );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[Checkout Fields Manager] filterCheckoutFields error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
			return $fields;
		}
	}

	private function doFilterCheckoutFields( array $fields ): array {
		$raw       = \get_option( 'cecfm_native_fields', '{}' );
		$overrides = is_string( $raw ) ? ( json_decode( $raw, true ) ?? array() ) : ( is_array( $raw ) ? $raw : array() );

		$fields = $this->restoreHiddenOptionalFields( $fields, $overrides );

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
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					$request_method  = isset( $_SERVER['REQUEST_METHOD'] ) ? \sanitize_key( \wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) : 'get';
					// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the next line.
					$is_post         = ( 'post' === strtolower( $request_method ) );
					$nonce_ok        = isset( $_POST['_cecfm_type_nonce'] ) && \wp_verify_nonce( \sanitize_text_field( \wp_unslash( (string) $_POST['_cecfm_type_nonce'] ) ), 'cecfm_set_type' );
					if ( ! empty( $allowed_types ) && $is_post && $nonce_ok ) {
						$posted_type = isset( $_POST['cecfm_customer_type'] ) ? \sanitize_key( \wp_unslash( (string) $_POST['cecfm_customer_type'] ) ) : '';
						// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- post_data is a URL-encoded string, sanitized later via parse_str results.
						$raw_post_data = isset( $_POST['post_data'] ) ? \wp_unslash( (string) $_POST['post_data'] ) : '';
						if ( '' === $posted_type && '' !== $raw_post_data ) {
							$form_data = array();
							\parse_str( $raw_post_data, $form_data );
							$posted_type = isset( $form_data['cecfm_customer_type'] ) ? \sanitize_key( (string) $form_data['cecfm_customer_type'] ) : '';
						}
						$current_type = '' !== $posted_type ? $posted_type : $this->currentTypeSlug();
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
				error_log( '[Checkout Fields Manager] filterNativeFieldHtml error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
			return $field_html;
		}
	}

	private function doFilterNativeFieldHtml( string $field_html, string $key, array $args, mixed $value ): string {
		$raw       = \get_option( 'cecfm_native_fields', '{}' );
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
		$posted_type  = isset( $_POST['cecfm_customer_type'] ) ? \sanitize_key( \wp_unslash( (string) $_POST['cecfm_customer_type'] ) ) : '';
		$current_type = '' !== $posted_type ? $posted_type : '';
		if ( '' === $current_type ) {
			try {
				$current_type = $this->currentTypeSlug();
			} catch ( \Throwable $e ) {
			}
		}

		$hidden     = ( '' !== $current_type && ! in_array( $current_type, $types, true ) );
		$field_html = preg_replace(
			'/^(<p\b)/',
			'$1 data-cecfm-types="' . $types_str . '"' . ( $hidden ? ' style="display:none"' : '' ),
			ltrim( $field_html ),
			1
		);

		return $field_html;
	}

	public function maybeAddFileEnctypeScript(): void {
		// Script moved to FrontendModule via data attribute or separate JS.
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
			function ( CECFM_Field $field ) use ( $position ): bool {
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
			static fn ( CECFM_Field $a, CECFM_Field $b ): int => ( (int) $a->priority ) <=> ( (int) $b->priority )
		);

		echo '<div class="cecfm-field-group cecfm-field-group--' . esc_attr( $position ) . '">';
		foreach ( $fields as $field ) {
			$this->renderField( $field );
		}
		echo '</div>';
	}

	private function isLegacyFieldPositionMatch( CECFM_Field $field, string $position ): bool {
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
		$current_slug = $this->currentTypeSlug();

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
	 * @param \CoderEmbassy\CheckoutFieldsManager\Models\CECFM_Section  $section         Section model.
	 * @param \CoderEmbassy\CheckoutFieldsManager\Models\CECFM_Field[]  $all_fields      All enabled fields (filtered inside).
	 * @param string                                $types_value     Value for data-cecfm-types attribute (comma-separated customer type keys).
	 * @param bool                                  $is_hidden       Whether to hide the section via inline style.
	 * @param string                                $name_suffix     Suffix appended to every field_key (e.g. "_1").
	 * @param string                                $label_suffix    Suffix appended to section title and field labels (e.g. " 1").
	 */
	private function renderSectionInstance(
		\CoderEmbassy\CheckoutFieldsManager\Models\CECFM_Section $section,
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
			? ' data-cecfm-types="' . esc_attr( $types_value ) . '"'
			: '';

		$hidden_attr = $is_hidden ? ' style="display:none"' : '';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="cecfm-section" id="cecfm-section-' . esc_attr( $section_id_raw ) . '"' . $type_attr . $hidden_attr . '>';

		if ( '' !== $section->title ) {
			$section_title = $this->translateString( (string) $section->title, 'section_title_' . $section->section_key );
			echo '<h3 class="cecfm-section__title">' . esc_html( $section_title . $label_suffix ) . '</h3>';
		}

		if ( '' !== $section->description ) {
			$section_desc = $this->translateString( (string) $section->description, 'section_description_' . $section->section_key );
			echo '<p class="cecfm-section__desc">' . esc_html( $section_desc ) . '</p>';
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
	 * @param CECFM_Field $field        The field to render.
	 * @param string   $name_suffix  Appended to field_key for name/id HTML attributes (e.g. "_1").
	 * @param string   $label_suffix Appended to the visible label (e.g. " 1").
	 */
	public function renderField( CECFM_Field $field, string $name_suffix = '', string $label_suffix = '' ): void {
		$context      = $this->buildContext();
		$current_type = $this->currentTypeSlug();
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
					? ' <abbr class="required" title="' . esc_attr__( 'required', 'coderembassy-checkout-fields-manager' ) . '">*</abbr>'
					: ( $show_optional ? ' <span class="optional">(' . esc_html__( 'optional', 'coderembassy-checkout-fields-manager' ) . ')</span>' : '' );
				$input_html   = '<label for="' . esc_attr( $effective_key ) . '" class="cecfm-checkbox-label">'
					. '<input type="checkbox" name="' . esc_attr( $effective_key ) . '" id="' . esc_attr( $effective_key ) . '" value="1"' . checked( $value, '1', false ) . ' />'
					. ' ' . esc_html( $label ) . $req_suffix
					. '</label>';
				break;

			case 'checkbox_group':
				foreach ( $field->options as $option ) {
					$opt_value   = is_array( $option ) ? (string) ( $option['value'] ?? '' ) : '';
					$opt_label   = is_array( $option ) ? (string) ( $option['label'] ?? $opt_value ) : '';
					$opt_label   = $this->translateString( $opt_label, 'field_option_' . $field->field_key . '_' . $opt_value );
					$checked_arr = is_array( $value ) ? $value : explode( ',', (string) $value );
					$input_html .= '<label class="cecfm-checkbox-item"><input type="checkbox" name="' . esc_attr( $effective_key ) . '[]" value="' . esc_attr( $opt_value ) . '"' . ( in_array( $opt_value, $checked_arr, true ) ? ' checked' : '' ) . '> ' . esc_html( $opt_label ) . '</label>';
				}
				break;

			case 'date':
				$cecfm_settings = $this->getSettings();
				$date_fmt    = isset( $cecfm_settings['date_format'] ) && is_string( $cecfm_settings['date_format'] ) && '' !== trim( $cecfm_settings['date_format'] )
					? trim( $cecfm_settings['date_format'] )
					: (string) \get_option( 'date_format', 'Y-m-d' );
				$date_title  = sprintf(
					/* translators: %s: PHP date format string (e.g. F j, Y). */
					\__( 'Pick a date. Values are stored as YYYY-MM-DD; displayed elsewhere using: %s', 'coderembassy-checkout-fields-manager' ),
					$date_fmt
				);
				$input_html = '<input type="date" name="' . esc_attr( $effective_key ) . '" id="' . esc_attr( $effective_key ) . '" value="' . esc_attr( $value ) . '" class="input-text" title="' . esc_attr( $date_title ) . '" />';
				break;

			case 'heading':
				$input_html = '<h3>' . esc_html( $label ) . '</h3>';
				break;

			case 'paragraph':
				$input_html = '<p>' . esc_html( $description ) . '</p>';
				break;

			default:
				if ( ! in_array( $field->type, array( 'text', 'email', 'tel', 'phone', 'number', 'url' ), true ) ) {
					// A type this plugin does not implement — an add-on supplies it
					// and draws the markup through the cecfm_field_html filter.
					// Falling back to a text input here would silently downgrade
					// the field and mangle its value on submit.
					$input_html = '';
					break;
				}

				$input_type = ( 'phone' === $field->type ) ? 'tel' : $field->type;
				$input_html = '<input type="' . esc_attr( $input_type ) . '" name="' . esc_attr( $effective_key ) . '" id="' . esc_attr( $effective_key ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" class="input-text" />';
		}

		$classes             = trim( 'form-row ' . $css_class . ' cecfm-field cecfm-field--' . sanitize_html_class( $field->type ) . ' cecfm-field--' . sanitize_html_class( $field->field_key ) . ( $is_required ? ' is-required' : '' ) );
		$types_attr          = '';
		$req_conditions_attr = '';
		$pricing_attr        = '';
		$hidden_style        = '';

		if ( ! empty( $field->customer_types ) && is_array( $field->customer_types ) ) {
			$slugs      = array_filter( array_map( 'sanitize_key', $field->customer_types ) );
			$types_attr = ' data-cecfm-types="' . esc_attr( implode( ',', $slugs ) ) . '"';
			if ( '' !== $current_type && ! in_array( $current_type, $slugs, true ) ) {
				$hidden_style = ' style="display:none"';
			}
		}

		if ( ! empty( $field->required_conditions['groups'] ) ) {
			$req_conditions_attr = ' data-cecfm-req-conditions="' . esc_attr( (string) \wp_json_encode( $field->required_conditions ) ) . '"';
		}
		if ( ! empty( $field->pricing_rules ) && is_array( $field->pricing_rules ) ) {
			$pricing_attr = ' data-cecfm-has-pricing="1"';
		}

		// Outer label — suppressed for checkbox (inlined) and heading/paragraph.
		$outer_label = '';
		if ( ! $inline_label && ! in_array( $field->type, array( 'heading', 'paragraph', 'repeater' ), true ) ) {
			$outer_label = '<label for="' . esc_attr( $effective_key ) . '">'
				. esc_html( $label )
				. ( $is_required
					? ' <abbr class="required" title="' . esc_attr__( 'required', 'coderembassy-checkout-fields-manager' ) . '">*</abbr>'
					: ( $show_optional ? ' <span class="optional">(' . esc_html__( 'optional', 'coderembassy-checkout-fields-manager' ) . ')</span>' : '' ) )
				. '</label>';
		}

		// data-key retains the original field_key so JS visibility / conditions still match.
		$wrapper = '<p class="' . esc_attr( $classes ) . '" id="' . esc_attr( $effective_key ) . '_field" data-key="' . esc_attr( $field->field_key ) . '"' . $types_attr . $req_conditions_attr . $pricing_attr . $hidden_style . '>'
			. '<span id="cecfm-field-' . esc_attr( $effective_key ) . '" class="cecfm-field-inner">'
			. $outer_label
			. $input_html
			. ( '' !== (string) $description ? '<span class="cecfm-field-description">' . esc_html( $description ) . '</span>' : '' )
			. '</span></p>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $wrapper is composed of escaped fragments.
		echo $this->validationEngine->filterFieldHtml( $wrapper, $field, $context );
	}

	public function prependCartItemThumb( string $name, array $cart_item, string $cart_item_key ): string {
		if ( ! function_exists( 'is_checkout' ) || ! \is_checkout() ) {
			return $name;
		}
		$product = $cart_item['data'] ?? null;
		if ( ! $product instanceof \WC_Product ) {
			return $name;
		}
		$thumb = $product->get_image( array( 40, 40 ), array( 'class' => 'cecfm-order-thumb' ) );
		return '<span class="cecfm-order-thumb-wrap">' . $thumb . '</span>' . $name;
	}

	public function renderQuantityControl( string $product_quantity, array $cart_item, string $cart_item_key ): string {
		if ( ! function_exists( 'is_checkout' ) || ! \is_checkout() ) {
			return $product_quantity;
		}
		$qty   = (int) ( $cart_item['quantity'] ?? 0 );
		$nonce = \wp_create_nonce( 'cecfm_cart_qty' );
		return sprintf(
			'<span class="cecfm-qty-control" data-key="%s" data-nonce="%s">'
			. '<button type="button" class="cecfm-qty-btn cecfm-qty-minus" aria-label="%s">&#8722;</button>'
			. '<span class="cecfm-qty-value">%d</span>'
			. '<button type="button" class="cecfm-qty-btn cecfm-qty-plus" aria-label="%s">&#43;</button>'
			. '<button type="button" class="cecfm-qty-btn cecfm-qty-remove" aria-label="%s" title="%s">&#10005;</button>'
			. '</span>',
			esc_attr( $cart_item_key ),
			esc_attr( $nonce ),
			esc_attr__( 'Decrease quantity', 'coderembassy-checkout-fields-manager' ),
			$qty,
			esc_attr__( 'Increase quantity', 'coderembassy-checkout-fields-manager' ),
			esc_attr__( 'Remove item', 'coderembassy-checkout-fields-manager' ),
			esc_attr__( 'Remove item', 'coderembassy-checkout-fields-manager' )
		);
	}

	public function ajaxUpdateCartQty(): void {
		\check_ajax_referer( 'cecfm_cart_qty', 'nonce' );
		if ( ! function_exists( 'WC' ) || ! \WC()->cart ) {
			\wp_send_json_error( array( 'message' => 'Cart not available.' ), 400 );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$cart_item_key = sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the AJAX entry point.
		$qty = (int) sanitize_text_field( wp_unslash( $_POST['qty'] ?? '' ) );

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

	/**
	 * The active customer type slug for this request.
	 *
	 * Customer types are a Pro capability. Free has no type engine, so this
	 * always resolves to an empty string unless the add-on answers the filter —
	 * and an empty slug means "no segmentation", which is the correct free
	 * behaviour: every enabled field is shown to everyone.
	 */
	/**
	 * The active customer type slug for this request.
	 *
	 * The free plugin manages a fixed Private/Company pair, so this resolves
	 * from its own manager. The filter stays so an add-on with more types can
	 * override the choice.
	 */
	private function currentTypeSlug(): string {
		$slug = $this->customerTypeManager->getCurrentTypeSlug();

		return (string) \apply_filters(
			ExtensionPoints::CURRENT_CUSTOMER_TYPE,
			$slug,
			array( 'source' => 'renderer' )
		);
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
			: \__( 'Customer Type', 'coderembassy-checkout-fields-manager' );
		$display_type   = $settings['switcher_display_type'] ?? 'buttons';

		echo '<div class="cecfm-type-switcher">';
		echo '<label>' . esc_html( $switcher_label ) . '</label>';

		if ( 'radio' === $display_type ) {
			echo '<div class="cecfm-type-radios">';
			foreach ( $types as $type ) {
				$uid = 'cecfm-type-radio-' . \sanitize_key( (string) $type->slug );
				echo '<label class="cecfm-type-radio-label" for="' . esc_attr( $uid ) . '">';
				echo '<input type="radio" id="' . esc_attr( $uid ) . '" name="cecfm_customer_type_radio" value="' . esc_attr( $type->slug ) . '"' . checked( $type->slug, $current_type, false ) . ' data-type="' . esc_attr( $type->slug ) . '" class="cecfm-type-radio" />';
				echo ' ' . esc_html( $type->label );
				echo '</label>';
			}
			echo '</div>';
		} else {
			echo '<div class="cecfm-type-buttons">';
			foreach ( $types as $type ) {
				$is_active = $type->slug === $current_type;
				echo '<button type="button" class="cecfm-type-btn' . ( $is_active ? ' is-active' : '' ) . '" data-type="' . esc_attr( $type->slug ) . '">';
				echo esc_html( $type->label );
				echo '</button>';
			}
			echo '</div>';
		}

		wp_nonce_field( 'cecfm_set_type', '_cecfm_type_nonce', false );
		echo '<input type="hidden" name="cecfm_customer_type" id="cecfm-current-type-input" value="' . esc_attr( $current_type ) . '" />';

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
			$padding     = \sanitize_text_field( (string) $settings['switcher_padding'] );
			$css_parts[] = '.cecfm-type-switcher .cecfm-type-btn { padding: ' . $padding . ' !important; }';
		}
		if ( ! empty( $settings['switcher_margin'] ) ) {
			$margin      = \sanitize_text_field( (string) $settings['switcher_margin'] );
			$css_parts[] = '.cecfm-type-switcher .cecfm-type-btn { margin: ' . $margin . ' !important; }';
		}
		// Styles moved to wp_add_inline_style in FrontendModule.
		echo '</div>';
	}

	public function ajaxSetCustomerType(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the caller.
		$nonce = ( isset( $_POST['_cecfm_type_nonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['_cecfm_type_nonce'] ) ) : '' )
			?: ( isset( $_POST['nonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ) : '' )
			?: '';
		if ( ! wp_verify_nonce( (string) $nonce, 'cecfm_set_type' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the caller.
		$type = isset( $_POST['type'] ) ? \sanitize_key( \wp_unslash( $_POST['type'] ) ) : '';
		if ( '' === $type ) {
			$type = isset( $_POST['value'] ) ? \sanitize_key( \wp_unslash( $_POST['value'] ) ) : '';
		}

		if ( '' === $type ) {
			wp_send_json_error( array( 'message' => 'Missing type.' ), 422 );
		}
		$this->customerTypeManager->setCurrentType( $type );
		wp_send_json_success( array( 'type' => $type ) );
	}

	public function applyFees( \WC_Cart $cart ): void {
		$context = $this->buildContext();
		$fields  = $this->fieldRepository->findAll( array( 'enabled' => true ) );

		/**
		 * Add cart fees derived from checkout field values.
		 *
		 * Pricing is a Pro capability. This is how the add-on contributes fees
		 * without the free plugin needing to know its rules.
		 *
		 * @param array    $fields  Enabled fields.
		 * @param array    $context Current checkout context.
		 * @param \WC_Cart $cart    The cart being calculated.
		 */
		\do_action( ExtensionPoints::CART_FEES, $fields, $context, $cart );
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the next line.
		$nonce_ok    = isset( $_POST['_cecfm_type_nonce'] )
			? \wp_verify_nonce( \sanitize_text_field( \wp_unslash( (string) $_POST['_cecfm_type_nonce'] ) ), 'cecfm_set_type' )
			: false;

		if ( $nonce_ok ) {
			$posted_type = \sanitize_key( \wp_unslash( (string) ( $_POST['cecfm_customer_type'] ?? '' ) ) );
			if ( '' !== $posted_type ) {
			}
		}
		$type_slug = '' !== $posted_type ? $posted_type : $this->currentTypeSlug();

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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the next line.
		$nonce_ok = isset( $_POST['_cecfm_type_nonce'] )
			? \wp_verify_nonce( \sanitize_text_field( \wp_unslash( (string) $_POST['_cecfm_type_nonce'] ) ), 'cecfm_set_type' )
			: false;
		if ( $nonce_ok && isset( $_POST[ $field_key ] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in the next steps based on whether it is an array or string.
			$raw_val = \wp_unslash( $_POST[ $field_key ] );
			if ( is_array( $raw_val ) ) {
				return array_map( 'sanitize_text_field', $raw_val );
			}
			return sanitize_text_field( (string) $raw_val );
		}

		// WooCommerce checkout AJAX requests often send serialized form data in post_data.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_post_data = isset( $_POST['post_data'] ) ? \wp_unslash( (string) $_POST['post_data'] ) : '';
		if ( $nonce_ok && '' !== $raw_post_data ) {
			$form_data = array();
			\parse_str( $raw_post_data, $form_data );
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
		$raw                  = \get_option( 'cecfm_settings', '' );
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

		$domain = 'coderembassy-checkout-fields-manager';

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

	/**
	 * Bring back fields WooCommerce hides through its own settings.
	 *
	 * WooCommerce removes Company, Address line 2 and Phone in
	 * WC_Countries::get_address_fields() when their setting is "hidden", which
	 * happens before woocommerce_checkout_fields runs. The field therefore never
	 * reaches this filter, and switching it on in Native Fields silently did
	 * nothing — the admin showed it enabled while checkout never rendered it.
	 *
	 * When our own configuration says the field is enabled, it is re-added here
	 * so the toggle means what it says.
	 *
	 * @param array<string, array<string, mixed>> $fields
	 * @param array<string, mixed>                $overrides
	 * @return array<string, array<string, mixed>>
	 */
	private function restoreHiddenOptionalFields( array $fields, array $overrides ): array {
		$optional = array(
			'billing_company'    => array( 'group' => 'billing',  'setting' => 'woocommerce_checkout_company_field',    'priority' => 30,  'autocomplete' => 'organization' ),
			'shipping_company'   => array( 'group' => 'shipping', 'setting' => 'woocommerce_checkout_company_field',    'priority' => 30,  'autocomplete' => 'organization' ),
			'billing_address_2'  => array( 'group' => 'billing',  'setting' => 'woocommerce_checkout_address_2_field',  'priority' => 60,  'autocomplete' => 'address-line2' ),
			'shipping_address_2' => array( 'group' => 'shipping', 'setting' => 'woocommerce_checkout_address_2_field',  'priority' => 60,  'autocomplete' => 'address-line2' ),
			'billing_phone'      => array( 'group' => 'billing',  'setting' => 'woocommerce_checkout_phone_field',      'priority' => 100, 'autocomplete' => 'tel' ),
		);

		$labels = array(
			'billing_company'    => \__( 'Company name', 'coderembassy-checkout-fields-manager' ),
			'shipping_company'   => \__( 'Company name', 'coderembassy-checkout-fields-manager' ),
			'billing_address_2'  => \__( 'Apartment, suite, unit, etc.', 'coderembassy-checkout-fields-manager' ),
			'shipping_address_2' => \__( 'Apartment, suite, unit, etc.', 'coderembassy-checkout-fields-manager' ),
			'billing_phone'      => \__( 'Phone', 'coderembassy-checkout-fields-manager' ),
		);

		foreach ( $optional as $key => $def ) {
			$group = $def['group'];

			// Already present, or we have no opinion about it.
			if ( isset( $fields[ $group ][ $key ] ) || ! isset( $overrides[ $key ] ) ) {
				continue;
			}

			// Only re-add what the merchant switched on here.
			if ( ! ( ! array_key_exists( 'enabled', $overrides[ $key ] ) || ! empty( $overrides[ $key ]['enabled'] ) ) ) {
				continue;
			}

			// Nothing to restore unless WooCommerce is the one hiding it.
			if ( 'hidden' !== \get_option( $def['setting'], '' ) ) {
				continue;
			}

			$override = $overrides[ $key ];

			$fields[ $group ][ $key ] = array(
				'label'        => ! empty( $override['label'] ) ? (string) $override['label'] : ( $labels[ $key ] ?? $key ),
				'placeholder'  => (string) ( $override['placeholder'] ?? '' ),
				'required'     => ! empty( $override['required'] ),
				'class'        => array( 'form-row-wide' ),
				'autocomplete' => $def['autocomplete'],
				'priority'     => isset( $override['priority'] ) ? (int) $override['priority'] : $def['priority'],
			);
		}

		return $fields;
	}
}
	 

