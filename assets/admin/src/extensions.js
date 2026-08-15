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
	/** @type {Array<{component: Function, tab: string}>} */
	fieldPanels: [],
	/** @type {Array<{component: Function, tab: string}>} */
	settingsPanels: [],
	/** @type {Array<{id: string, label: string, render: Function}>} */
	fieldTabs: [],
	/** @type {Array<{id: string, label: string, render: Function}>} */
	settingsTabs: [],
	/** @type {Function[]} */
	statCards: [],
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
	markConsumed( 'branding' );
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
	markConsumed( 'tabs' );
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
/**
 * Append a card to the field editor.
 *
 * @param {Function} component Rendered with { field, setField, isNewField }.
 * @param {string}   [tab]     Tab it belongs under once the editor is tabbed.
 */
export function registerFieldPanel( component, tab = 'basic' ) {
	if ( typeof component === 'function' ) {
		registry.fieldPanels.push( { component, tab } );
	}
}

/**
 * @param  {string}     [tab] Limit to one tab. Omit to get every panel.
 * @return {Function[]}
 */
export function getFieldPanels( tab ) {
	markConsumed( 'fieldPanels' );
	return registry.fieldPanels
		.filter( ( p ) => undefined === tab || p.tab === tab )
		.map( ( p ) => p.component );
}

/**
 * Append a settings card.
 *
 * @param {Function} component Rendered with { settings, setSetting }.
 * @param {string}   [tab]     Which tab it belongs under once the Settings
 *                             screen is in tabbed mode. Defaults to General.
 */
export function registerSettingsPanel( component, tab = 'general' ) {
	if ( typeof component === 'function' ) {
		registry.settingsPanels.push( { component, tab } );
	}
}

/**
 * @param  {string}     [tab] Limit to one tab. Omit to get every panel.
 * @return {Function[]}
 */
export function getSettingsPanels( tab ) {
	markConsumed( 'settingsPanels' );
	return registry.settingsPanels
		.filter( ( p ) => undefined === tab || p.tab === tab )
		.map( ( p ) => p.component );
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
	markConsumed( 'fieldTabs' );
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
	markConsumed( 'settingsTabs' );
	return registry.settingsTabs;
}

/**
 * Add a stat card to the dashboard's counters row.
 *
 * The card renders itself and sources its own count, because the free plugin
 * does not necessarily track what an add-on wants to count.
 *
 * @param {Function} component
 */
export function registerStatCard( component ) {
	if ( typeof component === 'function' ) {
		registry.statCards.push( component );
	}
}

/** @return {Function[]} */
export function getStatCards() {
	markConsumed( 'statCards' );
	return registry.statCards;
}

/**
 * Development aid: report anything registered that nothing ever rendered.
 *
 * A registration that lands in a bucket no screen reads fails silently — the
 * add-on looks correct, the feature is simply absent. That has happened to
 * branding, settings tabs and settings panels, so the check is built in.
 *
 * Runs once, after the first paint, and only with SCRIPT_DEBUG enabled.
 */
const consumed = new Set();

function markConsumed( bucket ) {
	consumed.add( bucket );
}

if ( typeof window !== 'undefined' ) {
	window.setTimeout( () => {
		if ( ! window.CECFM_ADMIN?.script_debug ) {
			return;
		}

		const populated = {
			tabs: Object.keys( registry.tabs ).length,
			branding: registry.branding ? 1 : 0,
			fieldPanels: registry.fieldPanels.length,
			settingsPanels: registry.settingsPanels.length,
			fieldTabs: registry.fieldTabs.length,
			settingsTabs: registry.settingsTabs.length,
			statCards: registry.statCards.length,
		};

		const orphaned = Object.keys( populated ).filter(
			( bucket ) => populated[ bucket ] > 0 && ! consumed.has( bucket )
		);

		if ( orphaned.length ) {
			// eslint-disable-next-line no-console
			console.warn(
				'[cecfm] Registered but never rendered: ' + orphaned.join( ', ' ) +
				'. The host app has no consumer for these, so the add-on feature will not appear.'
			);
		}
	}, 3000 );
}

// Published for the add-on bundle, which cannot import from this one.
window.cecfm = window.cecfm || {};
window.cecfm.registerTab = registerTab;
window.cecfm.registerBranding = registerBranding;
window.cecfm.registerFieldPanel = registerFieldPanel;
window.cecfm.registerSettingsPanel = registerSettingsPanel;
window.cecfm.registerFieldTab = registerFieldTab;
window.cecfm.registerSettingsTab = registerSettingsTab;
window.cecfm.registerStatCard = registerStatCard;
