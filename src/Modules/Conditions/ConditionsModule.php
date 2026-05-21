<?php
namespace CoderEmbassy\CheckoutFieldsManager\Modules\Conditions;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Abstracts\AbstractModule;

class ConditionsModule extends AbstractModule {
	public function register(): void {
		$this->container->singleton(
			ConditionEngine::class,
			static fn (): ConditionEngine => new ConditionEngine()
		);

		$engine   = $this->container->make( ConditionEngine::class );
		$registry = $this->container->make( ConditionRegistry::class );
		$registry->register( $engine );

		$this->hooks->addFilter( 'cecfm_conditions', array( $this, 'registerConditions' ) );
		$this->hooks->addFilter( 'cecfm_condition_schemas', array( $this, 'getSchemas' ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $conditions
	 * @return array<int, array<string, mixed>>
	 */
	public function registerConditions( array $conditions ): array {
		$engine = $this->container->make( ConditionEngine::class );
		foreach ( $engine->getSchemas() as $schema ) {
			$conditions[] = $schema;
		}

		return $conditions;
	}

	/**
	 * @param array<int, array<string, mixed>> $schemas
	 * @return array<int, array<string, mixed>>
	 */
	public function getSchemas( array $schemas ): array {
		$engine = $this->container->make( ConditionEngine::class );
		return array_merge( $schemas, $engine->getSchemas() );
	}
}


