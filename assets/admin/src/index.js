import { createRoot } from '@wordpress/element';
import { dispatch } from '@wordpress/data';
import App from './components/App';
import { store } from './store';
import { isPro, features } from './config';

// Publishes window.cecfm for the add-on bundle. Must run before the app mounts
// so anything the add-on registered is present on the first render.
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

const root = document.getElementById( 'cecfm-main' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
