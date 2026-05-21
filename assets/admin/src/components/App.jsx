import { useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { store } from '../store';
import { getFields, getTypes, getSettings } from '../api/client';
import Topbar from './Topbar';
import Sidebar from './Sidebar';
import Dashboard from './Dashboard';
import NativeFields from './NativeFields';
import FieldBuilder from './FieldBuilder';
import CustomerTypes from './CustomerTypes';
import Settings from './Settings';
import ErrorBoundary from './shared/ErrorBoundary';

function Notice() {
	const notice = useSelect( ( s ) => s( store ).getNotice(), [] );
	const { setNotice } = useDispatch( store );
	useEffect( () => {
		if ( ! notice ) return;
		const timer = setTimeout( () => setNotice( null ), 4000 );
		return () => clearTimeout( timer );
	}, [ notice, setNotice ] );
	if ( ! notice ) return null;
	return <div className={ `cecfm-alert cecfm-alert--${ notice.type }` }>{ notice.message }</div>;
}

export default function App() {
	const { setFields, setTypes, setSettings } = useDispatch( store );
	const activeTab = useSelect( ( s ) => s( store ).getActiveTab(), [] );
	const isDark    = useSelect( ( s ) => s( store ).isDark(), [] );

	useEffect( () => {
		Promise.allSettled( [
			getFields(),
			getTypes(),
			getSettings(),
		] ).then( ( [ a, b, c ] ) => {
			if ( a.status === 'fulfilled' ) setFields( a.value?.data || [] );
			if ( b.status === 'fulfilled' ) setTypes( b.value?.data || [] );
			if ( c.status === 'fulfilled' ) setSettings( c.value?.data || {} );
		} );
	}, [ setFields, setTypes, setSettings ] );

	return (
		<div id="cecfm-root" data-theme={ isDark ? 'dark' : 'light' }>
			<Topbar />
			<div className="cecfm-layout">
				<Sidebar />
				<main className="cecfm-content">
					<Notice />
					{ activeTab === 'dashboard' && <ErrorBoundary key="dashboard"><Dashboard /></ErrorBoundary> }
					{ activeTab === 'native'    && <ErrorBoundary key="native"><NativeFields /></ErrorBoundary> }
					{ activeTab === 'fields'    && <ErrorBoundary key="fields"><FieldBuilder /></ErrorBoundary> }
					{ activeTab === 'types'     && <ErrorBoundary key="types"><CustomerTypes /></ErrorBoundary> }
					{ activeTab === 'settings'  && <ErrorBoundary key="settings"><Settings /></ErrorBoundary> }
				</main>
			</div>
		</div>
	);
}

