<?php

namespace CoderEmbassy\CheckoutFieldsManager\Modules\Conditions\Types;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Interfaces\ConditionInterface;

class CartProductCondition implements ConditionInterface {
	public function getType(): string {
		return 'cart_products';
	}

	public function evaluate( array $context ): bool {
		$runtime   = $context['context'] ?? array();
		$operator  = (string) ( $context['operator'] ?? 'contains' );
		$rule_vals = is_array( $context['value'] ?? null ) ? $context['value'] : array();
		$products  = is_array( $runtime['cart_products'] ?? null ) ? $runtime['cart_products'] : array();
		$matched   = ! empty( array_intersect( array_map( 'intval', $rule_vals ), array_map( 'intval', $products ) ) );

		return 'not_contains' === $operator ? ! $matched : $matched;
	}

	public function getSchema(): array {
		return array(
			'type'       => $this->getType(),
			'label'      => 'Cart Products',
			'operators'  => array( 'contains', 'not_contains' ),
			'value_type' => 'product_select',
		);
	}
}


