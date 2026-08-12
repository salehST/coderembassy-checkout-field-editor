import { registerStore } from '@wordpress/data';

const DEFAULT_STATE = {
	fields: [],
	sections: [],
	// Customer types come from the Pro add-on. Free keeps the empty array so
	// components that list them simply render nothing instead of crashing.
	types: [],
	settings: {},
	globals: {},
	ui: { activeTab: 'dashboard', editingFieldId: null, notice: null, isDark: false },
};

const T = {
	SET_FIELDS: 'SET_FIELDS',
	SET_SECTIONS: 'SET_SECTIONS',
	SET_TYPES: 'SET_TYPES',
	SET_SETTINGS: 'SET_SETTINGS',
	SET_GLOBALS: 'SET_GLOBALS',
	SET_ACTIVE_TAB: 'SET_ACTIVE_TAB',
	SET_EDITING_FIELD_ID: 'SET_EDITING_FIELD_ID',
	SET_NOTICE: 'SET_NOTICE',
	TOGGLE_DARK: 'TOGGLE_DARK',
};

export const actions = {
	setFields: ( fields ) => ( { type: T.SET_FIELDS, fields } ),
	setSections: ( sections ) => ( { type: T.SET_SECTIONS, sections } ),
	setTypes: ( types ) => ( { type: T.SET_TYPES, types } ),
	setSettings: ( settings ) => ( { type: T.SET_SETTINGS, settings } ),
	setGlobals: ( globals ) => ( { type: T.SET_GLOBALS, globals } ),
	setActiveTab: ( tab ) => ( { type: T.SET_ACTIVE_TAB, tab } ),
	setEditingFieldId: ( id ) => ( { type: T.SET_EDITING_FIELD_ID, id } ),
	setNotice: ( notice ) => ( { type: T.SET_NOTICE, notice } ),
	toggleDark: () => ( { type: T.TOGGLE_DARK } ),
};

function reducer( state = DEFAULT_STATE, action ) {
	switch ( action.type ) {
		case T.SET_FIELDS:
			return { ...state, fields: action.fields || [] };
		case T.SET_SECTIONS:
			return { ...state, sections: action.sections || [] };
		case T.SET_TYPES:
			return { ...state, types: action.types || [] };
		case T.SET_SETTINGS:
			return { ...state, settings: action.settings || {} };
		case T.SET_GLOBALS:
			return { ...state, globals: { ...state.globals, ...( action.globals || {} ) } };
		case T.SET_ACTIVE_TAB:
			return { ...state, ui: { ...state.ui, activeTab: action.tab } };
		case T.SET_EDITING_FIELD_ID:
			return { ...state, ui: { ...state.ui, editingFieldId: action.id } };
		case T.SET_NOTICE:
			return { ...state, ui: { ...state.ui, notice: action.notice } };
		case T.TOGGLE_DARK:
			return { ...state, ui: { ...state.ui, isDark: ! state.ui.isDark } };
		default:
			return state;
	}
}

export const selectors = {
	getFields: ( s ) => s.fields,
	getSections: ( s ) => s.sections,
	getTypes: ( s ) => s.types,
	getSettings: ( s ) => s.settings,
	getGlobals: ( s ) => s.globals,
	getActiveTab: ( s ) => s.ui.activeTab,
	getEditingFieldId: ( s ) => s.ui.editingFieldId,
	getNotice: ( s ) => s.ui.notice,
	isDark: ( s ) => s.ui.isDark,
	isPro: ( s ) => !! s.globals.isPro,
};

export const store = 'cecfm/admin';

registerStore( store, { reducer, actions, selectors } );
