<?php
/**
 * Boot smoke test.
 *
 * Loads WordPress with this plugin (and the Pro add-on, when present), drives
 * the paths that only run in a real request, and fails loudly on a fatal.
 *
 * This exists because the expensive bugs in this codebase have not been logic
 * errors — they were a constructor signature that drifted between the two
 * plugins, a missing `use` statement, and registrations that landed in a bucket
 * nothing rendered. None of those show up in `php -l`; all of them are caught
 * here in a second.
 *
 * Usage:
 *   php tests/smoke-boot.php /path/to/wordpress
 *
 * Exits 0 when everything passes, 1 otherwise.
 */

$wp_root = $argv[1] ?? getenv( 'WP_ROOT' ) ?: 'C:/laragon/www/plugins';
$wp_load = rtrim( str_replace( '\\', '/', $wp_root ), '/' ) . '/wp-load.php';

if ( ! file_exists( $wp_load ) ) {
	fwrite( STDERR, "wp-load.php not found at {$wp_load}\nPass the WordPress root as the first argument.\n" );
	exit( 1 );
}

$failures = array();
$checks   = 0;

function check( string $label, bool $ok, string $detail = '' ): void {
	global $failures, $checks;
	++$checks;
	if ( $ok ) {
		echo "  PASS  {$label}\n";
		return;
	}
	$failures[] = $label . ( '' !== $detail ? " — {$detail}" : '' );
	echo "  FAIL  {$label}" . ( '' !== $detail ? " — {$detail}" : '' ) . "\n";
}

register_shutdown_function(
	static function (): void {
		$e = error_get_last();
		if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR ), true ) ) {
			fwrite( STDERR, "\nFATAL: {$e['message']}\n  at {$e['file']}:{$e['line']}\n" );
			exit( 1 );
		}
	}
);

define( 'WP_USE_THEMES', false );
$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require $wp_load;

echo "\nBase plugin\n";

$base = 'CoderEmbassy\\CheckoutFieldsManager\\';
check( 'base plugin loaded', class_exists( $base . 'Plugin' ) );
check( 'settings schema is non-empty', count( call_user_func( array( $base . 'Admin\\Controllers\\SettingsController', 'schema' ) ) ) > 0 );

$defaults = call_user_func( array( $base . 'Admin\\Controllers\\SettingsController', 'defaults' ) );
check( 'every schema entry yields a default', count( $defaults ) === count( call_user_func( array( $base . 'Admin\\Controllers\\SettingsController', 'schema' ) ) ) );

// Every setting the front end reads must be declared, or it is dropped on save.
$plugin_dir = dirname( __DIR__ );
$referenced = array();
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin_dir . '/src' ) ) as $file ) {
	if ( 'php' !== $file->getExtension() ) {
		continue;
	}
	if ( preg_match_all( "/\\\$settings\\[\s*'([a-z_0-9]+)'\s*\\]/", (string) file_get_contents( $file->getPathname() ), $m ) ) {
		foreach ( $m[1] as $key ) {
			$referenced[ $key ] = $file->getFilename();
		}
	}
}
$undeclared = array_diff( array_keys( $referenced ), array_keys( $defaults ) );
check(
	'no setting is read but undeclared',
	empty( $undeclared ),
	empty( $undeclared ) ? '' : implode( ', ', $undeclared )
);

// The add-on, when installed.
$pro_file = WP_PLUGIN_DIR . '/coderembassy-checkout-fields-manager-pro/coderembassy-checkout-fields-manager-pro.php';

if ( file_exists( $pro_file ) ) {
	echo "\nPro add-on\n";
	require_once $pro_file;

	$pro = 'CoderEmbassy\\CheckoutFieldsManagerPro\\';
	check( 'add-on loaded', defined( 'CECFMP_VERSION' ) );
	check( 'base guard sees the base plugin', function_exists( 'cecfmp_base_is_active' ) && cecfmp_base_is_active() );

	call_user_func( array( $pro . 'Database\\Installer', 'ensureTable' ) );
	call_user_func( array( $pro . 'Bootstrap', 'init' ) );
	call_user_func( array( $pro . 'LicenseSdkBootstrap', 'init' ) );
	call_user_func( array( $pro . 'Admin\\RestRoutes', 'init' ) );
	call_user_func( array( $pro . 'Admin\\Assets', 'init' ) );
	check( 'add-on boot sequence ran', true );

	// Every service getter, because these build objects out of the base plugin
	// and are exactly where a changed constructor signature surfaces.
	$instance = call_user_func( array( $pro . 'Bootstrap', 'instance' ) );
	foreach ( array( 'conditions', 'customerTypes', 'customerTypeRepository', 'fields', 'sections', 'resolver', 'context', 'pricing', 'wpml' ) as $service ) {
		$ok     = true;
		$detail = '';
		try {
			$instance->$service();
		} catch ( \Throwable $e ) {
			$ok     = false;
			$detail = $e->getMessage();
		}
		check( "service {$service}()", $ok, $detail );
	}
}

echo "\nREST routes\n";

do_action( 'rest_api_init' );
$routes = array_keys( rest_get_server()->get_routes() );
$ours   = array_filter( $routes, static fn( $r ) => false !== strpos( $r, 'coderembassy-checkout-fields-manager' ) );

foreach ( array( '/fields', '/customer-types', '/settings', '/sections' ) as $route ) {
	check(
		"route {$route} registered",
		(bool) array_filter( $ours, static fn( $r ) => false !== strpos( $r, $route ) )
	);
}

if ( defined( 'CECFMP_VERSION' ) ) {
	$has_route = static fn( string $route ): bool =>
		(bool) array_filter( $ours, static fn( $r ) => false !== strpos( $r, $route ) );

	// The add-on gates its feature routes behind the licence, so what counts as
	// correct depends on licence state. Asserting they are always present made
	// this fail the moment a licence was removed, reporting a working gate as a
	// bug — so assert the gate instead.
	$licensed = \CoderEmbassy\CheckoutFieldsManagerPro\Bootstrap::instance()->isLicensed();
	echo '  (licence state: ' . ( $licensed ? 'licensed' : 'unlicensed' ) . ")\n";

	// Reachable either way — you cannot enter a key without them.
	foreach ( array( '/license/status' ) as $route ) {
		check( "add-on route {$route} registered", $has_route( $route ) );
	}

	foreach ( array( '/templates', '/analytics', '/import-export/export' ) as $route ) {
		check(
			$licensed
				? "add-on route {$route} registered"
				: "add-on route {$route} correctly gated off",
			$licensed ? $has_route( $route ) : ! $has_route( $route )
		);
	}
}

echo "\n" . str_repeat( '-', 52 ) . "\n";
if ( $failures ) {
	echo count( $failures ) . " of {$checks} checks FAILED:\n";
	foreach ( $failures as $f ) {
		echo "  - {$f}\n";
	}
	exit( 1 );
}

echo "All {$checks} checks passed.\n";
exit( 0 );
