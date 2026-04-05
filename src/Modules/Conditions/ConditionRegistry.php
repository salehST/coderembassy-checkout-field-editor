<?php
namespace CoderEmbassy\CheckoutFieldEditor\Modules\Conditions;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldEditor\Modules\Conditions\Types\CartCategoryCondition;
use CoderEmbassy\CheckoutFieldEditor\Modules\Conditions\Types\CartProductCondition;
use CoderEmbassy\CheckoutFieldEditor\Modules\Conditions\Types\CartTotalCondition;
use CoderEmbassy\CheckoutFieldEditor\Modules\Conditions\Types\CountryCondition;
use CoderEmbassy\CheckoutFieldEditor\Modules\Conditions\Types\CouponCondition;
use CoderEmbassy\CheckoutFieldEditor\Modules\Conditions\Types\CustomerTypeCondition;
use CoderEmbassy\CheckoutFieldEditor\Modules\Conditions\Types\FieldValueCondition;
use CoderEmbassy\CheckoutFieldEditor\Modules\Conditions\Types\PaymentMethodCondition;
use CoderEmbassy\CheckoutFieldEditor\Modules\Conditions\Types\ShippingMethodCondition;
use CoderEmbassy\CheckoutFieldEditor\Modules\Conditions\Types\UserRoleCondition;

class ConditionRegistry {
	public function register( ConditionEngine $engine ): void {
		$types = array(
			new CustomerTypeCondition(),
			new CartTotalCondition(),
			new CartProductCondition(),
			new CartCategoryCondition(),
			new UserRoleCondition(),
			new CountryCondition(),
			new PaymentMethodCondition(),
			new ShippingMethodCondition(),
			new CouponCondition(),
			new FieldValueCondition(),
		);

		foreach ( $types as $handler ) {
			$engine->registerType( $handler->getType(), $handler );
		}
	}
}
