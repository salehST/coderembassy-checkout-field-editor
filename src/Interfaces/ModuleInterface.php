<?php
namespace CoderEmbassy\CheckoutFieldsManager\Interfaces;

defined( 'ABSPATH' ) || exit;

/**
 * Contract for plugin modules.
 */
interface ModuleInterface {
	/**
	 * Register hooks, filters, routes, and WordPress integration points.
	 */
	public function register(): void;

	/**
	 * Run post-registration boot logic after all modules are loaded.
	 */
	public function boot(): void;
}


