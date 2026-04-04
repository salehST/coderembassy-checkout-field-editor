<?php
namespace CoderEmbassy\CheckoutFieldEditor;

defined( 'ABSPATH' ) || exit;

use InvalidArgumentException;
use RuntimeException;

/**
 * Lightweight DI container with PSR-11-like behavior.
 */
class Container {
	/**
	 * @var array<string, callable(self):mixed>
	 */
	private array $bindings = array();

	/**
	 * @var array<string, callable(self):mixed>
	 */
	private array $singletons = array();

	/**
	 * @var array<string, mixed>
	 */
	private array $instances = array();

	public function bind( string $abstract, callable $factory ): void {
		$this->bindings[ $abstract ] = $factory;
	}

	public function singleton( string $abstract, callable $factory ): void {
		$this->singletons[ $abstract ] = $factory;
	}

	public function has( string $abstract ): bool {
		return isset( $this->instances[ $abstract ] ) || isset( $this->singletons[ $abstract ] ) || isset( $this->bindings[ $abstract ] ) || class_exists( $abstract );
	}

	/**
	 * PSR-11 compatibility method.
	 *
	 * @return mixed
	 */
	public function get( string $id ): mixed {
		return $this->make( $id );
	}

	/**
	 * @return mixed
	 */
	public function make( string $abstract ): mixed {
		if ( isset( $this->instances[ $abstract ] ) ) {
			return $this->instances[ $abstract ];
		}

		if ( isset( $this->singletons[ $abstract ] ) ) {
			$instance                     = $this->singletons[ $abstract ]( $this );
			$this->instances[ $abstract ] = $instance;
			return $instance;
		}

		if ( isset( $this->bindings[ $abstract ] ) ) {
			return $this->bindings[ $abstract ]( $this );
		}

		if ( ! class_exists( $abstract ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Container entry not found: %s', \esc_html( (string) $abstract ) )
			);
		}

		$reflection = new \ReflectionClass( $abstract );
		if ( ! $reflection->isInstantiable() ) {
			throw new RuntimeException(
				sprintf( 'Cannot instantiate: %s', \esc_html( (string) $abstract ) )
			);
		}

		$constructor = $reflection->getConstructor();
		if ( null === $constructor || 0 === $constructor->getNumberOfParameters() ) {
			return new $abstract();
		}

		$args = array();
		foreach ( $constructor->getParameters() as $parameter ) {
			$type = $parameter->getType();
			if ( ! $type instanceof \ReflectionNamedType || $type->isBuiltin() ) {
				if ( $parameter->isDefaultValueAvailable() ) {
					$args[] = $parameter->getDefaultValue();
					continue;
				}

				throw new RuntimeException(
					sprintf(
						'Unresolvable dependency %s::%s',
						\esc_html( (string) $abstract ),
						\esc_html( (string) $parameter->getName() )
					)
				);
			}

			$args[] = $this->make( $type->getName() );
		}

		return $reflection->newInstanceArgs( $args );
	}
}
