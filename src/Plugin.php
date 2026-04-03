<?php
namespace CoderEmbassy\CheckoutFieldEditor;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldEditor\Admin\AdminPage;
use CoderEmbassy\CheckoutFieldEditor\Admin\RestController;
use CoderEmbassy\CheckoutFieldEditor\Interfaces\ModuleInterface;
use CoderEmbassy\CheckoutFieldEditor\Modules\AdminModule;
use CoderEmbassy\CheckoutFieldEditor\Modules\Conditions\ConditionsModule;
use CoderEmbassy\CheckoutFieldEditor\Modules\CustomerTypes\CustomerTypesModule;
use CoderEmbassy\CheckoutFieldEditor\Modules\DatabaseModule;
use CoderEmbassy\CheckoutFieldEditor\Modules\Fields\FieldsModule;
use CoderEmbassy\CheckoutFieldEditor\Modules\FrontendModule;
use CoderEmbassy\CheckoutFieldEditor\Modules\Validation\ValidationModule;

class Plugin {
	private static ?self $instance = null;
	private Container $container;
	private HookManager $hooks;

	/**
	 * @var array<int, ModuleInterface>
	 */
	private array $modules = array();

	private bool $initialized = false;

	private function __construct() {
		$this->container = new Container();
		$this->hooks     = new HookManager();
	}

	public static function getInstance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init(): void {
		if ( $this->initialized ) {
			return;
		}

		$this->registerBindings();
		$this->loadModules();

		foreach ( $this->modules as $module ) {
			$module->register();
		}

		$this->hooks->register();

		foreach ( $this->modules as $module ) {
			$module->boot();
		}

		$this->initialized = true;
	}

	private function registerBindings(): void {
		$this->container->singleton(
			'wpdb',
			static fn (): \wpdb => $GLOBALS['wpdb']
		);
		$this->container->singleton(
			\wpdb::class,
			static fn (): \wpdb => $GLOBALS['wpdb']
		);

		$this->container->singleton(
			Container::class,
			fn (): Container => $this->container
		);

		$this->container->singleton(
			HookManager::class,
			fn (): HookManager => $this->hooks
		);

		$this->container->singleton(
			AdminPage::class,
			fn (): AdminPage => new AdminPage()
		);

		$this->container->singleton(
			RestController::class,
			fn (): RestController => new RestController( $this->container )
		);
		// CheckoutRenderer and BlocksIntegration are bound by FrontendModule::register()
		// with their full constructor dependencies — no stub binding needed here.
	}

	private function loadModules(): void {
		$module_order = array(
			DatabaseModule::class,
			FieldsModule::class,
			CustomerTypesModule::class,
			ConditionsModule::class,
			ValidationModule::class,
			FrontendModule::class,
			AdminModule::class,
		);

		foreach ( $module_order as $module_class ) {
			$module = $this->container->make( $module_class );
			if ( $module instanceof ModuleInterface ) {
				$this->modules[] = $module;
			}
		}
	}
}
