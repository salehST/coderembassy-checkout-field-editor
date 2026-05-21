<?php

namespace CoderEmbassy\CheckoutFieldsManager\Modules\Conditions\Types;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Interfaces\ConditionInterface;

class FieldValueCondition implements ConditionInterface {
	public function getType(): string {
		return 'field_value';
	}

	public function evaluate( array $context ): bool {
		$runtime   = $context['context'] ?? array();
		$operator  = (string) ( $context['operator'] ?? '==' );
		$field_key = (string) ( $context['field_key'] ?? '' );
		$value     = (string) ( $context['value'] ?? '' );
		$fields    = is_array( $runtime['field_values'] ?? null ) ? $runtime['field_values'] : array();
		$current   = (string) ( $fields[ $field_key ] ?? '' );

		return match ( $operator ) {
			'==' => $current === $value,
			'!=' => $current !== $value,
			'contains' => '' !== $value && false !== strpos( $current, $value ),
			default => false,
		};
	}

	public function getSchema(): array {
		return array(
			'type'               => $this->getType(),
			'label'              => 'Field Value',
			'operators'          => array( '==', '!=', 'contains' ),
			'value_type'         => 'text',
			'requires_field_key' => true,
		);
	}
}


