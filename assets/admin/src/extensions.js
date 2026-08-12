/**
 * The admin app's extension surface.
 *
 * The Pro add-on ships its own bundle, enqueued with this one as a dependency,
 * so it runs before the app mounts and can register itself here. The free app
 * then renders whatever it finds — and its own locked teasers where it finds
 * nothing.
 *
 * Tabs use a plain registry rather than SlotFill because a tab is a full-screen
 * replacement chosen by key, not an accumulation of fills. Inline panels do use
 * SlotFill, since several add-ons could legitimately contribute to one editor.
 */

const registry = {
	/** @type {Record<string, Function>} */
	tabs: {},
	/** @type {{name: string, version: string}|null} */
	branding: null,
	/** @type {Function[]} */
	fieldPanels: [],
	/** @type {Function[]} */
	settingsPanels: [],
	/** @type {Array<{id: string, label: string, render: Function}>} */
	fieldTabs: [],
	/** @type {Array<{id: string, label: string, render: Function}>} */
	settingsTabs: [],
};

/**
 * Let an add-on name the product.
 *
 * The free plugin cannot know the add-on's title or version, and should not
 * hardcode either, so the add-on supplies them and the header follows.
 *
 * @param {{name: string, version: string}} info
 */
export function registerBranding( info ) {
	if ( info && typeof info.name === 'string' ) {
		registry.branding = info;
	}
}

/** @return {{name: string, version: string}|null} */
export function getBranding() {
	return registry.branding;
}

/**
 * Replace a tab's contents.
 *
 * @param {string}   key    Tab key, matching the sidebar item.
 * @param {Function} render React component rendered for that tab.
 */
export function registerTab( key, render ) {
	if ( typeof key !== 'string' || typeof render !== 'function' ) {
		return;
	}
	registry.tabs[ key ] = render;
}

/** @return {Function|null} */
export function getTab( key ) {
	return registry.tabs[ key ] || null;
}

/**
 * Inline panels contributed by an add-on.
 *
 * A plain registry rather than SlotFill: the add-on ships a separate bundle
 * that is never mounted inside this app's React tree, and a Fill only renders
 * when it is. Registering the component itself and having the host render it
 * works regardless of where the code came from.
 */
export function registerFieldPanel( component ) {
	if ( typeof component === 'function' ) {
		registry.fieldPanels.push( component );
	}
}

/** @return {Function[]} */
export function getFieldPanels() {
	return registry.fieldPanels;
}

export function registerSettingsPanel( component ) {
	if ( typeof component === 'function' ) {
		registry.settingsPanels.push( component );
	}
}

/** @return {Function[]} */
export function getSettingsPanels() {
	return registry.settingsPanels;
}

/**
 * Add a tab to the field editor.
 *
 * @param {string}   id      Tab key.
 * @param {string}   label   Tab label.
 * @param {Function} render  Component rendered when the tab is active.
 */
export function registerFieldTab( id, label, render ) {
	if ( typeof id === 'string' && typeof render === 'function' ) {
		registry.fieldTabs.push( { id, label, render } );
	}
}

/** @return {Array<{id: string, label: string, render: Function}>} */
export function getFieldTabs() {
	return registry.fieldTabs;
}

/** Add a tab to the Settings screen. Same shape as registerFieldTab. */
export function registerSettingsTab( id, label, render ) {
	if ( typeof id === 'string' && typeof render === 'function' ) {
		registry.settingsTabs.push( { id, label, render } );
	}
}

/** @return {Array<{id: string, label: string, render: Function}>} */
export function getSettingsTabs() {
	return registry.settingsTabs;
}

// Published for the add-on bundle, which cannot import from this one.
window.cecfm = window.cecfm || {};
window.cecfm.registerTab = registerTab;
window.cecfm.registerBranding = registerBranding;
window.cecfm.registerFieldPanel = registerFieldPanel;
window.cecfm.registerSettingsPanel = registerSettingsPanel;
window.cecfm.registerFieldTab = registerFieldTab;
window.cecfm.registerSettingsTab = registerSettingsTab;
