<?php
namespace CoderEmbassy\CheckoutFieldEditor\Modules\Fields;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldEditor\Abstracts\AbstractModule;

class FieldsModule extends AbstractModule {
	public function register(): void {
		$this->hooks->addFilter( 'woocommerce_checkout_fields', array( $this, 'filterCheckoutFields' ), 20 );
	}

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	public function filterCheckoutFields( array $fields ): array {
		return $fields;
	}
}
