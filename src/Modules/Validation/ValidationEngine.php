<?php
namespace CoderEmbassy\CheckoutFieldEditor\Modules\Validation;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldEditor\Models\CA_Field;
use CoderEmbassy\CheckoutFieldEditor\Modules\Conditions\ConditionEngine;
use CoderEmbassy\CheckoutFieldEditor\Modules\CustomerTypes\CustomerTypeManager;
use CoderEmbassy\CheckoutFieldEditor\Modules\Fields\FieldRepository;
use CoderEmbassy\CheckoutFieldEditor\Modules\Fields\VisibilityResolver;

class ValidationEngine {
	/**
	 * @var array<string, callable>
	 */
	private array $validators = array();

	public function __construct(
		private VisibilityResolver $resolver,
		private FieldRepository $fieldRepository,
		private ConditionEngine $conditionEngine,
		private CustomerTypeManager $typeManager
	) {}

	public function validateCheckout(): void {
		try {
			$checkout = null;
			if ( function_exists( 'WC' ) && \WC()->checkout() ) {
				$checkout = \WC()->checkout();
			}

			if ( ! $checkout instanceof \WC_Checkout ) {
				return;
			}

			// Validate classic checkout request nonce when present.
			// We don't hard-fail when it's missing because some contexts (admin preview, tests) may call this method.
			$nonce_raw = isset( $_POST['woocommerce-process-checkout-nonce'] ) ? \wp_unslash( (string) $_POST['woocommerce-process-checkout-nonce'] ) : '';
			$nonce     = \sanitize_text_field( $nonce_raw );
			if ( '' !== $nonce && ! \wp_verify_nonce( $nonce, 'woocommerce-process_checkout' ) ) {
				return;
			}

			$context = $this->conditionEngine->buildContext();
			// buildContext() relies on an unregistered filter, so customer_type is always ''.
			// Inject the real session-based type so visibility/required checks work correctly.
			if ( '' === $context['customer_type'] ) {
				$type_slug = $this->typeManager->getCurrentTypeSlug();
				if ( '' !== $type_slug ) {
					$context['customer_type'] = $type_slug;
				}
			}

			$fields = $this->fieldRepository->findAll( array( 'enabled' => true ) );

			$visibility_map = $this->resolver->resolveAll( $context );
			$results        = array();

			foreach ( $fields as $field ) {
				$status = $visibility_map[ $field->field_key ] ?? array( 'visible' => true );
				if ( empty( $status['visible'] ) ) {
					continue;
				}

				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked at start of validateCheckout.
				$raw_post = isset( $_POST[ $field->field_key ] ) ? \wp_unslash( $_POST[ $field->field_key ] ) : null;
				if ( is_array( $raw_post ) ) {
					$value = implode( ',', array_map( 'sanitize_text_field', $raw_post ) );
				} elseif ( null !== $raw_post ) {
					$value = sanitize_text_field( (string) $raw_post );
				} else {
					$value = (string) ( $context['field_values'][ $field->field_key ] ?? '' );
				}

				$result = $this->validateField( $field, $value, $context );
				$results[ $field->field_key ] = $result;

				if ( ! $result->passed ) {
					\wc_add_notice( $result->error_message, 'error' );
				}
			}

			\do_action( 'ca_after_validation', $results, $context );
			\do_action( 'cecfe_after_validation', $results, $context );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[Checkout Architect] validateCheckout error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
		}
	}

	public function validateBlocksCheckout( \WC_Order $order, \WP_REST_Request $request ): void {
		$context = $this->conditionEngine->buildContext();
		// Inject the real customer type here as well.
		if ( '' === $context['customer_type'] ) {
			$type_slug = $this->typeManager->getCurrentTypeSlug();
			if ( '' !== $type_slug ) {
				$context['customer_type'] = $type_slug;
			}
		}

		$fields = array_values(
			array_filter(
				$this->fieldRepository->findAll( array( 'enabled' => true ) ),
				fn ( CA_Field $field ): bool => $this->isBlocksEligibleField( $field )
			)
		);
		$params = $request->get_body_params();
		$checkout_fields_service = null;
		if ( class_exists( '\Automattic\WooCommerce\Blocks\Package' ) && class_exists( '\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields' ) ) {
			try {
				$checkout_fields_service = \Automattic\WooCommerce\Blocks\Package::container()->get( \Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class );
			} catch ( \Throwable $e ) {
				$checkout_fields_service = null;
			}
		}

		$raw_customer_type = $request->get_param( 'ca_customer_type' );
		if ( null === $raw_customer_type ) {
			$raw_customer_type = $this->getBlocksRequestFieldValue( $request, $params, 'coderembassy-checkout-field-editor/ca_customer_type', 'ca_customer_type' );
		}
		if ( null === $raw_customer_type ) {
			$raw_customer_type = $this->getBlocksFieldValue( 'coderembassy-checkout-field-editor/ca_customer_type', 'order', $order, $checkout_fields_service );
		}
		$customer_type = sanitize_key( (string) $raw_customer_type );
		if ( '' !== $customer_type ) {
			$this->typeManager->setCurrentType( $customer_type );
			$context['customer_type'] = $customer_type;
			$context['field_values']['ca_customer_type'] = $customer_type;
		}

		foreach ( $fields as $field ) {
			if ( \in_array( $field->type, array( 'multiselect', 'checkbox_group' ), true ) ) {
				$selected_values = $this->getBlocksChoiceValues( $field, $order, $request, $params, $checkout_fields_service );
				$context['field_values'][ $field->field_key ] = implode( ',', $selected_values );
				continue;
			}

			$raw = $this->getBlocksRequestFieldValue(
				$request,
				$params,
				'coderembassy-checkout-field-editor/' . sanitize_key( (string) $field->field_key ),
				(string) $field->field_key
			);
			if ( null === $raw ) {
				$raw = $this->getBlocksFieldValue(
					'coderembassy-checkout-field-editor/' . sanitize_key( (string) $field->field_key ),
					$field->section,
					$order,
					$checkout_fields_service
				);
			}
			$context['field_values'][ $field->field_key ] = \sanitize_text_field( (string) $raw );
		}

		$visibility_map = $this->resolver->resolveAll( $context );
		$results        = array();

		foreach ( $fields as $field ) {
			$status = $visibility_map[ $field->field_key ] ?? array( 'visible' => true );
			if ( empty( $status['visible'] ) ) {
				continue;
			}

			$value  = (string) ( $context['field_values'][ $field->field_key ] ?? '' );
			$result = $this->validateField( $field, $value, $context );
			$results[ $field->field_key ] = $result;

			if ( ! $result->passed ) {
				$order->add_meta_data( '_ca_validation_error', $result->error_message );
				throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
					'ca_validation_error',
					\esc_html( (string) $result->error_message ),
					400
				);
			}
		}

		// Save custom field values to order meta for blocks checkout.
		foreach ( $fields as $field ) {
			if ( \in_array( $field->type, array( 'heading', 'paragraph' ), true ) ) {
				continue;
			}
			$status = $visibility_map[ $field->field_key ] ?? array( 'visible' => true );
			if ( empty( $status['visible'] ) ) {
				continue;
			}
			$value = (string) ( $context['field_values'][ $field->field_key ] ?? '' );
			if ( '' !== $value ) {
				$order->update_meta_data( '_ca_' . $field->field_key, $value );
			}
		}
		if ( '' !== $context['customer_type'] ) {
			$order->update_meta_data( '_ca_customer_type', $context['customer_type'] );
		}

		\do_action( 'ca_after_validation', $results, $context );
		\do_action( 'cecfe_after_validation', $results, $context );
	}

	public function validateField( CA_Field $field, mixed $value, array $context ): ValidationResult {
		$string_value = is_scalar( $value ) ? (string) $value : '';
		$rules        = is_array( $field->validation_rules ) ? $field->validation_rules : array();

		if ( $this->resolver->isFieldRequired( $field, $context ) && '' === trim( $string_value ) ) {
			/* translators: %s: the label shown for the checkout field. */
			$result = ValidationResult::fail( $field->field_key, sprintf( __( '%s is required.', 'coderembassy-checkout-field-editor' ), $field->label ) );
			return $this->filterFieldValidationResult( $result, $field, $value, $context );
		}

		if ( ! empty( $rules['email'] ) && '' !== $string_value && false === filter_var( $string_value, FILTER_VALIDATE_EMAIL ) ) {
			/* translators: %s: the label shown for the checkout field. */
			$result = ValidationResult::fail( $field->field_key, sprintf( __( '%s must be a valid email address.', 'coderembassy-checkout-field-editor' ), $field->label ) );
			return $this->filterFieldValidationResult( $result, $field, $value, $context );
		}

		// Skip length/format checks for empty optional fields — only apply when value is present.
		if ( '' !== $string_value ) {
			if ( isset( $rules['min_length'] ) && strlen( $string_value ) < (int) $rules['min_length'] ) {
				/* translators: %s: the label shown for the checkout field. */
				$result = ValidationResult::fail( $field->field_key, sprintf( __( '%s is too short.', 'coderembassy-checkout-field-editor' ), $field->label ) );
				return $this->filterFieldValidationResult( $result, $field, $value, $context );
			}

			if ( isset( $rules['max_length'] ) && strlen( $string_value ) > (int) $rules['max_length'] ) {
				/* translators: %s: the label shown for the checkout field. */
				$result = ValidationResult::fail( $field->field_key, sprintf( __( '%s is too long.', 'coderembassy-checkout-field-editor' ), $field->label ) );
				return $this->filterFieldValidationResult( $result, $field, $value, $context );
			}

			if ( isset( $rules['regex'] ) ) {
				try {
					$match = preg_match( (string) $rules['regex'], $string_value );
				} catch ( \ValueError $e ) {
					// Invalid regex pattern stored in DB — fail safe, don't crash checkout.
					$match = false;
				}
				if ( 1 !== $match ) {
					/* translators: %s: the label shown for the checkout field. */
					$result = ValidationResult::fail( $field->field_key, sprintf( __( '%s format is invalid.', 'coderembassy-checkout-field-editor' ), $field->label ) );
					return $this->filterFieldValidationResult( $result, $field, $value, $context );
				}
			}
		}

		if ( ! empty( $rules['numeric'] ) && '' !== $string_value && ! is_numeric( $string_value ) ) {
			/* translators: %s: the label shown for the checkout field. */
			$result = ValidationResult::fail( $field->field_key, sprintf( __( '%s must be numeric.', 'coderembassy-checkout-field-editor' ), $field->label ) );
			return $this->filterFieldValidationResult( $result, $field, $value, $context );
		}

		if ( '' !== $string_value && is_numeric( $string_value ) ) {
			if ( isset( $rules['min_value'] ) && is_numeric( (string) $rules['min_value'] ) && (float) $string_value < (float) $rules['min_value'] ) {
				/* translators: %s: the label shown for the checkout field. */
				$result = ValidationResult::fail( $field->field_key, sprintf( __( '%s is below the minimum allowed value.', 'coderembassy-checkout-field-editor' ), $field->label ) );
				return $this->filterFieldValidationResult( $result, $field, $value, $context );
			}

			if ( isset( $rules['max_value'] ) && is_numeric( (string) $rules['max_value'] ) && (float) $string_value > (float) $rules['max_value'] ) {
				/* translators: %s: the label shown for the checkout field. */
				$result = ValidationResult::fail( $field->field_key, sprintf( __( '%s exceeds the maximum allowed value.', 'coderembassy-checkout-field-editor' ), $field->label ) );
				return $this->filterFieldValidationResult( $result, $field, $value, $context );
			}
		}

		if ( isset( $rules['confirm'] ) && is_string( $rules['confirm'] ) ) {
			$confirm_key   = $rules['confirm'];
			$confirm_value = isset( $context['field_values'][ $confirm_key ] ) ? (string) $context['field_values'][ $confirm_key ] : '';
			if ( '' === $confirm_value && isset( $_POST[ $confirm_key ] ) ) {
				$nonce_val = isset( $_POST['woocommerce-process-checkout-nonce'] ) ? \wp_unslash( (string) $_POST['woocommerce-process-checkout-nonce'] ) : ( isset( $_POST['nonce'] ) ? \wp_unslash( (string) $_POST['nonce'] ) : ( isset( $_POST['security'] ) ? \wp_unslash( (string) $_POST['security'] ) : '' ) );
				$nonce_clean = \sanitize_text_field( $nonce_val );
				if ( '' !== $nonce_clean && ( \wp_verify_nonce( $nonce_clean, 'woocommerce-process_checkout' ) || \wp_verify_nonce( $nonce_clean, 'ca_set_type' ) ) ) {
					$confirm_value = \sanitize_text_field( \wp_unslash( (string) $_POST[ $confirm_key ] ) );
				}
			}
			if ( $string_value !== $confirm_value ) {
				/* translators: %s: the label shown for the checkout field. */
				$result = ValidationResult::fail( $field->field_key, sprintf( __( '%s does not match confirmation.', 'coderembassy-checkout-field-editor' ), $field->label ) );
				return $this->filterFieldValidationResult( $result, $field, $value, $context );
			}
		}

		foreach ( $this->validators as $type => $handler ) {
			$custom_result = $handler( $field, $value, $context, $rules[ $type ] ?? null );
			if ( $custom_result instanceof ValidationResult && ! $custom_result->passed ) {
				return $this->filterFieldValidationResult( $custom_result, $field, $value, $context );
			}
		}

		$base_result = ValidationResult::pass( $field->field_key );
		$custom = \apply_filters( 'cecfe_custom_validator_' . $field->field_key, $base_result, $value, $field, $context );
		$custom = \apply_filters( 'ca_custom_validator_' . $field->field_key, $custom, $value, $field, $context );
		if ( $custom instanceof ValidationResult ) {
			return $this->filterFieldValidationResult( $custom, $field, $value, $context );
		}

		return $this->filterFieldValidationResult( $base_result, $field, $value, $context );
	}

	public function registerValidator( string $type, callable $handler ): void {
		$this->validators[ $type ] = $handler;
	}

	public function filterFieldHtml( string $html, CA_Field $field, array $context ): string {
		\do_action( 'cecfe_before_field_render', $field, $context );
		\do_action( 'ca_before_field_render', $field, $context );

		$html = (string) \apply_filters( 'cecfe_field_html', $html, $field, $context );
		$html = (string) \apply_filters( 'ca_field_html', $html, $field, $context );

		\do_action( 'cecfe_after_field_render', $field, $context );
		\do_action( 'ca_after_field_render', $field, $context );
		return $html;
	}

	private function getBlocksFieldValue( string $blocks_field_id, string $section, \WC_Order $order, mixed $checkout_fields_service ): mixed {
		if ( null === $checkout_fields_service ) {
			return null;
		}
		$groups = array( 'other' );
		if ( 'billing' === $section ) {
			$groups = array( 'billing', 'other' );
		} elseif ( 'shipping' === $section ) {
			$groups = array( 'shipping', 'other' );
		}

		foreach ( $groups as $group ) {
			try {
				$raw = $checkout_fields_service->get_field_from_object( $blocks_field_id, $order, $group );
			} catch ( \Throwable $e ) {
				$raw = null;
			}
			if ( null !== $raw && '' !== (string) $raw ) {
				return $raw;
			}
		}

		return null;
	}

	/**
	 * Collect selected option values for multiselect/checkbox_group in Blocks.
	 *
	 * @param array<string,mixed> $params
	 * @return string[]
	 */
	private function getBlocksChoiceValues( CA_Field $field, \WC_Order $order, \WP_REST_Request $request, array $params, mixed $checkout_fields_service ): array {
		$selected = array();

		foreach ( (array) $field->options as $option ) {
			if ( ! \is_array( $option ) ) {
				continue;
			}
			$option_value = (string) ( $option['value'] ?? '' );
			if ( '' === $option_value ) {
				continue;
			}

			$option_key = sanitize_key( (string) $field->field_key ) . '__' . sanitize_key( $option_value );
			$raw        = $this->getBlocksRequestFieldValue(
				$request,
				$params,
				'coderembassy-checkout-field-editor/' . $option_key,
				$option_key
			);
			if ( null === $raw ) {
				$raw = $this->getBlocksFieldValue( 'coderembassy-checkout-field-editor/' . $option_key, $field->section, $order, $checkout_fields_service );
			}

			if ( true === $raw || '1' === (string) $raw || 'true' === strtolower( (string) $raw ) || 'yes' === strtolower( (string) $raw ) || 'on' === strtolower( (string) $raw ) ) {
				$selected[] = sanitize_text_field( $option_value );
			}
		}

		return $selected;
	}

	/**
	 * Read additional-checkout-field value from multiple request payload shapes.
	 *
	 * @param array<string,mixed> $params
	 */
	private function getBlocksRequestFieldValue( \WP_REST_Request $request, array $params, string $namespaced_key, string $short_key ): mixed {
		$candidates = array( $short_key, $namespaced_key );
		foreach ( $candidates as $key ) {
			$raw = $request->get_param( $key );
			if ( null !== $raw ) {
				return $raw;
			}
			if ( array_key_exists( $key, $params ) ) {
				return $params[ $key ];
			}
		}

		$nested_maps = array(
			$params['additional_fields'] ?? null,
			$params['additionalFields'] ?? null,
			$params['extensions']['coderembassy-checkout-field-editor'] ?? null,
			$params['extensions']['checkout_architect_pro'] ?? null,
		);
		foreach ( $nested_maps as $map ) {
			if ( ! is_array( $map ) ) {
				continue;
			}
			foreach ( $candidates as $key ) {
				if ( array_key_exists( $key, $map ) ) {
					return $map[ $key ];
				}
			}
		}

		return null;
	}

	private function filterFieldValidationResult( ValidationResult $result, CA_Field $field, mixed $value, array $context ): ValidationResult {
		$filtered = \apply_filters( 'cecfe_validate_field', $result, $field, $value, $context );
		$filtered = \apply_filters( 'ca_validate_field', $filtered, $field, $value, $context );
		return $filtered instanceof ValidationResult ? $filtered : $result;
	}

	private function isBlocksEligibleField( CA_Field $field ): bool {
		$supported_types = array( 'text', 'select', 'date', 'radio', 'checkbox' );
		if ( ! in_array( (string) $field->type, $supported_types, true ) ) {
			return false;
		}
		$meta = is_array( $field->meta ) ? $field->meta : array();
		return ! empty( $meta['blocks_enabled'] );
	}
}
