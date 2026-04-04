<?php
namespace CoderEmbassy\CheckoutFieldEditor\Modules\Validation;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldEditor\Abstracts\AbstractModule;

use CoderEmbassy\CheckoutFieldEditor\Modules\Conditions\ConditionEngine;
use CoderEmbassy\CheckoutFieldEditor\Modules\CustomerTypes\CustomerTypeManager;
use CoderEmbassy\CheckoutFieldEditor\Modules\Fields\FieldRepository;
use CoderEmbassy\CheckoutFieldEditor\Modules\Fields\VisibilityResolver;

class ValidationModule extends AbstractModule {
	public function register(): void {
		$this->container->singleton(
			ValidationEngine::class,
			fn (): ValidationEngine => new ValidationEngine(
				$this->container->make( VisibilityResolver::class ),
				$this->container->make( FieldRepository::class ),
				$this->container->make( ConditionEngine::class ),
				$this->container->make( CustomerTypeManager::class )
			)
		);

		$engine = $this->container->make( ValidationEngine::class );
		$this->hooks->add( 'woocommerce_checkout_process', array( $engine, 'validateCheckout' ), 10, 0 );
		$this->hooks->add( 'woocommerce_store_api_checkout_update_order_from_request', array( $engine, 'validateBlocksCheckout' ), 10, 2 );
	}
}
