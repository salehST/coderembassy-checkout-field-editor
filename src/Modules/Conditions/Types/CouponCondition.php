<?php

namespace CoderEmbassy\CheckoutFieldEditor\Modules\Conditions\Types;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldEditor\Interfaces\ConditionInterface;

class CouponCondition implements ConditionInterface {
	public function getType(): string {
		return 'coupon';
	}

	public function evaluate( array $context ): bool {
		$runtime   = $context['context'] ?? array();
		$operator  = (string) ( $context['operator'] ?? 'in' );
		$rule_vals = is_array( $context['value'] ?? null ) ? $context['value'] : array();
		$coupons   = is_array( $runtime['coupon_codes'] ?? null ) ? $runtime['coupon_codes'] : array();
		$matched   = ! empty( array_intersect( array_map( 'strval', $rule_vals ), array_map( 'strval', $coupons ) ) );

		return 'not_in' === $operator ? ! $matched : $matched;
	}

	public function getSchema(): array {
		return array(
			'type'       => $this->getType(),
			'label'      => 'Coupon',
			'operators'  => array( 'in', 'not_in' ),
			'value_type' => 'multiselect',
		);
	}
}
