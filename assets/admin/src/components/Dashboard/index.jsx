import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { store } from '../../store';

function StatCard( { icon, value, label, color } ) {
	return (
		<div className="ca-stat-card" style={ { '--stat-color': color } }>
			<span className={ `dashicons ${ icon } ca-stat-card__icon` } />
			<div className="ca-stat-card__body">
				<span className="ca-stat-card__value">{ value }</span>
				<span className="ca-stat-card__label">{ label }</span>
			</div>
		</div>
	);
}

function QuickAction( { icon, label, onClick } ) {
	return (
		<button type="button" className="ca-quick-action" onClick={ onClick }>
			<span className={ `dashicons ${ icon } ca-quick-action__icon` } />
			<span className="ca-quick-action__label">{ label }</span>
			<span className="dashicons dashicons-arrow-right-alt2 ca-quick-action__arrow" />
		</button>
	);
}

export default function Dashboard() {
	const globals   = useSelect( ( s ) => s( store ).getGlobals(), [] );
	const fields    = useSelect( ( s ) => s( store ).getFields(), [] );
	const types     = useSelect( ( s ) => s( store ).getTypes(), [] );
	const { setActiveTab, setEditingFieldId } = useDispatch( store );

	const enabledFields = fields.filter( ( f ) => f.enabled !== false ).length;

	return (
		<div className="ca-dashboard">

			<div className="ca-hero-card">
				<div className="ca-hero-card__body">
					<h1 className="ca-hero-card__title">{ __( 'Checkout Architect', 'coderembassy-checkout-field-editor' ) }</h1>
					<p className="ca-hero-card__desc">
						{ __( 'Customize WooCommerce checkout with extra fields, customer types, and conditional visibility.', 'coderembassy-checkout-field-editor' ) }
					</p>
					<div className="ca-hero-card__actions">
						<button
							type="button"
							className="ca-btn ca-btn--primary ca-btn--lg"
							onClick={ () => { setActiveTab( 'fields' ); setEditingFieldId( 'new' ); } }
						>
							<span className="dashicons dashicons-plus-alt2" />
							{ __( 'Add Your First Field', 'coderembassy-checkout-field-editor' ) }
						</button>
						<button
							type="button"
							className="ca-btn ca-btn--ghost ca-btn--lg"
							onClick={ () => setActiveTab( 'settings' ) }
						>
							{ __( 'Go to Settings', 'coderembassy-checkout-field-editor' ) }
						</button>
					</div>
				</div>
				<span className="ca-hero-card__version">v{ globals?.version || '1.0.0' }</span>
			</div>

			<div className="ca-stats-row">
				<StatCard
					icon="dashicons-editor-ul"
					value={ fields.length }
					label={ __( 'Custom Fields', 'coderembassy-checkout-field-editor' ) }
					color="var(--ca-indigo)"
				/>
				<StatCard
					icon="dashicons-marker"
					value={ enabledFields }
					label={ __( 'Active Fields', 'coderembassy-checkout-field-editor' ) }
					color="var(--ca-green)"
				/>
				<StatCard
					icon="dashicons-groups"
					value={ types.length }
					label={ __( 'Customer Types', 'coderembassy-checkout-field-editor' ) }
					color="var(--ca-violet)"
				/>
			</div>

			<div className="ca-dashboard-grid">
				<div className="ca-card">
					<h3 className="ca-card-title">{ __( 'Quick Actions', 'coderembassy-checkout-field-editor' ) }</h3>
					<div className="ca-quick-actions">
						<QuickAction
							icon="dashicons-plus-alt2"
							label={ __( 'Create a new field', 'coderembassy-checkout-field-editor' ) }
							onClick={ () => { setActiveTab( 'fields' ); setEditingFieldId( 'new' ); } }
						/>
						<QuickAction
							icon="dashicons-groups"
							label={ __( 'Manage customer types', 'coderembassy-checkout-field-editor' ) }
							onClick={ () => setActiveTab( 'types' ) }
						/>
						<QuickAction
							icon="dashicons-layout"
							label={ __( 'Adjust global settings', 'coderembassy-checkout-field-editor' ) }
							onClick={ () => setActiveTab( 'settings' ) }
						/>
					</div>
				</div>

				<div className="ca-card">
					<h3 className="ca-card-title">{ __( 'Getting Started', 'coderembassy-checkout-field-editor' ) }</h3>
					<ol className="ca-getting-started">
						<li>
							<strong>{ __( 'Create custom fields', 'coderembassy-checkout-field-editor' ) }</strong>
							<p>{ __( 'Use the Field Builder to add text, select, checkbox and other input types to your checkout.', 'coderembassy-checkout-field-editor' ) }</p>
						</li>
						<li>
							<strong>{ __( 'Use customer types', 'coderembassy-checkout-field-editor' ) }</strong>
							<p>{ __( 'Offer Private and Company checkout paths, then show different fields for each.', 'coderembassy-checkout-field-editor' ) }</p>
						</li>
						<li>
							<strong>{ __( 'Group fields into sections', 'coderembassy-checkout-field-editor' ) }</strong>
							<p>{ __( 'Organise fields under collapsible sections to keep checkout clean.', 'coderembassy-checkout-field-editor' ) }</p>
						</li>
						<li>
							<strong>{ __( 'Add conditional logic', 'coderembassy-checkout-field-editor' ) }</strong>
							<p>{ __( 'Show or hide fields based on customer type.', 'coderembassy-checkout-field-editor' ) }</p>
						</li>
					</ol>
				</div>
			</div>
		</div>
	);
}
