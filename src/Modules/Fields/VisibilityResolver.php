<?php
namespace CoderEmbassy\CheckoutFieldEditor\Modules\Fields;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldEditor\Models\CA_Field;
use CoderEmbassy\CheckoutFieldEditor\Modules\Conditions\ConditionEngine;
use CoderEmbassy\CheckoutFieldEditor\Modules\CustomerTypes\CustomerTypeManager;

class VisibilityResolver {
	public function __construct(
		private CustomerTypeManager $customerTypeManager,
		private ConditionEngine $conditionEngine
	) {}

	public function isFieldVisible( CA_Field $field, array $context = array() ): bool {
		$type_slug = (string) ( $context['customer_type'] ?? '' );

		$type_pass = $this->customerTypeManager->fieldBelongsToType( $field, $type_slug );
		$cond_pass = empty( $field->conditions ) ? true : $this->conditionEngine->evaluate( $field->conditions, $context );
		$visible   = $type_pass && $cond_pass;

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		return (bool) \apply_filters( 'ca_field_is_visible', $visible, $field, $context );
	}

	public function isFieldRequired( CA_Field $field, array $context = array() ): bool {
		if ( ! $this->isFieldVisible( $field, $context ) ) {
			return false;
		}

		// When required_conditions are defined AND have actual rule groups, the
		// field is required only when those conditions evaluate to true.
		// An empty groups array (the blank default) falls back to the static flag.
		$req_groups = $field->required_conditions['groups'] ?? array();
		if ( is_array( $field->required_conditions ) && ! empty( $req_groups ) ) {
			$required = $this->conditionEngine->evaluate( $field->required_conditions, $context );
		} else {
			$required = $field->required;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		return (bool) \apply_filters( 'ca_field_is_required', $required, $field, $context );
	}

	public function resolveAll( array $context ): array {
		$type_slug = (string) ( $context['customer_type'] ?? '' );
		$fields    = $this->customerTypeManager->getFieldsForType( $type_slug );
		$resolved  = array();

		foreach ( $fields as $field ) {
			$visible = $this->isFieldVisible( $field, $context );
			$resolved[ $field->field_key ] = array(
				'visible'  => $visible,
				'required' => $visible ? $this->isFieldRequired( $field, $context ) : false,
			);
		}

		return $resolved;
	}
}
