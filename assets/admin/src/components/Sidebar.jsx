import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { store } from '../store';

const items = [
	{ key: 'dashboard', label: __( 'Dashboard',      'coderembassy-checkout-fields-manager' ), icon: 'dashicons-dashboard' },
	{ key: 'native',    label: __( 'Native Fields',  'coderembassy-checkout-fields-manager' ), icon: 'dashicons-edit-page' },
	{ key: 'fields',    label: __( 'Custom Fields',  'coderembassy-checkout-fields-manager' ), icon: 'dashicons-editor-ul' },
	{ key: 'types',     label: __( 'Customer Types', 'coderembassy-checkout-fields-manager' ), icon: 'dashicons-groups' },
	{ key: 'settings',  label: __( 'Settings',       'coderembassy-checkout-fields-manager' ), icon: 'dashicons-admin-settings' },
];

export default function Sidebar() {
	const activeTab = useSelect( ( s ) => s( store ).getActiveTab(), [] );
	const { setActiveTab } = useDispatch( store );

	return (
		<aside className="cecfm-sidebar">
			{ items.map( ( item ) => (
				<button
					key={ item.key }
					type="button"
					className={ `cecfm-nav-item ${ activeTab === item.key ? 'is-active' : '' }` }
					onClick={ () => setActiveTab( item.key ) }
				>
					<span className={ `dashicons ${ item.icon }` } />
					<span className="cecfm-nav-item__label">{ item.label }</span>
				</button>
			) ) }
		</aside>
	);
}

