import { createRoot } from '@wordpress/element';
import { dispatch } from '@wordpress/data';
import App from './components/App';
import { store } from './store';

const { CECFM_ADMIN } = window;
dispatch( store ).setGlobals( {
	currency: CECFM_ADMIN?.currency ?? '$',
	currencyCode: CECFM_ADMIN?.currency_code ?? 'USD',
	version: CECFM_ADMIN?.version ?? '',
	currentUser: CECFM_ADMIN?.current_user ?? {},
	logoLight: CECFM_ADMIN?.logo_light ?? '',
	logoDark: CECFM_ADMIN?.logo_dark ?? '',
} );

const root = document.getElementById( 'cecfm-main' );
if ( root ) {
	createRoot( root ).render( <App /> );
}

