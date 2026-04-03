<?php

namespace CoderEmbassy\CheckoutFieldEditor\Modules\Conditions\Types;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldEditor\Interfaces\ConditionInterface;

class PaymentMethodCondition implements ConditionInterface {
	public function getType(): string {
		return 'payment_method';
	}

	public function evaluate( array $context ): bool {
		$runtime  = $context['context'] ?? array();
		$operator = (string) ( $context['operator'] ?? 'in' );
		$value    = is_array( $context['value'] ?? null ) ? $context['value'] : array();
		$current  = (string) ( $runtime['payment_method'] ?? '' );
		$matched  = in_array( $current, array_map( 'strval', $value ), true );

		return 'not_in' === $operator ? ! $matched : $matched;
	}

	public function getSchema(): array {
		return array(
			'type'       => $this->getType(),
			'label'      => 'Payment Method',
			'operators'  => array( 'in', 'not_in' ),
			'value_type' => 'multiselect',
		);
	}
}
