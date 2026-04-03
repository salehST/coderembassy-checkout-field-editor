<?php
namespace CoderEmbassy\CheckoutFieldEditor\Interfaces;

defined( 'ABSPATH' ) || exit;

/**
 * Contract for checkout field objects.
 */
interface FieldInterface {
	/**
	 * Determine if this field should be visible in current context.
	 *
	 * @param array<string, mixed> $context Runtime checkout context.
	 * @return bool True when field should be shown.
	 */
	public function isVisible( array $context ): bool;

	/**
	 * Determine if this field is required in current context.
	 *
	 * @param array<string, mixed> $context Runtime checkout context.
	 * @return bool True when field is required.
	 */
	public function isRequired( array $context ): bool;

	/**
	 * Render field markup.
	 *
	 * @param array<string, mixed> $field_data Field configuration payload.
	 * @return string HTML markup for checkout rendering.
	 */
	public function render( array $field_data ): string;
}
