<?php
namespace CoderEmbassy\CheckoutFieldEditor\Modules\Validation;

defined( 'ABSPATH' ) || exit;

final class ValidationResult {
	public function __construct(
		public readonly bool $passed,
		public readonly string $field_key,
		public readonly string $error_message = ''
	) {}

	public static function pass( string $field_key ): self {
		return new self( true, $field_key, '' );
	}

	public static function fail( string $field_key, string $message ): self {
		return new self( false, $field_key, $message );
	}
}
