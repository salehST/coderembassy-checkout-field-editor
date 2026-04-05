<?php
namespace CoderEmbassy\CheckoutFieldEditor\Modules\CustomerTypes;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldEditor\Abstracts\AbstractModule;
use CoderEmbassy\CheckoutFieldEditor\Modules\Conditions\ConditionEngine;
use CoderEmbassy\CheckoutFieldEditor\Modules\Fields\VisibilityResolver;

class CustomerTypesModule extends AbstractModule {
	public function register(): void {
		$this->hooks->addFilter( 'checkout_architect_customer_types', array( $this, 'registerTypes' ) );
	}

	/**
	 * @param array<int, array<string, string>> $types
	 * @return array<int, array<string, string>>
	 */
	public function registerTypes( array $types ): array {
		$repo = $this->container->make( CustomerTypeRepository::class );
		foreach ( $repo->findAll() as $type ) {
			$types[] = array(
				'type'  => $type->slug,
				'label' => $type->label,
			);
		}

		return $types;
	}

	public function ajaxSetCustomerType(): void {
		\check_ajax_referer( 'ca_checkout_nonce', 'nonce' );

		$slug    = isset( $_POST['type'] ) ? \sanitize_key( \wp_unslash( (string) $_POST['type'] ) ) : '';
		$manager = $this->container->make( CustomerTypeManager::class );
		$engine  = $this->container->make( ConditionEngine::class );
		$resolver = $this->container->make( VisibilityResolver::class );

		if ( '' !== $slug ) {
			$manager->setCurrentType( $slug );
		}

		$context                  = $engine->buildContext();
		$context['customer_type'] = $slug;
		$fields_map               = $resolver->resolveAll( $context );

		\wp_send_json_success(
			array(
				'type'   => $slug,
				'fields' => $fields_map,
			)
		);
	}
}
