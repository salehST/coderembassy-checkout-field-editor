<?php
namespace CoderEmbassy\CheckoutFieldsManager\Modules\Fields;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\ExtensionPoints;
use CoderEmbassy\CheckoutFieldsManager\Models\CECFM_Field;
use CoderEmbassy\CheckoutFieldsManager\Modules\CustomerTypes\CustomerTypeManager;

/**
 * Decides whether a field renders and whether it is required.
 *
 * This plugin honours its own fixed customer types, so a field assigned to a
 * type is hidden for the others. It has no conditional logic, so `required` is
 * otherwise the static flag on the field itself.
 *
 * An add-on layers conditional rules on top through the visibility and required
 * filters, reading the `conditions` and `required_conditions` columns that this
 * plugin stores but never interprets.
 */
class VisibilityResolver {

	public function __construct(
		private FieldRepository $fieldRepository,
		private CustomerTypeManager $customerTypeManager
	) {}

	/**
	 * @param array<string, mixed> $context
	 */
	public function isFieldVisible( CECFM_Field $field, array $context = array() ): bool {
		// Free honours the fixed customer type pair; add-ons layer conditional
		// rules on top through the filter.
		$type_slug = (string) ( $context['customer_type'] ?? '' );
		$visible   = $this->customerTypeManager->fieldBelongsToType( $field, $type_slug );

		return (bool) \apply_filters( ExtensionPoints::FIELD_IS_VISIBLE, $visible, $field, $context );
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public function isFieldRequired( CECFM_Field $field, array $context = array() ): bool {
		// A hidden field is never required. This is the rule that prevents the
		// "phantom required error" competitors suffer from, so it runs ahead of
		// the filter and cannot be overridden away by an add-on.
		if ( ! $this->isFieldVisible( $field, $context ) ) {
			return false;
		}

		/**
		 * Override whether a visible field is required.
		 *
		 * @param bool        $required The field's static required flag.
		 * @param CECFM_Field $field
		 * @param array       $context
		 */
		return (bool) \apply_filters( ExtensionPoints::FIELD_IS_REQUIRED, (bool) $field->required, $field, $context );
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array<string, array{visible: bool, required: bool}>
	 */
	public function resolveAll( array $context ): array {
		$fields   = $this->fieldRepository->findAll( array( 'enabled' => true ) );
		$resolved = array();

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
