<?php
namespace CoderEmbassy\CheckoutFieldsManager;

defined( 'ABSPATH' ) || exit;

class HookManager {
	/**
	 * @var array<int, array{hook:string, callback:callable, priority:int, args:int}>
	 */
	private array $actions = array();

	/**
	 * @var array<int, array{hook:string, callback:callable, priority:int, args:int}>
	 */
	private array $filters = array();

	public function add( string $hook, callable $callback, int $priority = 10, int $args = 1 ): void {
		$this->actions[] = array(
			'hook'     => $hook,
			'callback' => $callback,
			'priority' => $priority,
			'args'     => $args,
		);
	}

	public function addFilter( string $hook, callable $callback, int $priority = 10, int $args = 1 ): void {
		$this->filters[] = array(
			'hook'     => $hook,
			'callback' => $callback,
			'priority' => $priority,
			'args'     => $args,
		);
	}

	public function register(): void {
		foreach ( $this->actions as $action ) {
			add_action( $action['hook'], $action['callback'], $action['priority'], $action['args'] );
		}

		foreach ( $this->filters as $filter ) {
			add_filter( $filter['hook'], $filter['callback'], $filter['priority'], $filter['args'] );
		}
	}
}


