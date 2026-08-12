<?php
namespace CoderEmbassy\CheckoutFieldsManager\Modules\Validation;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Abstracts\AbstractModule;

use CoderEmbassy\CheckoutFieldsManager\Frontend\CheckoutContext;
use CoderEmbassy\CheckoutFieldsManager\Modules\Fields\FieldRepository;
use CoderEmbassy\CheckoutFieldsManager\Modules\Fields\VisibilityResolver;

class ValidationModule extends AbstractModule {
	public function register(): void {
		if ( ! $this->container->has( CheckoutContext::class ) ) {
			$this->container->singleton(
				CheckoutContext::class,
				static fn (): CheckoutContext => new CheckoutContext()
			);
		}

		$this->container->singleton(
			ValidationEngine::class,
			fn (): ValidationEngine => new ValidationEngine(
				$this->container->make( VisibilityResolver::class ),
				$this->container->make( FieldRepository::class ),
				$this->container->make( CheckoutContext::class )
			)
		);

		$engine = $this->container->make( ValidationEngine::class );
		$this->hooks->add( 'woocommerce_checkout_process', array( $engine, 'validateCheckout' ), 10, 0 );
		$this->hooks->add( 'woocommerce_store_api_checkout_update_order_from_request', array( $engine, 'validateBlocksCheckout' ), 10, 2 );
	}
}


