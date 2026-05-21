<?php
namespace CoderEmbassy\CheckoutFieldsManager\Modules\Conditions;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Modules\Conditions\Types\CartCategoryCondition;
use CoderEmbassy\CheckoutFieldsManager\Modules\Conditions\Types\CartProductCondition;
use CoderEmbassy\CheckoutFieldsManager\Modules\Conditions\Types\CartTotalCondition;
use CoderEmbassy\CheckoutFieldsManager\Modules\Conditions\Types\CountryCondition;
use CoderEmbassy\CheckoutFieldsManager\Modules\Conditions\Types\CouponCondition;
use CoderEmbassy\CheckoutFieldsManager\Modules\Conditions\Types\CustomerTypeCondition;
use CoderEmbassy\CheckoutFieldsManager\Modules\Conditions\Types\FieldValueCondition;
use CoderEmbassy\CheckoutFieldsManager\Modules\Conditions\Types\PaymentMethodCondition;
use CoderEmbassy\CheckoutFieldsManager\Modules\Conditions\Types\ShippingMethodCondition;
use CoderEmbassy\CheckoutFieldsManager\Modules\Conditions\Types\UserRoleCondition;

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


