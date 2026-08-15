<?php
namespace CoderEmbassy\CheckoutFieldsManager;

defined( 'ABSPATH' ) || exit;

/**
 * The public contract between this plugin and its Pro add-on.
 *
 * Every hook an add-on is allowed to rely on is named here once, so the two
 * plugins can never drift on a string literal. Anything not listed here is
 * internal and may change without notice.
 *
 * These are ordinary WordPress hooks, so third-party developers can use them
 * too — that is intentional.
 */
final class ExtensionPoints {

	/**
	 * Filter: bool. Is a licensed Pro add-on active?
	 * Free never sets this true; the add-on answers it once its license validates.
	 */
	public const IS_PRO = 'cecfm_is_pro';

	/**
	 * Filter: bool. Per-capability override. Receives ( bool $allowed, string $feature ).
	 */
	public const CAN = 'cecfm_can';

	/**
	 * Filter: int. Maximum number of custom sections allowed.
	 */
	public const MAX_SECTIONS = 'cecfm_max_sections';

	/**
	 * Filter: array<string, array{label:string, render?:callable}>.
	 * Register additional field types. Free ships the basic set; the add-on
	 * appends file, multiselect, repeater and custom_price.
	 */
	public const FIELD_TYPES = 'cecfm_field_types';

	/**
	 * Filter: bool. Override whether a field renders.
	 * Receives ( bool $visible, CECFM_Field $field, array $context ).
	 *
	 * Anything that hides a field here MUST also be reflected by
	 * FIELD_IS_REQUIRED, otherwise a hidden field can still block checkout.
	 */
	public const FIELD_IS_VISIBLE = 'cecfm_field_is_visible';

	/**
	 * Filter: bool. Override whether a field is required.
	 * Receives ( bool $required, CECFM_Field $field, array $context ).
	 */
	public const FIELD_IS_REQUIRED = 'cecfm_field_is_required';

	/**
	 * Filter: string. Override a field's rendered HTML.
	 */
	public const FIELD_HTML = 'cecfm_field_html';

	/** Action: fires immediately before a field renders. */
	public const BEFORE_FIELD_RENDER = 'cecfm_before_field_render';

	/** Action: fires immediately after a field renders. */
	public const AFTER_FIELD_RENDER = 'cecfm_after_field_render';

	/**
	 * Action: add WooCommerce cart fees derived from field values.
	 * Receives ( array $field_values, \WC_Cart $cart ).
	 */
	public const CART_FEES = 'cecfm_cart_fees';

	/**
	 * Filter: array. Adjust the checkout context for a Store API (Blocks) request.
	 * Receives ( array $context, \WC_Order $order, \WP_REST_Request $request, array $params ).
	 *
	 * Needed because some values only exist inside the Store API request and are
	 * not visible to the standard context builder.
	 */
	public const BLOCKS_CONTEXT = 'cecfm_blocks_checkout_context';

	/**
	 * Filter: array. Validation result for a single field.
	 */
	public const VALIDATE_FIELD = 'cecfm_validate_field';

	/** Action: fires after a full validation pass. */
	public const AFTER_VALIDATION = 'cecfm_after_validation';

	/**
	 * Filter: string. The active customer type slug for the current request.
	 * Free has no customer type engine and always resolves to an empty string.
	 */
	public const CURRENT_CUSTOMER_TYPE = 'cecfm_current_customer_type';

	/** Action: fires when the active customer type changes. */
	public const CUSTOMER_TYPE_CHANGED = 'cecfm_customer_type_changed';

	/** Action: fires on plugin deactivation. */
	public const DEACTIVATE = 'cecfm_deactivate';

	/**
	 * Filter: declare additional settings.
	 *
	 * Settings live in this plugin's single option, so an add-on must declare
	 * any key it owns as `key => array( type, default )` or the value is
	 * dropped on save. Types: bool, key, text, textarea, raw, hex, absint,
	 * absint_or_empty.
	 */
	public const SETTINGS_SCHEMA = 'cecfm_settings_schema';

	/**
	 * Filter: how many revisions to retain per entity.
	 *
	 * Return zero or less to keep every revision.
	 */
	public const KEEP_REVISIONS = 'cecfm_keep_revisions';

	private function __construct() {}
}
