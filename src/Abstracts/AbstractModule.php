<?php
namespace CoderEmbassy\CheckoutFieldEditor\Abstracts;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldEditor\Container;
use CoderEmbassy\CheckoutFieldEditor\HookManager;
use CoderEmbassy\CheckoutFieldEditor\Interfaces\ModuleInterface;

abstract class AbstractModule implements ModuleInterface {
	protected Container $container;
	protected HookManager $hooks;

	public function __construct( Container $container, HookManager $hooks ) {
		$this->container = $container;
		$this->hooks     = $hooks;
	}

	abstract public function register(): void;

	public function boot(): void {}
}
