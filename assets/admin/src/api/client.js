import apiFetch from '@wordpress/api-fetch';

const BASE = window.CA?.rest_url ?? '';

apiFetch.use( apiFetch.createNonceMiddleware( window.CA?.nonce ?? '' ) );

export const getFields = () => apiFetch( { url: BASE + 'fields' } );
export const createField = ( data ) => apiFetch( { url: BASE + 'fields', method: 'POST', data } );
export const updateField = ( id, data ) => apiFetch( { url: BASE + `fields/${ id }`, method: 'PUT', data } );
export const deleteField = ( id ) => apiFetch( { url: BASE + `fields/${ id }`, method: 'DELETE' } );
export const reorderFields = ( order ) => apiFetch( { url: BASE + 'fields/reorder', method: 'POST', data: { order } } );
export const getRevisions = ( id ) => apiFetch( { url: BASE + `fields/${ id }/revisions` } );
export const rollbackRevision = ( id, revId ) => apiFetch( { url: BASE + `fields/${ id }/revisions/${ revId }/rollback`, method: 'POST' } );
export const getTypes = () => apiFetch( { url: BASE + 'customer-types' } );
export const toggleCompanyType = ( enabled ) =>
	apiFetch( { url: BASE + 'customer-types/company-toggle', method: 'POST', data: { enabled } } );
export const getSettings = () => apiFetch( { url: BASE + 'settings' } );
export const saveSettings = ( data ) => apiFetch( { url: BASE + 'settings', method: 'POST', data } );
export const previewField = ( data ) => apiFetch( { url: BASE + 'preview', method: 'POST', data } );
export const getNativeFields = () => apiFetch( { url: BASE + 'native-fields' } );
export const saveNativeFields = ( data ) => apiFetch( { url: BASE + 'native-fields', method: 'POST', data } );
