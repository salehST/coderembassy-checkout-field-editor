<?php
namespace CoderEmbassy\CheckoutFieldEditor\Admin;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldEditor\Admin\Controllers\ConditionsController;
use CoderEmbassy\CheckoutFieldEditor\Admin\Controllers\CustomerTypesController;
use CoderEmbassy\CheckoutFieldEditor\Admin\Controllers\FieldsController;
use CoderEmbassy\CheckoutFieldEditor\Admin\Controllers\NativeFieldsController;
use CoderEmbassy\CheckoutFieldEditor\Admin\Controllers\PreviewController;
use CoderEmbassy\CheckoutFieldEditor\Admin\Controllers\SectionsController;
use CoderEmbassy\CheckoutFieldEditor\Admin\Controllers\SettingsController;
use CoderEmbassy\CheckoutFieldEditor\Container;
use CoderEmbassy\CheckoutFieldEditor\Modules\Conditions\ConditionEngine;
use CoderEmbassy\CheckoutFieldEditor\Modules\CustomerTypes\CustomerTypeRepository;
use CoderEmbassy\CheckoutFieldEditor\Modules\Fields\FieldRepository;
use CoderEmbassy\CheckoutFieldEditor\Modules\Fields\RevisionRepository;
use CoderEmbassy\CheckoutFieldEditor\Modules\Fields\VisibilityResolver;
use CoderEmbassy\CheckoutFieldEditor\Modules\Sections\SectionRepository;

class RestController {
	public const REST_NAMESPACE = 'checkout-architect/v1';

	public function __construct( private Container $container ) {}

	public function registerRoutes(): void {
		$this->register_rest_routes();
	}

	public function register_rest_routes(): void {
		$controllers = array(
			new FieldsController(
				$this->container->make( FieldRepository::class ),
				$this->container->make( RevisionRepository::class )
			),
			new CustomerTypesController(
				$this->container->make( CustomerTypeRepository::class )
			),
			new SectionsController(
				$this->container->make( SectionRepository::class )
			),
			new ConditionsController(
				$this->container->make( ConditionEngine::class )
			),
			new PreviewController(
				$this->container->make( FieldRepository::class ),
				$this->container->make( VisibilityResolver::class ),
				$this->container->make( ConditionEngine::class )
			),
			new SettingsController(),
			new NativeFieldsController(),
		);

		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}
	}
}
