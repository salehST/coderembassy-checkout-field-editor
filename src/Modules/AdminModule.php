<?php
namespace CoderEmbassy\CheckoutFieldsManager\Modules;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Abstracts\AbstractModule;
use CoderEmbassy\CheckoutFieldsManager\Admin\AdminPage;
use CoderEmbassy\CheckoutFieldsManager\Admin\RestController;

class AdminModule extends AbstractModule {
	public function register(): void {
		$admin_page      = $this->container->make( AdminPage::class );
		$rest_controller = $this->container->make( RestController::class );

		$this->hooks->add( 'admin_menu', array( $admin_page, 'registerMenu' ) );
		$this->hooks->add( 'admin_enqueue_scripts', array( $admin_page, 'enqueueAssets' ) );
		$this->hooks->add( 'rest_api_init', array( $rest_controller, 'registerRoutes' ) );
	}
}


