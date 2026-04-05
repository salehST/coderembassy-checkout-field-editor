<?php
namespace CoderEmbassy\CheckoutFieldEditor\Modules\Licensing;

defined( 'ABSPATH' ) || exit;

/**
 * Free plugin feature gate — always returns free-tier values.
 * No licensing checks; all limits are hard-coded to free-tier defaults.
 */
class FeatureGate {

	const FREE_FIELDS_MAX      = 3;
	const FREE_CONDITIONS_MAX  = 1;
	const FREE_SECTIONS_MAX    = 1;
	const PRICING_ENGINE       = 'pricing_engine';
	const REVISION_HISTORY     = 'revision_history';
	const CUSTOMER_TYPES_UNLIMITED = 'customer_types_unlimited';

	public static function isPro(): bool {
		return false;
	}

	public static function can( string $feature ): bool {
		return false;
	}
}
