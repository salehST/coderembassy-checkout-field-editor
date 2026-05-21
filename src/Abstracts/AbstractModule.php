<?php
namespace CoderEmbassy\CheckoutFieldsManager\Abstracts;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Container;
use CoderEmbassy\CheckoutFieldsManager\HookManager;
use CoderEmbassy\CheckoutFieldsManager\Interfaces\ModuleInterface;

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


