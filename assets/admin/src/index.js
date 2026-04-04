import { createRoot } from '@wordpress/element';
import { dispatch } from '@wordpress/data';
import App from './components/App';
import { store } from './store';

const { CA } = window;
dispatch( store ).setGlobals( {
	currency: CA?.currency ?? '$',
	currencyCode: CA?.currency_code ?? 'USD',
	version: CA?.version ?? '',
	currentUser: CA?.current_user ?? {},
	logoLight: CA?.logo_light ?? '',
	logoDark: CA?.logo_dark ?? '',
} );

const root = document.getElementById( 'ca-main' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
