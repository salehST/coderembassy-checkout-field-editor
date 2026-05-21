<?php
namespace CoderEmbassy\CheckoutFieldsManager\Modules;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Abstracts\AbstractModule;
use CoderEmbassy\CheckoutFieldsManager\Database\Migrator;

class DatabaseModule extends AbstractModule {
	public function register(): void {
		$migrator = $this->container->make( Migrator::class );
		$this->hooks->add( 'plugins_loaded', array( $migrator, 'maybeMigrate' ), 20 );
	}
}


