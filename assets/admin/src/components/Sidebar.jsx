import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { store } from '../store';
import { can, FEATURE } from '../config';
import { getTab } from '../extensions';

/**
 * The free plugin's own screens, then the ones the Pro add-on provides.
 *
 * Pro entries stay visible when locked rather than being hidden — someone who
 * cannot see a feature cannot want it, and a sidebar that changes shape on
 * upgrade is disorienting. The padlock carries the message instead.
 */
const items = [
	{ key: 'dashboard', label: __( 'Dashboard', 'coderembassy-checkout-fields-manager' ), icon: 'dashicons-dashboard' },
	{ key: 'native',    label: __( 'Native Fields', 'coderembassy-checkout-fields-manager' ), icon: 'dashicons-edit-page' },
	{ key: 'fields',    label: __( 'Custom Fields', 'coderembassy-checkout-fields-manager' ), icon: 'dashicons-editor-ul' },
	{ key: 'types',     label: __( 'Customer Types', 'coderembassy-checkout-fields-manager' ), icon: 'dashicons-groups' },
	{ key: 'settings',  label: __( 'Settings', 'coderembassy-checkout-fields-manager' ), icon: 'dashicons-admin-settings' },

	// Provided by the add-on; shown locked with a teaser until it is installed.
	{ key: 'sections',  label: __( 'Sections', 'coderembassy-checkout-fields-manager' ), icon: 'dashicons-editor-insertmore', feature: FEATURE.UNLIMITED_SECTIONS },
	{ key: 'templates', label: __( 'Templates', 'coderembassy-checkout-fields-manager' ), icon: 'dashicons-layout', feature: FEATURE.TEMPLATES },
	{ key: 'preview',   label: __( 'Preview', 'coderembassy-checkout-fields-manager' ), icon: 'dashicons-visibility', feature: FEATURE.PREVIEW },
	{ key: 'analytics', label: __( 'Analytics', 'coderembassy-checkout-fields-manager' ), icon: 'dashicons-chart-bar', feature: FEATURE.ANALYTICS },
	{ key: 'importexport', label: __( 'Import / Export', 'coderembassy-checkout-fields-manager' ), icon: 'dashicons-database-export', feature: FEATURE.IMPORT_EXPORT },

	// Only meaningful once the add-on is installed, so it is hidden entirely
	// otherwise — there would be nothing behind it.
	{ key: 'license',   label: __( 'License', 'coderembassy-checkout-fields-manager' ), icon: 'dashicons-admin-network', addonOnly: true },
];

export default function Sidebar() {
	const activeTab = useSelect( ( s ) => s( store ).getActiveTab(), [] );
	const { setActiveTab } = useDispatch( store );

	return (
		<aside className="cecfm-sidebar">
			{ items.map( ( item ) => {
				if ( item.addonOnly && ! getTab( item.key ) ) {
					return null;
				}

				const locked = !! item.feature && ! can( item.feature );

				return (
					<button
						key={ item.key }
						type="button"
						className={ `cecfm-nav-item ${ activeTab === item.key ? 'is-active' : '' } ${ locked ? 'is-locked' : '' }` }
						onClick={ () => setActiveTab( item.key ) }
					>
						<span className={ `dashicons ${ item.icon }` } />
						<span className="cecfm-nav-item__label">{ item.label }</span>
						{ locked && (
							<span
								className="dashicons dashicons-lock cecfm-nav-item__lock"
								title={ __( 'Available in Pro', 'coderembassy-checkout-fields-manager' ) }
							/>
						) }
					</button>
				);
			} ) }
		</aside>
	);
}
