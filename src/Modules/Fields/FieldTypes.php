<?php
namespace CoderEmbassy\CheckoutFieldsManager\Modules\Fields;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\ExtensionPoints;

/**
 * The registry of field types this install can use.
 *
 * Free renders the basic set itself. Advanced types are rendered by the Pro
 * add-on, which registers them through the `cecfm_field_types` filter and
 * draws their markup via `cecfm_field_html`.
 *
 * The admin app reads {@see self::forAdmin()} rather than hardcoding a list,
 * so Pro types appear in the dropdown as locked entries even when the add-on
 * is not installed. That is deliberate: people should be able to see what
 * upgrading gets them.
 */
class FieldTypes {

	/** Types the free plugin renders on its own. */
	private const FREE = array(
		'text'           => 'Text',
		'textarea'       => 'Textarea',
		'select'         => 'Select (dropdown)',
		'radio'          => 'Radio buttons',
		'checkbox'       => 'Checkbox',
		'checkbox_group' => 'Checkbox group',
		'number'         => 'Number',
		'email'          => 'Email',
		'phone'          => 'Phone',
		'date'           => 'Date picker',
		'hidden'         => 'Hidden',
		'heading'        => 'Heading / Divider',
	);

	/**
	 * Advanced types supplied by the Pro add-on.
	 *
	 * Listed here as labels only — no rendering logic — purely so the free
	 * admin UI can show them locked. The add-on registers the real
	 * implementations through the filter.
	 */
	private const PRO = array(
		'multiselect'   => 'Multi-select',
		'file'          => 'File upload',
		'repeater'      => 'Repeater',
		'custom_price'  => 'Custom price',
	);

	/** Types usable in the WooCommerce Checkout block. */
	private const BLOCK_SUPPORTED = array( 'text', 'select', 'checkbox' );

	/**
	 * Types that can actually be saved and rendered right now.
	 *
	 * @return array<string, string> type => label
	 */
	public static function all(): array {
		$types = self::FREE;

		/**
		 * Register additional field types.
		 *
		 * @param array<string, string> $types type => label
		 */
		$registered = \apply_filters( ExtensionPoints::FIELD_TYPES, $types );

		return is_array( $registered ) ? $registered : $types;
	}

	/**
	 * Can this install save and render the given type?
	 */
	public static function isAvailable( string $type ): bool {
		return array_key_exists( $type, self::all() );
	}

	/**
	 * The full list for the admin dropdown, including locked Pro types.
	 *
	 * @return array<int, array{value:string, label:string, pro:bool, locked:bool, block:bool}>
	 */
	public static function forAdmin(): array {
		$available = self::all();
		$list      = array();

		foreach ( self::FREE as $value => $label ) {
			$list[] = array(
				'value'  => $value,
				'label'  => $label,
				'pro'    => false,
				'locked' => false,
				'block'  => in_array( $value, self::BLOCK_SUPPORTED, true ),
			);
		}

		foreach ( self::PRO as $value => $label ) {
			$list[] = array(
				'value'  => $value,
				'label'  => $label,
				'pro'    => true,
				// Locked until the add-on registers a real implementation.
				'locked' => ! array_key_exists( $value, $available ),
				'block'  => in_array( $value, self::BLOCK_SUPPORTED, true ),
			);
		}

		// Any type a third party registered that is neither Free nor known Pro.
		foreach ( $available as $value => $label ) {
			if ( isset( self::FREE[ $value ] ) || isset( self::PRO[ $value ] ) ) {
				continue;
			}

			$list[] = array(
				'value'  => $value,
				'label'  => (string) $label,
				'pro'    => false,
				'locked' => false,
				'block'  => in_array( $value, self::BLOCK_SUPPORTED, true ),
			);
		}

		return $list;
	}
}
