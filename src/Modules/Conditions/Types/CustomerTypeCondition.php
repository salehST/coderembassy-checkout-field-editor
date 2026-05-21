<?php
namespace CoderEmbassy\CheckoutFieldsManager\Modules\Conditions\Types;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Interfaces\ConditionInterface;

class CustomerTypeCondition implements ConditionInterface {
	public function getType(): string {
		return 'customer_type';
	}

	public function evaluate( array $context ): bool {
		$runtime  = $context['context'] ?? array();
		$operator = (string) ( $context['operator'] ?? 'in' );
		$value    = is_array( $context['value'] ?? null ) ? $context['value'] : array();
		$current  = (string) ( $runtime['customer_type'] ?? '' );
		$matched  = in_array( $current, array_map( 'strval', $value ), true );

		return 'not_in' === $operator ? ! $matched : $matched;
	}

	public function getSchema(): array {
		return array(
			'type'         => $this->getType(),
			'label'        => 'Customer Type',
			'operators'    => array( 'in', 'not_in' ),
			'value_type'   => 'multiselect',
			'value_source' => 'customer_types',
		);
	}
}


