<?php

namespace CoderEmbassy\CheckoutFieldEditor\Modules\Conditions\Types;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldEditor\Interfaces\ConditionInterface;

class CountryCondition implements ConditionInterface {
	public function getType(): string {
		return 'country';
	}

	public function evaluate( array $context ): bool {
		$runtime  = $context['context'] ?? array();
		$operator = (string) ( $context['operator'] ?? 'in' );
		$value    = is_array( $context['value'] ?? null ) ? $context['value'] : array();
		$current  = (string) ( $runtime['country'] ?? '' );
		$matched  = in_array( $current, array_map( 'strval', $value ), true );

		return 'not_in' === $operator ? ! $matched : $matched;
	}

	public function getSchema(): array {
		return array(
			'type'       => $this->getType(),
			'label'      => 'Country',
			'operators'  => array( 'in', 'not_in' ),
			'value_type' => 'country_select',
		);
	}
}
