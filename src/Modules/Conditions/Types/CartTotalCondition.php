<?php

namespace CoderEmbassy\CheckoutFieldEditor\Modules\Conditions\Types;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldEditor\Interfaces\ConditionInterface;

class CartTotalCondition implements ConditionInterface {
	public function getType(): string {
		return 'cart_total';
	}

	public function evaluate( array $context ): bool {
		$runtime  = $context['context'] ?? array();
		$operator = (string) ( $context['operator'] ?? '>=' );
		$value    = (float) ( $context['value'] ?? 0 );
		$total    = (float) ( $runtime['cart_total'] ?? 0 );

		return match ( $operator ) {
			'>=' => $total >= $value,
			'<=' => $total <= $value,
			'==' => $total == $value,
			'>'  => $total > $value,
			'<'  => $total < $value,
			default => false,
		};
	}

	public function getSchema(): array {
		return array(
			'type'       => $this->getType(),
			'label'      => 'Cart Total',
			'operators'  => array( '>=', '<=', '==', '>', '<' ),
			'value_type' => 'number',
		);
	}
}
