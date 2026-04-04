import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { store } from '../store';

const items = [
	{ key: 'dashboard', label: __( 'Dashboard',      'coderembassy-checkout-field-editor' ), icon: 'dashicons-dashboard' },
	{ key: 'native',    label: __( 'Native Fields',  'coderembassy-checkout-field-editor' ), icon: 'dashicons-edit-page' },
	{ key: 'fields',    label: __( 'Custom Fields',  'coderembassy-checkout-field-editor' ), icon: 'dashicons-editor-ul' },
	{ key: 'types',     label: __( 'Customer Types', 'coderembassy-checkout-field-editor' ), icon: 'dashicons-groups' },
	{ key: 'settings',  label: __( 'Settings',       'coderembassy-checkout-field-editor' ), icon: 'dashicons-admin-settings' },
];

export default function Sidebar() {
	const activeTab = useSelect( ( s ) => s( store ).getActiveTab(), [] );
	const { setActiveTab } = useDispatch( store );

	return (
		<aside className="ca-sidebar">
			{ items.map( ( item ) => (
				<button
					key={ item.key }
					type="button"
					className={ `ca-nav-item ${ activeTab === item.key ? 'is-active' : '' }` }
					onClick={ () => setActiveTab( item.key ) }
				>
					<span className={ `dashicons ${ item.icon }` } />
					<span className="ca-nav-item__label">{ item.label }</span>
				</button>
			) ) }
		</aside>
	);
}
