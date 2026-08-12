import apiFetch from '@wordpress/api-fetch';

const BASE = window.CECFM_ADMIN?.rest_url ?? '';

apiFetch.use( apiFetch.createNonceMiddleware( window.CECFM_ADMIN?.nonce ?? '' ) );

// Fields
export const getFields = () => apiFetch( { url: BASE + 'fields' } );
export const createField = ( data ) => apiFetch( { url: BASE + 'fields', method: 'POST', data } );
export const updateField = ( id, data ) => apiFetch( { url: BASE + `fields/${ id }`, method: 'PUT', data } );
export const deleteField = ( id ) => apiFetch( { url: BASE + `fields/${ id }`, method: 'DELETE' } );
export const reorderFields = ( order ) => apiFetch( { url: BASE + 'fields/reorder', method: 'POST', data: { order } } );
export const getRevisions = ( id ) => apiFetch( { url: BASE + `fields/${ id }/revisions` } );
export const rollbackRevision = ( id, revId ) => apiFetch( { url: BASE + `fields/${ id }/rollback/${ revId }`, method: 'POST' } );

// Customer types. The free plugin ships a fixed Private/Company pair; the
// add-on replaces this screen with a full manager.
export const getTypes = () => apiFetch( { url: BASE + 'customer-types' } );
export const toggleCompanyType = ( enabled ) =>
	apiFetch( { url: BASE + 'customer-types/company-toggle', method: 'POST', data: { enabled } } );

// Settings
export const getSettings = () => apiFetch( { url: BASE + 'settings' } );
export const saveSettings = ( data ) => apiFetch( { url: BASE + 'settings', method: 'POST', data } );

// Native WooCommerce fields
export const getNativeFields = () => apiFetch( { url: BASE + 'native-fields' } );
export const saveNativeFields = ( data ) => apiFetch( { url: BASE + 'native-fields', method: 'POST', data } );

// Customer types, preview, templates, import/export and analytics are provided
// by the Pro add-on. It registers its own routes into this same namespace and
// calls them from its own bundle, so nothing for them belongs here.
