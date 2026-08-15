import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { store } from '../../store';
import { getBranding, getStatCards } from '../../extensions';

function StatCard( { icon, value, label, color } ) {
	return (
		<div className="cecfm-stat-card" style={ { '--stat-color': color } }>
			<span className={ `dashicons ${ icon } cecfm-stat-card__icon` } />
			<div className="cecfm-stat-card__body">
				<span className="cecfm-stat-card__value">{ value }</span>
				<span className="cecfm-stat-card__label">{ label }</span>
			</div>
		</div>
	);
}

function QuickAction( { icon, label, onClick } ) {
	return (
		<button type="button" className="cecfm-quick-action" onClick={ onClick }>
			<span className={ `dashicons ${ icon } cecfm-quick-action__icon` } />
			<span className="cecfm-quick-action__label">{ label }</span>
			<span className="dashicons dashicons-arrow-right-alt2 cecfm-quick-action__arrow" />
		</button>
	);
}

export default function Dashboard() {
	const globals   = useSelect( ( s ) => s( store ).getGlobals(), [] );
	const fields    = useSelect( ( s ) => s( store ).getFields(), [] );
	const types     = useSelect( ( s ) => s( store ).getTypes(), [] );
	const { setActiveTab, setEditingFieldId } = useDispatch( store );

	const enabledFields = fields.filter( ( f ) => f.enabled !== false ).length;

	// An add-on can put its own name and version on the hero card, so an
	// installed Pro reads as Pro rather than as the base plugin.
	const branding = getBranding();
	const title    = branding?.name || __( 'Checkout Fields Manager', 'coderembassy-checkout-fields-manager' );
	const version  = branding?.version || globals?.version || '1.0.0';
	const badge    = branding?.badge || '';

	return (
		<div className="cecfm-dashboard">

			<div className="cecfm-hero-card">
				<div className="cecfm-hero-card__body">
					<h1 className="cecfm-hero-card__title">{ title }</h1>
					<p className="cecfm-hero-card__desc">
						{ __( 'Customize WooCommerce checkout with extra fields, customer types, and conditional visibility.', 'coderembassy-checkout-fields-manager' ) }
					</p>
					<div className="cecfm-hero-card__actions">
						<button
							type="button"
							className="cecfm-btn cecfm-btn--primary cecfm-btn--lg"
							onClick={ () => { setActiveTab( 'fields' ); setEditingFieldId( 'new' ); } }
						>
							<span className="dashicons dashicons-plus-alt2" />
							{ __( 'Add Your First Field', 'coderembassy-checkout-fields-manager' ) }
						</button>
						<button
							type="button"
							className="cecfm-btn cecfm-btn--ghost cecfm-btn--lg"
							onClick={ () => setActiveTab( 'settings' ) }
						>
							{ __( 'Go to Settings', 'coderembassy-checkout-fields-manager' ) }
						</button>
					</div>
				</div>
				{ badge && <span className="cecfm-hero-card__pro-badge">{ badge }</span> }
				<span className="cecfm-hero-card__version">v{ version }</span>
			</div>

			<div className="cecfm-stats-row">
				<StatCard
					icon="dashicons-editor-ul"
					value={ fields.length }
					label={ __( 'Custom Fields', 'coderembassy-checkout-fields-manager' ) }
					color="var(--cecfm-indigo)"
				/>
				<StatCard
					icon="dashicons-marker"
					value={ enabledFields }
					label={ __( 'Active Fields', 'coderembassy-checkout-fields-manager' ) }
					color="var(--cecfm-green)"
				/>
				<StatCard
					icon="dashicons-groups"
					value={ types.length }
					label={ __( 'Customer Types', 'coderembassy-checkout-fields-manager' ) }
					color="var(--cecfm-violet)"
				/>
				{ getStatCards().map( ( Card, i ) => <Card key={ i } /> ) }
			</div>

			<div className="cecfm-dashboard-grid">
				<div className="cecfm-card">
					<h3 className="cecfm-card-title">{ __( 'Quick Actions', 'coderembassy-checkout-fields-manager' ) }</h3>
					<div className="cecfm-quick-actions">
						<QuickAction
							icon="dashicons-plus-alt2"
							label={ __( 'Create a new field', 'coderembassy-checkout-fields-manager' ) }
							onClick={ () => { setActiveTab( 'fields' ); setEditingFieldId( 'new' ); } }
						/>
						<QuickAction
							icon="dashicons-groups"
							label={ __( 'Manage customer types', 'coderembassy-checkout-fields-manager' ) }
							onClick={ () => setActiveTab( 'types' ) }
						/>
						<QuickAction
							icon="dashicons-layout"
							label={ __( 'Adjust global settings', 'coderembassy-checkout-fields-manager' ) }
							onClick={ () => setActiveTab( 'settings' ) }
						/>
					</div>
				</div>

				<div className="cecfm-card">
					<h3 className="cecfm-card-title">{ __( 'Getting Started', 'coderembassy-checkout-fields-manager' ) }</h3>
					<ol className="cecfm-getting-started">
						<li>
							<strong>{ __( 'Create custom fields', 'coderembassy-checkout-fields-manager' ) }</strong>
							<p>{ __( 'Use the Field Builder to add text, select, checkbox and other input types to your checkout.', 'coderembassy-checkout-fields-manager' ) }</p>
						</li>
						<li>
							<strong>{ __( 'Use customer types', 'coderembassy-checkout-fields-manager' ) }</strong>
							<p>{ __( 'Offer Private and Company checkout paths, then show different fields for each.', 'coderembassy-checkout-fields-manager' ) }</p>
						</li>
						<li>
							<strong>{ __( 'Group fields into sections', 'coderembassy-checkout-fields-manager' ) }</strong>
							<p>{ __( 'Organise fields under collapsible sections to keep checkout clean.', 'coderembassy-checkout-fields-manager' ) }</p>
						</li>
						<li>
							<strong>{ __( 'Add conditional logic', 'coderembassy-checkout-fields-manager' ) }</strong>
							<p>{ __( 'Show or hide fields based on customer type.', 'coderembassy-checkout-fields-manager' ) }</p>
						</li>
					</ol>
				</div>
			</div>
		</div>
	);
}

