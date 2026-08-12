<?php
namespace CoderEmbassy\CheckoutFieldsManager\Admin;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Admin\Controllers\CustomerTypesController;
use CoderEmbassy\CheckoutFieldsManager\Admin\Controllers\FieldsController;
use CoderEmbassy\CheckoutFieldsManager\Admin\Controllers\NativeFieldsController;
use CoderEmbassy\CheckoutFieldsManager\Admin\Controllers\SectionsController;
use CoderEmbassy\CheckoutFieldsManager\Admin\Controllers\SettingsController;
use CoderEmbassy\CheckoutFieldsManager\Container;
use CoderEmbassy\CheckoutFieldsManager\Modules\CustomerTypes\CustomerTypeRepository;
use CoderEmbassy\CheckoutFieldsManager\Modules\Fields\FieldRepository;
use CoderEmbassy\CheckoutFieldsManager\Modules\Fields\RevisionRepository;
use CoderEmbassy\CheckoutFieldsManager\Modules\Fields\VisibilityResolver;
use CoderEmbassy\CheckoutFieldsManager\Modules\Sections\SectionRepository;

class RestController {
	public const REST_NAMESPACE = 'coderembassy-checkout-fields-manager/v1';

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
			new SettingsController(),
			new NativeFieldsController(),
		);

		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}
	}
}


