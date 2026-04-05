<?php
namespace CoderEmbassy\CheckoutFieldEditor\Interfaces;

defined( 'ABSPATH' ) || exit;

/**
 * Contract for checkout rule conditions.
 */
interface ConditionInterface {
	/**
	 * Return unique condition type used by UI and storage.
	 *
	 * @return string Condition type slug.
	 */
	public function getType(): string;

	/**
	 * Evaluate condition against runtime checkout context.
	 *
	 * @param array<string, mixed> $context Runtime context values.
	 * @return bool True when condition matches.
	 */
	public function evaluate( array $context ): bool;

	/**
	 * Return condition schema for React rule builder.
	 *
	 * @return array<string, mixed> UI schema metadata.
	 */
	public function getSchema(): array;
}
