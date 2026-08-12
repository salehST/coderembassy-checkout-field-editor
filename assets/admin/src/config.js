/**
 * Single read of the data PHP hands the admin app.
 *
 * Everything goes through here rather than touching window directly, so a
 * rename on the PHP side breaks in exactly one place instead of silently
 * yielding undefined all over the UI.
 */
const cfg = window.CECFM_ADMIN || {};

/**
 * wp_localize_script() casts every top-level scalar to a string, so `true`
 * arrives as "1", `false` as "", and a number as its decimal text. Values are
 * coerced here rather than compared directly — `Number.isFinite("9007…")` is
 * false, which silently pinned the section limit to the free value even on Pro.
 */
const bool = ( value ) => value === true || value === 1 || value === '1';

const int = ( value, fallback ) => {
	const n = Number( value );

	return Number.isFinite( n ) && n > 0 ? n : fallback;
};

export const isPro = bool( cfg.is_pro );

/** @type {Record<string, boolean>} */
export const features = cfg.features || {};

/** Is a given Pro capability available right now? */
export const can = ( feature ) => bool( features[ feature ] );

export const maxSections = int( cfg.max_sections, 1 );

/** @type {Array<{value:string,label:string,pro:boolean,locked:boolean,block:boolean}>} */
export const fieldTypes = Array.isArray( cfg.field_types ) ? cfg.field_types : [];

export const upgradeUrl = cfg.upgrade_url || 'https://coderembassy.com/checkout-fields-manager/';

/** Capability keys — must match FeatureGate's constants in PHP. */
export const FEATURE = {
	CUSTOMER_TYPES:       'customer_types',
	CONDITIONS:           'conditions',
	CONDITIONAL_REQUIRED: 'conditional_required',
	PRICING:              'pricing',
	ADVANCED_FIELD_TYPES: 'advanced_field_types',
	UNLIMITED_SECTIONS:   'unlimited_sections',
	TEMPLATES:            'templates',
	IMPORT_EXPORT:        'import_export',
	PREVIEW:              'preview',
	REVISIONS:            'revisions',
	ANALYTICS:            'analytics',
	MULTILINGUAL:         'multilingual',
};

export default cfg;
