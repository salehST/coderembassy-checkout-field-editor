<?php
namespace CoderEmbassy\CheckoutFieldEditor\Modules;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldEditor\Abstracts\AbstractModule;
use CoderEmbassy\CheckoutFieldEditor\Database\Migrator;

class DatabaseModule extends AbstractModule {
	public function register(): void {
		$migrator = $this->container->make( Migrator::class );
		$this->hooks->add( 'plugins_loaded', array( $migrator, 'maybeMigrate' ), 20 );
	}
}
