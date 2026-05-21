<?php
namespace CoderEmbassy\CheckoutFieldsManager\Modules\Pricing;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Modules\Conditions\ConditionEngine;
use CoderEmbassy\CheckoutFieldsManager\Models\CECFM_Field;

class PricingEngine {
	public function __construct(
		private ConditionEngine $conditionEngine
	) {}

	public function calculateFees( array $fields, array $context ): array {
		$fees = array();

		foreach ( $fields as $field ) {
			if ( empty( $field->pricing_rules ) || ! is_array( $field->pricing_rules ) ) {
				continue;
			}

			foreach ( $field->pricing_rules as $rule ) {
				if ( ! is_array( $rule ) || empty( $rule['enabled'] ) ) {
					continue;
				}

				// Check conditions for this rule.
				if ( ! empty( $rule['conditions'] ) && is_array( $rule['conditions'] ) ) {
					if ( ! $this->conditionEngine->evaluate( $rule['conditions'], $context ) ) {
						continue;
					}
				}

				$type  = (string) ( $rule['type'] ?? 'fixed' );
				$value = (float) ( $rule['value'] ?? 0.0 );
				$label = ! empty( $rule['label'] ) ? (string) $rule['label'] : $field->label;

				if ( $value <= 0 ) {
					continue;
				}

				$fee_amount = 0.0;
				if ( 'fixed' === $type ) {
					$fee_amount = $value;
				} elseif ( 'percent_total' === $type ) {
					$fee_amount = ( $context['cart_total'] ?? 0.0 ) * ( $value / 100 );
				} elseif ( 'percent_subtotal' === $type ) {
					$fee_amount = ( $context['cart_subtotal'] ?? 0.0 ) * ( $value / 100 );
				}

				if ( $fee_amount > 0 ) {
					$fees[] = array(
						'id'     => sanitize_key( $field->field_key . '_' . $type ),
						'name'   => $label,
						'amount' => $fee_amount,
						'tax'    => ! empty( $rule['taxable'] ),
					);
				}
			}
		}

		return $fees;
	}
}
