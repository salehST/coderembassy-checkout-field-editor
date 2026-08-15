import { createRoot } from '@wordpress/element';
import { dispatch } from '@wordpress/data';
import App from './components/App';
import { store } from './store';
import { isPro, features } from './config';

// Publishes window.cecfm so an add-on bundle can register into it. This must be
// imported before the mount below, but publishing the registry is only half of
// it — see the mount comment for why we also wait.
import './extensions';

const { CECFM_ADMIN: config } = window;
dispatch( store ).setGlobals( {
	currency: config?.currency ?? '$',
	currencyCode: config?.currency_code ?? 'USD',
	version: config?.version ?? '',
	currentUser: config?.current_user ?? {},
	logoLight: config?.logo_light ?? '',
	logoDark: config?.logo_dark ?? '',
	isPro,
	features,
} );

/**
 * An add-on's bundle declares this one as a script dependency, so it is a
 * separate <script> that the parser runs *after* this file finishes. Mounting
 * inline here would therefore render before the add-on had registered any of
 * its tabs, panels or branding, and those are read during render — the screens
 * would only appear once some unrelated state change forced a re-render.
 *
 * Waiting for the document to finish parsing puts the mount after every
 * enqueued script has executed, so the first render already sees them.
 */
function mount() {
	const root = document.getElementById( 'cecfm-main' );
	if ( root ) {
		createRoot( root ).render( <App /> );
	}
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
