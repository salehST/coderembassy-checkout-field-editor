<?php

namespace CoderEmbassy\CheckoutFieldEditor\Modules\Conditions\Types;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldEditor\Interfaces\ConditionInterface;

class CartCategoryCondition implements ConditionInterface {
	public function getType(): string {
		return 'cart_categories';
	}

	public function evaluate( array $context ): bool {
		$runtime    = $context['context'] ?? array();
		$operator   = (string) ( $context['operator'] ?? 'contains' );
		$rule_vals  = is_array( $context['value'] ?? null ) ? $context['value'] : array();
		$categories = is_array( $runtime['cart_categories'] ?? null ) ? $runtime['cart_categories'] : array();
		$matched    = ! empty( array_intersect( array_map( 'intval', $rule_vals ), array_map( 'intval', $categories ) ) );

		return 'not_contains' === $operator ? ! $matched : $matched;
	}

	public function getSchema(): array {
		return array(
			'type'       => $this->getType(),
			'label'      => 'Cart Categories',
			'operators'  => array( 'contains', 'not_contains' ),
			'value_type' => 'category_select',
		);
	}
}
