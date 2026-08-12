<?php
namespace CoderEmbassy\CheckoutFieldsManager\Modules\Licensing;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for what this install is allowed to do.
 *
 * The free plugin ships no Pro code. It only asks whether a Pro add-on is
 * present and licensed, via the `cecfm_is_pro` filter, and renders locked
 * placeholders where Pro capability would otherwise appear.
 *
 * The Pro add-on answers that filter and supplies the actual features by
 * hooking the extension points listed in ExtensionPoints.
 */
class FeatureGate {

	// --- Pro capabilities ---
	public const CUSTOMER_TYPES        = 'customer_types';
	public const CONDITIONS            = 'conditions';
	public const CONDITIONAL_REQUIRED  = 'conditional_required';
	public const PRICING               = 'pricing';
	public const ADVANCED_FIELD_TYPES  = 'advanced_field_types';
	public const UNLIMITED_SECTIONS    = 'unlimited_sections';
	public const TEMPLATES             = 'templates';
	public const IMPORT_EXPORT         = 'import_export';
	public const PREVIEW               = 'preview';
	public const REVISIONS             = 'revisions';
	public const ANALYTICS             = 'analytics';
	public const MULTILINGUAL          = 'multilingual';

	/** Free plan: maximum number of custom sections. */
	public const FREE_SECTIONS_MAX = 1;

	/** The slug this plugin's Company toggle creates and removes. */
	public const COMPANY_TYPE = 'company';

	/**
	 * Customer type slugs this install is allowed to manage and assign.
	 *
	 * Without the add-on that is the default type plus Company — precisely what
	 * the Customer Types screen offers. The rule is read from the data rather
	 * than hardcoded, because the default type's slug has varied between
	 * versions ("retail" in the seed, shown as "Private" in the UI).
	 *
	 * Types an add-on created are left in the database untouched; they are
	 * simply not offered, so a store that downgrades sees a coherent choice
	 * instead of types nothing can manage.
	 *
	 * @return string[]|null Null means no restriction — the add-on manages them.
	 */
	public static function allowedCustomerTypes(): ?array {
		if ( self::can( self::CUSTOMER_TYPES ) ) {
			return null;
		}

		global $wpdb;

		$slugs = array( self::COMPANY_TYPE );

		if ( $wpdb instanceof \wpdb ) {
			$table = $wpdb->prefix . 'cecfm_customer_types';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$default = $wpdb->get_col( "SELECT slug FROM `{$table}` WHERE is_default = 1" );

			if ( is_array( $default ) ) {
				$slugs = array_merge( $slugs, array_map( 'strval', $default ) );
			}
		}

		return array_values( array_unique( (array) \apply_filters( 'cecfm_free_customer_types', $slugs ) ) );
	}

	/**
	 * Is a licensed Pro add-on active?
	 *
	 * Free always returns false on its own — nothing in this plugin ever sets
	 * it true. The Pro add-on hooks `cecfm_is_pro` once its license validates.
	 */
	public static function isPro(): bool {
		return (bool) \apply_filters( 'cecfm_is_pro', false );
	}

	/**
	 * Is a specific Pro capability available?
	 *
	 * Every capability is gated behind isPro(), but each also gets its own
	 * filter so a future tier can unlock features individually.
	 *
	 * @param string $feature One of the class constants above.
	 */
	public static function can( string $feature ): bool {
		return (bool) \apply_filters( 'cecfm_can', self::isPro(), $feature );
	}

	/**
	 * Maximum custom sections allowed. Free gets one so the concept is visible.
	 */
	public static function maxSections(): int {
		$max = self::can( self::UNLIMITED_SECTIONS ) ? PHP_INT_MAX : self::FREE_SECTIONS_MAX;

		return (int) \apply_filters( 'cecfm_max_sections', $max );
	}

	/**
	 * Capability list handed to the admin app so it knows what to lock.
	 *
	 * @return array<string, bool>
	 */
	public static function all(): array {
		$features = array(
			self::CUSTOMER_TYPES,
			self::CONDITIONS,
			self::CONDITIONAL_REQUIRED,
			self::PRICING,
			self::ADVANCED_FIELD_TYPES,
			self::UNLIMITED_SECTIONS,
			self::TEMPLATES,
			self::IMPORT_EXPORT,
			self::PREVIEW,
			self::REVISIONS,
			self::ANALYTICS,
			self::MULTILINGUAL,
		);

		$map = array();
		foreach ( $features as $feature ) {
			$map[ $feature ] = self::can( $feature );
		}

		return $map;
	}
}
