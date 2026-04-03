import { __ } from '@wordpress/i18n';

const emptyRule = () => ( {
	type: 'customer_type',
	operator: 'in',
	value: [],
} );

const emptyGroup = () => ( {
	logic: 'AND',
	rules: [ emptyRule() ],
} );

function normalize( conditions ) {
	const raw = conditions && typeof conditions === 'object' ? conditions : {};
	const logic = raw.logic === 'OR' ? 'OR' : 'AND';
	let groups = Array.isArray( raw.groups ) ? raw.groups : [];

	groups = groups
		.map( ( g ) => {
			if ( ! g || typeof g !== 'object' ) {
				return null;
			}
			const gl = g.logic === 'OR' ? 'OR' : 'AND';
			const rules = Array.isArray( g.rules )
				? g.rules
						.filter( ( r ) => r && r.type === 'customer_type' )
						.map( ( r ) => ( {
							type: 'customer_type',
							operator: r.operator === 'not_in' ? 'not_in' : 'in',
							value: Array.isArray( r.value )
								? r.value.filter( ( v ) => v === 'private' || v === 'company' )
								: [],
						} ) )
				: [];
			if ( rules.length === 0 ) {
				return { logic: gl, rules: [ emptyRule() ] };
			}
			return { logic: gl, rules };
		} )
		.filter( Boolean );

	return { logic, groups };
}

export default function ConditionEditor( { conditions, onChange } ) {
	const c = normalize( conditions );

	const setWhole = ( next ) => onChange( next );

	const setTopLogic = ( logic ) => setWhole( { ...c, logic } );

	const addGroup = () => setWhole( { ...c, groups: [ ...c.groups, emptyGroup() ] } );

	const removeGroup = ( gi ) => {
		const groups = c.groups.filter( ( _, i ) => i !== gi );
		setWhole( { ...c, groups } );
	};

	const setGroupLogic = ( gi, logic ) => {
		const groups = c.groups.map( ( g, i ) => ( i === gi ? { ...g, logic } : g ) );
		setWhole( { ...c, groups } );
	};

	const addRule = ( gi ) => {
		const groups = c.groups.map( ( g, i ) =>
			i === gi ? { ...g, rules: [ ...g.rules, emptyRule() ] } : g
		);
		setWhole( { ...c, groups } );
	};

	const removeRule = ( gi, ri ) => {
		const groups = c.groups.map( ( g, i ) => {
			if ( i !== gi ) {
				return g;
			}
			const rules = g.rules.filter( ( _, j ) => j !== ri );
			return { ...g, rules: rules.length ? rules : [ emptyRule() ] };
		} );
		setWhole( { ...c, groups } );
	};

	const updateRule = ( gi, ri, patch ) => {
		const groups = c.groups.map( ( g, i ) => {
			if ( i !== gi ) {
				return g;
			}
			const rules = g.rules.map( ( r, j ) =>
				j === ri ? { ...r, ...patch, type: 'customer_type' } : r
			);
			return { ...g, rules };
		} );
		setWhole( { ...c, groups } );
	};

	const toggleValue = ( gi, ri, slug, checked ) => {
		const g = c.groups[ gi ];
		const r = g.rules[ ri ];
		let value = Array.isArray( r.value ) ? [ ...r.value ] : [];
		if ( checked ) {
			if ( ! value.includes( slug ) ) {
				value.push( slug );
			}
		} else {
			value = value.filter( ( v ) => v !== slug );
		}
		updateRule( gi, ri, { value } );
	};

	return (
		<div className="ca-condition-editor">
			<p className="ca-setting-desc">
				{ __( 'Leave empty to always show this field. Add conditions to limit visibility by customer type (Private or Company).', 'coderembassy-checkout-field-editor' ) }
			</p>
			{ c.groups.length === 0 ? (
				<button type="button" className="ca-btn ca-btn--secondary ca-btn--sm" onClick={ addGroup }>
					{ __( 'Add conditions', 'coderembassy-checkout-field-editor' ) }
				</button>
			) : (
				<>
					<div className="ca-field-group" style={ { marginBottom: 12 } }>
						<label>{ __( 'Match groups', 'coderembassy-checkout-field-editor' ) }</label>
						<select
							className="ca-select"
							value={ c.logic }
							onChange={ ( e ) => setTopLogic( e.target.value ) }
							style={ { maxWidth: 280 } }
						>
							<option value="AND">{ __( 'All groups must match (AND)', 'coderembassy-checkout-field-editor' ) }</option>
							<option value="OR">{ __( 'Any group matches (OR)', 'coderembassy-checkout-field-editor' ) }</option>
						</select>
					</div>
					{ c.groups.map( ( group, gi ) => (
						<div key={ gi } className="ca-card" style={ { marginBottom: 12, padding: 12 } }>
							<div
								style={ {
									display: 'flex',
									justifyContent: 'space-between',
									alignItems: 'center',
									marginBottom: 8,
								} }
							>
								<strong>
									{ __( 'Condition group', 'coderembassy-checkout-field-editor' ) } { gi + 1 }
								</strong>
								<button type="button" className="ca-btn ca-btn--danger ca-btn--sm" onClick={ () => removeGroup( gi ) }>
									{ __( 'Remove group', 'coderembassy-checkout-field-editor' ) }
								</button>
							</div>
							<div className="ca-field-group" style={ { marginBottom: 8 } }>
								<label>{ __( 'Rules inside group', 'coderembassy-checkout-field-editor' ) }</label>
								<select
									className="ca-select"
									value={ group.logic }
									onChange={ ( e ) => setGroupLogic( gi, e.target.value ) }
									style={ { maxWidth: 220 } }
								>
									<option value="AND">{ __( 'All rules (AND)', 'coderembassy-checkout-field-editor' ) }</option>
									<option value="OR">{ __( 'Any rule (OR)', 'coderembassy-checkout-field-editor' ) }</option>
								</select>
							</div>
							{ group.rules.map( ( rule, ri ) => (
								<div
									key={ ri }
									style={ {
										borderTop: ri ? '1px solid var(--border)' : undefined,
										paddingTop: ri ? 12 : 0,
										marginTop: ri ? 12 : 0,
									} }
								>
									<div className="ca-card-grid">
										<div className="ca-field-group">
											<label>{ __( 'Operator', 'coderembassy-checkout-field-editor' ) }</label>
											<select
												className="ca-select"
												value={ rule.operator }
												onChange={ ( e ) => updateRule( gi, ri, { operator: e.target.value } ) }
											>
												<option value="in">{ __( 'Is one of', 'coderembassy-checkout-field-editor' ) }</option>
												<option value="not_in">{ __( 'Is not one of', 'coderembassy-checkout-field-editor' ) }</option>
											</select>
										</div>
										<div className="ca-field-group">
											<label>{ __( 'Customer types', 'coderembassy-checkout-field-editor' ) }</label>
											<div className="ca-checkbox-grid">
												{ [
													{ slug: 'private', label: __( 'Private', 'coderembassy-checkout-field-editor' ) },
													{ slug: 'company', label: __( 'Company', 'coderembassy-checkout-field-editor' ) },
												].map( ( opt ) => (
													<label key={ opt.slug } className="ca-checkbox-option">
														<input
															type="checkbox"
															checked={ Array.isArray( rule.value ) && rule.value.includes( opt.slug ) }
															onChange={ ( e ) => toggleValue( gi, ri, opt.slug, e.target.checked ) }
														/>
														<span>{ opt.label }</span>
													</label>
												) ) }
											</div>
										</div>
									</div>
									<div style={ { marginTop: 8 } }>
										{ group.rules.length > 1 && (
											<button
												type="button"
												className="ca-btn ca-btn--danger ca-btn--sm"
												onClick={ () => removeRule( gi, ri ) }
											>
												{ __( 'Remove rule', 'coderembassy-checkout-field-editor' ) }
											</button>
										) }
									</div>
								</div>
							) ) }
							<button type="button" className="ca-btn ca-btn--ghost ca-btn--sm" style={ { marginTop: 12 } } onClick={ () => addRule( gi ) }>
								{ __( 'Add rule', 'coderembassy-checkout-field-editor' ) }
							</button>
						</div>
					) ) }
					<button type="button" className="ca-btn ca-btn--secondary ca-btn--sm" onClick={ addGroup }>
						{ __( 'Add condition group', 'coderembassy-checkout-field-editor' ) }
					</button>
				</>
			) }
		</div>
	);
}
